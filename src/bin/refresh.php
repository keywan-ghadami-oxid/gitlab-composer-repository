<?php

namespace GitlabComposer;
require __DIR__ . '/../../vendor/autoload.php';
try {
    $confs = (new Config())->getConfs();

    $Cr = new RegistryBuilder();

    $Cr->setConfig($confs);
    $Cr->build();
} catch (\Exception $ex) {
    print $ex;
}