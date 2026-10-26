<?php
/** Private rental records for the two Coleman apartments. OSL-3.0. */
class ColemanTenancy
{
    /** Add isolated tables; existing applications and reservations are untouched. */
    public static function schema()
    {
        return array(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_tenancy` (application_id INT UNSIGNED PRIMARY KEY, revision INT UNSIGNED NOT NULL DEFAULT 0, access_hash CHAR(64) NOT NULL DEFAULT '', access_expires DATETIME NULL, data LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_tenancy_history` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id INT UNSIGNED NOT NULL, revision INT UNSIGNED NOT NULL, action VARCHAR(40) NOT NULL, employee_id INT UNSIGNED NOT NULL, date_add DATETIME NOT NULL, KEY(application_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        );
    }

    /** Run additive deployment migration. */
    public static function installSchema()
    {
        foreach (self::schema() as $sql) { if (!Db::getInstance()->execute($sql)) { throw new RuntimeException('Rental schema failed.'); } }
    }

    /** Empty records deliberately contain no assumed rental terms. */
    public static function defaults()
    {
        return array('profile' => array('phone'=>'', 'address'=>'', 'household'=>'', 'history'=>'', 'work'=>'', 'references'=>'', 'notes'=>'', 'submitted'=>''),
            'lease' => array('start'=>'', 'end'=>'', 'rent'=>'', 'deposit_due'=>'', 'deposit_received'=>'', 'signature'=>'not_sent', 'signed_on'=>'', 'keys'=>false, 'inventory'=>false, 'utilities'=>false, 'maintenance_enabled'=>false),
            'charges'=>array(), 'payments'=>array(), 'maintenance'=>array(), 'files'=>array());
    }

    /** Scope a rental through its parent enquiry before returning any private data. */
    public static function read($id)
    {
        $application = ColemanApplication::application($id);
        $row = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'coleman_tenancy` WHERE application_id='.(int)$id, false);
        if (!$row) { $row = array('application_id'=>(int)$id, 'revision'=>0, 'access_hash'=>'', 'access_expires'=>null, 'data'=>''); }
        $row['record'] = $row['data'] === '' ? self::defaults() : json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
        $row['application'] = $application;
        unset($row['data']);
        return $row;
    }

    /** Validate plain text without accepting arrays, HTML or control characters. */
    private static function text($input, $key, $limit, $required = false)
    {
        $value = $input[$key] ?? '';
        if (!ColemanApplication::validText($value, $limit, $required)) { throw new DomainException('fields'); }
        return trim($value);
    }

    /** Money is parsed as integer cents; blank means unknown. */
    public static function money($value, $required = false)
    {
        if ($value === '' && !$required) { return null; }
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?$/D', $value, $m)) { throw new DomainException('money'); }
        $cents = (int)$m[1]*100 + (int)str_pad($m[2] ?? '', 2, '0');
        if ($required && $cents < 1) { throw new DomainException('money'); }
        return $cents;
    }

    /** Strict ISO dates, optionally blank for undecided terms. */
    private static function date($value, $required = false)
    {
        if ($value === '' && !$required) { return ''; }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2100-12-31') { throw new DomainException('dates'); }
        return $value;
    }

