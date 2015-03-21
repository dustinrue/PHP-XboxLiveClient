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
    
    public function fetchGamertagForXuid($xuid = null) {
      if (!$xuid) 
        $xuid = $this->xuid;
      
      $user_data = json_decode($this->fetchUserDetails(array($xuid)));
      return ($user_data) ? $user_data->profileUsers[0]->settings[0]->value:null;
    }
    
    /**
     * 
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchUserAchievements(&$params = array()) {
      if (count($params) == 0) {
        $params = array(
          'orderBy' => 'unlockTime',
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
          'maxItems' => '24',
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
          'maxItems' => '24',
        );
      }
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf('https://screenshotsmetadata.xboxlive.com/users/xuid(%s)/screenshots?%s', $this->xuid, $param_string);
      return $this->fetchData($url);
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
      
      return $this->fetchData($url, $json_payload);
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
    
    public function fetchActivity() {
      // possible contentTypes include
      //   * Game
      //   * App
    }
    
    public function fetchOwnedGamesAndApps() {
      $url = sprintf("https://eplists.xboxlive.com/users/xuid(%s)/lists/RECN/MultipleLists?listNames=GamesRecents,AppsRecents&filterDeviceType=XboxOne", $this->xuid);
      
      return $this->fetchData($url);
    }
    
    public function fetchPlayedGamesAndApps() {
      $url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/History?contentTypes=Game&activityTypes=Played&numItems=10&platform=XboxOne", $this->xuid);
      
      return $this->fetchData($url);
    }
    public function test() {
      $url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/People/People/Summary/Title?contentTypes=App&activityTypes=Played&numItems=50&platform=XboxOne&includeSelf=false&startDate=2015-03-13+17-03-02", $this->xuid);
      $url = "https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=15&orderBy=unlockTime";
      //$url = "https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=25&titleId=247546985&orderBy=unlockTime";
      
      return $this->fetchData($url);
    }
    
    /* 
     * Convert settings array to associative array
     * 
     * $user_detail_lookup = array();
  foreach($raw_user_details->profileUsers AS $current_user) {
    foreach($current_user->settings AS $value) {
      $user_detail_lookup[$current_user->id][$value->id] = $value->value;
    }
  }
     */
  }