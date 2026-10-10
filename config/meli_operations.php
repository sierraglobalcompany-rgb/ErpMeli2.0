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
    'orders.get' => [
        'method' => 'GET',
        'path' => '/orders/{order_id}',
        'family' => 'orders',
        'classification' => 'READ',
        'official_doc_url' => 'https://developers.mercadolibre.com.co/gestiona-ventas',
        'verified_at' => '2026-10-09',
        'preserve_numbers' => true,
    ],
    'orders.search' => [
        'method' => 'GET',
        'path' => '/orders/search',
        'family' => 'orders',
        'classification' => 'READ',
        'official_doc_url' => 'https://developers.mercadolibre.com.co/gestiona-ventas',
        'verified_at' => '2026-10-09',
    ],
    'billing.period.details' => [
        'method' => 'GET',
        'path' => '/billing/integration/periods/key/{period_key}/group/ML/details',
        'family' => 'billing',
        'classification' => 'READ',
        'official_doc_url' => 'https://developers.mercadolibre.com.co/provisiones',
        'verified_at' => '2026-10-09',
        'preserve_numbers' => true,
    ],
];
