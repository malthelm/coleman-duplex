<?php
/** Rental details and per-apartment date allocation. OSL-3.0. */
class ColemanCalendar
{
    /** Return only the two installed Coleman apartment IDs. */
    public static function unitIds()
    {
        return array_values(array_filter(array((int)Configuration::get('COLEMAN_PRODUCT_ONE'), (int)Configuration::get('COLEMAN_PRODUCT_TWO'))));
    }

    /** Add blank rental details and a non-destructive calendar ledger. */
    public static function installSchema()
    {
        $db = Db::getInstance();
        foreach (array(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_unit` (
                id_product INT UNSIGNED NOT NULL PRIMARY KEY,
                nightly_rate DECIMAL(8,2) NULL, monthly_rate DECIMAL(8,2) NULL, cleaning_fee DECIMAL(8,2) NULL,
                min_nights SMALLINT UNSIGNED NULL, max_guests TINYINT UNSIGNED NULL,
                house_rules TEXT NULL, owner_confirmed TINYINT UNSIGNED NOT NULL DEFAULT 0,
                revision INT UNSIGNED NOT NULL DEFAULT 0, updated_at DATETIME NULL, updated_by INT UNSIGNED NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_calendar` (
                id_coleman_calendar INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                id_product INT UNSIGNED NOT NULL, request_id INT UNSIGNED NULL,
                arrival DATE NOT NULL, departure DATE NOT NULL, label VARCHAR(160) NOT NULL,
                active TINYINT UNSIGNED NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 0,
                date_add DATETIME NOT NULL, updated_at DATETIME NOT NULL, updated_by INT UNSIGNED NOT NULL,
                UNIQUE KEY (request_id), KEY dates (id_product, active, arrival, departure)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ) as $sql) {
            if (!$db->execute($sql)) { throw new RuntimeException('Calendar schema update failed'); }
        }
        foreach (self::unitIds() as $id) {
            if (!$db->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'coleman_unit` (id_product) VALUES ('.$id.')')) {
                throw new RuntimeException('Apartment settings initialization failed');
            }
        }
    }

