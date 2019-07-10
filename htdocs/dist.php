<?php
/**
 * This Software is the property of OXID eSales and is protected
 * by copyright law - it is NOT Freeware.
 *
 * Any unauthorized use of this software without a valid license key
 * is a violation of the license agreement and will be prosecuted by
 * civil and criminal law.
 *
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG
 * Created at 11/7/18 6:27 PM by Keywan Ghadami
 */

namespace GitlabComposer;
require __DIR__ . '/../vendor/autoload.php';
$confs = (new Config())->getConfs();
$auth = new Auth();
$auth->setConfig($confs);
$auth->auth();
$token = $auth->getBearerToken();
$url = $_GET['u'];

if ($url) {
    $parts =  explode('/-',$url);
    $path_with_namespace = $parts[0];
    $ref = explode('-', $parts[1]);
    $ref = substr($ref[1],0,-4);
    $registry = new RegistryBuilder();
    $registry->setConfig($confs);
    $packageList = $registry->getPackageList();
    foreach ($packageList as $package) {
        if ($package['path_with_namespace'] == $path_with_namespace) {
            $id = $package['id'];
            break;
        }
    }
} else {
    $id = $_GET['id'];
    $ref = $_GET['ref'];
}
$url = 'projects/' . $id .'/repository/archive.zip?sha='. $ref;
$target = $confs['endpoint'] . $url;


//GET /projects/:id/repository/archive[.format]
// /projects/281/repository/archive.zip
//https://psgit.oxid-esales.com/api/v4/projects/281/repository/archive.zip

// use key 'http' even if you send the request to https://...
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nAuthorization: Bearer $token\r\n",
        'method'  => 'GET'
    )
);

$context  = stream_context_create($options);

// stream the file
$fp = fopen($target, 'rb', false, $context);

if (isset($http_response_header)) {
    $a_header = $http_response_header;
    if (strpos($a_header[0],'200') === false){
        http_response_code(500);
        exit();
    }
}

header("Content-Type: application/zip");

fpassthru($fp);