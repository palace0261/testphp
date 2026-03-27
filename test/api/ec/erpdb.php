<?php
// 0326 2026 - test 테이블

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// CORS: allow cross-origin fetch from browser clients
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Credentials: true');
  header('Vary: Origin');
} else {
  header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
  http_response_code(204);
  exit;
}
$dbHost = 'svc.sel4.cloudtype.app:31446';
$dbUser = 'root';
$dbPass = 'palace0261@@';
$dbName = 'palace0261';

$tableName = 'erp_testTable';
$startAutoIncrement = 33;

$message = '';
$formError = '';
$dbError = '';
$listError = '';
$schemaWarning = '';
$rows = [];

// ECOUNT(ERP) API 전송 기본값 (ec/index.php로 POST)
$ecountEnv = 'test';
$ecountComCode = '608196';
$ecountUploadSerNo = '1';
$ecountWhCd = '100';
// 로그인 정보(필요 시 값 설정)
$ecountUserId = '추민식';
$ecountApiCertKey = '2d6396d0386044669b7a1b011190f7aee0';
$ecountLanType = 'ko-KR';
$ecountLoginPath = '/OAPI/V2/OAPILogin';

function h(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_self(array $params = []): void
{
  $base = strtok($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'], '?');
  $qs = $params ? ('?' . http_build_query($params)) : '';
  header('Location: ' . $base . $qs);
  exit;
}

function post_str(string $key): string
{
  $value = $_POST[$key] ?? '';
  if (!is_string($value)) {
    return '';
  }
  return trim($value);
}

function post_int(string $key): ?int
{
  $value = post_str($key);
  if ($value === '') {
    return null;
  }
  if (!preg_match('/^-?\d+$/', $value)) {
    return null;
  }
  return (int)$value;
}

$ok = isset($_GET['ok']) ? (string)$_GET['ok'] : '';
if ($ok === 'insert') {
  $message = '전송 완료: DB에 저장했습니다.';
} elseif ($ok === 'update') {
  $message = '변경 완료: DB에 반영했습니다.';
} elseif ($ok === 'error') {
  $formError = '요청 처리 중 오류가 발생했습니다.';
}

$mysqli = null;
try {
  // .env에서 포트/소켓 처리
  $dbSocket = $env['DB_SOCKET'] ?? '';
  $dbPortRaw = $env['DB_PORT'] ?? '';

  $host = $dbHost;
  $port = 0;

  // DB_HOST에 host:port 형식으로 들어왔을 때 분리 (도메인:포트)
  if ($host !== '' && strpos($host, ':') !== false && ($dbPortRaw === null || $dbPortRaw === '')) {
    $parts = explode(':', $host);
    $maybePort = array_pop($parts);
    if (ctype_digit($maybePort)) {
      $port = (int)$maybePort;
      $host = implode(':', $parts);
    }
  }

  // DB_PORT 환경변수가 있으면 우선 사용
  if ($dbPortRaw !== null && $dbPortRaw !== '' && ctype_digit((string)$dbPortRaw)) {
    $port = (int)$dbPortRaw;
  }

  // 소켓은 로컬 접속에 대해서만 사용하도록 제한합니다. 원격 호스트 연결 시 소켓을 전달하면 "No such file or directory" 오류 발생 가능.
  $useSocket = false;
  $normalizedHost = strtolower((string)$host);
  if ($dbSocket !== '' && ($normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1' || $normalizedHost === '::1')) {
    $useSocket = true;
  }

  if ($useSocket) {
    // localhost + socket 사용
    $mysqli = new mysqli('localhost', $dbUser, $dbPass, $dbName, $port, $dbSocket);
  } else {
    // 호스트/포트 기반 연결 (소켓 전달하지 않음)
    if ($port > 0) {
      $mysqli = new mysqli($host, $dbUser, $dbPass, $dbName, $port);
    } else {
      $mysqli = new mysqli($host, $dbUser, $dbPass, $dbName);
    }
  }

  $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
  $dbError = $e->getMessage();
}

if ($dbError === '' && $mysqli instanceof mysqli) {
  // 테이블이 존재하지 않으면 생성하고 AUTO_INCREMENT 초기값을 적용합니다.
  try {
    $createSql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
      `sno` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `CUST` VARCHAR(191) DEFAULT '',
      `PROD_CD` VARCHAR(191) DEFAULT '',
      `PROD_DES` VARCHAR(191) DEFAULT '',
      `QTY` INT DEFAULT 0,
      `PRICE` INT DEFAULT 0,
      `SUPPLY_AMT` INT DEFAULT 0,
      `VAT_AMT` INT DEFAULT 0,
      `RE_PRICE` INT DEFAULT 0,
      `DEPOSIT` INT DEFAULT 0,
      `MILEAGE` INT DEFAULT 0,
      `ch` VARCHAR(191) DEFAULT '',
      `U_MEMO1` VARCHAR(255) DEFAULT '',
      `U_MEMO2` VARCHAR(255) DEFAULT '',
      `U_MEMO3` VARCHAR(255) DEFAULT '',
      `U_MEMO4` VARCHAR(255) DEFAULT '',
      `orderno` VARCHAR(191) DEFAULT '',
      `ordernm` VARCHAR(191) DEFAULT '',
      PRIMARY KEY (`sno`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $mysqli->query($createSql);

    // AUTO_INCREMENT이 설정값보다 작으면 증가시킵니다.
    $stmt = $mysqli->prepare("SELECT `AUTO_INCREMENT` FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $dbNameForQuery = $dbName;
    $stmt->bind_param('ss', $dbNameForQuery, $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $currentAi = isset($row['AUTO_INCREMENT']) ? (int)$row['AUTO_INCREMENT'] : 0;
    $stmt->close();
    if ($currentAi === 0 || $currentAi < (int)$startAutoIncrement) {
      $setAi = (int)$startAutoIncrement;
      $mysqli->query("ALTER TABLE `{$tableName}` AUTO_INCREMENT = " . $setAi);
    }
    // 기존 테이블이 존재하는 경우, 새로 추가한 컬럼이 없으면 자동으로 추가합니다.
    $checkCols = [
      'RE_PRICE' => "INT DEFAULT 0",
      'DEPOSIT' => "INT DEFAULT 0",
      'MILEAGE' => "INT DEFAULT 0",
    ];
    foreach ($checkCols as $col => $def) {
      $stmtC = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
      $dbNameForQuery = $dbName;
      $stmtC->bind_param('sss', $dbNameForQuery, $tableName, $col);
      $stmtC->execute();
      $resC = $stmtC->get_result();
      $rowC = $resC ? $resC->fetch_assoc() : null;
      $stmtC->close();
      $exists = isset($rowC['cnt']) && (int)$rowC['cnt'] > 0;
      if (!$exists) {
        try {
          $mysqli->query("ALTER TABLE `{$tableName}` ADD COLUMN `{$col}` {$def}");
        } catch (Throwable $e) {
          $schemaWarning .= ' | ALTER COLUMN ' . $col . ' failed: ' . $e->getMessage();
        }
      }
    }
  } catch (Throwable $e) {
    // 테이블 생성/스키마 관련 경고를 보관
    $schemaWarning = $e->getMessage();
  }
  try {
    $requestMethod = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
      ? $_SERVER['REQUEST_METHOD']
      : 'GET';

    if ($requestMethod === 'POST') {
      $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

      if ($action === 'insert') {
        $cust = post_str('cust');
        $prodCd = post_str('prod_cd');
        $prodDes = post_str('prod_des');
        $qty = post_int('qty');
        $price = post_int('price');
        $rePrice = post_int('re_price');
        $deposit = post_int('deposit');
        $mileage = post_int('mileage');
        $ch = post_str('ch');
        $memo1 = post_str('U_MEMO1');
        $memo2 = post_str('U_MEMO2');
        $memo3 = post_str('U_MEMO3');
        $memo4 = post_str('U_MEMO4');
        $orderno = post_str('orderno');
        $ordernm = post_str('ordernm');

        if ($cust === '' || $prodCd === '' || $qty === null) {
          $formError = '필수값이 비어있거나 숫자 형식이 올바르지 않습니다.';
        } else {
          // RE_PRICE(총액)가 제공되면 이를 우선으로 공급가/부가세를 계산합니다.
          // RE_PRICE는 총액(공급가+VAT)으로 입력되므로 공급가는 RE_PRICE / 1.1 로 계산합니다.
          if ($rePrice !== null && $rePrice !== 0) {
            // RE_PRICE는 단가(총액 = 공급가+VAT)로 들어온다고 가정합니다.
            // 단가 기준으로 단위 공급가/부가세를 계산하고, 전체 공급금액은 수량(qty)을 곱합니다.
            $unitSupply = (int)round($rePrice / 1.1);
            $unitVat = (int)($rePrice - $unitSupply);
            $price = $unitSupply; // PRICE는 단가(공급가 단위)로 저장
            $qtyVal = ($qty === null) ? 1 : (int)$qty;
            if ($qtyVal < 1) $qtyVal = 1;
            $supplyAmt = $unitSupply * $qtyVal; // 전체 공급액
            $vatAmt = $unitVat * $qtyVal; // 전체 부가세
          } elseif ($price === null) {
            $supplyAmt = 0;
            $vatAmt = 0;
          } else {
            $supplyAmt = (int)$price;
            $vatAmt = (int)round($price * 0.1);
          }

          $stmt = $mysqli->prepare(
            "INSERT INTO `{$tableName}` (`CUST`, `PROD_CD`, `PROD_DES`, `QTY`, `PRICE`, `SUPPLY_AMT`, `VAT_AMT`, `RE_PRICE`, `DEPOSIT`, `MILEAGE`, `ch`, `U_MEMO1`, `U_MEMO2`, `U_MEMO3`, `U_MEMO4`, `orderno`, `ordernm`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
          );
          $priceVal = $price === null ? 0 : (int)$price;
          $rePriceVal = $rePrice === null ? 0 : (int)$rePrice;
          $depositVal = $deposit === null ? 0 : (int)$deposit;
          $mileageVal = $mileage === null ? 0 : (int)$mileage;
          // types: 3 strings, 7 ints (qty, price, supply, vat, re_price, deposit, mileage), 6 strings, 1 string (ordernm)
          $types = str_repeat('s', 3) . str_repeat('i', 7) . str_repeat('s', 6) . 's';
          $stmt->bind_param($types, $cust, $prodCd, $prodDes, $qty, $priceVal, $supplyAmt, $vatAmt, $rePriceVal, $depositVal, $mileageVal, $ch, $memo1, $memo2, $memo3, $memo4, $orderno, $ordernm);
          $stmt->execute();
          $stmt->close();
          redirect_self(['ok' => 'insert']);
        }
      }

      if ($action === 'update') {
        $sno = post_int('sno');
        $cust = post_str('cust');
        $prodCd = post_str('prod_cd');
        $prodDes = post_str('prod_des');
        $qty = post_int('qty');
        $price = post_int('price');
        $rePrice = post_int('re_price');
        $deposit = post_int('deposit');
        $mileage = post_int('mileage');
        $ch = post_str('ch');
        $memo1 = post_str('U_MEMO1');
        $memo2 = post_str('U_MEMO2');
        $memo3 = post_str('U_MEMO3');
        $memo4 = post_str('U_MEMO4');
        $orderno = post_str('orderno');
        $ordernm = post_str('ordernm');

        if ($sno === null || $cust === '' || $prodCd === '' || $qty === null ) {
          $formError = '필수값이 비어있거나 숫자 형식이 올바르지 않습니다.';
        } else {
          // RE_PRICE(총액)가 제공되면 이를 우선으로 공급가/부가세를 계산합니다.
          if ($rePrice !== null && $rePrice !== 0) {
            // RE_PRICE는 단가(총액 = 공급가+VAT)로 들어온다고 가정합니다.
            $unitSupply = (int)round($rePrice / 1.1);
            $unitVat = (int)($rePrice - $unitSupply);
            $price = $unitSupply;
            $qtyVal = ($qty === null) ? 1 : (int)$qty;
            if ($qtyVal < 1) $qtyVal = 1;
            $supplyAmt = $unitSupply * $qtyVal;
            $vatAmt = $unitVat * $qtyVal;
          } elseif ($price === null) {
            $supplyAmt = 0;
            $vatAmt = 0;
          } else {
            $supplyAmt = (int)$price;
            $vatAmt = (int)round($price * 0.1);
          }

          $stmt = $mysqli->prepare(
            "UPDATE `{$tableName}` SET `CUST` = ?, `PROD_CD` = ?, `PROD_DES` = ?, `QTY` = ?, `PRICE` = ?, `SUPPLY_AMT` = ?, `VAT_AMT` = ?, `RE_PRICE` = ?, `DEPOSIT` = ?, `MILEAGE` = ?, `ch` = ?, `U_MEMO1` = ?, `U_MEMO2` = ?, `U_MEMO3` = ?, `U_MEMO4` = ?, `orderno` = ?, `ordernm` = ? WHERE `sno` = ?"
          );
          $priceVal = $price === null ? 0 : (int)$price;
          $rePriceVal = $rePrice === null ? 0 : (int)$rePrice;
          $depositVal = $deposit === null ? 0 : (int)$deposit;
          $mileageVal = $mileage === null ? 0 : (int)$mileage;
          // types: 3 strings, 7 ints, 7 strings, 1 int (sno)
          $types = str_repeat('s', 3) . str_repeat('i', 7) . str_repeat('s', 7) . 'i';
          $stmt->bind_param($types, $cust, $prodCd, $prodDes, $qty, $priceVal, $supplyAmt, $vatAmt, $rePriceVal, $depositVal, $mileageVal, $ch, $memo1, $memo2, $memo3, $memo4, $orderno, $ordernm, $sno);
          $stmt->execute();
          $stmt->close();
          redirect_self(['ok' => 'update']);
        }
      }
    }
  } catch (Throwable $e) {
    $formError = $formError !== '' ? $formError : '요청 처리 중 오류가 발생했습니다.';
    $listError = $e->getMessage();
  }

  try {
    $result = $mysqli->query(
      "SELECT `sno`, `CUST` AS cust, `PROD_CD` AS prod_cd, `PROD_DES` AS prod_des, `QTY` AS qty, `PRICE` AS price, `SUPPLY_AMT` AS supply_amt, `VAT_AMT` AS vat_amt, `RE_PRICE` AS re_price, `DEPOSIT` AS deposit, `MILEAGE` AS mileage, `ch` AS ch, `U_MEMO1`, `U_MEMO2`, `U_MEMO3`, `U_MEMO4`, `orderno` AS orderno, `ordernm` AS ordernm FROM `{$tableName}` ORDER BY `sno` DESC"
    );
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
  } catch (Throwable $e) {
    $listError = $e->getMessage();
  }
}

// orderno(주문번호) 기준으로 품목 배열을 만들어 ERP API(SaveSaleOrder)로 보낼 수 있게 준비
$itemsByOrder = [];
  $orderCust = [];
  foreach ($rows as $rr) {
  $key = (string)($rr['orderno'] ?? '');
  if ($key === '') {
    $key = 'sno_' . (string)($rr['sno'] ?? '');
  }
  // record original cust for this order key (first occurrence)
  if (!array_key_exists($key, $itemsByOrder)) {
    $orderCust[$key] = (string)($rr['cust'] ?? '');
    $itemsByOrder[$key] = [];
  }

  $itemsByOrder[$key][] = [
    'PROD_CD' => (string)($rr['prod_cd'] ?? ''),
    'PROD_DES' => (string)($rr['prod_des'] ?? ''),
    'QTY' => (string)($rr['qty'] ?? ''),
    'PRICE' => (string)($rr['price'] ?? ''),
    'SUPPLY_AMT' => (string)($rr['supply_amt'] ?? ''),
    'VAT_AMT' => (string)($rr['vat_amt'] ?? ''),
    'RE_PRICE' => (string)($rr['re_price'] ?? ''),
    'DEPOSIT' => (string)($rr['deposit'] ?? ''),
    'MILEAGE' => (string)($rr['mileage'] ?? ''),
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>erp test </title>
</head>
<body>
  <style>
    body { font-family: Arial, sans-serif; margin: 16px; }
    table { border-collapse: separate; border-spacing: 0 10px; width:100%; }
    th { background: #f3f3f3; }
    input[type="text"], input[type="number"] { width: 140px; }
    .msg { margin: 8px 0; padding: 8px; background: #eef7ff; border: 1px solid #cfe8ff; }
    .err { margin: 8px 0; padding: 8px; background: #fff0f0; border: 1px solid #ffd0d0; color: #900; }
    .section { margin: 20px 0; }
    .row-actions { display: flex; gap: 6px; align-items: center; }
    /* 그룹별 색상 (순환) */
    tbody.order-group.group-0 tr td { background: #fbfdff; }
    tbody.order-group.group-1 tr td { background: #f7fff7; }
    tbody.order-group.group-2 tr td { background: #fff7f7; }
    tbody.order-group.group-3 tr td { background: #fffdfa; }
    tbody.order-group.group-4 tr td { background: #f7f7ff; }
    tbody.order-group.group-5 tr td { background: #f7fffb; }
    tbody.order-group tr td { border: 1px solid #e0e0e0; }
    /* 그룹간 여백은 table의 border-spacing으로 처리됨 */
    tbody.order-group tr td input { background: transparent; border: none; width:100%; box-sizing:border-box; }
  </style>
  

  <h1>ㅁERP Test server (erp_testTable)</h1>

  <?php if ($message !== ''): ?>
    <div class="msg"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="err"><?= h($formError) ?></div>
  <?php endif; ?>

  <h2>전송하자</h2>
  <div style="
    max-width: 1400px;
    margin: 0 auto;
">
  <form class="section" id="db-insert" method="post" action="">
  <div style="display: grid;grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;gap: 1rem;">
    <label>No <input type="text" name="action" value="insert"></label>
    <label>CUST <input type="text" name="cust" required></label>
    <label>PROD_CD <input type="text" name="prod_cd" required></label>
    <label>PROD_DES <input type="text" name="prod_des"></label>
    <label>QTY <input type="number" name="qty" required></label>
    <label>PRICE <input type="number" name="price"></label>
    <label>SUPPLY_AMT <input type="number" name="supply_amt"></label>
    <label>VAT_AMT <input type="number" name="vat_amt"></label>
    <label>re_price <input type="number" name="re_price"></label>
    <label>deposit <input type="number" name="deposit"></label>
    <label>mileage <input type="number" name="mileage"></label>
    <label>ch <input type="text" name="ch"></label>
    <label>배송메시지(U_MEMO1) <input type="text" name="U_MEMO1"></label>
    <label>받는사람(U_MEMO2) <input type="text" name="U_MEMO2"></label>
    <label>휴대폰번호(U_MEMO3) <input type="text" name="U_MEMO3"></label>
    <label>주소(U_MEMO4) <input type="text" name="U_MEMO4"></label>
    <label>orderno <input type="text" name="orderno"></label>
    <label>ordernm <input type="text" name="ordernm"></label>
    <button type="submit">전송</button>
  </div>

  </form>
</div>



  <section id="db-list" class="section">
    <h2>b2b-db-list</h2>

    <?php if ($dbError !== ''): ?>
      <p>DB 연결/초기화 오류로 목록을 표시하지 못했습니다.</p>
      <p style="color: red;">오류: <?= h($dbError) ?></p>
      <?php if ($schemaWarning !== ''): ?>
        <p style="color: orange;">스키마 경고: <?= h($schemaWarning) ?></p>
      <?php endif; ?>
    <?php elseif ($listError !== ''): ?>
      <p>조회 중 오류가 있어 목록을 표시하지 못했습니다.</p>
      <p style="color: red;">조회 오류: <?= h($listError) ?></p>
    <?php elseif (count($rows) === 0): ?>
      <p>저장된 데이터가 없습니다.</p>
    <?php else: ?>
      <table border="1" cellpadding="6" cellspacing="0">
        <thead>
          <tr>
            <th>sno</th>
            <th>CUST</th>
            <th>PROD_CD</th>
            <th>PROD_DES</th>
            <th>QTY</th>
            <th>PRICE</th>
            <th>SUPPLY_AMT</th>
            <th>VAT_AMT</th>
            <th>RE_PRICE</th>
            <th>DEPOSIT</th>
            <th>MILEAGE</th>
            <th>ch</th>
            <th>U_MEMO1</th>
            <th>U_MEMO2</th>
            <th>U_MEMO3</th>
            <th>U_MEMO4</th>
            <th>orderno</th>
            <th>ordernm</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $prevOrder = null;
            $groupIndex = 0;
            foreach ($rows as $r):
              $formId = 'u' . (string)($r['sno'] ?? '');
              $orderKey = (string)($r['orderno'] ?? '');
              if ($orderKey === '') {
                $orderKey = 'sno_' . (string)($r['sno'] ?? '');
              }
              // 그룹이 바뀌면 tbody 구분을 늘림
              if ($prevOrder === null || $prevOrder !== $orderKey) {
                if ($prevOrder !== null) {
                  // 닫는 태그 출력
                  echo "</tbody>\n";
                  $groupIndex++;
                }
                echo '<tbody class="order-group group-' . ((int)$groupIndex % 6) . '" data-order="' . h($orderKey) . '">';
                // 그룹 항목 JSON 및 대표 고객 정보 준비
                $itemsJson = json_encode($itemsByOrder[$orderKey] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($itemsJson)) $itemsJson = '[]';
                $groupCust = $orderCust[$orderKey] ?? '';

                echo '<tr class="order-header"><td colspan="20" style="padding:8px 12px;">';
                echo '<form method="post" action="index.php" class="group-api-form" style="display:flex;gap:8px;align-items:center;margin:0;">';
                echo '<h4 style="margin:0 12px 0 0">주문번호: ' . h($orderKey) . '</h4>';
                echo '<input type="hidden" name="action" value="saleorder">';
                echo '<input type="hidden" name="env" value="' . h($ecountEnv) . '">';
                echo '<input type="hidden" name="com_code" value="' . h($ecountComCode) . '">';
                echo '<input type="hidden" name="upload_ser_no" value="' . h($ecountUploadSerNo) . '">';
                echo '<input type="hidden" name="session_id" value="">';
                echo '<input type="hidden" name="user_id" value="' . h($ecountUserId) . '">';
                echo '<input type="hidden" name="api_cert_key" value="' . h($ecountApiCertKey) . '">';
                echo '<input type="hidden" name="lan_type" value="' . h($ecountLanType) . '">';
                echo '<input type="hidden" name="login_path" value="' . h($ecountLoginPath) . '">';
                echo '<input type="hidden" name="cust" value="' . h($groupCust) . '">';
                echo '<input type="hidden" name="wh_cd" value="' . h($ecountWhCd) . '">';
                echo '<input type="hidden" name="saleorder_items_json" value="' . h($itemsJson) . '">';
                echo '<input type="hidden" name="emp_cd" value="99">';
                echo '<button type="submit" ' . ($ecountWhCd === '' ? 'disabled title="WH_CD(창고코드) 고정값을 설정해 주세요."' : '') . '>API 전송</button>';
                // 그룹 단위 변경 버튼 (JS 처리)
                echo '<button type="button" class="group-update-btn" data-order="' . h($orderKey) . '" style="margin-left:8px;">변경</button>';
                echo '</form>';
                echo '</td></tr>';
                $prevOrder = $orderKey;
                // 그룹 내 중복 체크 초기화
                $seenSignatures = [];
              }
          ?>
            <?php
              // 중복 행 체크: prod_cd|prod_des|qty|price|deposit
              $sigParts = [
                (string)($r['prod_cd'] ?? ''),
                (string)($r['prod_des'] ?? ''),
                (string)($r['qty'] ?? ''),
                (string)($r['price'] ?? ''),
                (string)($r['deposit'] ?? ''),
              ];
              $sig = md5(implode('|', $sigParts));
              if (isset($seenSignatures[$sig])) {
                // 이미 표시한 항목이면 건너뜁니다.
                continue;
              }
              $seenSignatures[$sig] = true;
            ?>
            <tr>
              <td><?= h((string)($r['sno'] ?? '')) ?></td>
              <td>
                <input type="text" name="cust" value="<?= h((string)($r['cust'] ?? '')) ?>" form="<?= h($formId) ?>" required>
              </td>
              <td>
                <input type="text" name="prod_cd" value="<?= h((string)($r['prod_cd'] ?? '')) ?>" form="<?= h($formId) ?>" required>
              </td>
              <td>
                <input type="text" name="prod_des" value="<?= h((string)($r['prod_des'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="qty" value="<?= h((string)($r['qty'] ?? '')) ?>" form="<?= h($formId) ?>" required>
              </td>
              <td>
                <input type="text" name="price" value="<?= h((string)($r['price'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="SUPPLY_AMT" value="<?= h((string)($r['supply_amt'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="VAT_AMT" value="<?= h((string)($r['vat_amt'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="re_price" value="<?= h((string)($r['re_price'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="deposit" value="<?= h((string)($r['deposit'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="mileage" value="<?= h((string)($r['mileage'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="ch" value="<?= h((string)($r['ch'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="U_MEMO1" value="<?= h((string)($r['U_MEMO1'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="U_MEMO2" value="<?= h((string)($r['U_MEMO2'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="U_MEMO3" value="<?= h((string)($r['U_MEMO3'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <input type="text" name="U_MEMO4" value="<?= h((string)($r['U_MEMO4'] ?? '')) ?>" form="<?= h($formId) ?>">
              </td>
              <td>
                <div class="row-actions">
                  <input type="text" name="orderno" value="<?= h((string)($r['orderno'] ?? '')) ?>" form="<?= h($formId) ?>">
                </div>
              </td>
              <td>
                <div class="row-actions">
                  <input type="text" name="ordernm" value="<?= h((string)($r['ordernm'] ?? '')) ?>" form="<?= h($formId) ?>">
                </div>
              </td>
            </tr>
          <?php
              // 루프 끝날 때 현재 tbody 닫기
            endforeach;
            if ($prevOrder !== null) echo "</tbody>\n";
          ?>
      </table>
    <?php endif; ?>
  </section>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // 기존 AJAX 로그인 동작과 병행하여, API 전송 폼 제출 시 price 기반으로 공급가/부가세 계산 후 전송
  document.querySelectorAll('form[action="index.php"]').forEach(function(f){
    var saleAction = f.querySelector('input[name="action"][value="saleorder"]');
    if (!saleAction) return;
    f.addEventListener('submit', function(e){
      e.preventDefault();
      // 기본 동작 차단: 페이지 이동 없이 데이터만 전송합니다.
      try {
        // 같은 행(tr) 안에 있는 편집용 price 입력을 찾음
        var tr = f.closest('tr');
        if (!tr) return;
        // visible price 찾기: 같은 이름의 input이 여러개일 수 있으므로
        var visiblePrice = null;
        tr.querySelectorAll('input[name="price"]').forEach(function(p){
          if (visiblePrice) return;
          if (p.closest('form') !== f) visiblePrice = p;
        });
        if (!visiblePrice) visiblePrice = tr.querySelector('input[name="price"]');
        // visiblePrice가 없으면 가격 계산은 건너뛰고 바로 전송 처리로 이동
        var canCalc = true;
        if (!visiblePrice) {
          canCalc = false;
        }
        var raw = (visiblePrice.value || '').toString().trim();
        if (raw === '') return;
        var num = parseFloat(raw.replace(/,/g, ''));
        if (isNaN(num)) return;

        // 기본: 총액 -> 공급가/부가세 계산 (입력값이 총액일 때)
        var supply = Math.round(num / 1.1);
        var vat = Math.round(num - supply);

        // re_price 필드가 있으면 단가 기준으로 계산하고 수량을 곱해서 합계를 만듭니다.
        var visibleRe = tr.querySelector('input[name="re_price"]');
        var qtyVisible = tr.querySelector('input[name="qty"]');
        var qtyVal = 1;
        if (qtyVisible) {
          var qn = parseInt((qtyVisible.value || '').toString().replace(/,/g, ''), 10);
          if (!isNaN(qn) && qn > 0) qtyVal = qn;
        }
        if (canCalc && visibleRe && (visibleRe.value || '').toString().trim() !== '') {
          var reNum = parseFloat((visibleRe.value || '').toString().replace(/,/g, ''));
          if (!isNaN(reNum)) {
            var unitPrice = Math.round(reNum / 1.1); // 단가(공급가)
            var unitVat = Math.round(reNum - unitPrice); // 단위 VAT
            var calcPrice = unitPrice;
            var totalSupply = unitPrice * qtyVal;
            var totalVat = unitVat * qtyVal;
            visiblePrice.value = String(calcPrice);
            supply = totalSupply;
            vat = totalVat;
            // hidden 전송 필드는 단가와 총합을 구분하여 채움
            var sfPrice = f.querySelector('input[name="price"]');
            var sfSupply = f.querySelector('input[name="SUPPLY_AMT"]');
            var sfVat = f.querySelector('input[name="VAT_AMT"]');
            if (sfPrice) sfPrice.value = String(calcPrice);
            if (sfSupply) sfSupply.value = String(totalSupply);
            if (sfVat) sfVat.value = String(totalVat);
          }
        } else if (canCalc) {
          visiblePrice.value = String(supply);
          var sfPrice = f.querySelector('input[name="price"]');
          var sfSupply = f.querySelector('input[name="SUPPLY_AMT"]');
          var sfVat = f.querySelector('input[name="VAT_AMT"]');
          if (sfPrice) sfPrice.value = String(supply);
          if (sfSupply) sfSupply.value = String(supply);
          if (sfVat) sfVat.value = String(vat);
        }

        var visibleSupply = tr.querySelector('input[name="SUPPLY_AMT"]');
        var visibleVat = tr.querySelector('input[name="VAT_AMT"]');
        if (canCalc) {
          if (visibleSupply) visibleSupply.value = String(supply);
          if (visibleVat) visibleVat.value = String(vat);
        }
        // prod_cd, qty도 함께 채움
        var prodCdField = f.querySelector('input[name="prod_cd"]');
        var qtyField = f.querySelector('input[name="qty"]');
        var visibleProd = tr.querySelector('input[name="prod_cd"]');
        var visibleQty = tr.querySelector('input[name="qty"]');
        if (prodCdField && visibleProd) prodCdField.value = String(visibleProd.value || '');
        if (qtyField && visibleQty) qtyField.value = String(visibleQty.value || '');
      } catch (err) {
        console.error('calc error', err);
      }

      // 비동기로 폼 전송 (페이지 이동 없음)
      try {
        var submitBtn = f.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        var formData = new FormData(f);
        var actionUrl = f.getAttribute('action') || window.location.href;
        fetch(actionUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
          .then(function(res){ return res.text(); })
          .then(function(text){
            console.log('submit response', text);
            var exist = document.querySelector('.msg.ajax-submit');
            if (!exist) {
              var d = document.createElement('div');
              d.className = 'msg ajax-submit';
              d.textContent = '전송 완료 (페이지 유지)';
              document.body.insertBefore(d, document.body.firstChild);
            } else {
              exist.textContent = '전송 완료 (페이지 유지)';
            }
          })
          .catch(function(err){
            console.error('submit failed', err);
            var existErr = document.querySelector('.err.ajax-submit');
            if (!existErr) {
              var eD = document.createElement('div');
              eD.className = 'err ajax-submit';
              eD.textContent = '전송 실패: ' + (err && err.message ? err.message : '네트워크 오류');
              document.body.insertBefore(eD, document.body.firstChild);
            } else {
              existErr.textContent = '전송 실패: ' + (err && err.message ? err.message : '네트워크 오류');
            }
          })
          .finally(function(){ if (submitBtn) submitBtn.disabled = false; });
      } catch (e) { console.error('ajax submit err', e); if (submitBtn) submitBtn.disabled = false; }
    }, false);
  });
  // 그룹 변경 버튼 처리: 해당 tbody의 모든 행을 모아 각각 action=update로 POST
  document.querySelectorAll('.group-update-btn').forEach(function(btn){
    btn.addEventListener('click', async function(e){
      var order = btn.getAttribute('data-order');
      var tbody = btn.closest('tbody.order-group');
      if (!tbody) return;
      btn.disabled = true;
      var trs = Array.from(tbody.querySelectorAll('tr')).filter(function(tr){ return !tr.classList.contains('order-header'); });
      var results = [];
      for (var tr of trs) {
        try {
          // sno는 첫번째 td 텍스트에서 가져옴
          var snoCell = tr.querySelector('td');
          if (!snoCell) continue;
          var sno = (snoCell.textContent || '').toString().trim();
          if (!sno) continue;
          var fd = new FormData();
          fd.append('action', 'update');
          fd.append('sno', sno);
          // 모든 입력 요소(name이 있는) 값을 추가
          tr.querySelectorAll('input, select, textarea').forEach(function(inp){
            var name = inp.getAttribute('name');
            if (!name) return;
            // skip inputs that belong to header forms (none expected here)
            fd.append(name, inp.value || '');
          });
          var res = await fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' });
          var text = await res.text();
          results.push({ sno: sno, ok: res.ok, status: res.status });
        } catch (err) {
          results.push({ sno: sno || null, ok: false, error: err.message });
        }
      }
      // 결과 요약 표시
      var successCount = results.filter(r=>r.ok).length;
      var failCount = results.length - successCount;
      var msg = '변경 전송 완료: ' + successCount + '건, 실패: ' + failCount + '건';
      var exist = document.querySelector('.msg.group-update');
      if (!exist) {
        var d = document.createElement('div');
        d.className = 'msg group-update';
        d.textContent = msg;
        document.body.insertBefore(d, document.body.firstChild);
      } else {
        exist.textContent = msg;
      }
      btn.disabled = false;
    }, false);
  });
});
// 편집 화면에서 PRICE를 직접 수정할 때 즉시 SUPPLY_AMT/VAT_AMT를 계산하여
// 같은 행의 보이는 입력값과 관련된 전송 폼(hidden 필드)에 동기화합니다.
(function(){
  function calcAndFill(priceInput){
    try{
      var tr = priceInput.closest('tr');
      if (!tr) return;
      var raw = (priceInput.value || '').toString().trim();
      if (raw === '') return;
      var num = parseFloat(raw.replace(/,/g, ''));
      if (isNaN(num)) return;
      // 입력값을 총액으로 받는다고 가정하면 공급가는 1.1로 나눠서 계산
      var supply = Math.round(num / 1.1);
      var vat = Math.round(num - supply);

      // re_price 우선 처리: re_price는 단가(총액)로 간주하고 단가->단가공급가 계산, 총합은 qty 곱
      var visibleRe = tr.querySelector('input[name="re_price"]');
      var qtyVisible = tr.querySelector('input[name="qty"]');
      var qtyVal = 1;
      if (qtyVisible) {
        var qn = parseInt((qtyVisible.value || '').toString().replace(/,/g, ''), 10);
        if (!isNaN(qn) && qn > 0) qtyVal = qn;
      }
      if (visibleRe && (visibleRe.value || '').toString().trim() !== '') {
        var reNum = parseFloat((visibleRe.value || '').toString().replace(/,/g, ''));
        if (!isNaN(reNum)) {
          var unitPrice = Math.round(reNum / 1.1);
          var unitVat = Math.round(reNum - unitPrice);
          var calcPrice = unitPrice;
          var totalSupply = unitPrice * qtyVal;
          var totalVat = unitVat * qtyVal;
          if (priceInput) priceInput.value = String(calcPrice);
          supply = totalSupply;
          vat = totalVat;
          var saleForm = tr.querySelector('form[action="index.php"]');
          if (saleForm) {
            var sfPrice = saleForm.querySelector('input[name="price"]');
            var sfSupply = saleForm.querySelector('input[name="SUPPLY_AMT"]');
            var sfVat = saleForm.querySelector('input[name="VAT_AMT"]');
            if (sfPrice) sfPrice.value = String(calcPrice);
            if (sfSupply) sfSupply.value = String(totalSupply);
            if (sfVat) sfVat.value = String(totalVat);
          }
        }
      } else {
        if (priceInput) priceInput.value = String(supply);
        var saleForm = tr.querySelector('form[action="index.php"]');
        if (saleForm) {
          var sfPrice = saleForm.querySelector('input[name="price"]');
          var sfSupply = saleForm.querySelector('input[name="SUPPLY_AMT"]');
          var sfVat = saleForm.querySelector('input[name="VAT_AMT"]');
          if (sfPrice) sfPrice.value = String(supply);
          if (sfSupply) sfSupply.value = String(supply);
          if (sfVat) sfVat.value = String(vat);
        }
      }

      // 보이는 입력값(테이블)에 반영
      var visibleSupply = tr.querySelector('input[name="SUPPLY_AMT"]');
      var visibleVat = tr.querySelector('input[name="VAT_AMT"]');
      if (visibleSupply) visibleSupply.value = String(supply);
      if (visibleVat) visibleVat.value = String(vat);
    } catch (e) {
      console.error('calcAndFill error', e);
    }
  }

  // 테이블의 편집용 PRICE 입력(폼 속성 유무 상관없이)을 대상으로 이벤트 연결
  document.querySelectorAll('input[name="price"]').forEach(function(inp){
    // 사용자가 입력을 끝냈을 때와 실시간 입력 모두 반응하도록 두 이벤트를 연결
    inp.addEventListener('input', function(){ calcAndFill(inp); }, false);
    inp.addEventListener('change', function(){ calcAndFill(inp); }, false);
  });
  // 페이지 로드 시 이미 값이 있는 PRICE 입력에 대해 계산을 한 번 수행
  document.querySelectorAll('input[name="price"]').forEach(function(inp){
    try {
      if ((inp.value || '').toString().trim() !== '') {
        calcAndFill(inp);
      }
    } catch (e) { /* ignore */ }
  });
  // re_price 입력 변화가 있을 때 PRICE/SUPPLY/VAT를 동기화하도록 이벤트 연결
  document.querySelectorAll('input[name="re_price"]').forEach(function(rp){
    rp.addEventListener('input', function(){
      try{
        var tr = rp.closest('tr');
        if (!tr) return;
        var reRaw = (rp.value || '').toString().trim();
        if (reRaw === '') return;
        var reNum = parseFloat(reRaw.replace(/,/g, ''));
        if (isNaN(reNum)) return;
        var qtyVisible = tr.querySelector('input[name="qty"]');
        var qtyVal = 1;
        if (qtyVisible) {
          var qn = parseInt((qtyVisible.value || '').toString().replace(/,/g, ''), 10);
          if (!isNaN(qn) && qn > 0) qtyVal = qn;
        }
        var unitPrice = Math.round(reNum / 1.1);
        var unitVat = Math.round(reNum - unitPrice);
        var totalSupply = unitPrice * qtyVal;
        var totalVat = unitVat * qtyVal;
        var priceInput = tr.querySelector('input[name="price"]');
        var visibleSupply = tr.querySelector('input[name="SUPPLY_AMT"]');
        var visibleVat = tr.querySelector('input[name="VAT_AMT"]');
        if (priceInput) priceInput.value = String(unitPrice);
        if (visibleSupply) visibleSupply.value = String(totalSupply);
        if (visibleVat) visibleVat.value = String(totalVat);
        var saleForm = tr.querySelector('form[action="index.php"]');
        if (saleForm) {
          var sfPrice = saleForm.querySelector('input[name="price"]');
          var sfSupply = saleForm.querySelector('input[name="SUPPLY_AMT"]');
          var sfVat = saleForm.querySelector('input[name="VAT_AMT"]');
          if (sfPrice) sfPrice.value = String(unitPrice);
          if (sfSupply) sfSupply.value = String(totalSupply);
          if (sfVat) sfVat.value = String(totalVat);
        }
      }catch(e){console.error(e)}
    }, false);
  });
  // 자동 전송 기능을 제거했습니다.
  // (자동 제출/자동 전송 관련 코드 삭제)
})();
</script>
</body>
</html>