    /** Validate date-only stays; departure is exclusive, so adjacent stays are allowed. */
    public static function validRange($arrival, $departure)
    {
        foreach (array($arrival, $departure) as $value) {
            if (!is_string($value)) { return false; }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) { return false; }
        }
        $today = new DateTimeImmutable('today');
        return $arrival >= $today->format('Y-m-d') && $departure > $arrival && $departure <= $today->modify('+2 years')->format('Y-m-d');
    }

    /** Read draft or confirmed details without inventing unknown values. */
    public static function unit($id)
    {
        if (!in_array((int)$id, self::unitIds(), true)) { throw new DomainException('unit'); }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'coleman_unit` WHERE id_product = '.(int)$id, false);
    }

    /** Validate and save one apartment; optimistic revision checks protect concurrent edits. */
    public static function saveUnit($id, $revision, $values, $actor)
    {
        self::unit($id);
        $data = array();
        foreach (array('nightly_rate', 'monthly_rate', 'cleaning_fee') as $field) {
            $value = $values[$field] ?? '';
            if (!is_string($value) || ($value !== '' && (!preg_match('/^\d{1,6}(\.\d{1,2})?$/D', $value) || ($field !== 'cleaning_fee' && (float)$value <= 0)))) {
                throw new DomainException('money');
            }
            $data[$field] = $value === '' ? null : $value;
        }
        foreach (array('min_nights' => 730, 'max_guests' => 8) as $field => $max) {
            $value = $values[$field] ?? '';
            if (!is_string($value) || ($value !== '' && (!ctype_digit($value) || (int)$value < 1 || (int)$value > $max))) { throw new DomainException('limits'); }
            $data[$field] = $value === '' ? null : (int)$value;
        }
        $rules = $values['house_rules'] ?? '';
        if (!is_string($rules) || mb_strlen($rules) > 4000 || $rules !== strip_tags($rules)) { throw new DomainException('rules'); }
        $data['house_rules'] = pSQL(trim($rules));
        $data['owner_confirmed'] = ($values['owner_confirmed'] ?? '') === '1' ? 1 : 0;
        if ($data['owner_confirmed'] && (($data['nightly_rate'] === null && $data['monthly_rate'] === null)
            || $data['cleaning_fee'] === null || $data['min_nights'] === null || $data['max_guests'] === null || !$data['house_rules'])) {
            throw new DomainException('incomplete');
        }
        $data['revision'] = (int)$revision + 1;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = (int)$actor;
        $db = Db::getInstance();
        if (!$db->update('coleman_unit', $data, 'id_product = '.(int)$id.' AND revision = '.(int)$revision, 0, true)) { throw new RuntimeException('Settings save failed'); }
        if ($db->Affected_Rows() !== 1) { throw new DomainException('stale'); }
    }

    /** Check blocked nights without exposing private labels or guest details. */
    public static function available($id, $arrival, $departure)
    {
        if (!in_array((int)$id, self::unitIds(), true)) { return false; }
        return !Db::getInstance()->getValue('SELECT id_coleman_calendar FROM `'._DB_PREFIX_."coleman_calendar` WHERE id_product = ".(int)$id.
            " AND active = 1 AND arrival < '".pSQL($departure)."' AND departure > '".pSQL($arrival)."'", false);
    }

    /** Serialize allocation on an existing apartment row, even when its calendar is empty. */
    private static function lockUnit($id)
    {
        if (!in_array((int)$id, self::unitIds(), true)) { throw new DomainException('unit'); }
        if (!Db::getInstance()->executeS('SELECT id_product FROM `'._DB_PREFIX_.'coleman_unit` WHERE id_product = '.(int)$id.' FOR UPDATE', true, false)) {
            throw new RuntimeException('Apartment calendar not initialized');
        }
    }

    /** Block dates, atomically refusing any overlap with a manual block or reservation. */
    public static function block($id, $arrival, $departure, $label, $actor)
    {
        if (!self::validRange($arrival, $departure)) { throw new DomainException('dates'); }
        if (!is_string($label) || !trim($label) || mb_strlen($label) > 160 || $label !== strip_tags($label)) { throw new DomainException('label'); }
        $db = Db::getInstance(); $db->execute('START TRANSACTION');
        try {
            self::lockUnit($id);
            if (!self::available($id, $arrival, $departure)) { throw new DomainException('overlap'); }
            $now = date('Y-m-d H:i:s');
            if (!$db->insert('coleman_calendar', array('id_product' => (int)$id, 'arrival' => pSQL($arrival), 'departure' => pSQL($departure),
                'label' => pSQL(trim($label)), 'date_add' => $now, 'updated_at' => $now, 'updated_by' => (int)$actor))) { throw new RuntimeException('Date block save failed'); }
            $blockId = $db->Insert_ID();
            $db->execute('COMMIT');
            return $blockId;
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }

    /** Add a reviewed snapshot as manual blocks; conflicts roll back the entire import. */
    public static function importRanges($id, $ranges, $label, $actor)
    {
        if (!is_array($ranges) || count($ranges) > 500 || !is_string($label) || !trim($label) || mb_strlen($label) > 160 || strip_tags($label) !== $label) { throw new DomainException('ical_file'); }
        $db = Db::getInstance(); $db->execute('START TRANSACTION');
        try {
            self::lockUnit($id); $added = 0;
            foreach ($ranges as $range) {
                if (!isset($range['arrival'], $range['departure']) || !self::validRange($range['arrival'], $range['departure'])) { throw new DomainException('dates'); }
                $overlaps = $db->executeS('SELECT arrival, departure FROM `'._DB_PREFIX_."coleman_calendar` WHERE id_product=".(int)$id." AND active=1 AND arrival < '".pSQL($range['departure'])."' AND departure > '".pSQL($range['arrival'])."'", true, false);
                $covered = false;
                foreach ($overlaps as $block) { if ($block['arrival'] <= $range['arrival'] && $block['departure'] >= $range['departure']) { $covered = true; break; } }
                if ($covered) { continue; }
                if ($overlaps) { throw new DomainException('overlap'); }
                $now = date('Y-m-d H:i:s');
                if (!$db->insert('coleman_calendar', array('id_product' => (int)$id, 'arrival' => pSQL($range['arrival']), 'departure' => pSQL($range['departure']),
                    'label' => pSQL($label), 'date_add' => $now, 'updated_at' => $now, 'updated_by' => (int)$actor))) { throw new RuntimeException('Calendar import failed'); }
                $added++;
            }
            $db->execute('COMMIT'); return $added;
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }

    /** Release a manual block while retaining its record and rejecting stale forms. */
    public static function unblock($id, $revision, $actor)
    {
        $db = Db::getInstance();
        $row = $db->getRow('SELECT id_product FROM `'._DB_PREFIX_.'coleman_calendar` WHERE id_coleman_calendar = '.(int)$id, false);
        if (!$row) { throw new DomainException('stale'); }
        $db->execute('START TRANSACTION');
        try {
            self::lockUnit((int)$row['id_product']);
            if (!$db->execute('UPDATE `'._DB_PREFIX_."coleman_calendar` SET active = 0, revision = revision + 1, updated_at = '".pSQL(date('Y-m-d H:i:s'))."', updated_by = ".(int)$actor.
                ' WHERE id_coleman_calendar = '.(int)$id.' AND revision = '.(int)$revision.' AND active = 1 AND request_id IS NULL')) { throw new RuntimeException('Date block release failed'); }
            if ($db->Affected_Rows() !== 1) { throw new DomainException('stale'); }
            $db->execute('COMMIT');
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }

    /** Reserve an approved request, or release its dates back to approved follow-up. */
    public static function reserve($id, $revision, $release, $actor)
    {
        $db = Db::getInstance();
        $row = $db->getRow('SELECT id_product FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.(int)$id, false);
        if (!$row) { throw new DomainException('stale'); }
        $db->execute('START TRANSACTION');
        try {
            self::lockUnit((int)$row['id_product']);
            $locked = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.(int)$id.' FOR UPDATE', true, false);
            $request = $locked ? $locked[0] : null;
            if (!$request || (int)$request['revision'] !== (int)$revision || $request['status'] !== ($release ? 'reserved' : 'approved')) { throw new DomainException('stale'); }
            $now = pSQL(date('Y-m-d H:i:s'));
            if ($release) {
                if (!$db->execute('UPDATE `'._DB_PREFIX_."coleman_calendar` SET active = 0, revision = revision + 1, updated_at = '".$now."', updated_by = ".(int)$actor.' WHERE request_id = '.(int)$id.' AND active = 1')) { throw new RuntimeException('Reservation release failed'); }
                if ($db->Affected_Rows() !== 1) { throw new RuntimeException('Reserved request has no allocated dates'); }
            } else {
                if (!self::validRange($request['arrival'], $request['departure'])) { throw new DomainException('dates'); }
                if (!self::available($request['id_product'], $request['arrival'], $request['departure'])) { throw new DomainException('overlap'); }
                $unit = self::unit($request['id_product']);
                $nights = (new DateTimeImmutable($request['arrival']))->diff(new DateTimeImmutable($request['departure']))->days;
                if ($unit['owner_confirmed'] && ((int)$request['guests'] > (int)$unit['max_guests'] || $nights < (int)$unit['min_nights'])) { throw new DomainException('stay_limits'); }
                if (!$db->execute('INSERT INTO `'._DB_PREFIX_."coleman_calendar` (id_product, request_id, arrival, departure, label, date_add, updated_at, updated_by)
                    VALUES (".(int)$request['id_product'].','.(int)$id.",'".pSQL($request['arrival'])."','".pSQL($request['departure'])."','CD-".str_pad((int)$id, 5, '0', STR_PAD_LEFT)."','".$now."','".$now."',".(int)$actor.")
                    ON DUPLICATE KEY UPDATE active = 1, revision = revision + 1, updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)")) { throw new RuntimeException('Reservation allocation failed'); }
            }
            if (!$db->execute('UPDATE `'._DB_PREFIX_."coleman_request` SET status = '".($release ? 'approved' : 'reserved')."', revision = revision + 1, reviewed_at = '".$now."', reviewed_by = ".(int)$actor.' WHERE id_coleman_request = '.(int)$id)) { throw new RuntimeException('Reservation status save failed'); }
            $db->execute('COMMIT');
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }
}
