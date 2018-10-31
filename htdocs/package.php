<?php

namespace GitlabComposer;
require __DIR__ . '/../vendor/autoload.php';
try {
    $confs = (new Config())->getConfs();
    $a = new Auth();
    $a->setConfig($confs);
    $a->auth();
    $Cr = new PackageService();
    $Cr->setUserClient($a->getClient());
    $Cr->setConfig($confs);
    $Cr->outputFile();
} catch (\Exception $ex) {
    print $ex;
}
