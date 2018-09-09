<?php
class search {
  public function index() {
    $t = new template;
    $t->setView("search")->output();
  }
}
?>
