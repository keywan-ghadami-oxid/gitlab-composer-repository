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

    public function send_401()
    {
        header("need gitlab token", true, 401);
        $e = fopen('php://stderr', 'w');
        fwrite($e, "hello, world!" . PHP_EOL);
        fwrite($e, json_encode($_SERVER));
        exit('X-GITLAB-TOKEN Header missing or not valid');
    }

    public function getAllowedIps() {
        return $this->confs['allowed_client_ips'];
    }

    protected function authByIp() {
        $ips = $this->getAllowedIps();
        if ($ips) {
            if (!isset($_SERVER['REMOTE_ADDR'])){
                return true;
            }
            $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
            if (in_array($REMOTE_ADDR, $ips)) {
                return true;
            }
            return false;
        }
        return true;
    }

    public function auth()
    {
        $confs = $this->confs;

        $token = $this->getBearerToken();
        if (!$token) {
            $this->send_401();
        }

        $this->token = $token;
        $this->confs['api_key'] = $token;
        $client = $this->getClient();
        try {
            $userApi = $client->users();
            $me = $userApi->user();
        } catch (RuntimeException $ex) {
            if ($ex->getCode() == 401) {
                $this->send_401();
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
        $client->authenticate($this->token, Client::AUTH_OAUTH_TOKEN);
        return $client;
    }

    public function setConfig(&$confs){
        $this->confs = &$confs;
    }


    /**
     * Get header Authorization
     * copy right Ngô Văn Thao
     * https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
     * */
    function getAuthorizationHeader(){
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
     * copy right Ngô Văn Thao
     * https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
     * */
    function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }


}
//
//{"REDIRECT_STATUS":"200","HTTP_HOST":"127.0.0.1","HTTP_ACCEPT_ENCODING":"gzip","HTTP_CONNECTION":"close","HTTP_USER_AGENT":"Composer\/1.7.2 (Linux; 4.15.0-33-generic; PHP 7.0.30)","PATH":"\/usr\/local\/sbin:\/usr\/local\/bin:\/usr\/sbin:\/usr\/bin:\/sbin:\/bin","SERVER_SIGNATURE":"<address>Apache\/2.4.18 (Ubuntu) Server at 127.0.0.1 Port 80<\/address>\n","SERVER_SOFTWARE":"Apache\/2.4.18 (Ubuntu)","SERVER_NAME":"127.0.0.1","SERVER_ADDR":"127.0.0.1","SERVER_PORT":"80","REMOTE_ADDR":"127.0.0.1","DOCUMENT_ROOT":"\/home\/keywan\/git\/gitlab-composer\/htdocs","REQUEST_SCHEME":"http","CONTEXT_PREFIX":"","CONTEXT_DOCUMENT_ROOT":"\/home\/keywan\/git\/gitlab-composer\/htdocs","SERVER_ADMIN":"webmaster@localhost","SCRIPT_FILENAME":"\/home\/keywan\/git\/gitlab-composer\/htdocs\/packages.php","REMOTE_PORT":"45218","REDIRECT_URL":"\/packages.json","GATEWAY_INTERFACE":"CGI\/1.1","SERVER_PROTOCOL":"HTTP\/1.1","REQUEST_METHOD":"GET","QUERY_STRING":"","REQUEST_URI":"\/packages.json","SCRIPT_NAME":"\/packages.php","PHP_SELF":"\/packages.php","REQUEST_TIME_FLOAT":1535747988.557,"REQUEST_TIME":1535747988}
//==> /var/log/apache2/access.log <==
//127.0.0.1 - - [31/Aug/2018:22:39:48 +0200] "GET /packages.json HTTP/1.1" 401 219 "-" "Composer/1.7.2 (Linux; 4.15.0-33-generic; PHP 7.0.30)"

