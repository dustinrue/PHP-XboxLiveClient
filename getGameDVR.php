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
 
  
  $screenshot_data = array();
  $continue = true;
  $params = array('maxItems' => '24');
  do {
    $screenshots = json_decode($live->fetchGameDVRClips($params));
    print_r($screenshots);
    exit;
    foreach($screenshots->screenshots AS $screenshot) {
      $screenshot_data[] = $screenshot;      
    }
    
    if (!empty($screenshots->pagingInfo->continuationToken)) {
      $params['continuationToken'] = $screenshots->pagingInfo->continuationToken;
    }
    else {
      $continue = false;
    }
  } while ($continue);
  
  printf("<html>");
  foreach($screenshot_data AS $screenshot) {
    printf("<img src=\"%s\" />\n", $screenshot->screenshotUris[0]->uri);
  }
  printf("</html>");