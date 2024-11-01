<?php

/**
 * Zlick Payments Plugin
 *
 * @category  Zlick
 * @package   Zlick
 * @author    Arsalan Ahmad <arsalan@zlick.it>
 * @copyright Copyright (c) 2018 Zlick ltd (https://www.zlick.it)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://www.zlick.it
 */

/**
 * Not a reliable method but AFAIK, there is no reliable method of getting user ip
 * Returns IP Address (string) or null
 */
function zlickpay_get_client_ip() {
  return $_SERVER['REMOTE_ADDR'];

  $ipaddress = '';
  if (isset($_SERVER['HTTP_CLIENT_IP']))
      $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
  else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
      $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
  // else if(isset($_SERVER['HTTP_X_FORWARDED']))
  //     $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
  // else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
  //     $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
  // else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
  //     $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
  // else if(isset($_SERVER['HTTP_FORWARDED']))
  //     $ipaddress = $_SERVER['HTTP_FORWARDED'];
  else if(isset($_SERVER['REMOTE_ADDR']))
      $ipaddress = $_SERVER['REMOTE_ADDR'];
  else {
    $ipaddress = '';
  }
  
  $ips = explode(',', $ipaddress);
  // trim, so we can compare against trusted proxies properly
  $ips = array_map('trim', $ips);
  // remove trusted proxy IPs
  // $ips = array_diff($ips, $this->trustedProxies);

  return array_pop($ips);
}

function zlickpay_is_bot_useragent () {
  return (
    isset($_SERVER['HTTP_USER_AGENT'])
    && preg_match('/google|bing/i', $_SERVER['HTTP_USER_AGENT'])
  );
}

function zlickpay_is_search_engine () {

  if (wp_is_json_request()) return false;
  write_log('checking if the useragent belongs to a bot');
  // echo "<!-- checking if the useragent belongs to a bot -->";

  if (!zlickpay_is_bot_useragent()) {
    return false;
  }
  write_log("<!-- looks like this is a bot ". $_SERVER['HTTP_USER_AGENT'] . " -->");
  // echo "<!-- looks like this is a bot ". $_SERVER['HTTP_USER_AGENT'] . " -->";

  $client_ip = zlickpay_get_client_ip();

  $detected_hostname = gethostbyaddr($client_ip);
  write_log("detected hostname $detected_hostname for $client_ip");
  // echo "<!-- detected hostname $detected_hostname for $client_ip -->";

  $ALLOWED_HOSTS = ['googlebot.com', 'google.com', 'search.msn.com'];

  foreach ($ALLOWED_HOSTS as $value) {
    if (stripos($detected_hostname, $value) >= 0) {
      return true;
    }
  }
  return false;
}
