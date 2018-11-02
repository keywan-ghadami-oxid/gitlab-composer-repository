<?php
namespace GitlabComposer;
require __DIR__ . '/../vendor/autoload.php';

$confs = (new Config())->getConfs();
#forward request to gitlab
$apiEndpoint = $confs['endpoint'];
$mainUrl = substr($apiEndpoint,0,-8);
$tokenEndpoint = $mainUrl . '/oauth/token';


    // use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($_POST)
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($tokenEndpoint, false, $context);
print $result;