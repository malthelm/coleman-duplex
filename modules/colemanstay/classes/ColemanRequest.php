<?php
/** Persist a booking enquiry without allocating inventory. OSL-3.0. */
class ColemanRequest extends ObjectModel
{
    public $id_product;
    public $guest_name;
    public $email;
    public $arrival;
    public $departure;
    public $guests;
    public $message;
    public $request_token;
    public $date_add;

    /** Atomically review one request; revision prevents stale or double submissions. */
    public static function review($id, $revision, $decision, $employeeId)
    {
        if (!in_array($decision, array('pending', 'approved', 'declined'), true)) { return false; }
        $db = Db::getInstance();
        $from = $decision === 'pending' ? "status IN ('approved', 'declined')" : "status = 'pending'";
        $sql = 'UPDATE `'._DB_PREFIX_."coleman_request` SET status = '".pSQL($decision)."', revision = revision + 1,
            reviewed_at = NOW(), reviewed_by = ".(int)$employeeId.' WHERE id_coleman_request = '.(int)$id.' AND revision = '.(int)$revision.' AND '.$from.'
            AND id_product IN ('.(int)Configuration::get('COLEMAN_PRODUCT_ONE').','.(int)Configuration::get('COLEMAN_PRODUCT_TWO').')';
        if (!$db->execute($sql)) { throw new RuntimeException('Unable to save request review.'); }
        return $db->Affected_Rows() === 1;
    }

    public static $definition = array(
        'table' => 'coleman_request', 'primary' => 'id_coleman_request',
        'fields' => array(
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'guest_name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 120, 'required' => true),
            'email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 254, 'required' => true),
            'arrival' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'departure' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'guests' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'message' => array('type' => self::TYPE_STRING, 'size' => 2000),
            'request_token' => array('type' => self::TYPE_STRING, 'size' => 64, 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );
}
