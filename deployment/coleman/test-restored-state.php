<?php
/** Non-secret business-data fingerprints for an isolated restore/rollback rehearsal. */
if (PHP_SAPI !== 'cli' || !is_file('/restore/ready')) { exit(1); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN'); require '/var/www/html/config/config.inc.php';
$db = Db::getInstance(); $result = array();
foreach (array('coleman_request'=>'id_coleman_request', 'coleman_calendar'=>'id_coleman_calendar', 'coleman_unit'=>'id_product', 'coleman_message'=>'request_id, kind') as $table=>$order) {
    $rows = $db->executeS('SELECT * FROM `'._DB_PREFIX_.$table.'` ORDER BY '.$order, true, false);
    $result[$table] = array('count'=>count($rows), 'sha256'=>hash('sha256', json_encode($rows)));
}
$result['orders'] = (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'orders`');
$result['mail_disabled'] = (int)Configuration::get('PS_MAIL_METHOD') === 3;
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
