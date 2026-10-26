<?php
/** Installed-runtime exchange checks using temporary tables; no live data writes. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
define('_PS_ADMIN_DIR_', '/var/www/html/host-admin');
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN'); $_SERVER['REQUEST_METHOD'] = 'GET';
require_once '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_.'colemanstay/colemanstay.php';
require_once _PS_MODULE_DIR_.'colemanstay/controllers/admin/AdminColemanRequestsController.php';
/** Assert behavior, not parser implementation. */
function icalCheck($ok, $label) { if (!$ok) { throw new RuntimeException($label); } echo 'PASS: '.$label."\n"; }
/** Check a specific rejection. */
function icalReject($reason, $fn) {
    try { $fn(); } catch (DomainException $e) { icalCheck($e->getMessage() === $reason, 'reject '.$reason); return; }
    throw new RuntimeException('Expected '.$reason);
}
/** Make a future all-day fixture. */
function icalEvent($offset, $days = 2, $extra = '') {
    $start = new DateTimeImmutable('today +'.$offset.' days');
    return "BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:".$start->format('Ymd')."\r\nDTEND;VALUE=DATE:".$start->modify('+'.$days.' days')->format('Ymd')."\r\n".$extra."END:VEVENT\r\n";
}
/** Wrap events in a calendar. */
function icalFile($events) { return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n".$events."END:VCALENDAR\r\n"; }
$db = Db::getInstance();
foreach (array('coleman_unit', 'coleman_calendar', 'coleman_request') as $table) {
    $ddl = $db->executeS('SHOW CREATE TABLE `'._DB_PREFIX_.$table.'`')[0]['Create Table'];
    $db->execute(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
ColemanCalendar::installSchema();
list($one, $two) = ColemanCalendar::unitIds();
$fixture = icalFile(icalEvent(10, 2, "SUMMARY:Private guest name\r\nURL:https://example.invalid/do-not-fetch\r\n").icalEvent(11, 3).icalEvent(14, 2));
$parsed = ColemanIcal::parse($fixture);
$start = (new DateTimeImmutable('today +10 days'))->format('Y-m-d');
$end = (new DateTimeImmutable('today +16 days'))->format('Y-m-d');
icalCheck($parsed['events'] === 3 && $parsed['ranges'] === array(array('arrival' => $start, 'departure' => $end)), 'overlapping and adjacent events combine, checkout stays exclusive');
$day = (new DateTimeImmutable('today +20 days'))->format('Ymd');
$single = ColemanIcal::parse(icalFile("BEGIN:VEVENT\nDTSTART;VALUE=DA\n TE:".$day."\nEND:VEVENT\n"));
icalCheck($single['ranges'][0]['departure'] === (new DateTimeImmutable('today +21 days'))->format('Y-m-d'), 'unfolded date and default one-day duration');
$duration = ColemanIcal::parse(icalFile("BEGIN:VEVENT\nDTSTART;VALUE=DATE:".$day."\nDURATION:P1W\nEND:VEVENT\n"));
icalCheck($duration['ranges'][0]['departure'] === (new DateTimeImmutable('today +27 days'))->format('Y-m-d'), 'all-day duration supported');
$ignored = ColemanIcal::parse(icalFile(icalEvent(10, 2, "STATUS:CANCELLED\r\n").icalEvent(20, 2, "TRANSP:TRANSPARENT\r\n")."BEGIN:VEVENT\nDTSTART;VALUE=DATE:20000101\nEND:VEVENT\n"));
icalCheck($ignored['ignored'] === 3 && !$ignored['ranges'], 'cancelled, free and fully past events do not block nights');
$clipped = ColemanIcal::parse(icalFile("BEGIN:VEVENT\nDTSTART;VALUE=DATE:".(new DateTimeImmutable('yesterday'))->format('Ymd')."\nDTEND;VALUE=DATE:".(new DateTimeImmutable('today +3 years'))->format('Ymd')."\nEND:VEVENT\n"));
icalCheck($clipped['clipped'] === 1 && $clipped['ranges'][0] === array('arrival' => date('Y-m-d'), 'departure' => (new DateTimeImmutable('today +2 years'))->format('Y-m-d')), 'date window clipping is explicit');
icalReject('ical_dates', function () { ColemanIcal::parse(icalFile("BEGIN:VEVENT\nDTSTART;VALUE=DATE:20270230\nEND:VEVENT\n")); });
icalReject('ical_unsupported', function () { ColemanIcal::parse(icalFile("BEGIN:VEVENT\nDTSTART:20271101T120000Z\nEND:VEVENT\n")); });
icalReject('ical_unsupported', function () { ColemanIcal::parse(icalFile(icalEvent(10, 2, "RRULE:FREQ=DAILY\r\n"))); });
icalReject('ical_file', function () { ColemanIcal::parse(icalFile(str_repeat(icalEvent(10), 501))); });
icalReject('ical_file', function () { ColemanIcal::parse(str_repeat('x', 524289)); });
icalReject('ical_file', function () { ColemanIcal::parse("BEGIN:VCALENDAR\nVERSION:2.0\n"); });
$preview = ColemanIcal::preview($one, 'Fixture calendar', $fixture, 1);
icalCheck(ColemanIcal::verifiedPreview($one, $preview['payload'], $preview['signature'], 1)['ranges'] === $parsed['ranges'], 'signed preview preserves exact apartment and dates');
icalReject('ical_preview', function () use ($two, $preview) { ColemanIcal::verifiedPreview($two, $preview['payload'], $preview['signature'], 1); });
icalReject('ical_preview', function () use ($one, $preview) { ColemanIcal::verifiedPreview($one, $preview['payload'], $preview['signature'], 2); });
icalReject('ical_preview', function () use ($one, $preview) { ColemanIcal::verifiedPreview($one, $preview['payload'].'x', $preview['signature'], 1); });
$expired = $preview; $expired['expires'] = time()-1; $payload = base64_encode(json_encode($expired));
icalReject('ical_preview', function () use ($one, $payload) { ColemanIcal::verifiedPreview($one, $payload, hash_hmac('sha256', '1:'.$payload, _COOKIE_KEY_), 1); });
icalCheck(ColemanCalendar::available($one, $start, $end), 'preview does not write date blocks');
icalCheck(ColemanCalendar::importRanges($one, $parsed['ranges'], 'Private imported source', 1) === 1 && !ColemanCalendar::available($one, $start, $end) && ColemanCalendar::available($two, $start, $end), 'import blocks only selected apartment');
icalCheck(ColemanCalendar::importRanges($one, $parsed['ranges'], 'Different source', 1) === 0, 'repeat import does not duplicate or rename blocks');
$export = ColemanIcal::export($one);
icalCheck($export === ColemanIcal::export($one) && substr_count($export, 'BEGIN:VEVENT') === 1 && strpos($export, 'SUMMARY:Unavailable') !== false && strpos($export, 'Private') === strpos($export, 'Private Availability') && strpos($export, 'guest') === false && strpos($export, 'source') === false, 'stable export contains no guest details or private labels');
icalCheck(ColemanIcal::parse($export)['ranges'] === $parsed['ranges'] && substr_count(ColemanIcal::export($two), 'BEGIN:VEVENT') === 0, 'export round-trip and unit isolation');
$early = array('arrival' => (new DateTimeImmutable('today +1 day'))->format('Y-m-d'), 'departure' => (new DateTimeImmutable('today +2 days'))->format('Y-m-d'));
$overlap = array('arrival' => (new DateTimeImmutable('today +15 days'))->format('Y-m-d'), 'departure' => (new DateTimeImmutable('today +18 days'))->format('Y-m-d'));
icalReject('overlap', function () use ($one, $early, $overlap) { ColemanCalendar::importRanges($one, array($early, $overlap), 'Conflict fixture', 1); });
icalCheck(ColemanCalendar::available($one, $early['arrival'], $early['departure']) && (int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_calendar`', false) === 1, 'conflicting batch rolls back every added block');
$block = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'coleman_calendar`', false);
ColemanCalendar::unblock($block['id_coleman_calendar'], 0, 1);
icalCheck(ColemanCalendar::available($one, $start, $end) && strpos(ColemanIcal::export($one), 'BEGIN:VEVENT') === false, 'manual release frees imported dates and removes them from export');
$context = Context::getContext(); $context->employee = new Employee(1); $context->link = new Link('https://', 'https://');
foreach (array('calendar_export', 'calendar_preview', 'calendar_imported') as $decision) {
    $controller = new AdminColemanRequestsController(); $context->controller = $controller;
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = array('decision' => $decision, 'id_product' => (string)$one, 'token' => 'invalid');
    $controller->postProcess(); icalCheck(http_response_code() === 403, 'CSRF blocks '.$decision);
    $_POST['token'] = $controller->token; $controller->tabAccess['edit'] = 0; $controller->postProcess();
    icalCheck(http_response_code() === 403, 'view-only blocks '.$decision);
}
$_POST = array(); $_SERVER['REQUEST_METHOD'] = 'GET';
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$context->smarty->assign('token', 'fixture');
$_GET = array('view' => 'calendar', 'id_product' => (string)$one, 'month' => substr($start, 0, 7));
foreach (array('calendar', 'calendar-preview') as $variant) {
    if ($variant === 'calendar-preview') { $property = new ReflectionProperty($controller, 'calendarPreview'); $property->setAccessible(true); $property->setValue($controller, $preview); }
    $controller->initProcess(); $controller->initContent();
    $html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
    icalCheck(strpos($html, 'Preview dates') !== false && ($variant === 'calendar' || strpos($html, 'Import these dates') !== false), 'native template renders '.$variant);
    if (getenv('COLEMAN_PREVIEW_DIR')) {
        $html = preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html);
        file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/'.$variant.'.html', $html);
    }
}
echo "Temporary tables vanish at exit; no live data was changed.\n";
