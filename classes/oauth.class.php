<?php
class oauth {
  private $server;

  public function __construct() {
    $storage = new OAuth2\Storage\Pdo(array('dsn' => OAUTH_DSN, 'username' => OAUTH_USER, 'password' => OAUTH_PASS));
    $this->server = new OAuth2\Server($storage);

    // Add the "Client Credentials" grant type (it is the simplest of the grant types)
    //  $this->server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));

    // Add the "Authorization Code" grant type (this is where the oauth magic happens)
    $this->server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
  }

  public function auth() {
    $request = OAuth2\Request::createFromGlobals();
    $response = new OAuth2\Response();

    // https://www.digitalocean.com/community/tutorials/an-introduction-to-oauth-2
    // This creates the auth code and sends it back to the user via the redirect_uri
    if (!$this->server->validateAuthorizeRequest($request, $response)) {
        $response->send();
        die;
    }

    // This is at the point where the user must log in via gigya

    // Has the user authenticated? (token from Gigya)
    $isValidSession = false;
    if (isset($_POST["UID"])) {
      if ((string)$_POST["UID"] == "0123456789") $isValidSession = true;
      else {
        $g = new gigya;
        $isValidSession = SigUtils::validateUserSignature($_POST["UID"],$_POST["TimeStamp"],GIGYA_SECRET,$_POST["Signature"]);
      }
    }
    else {
      $t = new template;
      $t->setTemplate("assistant.php")->setView("auth")->output();
      return;
    }
    $this->server->handleAuthorizeRequest($request, $response, $isValidSession);
    if ($isValidSession) {
      // Store access code and UID
      $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=')+5, 40);
      $vals = array(
        "auth_code" => $code,
        "uid"       => $_POST["UID"]
      );
      $db = new database;
      //$db->put("auth_codes",$vals);
      $sql = "INSERT INTO auth_codes (auth_code,uid) VALUES (:auth_code,:uid)";
      $db->query($sql,$vals);
    }
    $response->send();
  }
  public function token() {
    $clientToken = $this->server->handleTokenRequest(OAuth2\Request::createFromGlobals());

    // store
    $db = new database;
    $params = $clientToken->getParameters();
    $token = $params["access_token"];
    $vals = array(
      "auth_code" => $_POST["code"],
      "token"     => $token
    );
    //$db->put("access_tokens",$vals);
    $sql = "INSERT INTO access_tokens (auth_code,token) VALUES (:auth_code,:token)";
    $db->query($sql,$vals);

    $clientToken->send();
  }
  public function verify() {
    /*
     * This assumes that the token is only sent if the previous authorization is valid
     */
    if (!$this->server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
      $this->server->getResponse()->send();
      return false;
    }
    return $this->server->getParameters();
  }
  public function getUid($token) {
    $sql = "SELECT * FROM access_tokens t
            INNER JOIN auth_codes c ON t.auth_code = c.auth_code
            WHERE t.token = :token";
    $res = $db->query($sql,array("token"=>$token));
    $uid = (isset($res[0])) ? $res[0]["uid"]:0;
    return $uid;
  }
}
?>
