<?php
/**
 * Responsive image variants.
 *
 * Uploaded photos are often several megabytes straight from a camera. These
 * helpers serve resized (WebP when available) copies stored under
 * uploads/_resized/w{width}/. Missing variants are never generated while the
 * page renders: the original is served for that request and the variant is
 * built after the response has been sent, so every later visit gets the
 * small file. tools/warm-images.php pre-builds everything from the CLI.
 */

/** Every width a variant may have; tools/warm-images.php builds all of them. */
if (!defined('IMG_VARIANT_WIDTHS')) {
    define('IMG_VARIANT_WIDTHS', [96, 192, 400, 800, 1200, 1600, 1920]);
}

/** Default srcset widths for full-width photos. */
if (!defined('IMG_PHOTO_WIDTHS')) {
    define('IMG_PHOTO_WIDTHS', [400, 800, 1200, 1600, 1920]);
}

if (!defined('IMG_VARIANT_DIR')) {
    define('IMG_VARIANT_DIR', 'uploads/_resized');
}

/** Generation budget per request (seconds) so shutdown work stays bounded. */
if (!defined('IMG_VARIANT_BUDGET')) {
    define('IMG_VARIANT_BUDGET', 10);
}

function imgSiteRoot(): string
{
    return dirname(__DIR__);
}

/**
 * Normalise a stored image path to a site-relative path ("uploads/x.jpg"),
 * or return null when it is remote, missing or not a resizable type.
 */
function imgLocalPath($src): ?string
{
    $src = trim(str_replace('\\', '/', (string)$src));

    if ($src === '' || preg_match('#^([a-z]+:)?//#i', $src) || str_starts_with($src, 'data:')) {
        return null;
    }

    $src = ltrim((string)parse_url($src, PHP_URL_PATH), '/');
    $src = rawurldecode($src);

    if ($src === '' || str_contains($src, '..') || str_starts_with($src, IMG_VARIANT_DIR . '/')) {
        return null;
    }

    if (!preg_match('#^(uploads|assets/img)/.+\.(jpe?g|png)$#i', $src)) {
        return null;
    }

    return is_file(imgSiteRoot() . '/' . $src) ? $src : null;
}

function imgOutputExtension(string $path): string
{
    if (function_exists('imagewebp')) {
        return 'webp';
    }

    return preg_match('/\.png$/i', $path) ? 'png' : 'jpg';
}

/** Intrinsic [width, height] of a local image, cached per request. */
function imgDimensions($src): ?array
{
    static $cache = [];

    $path = imgLocalPath($src);
    if ($path === null) {
        return null;
    }

    if (!array_key_exists($path, $cache)) {
        $info = @getimagesize(imgSiteRoot() . '/' . $path);
        $cache[$path] = $info ? [(int)$info[0], (int)$info[1]] : null;

        if ($cache[$path] !== null && preg_match('/\.jpe?g$/i', $path)) {
            // Camera photos rotated by EXIF display with swapped dimensions.
            $orientation = imgJpegOrientation(imgSiteRoot() . '/' . $path);
            if (in_array($orientation, [5, 6, 7, 8], true)) {
                $cache[$path] = [$cache[$path][1], $cache[$path][0]];
            }
        }
    }

    return $cache[$path];
}

/** Widths worth producing for an image: never upscale, always include a capped copy. */
function imgWidthsFor(string $path, array $widths): array
{
    // Only canonical widths, so pages never ask for a variant the warm script skips.
    $widths = array_intersect($widths, IMG_VARIANT_WIDTHS);

    $dims = imgDimensions($path);
    if ($dims === null) {
        return [];
    }

    $out = [];
    foreach ($widths as $width) {
        $out[] = min((int)$width, $dims[0]);
    }

    $out = array_values(array_unique(array_filter($out)));
    sort($out);

    return $out;
}

function imgVariantPath(string $path, int $width): string
{
    return IMG_VARIANT_DIR . '/w' . $width . '/' . preg_replace('#^uploads/#', '', $path) . '.' . imgOutputExtension($path);
}

