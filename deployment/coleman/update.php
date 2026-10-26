<?php
/** Apply additive Coleman module updates without reinstalling or reseeding data. */
$_SERVER['HTTP_HOST'] = getenv('DEMO_DOMAIN');
require '/var/www/html/config/config.inc.php';
$module = Module::getInstanceByName('colemanstay');
foreach (array('actionAdminLoginControllerSetMedia', 'actionAdminControllerSetMedia') as $hook) {
    if (!$module->isRegisteredInHook($hook) && !$module->registerHook($hook)) {
        throw new RuntimeException('Unable to register Coleman host styles.');
    }
}
Context::getContext()->smarty->clearCompiledTemplate(_PS_MODULE_DIR_.'colemanstay/views/templates/front/stay.tpl');

$module->upgradeRequestReviewSchema();
Context::getContext()->smarty->clearCompiledTemplate(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');

ColemanCalendar::installSchema();
ColemanMessage::installSchema();
ColemanApplication::installSchema();
foreach (array('calendar.tpl', 'calendar-exchange.tpl', 'settings.tpl', 'message.tpl', 'applications.tpl', 'application.tpl') as $template) {
    Context::getContext()->smarty->clearCompiledTemplate(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/'.$template);
}
