<?php

namespace App\Components\ESearch;

use App\Components\ESearch\Business\bioItems;
use App\Components\ESearch\Business\usersItems;
use App\Components\ESearch\Business\discoverItems;
use App\Components\ESearch\Business\tagsItems;

class ESearch
{

    private $source = [];

    public function __construct($source)
    {
        $client = SearchClient::getInstance();
        switch ($source) {
            case 'users:users':
                $this->source[usersItems::TYPE] = new usersItems($client);
                break;
            case 'discover:discover':
                $this->source[discoverItems::TYPE] = new discoverItems($client);
                break;
            case 'tags:tags':
                $this->source[tagsItems::TYPE] = new tagsItems($client);
                break;
            case 'bio:bio':
                $this->source[bioItems::TYPE] = new bioItems($client);
                break;
            default:
                throw new \Exception('err source');
        }
    }

    public function sync(array $dateArr, string $action)
    {
        foreach ($this->source as $source) {
            $source->sync($dateArr, $action);
        }
    }

    public function updateSingle(array $dateArr)
    {
        foreach ($this->source as $source) {
            $source->updateBase($dateArr);
        }
    }

    public function getSingleDoc(array $dateArr)
    {
        foreach ($this->source as $source) {
            return $source->getBaseById($dateArr);
        }
    }

    public function getManyDoc(array $dateArr)
    {
        foreach ($this->source as $source) {
            return $source->mgetDocById($dateArr);
        }
    }

    public function deleteSingle(array $dateArr)
    {
        foreach ($this->source as $source) {
            $source->deleteDocById($dateArr);
        }
    }

    public function search($model)
    {
        return $model->format($this->source);
    }

}
