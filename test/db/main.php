<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['phpinfo']) && $_GET['phpinfo'] === '1') {
    phpinfo();
    exit;
}

$messages = [];
$errors = [];

// .env 지원 (vlucas/phpdotenv)
$projectRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
$autoloadPath = $projectRoot . '/vendor/autoload.php';
$envPath = $projectRoot . '/.env';

try {
  if (is_file($autoloadPath)) {
    require_once $autoloadPath;
    $dotenvClass = 'Dotenv\\Dotenv';
    if (class_exists($dotenvClass)) {
      $dotenvClass::createImmutable($projectRoot)->safeLoad();
    }
  } elseif (is_file($envPath)) {
    $errors[] = '.env 파일은 존재하지만 vendor/autoload.php가 없어 로드할 수 없습니다. (composer install 필요)';
  }
} catch (Throwable $e) {
  $errors[] = '.env 로드 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 환경변수: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_TABLE
$dbHost = getenv('DB_HOST') ?: 'svc.sel4.cloudtype.app';
$dbPort = getenv('DB_PORT') ?: '31446';
$dbName = getenv('DB_NAME') ?: 'palace0261';
$dbUser = getenv('DB_USER') ?: 'palace0261';
$dbPass = getenv('DB_PASS');
$dbPass = ($dbPass === false) ? '' : (string)$dbPass;
$dbTable = getenv('DB_TABLE') ?: 'es_testTable';

if ($dbPass === '') {
    $errors[] = 'DB_PASS 환경변수가 설정되어 있지 않습니다. (예: DB_PASS)';
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbTable)) {
    $errors[] = 'DB_TABLE 값이 안전하지 않습니다. 영문/숫자/언더스코어(_)만 허용됩니다.';
}

$pdo = null;
$tableColumns = [];

if (empty($errors)) {
  try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 테이블 컬럼 목록 조회 (스키마 기반 자동 감지)
    $colStmt = $pdo->prepare(
      'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl'
    );
    $colStmt->execute([':db' => $dbName, ':tbl' => $dbTable]);
    $tableColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
  } catch (Throwable $e) {
    $errors[] = 'DB 연결 오류: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

$columnSet = array_fill_keys($tableColumns, true);

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'sno' => trim((string)($_POST['sno'] ?? '')),
        'testNo' => trim((string)($_POST['testNo'] ?? '')),
        'testId' => trim((string)($_POST['testId'] ?? '')),
        'title' => trim((string)($_POST['title'] ?? '')),
        'detail' => trim((string)($_POST['detail'] ?? '')),
    ];

    $insertCols = [];
    $params = [];

    foreach ($payload as $col => $value) {
        if ($value === '') {
            continue;
        }
        if (!isset($columnSet[$col])) {
            continue;
        }
        $insertCols[] = $col;
        $params[":" . $col] = $value;
    }

    if (count($insertCols) === 0) {
      $errors[] = '저장할 값이 없습니다. (테이블 컬럼과 일치하는 입력값이 필요합니다.)';
    } else {
        try {
            $quotedCols = array_map(static fn($c) => "`{$c}`", $insertCols);
            $placeholders = array_map(static fn($c) => ":{$c}", $insertCols);
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $dbTable,
                implode(', ', $quotedCols),
                implode(', ', $placeholders)
            );
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $messages[] = '저장 완료 (INSERT 성공)';
        } catch (Throwable $e) {
            $errors[] = '저장 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
}

$recentRows = [];
if ($pdo && empty($errors) && !empty($dbTable)) {
    try {
        $orderBy = isset($columnSet['sno']) ? ' ORDER BY `sno` DESC' : '';
        $sql = sprintf('SELECT * FROM `%s`%s LIMIT 20', $dbTable, $orderBy);
        $recentRows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
      $errors[] = '조회 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database test</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    .box { padding: 10px 12px; border-radius: 8px; margin: 10px 0; }
    .box-error { border: 1px solid #d33; background: #fff5f5; color: #8a1f1f; }
    .box-ok { border: 1px solid #2e7d32; background: #f1fff2; color: #1b5e20; }
    form > div { margin: 8px 0; }
    label { display: inline-block; width: 64px; }
    input { padding: 6px 8px; min-width: 240px; }
    table { border-collapse: collapse; }
    th { background: #f6f6f6; }
  </style>
</head>
<body>

  <h3>Database test</h3>

  <?php if (!empty($messages)): ?>
    <div class="box box-ok">
      <?php foreach ($messages as $m): ?>
        <p><?php echo htmlspecialchars($m, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="box box-error">
      <?php foreach ($errors as $err): ?>
        <p><?php echo $err; ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div>
    <p>
      host=<?php echo htmlspecialchars($dbHost, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
      port=<?php echo htmlspecialchars((string)$dbPort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
      db=<?php echo htmlspecialchars($dbName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
      user=<?php echo htmlspecialchars($dbUser, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>,
      table=<?php echo htmlspecialchars($dbTable, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
    </p>

    <?php if (!empty($tableColumns)): ?>
      <p>테이블 컬럼: <?php echo htmlspecialchars(implode(', ', $tableColumns), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post">
      <div>
        <label for="sno">sno</label>
        <input type="text" id="sno" name="sno" value="<?php echo htmlspecialchars((string)($_POST['sno'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>
      <div>
        <label for="testNo">testNo</label>
        <input type="text" id="testNo" name="testNo" value="<?php echo htmlspecialchars((string)($_POST['testNo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>
      <div>
        <label for="testId">testId</label>
        <input type="text" id="testId" name="testId" value="<?php echo htmlspecialchars((string)($_POST['testId'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>
      <div>
        <label for="title">title</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars((string)($_POST['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>
      <div>
        <label for="detail">detail</label>
        <input type="text" id="detail" name="detail" value="<?php echo htmlspecialchars((string)($_POST['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
      </div>
      <div>
        <button type="submit">submit</button>
      </div>
    </form>
  </div>

  <?php if (!empty($recentRows)): ?>
    <h4>최근 20건</h4>
    <table border="1" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <?php foreach (array_keys($recentRows[0]) as $key): ?>
            <th><?php echo htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRows as $row): ?>
          <tr>
            <?php foreach ($row as $val): ?>
              <td><?php echo htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

</body>
</html>