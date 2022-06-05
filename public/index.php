<?php
require __DIR__ . '/../vendor/autoload.php';
//定义基本时间常量
define('CORE_MICRO', microtime(true));
define('CORE_TIME', date('Y-m-d H:i:s'));
define('CORE_DATE', date('Y-m-d'));

$app = require_once __DIR__ . '/../bootstrap/app.php';


$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
