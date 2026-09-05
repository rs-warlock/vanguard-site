<?php
// Vanguard store checkout bridge. The only server-side code on this site.
// Reads the TeamGames store API key from OUTSIDE the web root, validates the
// request against a fixed pack table, asks TeamGames for a checkout URL and
// redirects the player there. Nothing is stored here.
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$PACKS = [ // pack => [TeamGames numeric product id, quantity]
  'recruit'   => [20713, 10],
  'vanguard'  => [20713, 25],
  'champion'  => [20713, 50],
  'warlord'   => [20713, 100],
  'ascendant' => [20713, 250],
];
$KEY_FILE = dirname(__DIR__, 2) . '/.vanguard/teamgames.key';

function fail(string $msg, int $code = 400): void {
  http_response_code($code);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>Store</title><link rel="stylesheet" href="/assets/style.css?v=4">'
     . '<div class="wrap" style="padding:80px 20px;text-align:center"><h1 style="font-size:24px">Checkout unavailable</h1>'
     . '<p style="color:#8e82b0">' . htmlspecialchars($msg, ENT_QUOTES) . '</p><p><a class="btn" href="/store/">Back to the store</a></p></div>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Use the store page to start a purchase.', 405);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== 'https://vanguardrsps.org') fail('Bad origin.', 403);
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') !== 'same-origin') fail('Bad origin.', 403);

$user = trim((string)($_POST['username'] ?? ''));
$pack = (string)($_POST['pack'] ?? '');
if (!preg_match('/^[A-Za-z0-9 _-]{1,12}$/', $user)) fail('Enter your in-game name (1-12 letters, numbers, spaces).');
if (!isset($PACKS[$pack])) fail('Unknown pack.');
if (!is_readable($KEY_FILE)) fail('The store is not connected yet. Please try again later.', 503);
$key = trim((string)file_get_contents($KEY_FILE));
if ($key === '') fail('The store is not connected yet.', 503);

[$productId, $qty] = $PACKS[$pack];
$body = json_encode(['username' => $user, 'cartItems' => json_encode([['id' => $productId, 'quantity' => $qty]])]);
$ch = curl_init('https://vanguard.teamgames.io/api/v2/client/global/checkout/complete');
curl_setopt_array($ch, [
  CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . base64_encode($key)],
]);
$res = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
$j = is_string($res) ? json_decode($res, true) : null;
if (!is_array($j) || ($j['status'] ?? '') !== 'SUCCESS') {
  $why = is_array($j) ? (string)($j['message'] ?? $j['status'] ?? 'error') : ('http ' . $http);
  fail('TeamGames could not start this checkout (' . $why . '). Try again in a moment.', 502);
}
if (!empty($j['isFree'])) { header('Location: /store/?done=1', true, 303); exit; }
$redir = (string)($j['redirect'] ?? '');
if (!preg_match('#^https://[a-z0-9.-]+\.(teamgames\.io|paypal\.com|stripe\.com)/#i', $redir)) fail('Unexpected checkout address.', 502);
header('Location: ' . $redir, true, 303);
