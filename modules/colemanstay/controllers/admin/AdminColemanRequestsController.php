<?php
require_once _PS_MODULE_DIR_.'colemanstay/classes/ColemanRequest.php';
/** Authenticated, read-only enquiry inbox. No request automatically becomes a booking. */
class AdminColemanRequestsController extends ModuleAdminController
{
    /** Use QloApps permissions, login and list rendering. */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'coleman_request';
        $this->className = 'ColemanRequest';
        $this->identifier = 'id_coleman_request';
        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';
        parent::__construct();
        $this->_select = 'pl.name AS apartment_name';
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.id_product = a.id_product AND pl.id_lang = '.(int)$this->context->language->id.' AND pl.id_shop = '.(int)$this->context->shop->id.')';
        $this->allow_export = false;
        $this->list_no_link = true;
        $this->fields_list = array(
            'id_coleman_request' => array('title' => $this->l('Request'), 'type' => 'int'),
            'apartment_name' => array('title' => $this->l('Apartment'), 'filter_key' => 'pl!name'),
            'guest_name' => array('title' => $this->l('Guest')),
            'email' => array('title' => $this->l('Email')),
            'arrival' => array('title' => $this->l('Arrival'), 'type' => 'date'),
            'departure' => array('title' => $this->l('Departure'), 'type' => 'date'),
            'guests' => array('title' => $this->l('Guests'), 'type' => 'int'),
            'message' => array('title' => $this->l('Message')),
            'date_add' => array('title' => $this->l('Received'), 'type' => 'datetime'),
        );
    }

    /** The demo inbox has no edit, delete or reservation-conversion actions. */
    public function initProcess()
    {
        parent::initProcess();
        if ($this->action && $this->action !== 'reset_filters') {
            $this->action = '';
            $this->errors[] = $this->l('The demo inbox is read-only. Requests are not confirmed reservations.');
        }
    }

    /** Remove standard add/edit affordances. */
    public function initToolbar()
    {
        $this->toolbar_btn = array();
    }
}
