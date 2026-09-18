<?php

return [
    // 'hml' ou 'prod' -- também pode vir de Company::first()->agt_env
    'env' => env('AGT_ENV', 'hml'),

    'endpoints' => [
        'hml' => 'https://sifphml.minfin.gov.ao/sigt/fe/v1',
        'prod' => 'https://sifp.minfin.gov.ao/sigt/fe/v1',
    ],

    'schema_version' => '1.2',

    // Timeout e retries do HTTP client
    'timeout' => env('AGT_HTTP_TIMEOUT', 30),
    'retries' => env('AGT_HTTP_RETRIES', 2),

    // Software de facturação (dados dados no acto de certificação)
    'software' => [
        'product_id' => env('AGT_PRODUCT_ID'),
        'product_version' => env('AGT_PRODUCT_VERSION'),
        'software_validation_number' => env('AGT_SOFTWARE_VALIDATION_NUMBER'),
    ],

    // Credenciais e chaves de teste — usadas como fallback quando os
    // campos correspondentes na tabela `company` estiverem vazios.
    // Facilita testes rápidos de homologação sem precisar popular a BD.
    'tax_registration_number' => env('AGT_NIF'),
    'username' => env('AGT_USERNAME'),
    'password' => env('AGT_PASSWORD'),
    'private_key_path' => env('AGT_PRIVATE_KEY_PATH')
        ? storage_path('app/' . env('AGT_PRIVATE_KEY_PATH'))
        : null,
    'public_key_path' => env('AGT_PUBLIC_KEY_PATH')
        ? storage_path('app/' . env('AGT_PUBLIC_KEY_PATH'))
        : null,
    'software_private_key_path' => env('AGT_SOFTWARE_PRIVATE_KEY_PATH')
        ? storage_path('app/' . env('AGT_SOFTWARE_PRIVATE_KEY_PATH'))
        : null,

    'queue' => env('AGT_QUEUE', 'agt'),

    // Quantas vezes o job de polling tenta obterEstado antes de desistir
    'poll_max_attempts' => 10,
    'poll_backoff_seconds' => 60,
];