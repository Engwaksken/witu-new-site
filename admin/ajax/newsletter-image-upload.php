<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function newsletterImageResponse(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message,
        ], $data),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function newsletterUploadLimitBytes(): int
{
    $toBytes = static function (string $value): int {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $last = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($last) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    };

    $uploadMax = $toBytes((string) ini_get('upload_max_filesize'));
    $postMax = $toBytes((string) ini_get('post_max_size'));

    $configured = defined('MAX_FILE_SIZE')
        ? (int) MAX_FILE_SIZE
        : 10 * 1024 * 1024;

    $limits = array_filter(
        [$uploadMax, $postMax, $configured],
        static fn (int $value): bool => $value > 0
    );

    return $limits ? min($limits) : $configured;
}

function newsletterHumanBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2), '0'), '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 2), '0'), '.') . ' KB';
    }

    return $bytes . ' bytes';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('requireLogin')) {
    requireLogin();
}

if (
    function_exists('hasPermission')
    && !hasPermission($conn, 'manage_newsletters')
) {
    newsletterImageResponse(
        false,
        'You do not have permission to upload newsletter images.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    newsletterImageResponse(
        false,
        'Invalid request method.',
        [],
        405
    );
}

/*
|--------------------------------------------------------------------------
| Detect post_max_size overflow
|--------------------------------------------------------------------------
|
| When the entire POST body exceeds post_max_size PHP can leave $_POST and
| $_FILES completely empty. Without this check that looks like "no file
| selected", even though the user did select a file.
|
*/
$contentLength = isset($_SERVER['CONTENT_LENGTH'])
    ? (int) $_SERVER['CONTENT_LENGTH']
    : 0;

$postMaxRaw = (string) ini_get('post_max_size');

$parseIniBytes = static function (string $value): int {
    $value = trim($value);

    if ($value === '') {
        return 0;
    }

    $last = strtolower(substr($value, -1));
    $number = (float) $value;

    return match ($last) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
};

$postMaxBytes = $parseIniBytes($postMaxRaw);

if (
    $contentLength > 0
    && $postMaxBytes > 0
    && $contentLength > $postMaxBytes
) {
    newsletterImageResponse(
        false,
        'The selected image is larger than the server POST limit of '
            . newsletterHumanBytes($postMaxBytes)
            . '. Please choose a smaller image or increase post_max_size.',
        [
            'error_code' => 'POST_TOO_LARGE',
            'max_size' => $postMaxBytes,
        ],
        413
    );
}

if (!isset($_FILES['image'])) {
    newsletterImageResponse(
        false,
        'No image file was received by the server. Please select the image again.',
        [
            'error_code' => 'FILE_NOT_RECEIVED',
            'received_fields' => array_keys($_FILES),
        ],
        422
    );
}

$file = $_FILES['image'];

if (
    !isset($file['error'])
    || is_array($file['error'])
) {
    newsletterImageResponse(
        false,
        'Invalid image upload request.',
        ['error_code' => 'INVALID_UPLOAD'],
        422
    );
}

$uploadError = (int) $file['error'];

if ($uploadError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE =>
            'The selected image exceeds the server upload_max_filesize limit of '
            . ini_get('upload_max_filesize') . '.',

        UPLOAD_ERR_FORM_SIZE =>
            'The selected image is larger than the maximum size allowed by the form.',

        UPLOAD_ERR_PARTIAL =>
            'The image upload was interrupted and only part of the file reached the server. Please try again.',

        UPLOAD_ERR_NO_FILE =>
            'No image was uploaded. Please select an image and try again.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload directory is missing. Please contact the system administrator.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not write the uploaded image to disk. Check folder permissions and available disk space.',

        UPLOAD_ERR_EXTENSION =>
            'A PHP extension stopped the image upload.',
    ];

    newsletterImageResponse(
        false,
        $errorMessages[$uploadError] ?? 'Image upload failed with error code ' . $uploadError . '.',
        [
            'error_code' => $uploadError,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ],
        422
    );
}

if (
    empty($file['tmp_name'])
    || !is_uploaded_file($file['tmp_name'])
) {
    newsletterImageResponse(
        false,
        'The uploaded image could not be verified as a valid HTTP upload. Please try again.',
        ['error_code' => 'INVALID_TEMP_FILE'],
        422
    );
}

$fileSize = isset($file['size']) ? (int) $file['size'] : 0;
$maxBytes = newsletterUploadLimitBytes();

if ($fileSize <= 0) {
    newsletterImageResponse(
        false,
        'The selected image is empty.',
        ['error_code' => 'EMPTY_FILE'],
        422
    );
}

if ($maxBytes > 0 && $fileSize > $maxBytes) {
    newsletterImageResponse(
        false,
        'The selected image is '
            . newsletterHumanBytes($fileSize)
            . ', but the maximum allowed size is '
            . newsletterHumanBytes($maxBytes)
            . '.',
        [
            'error_code' => 'FILE_TOO_LARGE',
            'file_size' => $fileSize,
            'max_size' => $maxBytes,
        ],
        413
    );
}

$originalName = basename((string) ($file['name'] ?? 'image'));
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowedExtensions = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
];

if (!in_array($extension, $allowedExtensions, true)) {
    newsletterImageResponse(
        false,
        'Unsupported image format. Please use JPG, JPEG, PNG, GIF or WebP.',
        [
            'error_code' => 'INVALID_EXTENSION',
            'extension' => $extension,
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Verify actual MIME type
|--------------------------------------------------------------------------
*/
$detectedMime = '';

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo) {
        $detectedMime = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}

if ($detectedMime === '' && function_exists('mime_content_type')) {
    $detectedMime = (string) mime_content_type($file['tmp_name']);
}

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

if (
    $detectedMime !== ''
    && !in_array($detectedMime, $allowedMimeTypes, true)
) {
    newsletterImageResponse(
        false,
        'The uploaded file is not a supported image.',
        [
            'error_code' => 'INVALID_MIME',
            'mime_type' => $detectedMime,
        ],
        422
    );
}

$result = uploadFile(
    $file,
    'newsletters/builder',
    $allowedExtensions
);

if (empty($result['success'])) {
    newsletterImageResponse(
        false,
        (string) ($result['message'] ?? 'The image could not be saved.'),
        [
            'error_code' => 'SAVE_FAILED',
        ],
        422
    );
}

$filename = (string) $result['filename'];

$scheme = (
    !empty($_SERVER['HTTPS'])
    && strtolower((string) $_SERVER['HTTPS']) !== 'off'
)
    ? 'https'
    : 'http';

$host = $_SERVER['HTTP_HOST'] ?? 'www.witu.org';

if (defined('SITE_URL') && trim((string) SITE_URL) !== '') {
    $baseUrl = rtrim((string) SITE_URL, '/');
} else {
    $baseUrl = $scheme . '://' . $host;
}

$url = $baseUrl
    . '/uploads/newsletters/builder/'
    . rawurlencode($filename);

/*
|--------------------------------------------------------------------------
| Verify saved file
|--------------------------------------------------------------------------
*/
$savedPath = $result['path'] ?? null;

if (
    is_string($savedPath)
    && $savedPath !== ''
    && !is_file($savedPath)
) {
    newsletterImageResponse(
        false,
        'The upload completed but the image could not be found in the destination folder.',
        [
            'error_code' => 'SAVED_FILE_MISSING',
        ],
        500
    );
}

newsletterImageResponse(
    true,
    'Image uploaded successfully.',
    [
        'url' => $url,
        'filename' => $filename,
        'original_name' => $originalName,
        'size' => $fileSize,
        'mime_type' => $detectedMime,
    ]
);
