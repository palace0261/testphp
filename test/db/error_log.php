<?php
declare(strict_types=1);

/**
 * 에러 로그를 파일(JSON Lines)로 누적 저장합니다.
 * - 직접 호출(POST) 시: JSON 응답
 * - include/require 시: 함수만 제공(출력/exit 없음)
 */

function append_error_log(array $payload, ?string $logFilePath = null): bool
{
	$logFilePath = $logFilePath ?? (__DIR__ . DIRECTORY_SEPARATOR . 'error_log.jsonl');

	$nowIso = gmdate('c');
	$level = (string)($payload['level'] ?? 'error');
	$message = (string)($payload['message'] ?? '');
	$page = (string)($payload['page'] ?? '');
	$stage = (string)($payload['stage'] ?? '');
	$context = $payload['context'] ?? null;

	if ($message === '') {
		$message = 'unknown error';
	}

	$maxLen = 8000;
	if (strlen($message) > $maxLen) {
		$message = substr($message, 0, $maxLen) . '…';
	}

	$entry = [
		'ts' => $nowIso,
		'level' => $level,
		'message' => $message,
		'page' => $page,
		'stage' => $stage,
		'context' => $context,
		'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
		'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
	];

	$json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		return false;
	}

	$line = $json . "\n";
	if (strlen($line) > 20000) {
		return false;
	}

	$dir = dirname($logFilePath);
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}

	$bytes = @file_put_contents($logFilePath, $line, FILE_APPEND | LOCK_EX);
	return $bytes !== false;
}

function _error_log_read_request_payload(): array
{
	$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');

	if (stripos($contentType, 'application/json') !== false) {
		$raw = file_get_contents('php://input');
		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
	}

	if (!empty($_POST) && is_array($_POST)) {
		$payload = $_POST;
		if (isset($payload['context']) && is_string($payload['context'])) {
			$maybe = json_decode($payload['context'], true);
			if (is_array($maybe)) {
				$payload['context'] = $maybe;
			}
		}
		return $payload;
	}

	$raw = file_get_contents('php://input');
	if (is_string($raw) && $raw !== '') {
		return ['message' => $raw];
	}

	return [];
}

function _error_log_send_json(int $statusCode, array $data): void
{
	http_response_code($statusCode);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$isDirect = isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
if ($isDirect) {
	$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
	if ($method !== 'POST') {
		_error_log_send_json(405, ['ok' => false, 'error' => 'POST only']);
		exit;
	}

	$payload = _error_log_read_request_payload();
	$ok = append_error_log($payload);
	if ($ok) {
		_error_log_send_json(200, ['ok' => true]);
		exit;
	}

	_error_log_send_json(500, ['ok' => false, 'error' => 'failed to write log']);
	exit;
}

