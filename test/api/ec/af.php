<?php
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AF Sender</title>
</head>
<body style="max-width: 1000px; margin: 0 auto;">

<div style="display: flex; height: 40px; align-items: center; gap: 8px; margin-top: 16px;">
<form id="af-form" autocomplete="off">
<label for="tzz">tzz</label>
<input type="text" id="tzz" name="tzz" value="">
<button type="submit">Submit</button>
</form>
</div>

<div id="status" aria-live="polite" style="margin-top:8px;color:#006;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('af-form') || document.querySelector('form');
  const status = document.getElementById('status');
  if (!form || !status) return;

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    status.style.color = '#006';
    status.textContent = '전송 중...';

    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const tzzVal = (form.querySelector('#tzz')?.value || form.querySelector('[name="tzz"]')?.value || '').toString().trim();
      if (tzzVal === '') {
        status.style.color = '#900';
        status.textContent = 'tzz 값이 비어 있습니다.';
        return;
      }

      let listUrl = 'erpdb.php?tzz=' + encodeURIComponent(tzzVal) + '&_=' + Date.now();
      if (location.protocol === 'file:') {
        listUrl = 'http://localhost' + location.pathname.replace(/\/[^/]*$/, '/' + listUrl);
      }

      const res = await fetch(listUrl, { method: 'GET', credentials: 'same-origin' });
      const text = await res.text();

      if (!res.ok) {
        status.style.color = '#900';
        status.textContent = '목록 조회 실패: ' + res.status + ' ' + res.statusText;
        return;
      }

      status.style.color = '#060';
      status.textContent = '목록 조회 완료. API 전송 준비 중...';

      const parser = new DOMParser();
      const doc = parser.parseFromString(text, 'text/html');

      const esc = (window.CSS && typeof CSS.escape === 'function')
        ? CSS.escape
        : function (v) { return String(v).replace(/["\\]/g, '\\$&'); };

      const scopedSelector = 'tbody.order-group[data-order="' + esc(tzzVal) + '"] form[method][action*="index.php"]';
      let apiForm = doc.querySelector(scopedSelector);
      if (!apiForm) apiForm = doc.querySelector('form[method][action*="index.php"]');

      if (!apiForm) {
        status.style.color = '#900';
        status.textContent = 'API 전송용 form(index.php)을 찾지 못했습니다.';
        return;
      }

      const apiAction = apiForm.getAttribute('action') || 'index.php';
      const apiUrl = new URL(apiAction, res.url || window.location.href).href;

      const payload = new URLSearchParams();
      const apiInputs = apiForm.querySelectorAll('input, select, textarea');

      apiInputs.forEach(function (el) {
        const name = el.getAttribute('name');
        if (!name) return;

        if (el.tagName === 'INPUT') {
          const type = (el.getAttribute('type') || '').toLowerCase();
          if (type === 'checkbox' || type === 'radio') {
            if (el.checked) payload.append(name, el.value);
          } else {
            payload.append(name, el.value);
          }
        } else if (el.tagName === 'SELECT') {
          payload.append(name, el.value);
        } else if (el.tagName === 'TEXTAREA') {
          payload.append(name, el.value);
        }
      });

      const apiRes = await fetch(apiUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: payload.toString(),
        credentials: 'same-origin',
        redirect: 'follow'
      });

      if (apiRes.ok) {
        status.style.color = '#060';
        status.textContent = 'API 전송 완료.';
      } else {
        const apiErrText = await apiRes.text();
        status.style.color = '#900';
        status.textContent = 'API 전송 실패: ' + apiRes.status + ' ' + apiRes.statusText + (apiErrText ? ' / ' + apiErrText.slice(0, 120) : '');
      }
    } catch (err) {
      status.style.color = '#900';
      status.textContent = '전송 중 오류: ' + (err?.message || String(err));
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
});
</script>

</body>
</html>
