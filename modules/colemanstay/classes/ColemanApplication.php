<?php
/** Long-term rental enquiries and host progress, separate from stay reservations. OSL-3.0. */
class ColemanApplication extends ObjectModel
{
    public $id_product;
    public $guest_name;
    public $email;
    public $move_in;
    public $duration;
    public $occupants;
    public $pets;
    public $message;
    public $request_token;
    public $date_add;

    public static $definition = array(
        'table' => 'coleman_application', 'primary' => 'id_coleman_application',
        'fields' => array(
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'guest_name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 120, 'required' => true),
            'email' => array('type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 254, 'required' => true),
            'move_in' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'allow_null' => true),
            'duration' => array('type' => self::TYPE_STRING, 'size' => 80, 'required' => true),
            'occupants' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'pets' => array('type' => self::TYPE_STRING, 'size' => 500, 'required' => true),
            'message' => array('type' => self::TYPE_STRING, 'size' => 2000),
            'request_token' => array('type' => self::TYPE_STRING, 'size' => 64, 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    /** Create additive tables without changing existing enquiries or rental terms. */
    public static function installSchema()
    {
        $db = Db::getInstance();
        foreach (array(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_application` (
                id_coleman_application INT UNSIGNED NOT NULL AUTO_INCREMENT,
                id_product INT UNSIGNED NOT NULL, guest_name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL,
                move_in DATE NULL, duration VARCHAR(80) NOT NULL, occupants TINYINT UNSIGNED NOT NULL,
                pets VARCHAR(500) NOT NULL, message TEXT NOT NULL, request_token CHAR(64) NOT NULL,
                stage VARCHAR(20) NOT NULL DEFAULT 'enquiry', next_action VARCHAR(250) NOT NULL DEFAULT '',
                revision INT UNSIGNED NOT NULL DEFAULT 0, date_add DATETIME NOT NULL,
                PRIMARY KEY (id_coleman_application), UNIQUE KEY (request_token), KEY (id_product, stage)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_application_history` (
                id_coleman_application_history INT UNSIGNED NOT NULL AUTO_INCREMENT,
                application_id INT UNSIGNED NOT NULL, stage VARCHAR(20) NOT NULL,
                next_action VARCHAR(250) NOT NULL, note TEXT NOT NULL, employee_id INT UNSIGNED NOT NULL,
                date_add DATETIME NOT NULL, PRIMARY KEY (id_coleman_application_history), KEY (application_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ) as $sql) {
            if (!$db->execute($sql)) { throw new RuntimeException('Unable to install Coleman applications.'); }
        }
    }

    /** Permit plain-text entries only, including optional blank notes. */
    public static function validText($value, $limit, $required = false)
    {
        return is_string($value) && (!$required || trim($value) !== '') && mb_strlen($value) <= $limit
            && $value === strip_tags($value) && Validate::isCleanHtml($value)
            && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value);
    }

    /** Fixed workflow stages, with closed enquiries retained for history. */
    public static function stages()
    {
        return array('enquiry', 'viewing', 'application', 'review', 'lease', 'move_in', 'closed');
    }

    /** Scope host reads and writes to the configured Coleman apartments. */
    public static function scope()
    {
        return 'id_product IN ('.implode(',', array_map('intval', ColemanCalendar::unitIds())).')';
    }

    /** Read one application without leaking records from other properties. */
    public static function application($id)
    {
        $row = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'coleman_application` WHERE id_coleman_application = '.(int)$id.' AND '.self::scope(), false);
        if (!$row) { throw new DomainException('application_missing'); }
        return $row;
    }

    /** Atomically save stage/next action and append a dated host note; stale edits do not overwrite. */
    public static function progress($id, $revision, $stage, $nextAction, $note, $employeeId)
    {
        if (!is_string($stage) || !in_array($stage, self::stages(), true)
            || !self::validText($nextAction, 250) || !self::validText($note, 4000)) {
            throw new DomainException('application_fields');
        }
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) { throw new RuntimeException('Unable to start application update.'); }
        try {
            if (!$db->execute('UPDATE `'._DB_PREFIX_."coleman_application` SET stage='".pSQL($stage)."', next_action='".pSQL(trim($nextAction))."', revision=revision+1
                WHERE id_coleman_application=".(int)$id.' AND revision='.(int)$revision.' AND '.self::scope())) { throw new RuntimeException('Unable to update application.'); }
            if ($db->Affected_Rows() !== 1) { throw new DomainException('application_stale'); }
            if (!$db->insert('coleman_application_history', array('application_id' => (int)$id,
                'stage' => pSQL($stage), 'next_action' => pSQL(trim($nextAction)), 'note' => pSQL(trim($note)),
                'employee_id' => (int)$employeeId, 'date_add' => date('Y-m-d H:i:s')))) { throw new RuntimeException('Unable to save application history.'); }
            if (!$db->execute('COMMIT')) { throw new RuntimeException('Unable to commit application update.'); }
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }
}
