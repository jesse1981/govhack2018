<?php
class datasearch {
  var $version = "v0";

  public function __construct() {

  }

  public function migrate() {
    $a = new algolia;
    $n = new network;

    echo "Initializing...<br/>";

    $start = 0;
    $limit = 1000;

    $base_url = sprintf("http://%s/api/%s/search/datasets",DATASEARCH_HOST,$this->version);
    while (true) {
      $url = sprintf("$base_url?start=%d&limit=%d",$start,$limit);
      $response = json_decode($n->request($url),true);

      foreach ($response["dataSets"] as $d) {
        try {
          $a->add([$d]);
        }
        catch (Exception $e) {
          echo sprintf("Issue with dataset | %s | %s<br/>",$d["title"],$e->getMessage());
        }
      }

      if (count($response["dataSets"]) < $limit) break;
      $start += $limit;
    }

    echo sprintf("Complete! Start Next From %d",($start + count($response["dataSets"])));
  }
}
?>
