<?php
/**
 * Created by PhpStorm.
 * User: keywan
 * Date: 30.07.18
 * Time: 08:49
 */

namespace GitlabComposer;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;

class Auth
{
    protected $confs;
    protected $token;
    protected $authType;

    public function send_401($warning)
    {
        header("HTTP/1.0 401 UNAUTHORIZED", true, 401);
        header('Content-Type: application/json');
        print json_encode(["warning"=>$warning]);
        exit();
    }

    public function auth()
    {
        $confs = $this->confs;

        if (!$this->readBearerToken()) {
            if ($_SERVER['PHP_AUTH_USER']) {
                $this->send_401("make sure you have configured gitlab domains" );
            }
            $domain = parse_url($confs['base_url'],PHP_URL_HOST);
            $this->send_401("No token found, make sure you run first composer config gitlab-domains $domain");
        }

        $client = $this->getClient();
        try {
            $userApi = $client->users();
            $me = $userApi->user();
        } catch (RuntimeException $ex) {
            if ($ex->getCode() == 401) {
                $token = $this->getBearerToken();
                $this->send_401("auth failed with token '$token''");
            } else {
                header($ex->getMessage(), true, 500);
            }
        }

    }

    /**
     * @param $confs
     * @return Client
     */
    public function getClient()
    {
        $confs = $this->confs;
        $client = Client::create($confs['endpoint']);
        $client->authenticate($this->token, $this->authType);
        return $client;
    }

    public function setConfig($confs){
        $this->confs = $confs;
    }


    /**
     * Get header Authorization
     * copy right Ngô Văn Thao
     * https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
     * */
    public function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * get access token from header
     */
    public function getBearerToken()
    {
        $this->token;
    }

    /**
     * get access token from header
     * copy right Ngô Văn Thao
     * https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
     * */
    public function readBearerToken() {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                $this->authType = Client::AUTH_OAUTH_TOKEN;
                $this->token = $matches[1];
                return true;
            }
        }
        $privateToken = $_SERVER['HTTP_PRIVATE_TOKEN'];
        if ($privateToken) {
            $this->authType = Client::AUTH_HTTP_TOKEN;
            $this->token = $privateToken;
            return true;
        }

        return false;
    }



}