function imgVariantIsFresh(string $path, string $variant): bool
{
    $root = imgSiteRoot();

    return is_file($root . '/' . $variant)
        && filemtime($root . '/' . $variant) >= filemtime($root . '/' . $path);
}

/**
 * URL of the best variant no wider than $width; falls back to the original
 * (and queues the variant) when it has not been built yet.
 */
function imgVariant($src, int $width = 1200): string
{
    $path = imgLocalPath($src);
    if ($path === null) {
        return (string)$src;
    }

    $widths = imgWidthsFor($path, [$width]);
    if (!$widths) {
        return (string)$src;
    }

    $variant = imgVariantPath($path, $widths[0]);
    if (imgVariantIsFresh($path, $variant)) {
        return imgUrl($variant);
    }

    imgQueue($path, $widths[0]);

    return (string)$src;
}

/** "url 480w, url 800w" for variants that already exist (queues the rest). */
function imgSrcset($src, array $widths = IMG_PHOTO_WIDTHS): string
{
    $path = imgLocalPath($src);
    if ($path === null) {
        return '';
    }

    $parts = [];
    foreach (imgWidthsFor($path, $widths) as $width) {
        $variant = imgVariantPath($path, $width);

        if (imgVariantIsFresh($path, $variant)) {
            $parts[] = imgUrl($variant) . ' ' . $width . 'w';
        } else {
            imgQueue($path, $width);
        }
    }

    // A partial set would let the browser pick a too-small image.
    return count($parts) === count(imgWidthsFor($path, $widths)) ? implode(', ', $parts) : '';
}

function imgUrl(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/**
 * Render an <img> with srcset, intrinsic size (prevents layout shift) and
 * lazy loading unless $attrs overrides it.
 */
function responsiveImg($src, string $alt, array $attrs = [], array $widths = IMG_PHOTO_WIDTHS, string $sizes = '100vw'): string
{
    $src = (string)$src;
    $largest = max($widths);
    $attrs = array_merge(['loading' => 'lazy', 'decoding' => 'async'], $attrs);

    $html = '<img src="' . htmlspecialchars(imgVariant($src, $largest), ENT_QUOTES, 'UTF-8') . '"';

    $srcset = imgSrcset($src, $widths);
    if ($srcset !== '') {
        $html .= ' srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '"';
        $html .= ' sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
    }

    $dims = imgDimensions($src);
    if ($dims !== null && !isset($attrs['width'], $attrs['height'])) {
        $html .= ' width="' . $dims[0] . '" height="' . $dims[1] . '"';
    }

    $html .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';

    foreach ($attrs as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }
        $html .= ' ' . $name . ($value === true ? '' : '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"');
    }

    return $html . '>';
}

/* -------------------------------------------------------------------------
   Deferred generation
------------------------------------------------------------------------- */

function imgQueue(string $path, int $width): void
{
    static $registered = false;

    $GLOBALS['__img_variant_queue'][$path . '|' . $width] = [$path, $width];

    if (!$registered && PHP_SAPI !== 'cli') {
        $registered = true;
        register_shutdown_function('imgProcessQueue');
    }
}

function imgProcessQueue(): void
{
    $queue = $GLOBALS['__img_variant_queue'] ?? [];
    if (!$queue) {
        return;
    }

    // Send the page to the visitor first; resizing happens afterwards.
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    ignore_user_abort(true);
    @set_time_limit(IMG_VARIANT_BUDGET + 30);

    $started = microtime(true);
    foreach ($queue as [$path, $width]) {
        if (microtime(true) - $started > IMG_VARIANT_BUDGET) {
            break;
        }
        imgGenerate($path, $width);
    }
}

