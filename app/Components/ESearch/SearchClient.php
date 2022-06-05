<?php

namespace App\Components\ESearch;

use Elasticsearch\ClientBuilder;

class SearchClient implements esInterface
{

    private static $client;
    private static $instance;

    public static function getInstance()
    {
        if (!self::$client) {
            $clientBuilder = ClientBuilder::create();
            $clientBuilder->setRetries(3);
            $clientBuilder->setHosts([env('ES_HOST')]);

            self::$client = $clientBuilder->build();
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function client()
    {
        return self::$client;
    }

    public function delete(array $params)
    {
        return $this->client()->delete($params);
    }

    protected function indices()
    {
        return $this->client()->indices();
    }

    public function exists(array $params)
    {
        return $this->indices()->exists($params);
    }

    public function createIndices(array $params)
    {
        return $this->indices()->create($params);
    }

    public function deleteIndices(array $params)
    {
        return $this->indices()->delete($params);
    }

    public function putMapping(array $params)
    {
        return $this->indices()->putMapping($params);
    }

    public function search(array $params)
    {
        return $this->client()->search($params);
    }

    public function bulk(array $params)
    {
        return $this->client()->bulk($params);
    }

    public function getSource(array $params)
    {
        return $this->client()->getSource($params);
    }

    public function mget(array $params)
    {
        return $this->client()->mget($params);
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

}
