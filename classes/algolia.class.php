<?php
class algolia {
  private $client;
  private $index;

  public function __construct($index="sources") {
    $this->client = new \AlgoliaSearch\Client(ALGOLIA_APIKEY,ALGOLIA_SECRET);
    $this->index  = $this->client->initIndex($index);
  }

  public function add($objects) {
    $res = $this->index->addObjects($objects);
    return $res;
  }
  public function clear() {
    $this->index->clearIndex();
  }
  public function search($searchText,$api=true) {
    $res = $this->index->search($searchText);
  }

  public function test() {
    $woo = new woocommerce;

    $this->add([
      [
        "name"          => "Test Product",
        "category_id"   => $woo->getCategoryId("Baby"),
        "catalogue_id"  => 1474,
        "image"         => "https://res.cloudinary.com/projectmerlin/image/upload/w_300,h_300,c_pad/64asd6f54as654asdf.png",
        "deepLink"      => "https://stagingrewards.wpengine.com/product/admiral-coconut-cream-400ml/"
        ]
    ]);
  }
}
?>
