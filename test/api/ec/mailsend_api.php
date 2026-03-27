<?php
// mailsend_api.php
// API 전용 엔드포인트: POST(title, content) -> JSON 응답
// 사용: POST로 title, content 전송

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

$result = ['success' => false, 'message' => '요청 없음'];

// SMTP / PHPMailer 설정 (mailsend.php와 동일하게 유지)
$smtpEnabled = true;
$smtpHost = 'smtp.daum.net';
$smtpPort = 465;
$smtpSecure = 'ssl';
$smtpUser = 'sodamedia@daum.net';
$smtpPass = 'gebcngmmvxyynrfk';
$fromEmail = 'sodamedia@daum.net';
$fromName = '웹 알림';

// 수신자
$to = 'palace0261@naver.com';

// POST 데이터 수집
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if ($title === '' && $content === '') {
    $result['message'] = 'title 또는 content 필요';
    http_response_code(400);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$subject = $title !== '' ? $title : '웹페이지 알림';
$body = "입력값:\n" . $content . "\n\n보낸 시각: " . date('Y-m-d H:i:s');

// PHPMailer 사용
if ($smtpEnabled && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // 디버그 캡처용 콜백
        $debugLog = '';
        $mail->SMTPDebug = 2; // 문제 발생 시 2로 설정
        $mail->Debugoutput = function($str, $level) use (&$debugLog) { $debugLog .= trim($str) . "\n"; };

        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);

        $mail->send();
        $result['success'] = true;
        $result['message'] = '메일 발송 완료 (PHPMailer)';
        $result['debug'] = $debugLog;
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        $result['message'] = '메일 발송 실패 (PHPMailer): ' . $e->getMessage();
        if (!empty($debugLog)) $result['debug'] = $debugLog;
        http_response_code(500);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    // fallback mail()
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
    $headers = "From: " . $fromEmail . "\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    $sent = @mail($to, $encodedSubject, $body, $headers);
    if ($sent) {
        $result['success'] = true;
        $result['message'] = '메일 발송 완료 (mail())';
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        $result['message'] = '메일 발송 실패 (서버 설정 확인)';
        http_response_code(500);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

?>
