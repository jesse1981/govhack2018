<?php
class zip {
  var $local;
  var $status;

  public function __construct($filename,$open = false,$overwrite = false) {
    $this->local = new ZipArchive;
    $this->createOrOpen($filename, $open, $overwrite);
  }
  public function __destruct() {
    // Close any existing
    $this->close();
  }
  private function friendlyError($res) {
    switch ($res) {
      case ZipArchive::ER_EXISTS:
        $res = "File already exists.";
        break;
      case ZipArchive::ER_INCONS:
        $res = "Zip archive inconsistent.";
        break;
      case ZipArchive::ER_INVAL:
        $res = "Invalid argument.";
        break;
      case ZipArchive::ER_MEMORY:
        $res = "Malloc failure.";
        break;
      case ZipArchive::ER_NOENT:
        $res = "No such file.";
        break;
      case ZipArchive::ER_NOZIP:
        $res = "Not a zip archive.";
        break;
      case ZipArchive::ER_OPEN:
        $res = "Can't open file.";
        break;
      case ZipArchive::ER_READ:
        $res = "Read Error.";
        break;
      case ZipArchive::ER_SEEK:
        $res = "Seek error.";
        break;
    }
    return $res;
  }

  public function createOrOpen($filename,$open = false,$overwrite = false) {
    // Try to create
    if (($filename)     && (!$open))        $this->status = $this->local->open($filename, ZIPARCHIVE::CREATE);
    elseif (($filename) && ($overwrite))    $this->status = $this->local->open($filename, ZIPARCHIVE::OVERWRITE);
    elseif (($filename) && ($open))         $this->status = $this->local->open($filename);

    if ($this->status!==true) $this->status = $this->friendlyError($this->status);
  }
  public function close() {
    if ($this->local===true) return $this->local->close();
    else return false;
  }
  public function getStatus() {
    return $this->status;
  }

  public function addData($localname,$data) {
    // Return true/false
    if ($this->local===true) return $this->local->addFromString($localname,$data);
    else return false;
  }
  public function addDir($dir) {
    // Return true/false
    if ($this->local===true) return $this->local->addEmptyDir($dir);
    else return false;
  }
  public function addFile($file,$localname) {
    // Add file (return true/false
    if ($this->local===true) return $this->local->addFile($file, $localname);
    else return false;
  }
  public function remove($name) {
    // Add file (return true/false
    if ($this->local===true) return $this->local->deleteName($name);
    else return false;
  }
  public function extract($dest,$entries = array()) {
    // Return true/false
    if ($this->status===true) {
      if (count($entries)) return $this->local->extractTo($dest,$entries);
      else return $this->local->extractTo($dest);
    }
    else return false;
  }

  public function getComment() {
    // Return true/false
    if ($this->local===true) return $this->local->getArchiveComment();
    else return false;
  }
  public function getData($name) {
    // Return true/false
    if ($this->local===true) return $this->local->getFromName($name);
    else return false;
  }
  public function getLastStatus() {
    // return msg/false
    if ($this->local===true) return $this->local->getStatusString();
    else return false;
  }
  public function setComment($comment) {
    // Return true/false
    if ($this->local===true) return $this->local->setArchiveComment($comment);
    else return false;
  }

  /**
 * GZIPs a file on disk (appending .gz to the name)
 *
 * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
 * Based on function by Kioob at:
 * http://www.php.net/manual/en/function.gzwrite.php#34955
 *
 * @param string $source Path to file that should be compressed
 * @param integer $level GZIP compression level (default: 9)
 * @return string New filename (with .gz appended) if success, or false if operation fails
 */
function gzCompressFile($source, $level = 9){
  $dest = $source . '.gz';
  $mode = 'wb' . $level;
  $error = false;
  if ($fp_out = gzopen($dest, $mode)) {
    if ($fp_in = fopen($source,'rb')) {
      while (!feof($fp_in)) gzwrite($fp_out, fread($fp_in, 1024 * 512));
      fclose($fp_in);
    }
    else $error = true; 
    gzclose($fp_out);
  }
  else $error = true;

  if ($error) return false;
  else return $dest;
  }
}
?>
