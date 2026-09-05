<?php
// TEMPORARY outbound diagnostics. Delete after use.
header('Content-Type: text/plain');
$key = trim((string)@file_get_contents(dirname(__DIR__, 2) . '/.vanguard/teamgames.key'));
echo "php " . PHP_VERSION . " curl " . curl_version()['version'] . " keylen " . strlen($key) . "\n\n";
$tests = [
  ['GET',  'https://teamgames.io/', null],
  ['GET',  'https://vanguard.teamgames.io/store', null],
  ['POST', 'https://vanguard.teamgames.io/api/v2/client/global/products', '{}'],
  ['POST', 'https://api.teamgames.io/api/v2/client/global/checkout/complete', '{"username":"Test","cartItems":"[{\"id\":20713,\"quantity\":1}]"}'],
];
foreach ($tests as [$m, $u, $b]) {
  $ch = curl_init($u);
  curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $m, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_USERAGENT => 'VanguardStore/1.0',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . base64_encode($key), 'x-api-key: ' . $key]]);
  if ($b !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $b);
  $r = curl_exec($ch);
  echo "$m $u\n  code=" . curl_getinfo($ch, CURLINFO_RESPONSE_CODE) . " err=" . curl_error($ch) . " ip=" . curl_getinfo($ch, CURLINFO_PRIMARY_IP) . "\n";
  echo "  " . str_replace("\n", "\n  ", substr(preg_replace('/' . preg_quote($key, '/') . '/', 'KEY', preg_replace('/?
(?=\S+: )/', "
", (string)$r)), 0, 1400)) . "\n\n";
  curl_close($ch);
}
