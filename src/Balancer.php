<?php

namespace Kinoplenka\Balancer;

use Please\Cache\Drivers\Filesystem;
use Please\Cache\Cache;
use CurlHandle;
use Exception;

class Balancer
{
    protected CurlHandle $httpClient;

    protected Cache $cache;

    public function __construct(protected string $token, protected ?string $endpoint = null)
    {
        $this->createHttpClient();
        $this->createCache();
        $this->resolveEndpoint();
    }

    protected function createHttpClient(): void
    {
        $this->httpClient = curl_init();

        curl_setopt($this->httpClient, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->httpClient, CURLOPT_TIMEOUT, 60);
        curl_setopt($this->httpClient, CURLOPT_HEADER, 0);
        curl_setopt($this->httpClient, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->httpClient, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->httpClient, CURLOPT_USERAGENT, 'Kinoplenka-Parser/1.0');
    }

    protected function createCache(): void
    {
        $driver = new Filesystem(prefix: 'kinoplenka_parser_');

        $this->cache = new Cache($driver);
    }

    protected function resolveEndpoint(): void
    {
        if ($this->endpoint) {
            return;
        }

        $url = 'https://gist.githubusercontent.com/dev-dle/351244dc70855ebe2d13eafca621d298/raw';
        $response = json_decode(file_get_contents($url), true);

        if (!$endpoint = @$response['domains']['api']) {
            throw new Exception('Домен для запросов к API не был получен автоматически');
        }

        $this->endpoint = rtrim(str_replace('/api.', '/api' . time() . '.', $endpoint), '/');
    }

    public function get(string $method, array $parameters = [], int|string|bool $ttl = false): array|bool
    {
        $defaultParameters = [
            'token' => $this->token,
            'limit' => 20,
            'page' => 1,
            'format' => 'json',
        ];

        $query = http_build_query([...$defaultParameters, ...$parameters]);

        $url = $this->endpoint . '/' . $method . '?' . $query;

        $response = $this->execute($url, $ttl);

        return $response;
    }

    public function execute(string $url, int|string|bool $ttl = false): array|bool
    {
        $cacheKey = md5($url);

        if ($ttl !== false && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        curl_setopt($this->httpClient, CURLOPT_URL, $url);

        $body = curl_exec($this->httpClient);

        if ($ttl !== false) {
            $this->cache->set($cacheKey, $body, $ttl);
        }

        if (!$body) {
            throw new Exception('Ошибка запроса к API');
        }

        $response = json_decode($body, true);

        if (isset($response['code']) && isset($response['status'])) {
            throw new Exception('[' . $response['name'] . '] ' . $response['message'], $response['status']);
        }

        return $response;
    }

    public function getCache(): Cache
    {
        return $this->cache;
    }
}
