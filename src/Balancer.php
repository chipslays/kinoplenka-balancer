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

        $this->endpoint = $this->cache->through(function () {
            $url = 'https://gist.githubusercontent.com/dev-dle/351244dc70855ebe2d13eafca621d298/raw';
            $response = json_decode(file_get_contents($url), true);

            if (!$endpoint = @$response['domains']['api']) {
                throw new Exception('Домен для запросов к API не был получен автоматически');
            }

            return rtrim(str_replace('/api.', '/api' . time() . '.', $endpoint), '/');
        }, '1 day');
    }

    public function get(string $method, array $parameters = [], int|string $ttl = '1 hour')
    {
        $defaultParameters = [
            'token' => $this->token,
            'limit' => 20,
            'page' => 1,
            'format' => 'json',
        ];

        $query = http_build_query([...$defaultParameters, ...$parameters]);

        $url = $this->endpoint . '/' . $method . '?' . $query;

        $body = $this->execute($url);

        if (!$body) {
            throw new Exception('Ошибка запроса к API');
        }

        return json_decode($body, true);
    }

    public function execute(string $url, int|string $ttl = '1 hour'): string|bool
    {
        $cacheKey = md5($url);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        curl_setopt($this->httpClient, CURLOPT_URL, $url);

        $body = curl_exec($this->httpClient);

        if (curl_errno($this->httpClient) === 0 && curl_getinfo($this->httpClient, CURLINFO_HTTP_CODE) === 200) {
            return false;
        }

        $this->cache->set($cacheKey, $body, $ttl);

        return $body;
    }
}