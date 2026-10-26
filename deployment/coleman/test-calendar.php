<?php
/** Installed-runtime regression checks; all writes use connection-local temporary tables. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
if (!defined('_PS_ADMIN_DIR_')) { define('_PS_ADMIN_DIR_', '/var/www/html/host-admin'); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN');
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_.'colemanstay/colemanstay.php';
require_once _PS_MODULE_DIR_.'colemanstay/controllers/admin/AdminColemanRequestsController.php';
/** Assert a user-visible or persistence invariant. */
function calendarCheck($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo 'PASS: '.$label."\n"; }
/** Expect a domain failure without masking infrastructure errors. */
function calendarReject($reason, $fn) {
    try { $fn(); } catch (DomainException $error) { calendarCheck($error->getMessage() === $reason, 'reject '.$reason); return; }
    throw new RuntimeException('Expected rejection: '.$reason);
}
$db = Db::getInstance();
foreach (array('coleman_unit', 'coleman_calendar', 'coleman_request') as $table) {
    $ddl = $db->executeS('SHOW CREATE TABLE `'._DB_PREFIX_.$table.'`')[0]['Create Table'];
    $db->execute(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
ColemanCalendar::installSchema();
ColemanCalendar::installSchema();
$ids = ColemanCalendar::unitIds(); $one = $ids[0]; $two = $ids[1];
$unit = ColemanCalendar::unit($one);
calendarCheck($unit['nightly_rate'] === null && $unit['cleaning_fee'] === null && !(int)$unit['owner_confirmed'], 'unknown details remain blank and unconfirmed');
$details = array('nightly_rate' => '180', 'monthly_rate' => '', 'cleaning_fee' => '0', 'min_nights' => '2', 'max_guests' => '4', 'house_rules' => 'Fixture rules only.', 'owner_confirmed' => '');
ColemanCalendar::saveUnit($one, 0, $details, 1);
$unit = ColemanCalendar::unit($one);
calendarCheck($unit['cleaning_fee'] === '0.00' && $unit['monthly_rate'] === null && !(int)$unit['owner_confirmed'], 'zero fee differs from unknown rent; draft stays unconfirmed');
calendarReject('stale', function () use ($one, $details) { ColemanCalendar::saveUnit($one, 0, $details, 1); });
calendarReject('money', function () use ($one, $details) { ColemanCalendar::saveUnit($one, 1, array_merge($details, array('nightly_rate' => '-1')), 1); });
calendarReject('incomplete', function () use ($one) { ColemanCalendar::saveUnit($one, 1, array('owner_confirmed' => '1'), 1); });
$details['owner_confirmed'] = '1'; ColemanCalendar::saveUnit($one, 1, $details, 1);
$start = new DateTimeImmutable('today +10 days');
$a = $start->format('Y-m-d'); $b = $start->modify('+3 days')->format('Y-m-d'); $c = $start->modify('+6 days')->format('Y-m-d');
calendarCheck(!ColemanCalendar::validRange('2027-02-30', '2027-03-05') && !ColemanCalendar::validRange($a, $a), 'invalid and empty date ranges rejected');
$block = ColemanCalendar::block($one, $a, $b, 'Temporary maintenance fixture', 1);
calendarCheck(!ColemanCalendar::available($one, $a, $b) && ColemanCalendar::available($one, $b, $c) && ColemanCalendar::available($two, $a, $b), 'blocked nights, adjacent stays and apartment isolation');
calendarReject('overlap', function () use ($one, $a, $c) { ColemanCalendar::block($one, $a, $c, 'Overlapping fixture', 1); });
calendarReject('stale', function () use ($block) { ColemanCalendar::unblock($block, 1, 1); });
ColemanCalendar::unblock($block, 0, 1);
calendarCheck(ColemanCalendar::available($one, $a, $b), 'released manual block makes nights available');
$request = new ColemanRequest();
$request->id_product = $one; $request->guest_name = 'Calendar fixture'; $request->email = 'calendar@example.invalid';
$request->arrival = $a; $request->departure = $b; $request->guests = 2; $request->message = 'Temporary calendar test.';
$request->request_token = bin2hex(random_bytes(32)); $request->date_add = date('Y-m-d H:i:s'); $request->add(); $id = (int)$request->id;
calendarReject('stale', function () use ($id) { ColemanCalendar::reserve($id, 0, false, 1); });
ColemanRequest::review($id, 0, 'approved', 1);
ColemanCalendar::reserve($id, 1, false, 1);
calendarCheck(!ColemanCalendar::available($one, $a, $b) && $db->getValue('SELECT status FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.$id, false) === 'reserved', 'reservation status and dates allocated together');
calendarReject('stale', function () use ($id) { ColemanCalendar::reserve($id, 1, false, 1); });
calendarCheck(!ColemanRequest::review($id, 2, 'pending', 1), 'ordinary review cannot release a reservation');
$allocation = (int)$db->getValue('SELECT id_coleman_calendar FROM `'._DB_PREFIX_.'coleman_calendar` WHERE request_id = '.$id, false);
calendarReject('stale', function () use ($allocation) { ColemanCalendar::unblock($allocation, 0, 1); });
calendarReject('overlap', function () use ($one, $a, $b) { ColemanCalendar::block($one, $a, $b, 'Conflicting maintenance', 1); });
$second = clone $request; $second->id = null; $second->request_token = bin2hex(random_bytes(32)); $second->add(); $otherId = (int)$second->id;
ColemanRequest::review($otherId, 0, 'approved', 1);
calendarReject('overlap', function () use ($otherId) { ColemanCalendar::reserve($otherId, 1, false, 1); });
calendarCheck($db->getValue('SELECT status FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.$otherId, false) === 'approved', 'conflicting reservation leaves request unchanged');
ColemanCalendar::reserve($id, 2, true, 1);
calendarCheck(ColemanCalendar::available($one, $a, $b), 'releasing reservation frees its dates');
ColemanCalendar::reserve($otherId, 1, false, 1);
$context = Context::getContext(); $context->employee = new Employee(1); $context->link = new Link('https://', 'https://');
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$_POST = array('decision' => 'reserved', 'request_id' => (string)$id, 'revision' => '3', 'token' => $controller->token);
$_SERVER['REQUEST_METHOD'] = 'POST'; $controller->postProcess();
calendarCheck(http_response_code() === 400, 'reservation requires explicit checkbox confirmation');
$_POST = array('decision' => 'blocked', 'id_product' => (string)$one, 'arrival' => $b, 'departure' => $c, 'label' => 'No permission fixture', 'token' => $controller->token);
$controller->tabAccess['edit'] = 0; $controller->postProcess();
calendarCheck(http_response_code() === 403 && ColemanCalendar::available($one, $b, $c), 'view-only host cannot block dates');
$_POST = array(); $_SERVER['REQUEST_METHOD'] = 'GET';
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$context->smarty->assign('token', 'fixture');
foreach (array('calendar', 'settings', 'requests') as $view) {
    $_GET = array('view' => $view, 'id_product' => (string)$one, 'month' => $start->format('Y-m'), 'status' => 'reserved');
    $controller->initProcess(); $controller->initContent();
    $html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
    calendarCheck(strpos($html, $view === 'calendar' ? 'calendar-grid' : ($view === 'settings' ? 'Nightly rent' : 'Release dates')) !== false, 'native template renders '.$view);
    if (getenv('COLEMAN_PREVIEW_DIR')) {
        $html = preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html);
        file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/'.$view.'.html', $html);
    }
}
// Run the actual guest validation path against an allocated stay without creating a request.
require_once _PS_MODULE_DIR_.'colemanstay/controllers/front/stay.php';
$_GET = array('module' => 'colemanstay');
$front = new ColemanstayStayModuleFrontController(); $context->controller = $front;
$form = array('apartment' => 'one', 'guest_name' => 'Blocked fixture', 'email' => 'blocked@example.invalid', 'arrival' => $a, 'departure' => $b, 'guests' => '2', 'message' => 'Temporary fixture');
foreach (array('form' => $form, 'apartments' => array('one' => $one, 'two' => $two)) as $name => $value) {
    $property = new ReflectionProperty($front, $name); $property->setAccessible(true); $property->setValue($front, $value);
}
$_SESSION = array('csrf' => 'fixture', 'request_token' => str_repeat('a', 64));
$_POST = array('csrf' => 'fixture', 'request_token' => str_repeat('a', 64), 'demo_consent' => '1');
$_SERVER['REQUEST_METHOD'] = 'POST';
$count = (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_request`');
$front->postProcess();
calendarCheck(http_response_code() === 422 && $count === (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_request`'), 'guest request cannot use blocked nights');
foreach (array(array('arrival' => $b, 'departure' => $start->modify('+4 days')->format('Y-m-d'), 'guests' => '2'),
    array('arrival' => $b, 'departure' => $c, 'guests' => '5')) as $limits) {
    $limited = new ColemanstayStayModuleFrontController(); $context->controller = $limited;
    foreach (array('form' => array_merge($form, $limits), 'apartments' => array('one' => $one, 'two' => $two)) as $name => $value) {
        $property = new ReflectionProperty($limited, $name); $property->setAccessible(true); $property->setValue($limited, $value);
    }
    http_response_code(200); $limited->postProcess();
    calendarCheck(http_response_code() === 422 && $count === (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_request`'), 'confirmed stay limits reject invalid guest request');
}
echo "Temporary tables vanish at exit; no live data was changed.\n";
