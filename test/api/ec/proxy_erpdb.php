<?php
// simple proxy to forward requests to remote erpdb.php and add CORS headers
// Usage: proxy_erpdb.php?tzz=... (GET) or POST body will be forwarded

$remote = 'https://port-9000-testphp-ma6q5cjy22737d6f.sel4.cloudtype.app/test/api/ec/erpdb.php';

// Build remote URL including query string
$query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
$url = $remote . ($query !== '' ? '?' . $query : '');

// Allow preflight
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Credentials: true');
  header('Vary: Origin');
} else {
  header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Initialize curl
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);

// Forward POST body if present
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = file_get_contents('php://input');
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  $forwardHeaders = [];
  if (!empty($_SERVER['CONTENT_TYPE'])) {
    $forwardHeaders[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
  }
  if ($forwardHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
}

$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 200;
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/html; charset=UTF-8';
curl_close($ch);

if ($resp === false) {
  http_response_code(502);
  echo 'Proxy fetch error';
  exit;
}

header('Content-Type: ' . $contentType);
http_response_code($httpCode);
echo $resp;

?>
