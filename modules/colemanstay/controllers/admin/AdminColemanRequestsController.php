<?php
require_once _PS_MODULE_DIR_.'colemanstay/classes/ColemanRequest.php';
/** A focused host inbox using native employee authentication and permissions. */
class AdminColemanRequestsController extends ModuleAdminController
{
    private $requestFilter = 'pending';

    /** Retain the module tab's native access checks. */
    public function __construct()
    {
        parent::__construct();
        $this->display_header = false;
        $this->display_footer = false;
    }

    /** This inbox supports only its explicit review action, never native CRUD. */
    public function initProcess()
    {
        $this->action = '';
        $this->ajax = false;
        $filter = Tools::getValue('status', 'pending');
        if (is_string($filter) && in_array($filter, array('pending', 'approved', 'declined', 'all'), true)) {
            $this->requestFilter = $filter;
        }
    }

    /** Save a decision through a token-protected POST and reject stale forms. */
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
        if (!$this->viewAccess() || empty($this->tabAccess['edit']) || !$this->checkToken()) {
            http_response_code(403);
            $this->errors[] = $this->l('You do not have permission to review requests.');
            return;
        }
        $id = Tools::getValue('request_id');
        $revision = Tools::getValue('revision');
        $decision = Tools::getValue('decision');
        if (!is_string($id) || !ctype_digit($id) || (int)$id < 1
            || !is_string($revision) || !ctype_digit($revision)
            || !is_string($decision) || !in_array($decision, array('approved', 'declined', 'pending'), true)) {
            http_response_code(400);
            $this->errors[] = $this->l('This decision could not be saved. Refresh the inbox and try again.');
            return;
        }
        if (!ColemanRequest::review((int)$id, (int)$revision, $decision, (int)$this->context->employee->id)) {
            http_response_code(409);
            $this->errors[] = $this->l('This request has changed or is no longer available. Review its current status before trying again.');
            return;
        }
        $this->context->cookie->coleman_notice = $decision;
        $this->context->cookie->write();
        header('Location: '.$this->context->link->getAdminLink('AdminColemanRequests').'&status='.$this->requestFilter, true, 303);
        exit;
    }

    /** Read a bounded page of requests for the two configured apartments. */
    public function initContent()
    {
        $db = Db::getInstance();
        $scope = 'id_product IN ('.(int)Configuration::get('COLEMAN_PRODUCT_ONE').','.(int)Configuration::get('COLEMAN_PRODUCT_TWO').')';
        $counts = array('pending' => 0, 'approved' => 0, 'declined' => 0, 'all' => 0);
        foreach ($db->executeS('SELECT status, COUNT(*) AS total FROM `'._DB_PREFIX_.'coleman_request` WHERE '.$scope.' GROUP BY status') as $group) {
            $counts[$group['status']] = (int)$group['total'];
            $counts['all'] += (int)$group['total'];
        }
        $pages = max(1, (int)ceil($counts[$this->requestFilter] / 20));
        $pageValue = Tools::getValue('page', '1');
        $page = is_scalar($pageValue) ? max(1, min($pages, (int)$pageValue)) : 1;
        $where = $scope.($this->requestFilter === 'all' ? '' : " AND status = '".pSQL($this->requestFilter)."'");
        $requests = $db->executeS('SELECT id_coleman_request, id_product, guest_name, email, arrival, departure, guests, message, date_add, status, reviewed_at, revision
            FROM `'._DB_PREFIX_.'coleman_request` WHERE '.$where.' ORDER BY date_add DESC, id_coleman_request DESC LIMIT '.(($page - 1) * 20).', 20');
        foreach ($requests as &$request) {
            $request['unit'] = (int)$request['id_product'] === (int)Configuration::get('COLEMAN_PRODUCT_ONE') ? 'one' : 'two';
            $request['reference'] = 'CD-'.str_pad($request['id_coleman_request'], 5, '0', STR_PAD_LEFT);
            $request['nights'] = (new DateTime($request['arrival']))->diff(new DateTime($request['departure']))->days;
        }
        unset($request);
        $notice = $this->context->cookie->coleman_notice;
        unset($this->context->cookie->coleman_notice);
        $this->context->smarty->assign(array(
            'host_language' => $this->context->language->iso_code,
            'requests' => $requests, 'counts' => $counts, 'filter' => $this->requestFilter,
            'page' => $page, 'pages' => $pages,
            'inbox_url' => $this->context->link->getAdminLink('AdminColemanRequests'),
            'settings_url' => $this->context->link->getAdminLink('AdminDashboard'),
            'can_review' => !empty($this->tabAccess['edit']),
            'review_errors' => $this->errors, 'notice' => $notice,
            'status_labels' => array('pending' => $this->l('Pending'), 'approved' => $this->l('Approved'), 'declined' => $this->l('Declined'), 'all' => $this->l('All requests')),
        ));
    }

    /** Render the branded shell after native authentication and access checks. */
    public function display()
    {
        if (!$this->viewAccess()) { parent::display(); return; }
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        $this->smartyOutputContent(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
    }
}
