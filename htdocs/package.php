<?php

namespace GitlabComposer;
require __DIR__ . '/../vendor/autoload.php';
try {
    $confs = (new Config())->getConfs();
    $Cr = new PackageService();
    $Cr->setConfig($confs);
    $Cr->outputFile();
} catch (\Exception $ex) {
    //TODO log exception
    http_response_code(404);
 }
