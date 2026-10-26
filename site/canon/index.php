<?php
$pdsCats = ['manual' => '매뉴얼', 'data' => '자료실'];
$pdsAll = [
    'manual-n300-n500-en' => [
        'cat' => 'manual',
        'title' => 'CANON PTZ카메라 영문 매뉴얼_N300_N500',
        'url' => 'http://soda0041093.godomall.com/data/file/canon/mn/%5Bmanual%5DCanonPtzN300%2CN500-EN.pdf',
        'file' => 'CANON PTZ camera manual_N300_N500_EN.pdf',
    ],
    'tool-ip-win' => [
        'cat' => 'data',
        'title' => 'Window OS 용 CANON PTZ IP 검색 툴',
        'url' => null,
    ],
];

// 허용 목록에 있는 파일만 PDF 다운로드로 전달
if (isset($_GET['dl'], $pdsAll[$_GET['dl']]) && $pdsAll[$_GET['dl']]['url']) {
    $it = $pdsAll[$_GET['dl']];
    $ch = curl_init($it['url']);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
    $body = curl_exec($ch);
    $ok = $body !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    if (!$ok) {
        header('Location: ' . $it['url']);
        exit;
    }
    header('Content-Type: application/pdf');
    header("Content-Disposition: attachment; filename=\"manual.pdf\"; filename*=UTF-8''" . rawurlencode($it['file']));
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

$cat = $_GET['cat'] ?? '';
if (!isset($pdsCats[$cat])) $cat = '';
$q = trim((string)($_GET['q'] ?? ''));
$pdsList = array_filter($pdsAll, fn($it) => ($cat === '' || $it['cat'] === $cat)
    && ($q === '' || mb_stripos($it['title'], $q) !== false));

$pages = [
    'main'    => '홈',
    'product' => '제품정보',
    'support' => '고객지원',
    'about'   => '회사소개',
    'demo'    => '데모요청',
];
$p = $_GET['p'] ?? 'main';
if (!isset($pages[$p]) && !in_array($p, ['detail', 'pds', 'way'], true)) $p = 'main';
$view = $pdsAll[$_GET['id'] ?? ''] ?? null;
if ($p === 'pds' && !$view) $p = 'support';

$products = json_decode((string)@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
$products = array_column($products, null, 'slug');
$pcats = ['camera' => 'PTZ 카메라', 'controller' => 'PTZ 컨트롤러'];
$pcat = $_GET['cat'] ?? '';
if (!isset($pcats[$pcat])) $pcat = '';
$prd = $products[$_GET['id'] ?? ''] ?? null;
if ($p === 'detail' && !$prd) $p = 'product';
$img = fn($f) => '../a-cn-img/' . $f;
$e = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$sent = $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($p, ['demo', 'support'], true);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Canon PTZ 공식 리셀러 | 소다미디어</title>
<link rel="stylesheet" href="../a-cn-css/index.css?v=<?= filemtime(__DIR__ . '/../a-cn-css/index.css') ?>">
</head>
<body>
<?php
$menu = [
    ['제품정보', '?p=product', [['제품정보', '?p=product']]],
    ['고객지원', '?p=support', [['매뉴얼', '?p=support&cat=manual'], ['자료실', '?p=support&cat=data'], ['상담신청', '?p=support#consult']]],
    ['회사소개', '?p=about', [['인사말', '?p=about#greet'], ['오시는길', '?p=way']]],
    ['데모요청', '?p=demo', [['데모요청', '?p=demo']]],
];
?>
<header id="hd"><div class="in">
  <a class="logo" href="?p=main"><img class="lg" src="../a-cn-img/logo.png" alt="Canon PTZ"><img class="lg-on" src="../a-cn-img/logo_on.png" alt=""></a>
  <nav><?php foreach ($pages as $k => $v): ?><a href="?p=<?= $k ?>" class="<?= $k === $p ? 'on' : '' ?>"><?= $e($v) ?></a><?php endforeach; ?></nav>
  <button type="button" class="btn-menu" id="btnMenu" aria-label="메뉴 열기" aria-expanded="false"><span></span><span></span><span></span></button>
</div></header>
<div class="m-menu" id="mMenu" aria-hidden="true">
  <button type="button" class="m-close" id="mClose" aria-label="메뉴 닫기"></button>
  <div class="m-cols">
    <?php foreach ($menu as $m): ?>
      <div class="m-col"><a class="m-h" href="<?= $e($m[1]) ?>"><?= $e($m[0]) ?></a>
        <?php foreach ($m[2] as $s): ?><a href="<?= $e($s[1]) ?>"><?= $e($s[0]) ?></a><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  var b = document.getElementById('btnMenu'), m = document.getElementById('mMenu');
  function set(o) {
    m.classList.toggle('open', o);
    b.setAttribute('aria-expanded', o);
    m.setAttribute('aria-hidden', !o);
    document.body.style.overflow = o ? 'hidden' : '';
  }
  b.addEventListener('click', function () { set(true); });
  document.getElementById('mClose').addEventListener('click', function () { set(false); });
  m.addEventListener('click', function (e) { if (e.target.tagName === 'A') set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  window.addEventListener('resize', function () { if (window.innerWidth > 900) set(false); });
})();
</script>

<?php if ($p === 'main'): ?>
<div class="hero" id="hero">
  <?php
  $slides = [
    ['a3.jpg', '새로운 Canon PTZ 카메라', 'New Canon<br>PTZ Camera'],
    ['a1.jpg', 'NEW SMC-10', 'CANON PTZ 호환<br> 고급 IP 컨트롤러'],
    ['a2.png', 'CANON 공식 리셀러 소다미디어', 'CANON Official<br>Reseller'],
  ];
  foreach ($slides as $i => $s): ?>
  <div class="slide<?= $i === 0 ? ' on' : '' ?>" style="background-image:url('../a-cn-img/<?= $s[0] ?>')">
    <div class="in txt"><h2 class="s-t"><?= $e($s[1]) ?></h2><p class="s-p"><?= $s[2] ?></p></div>
  </div>
  <?php endforeach; ?>
  <div class="dots"><?php foreach ($slides as $i => $s): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" aria-label="slide <?= $i + 1 ?>"></button><?php endforeach; ?></div>

</div>
<script>
(function () {
  var hd = document.getElementById('hd');
  function onScroll() { hd.classList.toggle('scrolled', window.scrollY > 10); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  var slides = document.querySelectorAll('#hero .slide'),
      dots = document.querySelectorAll('#hero .dots button'), cur = 0, timer;
  function go(n) {
    slides[cur].classList.remove('on'); dots[cur].classList.remove('on');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('on'); dots[cur].classList.add('on');
  }
  function start() { clearInterval(timer); timer = setInterval(function () { go(cur + 1); }, 5000); }
  dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); start(); }); });
  start();
})();
</script>
<?php else: ?>
<script>
(function () {
  var hd = document.getElementById('hd');
  function onScroll() { hd.classList.toggle('scrolled', window.scrollY > 10); }
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
<?php endif; ?>
<?php if ($p === 'main'): ?>
<section><div class="in">
  <h2 class="h">PRODUCTS</h2>
  <p class="lead">판매하는 모든 캐논 카메라는 캐논 코리아에서 보증하는 정품입니다.</p>
  <div class="grid">
    <a class="card" href="?p=product"><div class="thumb"><img src="../a-cn-img/ms1_li1.jpg" alt="제품소개"><span>01</span></div><div class="b"><h3>제품소개</h3><p>MORE VIEW →</p></div></a>
    <a class="card" href="?p=support"><div class="thumb"><img src="../a-cn-img/ms1_li2.jpg" alt="고객지원"><span>02</span></div><div class="b"><h3>고객지원</h3><p>MORE VIEW →</p></div></a>
    <a class="card" href="?p=demo"><div class="thumb"><img src="../a-cn-img/ms1_li3.jpg" alt="데모요청"><span>03</span></div><div class="b"><h3>데모요청</h3><p>MORE VIEW →</p></div></a>
  </div>
</div></section>
<section class="gray"><div class="in">
  <h2 class="h">CONTACT US</h2>
  <p class="lead">상담신청을 통해 문의를 남겨주세요.</p>
  <div class="contact">
    <div><b>구매 문의</b>070-8866-1171</div>
    <div><b>영업시간</b>오전 10시 ~ 오후 7시 (토/일/공휴일 휴무)</div>
    <div><b>점심식사</b>오후 1시 ~ 2시</div>
    <div><b>이메일</b>sodamedia@hanmal.net</div>
  </div>
</div></section>
<section><div class="in">
  <div class="sec-head"><h2 class="h2l">자료실</h2><a class="plus" href="?p=support" aria-label="자료실 더보기">+</a></div>
  <ul class="pds-mini">
    <?php foreach (array_slice($pdsAll, 0, 3, true) as $id => $it): ?>
      <li><a href="?p=pds&id=<?= $e($id) ?>"><strong><?= $e($it['title']) ?></strong><span><?= $e($pdsCats[$it['cat']]) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</div></section>
<section class="loc"><div class="in">
  <div class="sec-head"><h2 class="h2l">LOCATION</h2><a class="plus" href="?p=way" aria-label="오시는길">+</a></div>
  <iframe class="map" title="오시는길" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
    src="https://maps.google.com/maps?q=<?= urlencode('서울특별시 강서구 양천로 400-12') ?>&z=16&output=embed"></iframe>
</div></section>

<?php elseif ($p === 'product'): ?>
<div class="sub"><div class="title"><h2>제품정보</h2></div></div>
<section><div class="in">
  <div class="tabs">
    <?php foreach (['' => '전체'] + $pcats as $k => $v): ?>
      <a href="?p=product&cat=<?= urlencode($k) ?>" class="<?= $k === $pcat ? 'on' : '' ?>"><?= $e($v) ?></a>
    <?php endforeach; ?>
  </div>
  <?php foreach ($pcats as $ck => $cv): if ($pcat !== '' && $pcat !== $ck) continue; ?>
    <h3 class="grp"><?= $e($cv) ?></h3>
    <div class="grid">
    <?php foreach ($products as $x): if ($x['cat'] !== $ck) continue; ?>
      <a class="card" href="?p=detail&id=<?= $e($x['slug']) ?>">
        <div class="thumb pimg"><img src="<?= $e($img($x['gallery'][0] ?? '')) ?>" alt="<?= $e($x['name']) ?>" loading="lazy"></div>
        <div class="b"><h3><?= $e($x['name']) ?></h3><small><?= $e($cv) ?></small></div>
      </a>
    <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div></section>

<?php elseif ($p === 'detail'): ?>
<div class="sub"><div class="title"><h2><?= $e($prd['name']) ?></h2></div></div>
<section><div class="in" style="max-width:900px">
  <p class="crumb"><a href="?p=product">제품정보</a> &gt; <a href="?p=product&cat=<?= $e($prd['cat']) ?>"><?= $e($pcats[$prd['cat']]) ?></a> &gt; <?= $e($prd['name']) ?></p>
  <?php if ($prd['gallery']): ?>
  <div class="gal">
    <img id="main-img" src="<?= $e($img($prd['gallery'][0])) ?>" alt="<?= $e($prd['name']) ?>">
    <div class="thumbs">
      <?php foreach ($prd['gallery'] as $g): ?>
        <img src="<?= $e($img($g)) ?>" alt="" onclick="document.getElementById('main-img').src=this.src">
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($prd['info']): ?>
    <table class="info"><?php foreach ($prd['info'] as $r): ?><tr><th><?= $e($r[0]) ?></th><td><?= $e($r[1]) ?></td></tr><?php endforeach; ?></table>
  <?php endif; ?>
  <?php if ($prd['detail']): ?>
    <h2 class="h" style="margin-top:60px">제품특징</h2><p class="lead">&nbsp;</p>
    <?php foreach ($prd['detail'] as $d): ?>
      <img class="dimg" src="<?= $e($img($d)) ?>" alt="<?= $e($prd['name']) ?> 제품특징" loading="lazy">
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($prd['spec']): ?>
    <h2 class="h" style="margin-top:60px">제품사양</h2><p class="lead">&nbsp;</p>
    <div class="spec"><?= implode('', $prd['spec']) // 수집 시 정리한 로컬 데이터 ?></div>
  <?php endif; ?>
  <p style="text-align:center;margin-top:40px"><a href="?p=demo"><button type="button" style="padding:14px 40px">데모 요청</button></a></p>
  <p class="note">이미지·사양 출처: <a href="<?= $e($prd['source']) ?>" target="_blank" rel="noopener"><?= strpos($prd['source'], 'kr.canon') !== false ? '캐논코리아' : '소다미디어' ?></a></p>
</div></section>

<?php elseif ($p === 'pds'): ?>
<div class="sub"><div class="title"><h2>자료실</h2></div></div>
<section><div class="in" style="max-width:900px">
  <div style="border-top:2px solid #222;border-bottom:1px solid #ddd;padding:18px 10px">
    <small style="color:#e60012"><?= $e($pdsCats[$view['cat']]) ?></small>
    <h3 class="post-title"><?= $e($view['title']) ?></h3>
  </div>
  <div style="padding:40px 10px;min-height:160px;border-bottom:1px solid #ddd">
    <?php if ($view['url']): ?>
      <p><?= $e($view['title']) ?> 파일을 다운로드할 수 있습니다.</p>
      <p style="margin-top:20px"><a href="?dl=<?= $e($_GET['id']) ?>" style="display:inline-block;padding:12px 30px;background:#e60012;color:#fff;border-radius:4px">PDF 다운로드</a></p>
    <?php else: ?>
      <p>등록된 파일이 없습니다.</p>
    <?php endif; ?>
  </div>
  <p style="text-align:center;margin-top:30px"><a href="?p=support&cat=<?= $e($view['cat']) ?>" style="display:inline-block;padding:12px 30px;border:1px solid #888;border-radius:4px">목록</a></p>
</div></section>

<?php elseif ($p === 'support'): ?>
<div class="sub"><div class="title"><h2>고객지원</h2></div></div>
<section><div class="in">
  <h2 class="h">자료실</h2><p class="lead">매뉴얼 및 설치 도구</p>
  <div style="display:flex;gap:8px;justify-content:center;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['' => '전체'] + $pdsCats as $k => $v): ?>
      <a href="?p=support&cat=<?= urlencode($k) ?>&q=<?= urlencode($q) ?>" style="padding:8px 18px;border:1px solid <?= $k === $cat ? '#e60012' : '#ccc' ?>;border-radius:20px;<?= $k === $cat ? 'background:#e60012;color:#fff' : '' ?>"><?= $e($v) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" action="" style="display:flex;gap:8px;margin:0 auto 20px;max-width:560px">
    <input type="hidden" name="p" value="support"><input type="hidden" name="cat" value="<?= $e($cat) ?>">
    <input name="q" value="<?= $e($q) ?>" placeholder="제목 검색">
    <button style="white-space:nowrap;padding:0 24px">검색</button>
  </form>
  <table>
    <tr><th style="width:110px">분류</th><th>제목</th></tr>
    <?php foreach ($pdsList as $id => $it): ?>
      <tr><td><?= $e($pdsCats[$it['cat']]) ?></td><td><?php if ($it['url']): ?><a href="?p=pds&id=<?= $e($id) ?>" style="text-decoration:underline"><?= $e($it['title']) ?></a><?php else: ?><?= $e($it['title']) ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pdsList): ?><tr><td colspan="2" style="text-align:center;color:#888">검색 결과가 없습니다.</td></tr><?php endif; ?>
  </table>
</div></section>
<section class="gray" id="consult"><div class="in">
  <h2 class="h">상담신청</h2><p class="lead">&nbsp;</p>
  <?php if ($sent): ?><div class="ok">상담 신청이 접수되었습니다.</div><?php endif; ?>
  <form method="post" action="?p=support">
    <input name="name" placeholder="이름" required>
    <input name="tel" placeholder="연락처" required>
    <textarea name="msg" rows="5" placeholder="문의 내용" required></textarea>
    <button>상담 신청</button>
  </form>
</div></section>

<?php elseif ($p === 'about'): ?>
<div class="sub"><div class="title"><h2>회사소개</h2></div></div>
<section><div class="in" style="max-width:800px">
  <h2 class="h" id="greet">인사말</h2><p class="lead">GREETINGS</p>
  <p>
소다미디어는 캐논PTZ 공식 리셀러이며 최선의 고객지원 서비스를 위해 항상 노력하고 있습니다.<br><br>

오직 미디어 장비를 전문적으로 판매하고 있으며 판매도 중요하지만 사후관리를 더욱 중요하게 생각하며
모두 고객사의 대한 감사의 도리라고 생각합니다.<br><br>

단순히 물건만 판매하는 것에 목적을 두어 물건을 구매해주시는 고객사를 어렵게 만드는 일 없도록 항상 노 력하겠습니다, 치열한 경쟁 시대에
앞서 나갈 수 있도록 강력하게 도와드리는 가장 든든한 조력자가 되겠습니다.<br><br>

말뿐이 아닌 지속적인 상품 개발과 맞춤형 제안으로 고객 제일주의를 실현하겠습니다. 고객을 위해 흘리는 땀을 두려워하지 않는 정직한 기업이 될 것을 약속드립니다.<br><br>

소다미디어의 문은 고객여러분께 항상 열려있습니다.<br><br>
한분 한분 고객님들과 같이 커가는 소다미디어가 되겠습니다.<br>

감사합니다.</p>
</div></section>

<?php elseif ($p === 'way'): ?>
<div class="sub"><div class="title"><h2>오시는길</h2></div></div>
<section><div class="in">
  <p class="eyebrow">LOCATION</p>
  <h2 class="way-h">오시는길</h2>
  <iframe class="map" title="오시는길" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
    src="https://maps.google.com/maps?q=<?= urlencode('서울특별시 강서구 양천로 400-12') ?>&z=16&output=embed"></iframe>
  <table class="way-tb">
    <tr><th>주소</th><td>서울특별시 강서구 양천로 400-12 (더리브골드타워) 1112호(사무실) 1114호(STUDIO)</td></tr>
    <tr><th>대표번호</th><td>070-8866-1171</td></tr>
    <tr><th>AS센터</th><td>010-7679-1037</td></tr>
    <tr><th>메일</th><td>sodamedia@hanmail.net</td></tr>
  </table>
</div></section>

<?php elseif ($p === 'demo'): ?>
<div class="sub"><div class="title"><h2>데모요청</h2></div></div>
<section><div class="in">
  <?php if ($sent): ?><div class="ok">데모 요청이 접수되었습니다.</div><?php endif; ?>
  <form method="post" action="?p=demo">
    <input name="name" placeholder="이름 / 회사명" required>
    <input name="tel" placeholder="연락처" required>
    <select name="model"><?php foreach ($products as $x): ?><option><?= $e($x['name']) ?></option><?php endforeach; ?></select>
    <textarea name="msg" rows="5" placeholder="요청 사항"></textarea>
    <button>데모 요청</button>
  </form>
</div></section>
<?php endif; ?>

<footer><div class="in">
  <img class="f-logo" src="../a-cn-img/f_logo.png" alt="Canon PTZ">
  <ul class="f-info">
    <li><span><b>상호명</b> : 소다미디어</span><span><b>주소</b> : 서울특별시 강서구 양천로 400-12 (더리브골드타워) 1112호(사무실) 1114호(STUDIO)</span></li>
    <li><span><b>대표번호</b> : 070-8866-1171</span><span><b>AS센터</b> : 010-7679-1037</span></li>
    <li><span><b>사업자등록번호</b> : 181-07-00410</span><span><b>통신판매업신고</b> : 2020-서울강서-2732</span></li>
    <li><span><b>개인정보관리자</b> : 서정우</span><span><b>메일</b> : sodamedia@hanmail.net</span></li>
    <li><span><b>영업시간</b> : 오전 10시 ~ 오후 7시 (토/일/공휴일 휴무)</span><span><b>점심식사</b> : 오후 1시 ~ 2시</span></li>
  </ul>
  <p class="copy">COPYRIGHT(C) 소다미디어 ALL RIGHT RESERVED.</p>
</div></footer>
</body>
</html>