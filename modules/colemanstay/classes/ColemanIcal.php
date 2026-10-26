<?php
/** Date-only iCalendar exchange for the private host calendar. OSL-3.0. */
class ColemanIcal
{
    /** Export a snapshot without guest names, request references, private labels or imported text. */
    public static function export($id)
    {
        ColemanCalendar::unit($id);
        $today = date('Y-m-d');
        $rows = Db::getInstance()->executeS('SELECT id_coleman_calendar, arrival, departure, updated_at, revision FROM `'._DB_PREFIX_."coleman_calendar` WHERE id_product = ".(int)$id." AND active = 1 AND departure > '".pSQL($today)."' ORDER BY arrival, id_coleman_calendar", true, false);
        $lines = array('BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Coleman Duplex//Private Availability//EN', 'CALSCALE:GREGORIAN');
        foreach ($rows as $row) {
            $uid = substr(hash_hmac('sha256', $id.':'.$row['id_coleman_calendar'], _COOKIE_KEY_), 0, 32).'@coleman.invalid';
            $updated = (new DateTimeImmutable($row['updated_at']))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $lines = array_merge($lines, array('BEGIN:VEVENT', 'UID:'.$uid, 'DTSTAMP:'.$updated,
                'DTSTART;VALUE=DATE:'.str_replace('-', '', $row['arrival']), 'DTEND;VALUE=DATE:'.str_replace('-', '', $row['departure']),
                'SEQUENCE:'.(int)$row['revision'], 'SUMMARY:Unavailable', 'TRANSP:OPAQUE', 'STATUS:CONFIRMED', 'END:VEVENT'));
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines)."\r\n";
    }

    /** Read an all-day date without PHP's normalization of invalid dates. */
    private static function date($value)
    {
        $date = DateTimeImmutable::createFromFormat('!Ymd', $value);
        if (!$date || $date->format('Ymd') !== $value) { throw new DomainException('ical_dates'); }
        return $date;
    }

