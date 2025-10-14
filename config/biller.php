<?php

return [
    'base_url' => env('BILLER_BASE_URL', ''),
    'client_id' => env('BILLER_CLIENT_ID', ''),
    'client_secret' => env('BILLER_CLIENT_SECRET', ''),
    'api_key' => env('BILLER_API_KEY', ''),
    'timeout' => env('BILLER_TIMEOUT', 15),
    'biller_code' => env('BILLER_CODE', ''),
    'service_type' => env('BILLER_SERVICE_TYPE', ''),
    'shared_secret' => env('GATEWAY_SHARED_SECRET', ''),
    'allowed_clock_skew' => env('GATEWAY_ALLOWED_CLOCK_SKEW', 300),
];


