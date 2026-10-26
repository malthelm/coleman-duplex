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
