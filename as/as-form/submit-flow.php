<?php
header('Access-Control-Allow-Origin: https://www.sodamedia.co.kr');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

$title = trim((string)($input['title'] ?? ''));
$contents = trim((string)($input['contents'] ?? $input['content'] ?? ''));

if ($title === '' || $contents === '') {
    http_response_code(400);
    echo json_encode(['error' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$body = json_encode([
    'title'    => $title,
    'contents' => $contents,
    'status'   => 'request'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($body === false) {
    http_response_code(500);
    echo json_encode(['error' => '요청 데이터 인코딩에 실패했습니다.']);
    exit;
}

$url = 'https://api.flow.team/user/posts/projects/' . FLOW_PROJECT_ID . '/tasks';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-flow-api-key: ' . FLOW_API_KEY
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => '플로우 서버 통신 실패: ' . $curlError]);
    exit;
}

if ($status !== 200 && $status !== 201) {
    http_response_code($status);
    echo json_encode(['error' => '플로우 전송 실패: ' . $response]);
    exit;
}

echo json_encode(['success' => true]);
