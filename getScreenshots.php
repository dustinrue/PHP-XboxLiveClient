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
    $screenshots = json_decode($live->fetchUserScreenShots($params));
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
  

  print_r($screenshot_data);
  exit;
  printf("<html>");
  foreach($screenshot_data AS $screenshot) {
    $uri_meta = parse_url($screenshot->screenshotUris[0]->uri);
    printf("<img src=\"%s/%s://%s%s\" />\n", "http://i.fccinteractive.com/unsafe/1280x768",$uri_meta['scheme'], $uri_meta['host'], $uri_meta['path']);
  }
  printf("</html>");