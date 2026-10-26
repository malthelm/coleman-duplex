<?php
/** Installed-runtime draft tests; writes are limited to temporary tables. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
if (!defined('_PS_ADMIN_DIR_')) { define('_PS_ADMIN_DIR_', '/var/www/html/host-admin'); }
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN'); $_SERVER['REQUEST_METHOD'] = 'GET';
require_once '/var/www/html/config/config.inc.php';
require_once _PS_MODULE_DIR_.'colemanstay/colemanstay.php';
require_once _PS_MODULE_DIR_.'colemanstay/controllers/admin/AdminColemanRequestsController.php';
/** Fail visibly when an observable draft invariant does not hold. */
function messageCheck($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo 'PASS: '.$label."\n"; }
/** Require the intended rejection rather than treating any failure as a pass. */
function messageReject($reason, $callback) {
    try { $callback(); } catch (DomainException $error) { messageCheck($error->getMessage() === $reason, 'reject '.$reason); return; }
    throw new RuntimeException('Expected rejection '.$reason);
}
$db = Db::getInstance();
foreach (array('coleman_request', 'coleman_message') as $table) {
    $ddl = $db->executeS('SHOW CREATE TABLE `'._DB_PREFIX_.$table.'`')[0]['Create Table'];
    $db->execute(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
ColemanMessage::installSchema(); ColemanMessage::installSchema();
$request = new ColemanRequest(); $request->id_product = (int)Configuration::get('COLEMAN_PRODUCT_ONE');
$request->guest_name = 'Message fixture'; $request->email = 'draft@example.invalid';
$request->arrival = date('Y-m-d', strtotime('+20 days')); $request->departure = date('Y-m-d', strtotime('+23 days'));
$request->guests = 2; $request->message = 'Temporary message test'; $request->request_token = bin2hex(random_bytes(32)); $request->date_add = date('Y-m-d H:i:s'); $request->add(); $id = (int)$request->id;
messageReject('message_stale', function () use ($id) { ColemanMessage::save($id, 'approved', 0, 0, 'Reply', 'Body', 1); });
ColemanRequest::review($id, 0, 'approved', 1);
ColemanMessage::save($id, 'approved', 1, 0, 'Edited reply — café', "Hello,\r\n\r\nHost-edited wording & details.", 1);
$draft = ColemanMessage::draft($id, 'approved');
messageCheck((int)$draft['revision'] === 1 && $draft['body'] === "Hello,\n\nHost-edited wording & details.", 'draft saves edits and normalizes newlines');
messageReject('draft_stale', function () use ($id) { ColemanMessage::save($id, 'approved', 1, 0, 'Old tab', 'Outdated', 1); });
messageReject('message_content', function () use ($id) { ColemanMessage::save($id, 'approved', 1, 1, "Reply\r\nBcc: injected@example.invalid", 'Body', 1); });
messageReject('message_content', function () use ($id) { ColemanMessage::save($id, 'approved', 1, 1, 'Reply', '<script>alert(1)</script>', 1); });
messageReject('message_content', function () use ($id) { ColemanMessage::save($id, 'approved', 1, 1, 'Reply', str_repeat('x',10001), 1); });
messageReject('message_stale', function () use ($id) { ColemanMessage::save($id, 'reserved', 1, 0, 'Confirmation', 'Body', 1); });
$eml = ColemanMessage::emailDraft($id, 'approved', 1, 1);
list($headers, $encodedBody) = explode("\r\n\r\n", $eml, 2);
messageCheck(strpos($headers, 'To: draft@example.invalid') !== false && strpos($headers, 'X-Unsent: 1') !== false
    && base64_decode($encodedBody) === "Hello,\r\n\r\nHost-edited wording & details.", 'download contains saved recipient and Unicode plain-text draft, marked unsent');
messageCheck(iconv_mime_decode_headers($headers, 0, 'UTF-8')['Subject'] === 'Edited reply — café', 'Unicode subject round-trips');
ColemanRequest::review($id, 1, 'pending', 1);
messageReject('message_stale', function () use ($id) { ColemanMessage::emailDraft($id, 'approved', 1, 1); });
ColemanRequest::review($id, 2, 'declined', 1);
ColemanMessage::save($id, 'declined', 3, 0, 'Decline reply', 'Thank you for your interest.', 1);
messageCheck(ColemanMessage::draft($id, 'approved')['subject'] === 'Edited reply — café', 'another decision retains the earlier edited draft');
ColemanRequest::review($id, 3, 'pending', 1); ColemanRequest::review($id, 4, 'approved', 1);
messageReject('draft_stale', function () use ($id) { ColemanMessage::emailDraft($id, 'approved', 5, 1); });
$context = Context::getContext(); $context->employee = new Employee(1); $context->link = new Link('https://', 'https://');
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$_GET = array('view' => 'message', 'request_id' => (string)$id); $_POST = array();
$controller->initProcess(); $controller->initContent(); $context->smarty->assign('token', 'fixture');
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
messageCheck(strpos($html, 'This draft was saved before the request changed.') !== false && strpos($html, 'Host-edited wording &amp; details.') !== false
    && strpos($html, 'name="request_revision" value="5"') !== false, 'stale draft preserves wording, escapes content and offers current revision after reload');
if (getenv('COLEMAN_PREVIEW_DIR')) { file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/message-stale.html', preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html)); }
ColemanMessage::save($id, 'approved', 5, 1, 'Reviewed reply', 'Reviewed wording for the new decision.', 1);
messageCheck(strpos(ColemanMessage::emailDraft($id, 'approved', 5, 2), 'X-Unsent: 1') !== false, 'reviewed current draft can be downloaded');
$_POST = array('decision' => 'draft_saved', 'token' => $controller->token, 'request_id' => (string)$id, 'kind' => 'approved', 'request_revision' => '5', 'revision' => '2', 'subject' => 'Forbidden edit', 'body' => 'Should not save');
$_SERVER['REQUEST_METHOD'] = 'POST'; $controller->tabAccess['edit'] = 0; $controller->postProcess();
messageCheck(http_response_code() === 403 && (int)ColemanMessage::draft($id,'approved')['revision'] === 2, 'view-only employee cannot edit draft');
$controller = new AdminColemanRequestsController(); $context->controller = $controller; $_POST['token'] = 'invalid'; $controller->postProcess();
messageCheck(http_response_code() === 403 && (int)ColemanMessage::draft($id,'approved')['revision'] === 2, 'invalid CSRF token cannot save draft');
// Simulate a reserved fixture without changing the live calendar or request table.
$db->execute('UPDATE `'._DB_PREFIX_."coleman_request` SET status='reserved', revision=6 WHERE id_coleman_request=".$id);
$_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = array();
$controller = new AdminColemanRequestsController(); $context->controller = $controller;
$controller->initProcess(); $controller->initContent();
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
messageCheck(strpos($html, 'Date confirmation draft') !== false && strpos($html, 'test confirmation, not a rental agreement') !== false, 'reserved request offers honest demo confirmation wording');
if (getenv('COLEMAN_PREVIEW_DIR')) { file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/message.html', preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html)); }
ColemanMessage::save($id, 'reserved', 6, 0, 'Confirmation draft', 'Edited confirmation.', 1);
$controller->initContent(); $html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
if (getenv('COLEMAN_PREVIEW_DIR')) { file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/message-saved.html', preg_replace('/token=[a-zA-Z0-9_-]+/', 'token=fixture', $html)); }
$db->execute('UPDATE `'._DB_PREFIX_."coleman_request` SET status='approved', revision=7 WHERE id_coleman_request=".$id);
messageReject('message_stale', function () use ($id) { ColemanMessage::emailDraft($id, 'reserved', 6, 1); });
messageReject('missing_request', function () { ColemanMessage::request(999999999); });
messageCheck((int)Configuration::get('PS_MAIL_METHOD') === 3, 'mail delivery remains disabled');
echo "Temporary tables vanish at exit; no live requests or drafts were changed.\n";
