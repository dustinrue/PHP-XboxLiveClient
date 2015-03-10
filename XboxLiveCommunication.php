<?php
  define("COOKIEJAR", "/tmp/xbox_live_cookies");
  define("AUTHCACHE", "/tmp/auth_info");
  define("XUIDCACHE", "/tmp/xuid");
  class XboxLiveCommunication {
    var $headers;
    var $username;
    var $password;
    var $xuid;
    var $authorization_header;
    var $authentication_data;
    
    public function __construct($username, $password, $force = 0) {
      $this->username = $username;
      $this->password = $password;
      $headers = array();
      $authentication_data = array();
      if (file_exists(AUTHCACHE) && !$force) {
        $this->authorization_header = file_get_contents(AUTHCACHE);
        $this->xuid = file_get_contents(XUIDCACHE);
      }
      else {
        $this->authorize();
        file_put_contents(AUTHCACHE, $this->authorization_header);
        file_put_contents(XUIDCACHE, $this->xuid);
      }
    
    }
    
    public function request($url, $post_data = null, $use_header = 0) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIEJAR);
      curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIEJAR);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      
      $headers = array();
      if (count($this->headers) > 0) {
        foreach($this->headers AS $header_name => $header_value) {
          $headers[] = sprintf("%s: %s", $header_name, $header_value);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      }
      
      if ($post_data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        
        if (is_array($post_data)) {
          curl_setopt($ch, CURLOPT_POST, count($post_data));
        }
      }
      
      curl_setopt($ch, CURLOPT_VERBOSE, 0);
      curl_setopt($ch, CURLOPT_HEADER, $use_header);
      
      //printf("Accessing %s\n", $url);
      $results = curl_exec($ch);
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
      unlink(COOKIEJAR);
      $post_vals = http_build_query(array(
        'client_id' => '0000000048093EE3',
        'redirect_uri' => 'https://login.live.com/oauth20_desktop.srf',
        'response_type' => 'token',
        'display' => 'touch',
        'scope' => 'service::user.auth.xboxlive.com::MBI_SSL',
        'locale' => 'en',
      ));
      $post_vals = urldecode($post_vals);
      
      $preAuthData = $this->request(sprintf("https://login.live.com/oauth20_authorize.srf?%s", $post_vals), null, 1);
      
      $match = array();
      preg_match("/urlPost:'([A-Za-z0-9:\?_\-\.&\/=]+)/", $preAuthData, $match);
      $urlPost = $match[1];
      $ppft_re = preg_match("/sFTTag:'.*value=\"(.*)\"\/>'/", $preAuthData, $match);
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
      preg_match('/Location: (.*)/', $access_token_results, $match);
  
      $location_parsed = parse_url($match[1]);
      $qs_exploded = explode('&', $location_parsed['fragment']);
      preg_match('/access_token=(.+?)&/', $location_parsed['fragment'], $match);

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
      
      $authentication_results = $this->request($url, $json_payload);
      $user_data = json_decode($authentication_results);

      $this->authentication_data['token'] = $user_data->Token;
      $this->authentication_data['uhs'] = $user_data->DisplayClaims->xui[0]->uhs;
    }
    
    private function authorize() {
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
      
      $authorization_data = json_decode($this->request($url, $json_payload));

      $this->xuid = $authorization_data->DisplayClaims->xui[0]->xid;
      $this->authorization_header = sprintf('XBL3.0 x=%s;%s', $this->authentication_data['uhs'], $authorization_data->Token);
    }
    
    public function fetchData($url, $json = null) {
      $this->setHeader('Authorization', $this->authorization_header);
      $this->setHeader('Content-Type', 'application/json');
      $this->setHeader('Accept', 'application/json');
      $this->setHeader('x-xbl-contract-version', '2');
      
      if ($json) {
        $this->setHeader('Content-Length', strlen($json));
      }
      
      return $this->request($url, $json);
    }
  }