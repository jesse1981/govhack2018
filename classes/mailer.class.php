<?php
use PHPMailer\PHPMailer\PHPMailer;
class mailer {
  public function send($to,$subject,$body,$attachments=array()) {
    //Create a new PHPMailer instance
    $mail = new PHPMailer;
    //Set who the message is to be sent from
    $mail->setFrom(SYSTEM_OWNER_EMAIL, SYSTEM_OWNER_NAME);
    //Set an alternative reply-to address
    $mail->addReplyTo(SYSTEM_OWNER_EMAIL, SYSTEM_OWNER_NAME);
    //Set who the message is to be sent to
    foreach ($to as $k=>$v)
      $mail->addAddress($v, $k); // ["Full Name"] => "email address"
    //Set the subject line
    $mail->Subject = $subject;
    //Read an HTML message body from an external file, convert referenced images to embedded,
    //convert HTML into a basic plain-text alternative body
    $mail->msgHTML($body);

    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';

    //Attach an image file
    foreach ($attachments as $filename)
      $mail->addAttachment($filename);

    //send the message, check for errors
    if (!$mail->send()) {
      echo "Mailer Error: " . $mail->ErrorInfo;
    } else {
      //echo "Message sent!";
    }
  }
  public function validate($api=true) {
    $n = new network;
    $t = new template;

    $keys = array("email");
    foreach ($keys as $k)
      $$k = (isset($_GET[$k])) ? $_GET[$k]:"";

    //set the api key and email to be validated
    //$apiKey = 'Your Secret Key';

    //$emailToValidate = 'example@example.com';

    //$IPToValidate = '99.123.12.122';

    // use curl to make the request
    $url = 'https://api.zerobounce.net/v1/validate?apikey='.ZEROBOUNCE_APIKEY.'&email='.urlencode($email);

    //Uncomment out to use the optional API with IP Lookup
    // $url = 'https://api.zerobounce.net/v1/validatewithip?apikey='.$apiKey.'&email='.urlencode($emailToValidate).'&ipaddress='.urlencode($IPToValidate);

    $ch = curl_init($url);
    //PHP 5.5.19 and higher has support for TLS 1.2
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 150);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if ($api) {
      $n->enableCOR();
      $t->setTemplate("blank.php")->setView("json",$result)->output();
    }
    else return $result;
  }

  public function test() {
    $filename = "/tmp/testEmailAttachment.txt";
    $handle = fopen($filename,'w');
    fwrite($handle,"Data has been written");
    fclose($handle);
    $this->send(
      array("Jesse Bryant"=>"jesse.bryant@gmail.com"),
      "Test Email",
      "Test Body",
      array($filename)
    );
  }
}
?>
