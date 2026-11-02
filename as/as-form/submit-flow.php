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
require_once 'solapi.php';

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

// 프론트엔드마다 필드명이 달라(userName/user_name/name 등) 여러 키를 순서대로 확인
function pickInput(array $input, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($input[$key]) && trim((string)$input[$key]) !== '') {
            return trim((string)$input[$key]);
        }
    }
    return '';
}

$name = pickInput($input, ['userName', 'user_name', 'name']);
$phone = pickInput($input, ['userPhone', 'user_phone', 'phone']);
$address = pickInput($input, ['userAddress', 'user_address', 'address']);
$productName = pickInput($input, ['productName', 'prod_name', 'product']);
$productSerial = pickInput($input, ['productSerial', 'prod_sn', 'serial']);
$buyDate = pickInput($input, ['buyDate', 'buy_date']);
$receiptDate = pickInput($input, ['receiptDate', 'today_date']);
$symptom = pickInput($input, ['symptom', 'symptom_desc']);

// 링크 값이 문자열/단일 객체/배열 어느 형태로 와도 [['name'=>..., 'url'=>...], ...] 로 정규화
function normalizeLinks($value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : (trim($value) !== '' ? [['url' => trim($value)]] : []);
    }
    if (!is_array($value) || empty($value)) {
        return [];
    }
    if (isset($value['url']) || isset($value['link']) || isset($value['name'])) {
        $value = [$value];
    }
    $links = [];
    foreach ($value as $item) {
        if (is_string($item)) {
            $item = ['url' => $item];
        }
        if (!is_array($item)) {
            continue;
        }
        $url = trim((string)($item['url'] ?? $item['link'] ?? $item['href'] ?? ''));
        $name = trim((string)($item['name'] ?? $item['fileName'] ?? ''));
        if ($url === '' && $name === '') {
            continue;
        }
        $links[] = ['name' => $name !== '' ? $name : '첨부파일', 'url' => $url];
    }
    return $links;
}

function pickLinks(array $input, array $keys): array
{
    foreach ($keys as $key) {
        if (isset($input[$key])) {
            $links = normalizeLinks($input[$key]);
            if (!empty($links)) {
                return $links;
            }
        }
    }
    return [];
}

$imageLinks = pickLinks($input, ['imageLinks', 'image_links', 'imageUrls', 'images', 'imageLink']);
$attachLinks = pickLinks($input, ['attachLink', 'attach_link', 'attachLinks', 'fileLink', 'fileLinks', 'attachment', 'attachments']);

$title = pickInput($input, ['title', 'subject']);
$contents = pickInput($input, ['contents', 'content', 'body', 'message', 'text']);

// 운영 페이지가 링크를 별도 필드로 보내지 않고 contents에만 포함한 경우 그대로 유지
$clientContentsHasLinks = $contents !== '' && empty($imageLinks) && empty($attachLinks);

if (!$clientContentsHasLinks && $name !== '' && $phone !== '' && $address !== '' && $productName !== '' && $symptom !== '') {
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
            $imageName = $imageLink['name'];
            $imageUrl = $imageLink['url'];
            $lines[] = ($index + 1) . '. ' . $imageName;
            if ($imageUrl !== '') {
                $lines[] = $imageUrl;
            }
        }
    }

    if (!empty($attachLinks)) {
        $lines[] = '';
        $lines[] = '■ 첨부 파일:';
        foreach ($attachLinks as $attach) {
            $lines[] = $attach['name'];
            if ($attach['url'] !== '') {
                $lines[] = $attach['url'];
            }
        }
    }

    $contents = implode("\n", $lines);
} elseif ($contents !== '' && (!empty($imageLinks) || !empty($attachLinks))) {
    // 직접 작성된 contents로 오는 경우에도 첨부 링크를 덧붙임
    $extra = [];
    foreach (array_merge($imageLinks, $attachLinks) as $link) {
        $extra[] = $link['name'] . ($link['url'] !== '' ? "\n" . $link['url'] : '');
    }
    $contents .= "\n\n■ 첨부:\n" . implode("\n", $extra);
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

$alimtalkResult = null;
if ($phone !== '') {
    $receiptNo = date('YmdHis');
    $alimtalkResult = sendSolapiAlimtalk($phone, [
        '#{이름}' => $name,
        '#{접수번호}' => $receiptNo,
        '#{제품명}' => $productName,
    ]);
}

$result = ['success' => true];
if ($alimtalkResult !== null) {
    // 디버깅용: 알림톡 실패 원인과 실제 전송된 수신/발신번호, 솔라피 원본 응답을 노출 (원인 확인 후 제거 예정)
    if ($alimtalkResult['success'] === false) {
        $result['alimtalkError'] = $alimtalkResult['error'];
    }
    $result['alimtalkDebug'] = $alimtalkResult['debug'] ?? null;
    $result['alimtalkResponse'] = $alimtalkResult['response'] ?? null;
}
echo json_encode($result);
