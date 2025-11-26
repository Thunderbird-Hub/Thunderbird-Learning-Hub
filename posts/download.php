<?php
// Secure file download/inline viewer for posts and replies
$login_path = '/mobile/login.php';

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';

$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$force_download = isset($_GET['download']) && $_GET['download'] === '1';
$inline_request = isset($_GET['inline']) && $_GET['inline'] === '1';

if ($file_id <= 0) {
    http_response_code(400);
    exit('Missing or invalid file id.');
}

$stmt = $pdo->prepare('SELECT id, original_filename, file_path, file_size FROM files WHERE id = ? LIMIT 1');
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || empty($file['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}

$raw_path = $file['file_path'];

// Redirect remote assets directly
if (preg_match('#^https?://#i', $raw_path)) {
    header('Location: ' . $raw_path);
    exit;
}

$project_root = realpath(__DIR__ . '/..');
$full_path = $raw_path;
if ($raw_path[0] === '/') {
    $full_path = realpath($project_root . $raw_path);
} else {
    $full_path = realpath($project_root . '/' . ltrim($raw_path, '/'));
}

if ($full_path === false || strpos($full_path, $project_root) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    exit('File not found.');
}

$ext = strtolower(pathinfo($file['original_filename'] ?? $full_path, PATHINFO_EXTENSION));
$is_pdf = ($ext === 'pdf');

$mime_type = $is_pdf ? 'application/pdf' : (mime_content_type($full_path) ?: 'application/octet-stream');
$size = filesize($full_path);

// Default to inline for PDFs to support previews; allow explicit download toggle
$disposition = 'inline';
if ($force_download || (!$is_pdf && !$inline_request)) {
    $disposition = 'attachment';
}

// Basic range support for improved PDF viewing
$range_start = 0;
$range_end = $size - 1;
$status_code = 200;

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
    if ($matches[1] !== '') {
        $range_start = intval($matches[1]);
    }
    if ($matches[2] !== '') {
        $range_end = intval($matches[2]);
    }
    if ($range_end > $size - 1) {
        $range_end = $size - 1;
    }
    if ($range_start <= $range_end) {
        $status_code = 206;
    }
}

if ($status_code === 206) {
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes {$range_start}-{$range_end}/{$size}");
    $size = ($range_end - $range_start) + 1;
}

header('Content-Type: ' . $mime_type);
header('Accept-Ranges: bytes');
header('Content-Length: ' . $size);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($file['original_filename'] ?? basename($full_path)) . '"');

$fp = fopen($full_path, 'rb');
if ($status_code === 206) {
    fseek($fp, $range_start);
}

$chunk_size = 8192;
while (!feof($fp) && $size > 0) {
    $read_length = ($size > $chunk_size) ? $chunk_size : $size;
    $buffer = fread($fp, $read_length);
    echo $buffer;
    $size -= $read_length;
    if (connection_status() != CONNECTION_NORMAL) {
        break;
    }
}

fclose($fp);
exit;
