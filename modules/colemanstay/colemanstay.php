<?php
/** 560 Coleman demo additions. Copyright 2026. Licensed under OSL-3.0. */
if (!defined('_PS_VERSION_')) { exit; }
require_once __DIR__.'/classes/ColemanRequest.php';

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
        if (!parent::install() || !$this->registerHook('actionDispatcher')) { return false; }
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

    /** Keep the demo request-only, including legacy checkout URLs. */
    public function hookActionDispatcher($params)
    {
        if ($params['controller_type'] == Dispatcher::FC_FRONT
            && $params['controller_class'] !== 'ColemanstayStayModuleFrontController') {
            Tools::redirect('/');
        }
        if ($params['controller_type'] == Dispatcher::FC_MODULE && Tools::getValue('module') !== $this->name) {
            Tools::redirect('/');
        }
    }
}
