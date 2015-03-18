<?php
  
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
    var $authorization_expires;
    
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
    public static function withUsernameAndPassword($username, $password) {
      // for the initial connection generate some temp cookie file
      
      $instance = new self();
      $instance->username = $username;
      $instance->password = $password;
      $instance->authorize();
      
      $instance->sha1 = sha1(sprintf("%s%s", $instance->xuid, $instance->authorization_header));
      $instance->setCookieJar($instance->sha1);
      return $instance;
    }
    
    protected function setCookieJar($xuid = null) {
      // writes out the existing cookiejar file
      // if there is an existing curl handle
      if ($this->ch)
        curl_close($this->ch);

      $this->cookiejar->setCookieJar($xuid);
        
      $this->logger->log(sprintf("Setting cookiejar to %s", $this->cookiejar->cookiejar), Logger::debug);
      
      $this->ch = curl_init();
      curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookiejar->cookiejar);
      curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookiejar->cookiejar);
    }
    
    
    
    public function request($url, $post_data = null, $use_header = 0) {
      
      curl_setopt($this->ch, CURLOPT_URL, $url);
      curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
      
      $headers = array();
      if (count($this->headers) > 0) {
        foreach($this->headers AS $header_name => $header_value) {
          $headers[] = sprintf("%s: %s", $header_name, $header_value);
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
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
      $results = curl_exec($this->ch);
      $this->clearHeaders();
      return $results;
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

      $this->logger->log($preAuthData, Logger::debug);
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

      $access_token_results = $this->request($this->authentication_data['urlPost'], $post_vals, true);
      $this->logger->log($access_token_results, Logger::debug);
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
    
    private function authenticate() {
      $this->fetchInitialAccessToken();
      
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
      
      if ($this->logger->level == Logger::debug) {
        $authentication_results = $this->request($url, $json_payload, 1);
        $this->logger->log($authentication_results, Logger::debug);
        
      }
      $authentication_results = $this->request($url, $json_payload);
      
      if (empty($authentication_results)) {  
        $this->logger->log("Unable to authenticate, no data returned", Logger::debug);
        throw new Exception("Unable to authenticate, no data returned");
      }
      
      $user_data = json_decode($authentication_results);

      $this->authentication_data['token'] = $user_data->Token;
      $this->authentication_data['uhs'] = $user_data->DisplayClaims->xui[0]->uhs;
    }
    
    protected function authorize() {
      $this->authenticate();
      
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
      
      if ($this->logger->level == Logger::debug) {
        $authentication_results = $this->request($url, $json_payload, 1);
        $this->logger->log($authentication_results, Logger::debug);
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
      $this->setHeader('Content-Type', 'application/json');
      $this->setHeader('Accept', 'application/json');
      $this->setHeader('x-xbl-contract-version', '2');
      
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
    

    public function log($message, $level) {
      if (isset($this->level) && $level <= $this->level) {
        printf("%s\n", $message);
      }
    }
  }
  