/** Build one variant. Returns true when the variant exists afterwards. */
function imgGenerate(string $path, int $width): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $root = imgSiteRoot();
    $variant = imgVariantPath($path, $width);
    $target = $root . '/' . $variant;

    if (imgVariantIsFresh($path, $variant)) {
        return true;
    }

    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    // Another request is already building this file.
    $lock = @fopen($target . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return false;
    }

    $ok = false;

    try {
        $source = $root . '/' . $path;
        $info = @getimagesize($source);

        // Skip images too large to decode safely (~40 megapixels).
        if (!$info || $info[0] * $info[1] > 40000000) {
            return false;
        }

        @ini_set('memory_limit', '512M');

        $isPng = $info[2] === IMAGETYPE_PNG;
        $image = $isPng ? @imagecreatefrompng($source) : @imagecreatefromjpeg($source);
        if (!$image) {
            return false;
        }

        if (!$isPng) {
            $image = imgApplyOrientation($image, imgJpegOrientation($source));
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $dstW = min($width, $srcW);
        $dstH = max(1, (int)round($srcH * $dstW / $srcW));

        $resized = imagecreatetruecolor($dstW, $dstH);
        if ($isPng) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($image);

        $tmp = $target . '.' . getmypid() . '.tmp';
        $extension = imgOutputExtension($path);

        if ($extension === 'webp') {
            if (!imageistruecolor($resized)) {
                imagepalettetotruecolor($resized);
            }
            $ok = imagewebp($resized, $tmp, 78);
        } elseif ($extension === 'png') {
            $ok = imagepng($resized, $tmp, 9);
        } else {
            imageinterlace($resized, true);
            $ok = imagejpeg($resized, $tmp, 80);
        }
        imagedestroy($resized);

        // Keep the original if re-encoding did not make it smaller.
        if ($ok && filesize($tmp) >= filesize($source) && $dstW === $srcW && $extension !== 'webp') {
            $ok = copy($source, $tmp);
        }

        $ok = $ok && rename($tmp, $target);
        if (!$ok && is_file($tmp)) {
            @unlink($tmp);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($target . '.lock');
    }

    return $ok;
}

/** EXIF orientation (1-8) read straight from the JPEG APP1 segment. */
function imgJpegOrientation(string $file): int
{
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return 1;
    }

    $orientation = 1;

    try {
        if (fread($fh, 2) !== "\xFF\xD8") {
            return 1;
        }

        while (!feof($fh)) {
            $marker = fread($fh, 2);
            if (strlen($marker) < 2 || $marker[0] !== "\xFF") {
                break;
            }

            $length = unpack('n', (string)fread($fh, 2))[1] ?? 0;
            if ($length < 2) {
                break;
            }

            if ($marker[1] === "\xDA") {
                break; // Image data starts; no EXIF found.
            }

            if ($marker[1] !== "\xE1") {
                fseek($fh, $length - 2, SEEK_CUR);
                continue;
            }

            $data = (string)fread($fh, $length - 2);
            if (!str_starts_with($data, "Exif\0\0")) {
                continue;
            }

            $tiff = substr($data, 6);
            $little = substr($tiff, 0, 2) === 'II';
            $u16 = fn(int $at) => unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] ?? 0;
            $u32 = fn(int $at) => unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] ?? 0;

            $ifd = $u32(4);
            $entries = $u16($ifd);

            for ($i = 0; $i < $entries; $i++) {
                $entry = $ifd + 2 + $i * 12;
                if ($u16($entry) === 0x0112) {
                    $orientation = $u16($entry + 8);
                    break 2;
                }
            }
            break;
        }
    } finally {
        fclose($fh);
    }

    return ($orientation >= 1 && $orientation <= 8) ? $orientation : 1;
}

function imgApplyOrientation($image, int $orientation)
{
    // imagerotate() turns counter-clockwise.
    $angle = match ($orientation) {
        3 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => 0,
    };

    if ($angle !== 0) {
        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);
        $image = $rotated;
    }

    if (in_array($orientation, [2, 5, 7], true)) {
        imageflip($image, IMG_FLIP_HORIZONTAL);
    } elseif ($orientation === 4) {
        imageflip($image, IMG_FLIP_VERTICAL);
    }

    return $image;
}
