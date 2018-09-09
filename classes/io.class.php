<?php
$tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
define('TMP_DIR',$tmp_dir,true);

class io {
  public function downloadFile($file, $name, $mime_type='') {
   /*
   This function takes a path to a file to output ($file),
   the filename that the browser will see ($name) and
   the MIME type of the file ($mime_type, optional).

   If you want to do something on download abort/finish,
   register_shutdown_function('function_name');
   */
   if(!is_readable($file)) throw new Exception('File not found or inaccessible! ['.$file.']');

   $size = filesize($file);
   $name = rawurldecode($name);

   /* Figure out the MIME type (if not specified) */
   $known_mime_types=array(
    "pdf" => "application/pdf",
    "txt" => "text/plain",
    "html" => "text/html",
    "htm" => "text/html",
    "exe" => "application/octet-stream",
    "zip" => "application/zip",
    "doc" => "application/msword",
    "xls" => "application/vnd.ms-excel",
    "ppt" => "application/vnd.ms-powerpoint",
    "gif" => "image/gif",
    "png" => "image/png",
    "jpeg"=> "image/jpg",
    "jpg" => "image/jpg",
    "php" => "text/plain"
   );

   if($mime_type==''){
      $file_extension = strtolower(substr(strrchr($name,"."),1));
      if (array_key_exists($file_extension, $known_mime_types)) {
        $mime_type=$known_mime_types[$file_extension];
      }
      else {
        $mime_type="application/force-download";
      };
   };

   @ob_end_clean(); //turn off output buffering to decrease cpu usage

   // required for IE, otherwise Content-Disposition may be ignored
   if(ini_get('zlib.output_compression'))
    ini_set('zlib.output_compression', 'Off');

    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header("Content-Transfer-Encoding: binary");
    header('Accept-Ranges: bytes');

    /* The three lines below basically make the
    download non-cacheable */
    header("Cache-control: private");
    header('Pragma: private');
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

    // multipart-download and download resuming support
    if(isset($_SERVER['HTTP_RANGE'])) {
      list($a, $range) = explode("=",$_SERVER['HTTP_RANGE'],2);
      list($range) = explode(",",$range,2);
      list($range, $range_end) = explode("-", $range);
      $range=intval($range);
      if (!$range_end) {
        $range_end=$size-1;
      }
      else {
        $range_end=intval($range_end);
      }

      $new_length = $range_end-$range+1;
      header("HTTP/1.1 206 Partial Content");
      header("Content-Length: $new_length");
      header("Content-Range: bytes $range-$range_end/$size");
    }
    else {
      $new_length=$size;
      header("Content-Length: ".$size);
    }

   /* output the file itself */
   $chunksize = 1*(1024*1024); //you may want to change this
   $bytes_send = 0;
   if ($file = fopen($file, 'r')) {
    if(isset($_SERVER['HTTP_RANGE'])) fseek($file, $range);

    while(!feof($file) && (!connection_aborted()) && ($bytes_send<$new_length)) {
      $buffer = fread($file, $chunksize);
      print($buffer); //echo($buffer); // is also possible
      flush();
      $bytes_send += strlen($buffer);
    }
    fclose($file);
   }
   else die('Error - can not open file.');

   die();
  }
  public function uploadFile($fieldName,$path="",$output=false) {
    $debug = false;
    if (!isset($_FILES[$fieldName])) return false;
    if (!$path) $path = tempnam(TMP_DIR,"tmp");
    if (!$debug) {
      if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $path)) {
        if ($output) {
          $buffer = file_get_contents($path);
          return $buffer;
        }
        else return $path;
      }
      else return false;
    }
    else {
      var_dump($file);
      // use dummy file
      $path = "./fg_qld.csv";
      return ($output) ? file_get_contents($path):true;
    }
  }

  public function csvToArray($data,$delimiter=",",$hasHeaders=false,$headers=array(),$ignoreFirst=false,$rowSep="\r\n") {
    $result   = array();
    $headers  = ($headers && !$hasHeaders) ? $headers:array();
    $rows     = explode($rowSep,$data);
    $count    = 0;

    foreach ($rows as $row) {
      $cols = str_getcsv($row,$delimiter);
      $temp = array();
      $colCount = 0;
      foreach ($cols as $col) {
        if ($ignoreFirst && !$count) break;
        else if ($hasHeaders && !$count) $headers[] = $col;

        if (($hasHeaders && $count) || (!$hasHeaders && $headers)) $temp[$headers[$colCount]] = $col;
        else $temp[$colCount] = $col;
        $colCount++;
      }
      if (($hasHeaders && $count) || !$hasHeaders) $result[] = $temp;
      $count++;
    }
    return $result;
  }
  public function arrayToCsv($data,$outputFile="",$delimiter=",",$useHeaders=true,$stringQualifier='"',$lineSep="\r\n",$toFile=true,$trailingLine=true) {
    if (!$data) {
      return false;
    }
    $buffer = "";
    if ($useHeaders) {
      $colCount = 0;
      foreach ($data[0] as $k=>$v) {
        $colCount++;
        if ($colCount>1) $buffer .= $delimiter;
        $buffer .= $stringQualifier . $k . $stringQualifier;
      }
      $buffer .= $lineSep;
    }
    $rowCount = 0;
    foreach ($data as $row) {
      $rowCount++;
      if ($rowCount>=2) $buffer .= $lineSep;
      $buffer .= $stringQualifier.implode($stringQualifier . $delimiter . $stringQualifier,$row).$stringQualifier;
    }
    if ($trailingLine) $buffer .= $lineSep;
    if ($toFile) {
      try {
        file_put_contents($outputFile,$buffer);
      }
      catch (Exception $e) {
        echo "Failed to create file: ".$e->getMessage();
      }
    }
    else return $buffer;
    return true;
  }
  public function xlsxToArray($inputFileName) {
    $reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx;

    $spreadsheet = $reader->load($inputFileName);
    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    return $sheetData;
  }
  public function xlsxLoad($inputFileName) {
    $reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx;
    $spreadsheet = $reader->load($inputFileName);
    return $spreadsheet;
  }

  public function convert($filename="./import/CorowaIGALoyaltyExportOptIns.csv",$from="csv",$to="json",$toFile=false) {
    $data = "";
    $t = new template;
    switch ($from) {
      case "csv":
        $buffer = file_get_contents($filename);
        $data   = $this->csvToArray($buffer,",",true,array(),false,"\n");
        /*
        $sheet = $this->xlsxLoad($filename);
        for ($a=1;$a<=$($sheet->getHighestRow();$a++) {
          for ($b=chr();$)
        }
        */
        break;
    }
    switch ($to) {
      case "json":
        if (!$toFile) $t->setTemplate("blank.php")->setView("json",$data)->output();
        break;
    }
  }
}
?>
