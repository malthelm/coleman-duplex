<?php
require_once _PS_MODULE_DIR_.'colemanstay/classes/ColemanRequest.php';
require_once _PS_MODULE_DIR_.'colemanstay/classes/ColemanCalendar.php';
/** A focused host inbox using native employee authentication and permissions. */
class AdminColemanRequestsController extends ModuleAdminController
{
    private $requestFilter = 'pending';
    private $hostView = 'requests';
    private $formValues = array();

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
        $view = Tools::getValue('view', 'requests');
        if (is_string($view) && in_array($view, array('requests', 'calendar', 'settings'), true)) { $this->hostView = $view; }
        $this->action = '';
        $this->ajax = false;
        $filter = Tools::getValue('status', 'pending');
        if (is_string($filter) && in_array($filter, array('pending', 'approved', 'reserved', 'declined', 'all'), true)) {
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
        $decision = Tools::getValue('decision');
        $id = $decision === 'unblocked' ? Tools::getValue('block_id') : Tools::getValue('request_id', Tools::getValue('id_product', ''));
        $revision = Tools::getValue('revision', '0');
        $this->formValues = array();
        foreach (array('id_product', 'nightly_rate', 'monthly_rate', 'cleaning_fee', 'min_nights', 'max_guests', 'house_rules', 'owner_confirmed', 'arrival', 'departure', 'label') as $field) {
            $value = Tools::getValue($field, '');
            $this->formValues[$field] = is_string($value) ? trim($value) : $value;
        }
        try {
            if (!is_string($id) || !ctype_digit($id) || (int)$id < 1 || !is_string($revision) || !ctype_digit($revision)
                || !is_string($decision) || !in_array($decision, array('approved', 'declined', 'pending', 'reserved', 'released', 'settings_saved', 'blocked', 'unblocked'), true)) {
                throw new DomainException('invalid');
            }
            $actor = (int)$this->context->employee->id;
            if ($decision === 'settings_saved') {
                ColemanCalendar::saveUnit((int)$id, (int)$revision, $this->formValues, $actor);
            } elseif ($decision === 'blocked') {
                ColemanCalendar::block((int)$id, $this->formValues['arrival'], $this->formValues['departure'], $this->formValues['label'], $actor);
            } elseif ($decision === 'unblocked') {
                ColemanCalendar::unblock((int)$id, (int)$revision, $actor);
            } elseif ($decision === 'reserved' || $decision === 'released') {
                if (Tools::getValue('confirm_dates') !== '1') { throw new DomainException('confirm'); }
                ColemanCalendar::reserve((int)$id, (int)$revision, $decision === 'released', $actor);
            } elseif (!ColemanRequest::review((int)$id, (int)$revision, $decision, $actor)) {
                throw new DomainException('stale');
            }
        } catch (DomainException $error) {
            $messages = array(
                'invalid' => $this->l('This decision could not be saved. Refresh the inbox and try again.'),
                'unit' => $this->l('Choose one of the two Coleman apartments.'),
                'money' => $this->l('Enter a positive rent amount with up to two decimal places, or leave it blank. A cleaning fee may be zero.'),
                'limits' => $this->l('Minimum stay must be between 1 and 730 nights, and capacity between 1 and 8 guests. Leave undecided details blank.'),
                'rules' => $this->l('Use plain text for house rules, up to 4,000 characters.'),
                'incomplete' => $this->l('Before marking details confirmed, enter at least one rent amount, the cleaning fee, minimum stay, capacity and house rules.'),
                'dates' => $this->l('Choose dates within the next two years, with checkout after check-in and no past nights.'),
                'label' => $this->l('Add a plain-text reason for the date block, up to 160 characters.'),
                'overlap' => $this->l('Those nights overlap an existing block or reservation. Check the calendar and choose different dates.'),
                'stay_limits' => $this->l('This request does not meet the confirmed minimum stay or guest capacity. Review the apartment details first.'),
                'confirm' => $this->l('Tick the confirmation box before reserving or releasing dates.'),
                'stale' => $this->l('This request has changed or is no longer available. Review its current status before trying again.'),
            );
            http_response_code(in_array($error->getMessage(), array('overlap', 'stale'), true) ? 409 : 400);
            $this->errors[] = $messages[$error->getMessage()] ?? $messages['invalid'];
            return;
        } catch (Throwable $error) {
            error_log('Coleman host save failed: '.get_class($error));
            http_response_code(503);
            $this->errors[] = $this->l('The change was not saved. Your entries are still here; please try again.');
            return;
        }
        $this->context->cookie->coleman_notice = $decision;
        $this->context->cookie->write();
        $returnUrl = $this->context->link->getAdminLink('AdminColemanRequests').'&status='.$this->requestFilter.'&view='.$this->hostView;
        if ($this->hostView === 'calendar') {
            $returnUrl .= '&id_product='.(int)Tools::getValue('id_product');
            $month = Tools::getValue('month');
            if (is_string($month) && preg_match('/^\d{4}-\d{2}$/D', $month)) { $returnUrl .= '&month='.$month; }
        }
        header('Location: '.$returnUrl, true, 303);
        exit;
    }

