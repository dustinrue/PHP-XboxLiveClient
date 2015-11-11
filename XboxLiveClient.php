<?php
  require_once 'XboxLiveCommunication.php';
  
  class XboxLiveClient extends XboxLiveCommunication {
    
    /**
     * 
     * @param type $xuid
     * @param type $authorization_header
     * @return \XboxLiveClient
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
     * @return \XboxLiveClient
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
    
    /**
     * 
     * @param array array of xuids
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
    public function fetchUserAchievements(&$params = array(), $xuid) {
      if (count($params) == 0) {
        $params = array(
          'orderBy' => 'unlockTime',
          'maxItems' => '600',
        );
      }      
      $param_string = $this->buildParameterString($params);

      // summary - https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=25&orderBy=unlockTime
      // summary for specific titleids - https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=25&titleId=1579532320,1169551644,1649675719,1537894068,858615632,1979014977,301917535&orderBy=unlockTime
      // via activity - https://avty.xboxlive.com/users/xuid(2535455857670853)/activity/People/People/Feed?activityTypes=Achievement&numItems=5&platform=XboxOne&includeSelf=false
      $url = sprintf("https://achievements.xboxlive.com/users/xuid(%s)/achievements?%s",  ($xuid) ? $xuid:$this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * Returns users followed by the user defined in the xuid property
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchFollowedUsers(&$params = array(), $xuid = null) {
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '100',
        );
      }      
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf("https://social.xboxlive.com/users/xuid(%s)/people?%s", ($xuid) ? $xuid:$this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * Returns GameDVR clip information for the user defined in the xuid property
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchGameDVRClips(&$params, $xuid = null) {
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '24',
        );
      }
      $param_string = $this->buildParameterString($params);
      // qualifier? https://gameclipsmetadata.xboxlive.com/public/titles/1512517621/clips?maxItems=50&qualifier=created
      $url = sprintf('https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/clips?%s', ($xuid) ? $xuid:$this->xuid, $param_string);
      return $this->fetchData($url);
    }
    
    /**
     * Returns GameDVR clip information for the user defined in the xuid property
     * @param array $params
     * @return string decodeable json string
     */
    public function fetchGameDVRClip($scid, $clipId, $xuid = null) {
      $url = sprintf("https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/scids/%s/clips/%s", ($xuid) ? $xuid:$this->xuid, $scid, $clipId);
      return $this->fetchData($url);
    }
    
    public function fetchUserGameDVRClipsForGame(&$params, $titleid, $xuid = null) {
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '24',
        );
      }
      $param_string = $this->buildParameterString($params);
      // qualifier? https://gameclipsmetadata.xboxlive.com/public/titles/1512517621/clips?maxItems=50&qualifier=created
      $url = sprintf('https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/titles/%s/clips?%s', ($xuid) ? $xuid:$this->xuid, $titleid, $param_string);
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
      
      $this->setHeader('x-xbl-contract-version', '3');  
      
      return $this->fetchData($url, $json_payload);
    }
    
    public function fetchUserScreenshots(&$params = array(), $xuid = null) {
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '24',
        );
      }
      
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf('https://screenshotsmetadata.xboxlive.com/users/xuid(%s)/screenshots?%s', ($xuid) ? $xuid:$this->xuid, $param_string);
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
      $url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/People/People/Feed?excludeTypes=Played&numItems=50", $this->xuid);
      
      return $this->fetchData($url);
    }
    
    
    public function fetchActivityForUser(&$params = array(), $xuid) {
      // possible contentTypes include
      //   * Game
      //   * App
      if (count($params) == 0) {
        $params = array(
          'excludeTypes' => 'Played',
          'numItems' => '5',
        );
      }      
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/History?%s", $xuid, $param_string);
      
      return $this->fetchData($url);
    }
    public function fetchOwnedGamesAndApps() {
      $url = sprintf("https://eplists.xboxlive.com/users/xuid(%s)/lists/RECN/MultipleLists?listNames=GamesRecents,AppsRecents&filterDeviceType=XboxOne", $this->xuid);
      
      return $this->fetchData($url);
    }
    
    public function fetchPlayedGamesAndApps() {
      $url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/History?contentTypes=Game&activityTypes=Played&numItems=10&platform=XboxOne", $this->xuid);
      
      return $this->fetchData($url);
    }
    
    public function postClipView($xuid, $scid, $clipId) {
      $url = sprintf("https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/scids/%s/clips/%s/views", $xuid, $scid, $clipId);
      
      return $this->fetchData($url, true);
    }
    public function test() {
      //$url = sprintf("https://avty.xboxlive.com/users/xuid(%s)/activity/People/People/Summary/Title?contentTypes=Games&activityTypes=Screenshots&numItems=50&platform=XboxOne&includeSelf=false&startDate=2015-03-126+17-03-02", $this->xuid);
      //$url = "https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=15&orderBy=unlockTime";
      //$url = "https://achievements.xboxlive.com/users/xuid(2535455857670853)/history/titles?skipItems=0&maxItems=25&titleId=247546985&orderBy=unlockTime";
      //$url = sprintf("https://screenshotsmetadata.xboxlive.com/users/xuid(%s)/scids/37770100-f9ae-4b80-9dad-7c1d0ec14469/screenshots/b6be00ec-a7d1-4ed1-a880-954db7ad97ea/view", $this->xuid);
      $url = "https://gameclipsmetadata.xboxlive.com/users/xuid(2533274812719746)/scids/1b180100-2e72-4297-a9e6-b79d5a9771a4/clips/1e9b3dae-5332-4dbf-80f2-bd4a6bd7b9af/views";
      $url = "https://gameclipsmetadata.xboxlive.com/titles/247546985/clips?maxItems=4";
      
      if (count($params) == 0) {
        $params = array(
          'maxItems' => '24',
        );
      }
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf('https://gameclipsmetadata.xboxlive.com/users/xuids/2533274976649935,2535468563302026/titles/247546985/clips?%s', $param_string);
      $url = 'https://avty.xboxlive.com/users/xuid(2535455857670853)/activity/People/People/Summary/User?contentTypes=Game&activityTypes=Played&platform=XboxOne&startDate=2015-04-24%2000%3A00%3A00&titleIds=1512517621';
        
        return $this->fetchData($url);
    }
    
    static public function convertTime($datetime, $timezone = 'America/Chicago') {
      $parsed = date_parse($datetime);

      $datetime = new DateTime();
      $utczone = new DateTimeZone('UTC');
      
      $datetime->setTimezone($utczone);
      $datetime->setDate($parsed['year'], $parsed['month'], $parsed['day']);
      $datetime->setTime($parsed['hour'], $parsed['minute'], $parsed['second']);
      $newzone = new DateTimeZone($timezone); 
      $datetime->setTimezone($newzone);
     
      return $datetime->format('U');
    }
    
    public function fetchRecentPlayers() {
      $url = sprintf("https://social.xboxlive.com/users/xuid(%s)/recentplayers", $this->xuid);
      
      return $this->fetchData($url);
    }
    
    public function fetchStoreInformationForGame($bingId) {
      $url = sprintf("https://eds.xboxlive.com/media/en-US/details?ids=%s&idType=Canonical&mediaItemType=DGame&mediaGroup=GameType&desired=TitleId.ReleaseDate.Description.Images.DeveloperName.PublisherName.ZuneId.AllTimeAverageRating.AllTimeRatingCount.RatingId.UserRatingCount.RatingDescriptors.SlideShows.Genres.Capabilities.HasCompanion.ParentalRatings.IsBundle.BundlePrimaryItemId.IsPartOfAnyBundle&targetDevices=XboxOne&domain=Modern", $bingId);
      $this->setHeader('x-xbl-contract-version', '3.2');
      $this->setHeader('x-xbl-client-type', 'Companion');
      $this->setHeader('Accept-Language', 'en-us');
      $this->setHeader('x-xbl-device-type', 'iPhone');
      //$this->setHeader('Accept-Encoding', 'gzip, deflate');
      //$this->setHeader('User-Agent', 'SmartGlass/2.103.0302 CFNetwork/711.3.18 Darwin/14.0.0');
      $this->setHeader('x-xbl-client-version', '1.0');
      $this->setHeader('Content-Type', '');
      //$this->setHeader('Connection','keep-alive');
      //$this->setHeader('Proxy-Connection', 'keep-alive');
      return $this->fetchData($url);
    }
    
    public function searchStoreForGameTitle($game_title) {
      $url = sprintf("https://eds.xboxlive.com/media/en-US/crossMediaGroupSearch?maxItems=10&q=%s&DesiredMediaItemTypes=DGame.DGameDemo.DApp.DActivity.DConsumable.DDurable.DNativeApp.MusicArtistType.Album.Track.MovieType.TvType&desired=Images.ReleaseDate.Providers.HasCompanion.ParentItems.IsBundle.BundlePrimaryItemId.IsPartOfAnyBundle.AllTimeAverageRating.AllTimeRatingCount&targetDevices=XboxOne&domain=Modern", urlencode($game_title));
      
      $this->setHeader('x-xbl-contract-version', '3.2');
      $this->setHeader('x-xbl-client-type', 'Companion');
      $this->setHeader('Accept-Language', 'en-us');
      $this->setHeader('x-xbl-device-type', 'iPhone');
      //$this->setHeader('Accept-Encoding', 'gzip, deflate');
      //$this->setHeader('User-Agent', 'SmartGlass/2.103.0302 CFNetwork/711.3.18 Darwin/14.0.0');
      $this->setHeader('x-xbl-client-version', '0.0');
      //$this->setHeader('Connection','keep-alive');
      //$this->setHeader('Proxy-Connection', 'keep-alive');
      return $this->fetchData($url);
    }
    
    public function fetchUserGames($xuid = null) {
      if (!$xuid)
        $xuid = $this->xuid;
      
      $url = sprintf("https://eplists.xboxlive.com/users/xuid(%s)/lists/RECN/MultipleLists?listNames=GamesRecents,AppsRecents&filterDeviceType=XboxOne", $xuid);
      
      return $this->fetchData($url);
    }
    
    /*
     * @param string of period separated item ids from fetchUserGames()
     */
    public function fetchGameDetails($itemIds) {
      $url = sprintf("https://eds.xboxlive.com/media/en-US/details?ids=%s&idType=Canonical&fields=all&desiredMediaItemTypes=DGame.DApp.DApp.DApp.DApp.DApp.DApp.DApp.DGame.DApp&targetDevices=XboxOne&domain=Modern", $itemIds);
      
      return $this->fetchData($url);
    }
    
    public function fetchTrendingGameDVR($params = array()) {
      if (count($params) == 0) {
        $params = array(
          'qualifier' => 'created',
          'maxItems' => '5',
        );
      }
      
      $param_string = $this->buildParameterString($params);
      $url = sprintf("https://gameclipsmetadata.xboxlive.com/public/trending/clips?%s", $param_string);
      
      return $this->fetchData($url);
    }
    
    public function fetchTrendingScreenshots($params = array()) {
      if (count($params) == 0) {
        $params = array(
          'qualifier' => 'created',
          'maxItems' => '5',
        );
      }
      
      $param_string = $this->buildParameterString($params);
      $url = sprintf("https://screenshotsmetadata.xboxlive.com/public/trending/screenshot?%s", $param_string);
      
      return $this->fetchData($url);
    }
    
    public function fetchRecentGameDVRForTitle(&$params, $titleId) {
      if (count($params) == 0) {
        $params = array(
          'qualifier' => 'created',
          'maxItems' => '5',
        );
      }
      
      $param_string = $this->buildParameterString($params);
      $url = sprintf("https://gameclipsmetadata.xboxlive.com/public/titles/%s/clips?%s", $titleId, $param_string);
      
      return $this->fetchData($url);
    }
    
    public function fetchRecentScreenshotsForTitle(&$params, $titleId) {
      if (count($params) == 0) {
        $params = array(
          'qualifier' => 'created',
          'maxItems' => '5',
        );
      }
      
      $param_string = $this->buildParameterString($params);
      $url = sprintf("https://screenshotsmetadata.xboxlive.com/public/titles/%s/screenshots/?%s", $titleId, $param_string);
      
      return $this->fetchData($url);
    }
    
    public function fetchRecentActivity(&$params = array(), $xuid) {
      // possible contentTypes include
      //   * Game
      //   * App
      if (count($params) == 0) {
        $params = array(
          'excludeTypes' => 'Played',
          'numItems' => '5',
        );
      }      
      $param_string = $this->buildParameterString($params);
      
      $url = sprintf("https://avty.xboxlive.com/public/activity/People/People/Feed?%s", $param_string);
      
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