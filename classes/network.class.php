<?php

$origin   = "";
$referer  = "";
if (php_sapi_name()!="cli") {
  if (array_key_exists('HTTP_HOST',     $_SERVER)) $origin = $_SERVER['HTTP_HOST'];
  if (array_key_exists('HTTP_ORIGIN',   $_SERVER)) $origin = $_SERVER['HTTP_ORIGIN'];
  if (array_key_exists('HTTP_REFERER',  $_SERVER)) {
      $origin = ($origin) ? $origin:$_SERVER['HTTP_REFERER'];
      $referer = $origin;
  }
  else $origin = $_SERVER['REMOTE_ADDR'];
  define('IP',$_SERVER["SERVER_ADDR"],true);
}
define('ORIGIN',$origin,true);
define('REFERER',$referer,true);

class network {
  private function stringifyHeaders() {
    $headers = getallheaders();
    $result = array();
    foreach ($headers as $k=>$v)
      $result[] = $k;

    return implode(",",$result);
  }

  public function getHttpClient($enableCookies=false) {
    $client = new \GuzzleHttp\Client(['cookies' => $enableCookies]);
    return $client;
  }
  public function getHttpRequest($client,$url,$postFields=array(),$headers=array()) {
    $type     = (!$postFields) ? "GET":"POST";
    $options = ['headers'=>$headers];
    if ($type=="POST") $options['form_params'] = $postFields;
    $response = $client->request($type,$url,$options);
    return $response;
  }

  public function enableCOR() {
    header('Access-Control-Allow-Origin: '.ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, DELETE');
    header('Access-Control-Allow-Headers: '.$this->stringifyHeaders());
    header('Access-Control-Allow-Credentials: true');
  }
  public function request($url,$headers=array(),$username="",$password="",$postbody="",$postdata=array(),$type="GET",$cookie="",$insecure=false,$pemfile="") {
    $field_string = "";
    if ($postdata) {
      foreach ($postdata as $k=>$v) {
        if ($field_string) $field_string .= "&";
        $v2 = urlencode($v);
        $field_string .= "$k=$v2";
      }
    }

    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
    if ($headers) curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    if ($username) {
      curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    if ($postbody) {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postbody);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postbody))
      );
    }
    else if ($postdata) {
      curl_setopt($ch,CURLOPT_POST, count($postdata));
      curl_setopt($ch,CURLOPT_POSTFIELDS, $field_string);
    }
    else curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);

    if ($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);

    if ($insecure) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    if ($pemfile) {
      curl_setopt($ch, CURLOPT_SSLCERT, $pemfile);
      curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
    }

    //execute post
    $result = curl_exec($ch);

  	if ($result === FALSE) {
  		printf("cUrl error (#%d): %s | URL: %s<br>\n",
        curl_errno($ch),
  		  htmlspecialchars(curl_error($ch)),
        $url);
      return false;
  	}

    $info = curl_getinfo($ch);

    //close connection
    curl_close($ch);

    return $result;
  }
  public function redirect($url) {
    header("Location: $url");
  }
  public function dieWithCode($code=403,$msg="Unauthorized.") {
    header("HTTP/1.0 $code $msg");
    die();
  }

  public function getLoginCookie($url,$postdata) {
    $field_string = "";
    if ($postdata) {
      foreach ($postdata as $k=>$v) {
        if ($field_string) $field_string .= "&";
        $v2 = urlencode($v);
        $field_string .= "$k=$v2";
      }
    }

    //open connection
    $ch = curl_init();
    $headers = [];

    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    //curl_setopt($ch,CURLOPT_HEADER,1);
    curl_setopt($ch,CURLOPT_POST, count($postdata));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $field_string);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
      function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
          return $len;

        $name = strtolower(trim($header[0]));
        if (!array_key_exists($name, $headers)) $headers[$name] = [trim($header[1])];
        else $headers[$name][] = trim($header[1]);

        return $len;
      }
    );

    //execute post
    $data = curl_exec($ch);
    $JSESSION = (isset($headers["set-cookie"])) ? $headers["set-cookie"][0]:"";
    $JSESSION = explode("; ",$JSESSION);
    $cookie   = (isset($JSESSION[0])) ? substr($JSESSION[0],strlen("JSESSIONID=")):"";
    return $cookie;
  }

  //if (!check_netmask("10.130.0.0/16", $ip)) {
  public function check_netmask($mask, $ip) {
    @list($net, $bits) = explode('/', $mask);
    $bits = isset($bits) ? $bits : 32;
    $bitmask = -pow(2, 32-$bits) & 0x00000000FFFFFFFF;
    $netmask = ip2long($net) & $bitmask;
    $ip_bits = ip2long($ip)  & $bitmask;
    return (($netmask ^ $ip_bits) == 0);
  }
}
?>
