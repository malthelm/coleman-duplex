<?php
/** Private applicant/resident portal using revocable host-issued access. OSL-3.0. */
class ColemanstayPortalModuleFrontController extends ModuleFrontController
{
    private $portalErrors = array();
    private $rental = null;

    /** Start an isolated, secure session; validate the grant on every request. */
    public function init()
    {
        parent::init();
        header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('coleman_portal');
            session_start(array('cookie_httponly'=>true,'cookie_secure'=>Tools::usingSecureMode(),'cookie_samesite'=>'Strict','use_strict_mode'=>true));
        }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
        if (isset($_SESSION['rental']) && ($_SESSION['expires'] <= time() || !ColemanTenancy::authorized($_SESSION['rental'],$_SESSION['grant']))) { unset($_SESSION['rental'],$_SESSION['grant'],$_SESSION['expires']); }
        if (isset($_SESSION['rental'])) { $this->rental = ColemanTenancy::read($_SESSION['rental']); }
    }

    /** Authenticate without secrets in URLs; every mutation and download requires CSRF. */
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
        try {
            $csrf = Tools::getValue('csrf');
            if (!is_string($csrf) || !hash_equals($_SESSION['csrf'],$csrf)) { throw new DomainException('access'); }
            $operation = Tools::getValue('operation');
            if ($operation === 'login') {
                $_SESSION['attempts'] = array_values(array_filter($_SESSION['attempts'] ?? array(), function($t) { return $t > time()-3600; }));
                if (count($_SESSION['attempts']) >= 10) { throw new DomainException('attempts'); }
                $_SESSION['attempts'][] = time();
                $reference = Tools::getValue('reference',''); $code = Tools::getValue('code','');
                if (!is_string($reference) || !preg_match('/^(?:CA-)?0*([1-9][0-9]*)$/Di',$reference,$m)
                    || !is_string($code) || !preg_match('/^[a-f0-9]{64}$/D',$code) || !ColemanTenancy::authorized((int)$m[1],hash('sha256',$code))) { throw new DomainException('access'); }
                session_regenerate_id(true);
                $_SESSION['rental'] = (int)$m[1]; $_SESSION['grant'] = hash('sha256',$code); $_SESSION['expires'] = time()+12*3600; $_SESSION['csrf'] = bin2hex(random_bytes(32));
            } elseif ($operation === 'logout') {
                $_SESSION = array(); session_regenerate_id(true);
            } else {
                if (!$this->rental) { throw new DomainException('access'); }
                if ($operation === 'file_download') { ColemanTenancy::download($this->rental,Tools::getValue('file_id')); }
                if ($operation === 'receipt') { ColemanTenancy::export($this->rental,Tools::getValue('entry_id')); }
                ColemanTenancy::change($_SESSION['rental'], Tools::getValue('revision'), $operation, $_POST, 0, $_FILES['document'] ?? null, $_SESSION['grant']);
            }
            header('Location: /portal?section='.ColemanTenancy::section(Tools::getValue('section')).'&saved=1',true,303); exit;
        } catch (DomainException $e) {
            http_response_code($e->getMessage() === 'stale' ? 409 : ($e->getMessage() === 'access' ? 403 : 422));
            $this->portalErrors[] = ColemanTenancy::errorMessage($e->getMessage());
        } catch (Throwable $e) {
            error_log('Coleman portal failed: '.get_class($e)); http_response_code(503);
            $this->portalErrors[] = $this->module->l('The record could not be saved. Please reload and try again.','portal');
        }
    }

    /** Render the shared rental components in the public site's visual language. */
    public function initContent()
    {
        $this->context->smarty->assign(ColemanTenancy::viewData($this->rental,true,'/portal',$this->portalErrors ? $_POST : array()));
        $this->context->smarty->assign(array('portal_errors'=>$this->portalErrors,'portal_csrf'=>$_SESSION['csrf'],'access_code'=>null,
            'host_language'=>$this->context->language->iso_code,'rental_saved'=>Tools::getValue('saved') === '1',
            'rental_section'=>ColemanTenancy::section(Tools::getValue('section'))));
    }

    /** Avoid the native checkout shell. */
    public function display() { $this->context->smarty->display(_PS_MODULE_DIR_.'colemanstay/views/templates/front/portal.tpl'); }
}
