<?php
// 간단한 메일 전송 폼 + POST 핸들러
// 주의: 이 코드는 PHP의 mail()을 사용합니다. 로컬 XAMPP에서 실제 발송하려면
// sendmail 설정 또는 SMTP를 통한 발송(PHPMailer 등) 구성이 필요합니다.

mb_internal_encoding('UTF-8');
$resultMessage = '';

// SMTP / PHPMailer 설정 (아래 값을 실제 SMTP 정보로 변경하세요)
$smtpEnabled = true; // true로 설정하면 PHPMailer 사용
$smtpHost = 'smtp.daum.net'; // SMTP 서버 주소
$smtpPort = 465; // 465(SSL) 또는 587(TLS)
$smtpSecure = 'ssl'; // 'ssl' 또는 'tls'
$smtpUser = 'sodamedia@daum.net';
$smtpPass = 'gebcngmmvxyynrfk';
$fromEmail = 'sodamedia@daum.net';
$fromName = '웹 알림';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $to = 'palace0261@naver.com';
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');

  if ($title === '' && $content === '') {
    $resultMessage = '보낼 값이 없습니다.';
  } else {
    $subject = $title !== '' ? $title : '웹페이지 알림';
    $body = "입력값:\n" . $content . "\n\n보낸 시각: " . date('Y-m-d H:i:s');

    if ($smtpEnabled && file_exists(__DIR__ . '/vendor/autoload.php')) {
      require __DIR__ . '/vendor/autoload.php';
      // PHPMailer 사용
      try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // 디버그 활성화: SMTP 통신 로그를 HTML로 출력합니다. 문제 해결 후 0으로 변경하세요.
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';
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
        $resultMessage = '메일 발송 완료 (PHPMailer)';
      } catch (Exception $e) {
        $resultMessage = '메일 발송 실패 (PHPMailer): ' . $e->getMessage();
      }
    } else {
      // PHPMailer 미설치시 기본 mail() 사용
      $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
      $headers = "From: " . $fromEmail . "\r\n";
      $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
      $sent = @mail($to, $encodedSubject, $body, $headers);
      $resultMessage = $sent ? '메일 발송 완료 (mail())' : '메일 발송 실패 (서버 설정 확인 필요)';
    }
  }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>메일 전송</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px}input,textarea{width:100%;max-width:600px;margin:8px 0;padding:8px}</style>
</head>
<body>
  <h2>메일 전송ㅁㅁ</h2>
  <?php if ($resultMessage !== ''): ?>
    <p><strong><?php echo htmlspecialchars($resultMessage, ENT_QUOTES, 'UTF-8'); ?></strong></p>
  <?php endif; ?>

  <form action="" method="post">
    <label>메일 제목</label>
    <input type="text" id="title" name="title" value="전송 실패" placeholder="메일 제목">
    <label>메일 내용</label>
    <textarea id="content" name="content" placeholder="메일 내용">전송 실패</textarea>
    <button type="submit" id="ms7" name="ms7">메일전송</button>
  </form>

</body>
</html>