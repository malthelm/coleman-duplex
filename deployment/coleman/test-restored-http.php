<?php
/** HTTP smoke test inside the isolated restore web container; never contacts the live hostname. */
if (PHP_SAPI !== 'cli' || (getenv('COLEMAN_RESTORE_TEST') !== '1' || !is_file('/restore/ready'))) { exit(1); }
$restoreCookies = array();
/** Check a visible result without printing private page contents or cookies. */
function restoreCheck($condition, $label) { if (!$condition) { throw new RuntimeException($label); } echo 'PASS: '.$label."\n"; }
/** Resolve only same-site redirects, and always route transport to this container's localhost. */
function restorePath($url, $from = '/host-admin/index.php') {
    $parts = parse_url($url);
    if (isset($parts['host'])) {
        $allowed = parse_url('https://'.getenv('DEMO_DOMAIN'), PHP_URL_HOST);
        if ($parts['host'] !== $allowed && $parts['host'] !== 'localhost') { throw new RuntimeException('Unexpected redirect host'); }
    }
    $path = $parts['path'] ?? $from;
    if ($path[0] !== '/') { $path = dirname($from).'/'.$path; }
    return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
}
/** Exercise HTTP with the normal proxy headers and a private native cookie jar. */
function restoreHttp($path, $post = null) {
    global $restoreCookies;
    for ($n = 0; $n < 5; $n++) {
        $curl = curl_init('http://localhost'.$path); $headers = array();
        curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array('Host: '.getenv('DEMO_DOMAIN'), 'X-Forwarded-Proto: https'),
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$headers) {
                if (strpos($line, ':') !== false) { list($name, $value) = explode(':', $line, 2); if (strtolower($name) === 'set-cookie') { $headers['set-cookie'][] = trim($value); } else { $headers[strtolower($name)] = trim($value); } } return strlen($line);
            }));
        // Forward cookies issued by this same local app, as on the reverse proxy's HTTP hop.
        // No application cookie flags, credentials or authentication settings are changed.
        $cookies = array();
        foreach ($restoreCookies as $item) { if (strpos($path, $item['path']) === 0) { $cookies[] = $item['pair']; } }
        if ($cookies) { curl_setopt($curl, CURLOPT_COOKIE, implode('; ', $cookies)); }
        if ($post !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $body = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_errno($curl); curl_close($curl);
        if ($error) { throw new RuntimeException('Internal HTTP request failed'); }
        foreach ($headers['set-cookie'] ?? array() as $cookieHeader) {
            $parts = array_map('trim', explode(';', $cookieHeader)); $pair = array_shift($parts); $name = explode('=', $pair, 2)[0];
            $attributes = array(); foreach ($parts as $part) { $a = explode('=', $part, 2); $attributes[strtolower($a[0])] = $a[1] ?? ''; }
            $host = parse_url('https://'.getenv('DEMO_DOMAIN'), PHP_URL_HOST);
            if (isset($attributes['domain']) && ltrim($attributes['domain'], '.') !== $host) { throw new RuntimeException('Unexpected cookie domain'); }
            $cookiePath = $attributes['path'] ?? rtrim(dirname(parse_url($path, PHP_URL_PATH)), '/').'/';
            $key = $cookiePath.'|'.$name;
            if ((isset($attributes['max-age']) && (int)$attributes['max-age'] <= 0) || (isset($attributes['expires']) && strtotime($attributes['expires']) < time())) { unset($restoreCookies[$key]); }
            else { $restoreCookies[$key] = array('path' => $cookiePath, 'pair' => $pair); }
        }
        if (in_array($code, array(301,302,303), true) && isset($headers['location'])) { $path = restorePath($headers['location'], $path); $post = null; continue; }
        return array($code, $body);
    }
    throw new RuntimeException('Unexpected redirect loop');
}
try {
    foreach (array('/', '/apartments/one/', '/apartments/two/') as $path) {
        list($code, $body) = restoreHttp($path);
        restoreCheck($code === 200 && strpos($body, 'Request a stay') !== false, 'restored guest page '.$path);
    }
    list($code, $body) = restoreHttp('/host-admin/index.php?controller=AdminColemanRequests');
    restoreCheck($code === 200 && strpos($body, 'name="passwd"') !== false && strpos($body, 'design-review@example.invalid') === false, 'restored host inbox requires sign-in');
    list($code, $body) = restoreHttp('/host-admin/index.php?controller=AdminLogin', array('email' => 'host@coleman.example', 'passwd' => getenv('ADMIN_PASSWORD'), 'submitLogin' => '1', 'ajax' => '1', 'redirect' => 'AdminColemanRequests'));
    $login = json_decode($body, true);
    restoreCheck($code === 200 && $login && empty($login['hasErrors']) && !empty($login['redirect']), 'restored native credentials authenticate');
    $inbox = restorePath($login['redirect']);
    list($code, $body) = restoreHttp($inbox);
    restoreCheck($code === 200 && strpos($body, 'Booking requests') !== false && strpos($body, 'design-review@example.invalid') !== false, 'restored signed-in inbox shows saved requests');
    foreach (array('calendar' => 'calendar-grid', 'settings' => 'Nightly rent') as $view => $marker) {
        list($code, $body) = restoreHttp($inbox.'&view='.$view);
        restoreCheck($code === 200 && strpos($body, $marker) !== false, 'restored host '.$view);
    }
    if (getenv('COLEMAN_EXPECT_MESSAGES') === '1') {
        list($code, $body) = restoreHttp($inbox.'&view=message&request_id=5');
        restoreCheck($code === 200 && strpos($body, 'Guest message') !== false, 'restored message screen');
    }
    restoreCheck(!getenv('COLEMAN_TEST_GUEST_CREATE') || getenv('COLEMAN_TEST_GUEST_CREATE') === '1', 'explicit guest-write test option');
    if (getenv('COLEMAN_TEST_GUEST_CREATE') === '1') {
        list($code, $body) = restoreHttp('/');
        $dom = new DOMDocument(); @$dom->loadHTML($body, LIBXML_NONET); $xpath = new DOMXPath($dom);
        $data = array('apartment'=>'one', 'guest_name'=>'Restore rehearsal fixture', 'email'=>'restore@example.invalid',
            'arrival'=>date('Y-m-d', strtotime('+45 days')), 'departure'=>date('Y-m-d', strtotime('+48 days')), 'guests'=>'2',
            'message'=>'Isolated restore verification only.', 'demo_consent'=>'1');
        foreach (array('csrf', 'request_token') as $field) {
            $node = $xpath->query('//input[@name="'.$field.'"]')->item(0);
            if (!$node) { throw new RuntimeException('Restored guest form token missing'); } $data[$field] = $node->getAttribute('value');
        }
        list($code, $body) = restoreHttp('/', $data);
        restoreCheck($code === 200 && strpos($body, 'CD-00006') !== false, 'restored guest request saves and returns a receipt');
        list($code, $body) = restoreHttp($inbox);
        restoreCheck($code === 200 && strpos($body, 'Restore rehearsal fixture') !== false, 'new request appears in restored host inbox');
    }
    echo "All transport stayed on container localhost; no email was sent.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Restore HTTP check failed: '.get_class($error).': '.$error->getMessage()."\n"); exit(1);
}
