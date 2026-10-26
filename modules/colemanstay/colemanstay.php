<?php
/** 560 Coleman demo additions. Copyright 2026. Licensed under OSL-3.0. */
if (!defined('_PS_VERSION_')) { exit; }
require_once __DIR__.'/classes/ColemanRequest.php';
require_once __DIR__.'/classes/ColemanCalendar.php';
require_once __DIR__.'/classes/ColemanMessage.php';

/** A direct-request storefront for two apartments; no payment or reservation creation. */
class ColemanStay extends Module
{
    /** Register module metadata. */
    public function __construct()
    {
        $this->name = 'colemanstay';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Coleman Duplex';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('560 Coleman — direct stay requests');
        $this->description = $this->l('Private demo website and a host inbox. Requests do not reserve dates or charge guests.');
    }

    /** Install an additive request table and authenticated host inbox. */
    public function install()
    {
        if (!parent::install() || !$this->registerHook(array('actionDispatcher', 'actionAdminLoginControllerSetMedia', 'actionAdminControllerSetMedia'))) { return false; }
        if (!Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'coleman_request` (
            `id_coleman_request` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT UNSIGNED NOT NULL,
            `guest_name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(254) NOT NULL,
            `arrival` DATE NOT NULL, `departure` DATE NOT NULL,
            `guests` TINYINT UNSIGNED NOT NULL,
            `message` TEXT NOT NULL,
            `request_token` CHAR(64) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_coleman_request`), UNIQUE KEY (`request_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) { return false; }
        $this->upgradeRequestReviewSchema();
        ColemanCalendar::installSchema();
        ColemanMessage::installSchema();
        if (!Tab::getIdFromClassName('AdminColemanRequests')) {
            $tab = new Tab();
            $tab->class_name = 'AdminColemanRequests';
            $tab->module = $this->name;
            $tab->id_parent = 0;
            $tab->active = 1;
            foreach (Language::getLanguages(false) as $lang) { $tab->name[$lang['id_lang']] = $this->l('Stay requests'); }
            if (!$tab->add()) { return false; }
        }
        return true;
    }

    /** Add review metadata without changing existing requests or reservations. */
    public function upgradeRequestReviewSchema()
    {
        $db = Db::getInstance();
        $columns = array_column($db->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.'coleman_request`'), 'Field');
        $add = array();
        foreach (array(
            'status' => "VARCHAR(16) NOT NULL DEFAULT 'pending'",
            'revision' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'reviewed_at' => 'DATETIME NULL',
            'reviewed_by' => 'INT UNSIGNED NULL',
        ) as $name => $definition) {
            if (!in_array($name, $columns, true)) { $add[] = 'ADD COLUMN `'.$name.'` '.$definition; }
        }
        if ($add && !$db->execute('ALTER TABLE `'._DB_PREFIX_.'coleman_request` '.implode(', ', $add))) {
            throw new RuntimeException('Unable to add Coleman request review fields.');
        }
    }

    /** Apply the storefront design tokens to the native host login. */
    public function hookActionAdminLoginControllerSetMedia($params)
    {
        $this->context->controller->addCSS($this->_path.'views/css/tokens.css');
        $this->context->controller->addCSS($this->_path.'views/css/host.css');
        $this->context->controller->addJS($this->_path.'views/js/host.js');
    }

    /** Keep native host controls visually consistent with the guest storefront. */
    public function hookActionAdminControllerSetMedia($params)
    {
        $this->hookActionAdminLoginControllerSetMedia($params);
    }

    /** Keep the demo request-only, including legacy checkout URLs. */
    public function hookActionDispatcher($params)
    {
        // QloApps labels module storefronts FC_FRONT and builds class names in lowercase.
        if ($params['controller_type'] == Dispatcher::FC_FRONT
            && strcasecmp($params['controller_class'], 'ColemanstayStayModuleFrontController') !== 0) {
            header('Location: /', true, 302);
            exit;
        }
    }
}
