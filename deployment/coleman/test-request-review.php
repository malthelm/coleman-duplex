<?php
/** CLI regression check using a session-local temporary table; no live row changes. OSL-3.0. */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
define('_PS_ADMIN_DIR_', '/var/www/html/host-admin');
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN');
$_SERVER['REQUEST_METHOD'] = 'GET';
require '/var/www/html/config/config.inc.php';
require _PS_MODULE_DIR_.'colemanstay/controllers/admin/AdminColemanRequestsController.php';
/** Fail the CLI process on an incorrect observable result. */
function checkReview($passed, $description)
{
    if (!$passed) { throw new RuntimeException($description); }
    echo 'PASS: '.$description."\n";
}
$db = Db::getInstance();
$ddl = $db->executeS('SHOW CREATE TABLE `'._DB_PREFIX_.'coleman_request`')[0]['Create Table'];
// The temporary table shadows the real table on this connection and disappears at exit.
$db->execute(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
$module = Module::getInstanceByName('colemanstay');
$module->upgradeRequestReviewSchema();
$module->upgradeRequestReviewSchema();
$request = new ColemanRequest();
$request->id_product = (int)Configuration::get('COLEMAN_PRODUCT_ONE');
$request->guest_name = 'Review regression';
$request->email = 'review@example.invalid';
$request->arrival = '2027-01-03';
$request->departure = '2027-01-07';
$request->guests = 2;
$request->message = 'Temporary test request';
$request->request_token = bin2hex(random_bytes(32));
$request->date_add = date('Y-m-d H:i:s');
checkReview($request->add(), 'new guest requests still save after migration');
$id = (int)$request->id;
$context = Context::getContext();
$context->employee = new Employee(1);
$context->link = new Link('https://', 'https://');
$controller = new AdminColemanRequestsController();
$context->controller = $controller;
checkReview(ColemanRequest::review($id, 0, 'approved', 1), 'approve pending request');
$reviewedAt = $db->getValue('SELECT reviewed_at FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.$id);
checkReview(abs(time() - strtotime($reviewedAt)) < 60, 'review timestamps use the application timezone');
checkReview(!ColemanRequest::review($id, 0, 'declined', 1), 'stale form cannot overwrite decision');
checkReview(!ColemanRequest::review($id, 1, 'declined', 1), 'decided request must be reopened');
checkReview(ColemanRequest::review($id, 1, 'pending', 1), 'reopen approved request');
checkReview(ColemanRequest::review($id, 2, 'declined', 1), 'decline pending request');
checkReview(!ColemanRequest::review($id, 3, 'invalid', 1), 'reject invalid status');
checkReview(!ColemanRequest::review($id + 1, 0, 'approved', 1), 'reject missing request');
checkReview(ColemanRequest::review($id, 3, 'pending', 1), 'reopen declined request');
$_POST = array('decision' => 'approved', 'request_id' => (string)$id, 'revision' => '4', 'token' => $controller->token);
$controller->postProcess();
checkReview((int)$db->getValue('SELECT revision FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.$id) === 4, 'GET cannot mutate requests');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['token'] = 'invalid';
$controller->postProcess();
checkReview(http_response_code() === 403, 'reject invalid CSRF token');
$_POST['token'] = $controller->token;
$controller->tabAccess['edit'] = 0;
$controller->postProcess();
checkReview(http_response_code() === 403, 'view-only employee cannot decide');
$controller->tabAccess['edit'] = 1;
$_POST['decision'] = array('approved');
$controller->postProcess();
checkReview(http_response_code() === 400, 'reject malformed decision');
$_POST = array(); $_GET = array();
$controller->initProcess(); $controller->initContent();
$context->smarty->assign('token', 'test');
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
checkReview(strpos($html, 'Review regression') !== false && strpos($html, 'Approve request') !== false, 'real template renders pending request');
$db->update('coleman_request', array('message' => pSQL('<script>bad()</script>', true)), 'id_coleman_request = '.$id);
$controller->initContent();
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
checkReview(strpos($html, '<script>bad()') === false && strpos($html, '&lt;script&gt;') !== false, 'guest messages are escaped');
$_GET['status'] = 'approved';
$controller->initProcess(); $controller->initContent();
$html = $context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
checkReview(strpos($html, 'No approved requests yet') !== false, 'status filter and empty state');
echo "No live request rows were changed.\n";
