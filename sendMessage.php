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
  
 
  
  $shortopts = "";
  $shortopts .= "g:";
  $shortopts .= "m:";
  
  $options = getopt($shortopts);
  
  if (!array_key_exists('g', $options)) {
    printf("pass -g<gamertag(s)>\nComma separate multiple recipients\n");
    exit;
  }
  
  if (!array_key_exists('m', $options)) {
    printf("pass -m\"<message\"");
    exit;
  }
  $gts = explode(",",$options['g']);
  
  foreach($gts AS $gt) {
    $recipients[] = $live->fetchXuidForGamertag($gt);
  }
  
  
  $message = $options['m'];
  
  //$live->xuid = '2533274868737812';
  $live->sendMessage($recipients, $message);
  