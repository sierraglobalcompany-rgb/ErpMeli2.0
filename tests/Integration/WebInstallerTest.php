<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth\AuthService;
use App\Core\Config\Environment;
use App\Core\Install\Installation;
use App\Core\Install\WebInstaller;
use App\Core\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class WebInstallerTest extends TestCase
{
    private string $root;
    private string $dbName;
    private string|false $originalDb;
    private PDO $server;

    protected function setUp(): void
    {
        TestDatabase::resetSession();
        $config = TestDatabase::config();
        self::assertContains($config->dbHost, ['127.0.0.1','localhost']);
        self::assertStringEndsWith('_test', $config->dbName);
        $this->server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$config->dbHost,$config->dbPort),$config->dbUser,$config->dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->dbName = 'erp_install_' . bin2hex(random_bytes(8)) . '_test';
        $this->server->exec('CREATE DATABASE `'.$this->dbName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->originalDb = getenv('DB_NAME');
        putenv('DB_NAME='.$this->dbName);
        $this->root = sys_get_temp_dir() . '/erp-install-test-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage/private', 0700, true);
        mkdir($this->root . '/vendor');
        touch($this->root . '/vendor/autoload.php');
    }

    protected function tearDown(): void
    {
        $this->server->exec('DROP DATABASE `'.$this->dbName.'`');
        putenv($this->originalDb === false ? 'DB_NAME' : 'DB_NAME='.$this->originalDb);
        foreach (glob($this->root . '/storage/private/*') ?: [] as $file) { unlink($file); }
        rmdir($this->root . '/storage/private');
        rmdir($this->root . '/storage');
        unlink($this->root . '/vendor/autoload.php');
        rmdir($this->root . '/vendor');
        rmdir($this->root);
        $_SESSION = [];
    }

    public function testNamedAdminAuthenticatesByUsernameAndRetainsMembershipContract(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Company', 'company', 'recovery@example.test', 'test-password-long', 'Visible Admin', 'operator');
        self::assertTrue($auth->login('OPERATOR', 'test-password-long'));
        self::assertSame('Visible Admin', $pdo->query('SELECT display_name FROM users')->fetchColumn());
        self::assertFalse($auth->login('recovery@example.test', 'test-password-long'));
    }

    public function testPrivateConfigFallbackAndEnvironmentPrecedence(): void
    {
        $file = $this->root . '/storage/private/runtime.json';
        file_put_contents($file, json_encode(['APP_NAME'=>'Private App', 'DB_HOST'=>'private-host', 'UNKNOWN'=>'ignored']));
        $env = Environment::all($file);
        self::assertSame('Private App', $env['APP_NAME']);
        self::assertSame(getenv('DB_HOST'), $env['DB_HOST']);
        self::assertArrayNotHasKey('UNKNOWN', $env);
    }

    public function testFreshAndCorruptInstalledStatesNeverReopenInstallation(): void
    {
        $install = new Installation($this->root);
        self::assertSame('fresh', $install->state());
        touch($this->root . '/storage/private/installed.lock');
        self::assertSame('invalid', $install->state());
        file_put_contents($this->root . '/storage/private/runtime.json', '{broken');
        self::assertSame('invalid', $install->state());
    }

    public function testInstallerRejectsCsrfAndCrossOriginWithoutMutation(): void
    {
        $app = (new WebInstaller($this->root))->app();
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', 'http://localhost/install')->withParsedBody(['step'=>'server']);
        self::assertSame(419, $app->handle($request)->getStatusCode());
        $request = $request->withHeader('Origin','https://foreign.test')->withParsedBody(['step'=>'server','csrf_token'=>(new Csrf())->token()]);
        self::assertSame(403, $app->handle($request)->getStatusCode());
        self::assertFileDoesNotExist($this->root . '/storage/private/runtime.json');
    }

    public function testDatabaseProbeAndFinalInstallAreOneTimeAndSecretsAreNotEmitted(): void
    {
        $pdo = \App\Core\Database\Connection::fromConfig(TestDatabase::config());
        $before = $pdo->query('SHOW TABLES')->fetchAll();
        $installer = new WebInstaller($this->root);
        $app = $installer->app();
        $factory = new ServerRequestFactory();
        $root = $app->handle($factory->createServerRequest('GET','http://localhost/'));
        self::assertSame('/install', $root->getHeaderLine('Location'));
        $post = function (array $body) use ($app, $factory) {
            return $app->handle($factory->createServerRequest('POST','http://localhost/install')->withHeader('Origin','http://localhost')
                ->withParsedBody($body + ['csrf_token'=>(new Csrf())->token()]));
        };
        self::assertSame(200, $post(['step'=>'server'])->getStatusCode());
        $db = $this->databaseInput();
        $bad = $post(['step'=>'database'] + array_replace($db, ['DB_PASSWORD'=>'synthetic-invalid-password']));
        self::assertSame(422,$bad->getStatusCode());
        self::assertStringNotContainsString('synthetic-invalid-password',(string)$bad->getBody());
        self::assertSame($before, $pdo->query('SHOW TABLES')->fetchAll());
        self::assertSame(200, $post(['step'=>'database']+$db)->getStatusCode());
        self::assertSame($before, $pdo->query('SHOW TABLES')->fetchAll());
        self::assertSame(200, $post(['step'=>'database','action'=>'continue'])->getStatusCode());
        self::assertSame(200,$post(['step'=>'application','APP_NAME'=>'My ERP','APP_URL'=>'https://erp.example.test','MELI_CLIENT_ID'=>'synthetic-client','MELI_CLIENT_SECRET'=>'synthetic-secret'])->getStatusCode());
        $back = $post(['step'=>'company','action'=>'back']);
        self::assertSame(200,$back->getStatusCode());
        self::assertStringNotContainsString('synthetic-secret',(string)$back->getBody());
        self::assertSame(200,$post(['step'=>'application','APP_NAME'=>'My ERP','APP_URL'=>'https://erp.example.test','MELI_CLIENT_ID'=>'synthetic-client','MELI_CLIENT_SECRET'=>'','keep_meli'=>'1'])->getStatusCode());
        self::assertSame(200,$post(['step'=>'company','company_name'=>'Company','company_slug'=>'company'])->getStatusCode());
        $review = $post(['step'=>'administrator','display_name'=>'Main Admin','username'=>'operator','email'=>'recovery@example.test','password'=>'test-password-long','confirm_password'=>'test-password-long']);
        self::assertSame(200,$review->getStatusCode());
        self::assertStringNotContainsString('test-password-long',(string)$review->getBody());
        self::assertStringNotContainsString('synthetic-secret',(string)$review->getBody());
        self::assertFileDoesNotExist($this->root . '/storage/private/installed.lock');
        self::assertSame([], $pdo->query('SHOW TABLES')->fetchAll());
        $guard = fopen($this->root . '/storage/private/install.guard','c');
        flock($guard, LOCK_EX | LOCK_NB);
        self::assertSame(422,$post(['step'=>'install'])->getStatusCode());
        self::assertFileDoesNotExist($this->root . '/storage/private/installed.lock');
        flock($guard, LOCK_UN);
        fclose($guard);
        $oldName = getenv('DB_NAME');
        putenv('DB_NAME=other_disposable_test');
        try {
            self::assertSame(422,$post(['step'=>'install'])->getStatusCode(), 'Conflicting runtime DB environment must not bootstrap another target.');
            self::assertFileDoesNotExist($this->root . '/storage/private/installed.lock');
            self::assertSame([], $pdo->query('SHOW TABLES')->fetchAll());
        } finally { putenv($oldName === false ? 'DB_NAME' : 'DB_NAME='.$oldName); }
        $done = $post(['step'=>'install']);
        self::assertSame(200,$done->getStatusCode());
        self::assertStringContainsString('se instaló correctamente',(string)$done->getBody());
        self::assertStringContainsString('Iniciar sesión',(string)$done->getBody());
        foreach (['companies','users','company_users'] as $table) { self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()); }
        self::assertSame('admin',$pdo->query('SELECT role FROM company_users')->fetchColumn());
        self::assertSame(0,(int)$pdo->query('SELECT meli_writes_enabled FROM system_settings')->fetchColumn());
        self::assertSame('installed',(new Installation($this->root))->state());
        self::assertArrayNotHasKey('installer',$_SESSION);
        self::assertSame(404,$post(['step'=>'install'])->getStatusCode());
        unlink($this->root . '/storage/private/installed.lock');
        $_SESSION['installer'] = ['stage'=>5,'data'=> $db + ['APP_NAME'=>'My ERP','APP_URL'=>'https://erp.example.test','MELI_CLIENT_ID'=>'synthetic-client','MELI_CLIENT_SECRET'=>'synthetic-secret','company_name'=>'Company','company_slug'=>'company','display_name'=>'Main Admin','username'=>'operator','email'=>'recovery@example.test','password'=>'test-password-long']];
        $recovery = $post(['step'=>'install']);
        self::assertSame(422,$recovery->getStatusCode());
        self::assertStringContainsString('Recuperación requerida',(string)$recovery->getBody());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertFileDoesNotExist($this->root . '/storage/private/installed.lock');
    }

    public function testMigrationBackfillsUniqueLegacyUsernameAndDisplayName(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec('ALTER TABLE users DROP INDEX uq_users_username, DROP COLUMN username, DROP COLUMN display_name');
        $pdo->exec("DELETE FROM schema_migrations WHERE version='009_user_identity.sql'");
        $hash = (new \App\Core\Auth\PasswordService())->hash('test-password-long');
        $insert = $pdo->prepare("INSERT INTO users(email,password_hash) VALUES('LEGACY@example.test',?)");
        $insert->execute([$hash]);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Legacy','legacy')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        (new \App\Core\Database\Migrator())->migrate($pdo,dirname(__DIR__,2).'/database/migrations');
        self::assertTrue((new AuthService($pdo))->login('legacy@example.test','test-password-long'));
        self::assertSame('LEGACY@example.test',$pdo->query('SELECT display_name FROM users')->fetchColumn());
        self::assertSame(1,$_SESSION['company_id']);
    }

    private function databaseInput(): array
    {
        return ['DB_HOST'=>(string)getenv('DB_HOST'),'DB_PORT'=>(string)getenv('DB_PORT'),'DB_NAME'=>$this->dbName,'DB_USER'=>(string)getenv('DB_USER'),'DB_PASSWORD'=>(string)getenv('DB_PASSWORD')];
    }

    private function post(array $body): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST','http://localhost/install')->withHeader('Origin','http://localhost')
            ->withParsedBody($body + ['csrf_token'=>(new Csrf())->token()]);
        return (new WebInstaller($this->root))->app()->handle($request);
    }

    public function testHumanProgressBackAndLocalOptionalMeliReview(): void
    {
        $app = (new WebInstaller($this->root))->app();
        $welcome = (string)$app->handle((new ServerRequestFactory())->createServerRequest('GET','http://localhost/install'))->getBody();
        self::assertStringContainsString('Paso 1 de 6',$welcome);
        self::assertStringContainsString('Bienvenido',$welcome);
        self::assertStringNotContainsString('PASS',$welcome);
        self::assertSame(200,$this->post(['step'=>'server'])->getStatusCode());
        $database = $this->post(['step'=>'database']+$this->databaseInput());
        self::assertStringContainsString('Conexión y permisos verificados',(string)$database->getBody());
        self::assertSame(1,$_SESSION['installer']['stage']);
        self::assertSame(200,$this->post(['step'=>'database','action'=>'continue'])->getStatusCode());
        $bad = $this->post(['step'=>'application','APP_NAME'=>'Local ERP','APP_URL'=>'http://erp.example.test','MELI_CLIENT_ID'=>'','MELI_CLIENT_SECRET'=>'']);
        self::assertSame(422,$bad->getStatusCode());
        self::assertStringContainsString('HTTPS',(string)$bad->getBody());
        $one = $this->post(['step'=>'application','APP_NAME'=>'Local ERP','APP_URL'=>'http://localhost:8080','MELI_CLIENT_ID'=>'only-one','MELI_CLIENT_SECRET'=>'']);
        self::assertSame(422,$one->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'application','APP_NAME'=>'Local ERP','APP_URL'=>'http://localhost:8080','MELI_CLIENT_ID'=>'','MELI_CLIENT_SECRET'=>''])->getStatusCode());
        self::assertSame('local',$_SESSION['installer']['data']['APP_ENV']);
        self::assertSame(200,$this->post(['step'=>'company','action'=>'back'])->getStatusCode());
        self::assertSame(2,$_SESSION['installer']['stage']);
        self::assertSame(200,$this->post(['step'=>'application','APP_NAME'=>'Local ERP','APP_URL'=>'http://127.0.0.1:8080','MELI_CLIENT_ID'=>'','MELI_CLIENT_SECRET'=>''])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'company','company_name'=>'Local Company','company_slug'=>''])->getStatusCode());
        $review=$this->post(['step'=>'administrator','display_name'=>'Local Admin','username'=>'local-admin','email'=>'local@example.test','password'=>'test-password-long','confirm_password'=>'test-password-long']);
        self::assertSame(200,$review->getStatusCode());
        $html=(string)$review->getBody();
        self::assertStringContainsString('Mercado Libre: Pendiente',$html);
        self::assertStringNotContainsString('DB_PASSWORD',$html);
        self::assertStringNotContainsString('APP_ENV',$html);
        self::assertStringNotContainsString('test-password-long',$html);
        self::assertSame(200,$this->post(['step'=>'install'])->getStatusCode());
        self::assertSame('installed',(new Installation($this->root))->state());
        $config=json_decode((string)file_get_contents($this->root.'/storage/private/runtime.json'),true);
        self::assertSame('local',$config['APP_ENV']);
        self::assertSame('',$config['MELI_CLIENT_ID']);
        self::assertSame('',$config['MELI_CLIENT_SECRET']);
        self::assertTrue((new AuthService(\App\Core\Database\Connection::fromConfig(TestDatabase::config())))->login('local-admin','test-password-long'));
        $boot=\App\Bootstrap::create();
        self::assertSame(200,$boot->handle((new ServerRequestFactory())->createServerRequest('GET','/login'))->getStatusCode());
        $provider=$boot->handle((new ServerRequestFactory())->createServerRequest('GET','/oauth/mercadolibre/connect'));
        self::assertSame(503,$provider->getStatusCode());
        self::assertStringContainsString('Mercado Libre no está configurado',(string)$provider->getBody());
        $config['DB_USER']='';
        file_put_contents($this->root.'/storage/private/runtime.json',json_encode($config));
        self::assertSame('invalid',(new Installation($this->root))->state());
    }

    public function testChangedDatabaseInputInvalidatesProofAndFinalRejectsResidue(): void
    {
        self::assertSame(200,$this->post(['step'=>'server'])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'database']+$this->databaseInput())->getStatusCode());
        self::assertSame(422,$this->post(['step'=>'database','action'=>'continue','DB_PASSWORD'=>'changed-synthetic'])->getStatusCode());
        self::assertSame(1,$_SESSION['installer']['stage']);
        self::assertSame(422,$this->post(['step'=>'database','action'=>'continue'])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'database']+$this->databaseInput())->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'database','action'=>'continue'])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'application','APP_NAME'=>'ERP','APP_URL'=>'http://localhost','MELI_CLIENT_ID'=>'','MELI_CLIENT_SECRET'=>''])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'company','company_name'=>'Company','company_slug'=>'company'])->getStatusCode());
        self::assertSame(200,$this->post(['step'=>'administrator','display_name'=>'Admin','username'=>'admin','email'=>'admin@example.test','password'=>'test-password-long','confirm_password'=>'test-password-long'])->getStatusCode());
        $pdo=\App\Core\Database\Connection::fromConfig(TestDatabase::config());
        $pdo->exec('CREATE TABLE schema_migrations (version VARCHAR(191) PRIMARY KEY)');
        $pdo->exec('CREATE TABLE companies (id INT PRIMARY KEY)');
        $tables=$pdo->query('SHOW TABLES')->fetchAll();
        $partial=$this->post(['step'=>'install']);
        self::assertSame(422,$partial->getStatusCode());
        self::assertStringContainsString('Recuperación requerida',(string)$partial->getBody());
        self::assertSame($tables,$pdo->query('SHOW TABLES')->fetchAll());
        self::assertFileDoesNotExist($this->root.'/storage/private/installed.lock');
    }

    public function testUnknownDatabaseTablesAreNeverReusedOrDropped(): void
    {
        $pdo=\App\Core\Database\Connection::fromConfig(TestDatabase::config());
        $pdo->exec('CREATE TABLE unrelated_customer_data (id INT PRIMARY KEY)');
        $pdo->exec('INSERT INTO unrelated_customer_data VALUES (7)');
        $this->post(['step'=>'server']);
        $reply=$this->post(['step'=>'database']+$this->databaseInput());
        self::assertSame(422,$reply->getStatusCode());
        self::assertStringContainsString('vacía',(string)$reply->getBody());
        self::assertSame(7,(int)$pdo->query('SELECT id FROM unrelated_customer_data')->fetchColumn());
        self::assertFileDoesNotExist($this->root.'/storage/private/runtime.json');
    }

    public function testReadOnlyDatabaseUserCannotPassInstallabilityProbe(): void
    {
        $user='erp_probe_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(12));
        $account=$this->server->quote($user)."@'%'";
        $this->server->exec('CREATE USER '.$account.' IDENTIFIED BY '.$this->server->quote($password));
        try {
            $this->server->exec('GRANT SELECT ON `'.$this->dbName.'`.* TO '.$account);
            $this->post(['step'=>'server']);
            $reply=$this->post(['step'=>'database']+array_replace($this->databaseInput(),['DB_USER'=>$user,'DB_PASSWORD'=>$password]));
            self::assertSame(422,$reply->getStatusCode());
            self::assertStringContainsString('permisos',(string)$reply->getBody());
            self::assertSame(1,$_SESSION['installer']['stage']);
            self::assertStringNotContainsString($password,(string)$reply->getBody());
            self::assertSame([],\App\Core\Database\Connection::fromConfig(TestDatabase::config())->query('SHOW TABLES')->fetchAll());
        } finally { $this->server->exec('DROP USER '.$account); }
    }

    #[DataProvider('installationUrls')]
    public function testUrlModeRejectsUnsafeInputAndUsesTheSameInstalledGate(string $url, ?string $mode): void
    {
        if ($mode === null) { $this->expectException(\InvalidArgumentException::class); }
        self::assertSame($mode,Installation::urlMode($url));
    }

    public static function installationUrls(): iterable
    {
        yield ['http://localhost:8080','local'];
        yield ['http://127.0.0.1:8080','local'];
        yield ['https://localhost','local'];
        yield ['https://erp.example.test','production'];
        yield ['http://erp.example.test',null];
        yield ['https://user:password@erp.example.test',null];
        yield ['http://localhost?force=1',null];
        yield ['http://localhost#fragment',null];
        yield ['https://invalid_host.example',null];
        yield ['http://localhost:99999',null];
    }
}
