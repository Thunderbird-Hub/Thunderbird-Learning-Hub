<?php
/**
 * PDF Extraction Helpers
 *
 * Extracts text and preview images from PDF uploads so mobile clients can
 * render static HTML and images instead of inline PDF viewers.
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../system/bootstrap.php';
}

require_once __DIR__ . '/../system/config.php';

if (!defined('PDF_EXTRACTED_IMAGE_PATH')) {
    define('PDF_EXTRACTED_IMAGE_PATH', 'uploads/pdf_pages/');
}
if (!defined('PDF_EXTRACTED_IMAGE_LIMIT')) {
    define('PDF_EXTRACTED_IMAGE_LIMIT', 3);
}

/**
 * Determine if the uploaded file is a PDF based on MIME type or extension.
 */
function is_pdf_upload(string $file_type, string $original_filename): bool
{
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $mime_type = strtolower($file_type);

    return $extension === 'pdf' || $mime_type === 'application/pdf';
}

/**
 * Build an absolute path from a relative upload path.
 */
function pdf_absolute_path(string $relative_path): string
{
    $trimmed = ltrim($relative_path, '/');
    return rtrim(APP_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $trimmed;
}

/**
 * Convert plain text into safe HTML paragraphs wrapped in a container.
 */
function pdf_text_to_html(?string $text): ?string
{
    if ($text === null) {
        return null;
    }

    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $paragraphs = preg_split('/\R{2,}/', $text);
    $html_parts = [];

    foreach ($paragraphs as $paragraph) {
        $clean_lines = array_filter(array_map('trim', preg_split('/\R/', $paragraph)), 'strlen');
        if (empty($clean_lines)) {
            continue;
        }
        $safe_paragraph = htmlspecialchars(implode(' ', $clean_lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($safe_paragraph !== '') {
            $html_parts[] = '<p>' . $safe_paragraph . '</p>';
        }
    }

    if (empty($html_parts)) {
        return null;
    }

    return '<div class="training-pdf-body">' . implode("\n", $html_parts) . '</div>';
}

/**
 * Extract text from a PDF using pdftotext if available.
 */
function extract_pdf_text(string $absolute_path): ?string
{
    $pdftotext_path = trim(shell_exec('command -v pdftotext'));
    if ($pdftotext_path === '') {
        if (function_exists('log_debug')) {
            log_debug('pdftotext not available - skipping PDF text extraction');
        }
        return null;
    }

    $temp_file = tempnam(sys_get_temp_dir(), 'pdftext_');
    $command = escapeshellcmd($pdftotext_path) . ' -layout ' . escapeshellarg($absolute_path) . ' ' . escapeshellarg($temp_file);
    exec($command, $output, $return_var);

    if ($return_var !== 0) {
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        if (function_exists('log_debug')) {
            log_debug('pdftotext failed with code ' . $return_var);
        }
        return null;
    }

    $text = file_exists($temp_file) ? file_get_contents($temp_file) : '';
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }

    return $text ?: null;
}

/**
 * Generate preview images for the first few pages of a PDF using pdftoppm.
 *
 * @return array<string> Relative paths to generated JPEG files.
 */
function extract_pdf_images(string $absolute_path, string $stored_filename): array
{
    $pdftoppm_path = trim(shell_exec('command -v pdftoppm'));
    if ($pdftoppm_path === '') {
        if (function_exists('log_debug')) {
            log_debug('pdftoppm not available - skipping PDF image extraction');
        }
        return [];
    }

    $image_dir = APP_ROOT . '/' . rtrim(PDF_EXTRACTED_IMAGE_PATH, '/');
    if (!is_dir($image_dir)) {
        mkdir($image_dir, 0755, true);
    }

    $base_name = pathinfo($stored_filename, PATHINFO_FILENAME);
    $output_prefix = rtrim($image_dir, '/') . '/' . $base_name;

    $command = sprintf(
        '%s -f 1 -l %d -jpeg -scale-to 1280 %s %s',
        escapeshellcmd($pdftoppm_path),
        PDF_EXTRACTED_IMAGE_LIMIT,
        escapeshellarg($absolute_path),
        escapeshellarg($output_prefix)
    );

    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        if (function_exists('log_debug')) {
            log_debug('pdftoppm failed with code ' . $return_var);
        }
        return [];
    }

    $image_urls = [];
    for ($page = 1; $page <= PDF_EXTRACTED_IMAGE_LIMIT; $page++) {
        $image_path = $output_prefix . '-' . $page . '.jpg';
        if (file_exists($image_path)) {
            $relative_path = str_replace(APP_ROOT . '/', '', $image_path);
            $image_urls[] = $relative_path;
        }
    }

    return $image_urls;
}

/**
 * Perform full PDF extraction (text + images) and return DB-ready values.
 */
function extract_pdf_content(string $file_type, string $original_filename, string $relative_path, string $stored_filename): array
{
    $result = [
        'extracted_html' => null,
        'extracted_images_json' => null,
    ];

    if (!is_pdf_upload($file_type, $original_filename)) {
        return $result;
    }

    $absolute_path = pdf_absolute_path($relative_path);
    if (!file_exists($absolute_path) || !is_readable($absolute_path)) {
        if (function_exists('log_debug')) {
            log_debug('PDF extraction skipped - file missing at ' . $absolute_path);
        }
        return $result;
    }

    $text = extract_pdf_text($absolute_path);
    $html = pdf_text_to_html($text);
    if ($html !== null) {
        $result['extracted_html'] = $html;
    }

    $images = extract_pdf_images($absolute_path, $stored_filename);
    if (!empty($images)) {
        $result['extracted_images_json'] = json_encode($images);
    }

    return $result;
}

/**
 * Remove generated PDF image assets from disk.
 */
function cleanup_pdf_extractions(?string $extracted_images_json): void
{
    if (empty($extracted_images_json)) {
        return;
    }

    $images = json_decode($extracted_images_json, true);
    if (!is_array($images)) {
        return;
    }

    foreach ($images as $image_path) {
        $absolute = pdf_absolute_path($image_path);
        if (file_exists($absolute)) {
            @unlink($absolute);
        }
    }
}
?>
