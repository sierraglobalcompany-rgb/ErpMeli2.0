<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Transport;

use CurlHandle;
use RuntimeException;

final class CurlMeliTransport implements MeliTransport
{
    public function __construct(
        private readonly RemoteHostPolicy $hostPolicy,
        private readonly string $appEnv,
    ) {
    }

    /** @param array<string,string> $headers */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
    ): MeliTransportResponse {
        $this->hostPolicy->assertAllowed($url, $this->appEnv);

        $handle = curl_init($url);
        if (!$handle instanceof CurlHandle) {
            throw new RuntimeException('Unable to initialize HTTP transport.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');
                if ($separator === false) {
                    return $length;
                }

                $name = strtolower(trim(substr($line, 0, $separator)));
                $value = trim(substr($line, $separator + 1));
                if ($name !== '') {
                    $responseHeaders[$name] = $value;
                }

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            curl_close($handle);
            throw new RuntimeException('Mercado Libre HTTP transport failed.');
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new MeliTransportResponse($status, $responseHeaders, (string) $responseBody);
    }
}