    /** Parse a bounded all-day snapshot; never follow URLs, interpret HTML, or fetch attachments. */
    public static function parse($text)
    {
        if (!is_string($text) || strlen($text) > 524288 || strpos($text, "\0") !== false) { throw new DomainException('ical_file'); }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $text = preg_replace('/\n[ \t]/', '', $text);
        $stack = array(); $event = null; $events = array(); $version = false; $ended = false;
        foreach (explode("\n", $text) as $line) {
            if ($line === '') { continue; }
            if ($ended || strpos($line, ':') === false) { throw new DomainException('ical_file'); }
            list($head, $value) = explode(':', $line, 2);
            $parts = explode(';', $head); $name = strtoupper(array_shift($parts));
            if ($name === 'BEGIN') {
                $component = strtoupper($value); $parent = $stack ? end($stack) : '';
                $allowed = array('' => array('VCALENDAR'), 'VCALENDAR' => array('VEVENT', 'VTIMEZONE'), 'VEVENT' => array('VALARM'), 'VTIMEZONE' => array('STANDARD', 'DAYLIGHT'));
                if ($parts || !in_array($component, $allowed[$parent] ?? array(), true)) { throw new DomainException('ical_unsupported'); }
                $stack[] = $component;
                if ($component === 'VEVENT') { $event = array(); }
                continue;
            }
            if ($name === 'END') {
                if ($parts || !$stack || strtoupper($value) !== end($stack)) { throw new DomainException('ical_file'); }
                $component = array_pop($stack);
                if ($component === 'VEVENT') { $events[] = $event; $event = null; if (count($events) > 500) { throw new DomainException('ical_file'); } }
                if ($component === 'VCALENDAR') { $ended = true; }
                continue;
            }
            if (!$stack) { throw new DomainException('ical_file'); }
            if (end($stack) === 'VCALENDAR') {
                if ($name === 'VERSION') { if ($version || $value !== '2.0' || $parts) { throw new DomainException('ical_file'); } $version = true; }
                if ($name === 'METHOD' && strtoupper($value) !== 'PUBLISH') { throw new DomainException('ical_unsupported'); }
            } elseif (end($stack) === 'VEVENT') {
                if (in_array($name, array('DTSTART','DTEND','DURATION','RRULE','RDATE','EXDATE','RECURRENCE-ID','STATUS','TRANSP'), true)) {
                    if (isset($event[$name])) { throw new DomainException('ical_unsupported'); }
                    $event[$name] = array('params' => $parts, 'value' => $value);
                }
            }
        }
        if (!$ended || $stack || !$version) { throw new DomainException('ical_file'); }
        $today = new DateTimeImmutable('today'); $limit = $today->modify('+2 years');
        $ranges = array(); $ignored = 0; $clipped = 0;
        foreach ($events as $event) {
            if (strtoupper($event['STATUS']['value'] ?? '') === 'CANCELLED' || strtoupper($event['TRANSP']['value'] ?? '') === 'TRANSPARENT') { $ignored++; continue; }
            foreach (array('RRULE','RDATE','EXDATE','RECURRENCE-ID') as $name) { if (isset($event[$name])) { throw new DomainException('ical_unsupported'); } }
            if (!isset($event['DTSTART']) || (isset($event['DTEND']) && isset($event['DURATION']))) { throw new DomainException('ical_dates'); }
            foreach (array('DTSTART','DTEND') as $name) {
                if (!isset($event[$name])) { continue; }
                $params = $event[$name]['params'];
                if (count($params) !== 1 || !preg_match('/^VALUE=(DATE|"DATE")$/iD', $params[0])) { throw new DomainException('ical_unsupported'); }
            }
            $arrival = self::date($event['DTSTART']['value']);
            if (isset($event['DTEND'])) { $departure = self::date($event['DTEND']['value']); }
            elseif (isset($event['DURATION'])) {
                if ($event['DURATION']['params'] || !preg_match('/^P([1-9][0-9]{0,3})(D|W)$/D', $event['DURATION']['value'], $m)) { throw new DomainException('ical_unsupported'); }
                $departure = $arrival->modify('+'.((int)$m[1] * ($m[2] === 'W' ? 7 : 1)).' days');
            } else { $departure = $arrival->modify('+1 day'); }
            if ($departure <= $arrival) { throw new DomainException('ical_dates'); }
            if ($departure <= $today || $arrival >= $limit) { $ignored++; continue; }
            if ($arrival < $today || $departure > $limit) { $clipped++; }
            $ranges[] = array('arrival' => max($arrival, $today)->format('Y-m-d'), 'departure' => min($departure, $limit)->format('Y-m-d'));
        }
        usort($ranges, function ($a, $b) { return strcmp($a['arrival'], $b['arrival']); });
        $merged = array();
        foreach ($ranges as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range['arrival'] <= $merged[$last]['departure']) { $merged[$last]['departure'] = max($merged[$last]['departure'], $range['departure']); }
            else { $merged[] = $range; }
        }
        return array('ranges' => $merged, 'events' => count($events), 'ignored' => $ignored, 'clipped' => $clipped);
    }

    /** Sign a short-lived preview so applying it cannot switch dates, apartment or actor. */
    public static function preview($id, $label, $text, $actor)
    {
        ColemanCalendar::unit($id);
        if (!is_string($label) || !trim($label) || mb_strlen($label) > 80 || strip_tags($label) !== $label) { throw new DomainException('ical_label'); }
        $preview = array_merge(self::parse($text), array('id_product' => (int)$id, 'label' => trim($label), 'expires' => time() + 900));
        $payload = base64_encode(json_encode($preview));
        return array_merge($preview, array('payload' => $payload, 'signature' => hash_hmac('sha256', (int)$actor.':'.$payload, _COOKIE_KEY_)));
    }

    /** Verify the exact preview; native permissions and CSRF remain enforced by the controller. */
    public static function verifiedPreview($id, $payload, $signature, $actor)
    {
        if (!is_string($payload) || strlen($payload) > 100000 || !is_string($signature)
            || !hash_equals(hash_hmac('sha256', (int)$actor.':'.$payload, _COOKIE_KEY_), $signature)) { throw new DomainException('ical_preview'); }
        $preview = json_decode(base64_decode($payload, true), true);
        if (!$preview || $preview['expires'] < time() || (int)$preview['id_product'] !== (int)$id) { throw new DomainException('ical_preview'); }
        return $preview;
    }
}
