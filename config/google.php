<?php

declare(strict_types=1);

$enabled = filter_var(env('GOOGLE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

return [
    'enabled' => $enabled,
    'service_account_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH', 'storage/credentials/google-service-account.json'),
    'ca_bundle_path' => env('GOOGLE_CA_BUNDLE_PATH', 'storage/certs/cacert.pem'),
    'scopes' => [
        'https://www.googleapis.com/auth/documents.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
    ],
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
];