    /** Tenant sessions must still match the current, unexpired host-issued grant. */
    public static function authorized($id, $hash)
    {
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) { return false; }
        try { $row = self::read($id); } catch (DomainException $e) { return false; }
        return $row['access_hash'] !== '' && hash_equals($row['access_hash'], $hash)
            && $row['access_expires'] !== null && $row['access_expires'] > gmdate('Y-m-d H:i:s');
    }

    /** Sum active manual payments for one charge. */
    public static function paid($record, $chargeId)
    {
        $total = 0;
        foreach ($record['payments'] as $payment) { if ($payment['charge_id'] === $chargeId && $payment['void_reason'] === '') { $total += $payment['amount']; } }
        return $total;
    }

    /** Serialize all changes for a rental and reject stale/replayed forms. */
    public static function change($id, $revision, $action, $input, $employeeId, $file = null, $grantHash = null)
    {
        $host = $employeeId > 0;
        $allowed = $host ? array('access_issue','access_revoke','reopen','lease_save','lease_upload','charge_add','charge_void','payment_add','payment_void','maintenance_update') : array('profile_save','profile_submit','maintenance_add');
        if (!in_array($action, $allowed, true) || !is_string($revision) || !ctype_digit($revision)) { throw new DomainException('fields'); }
        ColemanApplication::application($id);
        $db = Db::getInstance(); $createdFile = null; $code = null;
        if (!$db->execute('START TRANSACTION')) { throw new RuntimeException('Transaction failed.'); }
        try {
            if (!$db->execute('INSERT IGNORE INTO `'._DB_PREFIX_."coleman_tenancy` (application_id,data) VALUES (".(int)$id.",'".pSQL(json_encode(self::defaults()))."')")) { throw new RuntimeException('Rental insert failed.'); }
            // Db::getRow appends LIMIT, so use executeS for the locking query.
            $rows = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'coleman_tenancy` WHERE application_id='.(int)$id.' FOR UPDATE', true, false);
            $row = $rows[0];
            if ((int)$row['revision'] !== (int)$revision) { throw new DomainException('stale'); }
            if (!$host && (!is_string($grantHash) || $row['access_hash'] === '' || !hash_equals($row['access_hash'], $grantHash) || $row['access_expires'] <= gmdate('Y-m-d H:i:s'))) { throw new DomainException('access'); }
            $r = json_decode($row['data'], true, 512, JSON_THROW_ON_ERROR);
            if ($action === 'access_issue') {
                $code = bin2hex(random_bytes(32)); $row['access_hash'] = hash('sha256', $code); $row['access_expires'] = gmdate('Y-m-d H:i:s', time()+30*86400);
            } elseif ($action === 'access_revoke') { $row['access_hash'] = ''; $row['access_expires'] = null;
            } elseif ($action === 'reopen') { $r['profile']['submitted'] = '';
            } elseif ($action === 'profile_save' || $action === 'profile_submit') {
                if ($r['profile']['submitted'] !== '') { throw new DomainException('submitted'); }
                foreach (array('phone'=>40,'address'=>500,'household'=>1000,'history'=>2000,'work'=>1000,'references'=>1000,'notes'=>2000) as $key=>$limit) {
                    $r['profile'][$key] = self::text($input, $key, $limit, $action === 'profile_submit' && in_array($key, array('phone','address','household','history'), true));
                }
                if ($action === 'profile_submit') {
                    if (($input['confirm'] ?? '') !== '1') { throw new DomainException('confirm'); }
                    $r['profile']['submitted'] = gmdate('Y-m-d H:i:s');
                }
            } elseif ($action === 'lease_save') {
                $l = $r['lease'];
                $l['start'] = self::date($input['start'] ?? ''); $l['end'] = self::date($input['end'] ?? '');
                if ($l['end'] !== '' && ($l['start'] === '' || $l['end'] < $l['start'])) { throw new DomainException('dates'); }
                foreach (array('rent','deposit_due','deposit_received') as $key) { $c = self::money($input[$key] ?? ''); $l[$key] = $c === null ? '' : number_format($c/100, 2, '.', ''); }
                $l['signature'] = $input['signature'] ?? '';
                if (!in_array($l['signature'], array('not_sent','sent','partially_signed','signed'), true)) { throw new DomainException('fields'); }
                $l['signed_on'] = self::date($input['signed_on'] ?? '', $l['signature'] === 'signed');
                if ($l['signature'] !== 'signed' && $l['signed_on'] !== '') { throw new DomainException('dates'); }
                foreach (array('keys','inventory','utilities','maintenance_enabled') as $key) { $l[$key] = ($input[$key] ?? '') === '1'; }
                $r['lease'] = $l;
            } elseif ($action === 'lease_upload') {
                if (($input['confirm'] ?? '') !== '1') { throw new DomainException('confirm'); }
                $meta = self::storeFile($file, 'lease', $id); $createdFile = $meta['stored']; $r['files'][] = $meta;
            } elseif ($action === 'charge_add') {
                $period = $input['period'] ?? '';
                if (!is_string($period) || !preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $period)) { throw new DomainException('dates'); }
                foreach ($r['charges'] as $c) { if ($c['period'] === $period && $c['void_reason'] === '') { throw new DomainException('duplicate'); } }
                $r['charges'][] = array('id'=>count($r['charges'])+1,'period'=>$period,'due'=>self::date($input['due'] ?? '', true),'amount'=>self::money($input['amount'] ?? '', true),'void_reason'=>'','created'=>gmdate('Y-m-d H:i:s'));
            } elseif ($action === 'payment_add') {
                $key = self::entry($r['charges'], $input['charge_id'] ?? ''); $charge = $r['charges'][$key];
                $amount = self::money($input['amount'] ?? '', true);
                if ($charge['void_reason'] !== '' || $amount > $charge['amount'] - self::paid($r, $charge['id'])) { throw new DomainException('balance'); }
                $method = $input['method'] ?? '';
                if (!in_array($method, array('bank','cash','card','other'), true)) { throw new DomainException('fields'); }
                $received = self::date($input['received'] ?? '', true);
                if ($received > (new DateTimeImmutable('today',new DateTimeZone('America/Los_Angeles')))->format('Y-m-d')) { throw new DomainException('dates'); }
                $r['payments'][] = array('id'=>count($r['payments'])+1,'charge_id'=>$charge['id'],'amount'=>$amount,'received'=>$received,'method'=>$method,'reference'=>self::text($input,'reference',120),'void_reason'=>'','created'=>gmdate('Y-m-d H:i:s'));
            } elseif ($action === 'charge_void' || $action === 'payment_void') {
                $list = $action === 'charge_void' ? 'charges' : 'payments';
                $key = self::entry($r[$list], $input['entry_id'] ?? '');
                if ($r[$list][$key]['void_reason'] !== '' || ($list === 'charges' && self::paid($r, $r[$list][$key]['id']) > 0)) { throw new DomainException('balance'); }
                $r[$list][$key]['void_reason'] = self::text($input,'reason',500,true); $r[$list][$key]['voided'] = gmdate('Y-m-d H:i:s');
            } elseif ($action === 'maintenance_add') {
                if (!$r['lease']['maintenance_enabled']) { throw new DomainException('maintenance_disabled'); }
                $ticket = array('id'=>count($r['maintenance'])+1, 'title'=>self::text($input,'title',120,true), 'description'=>self::text($input,'description',2000,true), 'status'=>'open','scheduled'=>'','cost'=>'','update'=>'','created'=>gmdate('Y-m-d H:i:s'));
                if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $meta = self::storeFile($file, 'photo', $id); $createdFile = $meta['stored']; $meta['ticket_id'] = $ticket['id']; $r['files'][] = $meta;
                }
                $r['maintenance'][] = $ticket;
            } elseif ($action === 'maintenance_update') {
                $key = self::entry($r['maintenance'], $input['entry_id'] ?? ''); $ticket = $r['maintenance'][$key];
                $ticket['status'] = $input['status'] ?? '';
                if (!in_array($ticket['status'], array('open','scheduled','resolved'), true)) { throw new DomainException('fields'); }
                $ticket['scheduled'] = self::date($input['scheduled'] ?? '', $ticket['status'] === 'scheduled');
                $cost = self::money($input['cost'] ?? ''); $ticket['cost'] = $cost === null ? '' : number_format($cost/100,2,'.','');
                $ticket['update'] = self::text($input,'update',2000);
                $ticket['updates'][] = array('status'=>$ticket['status'],'scheduled'=>$ticket['scheduled'],'cost'=>$ticket['cost'],'update'=>$ticket['update'],'date'=>gmdate('Y-m-d H:i:s'));
                $r['maintenance'][$key] = $ticket;
            }
            $json = json_encode($r, JSON_THROW_ON_ERROR);
            if (strlen($json) > 2097152) { throw new DomainException('record_full'); }
            if (!$db->execute('UPDATE `'._DB_PREFIX_."coleman_tenancy` SET revision=revision+1,data='".pSQL($json)."',access_hash='".pSQL($row['access_hash'])."',access_expires=".($row['access_expires'] === null ? 'NULL' : "'".pSQL($row['access_expires'])."'").' WHERE application_id='.(int)$id)) { throw new RuntimeException('Rental save failed.'); }
            if (!$db->insert('coleman_tenancy_history', array('application_id'=>(int)$id,'revision'=>(int)$revision+1,'action'=>pSQL($action),'employee_id'=>(int)$employeeId,'date_add'=>gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Rental history failed.'); }
            if (!$db->execute('COMMIT')) { throw new RuntimeException('Rental commit failed.'); }
            return $code;
        } catch (Throwable $e) {
            $db->execute('ROLLBACK');
            if ($createdFile !== null && is_file(self::fileRoot().'/'.$createdFile)) { unlink(self::fileRoot().'/'.$createdFile); }
            throw $e;
        }
    }

    /** Locate an immutable list ID; IDs from another rental never select a row. */
    private static function entry($entries, $id)
    {
        if (!is_string($id) || !ctype_digit($id)) { throw new DomainException('fields'); }
        foreach ($entries as $key=>$entry) { if ($entry['id'] === (int)$id) { return $key; } }
        throw new DomainException('missing');
    }

    /** Private files stay on the backed-up site volume, outside direct HTTP access. */
    private static function fileRoot() { return _PS_ROOT_DIR_.'/var/coleman-private'; }

    /** Accept PDF lease versions or re-encoded JPEG/PNG maintenance photos. */
    private static function storeFile($file, $kind, $id)
    {
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) { throw new DomainException('file'); }
        $size = filesize($file['tmp_name']);
        if ($size < 1 || $size > ($kind === 'lease' ? 8 : 5)*1048576) { throw new DomainException('file'); }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $image = null;
        if ($kind === 'lease') {
            if ($mime !== 'application/pdf' || file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') { throw new DomainException('file'); }
            $extension = 'pdf';
        } else {
            $dimensions = @getimagesize($file['tmp_name']);
            if (!in_array($mime,array('image/jpeg','image/png'),true) || !$dimensions || $dimensions[0]*$dimensions[1] > 12000000) { throw new DomainException('file'); }
            $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
            if (!$image) { throw new DomainException('file'); }
            $extension = 'jpg'; $mime = 'image/jpeg';
        }
        $root = self::fileRoot();
        if (!is_dir($root) && !mkdir($root,0700,true)) { throw new RuntimeException('Private storage unavailable.'); }
        if (file_put_contents($root.'/.htaccess', "Require all denied\n") === false) { throw new RuntimeException('Storage protection failed.'); }
        $name = bin2hex(random_bytes(24)).'.'.$extension; $path = $root.'/'.$name;
        $saved = $image ? imagejpeg($image,$path,85) : move_uploaded_file($file['tmp_name'],$path);
        if ($image) { imagedestroy($image); }
        if (!$saved || !chmod($path,0600)) { if (is_file($path)) { unlink($path); } throw new RuntimeException('File save failed.'); }
        return array('id'=>bin2hex(random_bytes(12)),'stored'=>$name,'kind'=>$kind,'mime'=>$mime,'bytes'=>filesize($path),'date'=>gmdate('Y-m-d H:i:s'),'ticket_id'=>0);
    }

    /** Stream only a file listed on this already-authorized rental. */
    public static function download($row, $fileId)
    {
        foreach ($row['record']['files'] as $file) {
            if (is_string($fileId) && hash_equals($file['id'],$fileId)) {
                $path = self::fileRoot().'/'.$file['stored'];
                if (!is_file($path)) { throw new DomainException('missing'); }
                header('Cache-Control: private, no-store'); header('Content-Type: '.$file['mime']);
                header('Content-Disposition: attachment; filename="coleman-'.$file['kind'].'-'.$file['id'].'.'.($file['kind']==='lease'?'pdf':'jpg').'"');
                header("Content-Security-Policy: sandbox; default-src 'none'"); header('X-Content-Type-Options: nosniff');
                readfile($path); exit;
            }
        }
        throw new DomainException('missing');
    }

    /** Allow only the four rental sections. */
    public static function section($value)
    {
        return is_string($value) && in_array($value,array('application','lease','rent','maintenance'),true) ? $value : 'application';
    }

    /** Build shared display data; never expose grant hashes or storage paths to templates. */
    public static function viewData($row, $portal, $url, $posted = array())
    {
        $result = array('rental'=>null,'is_portal'=>$portal,'rental_url'=>$url,'can_review'=>true,'rental_history'=>array());
        if (!$row) { return $result; }
        $r = $row['record'];
        foreach ($r['charges'] as &$c) {
            $c['paid'] = self::paid($r,$c['id']); $c['balance'] = $c['void_reason'] === '' ? $c['amount']-$c['paid'] : 0;
            $c['state'] = $c['void_reason'] !== '' ? 'void' : ($c['balance'] === 0 ? 'paid' : ($c['due'] < (new DateTimeImmutable('today',new DateTimeZone('America/Los_Angeles')))->format('Y-m-d') ? 'overdue' : ($c['paid'] > 0 ? 'partial' : 'due')));
        } unset($c);
        $r['balance'] = array_sum(array_column($r['charges'],'balance'));
        $r['reference'] = 'CA-'.str_pad($row['application_id'],5,'0',STR_PAD_LEFT);
        $r['id'] = (int)$row['application_id']; $r['revision'] = (int)$row['revision']; $r['name'] = $row['application']['guest_name'];
        $r['unit'] = (int)$row['application']['id_product'] === (int)Configuration::get('COLEMAN_PRODUCT_ONE') ? 1 : 2;
        $r['access_expires'] = $row['access_hash'] !== '' ? $row['access_expires'] : null;
        foreach ($r['files'] as &$f) { unset($f['stored']); } unset($f);
        if ($posted) {
            // Retain submitted profile/lease values and the original revision on validation failure.
            if (isset($posted['revision']) && is_string($posted['revision']) && ctype_digit($posted['revision'])) { $r['revision'] = $posted['revision']; }
            $section = in_array($posted['operation'] ?? '',array('profile_save','profile_submit'),true) ? 'profile' : (($posted['operation'] ?? '') === 'lease_save' ? 'lease' : null);
            if ($section) {
                foreach ($r[$section] as $key=>$value) {
                    if ($key === 'submitted') { continue; }
                    $r[$section][$key] = is_bool($value) ? ($posted[$key] ?? '') === '1' : (is_string($posted[$key] ?? null) ? $posted[$key] : '');
                }
            }
        }
        $result['rental'] = $r;
        if (!$portal) { $result['rental_history'] = Db::getInstance()->executeS('SELECT revision,action,employee_id,date_add FROM `'._DB_PREFIX_.'coleman_tenancy_history` WHERE application_id='.(int)$row['application_id'].' ORDER BY id DESC LIMIT 30',true,false); }
        return $result;
    }

    /** Translate safe validation messages, never exception internals. */
    public static function errorMessage($key)
    {
        $messages = array(
            'stale'=>'This rental changed. Reload it before trying again; the previous form was not saved.',
            'fields'=>'Check the required fields and length limits. Use plain text only.',
            'money'=>'Enter a dollar amount with up to two decimal places (maximum 999,999.99). Charges and payments must be positive.',
            'dates'=>'Check the dates. An end date needs a start date, signed leases need a signing date, and received payments cannot be in the future.',
            'submitted'=>'This application was submitted. Ask the host to reopen it before making changes.',
            'confirm'=>'Please tick the acknowledgment before continuing.',
            'duplicate'=>'There is already an active rent charge for this month. Correct or void that record first.',
            'balance'=>'The amount exceeds the outstanding balance, or the entry is void. Void active payments before voiding their charge.',
            'missing'=>'This record or attachment is not available for this rental.',
            'application_missing'=>'This rental is not available.',
            'access'=>'Access could not be verified. Check your rental reference and current code, or sign in again.',
            'attempts'=>'Too many sign-in attempts. Please try again in an hour.',
            'maintenance_disabled'=>'Maintenance requests have not been enabled by the host for this rental.',
            'file'=>'Choose a PDF lease up to 8 MB, or a JPEG/PNG maintenance photo up to 5 MB and 12 megapixels. Please select the file again.',
            'record_full'=>'This rental record has reached its storage limit. Contact the host before adding more entries.',
        );
        return Module::getInstanceByName('colemanstay')->l($messages[$key] ?? $messages['fields'],'ColemanTenancy');
    }

    /** Export a private manual ledger or a single payment receipt. */
    public static function export($row, $receiptId = null)
    {
        header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
        $r = $row['record']; $reference = 'CA-'.str_pad($row['application_id'],5,'0',STR_PAD_LEFT);
        if ($receiptId !== null) {
            $p = $r['payments'][self::entry($r['payments'],$receiptId)];
            header('Content-Type: text/plain; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$reference.'-receipt-'.$p['id'].'.txt"');
            echo "560 Coleman — manual payment record\nRental: $reference\nReceipt: ".$p['id']."\nAmount: USD ".number_format($p['amount']/100,2,'.','')."\nReceived: ".$p['received']."\nMethod: ".$p['method']."\nReference: ".$p['reference']."\nStatus: ".($p['void_reason'] === '' ? 'Recorded' : 'VOID — '.$p['void_reason'])."\nThis records a payment entered by the host. This website did not process it.\n";
        } else {
            header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$reference.'-rent.csv"');
            $out = fopen('php://output','w'); fputcsv($out,array('Rental','Type','ID','Charge ID','Date','Period / method','USD','Reference','Void reason'),',','"','');
            foreach (array('charges','payments') as $kind) {
                foreach ($r[$kind] as $entry) {
                    $values = array($reference,$kind,$entry['id'],$entry['charge_id']??$entry['id'],$entry['received']??$entry['due'],$entry['method']??$entry['period'],number_format($entry['amount']/100,2,'.',''),$entry['reference']??'',$entry['void_reason']);
                    foreach ($values as &$value) { if (is_string($value) && preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u',$value)) { $value = "'".$value; } } unset($value);
                    fputcsv($out,$values,',','"','');
                }
            }
            fclose($out);
        }
        exit;
    }
}
