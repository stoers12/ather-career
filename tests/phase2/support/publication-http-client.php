<?php
declare(strict_types=1);
require_once __DIR__.'/completion-fixtures.php';
require_once __DIR__.'/../../../vendor/autoload.php';
$input = json_decode(file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
$client = new GuzzleHttp\Client(['base_uri'=>'http://127.0.0.1:'.$input['port'],
    'cookies'=>GuzzleHttp\Cookie\CookieJar::fromArray(['portfolio_owner_session'=>$input['session']], '127.0.0.1'),
    'http_errors'=>false, 'allow_redirects'=>false, 'timeout'=>18]);
$response = $client->post('/owner_publication.php', ['form_params'=>['csrf_token'=>$input['csrf'], 'action'=>$input['action']]]);
echo json_encode(['status'=>$response->getStatusCode(), 'location'=>$response->getHeaderLine('Location')], JSON_THROW_ON_ERROR);
