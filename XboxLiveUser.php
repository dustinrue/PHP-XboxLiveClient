<?php
  require_once 'XboxLiveCommunication.php';
  
  class XboxLiveUser extends XboxLiveCommunication {
    
    /**
     * 
     * @param type $xuid
     * @param type $authorization_header
     * @return \XboxLiveUser
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
     * @return \XboxLiveUser
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
    
    /**
     * 
     * @param array $user_list
     * @return string decodeable json string
     */
    public function fetchUserDetails($user_list = array()) {
      if (count($user_list) == 0) {
        $user_list[] = $this->xuid;
      }
       
      return $this->batchFetchUserDetailsWithXuids($user_list);
    }
    
    /**
     * Get the xuid for a given gamertag
     * @param string $gamertag
     * @return string xuid of the given gamertag, null if not found
     */
    public function fetchXuidForGamertag($gamertag) {
      $url = sprintf('https://profile.xboxlive.com/users/gt(%s)/profile/settings', urlencode($gamertag));
      
      $user_data = json_decode($this->fetchData($url));
      
      return ($user_data) ? $user_data->profileUsers[0]->id:null;
    }
    
    /**
     * 
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchUserAchievements(&$params = array()) {
      if (count($params) == 0) {
        $params = array(
          'orderBy' => 'EndingSoon',
          'maxItems' => '600',
        );
      }      
      $param_string = $this->buildParameterString($params);

      $url = sprintf("https://achievements.xboxlive.com/users/xuid(%s)/achievements?%s",  $this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * Returns users followed by the user defined in the xuid property
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchFollowedUsers(&$params = array()) {
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '100',
        );
      }      
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf("https://social.xboxlive.com/users/xuid(%s)/people?%s", $this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * Returns GameDVR clip information for the user defined in the xuid property
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchGameDVRClips(&$params) {
      if (count($params) == 0) {
        $params = array(
          'maxItems=24',
        );
      }
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf('https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/clips?%s', $this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * 
     * @param type $users Array of user xuids to get presence info for
     * @returns string of json that can be decoded
     */
    public function fetchUserPresence($users) {
      $url = "https://userpresence.xboxlive.com/users/batch";
      
      $json_payload = json_encode(array(
        'level' => 'all',
        'users' => $users,
      ));
      
      return $this->request($url, $json_payload);
    }
    
    public function fetchUserScreenshots($params = array()) {
      if (count($params) == 0) {
        $params = array(
          'maxItems=24',
        );
      }
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf('https://screenshotsmetadata.xboxlive.com/users/xuid(%s)/screenshots?%s', $this->xuid, $param_string);
      return $this->request($url);
    }
    
    private function batchFetchUserDetailsWithXuids($user_list) {
      $url = 'https://profile.xboxlive.com/users/batch/profile/settings';
  
      $json_payload = json_encode(array(
        'settings' => array(
          "Gamertag",
          "RealName",
          "Bio",
          "Location",
          "Gamerscore",
          "GameDisplayPicRaw",
          "AccountTier",
          "XboxOneRep",
          "PreferredColor",
        ),
        'userIds' => $user_list,
      ));
      
      return $this->request($url, $json_payload);
    }
    
    public function sendMessage($xuids, $message) {
      $url = sprintf("https://msg.xboxlive.com/users/xuid(%s)/outbox", $this->xuid);
 
      foreach($xuids AS $xuid) {
        $recipients[]['xuid'] = $xuid;
      }
      $json_payload = json_encode(array(
        'header' => array(
          'recipients' => $recipients,
        ),
        'messageText' => $message,
      ));
      
      $this->setHeader('x-xbl-contract-version', '3');
      $this->sendData($url, $json_payload);
    }
    
   
  }