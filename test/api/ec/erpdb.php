<?php
// 0326 2026 - test 테이블

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
    table { border-collapse: collapse; }
    th { background: #f3f3f3; }
    input[type="text"], input[type="number"] { width: 140px; }
    .msg { margin: 8px 0; padding: 8px; background: #eef7ff; border: 1px solid #cfe8ff; }
    .err { margin: 8px 0; padding: 8px; background: #fff0f0; border: 1px solid #ffd0d0; color: #900; }
    .section { margin: 20px 0; }
    .row-actions { display: flex; gap: 6px; align-items: center; }
  </style>
  

  <h1>ERP Test server (erp_testTable)</h1>

  <?php if ($message !== ''): ?>
    <div class="msg"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="err"><?= h($formError) ?></div>
  <?php endif; ?>

  <form class="section" id="db-insert" method="post" action="">
    <h2>전송하자</h2>
    <input type="hidden" name="action" value="insert">
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
  </form>




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
            <th>변경</th>
            <th>API 전송</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php $formId = 'u' . (string)($r['sno'] ?? ''); ?>
            <?php
              $orderKey = (string)($r['orderno'] ?? '');
              if ($orderKey === '') {
                $orderKey = 'sno_' . (string)($r['sno'] ?? '');
              }
              $itemsJson = json_encode($itemsByOrder[$orderKey] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
              if (!is_string($itemsJson)) {
                $itemsJson = '[]';
              }
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
              <td>
                <form id="<?= h($formId) ?>" method="post" action="" style="margin:0;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="sno" value="<?= h((string)($r['sno'] ?? '')) ?>">
                  <button type="submit">변경</button>
                </form>
              </td>
              <td>
                <form method="post" action="index.php" style="margin:0;">
                  <input type="hidden" name="action" value="saleorder">
                  <input type="hidden" name="env" value="<?= h($ecountEnv) ?>">
                  <input type="hidden" name="com_code" value="<?= h($ecountComCode) ?>">
                  <input type="hidden" name="upload_ser_no" value="<?= h($ecountUploadSerNo) ?>">
                  <input type="hidden" name="session_id" value="">
                  <input type="hidden" name="user_id" value="<?= h($ecountUserId) ?>">
                  <input type="hidden" name="api_cert_key" value="<?= h($ecountApiCertKey) ?>">
                  <input type="hidden" name="lan_type" value="<?= h($ecountLanType) ?>">
                  <input type="hidden" name="login_path" value="<?= h($ecountLoginPath) ?>">
                  <input type="hidden" name="cust" value="<?= h((string)($r['cust'] ?? '')) ?>">
                  <input type="hidden" name="wh_cd" value="<?= h($ecountWhCd) ?>">
                  <input type="hidden" name="prod_cd" value="">
                  <input type="hidden" name="qty" value="">
                  <input type="hidden" name="price" value="">
                  <input type="hidden" name="SUPPLY_AMT" value="">
                  <input type="hidden" name="VAT_AMT" value="">
                  <input type="hidden" name="re_price" value="<?= h((string)($r['re_price'] ?? '')) ?>">
                  <input type="hidden" name="deposit" value="<?= h((string)($r['deposit'] ?? '')) ?>">
                  <input type="hidden" name="mileage" value="<?= h((string)($r['mileage'] ?? '')) ?>">
                  <input type="hidden" name="u_memo1" value="<?= h((string)($r['U_MEMO1'] ?? '')) ?>">
                  <input type="hidden" name="u_memo2" value="<?= h((string)($r['U_MEMO2'] ?? '')) ?>">
                  <input type="hidden" name="u_memo3" value="<?= h((string)($r['U_MEMO3'] ?? '')) ?>">
                  <input type="hidden" name="u_memo4" value="<?= h((string)($r['U_MEMO4'] ?? '')) ?>">
                  <input type="hidden" name="saleorder_items_json" value="<?= h($itemsJson) ?>">
                  <input type="hidden" name="emp_cd" value="99">
                  <button type="submit" <?= $ecountWhCd === '' ? 'disabled title="WH_CD(창고코드) 고정값을 설정해 주세요."' : '' ?>>API 전송</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
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
        if (!visiblePrice) return;
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
        if (visibleRe && (visibleRe.value || '').toString().trim() !== '') {
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
        } else {
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
        if (visibleSupply) visibleSupply.value = String(supply);
        if (visibleVat) visibleVat.value = String(vat);
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

      // 폼을 그대로 제출합니다. (서버는 saleorder 요청 내에서 login 자격이 있으면 자동으로 로그인 처리합니다.)
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

  // 자동 전송: 행(row) 내 입력값 변경 시 해당 행의 saleorder 폼을 자동 제출
  // 단, orderno 값이 같은 행들은 그룹으로 묶어 최초 1회만 전송합니다.
  (function(){
    var sentOrders = new Set();
    var pending = Object.create(null);

    function orderKeyForRow(tr){
      var ord = (tr.querySelector('input[name="orderno"]') || {value: ''}).value || '';
      if (ord.toString().trim() !== '') return 'ord_' + ord.toString().trim();
      var sno = (tr.querySelector('input[name="sno"]') || {value: ''}).value || '';
      return 'sno_' + sno.toString().trim();
    }

    function scheduleRowSubmit(tr){
      var key = orderKeyForRow(tr);
      if (sentOrders.has(key)) return; // 이미 전송된 order
      // debounce: 같은 order에 대해 여러 이벤트가 오면 하나로 묶음
      if (pending[key]) clearTimeout(pending[key]);
      pending[key] = setTimeout(function(){
        delete pending[key];
        // find first row matching this key (orderno) to submit that form
        var selector;
        if (key.indexOf('ord_') === 0) {
          var ord = key.substring(4);
          selector = '#db-list tbody tr';
          var rows = document.querySelectorAll(selector);
          for (var i=0;i<rows.length;i++){
            var r = rows[i];
            var ordf = (r.querySelector('input[name="orderno"]') || {value: ''}).value || '';
            if (ordf.toString().trim() === ord) { tr = r; break; }
          }
        }
        // ensure hidden saleorder fields are up-to-date
        try{
          var priceInp = tr.querySelector('input[name="price"]');
          var reInp = tr.querySelector('input[name="re_price"]');
          if (reInp) reInp.dispatchEvent(new Event('input', {bubbles:true}));
          if (priceInp) priceInp.dispatchEvent(new Event('change', {bubbles:true}));
        } catch(e) { /* ignore */ }

        var form = tr.querySelector('form[action="index.php"]');
        if (form) {
          try{
            // mark as sent to avoid duplicates
            sentOrders.add(key);
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
          } catch(e){ console.error('auto submit failed', e); }
        }
      }, 300);
    }

    // listen for changes on relevant inputs in each row
    function attachRowListeners(tr){
      var sels = ['input[name="price"]','input[name="re_price"]','input[name="qty"]','input[name="orderno"]','input[name="prod_cd"]'];
      sels.forEach(function(sel){
        tr.querySelectorAll(sel).forEach(function(inp){
          var handler = function(){
            try{
              var v = (inp.value || '').toString().trim();
              if (v === '') return; // only trigger when a value is added (non-empty)
              scheduleRowSubmit(tr);
            }catch(e){ /* ignore */ }
          };
          inp.addEventListener('input', handler, false);
          inp.addEventListener('change', handler, false);
        });
      });
    }

    document.querySelectorAll('#db-list tbody tr').forEach(function(tr){ attachRowListeners(tr); });

    // Observe new rows being added and attach listeners; submit only if new row has filled inputs
    var tbody = document.querySelector('#db-list tbody');
    if (tbody) {
      var mo = new MutationObserver(function(mutations){
        mutations.forEach(function(m){
          m.addedNodes.forEach(function(node){
            if (!node || node.nodeType !== 1) return;
            if (node.tagName.toLowerCase() !== 'tr') return;
            attachRowListeners(node);
            var shouldSubmit = false;
            ['input[name="price"]','input[name="re_price"]','input[name="qty"]','input[name="orderno"]','input[name="prod_cd"]'].forEach(function(sel){
              node.querySelectorAll(sel).forEach(function(inp){
                if (shouldSubmit) return;
                var v = (inp.value || '').toString().trim();
                if (v !== '') shouldSubmit = true;
              });
            });
            if (shouldSubmit) scheduleRowSubmit(node);
          });
        });
      });
      mo.observe(tbody, { childList: true });
    }
  })();
})();
</script>
</body>
</html>