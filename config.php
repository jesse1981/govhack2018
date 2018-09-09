<?php
// change directory if not at script root already
chdir(dirname(__FILE__));
// Session Cache Limiter
session_cache_limiter("nocache");
// Set UTC Date/Time Zone
date_default_timezone_set('Australia/Sydney');
// SPL Autoload Register
spl_autoload_register(function ($class) {
  $file = 'classes/' . $class . '.class.php';
  if (file_exists($file)) require $file;
  else return;
});

$module = (isset($_GET["module"]) && !empty($_GET["module"]))  ? $_GET["module"]:"home";
$action = (isset($_GET["action"]))  ? $_GET["action"]:"index";
$id     = (isset($_GET["id"]))      ? $_GET["id"]:0;
$format = (isset($_GET["format"]))  ? $_GET["format"]:"";
foreach (array("module","action","id","format") as $k) define(strtoupper($k),$$k,true);

$settings = parse_ini_file('.env',true);
foreach ($settings as $group=>$arr) {
    foreach ($arr as $k=>$v) {
        define(strtoupper("$group"."_"."$k"),$v,true);
    }
}

// Load Composer Dependencies
require_once 'vendor/autoload.php';

// Load global functions
require_once 'global.php';
?>
