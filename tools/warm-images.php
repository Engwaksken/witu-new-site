<?php
/**
 * Pre-build responsive image variants for everything under uploads/.
 *
 *   php tools/warm-images.php            build missing variants
 *   php tools/warm-images.php uploads/sliders   limit to one folder
 *
 * Safe to re-run (only stale or missing variants are built), e.g. from cron
 * after editors upload new images.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/image.php';

$root = imgSiteRoot();
$base = trim(str_replace('\\', '/', $argv[1] ?? 'uploads'), '/');
$dir = $root . '/' . $base;

if (!is_dir($dir)) {
    fwrite(STDERR, "Not a directory: {$base}\n");
    exit(1);
}

$built = $skipped = $failed = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);

    if (imgLocalPath($path) === null) {
        continue;
    }

    foreach (imgWidthsFor($path, IMG_VARIANT_WIDTHS) as $width) {
        if (imgVariantIsFresh($path, imgVariantPath($path, $width))) {
            $skipped++;
        } elseif (imgGenerate($path, $width)) {
            $built++;
            echo "built  w{$width}  {$path}\n";
        } else {
            $failed++;
            echo "FAILED w{$width}  {$path}\n";
        }
    }
}

echo "\nBuilt {$built}, already fresh {$skipped}, failed {$failed}.\n";
exit($failed > 0 ? 2 : 0);
