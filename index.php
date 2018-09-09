<?php include_once 'config.php'; ?>
<?php
$controller = new $module;
if (ID) $controller->$action(ID);
else $controller->$action();
?>
