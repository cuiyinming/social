<?php

namespace App\Components\ESearch;

interface esInterface
{

    public function client();

    public function delete(array $params);

    public function exists(array $params);

    public function createIndices(array $params);

    public function deleteIndices(array $params);

    public function putMapping(array $params);

    public function bulk(array $params);

    public function search(array $params);

    public function getSource(array $params);

    public function mget(array $params);
}
