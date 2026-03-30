<?php
/**
 * Alnas Cleaning — contactformulier verwerker
 * Werkt op elke PHP-hosting (one.com, etc.)
 */

define('TO_EMAIL',   'info@alnas.be');
define('FROM_EMAIL', 'noreply@alnas.be');
define('SITE_NAME',  'Alnas Cleaning');
define('CONTACT_URL', '/contact.html');
define('SUCCESS_URL', '/bedankt.html');

// Alleen POST accepteren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . CONTACT_URL);
    exit;
}

// Honeypot — bot gevangen
if (!empty($_POST['bot-field'])) {
    header('Location: ' . SUCCESS_URL); // stil negeren
    exit;
}

// ── Helpers ────────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// Verhindert header injection (CR/LF in header-waarden)
function cleanHeader(string $val): string {
    return preg_replace('/[\r\n\t]/', ' ', trim($val));
}

// ── Invoer ophalen & valideren ─────────────────────────────
$naam       = clean($_POST['naam']           ?? '');
$bedrijf    = clean($_POST['bedrijf']        ?? '');
$email_raw  = trim($_POST['email']           ?? '');
$telefoon   = clean($_POST['telefoon']       ?? '');
$type_ruimte    = clean($_POST['type_ruimte']    ?? '');
$oppervlakte    = clean($_POST['oppervlakte']    ?? '');
$verdiepingen   = clean($_POST['verdiepingen']   ?? '');
$type_schoonmaak = clean($_POST['type_schoonmaak'] ?? '');
$frequentie = clean($_POST['frequentie']     ?? '');
$bericht    = clean($_POST['bericht']        ?? '');

// Verplichte velden
$email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
if (empty($naam) || !$email || empty($telefoon)) {
    header('Location: ' . CONTACT_URL . '?fout=validatie');
    exit;
}

// ── E-mail opbouwen ────────────────────────────────────────
$subject = 'Nieuwe offerte-aanvraag via alnas.be — ' . $naam;

$body  = "Nieuwe aanvraag via het contactformulier op alnas.be\n";
$body .= str_repeat('─', 50) . "\n\n";

$body .= "CONTACTGEGEVENS\n";
$body .= "Naam:      $naam\n";
if ($bedrijf)   $body .= "Bedrijf:   $bedrijf\n";
$body .= "E-mail:    $email\n";
$body .= "Telefoon:  $telefoon\n\n";

$body .= "RUIMTE\n";
if ($type_ruimte)  $body .= "Type:         $type_ruimte\n";
if ($oppervlakte)  $body .= "Oppervlakte:  {$oppervlakte} m²\n";
if ($verdiepingen) $body .= "Verdiepingen: $verdiepingen\n\n";

$body .= "SCHOONMAAK\n";
if ($type_schoonmaak) $body .= "Type:       $type_schoonmaak\n";
if ($frequentie)      $body .= "Frequentie: $frequentie\n\n";

if ($bericht) {
    $body .= "BERICHT\n$bericht\n\n";
}

$body .= str_repeat('─', 50) . "\n";
$body .= "Verstuurd op: " . date('d/m/Y \o\m H:i') . "\n";

// ── Headers ───────────────────────────────────────────────
$from_header   = cleanHeader(SITE_NAME . ' <' . FROM_EMAIL . '>');
$replyto_header = cleanHeader($naam . ' <' . $email . '>');

$headers  = "From: $from_header\r\n";
$headers .= "Reply-To: $replyto_header\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

// ── Verzenden ─────────────────────────────────────────────
$sent = mail(TO_EMAIL, $subject, $body, $headers);

if ($sent) {
    header('Location: ' . SUCCESS_URL);
} else {
    header('Location: ' . CONTACT_URL . '?fout=verzenden');
}
exit;