    /** Read a bounded page of requests for the two configured apartments. */
    public function initContent()
    {
        $db = Db::getInstance();
        $scope = 'id_product IN ('.(int)Configuration::get('COLEMAN_PRODUCT_ONE').','.(int)Configuration::get('COLEMAN_PRODUCT_TWO').')';
        $counts = array('pending' => 0, 'approved' => 0, 'reserved' => 0, 'declined' => 0, 'all' => 0);
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
            'host_view' => $this->hostView,
            'host_language' => $this->context->language->iso_code,
            'requests' => $requests, 'counts' => $counts, 'filter' => $this->requestFilter,
            'page' => $page, 'pages' => $pages,
            'inbox_url' => $this->context->link->getAdminLink('AdminColemanRequests'),
            'settings_url' => $this->context->link->getAdminLink('AdminDashboard'),
            'can_review' => !empty($this->tabAccess['edit']),
            'review_errors' => $this->errors, 'notice' => $notice,
            'status_labels' => array('pending' => $this->l('Pending'), 'approved' => $this->l('Approved'), 'reserved' => $this->l('Reserved'), 'declined' => $this->l('Declined'), 'all' => $this->l('All requests')),
        ));
        if ($this->hostView !== 'requests') { $this->initApartmentContent(); }
    }

    /** Build the monthly calendar and optional rental details using the same host shell. */
    private function initApartmentContent()
    {
        $ids = ColemanCalendar::unitIds();
        $selected = Tools::getValue('id_product', (string)$ids[0]);
        $selected = is_string($selected) && ctype_digit($selected) && in_array((int)$selected, $ids, true) ? (int)$selected : $ids[0];
        $units = array();
        foreach ($ids as $id) {
            $unit = ColemanCalendar::unit($id);
            $unit['name'] = $id === (int)Configuration::get('COLEMAN_PRODUCT_ONE') ? $this->l('Apartment One') : $this->l('Apartment Two');
            $unit['address'] = $id === (int)Configuration::get('COLEMAN_PRODUCT_ONE') ? $this->l('Lower apartment · 560') : $this->l('Upper apartment · 562');
            if ($this->errors && isset($this->formValues['id_product']) && $this->formValues['id_product'] === (string)$id && Tools::getValue('decision') === 'settings_saved') {
                foreach (array('nightly_rate','monthly_rate','cleaning_fee','min_nights','max_guests','house_rules','owner_confirmed') as $field) {
                    $unit[$field] = is_string($this->formValues[$field]) ? $this->formValues[$field] : '';
                }
                // Keep the submitted revision: stale forms require a deliberate reload before saving again.
                $revision = Tools::getValue('revision');
                if (is_string($revision) && ctype_digit($revision)) { $unit['revision'] = $revision; }
            }
            foreach (array('nightly_rate','monthly_rate','cleaning_fee','min_nights','max_guests','house_rules') as $field) { if ($unit[$field] === null) { $unit[$field] = ''; } }
            $units[] = $unit;
        }
        $today = new DateTimeImmutable('today');
        $monthValue = Tools::getValue('month', $today->format('Y-m'));
        $month = is_string($monthValue) ? DateTimeImmutable::createFromFormat('!Y-m', $monthValue) : false;
        if (!$month || $month->format('Y-m') !== $monthValue || $monthValue < $today->modify('-1 year')->format('Y-m') || $monthValue > $today->modify('+2 years')->format('Y-m')) {
            $month = $today->modify('first day of this month');
        }
        $start = $month->modify('-'.((int)$month->format('N') - 1).' days');
        $end = $start->modify('+42 days');
        $blocks = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_."coleman_calendar` WHERE id_product = ".$selected." AND active = 1 AND arrival < '".$end->format('Y-m-d')."' AND departure > '".$start->format('Y-m-d')."' ORDER BY arrival", true, false);
        $days = array();
        for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d'); $kind = '';
            foreach ($blocks as $block) { if ($block['arrival'] <= $date && $block['departure'] > $date) { $kind = $block['request_id'] ? 'reserved' : 'blocked'; break; } }
            $days[] = array('date' => $date, 'number' => $day->format('j'), 'full' => $day->format('F j, Y'), 'kind' => $kind, 'outside' => $day->format('Y-m') !== $month->format('Y-m'), 'today' => $date === $today->format('Y-m-d'));
        }
        $form = array('arrival' => '', 'departure' => '', 'label' => '');
        foreach ($form as $key => $value) { if (isset($this->formValues[$key]) && is_string($this->formValues[$key])) { $form[$key] = $this->formValues[$key]; } }
        $this->context->smarty->assign(array('units' => $units, 'selected_unit' => $selected, 'calendar_month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'), 'calendar_weeks' => array_chunk($days, 7), 'blocks' => $blocks,
            'previous_month' => $month->modify('-1 month')->format('Y-m'), 'next_month' => $month->modify('+1 month')->format('Y-m'),
            'today' => $today->format('Y-m-d'), 'max_date' => $today->modify('+2 years')->format('Y-m-d'), 'block_form' => $form));
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
