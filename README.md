# WITU website

PHP and MySQL website for Women in Technology Uganda, including public programme, impact, opportunity and participation pages, plus an administration area.

## Setup

1. Install PHP with the mysqli extension, MySQL, Composer and an Apache server with mod_rewrite enabled.
2. Run `composer install` to install the locked dependencies.
3. Copy `config/db.example.php` to `config/db.php` and set your local database credentials.
4. Restore the existing database through a secure backup. This repository does not include a database export.
5. Restore the `uploads/` directory from your existing deployment or backup. Uploaded images and documents are excluded from source control.
6. Configure your web server to serve the project directory. Review `.htaccess` before local use: it currently redirects traffic to `https://www.witu.org`.

## Validation

Run PHP syntax checks for modified PHP files and `node --check assets/js/site.js` for the shared JavaScript. Full visual and form testing requires a working database and uploaded content.

## Images and performance

Pages serve resized WebP copies of uploaded images from `uploads/_resized/` (see `core/image.php`). A missing copy is built in the background after the page that needs it has been sent, so the first visitor gets the original file. After deploying, or after uploading many images, build them all at once:

```
php tools/warm-images.php
```

The script can be re-run safely, including from cron. Local CSS and JS links carry a `?v=<modified time>` version because `.htaccess` caches them as immutable.

## Frontend audit

See [FRONTEND-AUDIT.md](FRONTEND-AUDIT.md) for implemented design, usability and accessibility improvements and remaining live verification.

Local credentials, runtime uploads, database dumps, logs and installed dependencies are intentionally excluded from Git.
