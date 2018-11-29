<?php

namespace GitlabComposer;

use Gitlab\Api\Projects;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\Model\Project;


class PackageService
{
    protected $confs;
    protected $client;
    /**
     * @var $userClient Client
     */
    protected $userClient;


    public function setConfig($confs){
        $this->confs = $confs;
    }

    function notFound(){
        header('HTTP/1.0 404 Not Found');

    }

    /**
     * Output a json file, sending max-age header
     */
    function outputFile()
    {
        $registry = new RegistryBuilder();
        $registry->setConfig($this->confs);
        $packageList = $registry->getPackageList();

        $packageName = $_GET['p'];

        if (!isset($packageList[$packageName])) {
            $this->notFound();
            return;
        }

        $a = new Auth();
        $a->setConfig($this->confs);
        $a->auth();
        $client = $a->getClient();

        $project = $packageList[$packageName];


        $file = __DIR__ . "/../cache/{$project['path_with_namespace']}.json";
        if (! file_exists($file)) {
            $this->notFound();
            return;
        }
        try {
            $tagList = $client->repositories()->tags($project['id']);
        } catch (\Gitlab\Exception\RuntimeException $ex) {
            //for security reason do not tell the client that he is not allowed to access that module
            $this->notFound();
        }
        if (! $tagList){
            //for security reason do not tell the client that there is a module with that name
            $this->notFound();
            return;
        }

        $mtime = filemtime($file);

        header('Content-Type: application/json');
        header('Last-Modified: ' . gmdate('r', $mtime));
        header('Cache-Control: max-age=10');

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) && $since >= $mtime) {
            header('HTTP/1.0 304 Not Modified');
        } else {
            $versions = file_get_contents($file);
            print '{"packages":{"'.$packageName.'":' . $versions . '}}';
        }

    }

    /**
     * @param $confs
     * @return Client
     */
    public function getUserClient()
    {
        return $this->userClient;
    }

    public function setUserClient($client){
        $this->userClient = $client;
    }

}
