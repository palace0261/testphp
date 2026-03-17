<?php

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>자동메일 발송 - API 전송</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    div { margin-bottom: 15px; }
    label { display: inline-block; width: 100px; font-weight: bold; }
    input[type="text"], input[type="submit"] { padding: 8px; font-size: 14px; }
    input[type="text"] { border: 1px solid #ccc; border-radius: 4px; }
    input[type="submit"] { background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
    input[type="submit"]:hover { background-color: #45a049; }
  </style>
</head>
<body>
  <h2>자동메일 발송 전 Flow API 전송</h2>
  <div>
    <label>API Key:</label>
    <input id="apiKey" type="text" style="width: 400px;" value="20260310042354473-54f40d54-0833-4c3b-9f4a-1ce8e39c98f6" readonly>
  </div>
  <div>
    <label>메시지:</label>
    <input id="contents" type="text" value="테스트 메시지ㅁㅁ" style="width: 400px;">
  </div>
  <input id="submitBtn" type="submit" value="전송">

  <script>
    function sendFlowTeamApi() {
      const apiKey = document.getElementById('apiKey').value;
      const contents = document.getElementById('contents').value;
      
      if (!apiKey) {
        console.warn('API 키가 없습니다.');
        return Promise.reject('API 키를 입력해주세요.');
      }
      
      const options = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-flow-api-key': apiKey,
        },
        body: JSON.stringify({
          registerId: 'palace790@gmail.com',
          contents: contents,
        }),
      };
      
      return fetch('https://api.flow.team/v1/chats/3926704/messages', options)
        .then(response => {
          console.log('응답 상태:', response.status, response.statusText);
          if (!response.ok) {
            return response.text().then(text => {
              console.error('응답 내용:', text);
              throw new Error(`HTTP ${response.status}: ${text}`);
            });
          }
          return response.json();
        })
        .then(data => {
          console.log('전송 성공:', data);
          return data;
        })
        .catch(err => {
          console.error('전송 실패:', err);
          throw err;
        });
    }

    // 페이지 로드 시 자동으로 API 전송
    window.addEventListener('load', function() {
      console.log('페이지 로드 완료 - 자동 API 전송 시작');
      sendFlowTeamApi()
        .then(data => {
          console.log('자동 API 전송 성공:', data);
        })
        .catch(err => {
          console.warn('자동 API 전송 실패:', err.message);
        });
    });

    // 수동 전송 버튼 클릭 이벤트
    document.getElementById('submitBtn').addEventListener('click', function(e) {
      e.preventDefault();
      
      sendFlowTeamApi()
        .then(data => {
          alert('메시지가 전송되었습니다!');
        })
        .catch(err => {
          alert('메시지 전송에 실패했습니다: ' + err.message);
        });
    });
  </script>

</body>
</html>