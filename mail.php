<?php
/**
 * Alnas Cleaning — contactformulier verwerker
 * Werkt op elke PHP-hosting (one.com, etc.)
 * Secured: header injection, input validation, rate limiting, enum validation
 * SMTP: Mailtrap (testing) — vervang door echte SMTP voor productie
 */

define('TO_EMAIL',   'info@alnas.be');
define('FROM_EMAIL', 'noreply@alnas.be');
define('SITE_NAME',  'Alnas Cleaning');

// Paden: werkt zowel lokaal (/alnas-website/) als op productie (/)
$_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('CONTACT_URL', $_base . '/contact.html');
define('SUCCESS_URL', $_base . '/bedankt.html');

// SMTP-instellingen uit aparte config (staat niet in Git)
$_cfg = __DIR__ . '/smtp_config.php';
if (!file_exists($_cfg)) {
    header('Location: ' . CONTACT_URL . '?fout=config');
    exit;
}
require $_cfg;

// Alleen POST accepteren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . CONTACT_URL);
    exit;
}

// Honeypot — bot gevangen
if (!empty($_POST['bot-field'])) {
    header('Location: ' . SUCCESS_URL);
    exit;
}

// ── Rate limiting: max 5 submissions per IP per minute ─────────────────
function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $lockFile = sys_get_temp_dir() . '/alnas_rl_' . md5($ip) . '.tmp';

    if (file_exists($lockFile)) {
        $data = json_decode(file_get_contents($lockFile), true) ?? [];
        $now = time();
        $count = 0;

        foreach (($data['times'] ?? []) as $t) {
            if ($now - $t < 60) $count++;
        }

        if ($count >= 5) return false;
        $data['times'][] = $now;
        $data['times'] = array_filter($data['times'], fn($t) => time() - $t < 60);
    } else {
        $data = ['times' => [time()]];
    }

    file_put_contents($lockFile, json_encode($data));
    return true;
}

if (!checkRateLimit()) {
    header('Location: ' . CONTACT_URL . '?fout=rate_limit');
    exit;
}

// ── Helpers ────────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function cleanHeader(string $val): string {
    return preg_replace('/[\r\n\t]/', ' ', trim($val));
}

function validatePhone(string $phone): bool {
    $phone = trim($phone);
    return (bool) preg_match('/^(\+32|0)[\d\s\-().]{7,20}$/', $phone);
}

define('MAX_NAAM', 200);
define('MAX_BEDRIJF', 200);
define('MAX_TELEFOON', 30);
define('MAX_BERICHT', 5000);

// ── Invoer ophalen & valideren ─────────────────────────────
$naam            = clean($_POST['naam']            ?? '');
$bedrijf         = clean($_POST['bedrijf']         ?? '');
$email_raw       = trim($_POST['email']            ?? '');
$telefoon        = clean($_POST['telefoon']        ?? '');
$type_ruimte     = clean($_POST['type_ruimte']     ?? '');
$oppervlakte     = (int) ($_POST['oppervlakte']    ?? 0);
$verdiepingen    = (int) ($_POST['verdiepingen']   ?? 0);
$type_schoonmaak = clean($_POST['type_schoonmaak'] ?? '');
$frequentie      = clean($_POST['frequentie']      ?? '');
$bericht         = clean($_POST['bericht']         ?? '');

// Whitelist enum fields
$allowed_ruimte     = ['', 'kantoor', 'winkel', 'medisch', 'woning', 'bouw', 'evenement', 'magazijn', 'shortstay', 'andere-ruimte'];
$allowed_schoonmaak = ['', 'algemeen', 'diepte', 'bouw', 'verhuis', 'desinfectie', 'vloer', 'glas', 'terras', 'andere-schoonmaak'];
$allowed_frequentie = ['', 'eenmalig', 'wekelijks', 'tweewekelijks', 'maandelijks', 'meerdere-keren', 'nog-te-bepalen'];

if (!in_array($type_ruimte, $allowed_ruimte, true))         $type_ruimte = '';
if (!in_array($type_schoonmaak, $allowed_schoonmaak, true)) $type_schoonmaak = '';
if (!in_array($frequentie, $allowed_frequentie, true))      $frequentie = '';

