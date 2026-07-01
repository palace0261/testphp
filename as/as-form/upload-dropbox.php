<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['fileBase64']) || !isset($input['fileName']) || !isset($input['folderName'])) {
    http_response_code(400);
    echo json_encode(['error' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$fileBase64 = $input['fileBase64'];
$fileName   = $input['fileName'];
$folderName = $input['folderName'];

// 파일명을 ASCII 안전 이름으로 변환 (드롭박스 헤더는 ASCII만 허용)
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$safeName = 'file_' . round(microtime(true) * 1000) . ($ext ? '.' . $ext : '');
$dropboxPath = '/as-uploads/' . $folderName . '/' . $safeName;

$fileBinary = base64_decode($fileBase64);

// ===== 1. 드롭박스에 파일 업로드 =====
$uploadArg = json_encode([
    'path' => $dropboxPath,
    'mode' => 'add',
    'autorename' => true,
    'mute' => false
]);

$ch = curl_init('https://content.dropboxapi.com/2/files/upload');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . DROPBOX_TOKEN,
    'Dropbox-API-Arg: ' . $uploadArg,
    'Content-Type: application/octet-stream'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fileBinary);
$uploadResponse = curl_exec($ch);
$uploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($uploadStatus !== 200) {
    http_response_code($uploadStatus);
    echo json_encode(['error' => '드롭박스 업로드 실패: ' . $uploadResponse]);
    exit;
}

$uploadData = json_decode($uploadResponse, true);
$pathLower = $uploadData['path_lower'];

// ===== 2. 공유 링크 생성 =====
$shareArg = json_encode([
    'path' => $pathLower,
    'settings' => ['requested_visibility' => 'public']
]);

$ch = curl_init('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . DROPBOX_TOKEN,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $shareArg);
$shareResponse = curl_exec($ch);
$shareStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$url = null;

if ($shareStatus === 200) {
    $shareData = json_decode($shareResponse, true);
    $url = str_replace('?dl=0', '?dl=1', $shareData['url']);
} else {
    // 이미 링크가 있는 경우 기존 링크 조회
    $listArg = json_encode(['path' => $pathLower]);
    $ch = curl_init('https://api.dropboxapi.com/2/sharing/list_shared_links');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . DROPBOX_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $listArg);
    $listResponse = curl_exec($ch);
    curl_close($ch);

    $listData = json_decode($listResponse, true);
    if (!empty($listData['links'])) {
        $url = str_replace('?dl=0', '?dl=1', $listData['links'][0]['url']);
    }
}

if (!$url) {
    http_response_code(500);
    echo json_encode(['error' => '공유 링크 생성에 실패했습니다.']);
    exit;
}

echo json_encode(['success' => true, 'url' => $url, 'name' => $fileName]);
