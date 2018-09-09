<?php
class home {
  public function __construct() {
    
  }
  public function index() {
    $template = new template;
    $template->setView("dashboard")->output();
  }
}
?>
