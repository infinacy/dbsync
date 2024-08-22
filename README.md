# inf-dbsync
Structure synchronization tool between two mysql databases. The script returns an diff script in SQL format.

# How to use

//Edit src/config/db.php to enter the databse server and username/password credentials

use Infinacy\DbSync\DbSync;
require_once('vendor/autoload.php');
$dbs = new DbSync;
$src_db = 'database1';
$dst_db = 'database2';
$syncScript = $dbs->createSyncScript($src_db, $dst_db);

echo '<pre>';
echo $syncScript;
