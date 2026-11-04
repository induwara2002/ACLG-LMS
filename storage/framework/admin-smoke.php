<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::withoutGlobalScopes()->findOrFail(65);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

foreach (['/admin/dashboard', '/admin/members', '/admin/books'] as $path) {
    Illuminate\Support\Facades\Auth::guard()->setUser($user);
    $request = Illuminate\Http\Request::create('http://127.0.0.1'.$path, 'GET');
    $response = $kernel->handle($request);
    echo $path.' HTTP '.$response->getStatusCode().PHP_EOL;
    $kernel->terminate($request, $response);
}