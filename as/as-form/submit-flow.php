<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['title']) || !isset($input['contents'])) {
    http_response_code(400);
    echo json_encode(['error' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$body = json_encode([
    'title'    => $input['title'],
    'contents' => $input['contents'],
    'status'   => 'request'
], JSON_UNESCAPED_UNICODE);

$url = 'https://api.flow.team/user/posts/projects/' . FLOW_PROJECT_ID . '/tasks';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-flow-api-key: ' . FLOW_API_KEY
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200 && $status !== 201) {
    http_response_code($status);
    echo json_encode(['error' => '플로우 전송 실패: ' . $response]);
    exit;
}

echo json_encode(['success' => true]);
