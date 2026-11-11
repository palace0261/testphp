# PHP Sample App using Composer

## Dropbox refresh token 발급

1. Dropbox 앱 `f6szxcyq0j1sna9`의 설정에서 다음 redirect URI를 등록합니다.
   `https://port-9000-testphp-ma6q5cjy22737d6f.sel4.cloudtype.app/as/as-form/dropbox-auth.php`
2. 서버 환경 변수 `DROPBOX_OAUTH_APP_SECRET`에 해당 앱의 App secret을 설정합니다.
3. HTTPS로 위 주소에 접속해 Dropbox 권한을 승인하면 refresh token이 표시됩니다.
   토큰은 비밀로 보관하고 사용 후 브라우저 화면을 닫습니다.

기존 `dropbox-token.php`는 콜백 페이지가 아니라 refresh token을 사용해
access token을 발급하는 함수입니다. 기존 업로드 설정의 앱 키와 refresh token은
위 앱과 별개이므로, 새 토큰을 업로드에 사용하려면 해당 앱의 키와 시크릿도
함께 맞춰야 합니다. 인증 페이지는 서버 세션과 PHP cURL 확장이 필요합니다.
