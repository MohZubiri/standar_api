<?php

namespace App\Services\Biller;

class BillerClient
{
    protected $http;
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;
    protected $apiKey;
    protected $timeout;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['base_url'] ?? env('BILLER_BASE_URL', '');
        $this->clientId = $config['client_id'] ?? env('BILLER_CLIENT_ID', '');
        $this->clientSecret = $config['client_secret'] ?? env('BILLER_CLIENT_SECRET', '');
        $this->apiKey = $config['api_key'] ?? env('BILLER_API_KEY', '');
        $this->timeout = (int)($config['timeout'] ?? env('BILLER_TIMEOUT', 15));

        $this->http = new \GuzzleHttp\Client([
            'base_uri' => rtrim((string)$this->baseUrl, '/') . '/',
            'timeout' => $this->timeout,
        ]);
    }

    public function authenticate(): array
    {
        return ['token_type' => 'Bearer', 'access_token' => 'REPLACE_ME'];
    }

    public function signPayload(array $payload): array
    {
        return $payload;
    }

    public function request(string $method, string $uri, array $options = [])
    {
        return $this->http->request($method, ltrim($uri, '/'), $options);
    }
}


