<?php
/** Editable, unsent guest replies tied to a request revision. OSL-3.0. */
class ColemanMessage
{
    /** Add private message drafts without enabling a mail transport. */
    public static function installSchema()
    {
        if (!Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_."coleman_message` (
            request_id INT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL,
            subject VARCHAR(180) NOT NULL, body TEXT NOT NULL,
            request_revision INT UNSIGNED NOT NULL, revision INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL, updated_by INT UNSIGNED NOT NULL,
            PRIMARY KEY (request_id, kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) { throw new RuntimeException('Message schema update failed'); }
    }

    /** Read only requests belonging to the two Coleman apartments. */
    public static function request($id, $lock = false)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'coleman_request` WHERE id_coleman_request = '.(int)$id.
            ' AND id_product IN ('.(int)Configuration::get('COLEMAN_PRODUCT_ONE').','.(int)Configuration::get('COLEMAN_PRODUCT_TWO').')'.($lock ? ' FOR UPDATE' : ''), true, false);
        if (!$rows) { throw new DomainException('missing_request'); }
        return $rows[0];
    }

    /** Read the saved draft for one decision without regenerating edited wording. */
    public static function draft($id, $kind)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_."coleman_message` WHERE request_id = ".(int)$id." AND kind = '".pSQL($kind)."'", false);
    }

    /** Require a reviewed decision and its exact revision before saving or exporting a reply. */
    private static function checkRequest($request, $kind, $revision)
    {
        if (!in_array($kind, array('approved', 'declined', 'reserved'), true) || $request['status'] !== $kind || (int)$request['revision'] !== (int)$revision) {
            throw new DomainException('message_stale');
        }
    }

    /** Save plain-text edits, rejecting another host's edits and changed request decisions. */
    public static function save($id, $kind, $requestRevision, $revision, $subject, $body, $actor)
    {
        if (!is_string($subject) || !trim($subject) || mb_strlen($subject) > 180 || preg_match('/[\x00-\x1F\x7F]/', $subject) || $subject !== strip_tags($subject)
            || !is_string($body) || !trim($body) || mb_strlen($body) > 10000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body) || $body !== strip_tags($body)) {
            throw new DomainException('message_content');
        }
        $db = Db::getInstance(); $db->execute('START TRANSACTION');
        try {
            $request = self::request($id, true);
            self::checkRequest($request, $kind, $requestRevision);
            $draft = self::draft($id, $kind);
            if (($draft ? (int)$draft['revision'] : 0) !== (int)$revision) { throw new DomainException('draft_stale'); }
            $data = array('subject' => pSQL(trim($subject)), 'body' => pSQL(str_replace(array("\r\n", "\r"), "\n", trim($body))),
                'request_revision' => (int)$requestRevision, 'revision' => (int)$revision + 1,
                'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => (int)$actor);
            $saved = $draft ? $db->update('coleman_message', $data, 'request_id = '.(int)$id." AND kind = '".pSQL($kind)."'")
                : $db->insert('coleman_message', array_merge($data, array('request_id' => (int)$id, 'kind' => pSQL($kind))));
            if (!$saved) { throw new RuntimeException('Message save failed'); }
            $db->execute('COMMIT');
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }

    /** Build a saved email draft for the host to review; this method never sends mail. */
    public static function emailDraft($id, $kind, $requestRevision, $revision)
    {
        $db = Db::getInstance(); $db->execute('START TRANSACTION');
        try {
            $request = self::request($id, true);
            self::checkRequest($request, $kind, $requestRevision);
            $draft = self::draft($id, $kind);
            if (!$draft || (int)$draft['revision'] !== (int)$revision || (int)$draft['request_revision'] !== (int)$requestRevision) { throw new DomainException('draft_stale'); }
            if (!Validate::isEmail($request['email']) || preg_match('/[\r\n]/', $request['email'])) { throw new RuntimeException('Invalid draft recipient'); }
            // Fold encoded words below the RFC header line limit; base64 body keeps Unicode and CRLF safe.
            $subject = iconv_mime_encode('Subject', $draft['subject'], array('scheme' => 'B', 'input-charset' => 'UTF-8', 'output-charset' => 'UTF-8', 'line-length' => 76, 'line-break-chars' => "\r\n"));
            if ($subject === false) { throw new RuntimeException('Subject encoding failed'); }
            $result = 'To: '.$request['email']."\r\n".$subject."\r\nX-Unsent: 1\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".
                chunk_split(base64_encode(str_replace("\n", "\r\n", $draft['body'])), 76, "\r\n");
            $db->execute('COMMIT');
            return $result;
        } catch (Throwable $error) { $db->execute('ROLLBACK'); throw $error; }
    }
}
