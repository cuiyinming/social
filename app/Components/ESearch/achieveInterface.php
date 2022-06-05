<?php

namespace App\Components\ESearch;

interface achieveInterface
{

    const MAX_RESULT_WINDOW = 10000000;

    public function sync(array $dateArr, string $action);

}
