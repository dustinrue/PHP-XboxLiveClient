#!/usr/bin/php
<?php
  define("AUTHCACHE", "/tmp/auth_info");
  define("XUIDCACHE", "/tmp/xuid");
  define("EXPIRES", "/tmp/session_expires");
  
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
  $shortopts = "";
  $shortopts .= "g:";
  
  $options = getopt($shortopts);
  
  if (!array_key_exists('g', $options)) {
    printf("pass -g<gamertag>\n");
  }
  $gt = $options['g'];
  
  $doc = $live->fetchXuidForGamertag($options['g']);
  $users[] = $doc;
  $raw = $live->fetchUserPresence($users);
  echo $raw;
  $doc_presence = json_decode($raw);
  
  print_r($doc_presence);
  $state = $doc_presence[0]->state;
  

  if ($state == "Online") {
    $game_info = $doc_presence[0]->devices[0]->titles;
    $game = array_pop($game_info);
    $game_name = $game->name;
    if (array_key_exists('activity', $game))
      $game_extended = $game->activity->richPresence;
    else
      $game_extended = "N/A";
  }
  
  printf("%s is %s\n", $gt, $state);
  if ($state == "Online") {
    printf("Playing: %s - %s\n", $game_name, $game_extended);
  }
  exit;
  
