<?php
class xml {
  private $xml;

  public function __construct($filename=null,$html=null,$xml=null) {
    if ($filename)  $this->xml = @simplexml_load_file($filename);
    else if ($html) $this->xml = @simplexml_import_dom(DOMDocument::loadHTML($html));
    else if ($xml)  $this->xml = @simplexml_load_string($xml);
    else return false;
  }

  public function _toArray($xml="") {
    $xml = ($xml) ? $xml:$this->xml;
    $array = json_decode(json_encode((array)$xml),1);
    return $array;
  }
  public function getAttribute($e,$att) {
    $result = $e->attributes()->$att->__toString();
    return $result;
  }
  public function getXpathArray($xpath,$xml=null) {
    $xml = ($xml) ? $xml:$this->xml;
    $result = (array)$xml->xpath($xpath);
    return $result;
  }

}
?>