// Numeric range check
if ($oppervlakte  < 0 || $oppervlakte  > 999999) $oppervlakte  = 0;
if ($verdiepingen < 0 || $verdiepingen > 999)    $verdiepingen = 0;

// Email validation
$email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

// Required fields
if (empty($naam) || mb_strlen($naam) > MAX_NAAM) {
    header('Location: ' . CONTACT_URL . '?fout=naam'); exit;
}
if (!$email || mb_strlen($email) > 254) {
    header('Location: ' . CONTACT_URL . '?fout=email'); exit;
}
if (empty($telefoon) || !validatePhone($telefoon) || mb_strlen($telefoon) > MAX_TELEFOON) {
    header('Location: ' . CONTACT_URL . '?fout=telefoon'); exit;
}
if (mb_strlen($bedrijf) > MAX_BEDRIJF) {
    header('Location: ' . CONTACT_URL . '?fout=bedrijf'); exit;
}
if (mb_strlen($bericht) > MAX_BERICHT) {
    header('Location: ' . CONTACT_URL . '?fout=bericht'); exit;
}

// ── E-mail opbouwen ────────────────────────────────────────
$subject = 'Nieuwe offerte-aanvraag via alnas.be — ' . cleanHeader($naam);

$body  = "Nieuwe aanvraag via het contactformulier op alnas.be\n";
$body .= str_repeat('-', 50) . "\n\n";

$body .= "CONTACTGEGEVENS\n";
$body .= "Naam:      $naam\n";
if ($bedrijf)   $body .= "Bedrijf:   $bedrijf\n";
$body .= "E-mail:    $email\n";
$body .= "Telefoon:  $telefoon\n\n";

$body .= "RUIMTE\n";
if ($type_ruimte)  $body .= "Type:         $type_ruimte\n";
if ($oppervlakte)  $body .= "Oppervlakte:  {$oppervlakte} m2\n";
if ($verdiepingen) $body .= "Verdiepingen: $verdiepingen\n\n";

$body .= "SCHOONMAAK\n";
if ($type_schoonmaak) $body .= "Type:       $type_schoonmaak\n";
if ($frequentie)      $body .= "Frequentie: $frequentie\n\n";

if ($bericht) {
    $body .= "BERICHT\n$bericht\n\n";
}

$body .= str_repeat('-', 50) . "\n";
$body .= "Verstuurd op: " . date('d/m/Y \o\m H:i') . "\n";

// ── SMTP verzenden via socket ──────────────────────────────
function smtpSend(string $to, string $from, string $fromName, string $replyTo, string $subject, string $body): bool {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;

    $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$sock) return false;

    $read = function() use ($sock) { return fgets($sock, 512); };
    $send = function(string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $read(); // 220 greeting

    $send("EHLO " . gethostname());
    while ($line = $read()) { if (substr($line, 3, 1) === ' ') break; }

    $send("AUTH LOGIN");
    $read();
    $send(base64_encode($user));
    $read();
    $send(base64_encode($pass));
    $r = $read();
    if (substr($r, 0, 3) !== '235') { fclose($sock); return false; }

    $send("MAIL FROM:<{$from}>");
    $read();
    $send("RCPT TO:<{$to}>");
    $read();
    $send("DATA");
    $read();

    $msg  = "From: {$fromName} <{$from}>\r\n";
    $msg .= "To: <{$to}>\r\n";
    $msg .= "Reply-To: <{$replyTo}>\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "\r\n";
    $msg .= $body . "\r\n";
    $msg .= ".";

    $send($msg);
    $r = $read();
    $send("QUIT");
    fclose($sock);

    return substr($r, 0, 3) === '250';
}

$sent = smtpSend(
    TO_EMAIL,
    FROM_EMAIL,
    SITE_NAME,
    (string) $email,
    $subject,
    $body
);

if ($sent) {
    header('Location: ' . SUCCESS_URL);
} else {
    header('Location: ' . CONTACT_URL . '?fout=verzenden');
}
exit;
