<?php
class template {
  private $template = "master.php";
  private $view = "";
  private $data;
  private $title;

  public function loadPartialView($filename="") {
    $api      = (isset($_POST["filename"])) ? true:false;
    $filename = (isset($_POST["filename"])) ? $_POST["filename"]:$filename;
    if (!file_exists($filename)) return "<h1>Error: $filename does not exist.</h1>";
    ob_start();
    include $filename;
    $buffer = ob_get_clean();
    if ($api) {
      $net = new network;
      $net->enableCOR();
      echo $buffer;
    }
    else return $buffer;
  }

  public function setTemplate($name) {
    $this->template = $name;
    return $this;
  }
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }
  public function setView($name,$data="") {
    $this->view = $name;
    if ($data) $this->data = $data;
    return $this;
  }

  public function output($cor=false) {
    if (($this->view) && (file_exists("./views/".$this->view."/index.php")))  include "./views/".$this->view."/index.php";
    if (($this->template) && (file_exists("./templates/".$this->template)))   include "./templates/".$this->template;
  }
  public function resToTable($res,$id="",$appendActions=true) {
    $buffer = "";
    if ($res && isset($res[0])) {
      $buffer = "<table id=\"$id\" name=\"$id\"><thead><tr>";
      // fields
      foreach ($res[0] as $k=>$v)
        if (!is_numeric($k) && $k!="id")
          $buffer .= "<th>".str_replace("_"," ",ucwords($k))."</th>";
      if ($appendActions) {
        $buffer .= "<th>&nbsp;</th>";
        $buffer .= "<th>&nbsp;</th>";
      }
      $buffer .= "</tr></thead><tbody>";
      // rows
      foreach ($res as $row) {
        $id = (isset($row["id"])) ? (int)$row["id"]:"";
        $buffer .= "<tr rel=\"$id\">";

        foreach ($row as $k=>$v)
          if (!is_numeric($k) && $k!="id")
            $buffer .= "<td>$v</td>";

        if ($appendActions) {
          $buffer .= "<td class=\"rowAction\"><button class=\"btn btn-primary\" rel=\"edit\">Edit</button></td>";
          $buffer .= "<td class=\"rowAction\"><button class=\"btn btn-danger\" rel=\"delete\">Delete</button></td>";
        }

        $buffer .= "</tr>";
      }
    }
    else $buffer = "<h3>There are no results for this entry.</h3>";
    return $buffer;
  }
  public function buildObjectForm($type) {
    $db = new database;
    $res = $db->getObject($type,ID);

    $groups = [];
    $lastGroup = "";
    foreach ($res as $r)
      if ($r["group_name"]!=$lastGroup) {
        $groups[$r["group_name"]] = "tab-".$r["group_name"];
        $lastGroup                = $r["group_name"];
      }
    include './templates/_formBuild.php';
  }
  public function getAttOptions($att_id,$source="") {
    $db  = new database;
    if ($source) {
      $arrData  = explode("-",$source);
      $tab = $arrData[0];
      $whe = (isset($arrData[1])) ? "INNER JOIN object_types b ON a.type_id = b.id WHERE b.name = '" . $arrData[1]."'":"";
    }
    else {
      $tab = "att_options";
    }
    $sql = "SELECT a.id,a.name
            FROM $tab a";
    if ($source)  $res = $db->query("$sql $whe ORDER BY a.name");
    else          $res = $db->query("$sql WHERE att_id = :att_id ORDER BY a.name",array("att_id"=>$att_id));

    return $res;
  }
}
?>
