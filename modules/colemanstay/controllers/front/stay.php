<?php
/** Public demo request controller. Copyright 2026. OSL-3.0. */
class ColemanstayStayModuleFrontController extends ModuleFrontController
{
    private $form = array();
    private $requestErrors = array();
    private $apartments = array();

    /** Initialise a same-site session and the two installed QloApps room types. */
    public function init()
    {
        parent::init();
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('coleman_demo');
            session_start(array('cookie_httponly' => true, 'cookie_secure' => Tools::usingSecureMode(), 'cookie_samesite' => 'Lax', 'use_strict_mode' => true));
        }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        if (empty($_SESSION['request_token'])) { $_SESSION['request_token'] = bin2hex(random_bytes(32)); }
        foreach (array('one' => 'COLEMAN_PRODUCT_ONE', 'two' => 'COLEMAN_PRODUCT_TWO') as $key => $setting) {
            $id = (int)Configuration::get($setting);
            if ($id) { $this->apartments[$key] = $id; }
        }
        foreach (array('apartment', 'guest_name', 'email', 'arrival', 'departure', 'guests', 'message') as $key) {
            $value = Tools::getValue($key, '');
            $this->form[$key] = is_string($value) ? trim($value) : '';
        }
    }

    /** Validate an ISO date without accepting normalised invalid calendar dates. */
    private function validDate($value)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('America/Los_Angeles'));
        return $date && $date->format('Y-m-d') === $value;
    }

    /** Save a validated request once, then redirect to a session-bound receipt. */
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
        $csrf = Tools::getValue('csrf', '');
        $token = Tools::getValue('request_token', '');
        if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)
            || !is_string($token) || !hash_equals($_SESSION['request_token'], $token)) {
            http_response_code(403);
            $this->requestErrors[] = $this->module->l('This form has expired. Please review your details and try again.', 'stay');
            return;
        }
        if (Tools::getValue('website', '') !== '') {
            http_response_code(400);
            $this->requestErrors[] = $this->module->l('We could not accept this request. Please try again.', 'stay');
            return;
        }
        $f = $this->form;
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Los_Angeles'));
        if (!isset($this->apartments[$f['apartment']])) { $this->requestErrors[] = $this->module->l('Choose an apartment.', 'stay'); }
        if (!$f['guest_name'] || mb_strlen($f['guest_name']) > 120 || !Validate::isGenericName($f['guest_name'])) { $this->requestErrors[] = $this->module->l('Enter your name using plain text, up to 120 characters.', 'stay'); }
        if (strlen($f['email']) > 254 || !Validate::isEmail($f['email'])) { $this->requestErrors[] = $this->module->l('Enter a valid email address.', 'stay'); }
        if (!$this->validDate($f['arrival']) || !$this->validDate($f['departure'])
            || $f['arrival'] < $today->format('Y-m-d') || $f['departure'] <= $f['arrival']
            || $f['departure'] > $today->modify('+2 years')->format('Y-m-d')) {
            $this->requestErrors[] = $this->module->l('Choose a future stay within the next two years, with departure after arrival.', 'stay');
        }
        if (!ctype_digit($f['guests']) || (int)$f['guests'] < 1 || (int)$f['guests'] > 8) { $this->requestErrors[] = $this->module->l('Enter between 1 and 8 guests. Capacity will be confirmed by the host.', 'stay'); }
        if (mb_strlen($f['message']) > 2000 || !Validate::isCleanHtml($f['message']) || $f['message'] !== strip_tags($f['message'])) { $this->requestErrors[] = $this->module->l('Use plain text in your message, up to 2,000 characters.', 'stay'); }
        if (Tools::getValue('demo_consent') !== '1') { $this->requestErrors[] = $this->module->l('Please acknowledge that this is a demo request.', 'stay'); }
        $_SESSION['submissions'] = array_values(array_filter($_SESSION['submissions'] ?? array(), function ($t) { return $t > time() - 3600; }));
        if (count($_SESSION['submissions']) >= 5) {
            http_response_code(429);
            $this->requestErrors[] = $this->module->l('You have sent several demo requests. Please try again in an hour.', 'stay');
        }
        if ($this->requestErrors) { if (http_response_code() < 400) { http_response_code(422); } return; }
        try {
            $existing = Db::getInstance()->getValue('SELECT id_coleman_request FROM `'._DB_PREFIX_."coleman_request` WHERE request_token='".pSQL($token)."'");
            if (!$existing) {
                $unitId = $this->apartments[$f['apartment']];
                if (!ColemanCalendar::available($unitId, $f['arrival'], $f['departure'])) { throw new DomainException('unavailable'); }
                $rental = ColemanCalendar::unit($unitId);
                $nights = (new DateTimeImmutable($f['arrival']))->diff(new DateTimeImmutable($f['departure']))->days;
                if ($rental['owner_confirmed'] && ($nights < (int)$rental['min_nights'] || (int)$f['guests'] > (int)$rental['max_guests'])) { throw new DomainException('stay_limits'); }
                $request = new ColemanRequest();
                $request->id_product = $this->apartments[$f['apartment']];
                foreach (array('guest_name', 'email', 'arrival', 'departure', 'message') as $key) { $request->$key = $f[$key]; }
                $request->guests = (int)$f['guests'];
                $request->request_token = $token;
                if (!$request->add()) { throw new RuntimeException('Request persistence failed'); }
                $existing = $request->id;
                $_SESSION['submissions'][] = time();
            }
            $_SESSION['receipt'] = sprintf('CD-%05d', $existing);
            // Keep the token until the receipt GET so a transport retry remains idempotent.
            header('Location: /?sent=1#request', true, 303);
            exit;
        } catch (DomainException $e) {
            http_response_code(422);
            $this->requestErrors[] = $e->getMessage() === 'unavailable'
                ? $this->module->l('Those nights are unavailable. Please choose different dates or another apartment.', 'stay')
                : $this->module->l('Please check the apartment’s minimum stay and maximum guests, then update your request.', 'stay');
        } catch (Exception $e) {
            error_log('Coleman request save failed: '.get_class($e));
            http_response_code(503);
            $this->requestErrors[] = $this->module->l('Your request was not saved. Your details are still here; please try again.', 'stay');
        }
    }

    /** Render the standalone module template, with server-side validation feedback. */
    public function initContent()
    {
        parent::initContent();
        $receipt = Tools::getValue('sent') === '1' ? ($_SESSION['receipt'] ?? '') : '';
        if ($receipt) { $_SESSION['request_token'] = bin2hex(random_bytes(32)); }
        $rental = null;
        if (isset($this->apartments[$this->form['apartment']])) {
            $details = ColemanCalendar::unit($this->apartments[$this->form['apartment']]);
            if ($details['owner_confirmed']) {
                $rental = $details;
                foreach (array('nightly_rate', 'monthly_rate', 'cleaning_fee') as $field) {
                    $rental[$field] = $details[$field] === null ? '' : number_format((float)$details[$field], 2);
                }
            }
        }
        $this->context->smarty->assign(array(
            'coleman_rental' => $rental,
            'coleman_form' => $this->form,
            'coleman_errors' => $this->requestErrors,
            'coleman_csrf' => $_SESSION['csrf'],
            'coleman_token' => $_SESSION['request_token'],
            'coleman_receipt' => $receipt,
            'coleman_ready' => count($this->apartments) === 2,
            'coleman_detail' => $_SERVER['REQUEST_METHOD'] === 'GET' && isset($this->apartments[$this->form['apartment']]) ? $this->form['apartment'] : '',
            'coleman_today' => (new DateTimeImmutable('today', new DateTimeZone('America/Los_Angeles')))->format('Y-m-d'),
        ));
        $this->setTemplate('stay.tpl');
    }

    /** Avoid the hotel theme wrapper; this module owns its complete page. */
    public function display()
    {
        $this->smartyOutputContent($this->template);
    }
}
