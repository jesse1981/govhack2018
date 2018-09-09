<!DOCTYPE html>
<html>
  <head>
    <title>Govhack 2018: OpenSearch | <?php echo $this->title; ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="">
    <?php echo $this->loadPartialView("./templates/_mobile_meta.php"); ?>
    <?php echo $this->loadPartialView("./templates/_styles.php"); ?>
    <?php echo $this->loadPartialView("./templates/_head.php"); ?>
  </head>
  <body>
    <?php echo $this->loadPartialView("./templates/_header.php"); ?>
    <?php echo $view; ?>
    <?php echo $this->loadPartialView("./templates/_scripts.php"); ?>
  </body>
</html>
