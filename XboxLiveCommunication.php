<?php

  if (defined('DRUPAL_ROOT'))
    require_once __DIR__ . '/vendor/autoload.php';
  else
    require_once 'vendor/autoload.php';
  
  class XboxLiveCommunication {
    var $headers;
    var $username;
    var $password;
    var $xuid;
    var $authorization_header;
    var $authentication_data;
    var $ch;
    var $cookiejar;
    var $logger;
    var $sha1;
    var $authentication_expires;
    var $authorization_expires;
    var $authentication_token;
    var $authorization_token;
    var $batch;
    var $batch_items;
    var $url; //last url created
    var $no_cookie_jar;
    static $requests;
    
    public function __construct() {
      $headers = $this->clearHeaders();
      $authentication_data = array();
      
      $this->logger = new Logger();
      $this->logger->level = Logger::error;
      
      $this->cookiejar = new CookieJar();
      
      // setCookieJar will create the curl handle
      $this->setCookieJar(); 
      
    }
    
    /**
     * 
     * @param type $xuid
     * @param type $authorization_header
     * @return \XboxLiveCommunication
     */
    public static function withCachedCredentials($xuid, $authorization_header) {
      $instance = new self();
      $instance->xuid = $xuid;
      $instance->authorization_header = $authorization_header;
      
      $instance->sha1 = sha1(sprintf("%s%s", $instance->xuid, $instance->authorization_header));
      $instance->setCookieJar($instance->sha1);
      return $instance;
    }
    
    /**
     * 
     * @param type $username
     * @param type $password
     * @return \XboxLiveCommunication
     */
    public static function withUsernameAndPassword($username, $password, $authentication_data = null) {
      // for the initial connection generate some temp cookie file
      
      $instance = new self();
      $instance->username = $username;
      $instance->password = $password;
      $instance->authorize($authentication_data);
      
      $instance->sha1 = sha1(sprintf("%s%s", $instance->xuid, $instance->authorization_header));
      $instance->setCookieJar($instance->sha1);
      return $instance;
    }
    
    protected function setCookieJar($xuid = null) {
      // writes out the existing cookiejar file
      // if there is an existing curl handle
      if ($this->ch) {
        curl_close($this->ch);
        $this->ch = null;
      }

      $this->cookiejar->setCookieJar($xuid);
        
      $this->logger->log(sprintf("Setting cookiejar to %s", $this->cookiejar->cookiejar), Logger::debug);
      
      
    }
    
    
    
    public function request($url, $post_data = null, $use_header = 0) {
      if ($this->ch)
        $this->logger->log("Curl handle exists and it's getting overwritten", Logger::debug);
      
      
      $this->ch = curl_init();
      
      if (!$this->no_cookie_jar) {
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookiejar->cookiejar);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookiejar->cookiejar);
      }
      curl_setopt($this->ch, CURLOPT_URL, $url);
      curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->ch, CURLOPT_TIMEOUT, 400);
      $headers = array();
      if (count($this->headers) > 0) {
        foreach($this->headers AS $header_name => $header_value) {
          $this->logger->log(sprintf("Adding header: %s", $header_name), Logger::debug_with_headers);
          $headers[] = sprintf("%s: %s", $header_name, $header_value);
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
      }
      else {
        $this->logger->log("No headers to set", Logger::debug_with_headers);
      }
      
      if ($post_data) {
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_data);
        
        if (is_array($post_data)) {
          curl_setopt($this->ch, CURLOPT_POST, count($post_data));
        }
      }
      
      curl_setopt($this->ch, CURLOPT_VERBOSE, 0);
      curl_setopt($this->ch, CURLOPT_HEADER, $use_header);
      
      $this->logger->log(sprintf("Accessing %s", $url), Logger::debug);
      if ($this->logger->level == Logger::debug_with_headers) {
        foreach($headers AS $header) {
          $this->logger->log(sprintf("    %s", $header), Logger::debug_with_headers);
        }
        $this->logger->log(sprintf("    %s", $post_data));
        $this->logger->log(sprintf(" \n\n"), Logger::debug_with_headers);
      }
      $time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;$time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;
      
      if ($this->batch) {
        // add to a batch list
        $request = new stdClass();
        $request->xlcObject = clone $this;
        $request->url = $url;
        $request->post_data = $post_data;
        $this->batch_items[] = clone $request;
        unset($request);
        $this->url = $url;
        return;
      }
      else {
        // perform the request now
        $results = curl_exec($this->ch);
      }
      
      
      $time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $finish = $time;
      $total_time = round(($finish - $start), 3);
      
      $request = array(
        'request_string' => $url,
        'time_taken' => $total_time,
      );
      XboxLiveCommunication::$requests[] = $request;
      syslog(LOG_ERR, sprintf("Accessing: '%s' took %ss", $url, $total_time));
      //dpm(sprintf("Accessing: '%s' took %ss", $url, $total_time));
      
      if ($this->logger->level == Logger::debug_with_headers) {
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        printf("%s\n\n", curl_exec($this->ch));
      }
      $this->clearHeaders();
      
      if (curl_errno($this->ch) > 0) {
        throw new Exception("Unknown error while communicating with Xbox Live. Xbox Live is taking too long to repond or is currently down");
      }
      //curl_close($this->ch);  
      return $results;
    }
    
    public function performBatch() {
      $client = new GuzzleHttp\Client(array(), array(
        'curl.options' => array(
          'CURLOPT_COOKIEJAR' => $this->cookiejar->cookiejar,
          'CURLOPT_COOKIEFILE' => $this->cookiejar->cookiejar,
        ),
      
      ));
      $time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;$time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;
      // does the batch operations
      $responses = array();
      $requests = array();
      syslog(LOG_ERR, sprintf("Accessing: starting batch"));
      foreach($this->batch_items AS $xlc) {
        $headers = $xlc->xlcObject->headers;
        $url = $xlc->url;
        $post_data = $xlc->post_data;
        
        
        
        if ($post_data) {
          syslog(LOG_ERR, sprintf("Accessing: adding post '%s' to batch", $url));

          $req = $client->createRequest('POST', $url, 
            array(
              'future' => false,
              'debug' => false,
              'body' => $post_data,
            ) 
            );
          
          
        }
        else {
          syslog(LOG_ERR, sprintf("Accessing: adding get '%s' to batch", $url));
          $req = $client->createRequest('GET', $url, 
            array(
              'future' => false,
              'debug' => false,
            ));
          
        }
        
        foreach($headers AS $header => $value) {
          $req->setHeader($header, $value);
        }
        
        
        $requests[] = $req;
        
      }
      $time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;$time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $start = $time;
      
      $results = \GuzzleHttp\Pool::batch($client, $requests);
     
      
      $time = microtime();
      $time = explode(' ', $time);
      $time = $time[1] + $time[0];
      $finish = $time;
      $total_time = round(($finish - $start), 3);
      syslog(LOG_ERR, sprintf("Accessing (batch of %s) took %ss", count($requests), $total_time));
      //dpm(sprintf("Accessing (batch) took %ss", $total_time));
      foreach ($results->getSuccessful() as $response) {
        
        $responses[] = array(
          'body' => $response->getBody(),
          'request' => $response->getEffectiveUrl(),
        );
      }
      
      foreach($results->getFailures() AS $failures) {
      }
      unset($this->batch_items);
      return $responses;
    }
    
    public function setHeader($header_name, $header_value) {
      $this->headers[$header_name] = $header_value;
    }
    
    private function clearHeaders() {
      $this->headers = array();
    }
    
    private function fetchPreAuthData() {
      // remove the old cookie file
      unlink($this->cookiejar->cookiejar);
      $post_vals = http_build_query(array(
        // don't change this client_id unless you know what you're doing
        'client_id' => '0000000048093EE3',
        'redirect_uri' => 'https://login.live.com/oauth20_desktop.srf',
        'response_type' => 'token',
        'display' => 'touch',
        'scope' => 'service::user.auth.xboxlive.com::MBI_SSL',
        'locale' => 'en',
      ));
      $post_vals = urldecode($post_vals);
      
      $preAuthData = $this->request(sprintf("https://login.live.com/oauth20_authorize.srf?%s", $post_vals), null, 1);

      $this->logger->log($preAuthData, Logger::debug_with_headers);
      $match = array();
      preg_match("/urlPost:'([A-Za-z0-9:\?_\-\.&\/=]+)/", $preAuthData, $match);
      
      if (!array_key_exists(1, $match)) {
        $this->logger->log("Unable to fetch pre auth data because urlPost wasn't found", Logger::debug);
        throw new Exception("Unable to fetch pre auth data, urlPost not found");
      }
      $urlPost = $match[1];
      $ppft_re = preg_match("/sFTTag:'.*value=\"(.*)\"\/>'/", $preAuthData, $match);
      
      if (!array_key_exists(1, $match)) {
        $this->logger->log("Unable to fetch pre auth data because sFFTag wasn't found", Logger::debug);
        throw new Exception("Unable to fetch pre auth data, sFFTag not found");
      }
      $ppft_re = $match[1];
      
      $this->authentication_data['urlPost'] = $urlPost;
      $this->authentication_data['ppft_re'] = $ppft_re;
    }
    
    private function fetchInitialAccessToken() {
      $this->fetchPreAuthData();
   
      $post_vals = http_build_query(array(
        'login' => $this->username,
        'passwd' => $this->password,
        'PPFT' => $this->authentication_data['ppft_re'],
        'PPSX' => 'Passpor',
        'SI' => "Sign In",
        'type' => '11',
        'NewUser' => '1',
        'LoginOptions' => '1',
        'i3' => '36728',
        'm1' => '768',
        'm2' => '1184',
        'm3' => '0',
        'i12' => '1',
        'i17' => '0',
        'i18' => '__Login_Host|1',
      ));
      $access_token_results = $this->request($this->authentication_data['urlPost'], $post_vals, 1);
      $this->logger->log($access_token_results, Logger::debug_with_headers);
      preg_match('/Location: (.*)/', $access_token_results, $match);
      if (!array_key_exists(1, $match)) {
        $this->logger->log("Unable to fetch initial token because Location wasn't found", Logger::debug);
        throw new Exception("Unable to fetch initial token, Location not found");
      }
  
      $location_parsed = parse_url($match[1]);
      preg_match('/access_token=(.+?)&/', $location_parsed['fragment'], $match);

      if (!array_key_exists(1, $match)) {
        $this->logger->log("Unable to fetch initial token because access_token wasn't found", Logger::debug);
        throw new Exception("Unable to fetch initial token, access token not found");
      }
      
      $access_token = $match[1];
     
      $this->authentication_data['access_token'] = $access_token;
    }
    
    public function authenticate($access_token = null) {
      
      if (!$access_token) {
        $this->fetchInitialAccessToken();
      }
      else {
        $this->authentication_data['access_token'] = $access_token;
      }
      
      $url = 'https://user.auth.xboxlive.com/user/authenticate';
  
      $payload = array(
        'RelyingParty' => 'http://auth.xboxlive.com',
        'TokenType' => 'JWT',
        'Properties' => array(
          'AuthMethod' => 'RPS',
          'SiteName' => 'user.auth.xboxlive.com',
          'RpsTicket' => $this->authentication_data['access_token'],
        )
      );
      $json_payload = json_encode($payload);
      
      $this->setHeader('Content-Type', 'application/json');
      $this->setHeader('Content-Length', strlen($json_payload));
      
      if ($this->logger->level == Logger::debug_with_headers) {
        $authentication_results = $this->request($url, $json_payload, 1);
        
        $this->logger->log($authentication_results, Logger::debug);
        $this->setHeader('Content-Type', 'application/json');
        $this->setHeader('Content-Length', strlen($json_payload));
      }
      
      
      $authentication_results = $this->request($url, $json_payload);
      
      if (empty($authentication_results)) {  
        $this->logger->log("Unable to authenticate, no data returned", Logger::debug);
        throw new Exception("Unable to authenticate, no data returned");
      }
      
      $user_data = json_decode($authentication_results);

      $this->authentication_data['token'] = $user_data->Token;
      $this->authentication_token = $user_data->Token;
      $this->authentication_expires = $user_data->NotAfter;
      $this->authentication_data['uhs'] = $user_data->DisplayClaims->xui[0]->uhs;
    }
    
    public function authorize($authentication_data = null) {
      if ($authentication_data) {
        $this->authentication_data = $authentication_data;
      }
      else {
        $this->authenticate();
      }
      
      $url = 'https://xsts.auth.xboxlive.com/xsts/authorize';

      $payload = array(
        'RelyingParty' => 'http://xboxlive.com',
        'TokenType' => 'JWT',
        'Properties' => array(
          'UserTokens' => array($this->authentication_data['token']),
          'SandboxId' => 'RETAIL',
        )
      );
      $json_payload = json_encode($payload);
      
      $this->setHeader('Content-Type', 'application/json');
      $this->setHeader('Content-Length', strlen($json_payload));
      
      if ($this->logger->level == Logger::debug_with_headers) {
        $authentication_results = $this->request($url, $json_payload, 1);
        $this->logger->log($authentication_results, Logger::debug);
        $this->setHeader('Content-Type', 'application/json');
        $this->setHeader('Content-Length', strlen($json_payload));
      }
      
      $authorization_data = json_decode($this->request($url, $json_payload));

      if (empty($authorization_data)) {  
        $this->logger->log("Unable to authorize, no data returned", Logger::debug);
        throw new Exception("Unable to authorize, no data returned");
      }
      
      $this->xuid = $authorization_data->DisplayClaims->xui[0]->xid;
      $this->authorization_header = sprintf('XBL3.0 x=%s;%s', $this->authentication_data['uhs'], $authorization_data->Token);
      $this->authorization_expires = $authorization_data->NotAfter;
    }
    
    public function fetchData($url, $json = null) {
      if (empty($this->authorization_header)) {
        throw new Exception("Not authorized");
      }
      $this->setHeader('Authorization', $this->authorization_header);
      
      if ($json)
        $this->setHeader('Content-Type', 'application/json');
      
      $this->setHeader('Accept', 'application/json');
      
      if (!array_key_exists('x-xbl-contract-version', $this->headers))
        $this->setHeader('x-xbl-contract-version', '2');
      
      if (!array_key_exists('User-Agent', $this->headers))
        $this->setHeader('User-Agent', 'XboxRecord.Us Like SmartGlass/2.105.0415 CFNetwork/711.3.18 Darwin/14.0.0');
      
      if ($json) {
        $this->setHeader('Content-Length', strlen($json));
      }
      
      return $this->request($url, $json);
    }
    
    public function buildParameterString($params = array()) {
      
      $output = "";
      foreach($params AS $key => $value) {
        $output .= sprintf("%s=%s&", $key, $value);
      }

      return substr($output, 0, strlen($output) - 1);
    }
    
    public function sendData($url, $json) {
      print_r($this->fetchData($url, $json));
    }
    
    public function requestLog() {
      return $this->requests;
    }
    
    public function batch($is_batch = 0) {
      $this->batch = $is_batch;
    }
  }
  
  
  
  class CookieJar {
    public $cookiejar;
    
    public function setCookieJar($id = null) {
      if (!$id) {
        $this->cookiejar = CookieJar::generateCookieJarName();
      }
      else {
        $cookiejar = CookieJar::generateCookieJarName($id);
        
        // if cookiejar is already defined and the new file doesn't exist
        // then we're tranisitioning from authorization to doing work
        if ($this->cookiejar && !file_exists($cookiejar))
          rename($this->cookiejar, $cookiejar);
        else
          unlink($this->cookiejar);
        
        $this->cookiejar = $cookiejar;
      }
      
    }
    
    private static function generateCookieJarName($id = null) {
      if ($id)
        return sprintf("%s/xbox_live_cookies_%s", realpath('/tmp'), $id);
      else
        return tempnam(realpath('/tmp'), 'xbox_live_cookies_');
    }
  }
  
  class Logger {
    var $level;
    
    const none = 0;
    const error = 1;
    const debug = 2;
    const debug_with_headers = 3;
    

    public function log($message, $level) {
      if (isset($this->level) && $level <= $this->level) {
        printf("%s\n", $message);
        syslog(LOG_ERR, "DERP" . $message);
      }
    }
  }
  
