<?php

namespace GitlabComposer;

use Gitlab\Api\Projects;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\Model\Project;


class RegistryBuilder
{
    protected $packages_file = __DIR__ . '/../cache/packages.json';
    protected $static_file = __DIR__ . '/../confs/static-repos.json';

    protected $confs;
    protected $client;

    public function getPackageList() {
        $packages_file = $this->packages_file;
        // Regenerate packages_file is need
        if (!file_exists($packages_file) || filemtime($packages_file) + (1000*60) < time()) {
            $this->build();
        }
        $packageList = json_decode(file_get_contents($this->packages_file), true);
        return $packageList;
    }

    public function build() {
        $packages_file = $this->packages_file;

        $all_projects = $this->loadAllProjects();
        $projects = [];
        foreach ($all_projects as $project) {
            $package = $this->load_data($project);
            if ($package) {
                $package_name = $this->get_package_name($package);
                $projects[$package_name] = [
                    'path_with_namespace' => $project['path_with_namespace'],
                    'name' => $project['name'],
                    'id' => $project['id'] ];
            }
        }

        $data = json_encode($projects,
         JSON_PRETTY_PRINT);

        $res = file_put_contents($packages_file , $data);
        if (! $res) {
            throw new \Exception("I can not write $packages_file");
        }

    }


    public function setConfig($confs){
        $this->confs = $confs;
    }


    /**
     * Retrieves some information about a project's composer.json
     *
     * @param array $project
     * @param string $ref commit id
     * @return array|false
     */
    public function fetch_composer($project, $ref) {
        $repos = $this->repos;

        try {
            $c = $repos->getFile($project['id'], 'composer.json', $ref);

            if (!isset($c['content'])) {
                return false;
            }

            $composer = json_decode(base64_decode($c['content']), true);

            if (empty($composer['name'])) {
                return false; // packages must have a name and must match
            }

            return $composer;
        } catch (RuntimeException $e) {
            //fwrite(STDERR, $e->getMessage() . $project['id'] . $ref);
            return false;
        }
    }

    /**
     * Retrieves some information about a project for a specific ref
     *
     * @param array $project
     * @param array $ref commit id
     * @return array   [$version => ['name' => $name, 'version' => $version, 'source' => [...]]]
     */
    public function fetch_ref($project, $ref) {

        static $ref_cache = [];

        $ref_key = hash('sha384',(serialize($project) . serialize($ref)));

        if (!isset($ref_cache[$ref_key])) {
            if (preg_match('/^v?\d+\.\d+(\.\d+)*(\-(dev|patch|alpha|beta|RC)\d*)?$/', $ref['name'])) {
                $version = $ref['name'];
            } else {
                $version = 'dev-' . $ref['name'];
            }
            if (($data = $this->fetch_composer($project, $ref['commit']['id'])) !== false) {
                $data['uid'] = $ref_key;
                $data['version'] = $version;
                $data['source'] = [
                    'url' => $project[method . '_url_to_repo'],
                    'type' => 'git',
                    'reference' => $ref['commit']['id'],
                ];
                $data['dist'] = [
                    'url' => $this->confs['base_url'] .'dist.php?u='. urlencode($project['path_with_namespace'].'/-/archive/'.$ref['name'].'/'.$project['name'].'-'.$ref['name'].'.zip'),
                    'type' => 'zip',
                    'reference' => $ref['commit']['id'],
                ];

                $ref_cache[$ref_key] = [$version => $data];
            } else {
                $ref_cache[$ref_key] = [];
            }
        }

        return $ref_cache[$ref_key];
    }

    protected $repos;
    /**
     * @var $projects Projects
     */
    protected $projects;


    /**
     * update from a webhook
     */
    public function update() {
        //get post data
        $data = json_decode(file_get_contents('php://input'), true);
        $client = $this->getClient();
        $this->repos = $repos = $client->api('repositories');
        $project = $data['project'];
        $project[method . '_url_to_repo']  = $project[method . '_url'];

        $ref_name = $data['ref'];
        $ref_name = str_replace('refs/tags/','', $ref_name);
        $ref_name = str_replace('refs/heads/','', $ref_name);

        $ref = ['name'=>$ref_name, 'commit' => ['id'=> $data['checkout_sha']]];


        $file = __DIR__ . "/../cache/{$project['path_with_namespace']}.json";
        $datas = json_decode(file_get_contents($file),true);
        foreach ($this->fetch_ref($project, $ref) as $version => $data) {
            $datas[$version] = $data;
        }

        file_put_contents($file,json_encode($datas,JSON_PRETTY_PRINT));
    }

