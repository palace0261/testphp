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

$name = trim((string)($input['userName'] ?? ''));
$phone = trim((string)($input['userPhone'] ?? ''));
$address = trim((string)($input['userAddress'] ?? ''));
$productName = trim((string)($input['productName'] ?? ''));
$productSerial = trim((string)($input['productSerial'] ?? ''));
$buyDate = trim((string)($input['buyDate'] ?? ''));
$receiptDate = trim((string)($input['receiptDate'] ?? ''));
$symptom = trim((string)($input['symptom'] ?? ''));
$imageLinks = is_array($input['imageLinks'] ?? null) ? $input['imageLinks'] : [];
$attachLink = is_array($input['attachLink'] ?? null) ? $input['attachLink'] : null;

$title = trim((string)($input['title'] ?? ''));
$contents = trim((string)($input['contents'] ?? $input['content'] ?? ''));

if ($name !== '' && $phone !== '' && $address !== '' && $productName !== '' && $symptom !== '') {
    $title = '[A/S 접수] ' . $name . ' / ' . $productName;

    $lines = [
        '■ 신청인: ' . $name,
        '■ 연락처: ' . $phone,
        '■ 수령주소: ' . $address,
        '■ 제품명: ' . $productName,
        '■ 시리얼번호: ' . ($productSerial !== '' ? $productSerial : '미입력'),
        '■ 구매일자: ' . ($buyDate !== '' ? $buyDate : '미입력'),
        '■ 접수일자: ' . ($receiptDate !== '' ? $receiptDate : '미입력'),
        '',
        '■ 증상 내용:',
        $symptom,
    ];

    if (!empty($imageLinks)) {
        $lines[] = '';
        $lines[] = '■ 첨부 이미지 (' . count($imageLinks) . '장):';
        foreach ($imageLinks as $index => $imageLink) {
            $imageName = trim((string)($imageLink['name'] ?? '첨부파일'));
            $imageUrl = trim((string)($imageLink['url'] ?? ''));
            $lines[] = ($index + 1) . '. ' . $imageName;
            if ($imageUrl !== '') {
                $lines[] = $imageUrl;
            }
        }
    }

    if (!empty($attachLink)) {
        $attachName = trim((string)($attachLink['name'] ?? '첨부파일'));
        $attachUrl = trim((string)($attachLink['url'] ?? ''));
        $lines[] = '';
        $lines[] = '■ 첨부 파일:';
        $lines[] = $attachName;
        if ($attachUrl !== '') {
            $lines[] = $attachUrl;
        }
    }

    $contents = implode("\n", $lines);
}

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
