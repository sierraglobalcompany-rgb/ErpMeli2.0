<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PrimaryButtonAnchorGeometryContractTest extends TestCase
{
    public function testSharedPrimaryButtonEstablishesInlineFlexBoxForAnchorGeometry(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );

        self::assertSame(200, $response->getStatusCode());
        $css = (string) preg_replace('/\s+/', ' ', (string) $response->getBody());
        preg_match_all('~(?:^|[{}])\s*button, \.button\s*\{([^{}]*)\}~', $css, $matches);

        self::assertCount(1, $matches[1], 'One authoritative shared primary button rule');
        $rule = $matches[1][0];
        self::assertMatchesRegularExpression('/\bdisplay:\s*inline-flex\s*;/', $rule);
        self::assertMatchesRegularExpression('/\balign-items:\s*center\s*;/', $rule);
        self::assertMatchesRegularExpression('/\bjustify-content:\s*center\s*;/', $rule);
    }
}