    /**
     * Retrieves some information about a project for all refs
     * @param array $project
     * @return array   Same as $fetch_ref, but for all refs
     */
    protected function fetch_refs($project) {
        $repos = $this->repos;
        $datas = array();
        try {
            foreach (array_merge($repos->branches($project['id']), $repos->tags($project['id'])) as $ref) {
                foreach ($this->fetch_ref($project, $ref) as $version => $data) {
                    $datas[$version] = $data;
                }
            }
        } catch (RuntimeException $e) {
            // The repo has no commits — skipping it.
        }

        return $datas;
    }

    /**
     * Caching layer on top of $fetch_refs
     * Uses last_activity_at from the $project array, so no invalidation is needed
     *
     * @param array $project
     * @return array Same as $fetch_refs
     */
    function load_data($project) {
        $file = __DIR__ . "/../cache/{$project['path_with_namespace']}.json";
        $mtime = strtotime($project['last_activity_at']);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        if (file_exists($file) && filemtime($file) >= $mtime) {
            if (filesize($file) > 0) {
                return json_decode(file_get_contents($file),true);
            } else {
                return false;
            }
        } else {
            $isComposer = $this->isComposerPackage($project);
            $data = $isComposer ? $this->fetch_refs($project) : false;
            if ($data) {
                if ($data) {
                    if ($this->confs['create_webhook']) {
                        $webhook_url = $this->confs['webhook_url'];
                        $id = $project['id'];
                        $allHooks = $this->projects->hooks($id);
                        $hookExists = false;
                        foreach ($allHooks as $hook) {
                            if ($hook['url'] == $webhook_url) {
                                $hookExists = true;
                                break;
                            }
                        }
                        if (!$hookExists) {
                            $arguments['tag_push_events'] = true;
                            if ($this->confs['webhook_token']) {
                                $arguments['token'] = $this->confs['webhook_token'];
                            }
                            $this->projects->addHook($id, $webhook_url, $arguments);
                        }
                    }
                }
                file_put_contents($file, json_encode($data,JSON_PRETTY_PRINT));
                touch($file, $mtime);

                return $data;
            } else {
                $f = fopen($file, 'w');
                fclose($f);
                touch($file, $mtime);

                return false;
            }
        }
    }

    /**
     * Determine the name to use for the package.
     *
     * @param array $project
     * @return array the composerdata
     */
    function isComposerPackage($project) {
        $composerData = $this->getDefaultBranch($project);

        return $this->get_package_name($composerData);
    }

    /**
     * Determine the name to use for the package.
     *
     * @param array $composerData
     * @return string|bool The name of the project
     */
    function get_package_name($composerData) {
        $data = reset($composerData);
        if (!isset($data['name'])) {
            return false;
        }

        return strtolower($data['name']);
    }


    /**
     * @param $confs
     * @return Client
     */
    public function getClient()
    {
        if ($this->client) {
            return $this->client;
        }
        $confs = $this->confs;
        $client = Client::create($confs['endpoint']);
        $client->authenticate($confs['api_key'], Client::AUTH_URL_TOKEN);
        $this->client = $client;
        return $client;
    }

    public function setClient($client){
        $this->client = $client;
    }

    /**
     * @param $project
     * @return mixed
     */
    public function getDefaultBranch($project)
    {
        $ref = $this->fetch_ref($project, $this->repos->branch($project['id'], $project['default_branch']));

        return $ref;
    }

    /**
     * @param $confs
     * @param $groups
     * @param $projects
     * @return array
     */
    protected function loadAllProjects()
    {

        $confs = $this->confs;

        $client = $this->getClient();

        $this->repos = $repos = $client->api('repositories');


        /**
         * @var $projects Projects
         */
        $projects = $client->projects;
        $this->projects = $projects;

        $all_projects = array();
        if (!empty($confs['groups'])) {
            $groups = $client->groups;
            $groupsIterator = new Iterator($groups,'all');
            // We have to get projects from specifics groups
            foreach ($groupsIterator as $group) {
                if (!in_array($group['name'], $confs['groups'], true)) {
                    continue;
                }
                $projectsIterator = new Iterator($groups, 'projects', $group['id']);
                $all_projects = $this->fetchProjects($projectsIterator, $all_projects);
            }
        } else {
            // We have to get all accessible projects
            $projectsIterator = new Iterator($projects,'all');
            $all_projects = $this->fetchProjects($projectsIterator, $all_projects);
        }
        return $all_projects;
    }

    /**
     * @param $projectsIterator
     * @param $all_projects
     * @return array
     */
    protected function fetchProjects($projectsIterator, $all_projects)
    {
        foreach ($projectsIterator as $project) {
            $all_projects[] = $project;
        }
        return $all_projects;
    }

}
