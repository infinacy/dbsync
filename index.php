<?php

use Infinacy\DbSync\DbSync;

require_once('vendor/autoload.php');
echo '<pre>';
try {
  $dbs = new DbSync;
  // $dbs->test();
  $src_db = $_REQUEST['src_db'] ?? '';
  $dst_db = $_REQUEST['dst_db'] ?? '';
  $syncScript = $dbs->createSyncScript($src_db, $dst_db);
  echo '<pre>';
  echo $syncScript;
} catch (\Throwable $ex) {
  echo $ex->getMessage();
}
