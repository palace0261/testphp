<?php
const DROPBOX_OAUTH_APP_KEY = 'f6szxcyq0j1sna9';
const DROPBOX_OAUTH_REDIRECT_URI = 'https://port-9000-testphp-ma6q5cjy22737d6f.sel4.cloudtype.app/as/as-form/dropbox-auth.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

function failDropboxAuth($status, $message)
{
    http_response_code($status);
    exit($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    failDropboxAuth(405, 'GET 요청만 허용됩니다.');
}

$appSecret = getenv('DROPBOX_OAUTH_APP_SECRET');
if ($appSecret === false || $appSecret === '') {
    failDropboxAuth(500, 'DROPBOX_OAUTH_APP_SECRET 환경 변수를 설정해 주세요.');
}

if (!function_exists('curl_init')) {
    failDropboxAuth(500, 'PHP cURL 확장이 필요합니다.');
}

session_set_cookie_params([
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (!session_start()) {
    failDropboxAuth(500, '인증 세션을 시작하지 못했습니다.');
}

if (!isset($_GET['code']) && !isset($_GET['error']) && !isset($_GET['state'])) {
    try {
        $state = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        error_log('Dropbox OAuth state generation failed: ' . $e->getMessage());
        failDropboxAuth(500, '인증 요청을 생성하지 못했습니다.');
    }

    $_SESSION['dropbox_oauth_state'] = $state;
    session_write_close();
    $authorizeUrl = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
        'client_id' => DROPBOX_OAUTH_APP_KEY,
        'response_type' => 'code',
        'redirect_uri' => DROPBOX_OAUTH_REDIRECT_URI,
        'token_access_type' => 'offline',
        'state' => $state,
    ]);
    header('Location: ' . $authorizeUrl, true, 302);
    exit;
}

$expectedState = $_SESSION['dropbox_oauth_state'] ?? null;
unset($_SESSION['dropbox_oauth_state']);
session_write_close();
$state = $_GET['state'] ?? null;
if (!is_string($expectedState) || !is_string($state) || !hash_equals($expectedState, $state)) {
    failDropboxAuth(400, '인증 세션이 일치하지 않습니다. 이 페이지에서 인증을 다시 시작해 주세요.');
}

if (isset($_GET['error'])) {
    failDropboxAuth(400, 'Dropbox 인증이 거부되었거나 실패했습니다. 이 페이지에서 다시 시도해 주세요.');
}

$code = $_GET['code'] ?? null;
if (!is_string($code) || $code === '') {
    failDropboxAuth(400, 'Dropbox 인증 코드가 없습니다.');
}

$ch = curl_init('https://api.dropbox.com/oauth2/token');
if ($ch === false) {
    failDropboxAuth(500, 'Dropbox 토큰 요청을 준비하지 못했습니다.');
}
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => DROPBOX_OAUTH_REDIRECT_URI,
]));
curl_setopt($ch, CURLOPT_USERPWD, DROPBOX_OAUTH_APP_KEY . ':' . $appSecret);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('Dropbox OAuth token request failed: ' . $curlError);
    failDropboxAuth(502, 'Dropbox 토큰 서버에 연결하지 못했습니다.');
}
if ($status !== 200) {
    error_log('Dropbox OAuth token request returned HTTP ' . $status);
    failDropboxAuth(502, 'Dropbox 토큰 발급에 실패했습니다. 앱 키, 앱 시크릿과 redirect URI를 확인하고 다시 인증해 주세요.');
}

$data = json_decode($response, true);
if (!is_array($data) || !isset($data['refresh_token']) || !is_string($data['refresh_token']) || $data['refresh_token'] === '') {
    error_log('Dropbox OAuth token response did not contain a refresh token.');
    failDropboxAuth(502, 'Dropbox 응답에 refresh token이 없습니다. 다시 인증해 주세요.');
}

echo "Dropbox refresh token (비밀로 보관하세요):\n\n";
echo $data['refresh_token'] . "\n";
