<?php
/** Rental regression checks using temporary tables only. Run inside the installed container. */
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__.'/test-applications.php';
require_once _PS_MODULE_DIR_.'colemanstay/classes/ColemanTenancy.php';
foreach (ColemanTenancy::schema() as $sql) { $db->execute(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql)); }
$id = 1;
/** Exercise the real transactional change path at the current revision. */
function rentalChange($action,$input=array(),$host=true) {
    global $id,$grant;
    return ColemanTenancy::change($id,(string)ColemanTenancy::read($id)['revision'],$action,$input,$host?1:0,null,$grant??null);
}
applicationCheck(ColemanTenancy::read($id)['record']['lease']['rent'] === '', 'rental starts without invented terms');
$code = rentalChange('access_issue'); $grant = hash('sha256',$code);
applicationCheck(ColemanTenancy::authorized($id,$grant) && !ColemanTenancy::authorized($id,str_repeat('0',64)), 'only current access grant authorizes portal');
applicationReject('fields',function(){ rentalChange('lease_save',array(),false); });
applicationReject('stale',function()use($id){ ColemanTenancy::change($id,'0','access_revoke',array(),1); });
applicationReject('access',function()use($id){ ColemanTenancy::change($id,(string)ColemanTenancy::read($id)['revision'],'profile_save',array(),0,null,str_repeat('a',64)); });
rentalChange('profile_save',array('phone'=>'555-0100','notes'=>'Draft & details'),false);
applicationReject('fields',function(){ rentalChange('profile_submit',array('confirm'=>'1'),false); });
$profile = array('phone'=>'555-0100','address'=>'Synthetic address','household'=>'Two adults','history'=>'Synthetic rental history','confirm'=>'1');
rentalChange('profile_submit',$profile,false);
applicationCheck(ColemanTenancy::read($id)['record']['profile']['submitted'] !== '', 'full application submits');
applicationReject('submitted',function()use($profile){ rentalChange('profile_save',$profile,false); });
rentalChange('reopen'); rentalChange('profile_submit',$profile,false);
applicationReject('dates',function(){ rentalChange('lease_save',array('signature'=>'signed')); });
$lease = array('start'=>'2026-10-01','end'=>'2027-09-30','rent'=>'2000.00','deposit_due'=>'2000','deposit_received'=>'1000.50','signature'=>'signed','signed_on'=>'2026-09-30','keys'=>'1','maintenance_enabled'=>'1');
rentalChange('lease_save',$lease);
applicationCheck(ColemanTenancy::read($id)['record']['lease']['deposit_received']==='1000.50','lease and deposit preserve exact cents');
applicationReject('money',function(){ rentalChange('charge_add',array('period'=>'2026-10','due'=>'2026-10-01','amount'=>'-10')); });
rentalChange('charge_add',array('period'=>'2026-10','due'=>'2026-10-01','amount'=>'2000.50'));
applicationReject('duplicate',function(){ rentalChange('charge_add',array('period'=>'2026-10','due'=>'2026-10-01','amount'=>'2000.50')); });
$payment=array('charge_id'=>'1','amount'=>'750.25','received'=>'2026-10-01','method'=>'bank','reference'=>'=SUM(1,2)');
rentalChange('payment_add',$payment);
$row=ColemanTenancy::read($id); $view=ColemanTenancy::viewData($row,false,'/fixture');
applicationCheck($view['rental']['balance']===125025 && $view['rental']['charges'][0]['state']==='overdue','partial payment gives exact overdue balance');
applicationReject('balance',function()use($payment){ rentalChange('payment_add',array_merge($payment,array('amount'=>'1250.26'))); });
applicationReject('balance',function(){ rentalChange('charge_void',array('entry_id'=>'1','reason'=>'Correction')); });
rentalChange('payment_void',array('entry_id'=>'1','reason'=>'Synthetic correction'));
rentalChange('charge_void',array('entry_id'=>'1','reason'=>'Synthetic correction'));
rentalChange('charge_add',array('period'=>'2026-10','due'=>'2026-10-01','amount'=>'100'));
rentalChange('payment_add',array_merge($payment,array('charge_id'=>'2','amount'=>'100')));
$row=ColemanTenancy::read($id); $view=ColemanTenancy::viewData($row,false,'/fixture');
applicationCheck($view['rental']['balance']===0 && count($row['record']['payments'])===2 && $row['record']['payments'][0]['void_reason']!=='','corrections preserve originals and paid balance');
rentalChange('maintenance_add',array('title'=>'Demo tap','description'=>'Synthetic drip & photo'),false);
rentalChange('maintenance_update',array('entry_id'=>'1','status'=>'scheduled','scheduled'=>'2026-11-01','cost'=>'25.50','update'=>'Synthetic visit arranged'));
rentalChange('maintenance_update',array('entry_id'=>'1','status'=>'resolved','scheduled'=>'2026-11-01','cost'=>'25.50','update'=>'Synthetic repair complete'));
applicationCheck(count(ColemanTenancy::read($id)['record']['maintenance'][0]['updates'])===2,'maintenance retains progress history');
$lease['maintenance_enabled']=''; rentalChange('lease_save',$lease);
applicationReject('maintenance_disabled',function(){ rentalChange('maintenance_add',array('title'=>'Demo','description'=>'Fixture'),false); });
applicationReject('file',function(){ rentalChange('lease_upload',array('confirm'=>'1')); });
rentalChange('access_revoke');
applicationCheck(!ColemanTenancy::authorized($id,$grant),'revocation invalidates existing portal session grant');
$code=rentalChange('access_issue');$grant=hash('sha256',$code);
$db->execute('UPDATE `'._DB_PREFIX_."coleman_tenancy` SET access_expires='2000-01-01 00:00:00' WHERE application_id=".$id);
applicationCheck(!ColemanTenancy::authorized($id,$grant),'expired access code rejected');
// Host controllers still enforce native edit permission and CSRF for new actions.
$_GET=array('view'=>'tenancy','application_id'=>'1'); $_POST=array('decision'=>'tenant_action','operation'=>'access_issue','application_id'=>'1','revision'=>(string)ColemanTenancy::read(1)['revision'],'token'=>'invalid'); $_SERVER['REQUEST_METHOD']='POST';
$controller=new AdminColemanRequestsController(); $context->controller=$controller; $controller->initProcess();$before=ColemanTenancy::read(1)['revision'];$controller->postProcess();
applicationCheck(http_response_code()===403 && ColemanTenancy::read(1)['revision']===$before,'rental host actions enforce CSRF');
$_POST['token']=$controller->token;$controller->tabAccess['edit']=0;$controller->postProcess();
applicationCheck(http_response_code()===403 && ColemanTenancy::read(1)['revision']===$before,'view-only hosts cannot mutate rentals');
$_POST=array(); $_SERVER['REQUEST_METHOD']='GET';
foreach(array('application','lease','rent','maintenance') as $section) {
    $_GET=array('view'=>'tenancy','application_id'=>'1','section'=>$section);
    $controller=new AdminColemanRequestsController();$context->controller=$controller;$controller->initProcess();$controller->initContent();
    $html=$context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/admin/requests.tpl');
    applicationCheck(strpos($html,'CA-00001')!==false,'host template renders '.$section);
    if(getenv('COLEMAN_PREVIEW_DIR')){file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/host-'.$section.'.html',preg_replace('/token=[a-zA-Z0-9_-]+/','token=fixture',$html));}
    $context->smarty->assign(ColemanTenancy::viewData(ColemanTenancy::read(1),true,'/portal'));
    $context->smarty->assign(array('portal_errors'=>array(),'portal_csrf'=>'fixture','access_code'=>null,'rental_section'=>$section,'rental_saved'=>false));
    $html=$context->smarty->fetch(_PS_MODULE_DIR_.'colemanstay/views/templates/front/portal.tpl');
    applicationCheck(strpos($html,'CA-00001')!==false && strpos($html,'access_issue')===false && strpos($html,'lease_save')===false,'portal template restricts host controls '.$section);
    if(getenv('COLEMAN_PREVIEW_DIR')){file_put_contents(getenv('COLEMAN_PREVIEW_DIR').'/portal-'.$section.'.html',$html);}
}
applicationCheck((int)$db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'coleman_calendar`',false)===0,'rental workflows never reserve calendar dates');
echo "Rental preflight complete; all test data is temporary.\n";
