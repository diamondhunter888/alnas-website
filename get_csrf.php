<?php
/**
 * CSRF-tokenendpoint — retourneert een eenmalig token voor het contactformulier.
 * Mag alleen via fetch() worden aangeroepen; rechtsreeks browsen levert een leeg object op.
 */
session_start();

$token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $token;

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode(['token' => $token]);
