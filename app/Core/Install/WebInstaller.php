<?php

declare(strict_types=1);

namespace App\Core\Install;

use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Core\Config\AppConfig;
use App\Core\Config\Environment;
use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use App\Core\Runtime\RuntimePreflight;
use App\Core\Security\Csrf;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class WebInstaller
{
    private const STEPS = ['server','database','application','company','administrator','install'];
    private readonly Installation $installation;

    public function __construct(private readonly string $root)
    {
        $this->installation = new Installation($root);
    }

    /** @return App<ContainerInterface|null> */
    public function app(): App
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Strict']);
            session_start();
        }
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->map(['GET','POST'], '/{path:.*}', $this->handle(...));
        return $app;
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('Cache-Control','no-store')->withHeader('Content-Type','text/html; charset=utf-8')
            ->withHeader('X-Content-Type-Options','nosniff')->withHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $state = $this->installation->state();
        if ($state !== 'fresh') { return $response->withStatus($state === 'installed' ? 404 : 503); }
        if ($request->getUri()->getPath() === '/' && $request->getMethod() === 'GET') {
            return $response->withHeader('Location','/install')->withStatus(303);
        }
        if ($request->getUri()->getPath() !== '/install') { return $response->withStatus(404); }
        if ($request->getUri()->getScheme() !== 'https' && !in_array($request->getUri()->getHost(),['localhost','127.0.0.1'],true)) {
            return $response->withStatus(403);
        }
        $error = ''; $done = false;
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody(); $body = is_array($body) ? $body : [];
            try { (new Csrf())->assertValid($this->input($body,'csrf_token')); }
            catch (\RuntimeException|\InvalidArgumentException) { return $response->withStatus(419); }
            $uri = $request->getUri();
            if ($request->getHeaderLine('Origin') !== $uri->getScheme().'://'.$uri->getAuthority()) { return $response->withStatus(403); }
            try { $done = $this->advance($body); }
            catch (\DomainException) { $error = 'Recuperación requerida: usa una base vacía y dedicada; no se reparará ni borrará automáticamente.'; }
            catch (\InvalidArgumentException $exception) { $error = $exception->getMessage(); }
            catch (\Throwable) { $error = 'No pudimos completar la operación. Revisa los datos y permisos e inténtalo de nuevo.'; }
            if ($error !== '') { $response = $response->withStatus(422); }
        }
        $stage = (int) ($_SESSION['installer']['stage'] ?? 0);
        $step = $done ? 'success' : self::STEPS[$stage];
        $csrf = (new Csrf())->token();
        $checks = (new RuntimePreflight())->installerChecks($this->root);
        /** @var array<string,string> $data */
        $data = $_SESSION['installer']['data'] ?? [];
        $databaseVerified = isset($_SESSION['installer']['db_proof']);
        ob_start(); require __DIR__.'/view.php';
        $response->getBody()->write((string)ob_get_clean());
        return $response;
    }

    /** @param array<mixed> $body */
    private function input(array $body, string $key): string
    {
        $value = $body[$key] ?? '';
        if (!is_string($value) && !is_int($value)) { throw new \InvalidArgumentException('Revisa el formato de los campos.'); }
        return (string)$value;
    }

    /** @param array<mixed> $body */
    private function advance(array $body): bool
    {
        $stage = (int) ($_SESSION['installer']['stage'] ?? 0);
        if ($this->input($body,'step') !== self::STEPS[$stage]) { throw new \InvalidArgumentException('Completa el paso actual antes de continuar.'); }
        $action = $this->input($body,'action');
        if ($action === 'back') {
            $_SESSION['installer']['stage'] = max(0,$stage-1);
            return false;
        }
        /** @var array<string,string> $data */
        $data = $_SESSION['installer']['data'] ?? [];
        if ($stage === 5) { return $this->finalize($data); }
        if ($stage === 1) {
            if ($action === 'edit') { unset($_SESSION['installer']['db_proof']); return false; }
            if ($action === 'continue') {
                $submitted = $data;
                foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
                    if (array_key_exists($key,$body)) { $submitted[$key] = $this->input($body,$key); }
                }
                if (($_SESSION['installer']['db_proof'] ?? '') !== $this->fingerprint($submitted)) {
                    unset($_SESSION['installer']['db_proof']);
                    throw new \InvalidArgumentException('Los datos cambiaron. Vuelve a probar la conexión antes de continuar.');
                }
            } else {
                unset($_SESSION['installer']['db_proof']);
                $data = $this->validate($stage,$body,$data);
                $_SESSION['installer']['data'] = $data;
                $this->probeDatabase($data);
                $_SESSION['installer']['db_proof'] = $this->fingerprint($data);
                return false;
            }
        } else { $data = $this->validate($stage,$body,$data); }
        $_SESSION['installer']['stage'] = $stage+1;
        $_SESSION['installer']['data'] = $data;
        return false;
    }

    /** @param array<mixed> $body
     *  @param array<string,string> $data
     *  @return array<string,string>
     */
    private function validate(int $stage, array $body, array $data): array
    {
        if ($stage === 0) {
            if (in_array(false,(new RuntimePreflight())->installerChecks($this->root),true)) {
                throw new \InvalidArgumentException('El servidor aún no cumple los requisitos. Revisa las comprobaciones indicadas.');
            }
        } elseif ($stage === 1) {
            foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) { $data[$key] = $this->input($body,$key); }
            if (preg_match('/^[a-zA-Z0-9.:-]+$/D',$data['DB_HOST']) !== 1 || $data['DB_USER'] === ''
                || filter_var($data['DB_PORT'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]) === false
                || preg_match('/^[a-zA-Z0-9_]+$/D',$data['DB_NAME']) !== 1) { throw new \InvalidArgumentException('Revisa servidor, puerto, nombre de la base y usuario.'); }
        } elseif ($stage === 2) {
            $data['APP_NAME'] = trim($this->input($body,'APP_NAME'));
            $data['APP_URL'] = rtrim(trim($this->input($body,'APP_URL')),'/');
            if ($data['APP_NAME'] === '' || mb_strlen($data['APP_NAME']) > 160) { throw new \InvalidArgumentException('Escribe un nombre para el ERP de hasta 160 caracteres.'); }
            $data['APP_ENV'] = Installation::urlMode($data['APP_URL']);
            $client = trim($this->input($body,'MELI_CLIENT_ID'));
            $secret = $this->input($body,'MELI_CLIENT_SECRET');
            if ($this->input($body,'keep_meli') === '1' && $client === ($data['MELI_CLIENT_ID'] ?? '') && $secret === '') { $secret = $data['MELI_CLIENT_SECRET'] ?? ''; }
            if (($client === '') !== ($secret === '')) { throw new \InvalidArgumentException('Completa las dos credenciales de Mercado Libre o deja ambas vacías.'); }
            $data['MELI_CLIENT_ID'] = $client; $data['MELI_CLIENT_SECRET'] = $secret;
        } elseif ($stage === 3) {
            $data['company_name'] = trim($this->input($body,'company_name'));
            $data['company_slug'] = trim($this->input($body,'company_slug'));
            if ($data['company_slug'] === '') { $data['company_slug'] = trim((string)preg_replace('/[^a-z0-9]+/','-',strtolower($data['company_name'])),'-'); }
            if ($data['company_name'] === '' || mb_strlen($data['company_name']) > 160 || strlen($data['company_slug']) > 120 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$data['company_slug']) !== 1) {
                throw new \InvalidArgumentException('Revisa el nombre y el identificador: usa letras minúsculas, números y guiones.');
            }
        } elseif ($stage === 4) {
            foreach (['display_name','username','email','password'] as $key) { $data[$key] = $this->input($body,$key); }
            $data['username'] = strtolower(trim($data['username'])); $data['email'] = trim($data['email']);
            if ($data['password'] !== $this->input($body,'confirm_password')) { throw new \InvalidArgumentException('Las contraseñas no coinciden.'); }
            if (trim($data['display_name']) === '' || mb_strlen($data['display_name']) > 191 || preg_match('/^[a-z0-9._-]{3,80}$/D',$data['username']) !== 1
                || filter_var($data['email'],FILTER_VALIDATE_EMAIL) === false || strlen($data['email']) > 191 || strlen($data['password']) < PasswordService::MIN_LENGTH || strlen($data['password']) > 72) {
                throw new \InvalidArgumentException('Revisa nombre, usuario de 3 a 80 caracteres, correo y contraseña de '.PasswordService::MIN_LENGTH.' a 72 bytes.');
            }
        }
        return $data;
    }

    /** @param array<string,string> $data */
    private function fingerprint(array $data): string
    {
        $tuple = [];
        foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) { $tuple[] = $data[$key] ?? ''; }
        return hash_hmac('sha256',json_encode($tuple,JSON_THROW_ON_ERROR),(new Csrf())->token());
    }

    private function requireFresh(\PDO $pdo): void
    {
        if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) { throw new \DomainException('Recovery required.'); }
    }

    /** @param array<string,string> $data */
    private function probeDatabase(array $data): void
    {
        try {
            $pdo = Connection::fromConfig(AppConfig::fromEnvironment($data),false);
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            if (preg_match('/^[0-9]+\.[0-9]+/', $version) !== 1) { throw new \InvalidArgumentException('La base de datos no corresponde a un servidor compatible.'); }
        } catch (\PDOException) { throw new \InvalidArgumentException('No pudimos conectar con la base de datos. Revisa los datos de acceso.'); }
        $this->requireFresh($pdo);
        $table = '__erp_install_probe_'.bin2hex(random_bytes(8)); $created = false;
        try {
            $pdo->exec('CREATE TABLE `'.$table.'` (id INT NOT NULL) ENGINE=InnoDB'); $created = true;
            $pdo->exec('ALTER TABLE `'.$table.'` ADD checked_value INT NULL');
            $pdo->exec('CREATE INDEX probe_index ON `'.$table.'` (id)');
        } catch (\PDOException) { throw new \InvalidArgumentException('El usuario no tiene permisos suficientes para instalar el ERP.'); }
        finally {
            if ($created) {
                try { $pdo->exec('DROP TABLE `'.$table.'`'); }
                catch (\PDOException) { throw new \InvalidArgumentException('No pudimos retirar la prueba de permisos. Revisa la base antes de continuar.'); }
            }
        }
    }

    /** @param array<string,string> $data */
    private function finalize(array $data): bool
    {
        $lock = @fopen($this->installation->privatePath('install.guard'),'c');
        if ($lock === false || !flock($lock,LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            throw new \InvalidArgumentException('Hay otra instalación en curso. Espera antes de volver a intentar.');
        }
        try {
            if ($this->installation->state() !== 'fresh') { throw new \DomainException('Installed.'); }
            for ($stage=0;$stage<5;$stage++) { $data = $this->validate($stage,$data + ['confirm_password'=>$data['password'] ?? ''],$data); }
            $configData = array_intersect_key($data,array_flip(['APP_ENV','APP_NAME','APP_URL','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','MELI_CLIENT_ID','MELI_CLIENT_SECRET']));
            $configData['APP_KEY'] = base64_encode(random_bytes(32));
            $effective = Environment::resolve($configData);
            foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD'] as $key) {
                if ((string)$effective[$key] !== $configData[$key]) { throw new \InvalidArgumentException('La configuración del servidor apunta a otra base. Ajusta esos datos antes de instalar.'); }
            }
            $config = AppConfig::fromEnvironment($effective);
            $pdo = Connection::fromConfig($config);
            $this->requireFresh($pdo);
            if (($_SESSION['installer']['db_proof'] ?? '') !== $this->fingerprint($data)) { throw new \InvalidArgumentException('Vuelve a probar los datos actuales de la base antes de instalar.'); }
            $this->probeDatabase($data);
            $this->installation->atomicWrite('runtime.json',json_encode($configData,JSON_THROW_ON_ERROR));
            (new Migrator())->migrate($pdo,dirname(__DIR__,3).'/database/migrations');
            (new AuthService($pdo))->bootstrapAdmin($data['company_name'],$data['company_slug'],$data['email'],$data['password'],$data['display_name'],$data['username']);
            foreach (['users','companies','company_users'] as $table) {
                if ((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== 1) { throw new \RuntimeException('Authority verification.'); }
            }
            if ($pdo->query('SELECT role FROM company_users')->fetchColumn() !== 'admin' || (int)$pdo->query('SELECT meli_writes_enabled FROM system_settings WHERE id=1')->fetchColumn() !== 0) { throw new \RuntimeException('Unsafe install.'); }
            $this->installation->atomicWrite('installed.lock','installed');
            unset($_SESSION['installer'],$_SESSION['csrf_token']); session_regenerate_id(true);
            return true;
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
}
