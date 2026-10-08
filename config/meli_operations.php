<?php

declare(strict_types=1);

return [
    'oauth.token' => [
        'method' => 'POST',
        'path' => '/oauth/token',
        'family' => 'oauth',
        'classification' => 'AUTH',
        'official_doc_url' => 'https://developers.mercadolibre.com.co/es_co/autenticacion-y-autorizacion',
        'verified_at' => '2026-10-08',
    ],
    'users.me' => [
        'method' => 'GET',
        'path' => '/users/me',
        'family' => 'users',
        'classification' => 'READ',
        'official_doc_url' => 'https://developers.mercadolibre.com.co/es_co/gestion-de-identidades-y-accesos-oauth-y-tokens',
        'verified_at' => '2026-10-08',
    ],
];
