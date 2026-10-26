<?php
/** Installed-runtime application regression checks; writes stay in temporary tables. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
if (!defined('_PS_ADMIN_DIR_')) { define('_PS_ADMIN_DIR_', '/var/www/html/host-admin'); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN'); $_SERVER['REQUEST_METHOD'] = 'GET';
require_once '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_.'colemanstay/colemanstay.php';
require_once _PS_MODULE_DIR_.'colemanstay/controllers/admin/AdminColemanRequestsController.php';
require_once _PS_MODULE_DIR_.'colemanstay/controllers/front/stay.php';
/** Assert an observable invariant. */
function applicationCheck($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo 'PASS: '.$label."\n"; }
/** Reject invalid or conflicting progress without accepting infrastructure errors. */
function applicationReject($reason, $fn) {
    try { $fn(); } catch (DomainException $e) { applicationCheck($e->getMessage() === $reason, 'reject '.$reason); return; }
    throw new RuntimeException('Expected rejection: '.$reason);
}
$db = Db::getInstance();
// Candidate preflight can run before the additive migration exists in the live database.
$db->execute('CREATE TEMPORARY TABLE `'._DB_PREFIX_."coleman_application` (
    id_coleman_application INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_product INT UNSIGNED NOT NULL, guest_name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL,
    move_in DATE NULL, duration VARCHAR(80) NOT NULL, occupants TINYINT UNSIGNED NOT NULL,
    pets VARCHAR(500) NOT NULL, message TEXT NOT NULL, request_token CHAR(64) NOT NULL UNIQUE,
    stage VARCHAR(20) NOT NULL DEFAULT 'enquiry', next_action VARCHAR(250) NOT NULL DEFAULT '',
    revision INT UNSIGNED NOT NULL DEFAULT 0, date_add DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->execute('CREATE TEMPORARY TABLE `'._DB_PREFIX_."coleman_application_history` (
    id_coleman_application_history INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL, stage VARCHAR(20) NOT NULL, next_action VARCHAR(250) NOT NULL,
    note TEXT NOT NULL, employee_id INT UNSIGNED NOT NULL, date_add DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
foreach (array('coleman_request', 'coleman_calendar', 'coleman_unit') as $table) {
    $ddl = $db->executeS('SHOW CREATE TABLE `'._DB_PREFIX_.$table.'`')[0]['Create Table'];
    $db->execute(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
ColemanCalendar::installSchema();
$ids = ColemanCalendar::unitIds();
$app = new ColemanApplication(); $app->id_product = $ids[0]; $app->guest_name = 'Application fixture';
$app->email = 'application@example.invalid'; $app->move_in = null; $app->duration = 'Six months, flexible';
$app->occupants = 2; $app->pets = 'One cat'; $app->message = 'A home for the summer & autumn.';
$app->request_token = bin2hex(random_bytes(32));
applicationCheck($app->add(true, true), 'minimal enquiry persists without departure or move-in date');
$id = (int)$app->id;
$row = ColemanApplication::application($id);
applicationCheck($row['move_in'] === null && $row['stage'] === 'enquiry' && $row['date_add'], 'optional date stays unknown and enquiry has timestamp');
ColemanApplication::progress($id, 0, 'viewing', 'Arrange a viewing', 'Flexible dates & one cat.', 1);
$row = ColemanApplication::application($id);
applicationCheck($row['stage'] === 'viewing' && (int)$row['revision'] === 1 && $row['next_action'] === 'Arrange a viewing', 'host progress persists');
applicationReject('application_stale', function () use ($id) { ColemanApplication::progress($id, 0, 'lease', '', 'Stale edit', 1); });
applicationCheck((int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_application_history`', false) === 1, 'stale update leaves history unchanged');
applicationReject('application_fields', function () use ($id) { ColemanApplication::progress($id, 1, 'reserved', '', '', 1); });
applicationReject('application_fields', function () use ($id) { ColemanApplication::progress($id, 1, 'review', '', '<script>alert(1)</script>', 1); });
applicationReject('application_fields', function () use ($id) { ColemanApplication::progress($id, 1, 'review', array(), '', 1); });
applicationReject('application_missing', function () { ColemanApplication::application(999999); });
$foreign = clone $app; $foreign->id = null; $foreign->id_product = 999999; $foreign->request_token = bin2hex(random_bytes(32)); $foreign->add(true, true);
applicationReject('application_missing', function () use ($foreign) { ColemanApplication::application($foreign->id); });
applicationReject('application_stale', function () use ($foreign) { ColemanApplication::progress($foreign->id, 0, 'viewing', '', '', 1); });
$revision = 1;
foreach (array('application', 'review', 'lease', 'move_in', 'closed', 'enquiry') as $stage) { ColemanApplication::progress($id, $revision++, $stage, '', 'Fixture stage change', 1); }
applicationCheck((int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_request`', false) === 0
    && (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_calendar`', false) === 0, 'application stages do not create stay requests or reserve dates');
$context = Context::getContext(); $context->employee = new Employee(1); $context->link = new Link('https://', 'https://');
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$_POST = array('decision' => 'application_saved', 'application_id' => (string)$id, 'revision' => (string)$revision, 'stage' => 'viewing', 'next_action' => '', 'note' => '', 'token' => 'invalid');
$_SERVER['REQUEST_METHOD'] = 'POST'; $controller->postProcess();
applicationCheck(http_response_code() === 403, 'invalid CSRF token rejected');
$_POST['token'] = $controller->token; $controller->tabAccess['edit'] = 0; $controller->postProcess();
applicationCheck(http_response_code() === 403 && (int)ColemanApplication::application($id)['revision'] === $revision, 'view-only host cannot change progress');
$_POST['revision'] = '0'; $_POST['note'] = 'My stale note & wording';
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$_GET = array('view' => 'application', 'application_id' => (string)$id); $controller->initProcess();
$_POST['token'] = $controller->token; $controller->postProcess();
applicationCheck(http_response_code() === 409, 'controller rejects stale edits');
$context->smarty->assign('token', 'fixture'); $controller->initContent();
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
applicationCheck(strpos($html, 'My stale note &amp; wording') !== false && strpos($html, 'name="revision" value="0"') !== false, 'failed edit preserves text and submitted revision');
$_POST = array(); $_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('applications', 'application') as $view) {
    $_GET = array('view' => $view, 'application_id' => (string)$id);
    $controller = new AdminColemanRequestsController(); $context->controller = $controller;
    $controller->initProcess(); $controller->initContent();
    $html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
    applicationCheck(strpos($html, 'Application fixture') !== false && strpos($html, 'Flexible') !== false, 'real template renders '.$view);
    if (getenv('COLEMAN_PREVIEW_DIR')) { file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/'.$view.'.html', preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html)); }
}
// Guest validation rejects malformed rental entries before saving anything.
$form = array('request_kind' => 'rental', 'apartment' => 'one', 'guest_name' => 'Rental fixture', 'email' => 'rental@example.invalid',
    'move_in' => '2027-02-30', 'duration' => 'Six months', 'pets' => 'None', 'arrival' => '', 'departure' => '', 'guests' => '2', 'message' => '');
$_GET = array('module' => 'colemanstay'); $_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = array('csrf' => 'fixture', 'request_token' => str_repeat('a', 64));
$_POST = array('csrf' => 'fixture', 'request_token' => str_repeat('a', 64), 'demo_consent' => '1');
foreach (array($form, array_merge($form, array('move_in' => '', 'pets' => '<b>cat</b>'))) as $invalid) {
    $front = new ColemanstayStayModuleFrontController(); $context->controller = $front;
    foreach (array('form' => $invalid, 'apartments' => array('one' => $ids[0], 'two' => $ids[1])) as $name => $value) {
        $property = new ReflectionProperty($front, $name); $property->setAccessible(true); $property->setValue($front, $value);
    }
    http_response_code(200); $front->postProcess();
    applicationCheck(http_response_code() === 422, 'guest rejects invalid rental date or markup');
}
echo "All fixture tables vanish at exit; live enquiries and rental settings were not changed.\n";
