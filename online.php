#!/usr/bin/php
<?php
  define("AUTHCACHE", "/tmp/auth_info");
  define("XUIDCACHE", "/tmp/xuid");
  define("EXPIRES", "/tmp/session_expires");
  
  require_once 'XboxLiveCommunication.php';
  require_once 'XboxLiveClient.php';
  


  if (file_exists(AUTHCACHE) && file_exists(XUIDCACHE)) {
    $xuid = file_get_contents(XUIDCACHE);
    $authorization_header = file_get_contents(AUTHCACHE);
    
    try {
      $live = XboxLiveClient::withCachedCredentials($xuid, $authorization_header);
    }
    catch (Exception $e) {
      printf("Couldn't go with cached creds with message '%s'\n", $e->getMessage());
      exit;
    }
  }
  
 
  //$url = sprintf('https://gameclipsmetadata.xboxlive.com/users/xuid(%s)/clips?continuationToken=abcde_vwxyzMAAAAA2&maxItems=24', '2615706747868666');
  
  //$url = sprintf("https://social.xboxlive.com/users/xuid(%s)/people?maxItems=1000", $live->xuid);
  
  $users_followed = json_decode($live->fetchFollowedUsers());
  
  $user_list = array();

  foreach($users_followed->people AS $user) {
    $user_list[] = $user->xuid;
  }
  
  $raw_user_presense = json_decode($live->fetchUserPresence($user_list));
  $raw_user_details = json_decode($live->fetchUserDetails($user_list));
  

  $user_detail_lookup = array();
  foreach($raw_user_details->profileUsers AS $current_user) {
    foreach($current_user->settings AS $value) {
      $user_detail_lookup[$current_user->id][$value->id] = $value->value;
    }
  }
  
  
  foreach($raw_user_presense AS $current_user) {
    $user_detail_lookup[$current_user->xuid]['state'] = $current_user->state;
    

    if ($current_user->state == "Online") {
      $user_detail_lookup[$current_user->xuid]['type'] = $current_user->devices[0]->type;
      foreach($current_user->devices[0]->titles AS $current_title) {

        if ($current_title->placement != "Background") {
          $current_state = $current_title;
        }
      }
      
      $user_detail_lookup[$current_user->xuid]['title'] = $current_state->name;
      if (array_key_exists('activity', $current_state)) {
        $user_detail_lookup[$current_user->xuid]['richPresence'] = $current_state->activity->richPresence;
      }
    }
    
  }
  
  foreach($user_detail_lookup AS $user) {
    
    if ($user['state'] == "Online") {
      printf("%s: %s", $user['Gamertag'], $user['title']);
      if (array_key_exists('richPresence', $user)) {
        printf(" - %s (%s)\n", $user['richPresence'], $user['type']);
      }
      else {
        printf(" (%s)\n", $user['type']);
      }
    }
  }
  exit;
  //echo sha1($live->authorization_header);
  $continue = 0;
  $params = array();
  $achievements = array();
  do {
    $data = json_decode($live->fetchUserAchievements($params));
    
    
    if (isset($data->pagingInfo->continuationToken)) {
      $params['continuationToken'] = $data->pagingInfo->continuationToken;
      $continue = 1;
    }
    else {
      $continue = 0;
    }
    $achievements = array_merge($achievements, $data->achievements);
    
  } while ($continue);
  
  print_r($achievements);
  
  //printf("%s\n", $live->authorization_header);
  
  // https://gameclipsmetadata.xboxlive.com/users/xuid(2615706747868666)/clips?continuationToken=abcde_vwxyzGAAAAA2&maxItems=24