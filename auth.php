#!/usr/bin/php
<?php
  define("AUTHCACHE", "/tmp/auth_info");
  define("XUIDCACHE", "/tmp/xuid");
  define("EXPIRES", "/tmp/session_expires");
  
  require_once 'XboxLiveClient.php';
  
  $shortopts = "";
  $shortopts .= "u:";
  $shortopts .= "p:";
  
  $options = getopt($shortopts);
  
  $username = $options['u'];
  $password = $options['p'];

  if (file_exists(AUTHCACHE) && file_exists(XUIDCACHE)) {
    unlink(AUTHCACHE);
    unlink(XUIDCACHE);
  }
  
  try {
    $live = XboxLiveClient::withUsernameAndPassword($username, $password);
  }
  catch (Exception $e) {
    printf("Couldn't go with u/p with message '%s'\n", $e->getMessage());
    exit;
  }
  file_put_contents(XUIDCACHE, $live->xuid);
  file_put_contents(AUTHCACHE, $live->authorization_header);
