// PHP 서버 주소 (실제 서버 주소로 변경 필요)
// 로컬 테스트: 'upload-dropbox.php', 'submit-flow.php'
// 실제 서버: 'https://PHP서버주소/upload-dropbox.php' 형태로 변경
const PHP_SERVER = '';

document.getElementById('today_date').value = new Date().toISOString().substring(0, 10);

const autoHyphen = (target) => {
    target.value = target.value
        .replace(/[^0-9]/g, '')
        .replace(/^(\d{0,3})(\d{0,4})(\d{0,4})$/g, "$1-$2-$3")
        .replace(/(\-{1,2})$/g, "");
}

function previewImages(input) {
    const preview = document.getElementById('image_preview');
    preview.innerHTML = '';
    for (let file of input.files) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.createElement('img');
            img.src = e.target.result;
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    }
}

function previewFile(input) {
    const preview = document.getElementById('file_name_preview');
    if (input.files.length > 0) {
        preview.innerHTML = `<div class="file-name">📎 ${input.files[0].name}</div>`;
    } else {
        preview.innerHTML = '';
    }
}

const toBase64 = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result.split(',')[1]);
    reader.onerror = reject;
    reader.readAsDataURL(file);
});

async function uploadFileViaPhp(file, folderName) {
    const base64 = await toBase64(file);
    const res = await fetch(PHP_SERVER + 'https://port-9000-testphp-ma6q5cjy22737d6f.sel4.cloudtype.app/as/as-form/upload-dropbox.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fileBase64: base64,
            fileName: file.name,
            folderName: folderName
        })
    });
    const data = await res.json();
    if (!res.ok) {
        throw new Error(data.error || '파일 업로드 실패');
    }
    return data.url;
}

async function submitViaPhp(title, contents) {
    const res = await fetch(PHP_SERVER + 'https://port-9000-testphp-ma6q5cjy22737d6f.sel4.cloudtype.app/as/as-form/submit-flow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, contents })
    });
    const data = await res.json();
    if (!res.ok) {
        throw new Error(data.error || '접수 전송 실패');
    }
    return data;
}

async function submitToFlow() {
    const requiredFields = [
        { id: 'user_name',    name: '신청인 정보' },
        { id: 'user_phone',   name: '연락처' },
        { id: 'user_address', name: '주소' },
        { id: 'prod_name',    name: '제품명' },
        { id: 'symptom_desc', name: '증상 내용' }
    ];

    for (let field of requiredFields) {
        if (!document.getElementById(field.id).value.trim()) {
            alert(`[${field.name}] 항목은 필수 입력 사항입니다.`);
            document.getElementById(field.id).focus();
            return;
        }
    }

    const phoneVal = document.getElementById('user_phone').value.trim();
    if (!/^01[0-9]-\d{3,4}-\d{4}$/.test(phoneVal)) {
        alert('연락처 형식이 올바르지 않습니다.\n예: 010-1234-5678');
        document.getElementById('user_phone').focus();
        return;
    }

    const name     = document.getElementById('user_name').value.trim();
    const phone    = document.getElementById('user_phone').value.trim();
    const address  = document.getElementById('user_address').value.trim();
    const prodName = document.getElementById('prod_name').value.trim();
    const prodSn   = document.getElementById('prod_sn').value.trim();
    const buyDate  = document.getElementById('buy_date').value;
    const today    = document.getElementById('today_date').value;
    const symptom  = document.getElementById('symptom_desc').value.trim();

    const imageInput  = document.getElementById('image_files');
    const attachInput = document.getElementById('attach_files');

    if (imageInput.files.length > 5) {
        alert('이미지는 최대 5장까지 첨부 가능합니다.');
        return;
    }

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;

    const now = new Date();
    const dateStr = now.getFullYear().toString() +
        String(now.getMonth() + 1).padStart(2, '0') +
        String(now.getDate()).padStart(2, '0') + '_' +
        String(now.getHours()).padStart(2, '0') +
        String(now.getMinutes()).padStart(2, '0') +
        String(now.getSeconds()).padStart(2, '0');
    const folderName = `${dateStr}_${prodName.replace(/[^a-zA-Z0-9]/g, '') || 'item'}`;

    try {
        const imageLinks = [];
        const totalFiles = imageInput.files.length + (attachInput.files.length > 0 ? 1 : 0);
        let uploadedCount = 0;

        for (let file of imageInput.files) {
            btn.textContent = `이미지 업로드 중... (${++uploadedCount}/${totalFiles})`;
            const url = await uploadFileViaPhp(file, folderName);
            imageLinks.push({ name: file.name, url });
        }

        let attachLink = null;
        if (attachInput.files.length > 0) {
            btn.textContent = `파일 업로드 중... (${++uploadedCount}/${totalFiles})`;
            const file = attachInput.files[0];
            const url = await uploadFileViaPhp(file, folderName);
            attachLink = { name: file.name, url };
        }

        const title = `[A/S 접수] ${name} / ${prodName}`;

        let contents =
            `■ 신청인: ${name}\n` +
            `■ 연락처: ${phone}\n` +
            `■ 수령주소: ${address}\n` +
            `■ 제품명: ${prodName}\n` +
            `■ 시리얼번호: ${prodSn || '미입력'}\n` +
            `■ 구매일자: ${buyDate || '미입력'}\n` +
            `■ 접수일자: ${today}\n\n` +
            `■ 증상 내용:\n${symptom}`;

        if (imageLinks.length > 0) {
            contents += `\n\n■ 첨부 이미지 (${imageLinks.length}장):\n`;
            imageLinks.forEach((img, i) => {
                contents += `${i + 1}. ${img.name}\n${img.url}\n`;
            });
        }

        if (attachLink) {
            contents += `\n■ 첨부 파일:\n${attachLink.name}\n${attachLink.url}\n`;
        }

        btn.textContent = '접수 등록 중...';

        await submitViaPhp(title, contents);

        document.getElementById('modal-overlay').style.display = 'flex';

    } catch (error) {
        alert('접수 중 오류가 발생했습니다.\n' + error.message);
        console.error(error);
    } finally {
        btn.disabled = false;
        btn.textContent = 'A/S 접수 신청하기';
    }
}
