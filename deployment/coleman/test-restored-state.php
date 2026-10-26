<?php
/** Non-secret business-data fingerprints for an isolated restore/rollback rehearsal. */
if (PHP_SAPI !== 'cli' || !is_file('/restore/ready')) { exit(1); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN'); require '/var/www/html/config/config.inc.php';
$db = Db::getInstance(); $result = array();
$tables = array('coleman_request'=>'id_coleman_request', 'coleman_calendar'=>'id_coleman_calendar', 'coleman_unit'=>'id_product', 'coleman_message'=>'request_id, kind');
if (getenv('COLEMAN_EXPECT_TENANCY') === '1') {
    $tables += array('coleman_application'=>'id_coleman_application', 'coleman_application_history'=>'id_coleman_application_history', 'coleman_tenancy'=>'application_id', 'coleman_tenancy_history'=>'id');
}
foreach ($tables as $table=>$order) {
    $rows = $db->executeS('SELECT * FROM `'._DB_PREFIX_.$table.'` ORDER BY '.$order, true, false);
    if (!is_array($rows)) { throw new RuntimeException('Unable to read restored table: '.$table); }
    $result[$table] = array('count'=>count($rows), 'sha256'=>hash('sha256', json_encode($rows)));
    if ($table === 'coleman_tenancy') {
        $files = array();
        foreach ($rows as $row) {
            $record = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($record['files'] as $file) {
                $path = _PS_ROOT_DIR_.'/var/coleman-private/'.$file['stored'];
                if (!is_file($path) || filesize($path) !== $file['bytes'] || (fileperms($path) & 0777) !== 0600) {
                    throw new RuntimeException('Restored attachment missing, wrong size or not private');
                }
                $files[] = array('application_id'=>$row['application_id'], 'id'=>$file['id'], 'sha256'=>hash_file('sha256', $path));
            }
        }
        $result['private_attachments'] = array('count'=>count($files), 'sha256'=>hash('sha256', json_encode($files)));
    }
}
$result['orders'] = (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'orders`');
$result['mail_disabled'] = (int)Configuration::get('PS_MAIL_METHOD') === 3;
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
