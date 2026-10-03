<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';

adminTrackActivity($conn);
requirePermission($conn, 'manage_newsletters');

$page_title = 'Newsletters';

/* ==========================================================================
   Database
   ========================================================================== */

function nlTableColumnExists(mysqli $conn, string $table, string $column): bool
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

function nlEnsureCampaignColumn(mysqli $conn, string $column, string $definition): void
{
    if (!nlTableColumnExists($conn, 'newsletter_campaigns', $column)) {
        $conn->query("ALTER TABLE newsletter_campaigns ADD COLUMN {$column} {$definition}");
    }
}

function nlEnsureTables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NULL,
        email VARCHAR(255) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        source VARCHAR(50) NULL,
        tags VARCHAR(500) NULL,
        subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at TIMESTAMP NULL,
        UNIQUE KEY uq_newsletter_email (email),
        INDEX idx_newsletter_status (status),
        INDEX idx_newsletter_source (source)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_campaigns (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        preheader VARCHAR(255) NULL,
        builder_json LONGTEXT NULL,
        body_html LONGTEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        recipient_source VARCHAR(30) NULL,
        recipient_count INT NOT NULL DEFAULT 0,
        sent_count INT NOT NULL DEFAULT 0,
        failed_count INT NOT NULL DEFAULT 0,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        INDEX idx_campaign_status (status),
        INDEX idx_campaign_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    nlEnsureCampaignColumn($conn, 'from_name', "VARCHAR(150) NULL AFTER preheader");
    nlEnsureCampaignColumn($conn, 'reply_to', "VARCHAR(255) NULL AFTER from_name");
    nlEnsureCampaignColumn($conn, 'campaign_tags', "VARCHAR(500) NULL AFTER reply_to");
    nlEnsureCampaignColumn($conn, 'scheduled_at', "DATETIME NULL AFTER failed_count");

    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_send_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(255) NOT NULL,
        status VARCHAR(30) NOT NULL,
        error_message VARCHAR(500) NULL,
        sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_send_campaign (campaign_id),
        INDEX idx_send_status (status),
        INDEX idx_send_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS newsletter_templates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description VARCHAR(500) NULL,
        builder_json LONGTEXT NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_template_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
nlEnsureTables($conn);

/* ==========================================================================
   Helpers
   ========================================================================== */

function nlValidEmailsFromCsv(string $tmp): array
{
    $emails = [];

    if (!is_file($tmp) || ($handle = fopen($tmp, 'r')) === false) {
        return [];
    }

    while (($row = fgetcsv($handle)) !== false) {
        foreach ($row as $cell) {
            $cell = trim((string) $cell);
            if (filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($cell)] = $cell;
            }
        }
    }

    fclose($handle);
    return array_values($emails);
}

function nlWrapEmailHtml(string $html, string $preheader = ''): string
{
    $safePreheader = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

    return '<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Newsletter</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
' . ($preheader !== ''
        ? '<div style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;">' . $safePreheader . '</div>'
        : '') . '
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f1f5f9;">
<tr>
<td align="center" style="padding:24px 10px;">
' . $html . '
</td>
</tr>
</table>
</body>
</html>';
}

function nlSendMail(
    string $email,
    string $subject,
    string $html,
    string $preheader = '',
    string $fromName = '',
    string $replyTo = ''
): array {
    $html = nlWrapEmailHtml($html, $preheader);

    try {
        if (function_exists('sendEmail')) {
            $ok = sendEmail($email, $subject, $html);
            return [
                'success' => (bool) $ok,
                'error' => $ok ? '' : 'sendEmail() returned false',
            ];
        }

        $defaultFrom = defined('MAIL_FROM_ADDRESS')
            ? MAIL_FROM_ADDRESS
            : (defined('SITE_EMAIL')
                ? SITE_EMAIL
                : 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        $senderName = $fromName !== ''
            ? $fromName
            : (defined('SITE_NAME') ? SITE_NAME : 'WITU');

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . $senderName . " <{$defaultFrom}>\r\n";

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }

        $ok = @mail($email, $subject, $html, $headers);

        return [
            'success' => $ok,
            'error' => $ok ? '' : 'mail() returned false',
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

function nlGetCampaign(mysqli $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM newsletter_campaigns WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $campaign = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $campaign;
}

function nlRedirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ==========================================================================
   POST actions
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $name = trim((string) ($_POST['campaign_name'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $preheader = trim((string) ($_POST['preheader'] ?? ''));
        $fromName = trim((string) ($_POST['from_name'] ?? ''));
        $replyTo = trim((string) ($_POST['reply_to'] ?? ''));
        $campaignTags = trim((string) ($_POST['campaign_tags'] ?? ''));
        $builderJson = (string) ($_POST['builder_json'] ?? '[]');
        $bodyHtml = (string) ($_POST['body_html'] ?? '');

        if ($name === '' || $subject === '' || trim(strip_tags($bodyHtml)) === '') {
            setFlashMessage('error', 'Campaign name, subject and newsletter content are required.');
        } elseif ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Reply-to email is not valid.');
        } elseif ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE newsletter_campaigns
                SET name = ?, subject = ?, preheader = ?, from_name = ?, reply_to = ?,
                    campaign_tags = ?, builder_json = ?, body_html = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'ssssssssi',
                $name,
                $subject,
                $preheader,
                $fromName,
                $replyTo,
                $campaignTags,
                $builderJson,
                $bodyHtml,
                $id
            );
            $stmt->execute();
            $stmt->close();

            adminLogActivity($conn, 'update', 'newsletters', 'Updated newsletter campaign: ' . $name);
            setFlashMessage('success', 'Newsletter campaign saved successfully.');
        } else {
            $userId = (int) ($_SESSION['user_id'] ?? 0);

            $stmt = $conn->prepare("
                INSERT INTO newsletter_campaigns
                (name, subject, preheader, from_name, reply_to, campaign_tags, builder_json, body_html, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssssssssi',
                $name,
                $subject,
                $preheader,
                $fromName,
                $replyTo,
                $campaignTags,
                $builderJson,
                $bodyHtml,
                $userId
            );
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();

            adminLogActivity($conn, 'create', 'newsletters', 'Created newsletter campaign: ' . $name);
            setFlashMessage('success', 'Newsletter campaign created.');
        }

        nlRedirect('newsletters.php?tab=builder&id=' . $id);
    }

    if ($action === 'duplicate_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $campaign = nlGetCampaign($conn, $id);

        if (!$campaign) {
            setFlashMessage('error', 'Campaign not found.');
            nlRedirect('newsletters.php?tab=campaigns');
        }

        $newName = $campaign['name'] . ' - Copy';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $stmt = $conn->prepare("
            INSERT INTO newsletter_campaigns
            (name, subject, preheader, from_name, reply_to, campaign_tags, builder_json, body_html, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
        ");
        $stmt->bind_param(
            'ssssssssi',
            $newName,
            $campaign['subject'],
            $campaign['preheader'],
            $campaign['from_name'],
            $campaign['reply_to'],
            $campaign['campaign_tags'],
            $campaign['builder_json'],
            $campaign['body_html'],
            $userId
        );
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        adminLogActivity($conn, 'create', 'newsletters', 'Duplicated newsletter campaign: ' . $campaign['name']);
        setFlashMessage('success', 'Campaign duplicated. You can now edit the copy.');
        nlRedirect('newsletters.php?tab=builder&id=' . $newId);
    }

    if ($action === 'save_template') {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $templateName = trim((string) ($_POST['template_name'] ?? ''));
        $templateDescription = trim((string) ($_POST['template_description'] ?? ''));
        $builderJson = (string) ($_POST['builder_json'] ?? '[]');

        if ($templateName === '' || $builderJson === '' || $builderJson === '[]') {
            setFlashMessage('error', 'Template name and newsletter layout are required.');
            nlRedirect('newsletters.php?tab=builder&id=' . $campaignId);
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare("
            INSERT INTO newsletter_templates(name, description, builder_json, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('sssi', $templateName, $templateDescription, $builderJson, $userId);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Newsletter layout saved as a reusable template.');
        nlRedirect('newsletters.php?tab=builder&id=' . $campaignId);
    }

    if ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM newsletter_templates WHERE id = ?");
        $stmt->bind_param('i', $templateId);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Template deleted.');
        nlRedirect('newsletters.php?tab=templates');
    }

    if ($action === 'test_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $testEmail = trim((string) ($_POST['test_email'] ?? ''));
        $campaign = nlGetCampaign($conn, $id);

        if (!$campaign) {
            setFlashMessage('error', 'Save the campaign before sending a test.');
        } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Enter a valid test email address.');
        } else {
            $result = nlSendMail(
                $testEmail,
                '[TEST] ' . $campaign['subject'],
                $campaign['body_html'],
                $campaign['preheader'] ?? '',
                $campaign['from_name'] ?? '',
                $campaign['reply_to'] ?? ''
            );

            setFlashMessage(
                $result['success'] ? 'success' : 'error',
                $result['success']
                    ? 'Test newsletter sent to ' . $testEmail . '.'
                    : 'Test email failed: ' . $result['error']
            );
        }

        nlRedirect('newsletters.php?tab=builder&id=' . $id);
    }

    if ($action === 'send_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        $source = (string) ($_POST['recipient_source'] ?? 'subscribers');
        $campaign = nlGetCampaign($conn, $id);

        if (!$campaign) {
            setFlashMessage('error', 'Campaign not found.');
            nlRedirect('newsletters.php?tab=campaigns');
        }

        $emails = [];

        if (in_array($source, ['subscribers', 'both'], true)) {
            $result = $conn->query("SELECT email FROM newsletter_subscribers WHERE status = 'active'");
            while ($result && $row = $result->fetch_assoc()) {
                if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $emails[strtolower($row['email'])] = $row['email'];
                }
            }
        }

        if (
            in_array($source, ['csv', 'both'], true)
            && isset($_FILES['recipient_csv'])
            && $_FILES['recipient_csv']['error'] === UPLOAD_ERR_OK
        ) {
            foreach (nlValidEmailsFromCsv($_FILES['recipient_csv']['tmp_name']) as $email) {
                $emails[strtolower($email)] = $email;
            }
        }

        $emails = array_values($emails);

        if (!$emails) {
            setFlashMessage('error', 'No valid recipient emails were found.');
            nlRedirect('newsletters.php?tab=builder&id=' . $id);
        }

        $sent = 0;
        $failed = 0;

        foreach ($emails as $email) {
            $result = nlSendMail(
                $email,
                $campaign['subject'],
                $campaign['body_html'],
                $campaign['preheader'] ?? '',
                $campaign['from_name'] ?? '',
                $campaign['reply_to'] ?? ''
            );

            $status = $result['success'] ? 'sent' : 'failed';
            $error = $result['error'];

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }

            $log = $conn->prepare("
                INSERT INTO newsletter_send_logs(campaign_id, email, status, error_message)
                VALUES (?, ?, ?, ?)
            ");
            $log->bind_param('isss', $id, $email, $status, $error);
            $log->execute();
            $log->close();
        }

        $total = count($emails);
        $campaignStatus = $sent > 0 ? 'sent' : 'failed';

        $stmt = $conn->prepare("
            UPDATE newsletter_campaigns
            SET status = ?, recipient_source = ?, recipient_count = ?,
                sent_count = ?, failed_count = ?, sent_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ssiiii', $campaignStatus, $source, $total, $sent, $failed, $id);
        $stmt->execute();
        $stmt->close();

        adminLogActivity(
            $conn,
            'send',
            'newsletters',
            "Sent campaign {$campaign['name']} to {$sent}/{$total} recipients"
        );

        setFlashMessage(
            $sent > 0 ? 'success' : 'error',
            "Campaign processed: {$sent} sent, {$failed} failed."
        );

        nlRedirect('newsletters.php?tab=report&id=' . $id);
    }

    if ($action === 'delete_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM newsletter_send_logs WHERE campaign_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM newsletter_campaigns WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Campaign deleted.');
        nlRedirect('newsletters.php?tab=campaigns');
    }

    if ($action === 'import_subscribers') {
        $added = 0;

        if (
            isset($_FILES['subscriber_csv'])
            && $_FILES['subscriber_csv']['error'] === UPLOAD_ERR_OK
        ) {
            foreach (nlValidEmailsFromCsv($_FILES['subscriber_csv']['tmp_name']) as $email) {
                $stmt = $conn->prepare("
                    INSERT INTO newsletter_subscribers(email, status, source)
                    VALUES (?, 'active', 'csv')
                    ON DUPLICATE KEY UPDATE
                        status = 'active',
                        unsubscribed_at = NULL
                ");
                $stmt->bind_param('s', $email);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $added++;
                }

                $stmt->close();
            }
        }

        setFlashMessage('success', "Subscriber import complete. {$added} records added or reactivated.");
        nlRedirect('newsletters.php?tab=subscribers');
    }

    if ($action === 'add_subscriber') {
        $name = trim((string) ($_POST['subscriber_name'] ?? ''));
        $email = trim((string) ($_POST['subscriber_email'] ?? ''));
        $tags = trim((string) ($_POST['subscriber_tags'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Enter a valid subscriber email.');
        } else {
            $stmt = $conn->prepare("
                INSERT INTO newsletter_subscribers(name, email, status, source, tags)
                VALUES (?, ?, 'active', 'admin', ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    tags = VALUES(tags),
                    status = 'active',
                    unsubscribed_at = NULL
            ");
            $stmt->bind_param('sss', $name, $email, $tags);
            $stmt->execute();
            $stmt->close();

            setFlashMessage('success', 'Subscriber saved.');
        }

        nlRedirect('newsletters.php?tab=subscribers');
    }

    if ($action === 'toggle_subscriber') {
        $id = (int) ($_POST['subscriber_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'unsubscribed';

        if ($status === 'active') {
            $stmt = $conn->prepare("
                UPDATE newsletter_subscribers
                SET status = 'active', unsubscribed_at = NULL
                WHERE id = ?
            ");
        } else {
            $stmt = $conn->prepare("
                UPDATE newsletter_subscribers
                SET status = 'unsubscribed', unsubscribed_at = NOW()
                WHERE id = ?
            ");
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Subscriber status updated.');
        nlRedirect('newsletters.php?tab=subscribers');
    }

    if ($action === 'delete_subscriber') {
        $id = (int) ($_POST['subscriber_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Subscriber deleted.');
        nlRedirect('newsletters.php?tab=subscribers');
    }

    /* Existing downloadable PDF newsletter archive */
    if ($action === 'save_archive') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = clean($conn, $_POST['title'] ?? '');
        $issue = clean($conn, $_POST['issue_number'] ?? '');
        $description = clean($conn, $_POST['description'] ?? '');
        $status = clean($conn, $_POST['status'] ?? 'published');
        $date = clean($conn, $_POST['published_date'] ?? date('Y-m-d'));

        $existing = [
            'pdf_file' => '',
            'file_size' => 0,
            'cover_image' => '',
        ];

        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT pdf_file, file_size, cover_image
                FROM newsletters
                WHERE id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc() ?: $existing;
            $stmt->close();
        }

        $pdf = $existing['pdf_file'];
        $size = (int) $existing['file_size'];
        $cover = $existing['cover_image'];

        if (
            isset($_FILES['pdf_file'])
            && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK
        ) {
            $upload = uploadFile($_FILES['pdf_file'], 'newsletters', ['pdf']);

            if (empty($upload['success'])) {
                setFlashMessage('error', $upload['message']);
                nlRedirect('newsletters.php?tab=archive');
            }

            if ($pdf) {
                deleteFile($pdf, 'newsletters');
            }

            $pdf = $upload['filename'];
            $size = (int) $_FILES['pdf_file']['size'];
        }

        if (
            isset($_FILES['cover_image'])
            && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK
        ) {
            $upload = uploadFile(
                $_FILES['cover_image'],
                'newsletters/covers',
                ['jpg', 'jpeg', 'png', 'webp']
            );

            if (empty($upload['success'])) {
                setFlashMessage('error', $upload['message']);
                nlRedirect('newsletters.php?tab=archive');
            }

            if ($cover) {
                deleteFile($cover, 'newsletters/covers');
            }

            $cover = $upload['filename'];
        }

        if ($id === 0 && !$pdf) {
            setFlashMessage('error', 'Please upload a PDF file.');
            nlRedirect('newsletters.php?tab=archive');
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE newsletters
                SET title = ?, issue_number = ?, description = ?, status = ?,
                    published_date = ?, pdf_file = ?, file_size = ?, cover_image = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'ssssssisi',
                $title,
                $issue,
                $description,
                $status,
                $date,
                $pdf,
                $size,
                $cover,
                $id
            );
        } else {
            $userId = (int) ($_SESSION['user_id'] ?? 0);

            $stmt = $conn->prepare("
                INSERT INTO newsletters
                (title, issue_number, description, pdf_file, file_size, cover_image, status, published_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssssisssi',
                $title,
                $issue,
                $description,
                $pdf,
                $size,
                $cover,
                $status,
                $date,
                $userId
            );
        }

        $stmt->execute();
        $stmt->close();

        setFlashMessage('success', 'Newsletter archive saved.');
        nlRedirect('newsletters.php?tab=archive');
    }
}

/* ==========================================================================
   Page data
   ========================================================================== */

$tab = $_GET['tab'] ?? 'campaigns';
$campaignId = (int) ($_GET['id'] ?? 0);
$campaign = nlGetCampaign($conn, $campaignId);

$campaignsResult = $conn->query("
    SELECT c.*, u.name AS author_name
    FROM newsletter_campaigns c
    LEFT JOIN users u ON u.id = c.created_by
    ORDER BY c.created_at DESC
");
$campaigns = $campaignsResult ? $campaignsResult->fetch_all(MYSQLI_ASSOC) : [];

$subscribersResult = $conn->query("
    SELECT *
    FROM newsletter_subscribers
    ORDER BY subscribed_at DESC
    LIMIT 500
");
$subscribers = $subscribersResult ? $subscribersResult->fetch_all(MYSQLI_ASSOC) : [];

$templatesResult = $conn->query("
    SELECT t.*, u.name AS author_name
    FROM newsletter_templates t
    LEFT JOIN users u ON u.id = t.created_by
    ORDER BY t.updated_at DESC
");
$templates = $templatesResult ? $templatesResult->fetch_all(MYSQLI_ASSOC) : [];

$subscriberCount = adminScalar(
    $conn,
    "SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'"
);
$sentCampaigns = adminScalar(
    $conn,
    "SELECT COUNT(*) FROM newsletter_campaigns WHERE status = 'sent'"
);
$totalSent = adminScalar(
    $conn,
    "SELECT COALESCE(SUM(sent_count), 0) FROM newsletter_campaigns"
);
$drafts = adminScalar(
    $conn,
    "SELECT COUNT(*) FROM newsletter_campaigns WHERE status = 'draft'"
);

$archives = [];
if (adminTableExists($conn, 'newsletters')) {
    $archiveResult = $conn->query("
        SELECT n.*, u.name AS author_name
        FROM newsletters n
        LEFT JOIN users u ON u.id = n.created_by
        ORDER BY n.published_date DESC, n.created_at DESC
    ");
    $archives = $archiveResult ? $archiveResult->fetch_all(MYSQLI_ASSOC) : [];
}

$reportLogs = [];
$reportStats = [
    'total' => 0,
    'sent' => 0,
    'failed' => 0,
];

if ($tab === 'report' && $campaignId > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM newsletter_send_logs
        WHERE campaign_id = ?
        ORDER BY sent_at DESC
        LIMIT 1000
    ");
    $stmt->bind_param('i', $campaignId);
    $stmt->execute();
    $reportLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($reportLogs as $log) {
        $reportStats['total']++;
        if ($log['status'] === 'sent') {
            $reportStats['sent']++;
        } else {
            $reportStats['failed']++;
        }
    }
}

include 'layout/header.php';
include 'layout/sidebar.php';

$flash = getFlashMessage();
?>

<style>
/* ==========================================================================
   Newsletter Builder — grid/column editor
   ========================================================================== */

.nl-builder-shell {
    display: grid;
    grid-template-columns: 245px minmax(0, 1fr) 310px;
    gap: 16px;
    align-items: start;
}

.nl-toolbox,
.nl-properties {
    position: sticky;
    top: 78px;
}

.nl-section-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    margin: 2px 0 9px;
}

.nl-layout-grid,
.nl-tool-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.nl-layout-btn,
.nl-tool {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 9px;
    padding: 9px 7px;
    text-align: center;
    cursor: pointer;
    font-size: 11px;
    color: #475569;
    transition: .15s ease;
}

.nl-layout-btn:hover,
.nl-tool:hover {
    border-color: #f86505;
    color: #f86505;
    background: #fff7ed;
}

.nl-layout-btn i,
.nl-tool i {
    display: block;
    margin-bottom: 5px;
    font-size: 17px;
}

.nl-canvas-wrap {
    background: #dfe6ee;
    border-radius: 13px;
    padding: 18px;
    min-height: 680px;
}

.nl-canvas {
    max-width: 720px;
    margin: 0 auto;
    background: #fff;
    min-height: 620px;
    border-radius: 7px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .12);
    overflow: hidden;
}

.nl-empty {
    min-height: 600px;
    display: grid;
    place-items: center;
    text-align: center;
    color: #94a3b8;
    padding: 35px;
}

.nl-row {
    position: relative;
    display: grid;
    gap: 0;
    border: 2px solid transparent;
    background: #fff;
}

.nl-row:hover,
.nl-row.selected-row {
    border-color: #fdba74;
}

.nl-row-tools {
    position: absolute;
    right: 7px;
    top: 5px;
    z-index: 20;
    display: none;
    gap: 4px;
}

.nl-row:hover .nl-row-tools,
.nl-row.selected-row .nl-row-tools {
    display: flex;
}

.nl-row-tools button,
.nl-block-tools button {
    border: 0;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #0f172a;
    color: #fff;
    cursor: pointer;
    font-size: 11px;
}

.nl-column {
    min-width: 0;
    min-height: 90px;
    border-right: 1px dashed #e2e8f0;
    position: relative;
    background: var(--column-bg, #fff);
}

.nl-column:last-child {
    border-right: 0;
}

.nl-column.is-drop-target {
    outline: 2px dashed #f86505;
    outline-offset: -4px;
    background: #fff7ed;
}

.nl-column-empty {
    min-height: 90px;
    display: grid;
    place-items: center;
    text-align: center;
    color: #94a3b8;
    padding: 14px;
    font-size: 11px;
}

.nl-column-empty i {
    display: block;
    font-size: 17px;
    margin-bottom: 5px;
}

.nl-block {
    position: relative;
    padding: 15px 18px;
    border: 2px solid transparent;
    cursor: pointer;
    min-width: 0;
}

.nl-block:hover,
.nl-block.selected {
    border-color: #f86505;
}

.nl-block-tools {
    position: absolute;
    right: 5px;
    top: 5px;
    z-index: 25;
    display: none;
    gap: 4px;
}

.nl-block:hover .nl-block-tools,
.nl-block.selected .nl-block-tools {
    display: flex;
}

.nl-block img {
    max-width: 100%;
    height: auto;
    display: inline-block;
    border-radius: inherit;
}

.nl-email-button {
    display: inline-block;
    text-decoration: none !important;
    font-weight: 700;
    line-height: 1;
}

.nl-social-icons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.nl-social-icons.center {
    justify-content: center;
}

.nl-social-icons.right {
    justify-content: flex-end;
}

.nl-social-item {
    display: inline-flex;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    align-items: center;
    justify-content: center;
    text-decoration: none !important;
    color: #fff !important;
}

.nl-image-property-preview {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    padding: 8px;
    margin: 8px 0;
    text-align: center;
}

.nl-image-property-preview img {
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
    border-radius: 7px;
}

.nl-device-toggle {
    display: inline-flex;
    gap: 5px;
    padding: 4px;
    background: #e2e8f0;
    border-radius: 8px;
}

.nl-device-toggle button {
    border: 0;
    background: transparent;
    padding: 6px 9px;
    border-radius: 6px;
    cursor: pointer;
    color: #64748b;
}

.nl-device-toggle button.active {
    background: #fff;
    color: #f86505;
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}

.nl-preview-width {
    margin: 0 auto;
    transition: max-width .2s ease;
}

.nl-preview-width.mobile {
    max-width: 390px;
}

.nl-preview-width.desktop {
    max-width: 720px;
}

.nl-template-card {
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    padding: 14px;
    background: #fff;
}

.nl-template-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: 14px;
}

.nl-report-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 14px;
    margin-bottom: 18px;
}


.nl-page-surface {
    min-height: 620px;
    padding: 20px 0;
    background-repeat: no-repeat;
    background-position: center center;
    background-size: cover;
}

.nl-page-inner {
    margin: 0 auto;
    overflow: hidden;
    transition: max-width .2s ease, border-radius .2s ease;
}

.nl-background-preview {
    height: 105px;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
    overflow: hidden;
    background-color: #f8fafc;
    background-position: center;
    background-size: cover;
    display: grid;
    place-items: center;
    color: #94a3b8;
    font-size: 11px;
    margin: 7px 0 10px;
}

.nl-section-bg-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 7px;
    border-radius: 999px;
    background: rgba(15,23,42,.72);
    color: #fff;
    font-size: 10px;
}

@media (max-width: 1180px) {
    .nl-builder-shell {
        grid-template-columns: 210px minmax(0,1fr);
    }

    .nl-properties {
        grid-column: 1 / -1;
        position: static;
    }
}

@media (max-width: 800px) {
    .nl-builder-shell {
        grid-template-columns: 1fr;
    }

    .nl-toolbox,
    .nl-properties {
        position: static;
    }

    .nl-layout-grid,
    .nl-tool-grid {
        grid-template-columns: repeat(4,minmax(0,1fr));
    }

    .nl-canvas-wrap {
        padding: 9px;
    }

    .nl-template-grid,
    .nl-report-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .nl-layout-grid,
    .nl-tool-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

    .nl-block {
        padding: 12px;
    }
}
</style>

<div class="content-header">
    <div>
        <h2><i class="fas fa-envelope-open-text"></i> Newsletters</h2>
        <p class="page-subtitle">
            Build responsive email campaigns with rows, columns, reusable templates, subscribers and delivery reports.
        </p>
    </div>

    <?php if ($tab !== 'builder'): ?>
        <a class="btn btn-primary" href="newsletters.php?tab=builder">
            <i class="fas fa-wand-magic-sparkles"></i>
            Design Newsletter
        </a>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
        <i class="fas fa-circle-info"></i>
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card stat-card--blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $subscriberCount) ?></div>
            <div class="stat-label">Active Subscribers</div>
        </div>
    </div>

    <div class="stat-card stat-card--green">
        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $sentCampaigns) ?></div>
            <div class="stat-label">Campaigns Sent</div>
        </div>
    </div>

    <div class="stat-card stat-card--amber">
        <div class="stat-icon"><i class="fas fa-envelope-circle-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $totalSent) ?></div>
            <div class="stat-label">Emails Sent</div>
        </div>
    </div>

    <div class="stat-card stat-card--purple">
        <div class="stat-icon"><i class="fas fa-pen-ruler"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format((int) $drafts) ?></div>
            <div class="stat-label">Draft Campaigns</div>
        </div>
    </div>
</div>

<div class="nl-tabs">
    <a class="nl-tab <?= $tab === 'campaigns' ? 'active' : '' ?>" href="?tab=campaigns">
        <i class="fas fa-layer-group"></i> Campaigns
    </a>

    <a class="nl-tab <?= $tab === 'builder' ? 'active' : '' ?>"
       href="?tab=builder<?= $campaignId ? '&id=' . $campaignId : '' ?>">
        <i class="fas fa-wand-magic-sparkles"></i> Designer
    </a>

    <a class="nl-tab <?= $tab === 'templates' ? 'active' : '' ?>" href="?tab=templates">
        <i class="fas fa-table-cells-large"></i> Templates
    </a>

    <a class="nl-tab <?= $tab === 'subscribers' ? 'active' : '' ?>" href="?tab=subscribers">
        <i class="fas fa-users"></i> Audience
    </a>

    <a class="nl-tab <?= $tab === 'archive' ? 'active' : '' ?>" href="?tab=archive">
        <i class="fas fa-file-pdf"></i> PDF Archive
    </a>
</div>

<?php if ($tab === 'campaigns'): ?>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Sent</th>
                    <th>Failed</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$campaigns): ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-envelope-open-text"></i><br>
                        No campaigns yet. Design your first newsletter.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($campaigns as $item): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                        <small><?= htmlspecialchars($item['author_name'] ?? '') ?></small>
                    </td>
                    <td><?= htmlspecialchars($item['subject']) ?></td>
                    <td>
                        <span class="badge <?= $item['status'] === 'sent' ? 'badge-success' : 'badge-warning' ?>">
                            <?= htmlspecialchars(ucfirst($item['status'])) ?>
                        </span>
                    </td>
                    <td><?= number_format((int) $item['recipient_count']) ?></td>
                    <td><?= number_format((int) $item['sent_count']) ?></td>
                    <td><?= number_format((int) $item['failed_count']) ?></td>
                    <td><?= htmlspecialchars(date('d M Y, g:i A', strtotime($item['created_at']))) ?></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <a class="btn btn-sm btn-primary"
                               href="?tab=builder&id=<?= (int) $item['id'] ?>"
                               title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>

                            <a class="btn btn-sm"
                               href="?tab=report&id=<?= (int) $item['id'] ?>"
                               title="Report">
                                <i class="fas fa-chart-column"></i>
                            </a>

                            <form method="post">
                                <input type="hidden" name="action" value="duplicate_campaign">
                                <input type="hidden" name="campaign_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-sm" title="Duplicate">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </form>

                            <form method="post" onsubmit="return confirm('Delete this campaign?')">
                                <input type="hidden" name="action" value="delete_campaign">
                                <input type="hidden" name="campaign_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-sm btn-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'builder'):
    $layout = [];
    $pageDesign = [
        'bgColor' => '#f1f5f9',
        'bgImage' => '',
        'bgSize' => 'cover',
        'bgPosition' => 'center center',
        'contentBg' => '#ffffff',
        'contentWidth' => 720,
        'contentRadius' => 0,
    ];

    if ($campaign && !empty($campaign['builder_json'])) {
        $decoded = json_decode($campaign['builder_json'], true);

        if (is_array($decoded)) {
            /*
             * New format:
             * {
             *   "page": {...},
             *   "rows": [...]
             * }
             */
            if (isset($decoded['rows']) && is_array($decoded['rows'])) {
                $layout = $decoded['rows'];

                if (isset($decoded['page']) && is_array($decoded['page'])) {
                    $pageDesign = array_merge($pageDesign, $decoded['page']);
                }
            }
            /*
             * Legacy flat block array.
             */
            elseif (isset($decoded[0]['type'])) {
                $layout = [[
                    'id' => 'legacy_' . time(),
                    'bg' => '#ffffff',
                    'bgImage' => '',
                    'bgSize' => 'cover',
                    'bgPosition' => 'center center',
                    'padding' => 0,
                    'columns' => [[
                        'id' => 'legacy_col',
                        'width' => 100,
                        'bg' => '#ffffff',
                        'blocks' => $decoded,
                    ]],
                ]];
            }
            /*
             * Previous row-array format.
             */
            else {
                $layout = $decoded;
            }
        }
    }
?>

<form method="post" id="campaignForm">
    <input type="hidden" name="action" value="save_campaign">
    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
    <input type="hidden" name="builder_json" id="builderJson">
    <input type="hidden" name="body_html" id="bodyHtml">

    <div class="card" style="margin-bottom:16px">
        <div class="form-row">
            <div class="form-group col-4">
                <label>Campaign Name *</label>
                <input
                    name="campaign_name"
                    required
                    value="<?= htmlspecialchars($campaign['name'] ?? '') ?>"
                    placeholder="August Community Update"
                >
            </div>

            <div class="form-group col-4">
                <label>Email Subject *</label>
                <input
                    name="subject"
                    required
                    value="<?= htmlspecialchars($campaign['subject'] ?? '') ?>"
                    placeholder="What is happening at WITU"
                >
            </div>

            <div class="form-group col-4">
                <label>Preheader</label>
                <input
                    name="preheader"
                    value="<?= htmlspecialchars($campaign['preheader'] ?? '') ?>"
                    placeholder="Short inbox preview text"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-4">
                <label>From Name</label>
                <input
                    name="from_name"
                    value="<?= htmlspecialchars($campaign['from_name'] ?? 'WITU') ?>"
                    placeholder="WITU"
                >
            </div>

            <div class="form-group col-4">
                <label>Reply-to Email</label>
                <input
                    type="email"
                    name="reply_to"
                    value="<?= htmlspecialchars($campaign['reply_to'] ?? '') ?>"
                    placeholder="info@witu.org"
                >
            </div>

            <div class="form-group col-4">
                <label>Campaign Tags</label>
                <input
                    name="campaign_tags"
                    value="<?= htmlspecialchars($campaign['campaign_tags'] ?? '') ?>"
                    placeholder="community, august, partners"
                >
            </div>
        </div>
    </div>

    <div class="nl-builder-shell">

        <div class="card nl-toolbox">
            <button type="button" class="btn btn-sm" id="editPageDesignBtn" style="width:100%;margin-bottom:12px;text-align:left">
                <i class="fas fa-palette"></i>
                Page Background & Design
            </button>

            <div class="nl-section-title">
                <i class="fas fa-table-columns"></i>
                Row Layouts
            </div>

            <div class="nl-layout-grid">
                <button type="button" class="nl-layout-btn" data-layout="1">
                    <i class="fas fa-square"></i>
                    1 Column
                </button>

                <button type="button" class="nl-layout-btn" data-layout="2">
                    <i class="fas fa-table-columns"></i>
                    2 Columns
                </button>

                <button type="button" class="nl-layout-btn" data-layout="3">
                    <i class="fas fa-table-cells"></i>
                    3 Columns
                </button>

                <button type="button" class="nl-layout-btn" data-layout="4">
                    <i class="fas fa-grip"></i>
                    4 Columns
                </button>
            </div>

            <hr style="margin:14px 0;border:0;border-top:1px solid #e5e7eb">

            <div class="nl-section-title">
                <i class="fas fa-shapes"></i>
                Content
            </div>

            <div class="nl-tool-grid">
                <div class="nl-tool" draggable="true" data-type="heading">
                    <i class="fas fa-heading"></i>
                    Heading
                </div>

                <div class="nl-tool" draggable="true" data-type="text">
                    <i class="fas fa-align-left"></i>
                    Text
                </div>

                <div class="nl-tool" draggable="true" data-type="image">
                    <i class="fas fa-image"></i>
                    Image
                </div>

                <div class="nl-tool" draggable="true" data-type="button">
                    <i class="fas fa-square-up-right"></i>
                    Button
                </div>

                <div class="nl-tool" draggable="true" data-type="divider">
                    <i class="fas fa-minus"></i>
                    Divider
                </div>

                <div class="nl-tool" draggable="true" data-type="spacer">
                    <i class="fas fa-arrows-up-down"></i>
                    Spacer
                </div>

                <div class="nl-tool" draggable="true" data-type="social">
                    <i class="fas fa-share-nodes"></i>
                    Social Icons
                </div>

                <div class="nl-tool" draggable="true" data-type="footer">
                    <i class="fas fa-shoe-prints"></i>
                    Footer
                </div>
            </div>

            <p class="page-subtitle" style="margin-top:12px">
                Add a row first, then drag content into any column. You can also select a column and click an item.
            </p>

            <?php if ($templates): ?>
                <hr style="margin:14px 0;border:0;border-top:1px solid #e5e7eb">

                <div class="nl-section-title">
                    <i class="fas fa-bookmark"></i>
                    Saved Templates
                </div>

                <div style="display:grid;gap:7px">
                    <?php foreach ($templates as $template): ?>
                        <button
                            type="button"
                            class="btn btn-sm"
                            style="text-align:left"
                            onclick='loadTemplate(<?= json_encode($template['builder_json']) ?>)'
                        >
                            <i class="fas fa-table-cells-large"></i>
                            <?= htmlspecialchars($template['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:10px;flex-wrap:wrap">
                <div>
                    <strong style="font-size:13px">Email Canvas</strong>
                    <div class="page-subtitle">720px email-safe layout</div>
                </div>

                <div class="nl-device-toggle">
                    <button type="button" class="active" data-builder-device="desktop" title="Desktop preview">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button type="button" data-builder-device="mobile" title="Mobile preview">
                        <i class="fas fa-mobile-screen"></i>
                    </button>
                </div>
            </div>

            <div class="nl-canvas-wrap" id="pageCanvasWrap">
                <div class="nl-preview-width desktop" id="builderPreviewWidth">
                    <div class="nl-page-surface" id="nlPageSurface">
                        <div class="nl-page-inner" id="nlPageInner">
                            <div class="nl-canvas" id="nlCanvas"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="nl-builder-actions">
                <a href="?tab=campaigns" class="btn">
                    Cancel
                </a>

                <button type="button" class="btn" onclick="previewNewsletter()">
                    <i class="fas fa-eye"></i>
                    Preview
                </button>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-floppy-disk"></i>
                    Save Draft
                </button>
            </div>
        </div>

        <div class="card nl-properties">
            <div class="nl-panel-title">
                <i class="fas fa-sliders"></i>
                Settings
            </div>

            <div id="nlProperties" class="nl-prop-empty">
                Select a row, column or content block to edit it.
            </div>
        </div>

    </div>
</form>

<?php if ($campaignId): ?>
    <div class="card" style="margin-top:18px">
        <div class="content-header" style="margin-bottom:14px">
            <div>
                <h3>
                    <i class="fas fa-paper-plane" style="color:#f86505"></i>
                    Test & Send
                </h3>
                <p class="page-subtitle">
                    Send a test first, then choose your audience.
                </p>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-6">
                <form method="post">
                    <input type="hidden" name="action" value="test_campaign">
                    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">

                    <label>Send Test Email</label>
                    <div style="display:flex;gap:7px">
                        <input
                            type="email"
                            name="test_email"
                            required
                            placeholder="your@email.com"
                        >
                        <button class="btn">
                            <i class="fas fa-vial"></i>
                            Test
                        </button>
                    </div>
                </form>
            </div>

            <div class="form-group col-6">
                <form method="post" id="saveTemplateForm">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
                    <input type="hidden" name="builder_json" id="templateBuilderJson">

                    <label>Save as Reusable Template</label>
                    <div style="display:flex;gap:7px">
                        <input
                            type="text"
                            name="template_name"
                            required
                            placeholder="Template name"
                        >
                        <button class="btn">
                            <i class="fas fa-bookmark"></i>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <form
            method="post"
            enctype="multipart/form-data"
            onsubmit="return confirm('Send this newsletter now?')"
        >
            <input type="hidden" name="action" value="send_campaign">
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">

            <div class="nl-recipient-options">
                <label class="recipient-card">
                    <input type="radio" name="recipient_source" value="subscribers" checked>
                    <strong>Subscribers</strong>
                    <small style="display:block;margin-top:5px">
                        <?= number_format((int) $subscriberCount) ?> active subscribers
                    </small>
                </label>

                <label class="recipient-card">
                    <input type="radio" name="recipient_source" value="csv">
                    <strong>Uploaded CSV</strong>
                    <small style="display:block;margin-top:5px">
                        Send only to emails in CSV
                    </small>
                </label>

                <label class="recipient-card">
                    <input type="radio" name="recipient_source" value="both">
                    <strong>Both</strong>
                    <small style="display:block;margin-top:5px">
                        Subscribers + CSV, duplicates removed
                    </small>
                </label>
            </div>

            <div class="form-group" style="margin-top:14px">
                <label>Recipient CSV</label>
                <input type="file" name="recipient_csv" accept=".csv,text/csv">
                <small>
                    Invalid email addresses are skipped automatically.
                </small>
            </div>

            <button class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
                Send Newsletter
            </button>
        </form>
    </div>
<?php endif; ?>

<div id="previewModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Newsletter Preview</h3>

            <div style="display:flex;align-items:center;gap:10px">
                <div class="nl-device-toggle">
                    <button type="button" class="active" data-preview-device="desktop">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button type="button" data-preview-device="mobile">
                        <i class="fas fa-mobile-screen"></i>
                    </button>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    onclick="hideModal('previewModal')"
                >&times;</button>
            </div>
        </div>

        <div style="padding:20px" class="newsletter-preview">
            <div class="nl-preview-width desktop" id="modalPreviewWidth">
                <div class="nl-page-surface" id="modalPageSurface">
                    <div class="nl-page-inner" id="modalPageInner">
                        <div id="previewContent" class="newsletter-preview-inner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const initialLayout = <?= json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const initialPageDesign = <?= json_encode($pageDesign, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

let rows = Array.isArray(initialLayout) ? initialLayout : [];
let pageDesign = {
    bgColor: '#f1f5f9',
    bgImage: '',
    bgSize: 'cover',
    bgPosition: 'center center',
    contentBg: '#ffffff',
    contentWidth: 720,
    contentRadius: 0,
    ...(initialPageDesign || {})
};
let selected = null;
let imagePreviewNonce = Date.now();

const canvas = document.getElementById('nlCanvas');

const blockDefaults = {
    heading: {
        text: 'Your newsletter heading',
        level: 'h2',
        align: 'left',
        color: '#0f172a',
        bg: '#ffffff',
        padding: 16
    },
    text: {
        text: 'Add your newsletter text here. Share an update, story or announcement with your audience.',
        align: 'left',
        color: '#475569',
        bg: '#ffffff',
        padding: 16
    },
    image: {
        src: '',
        alt: 'Newsletter image',
        link: '',
        align: 'center',
        bg: '#ffffff',
        width: 100,
        radius: 0,
        padding: 10
    },
    button: {
        text: 'Learn More',
        url: 'https://witu.org',
        align: 'center',
        color: '#ffffff',
        buttonBg: '#f86505',
        radius: 6,
        paddingX: 20,
        paddingY: 12,
        bg: '#ffffff',
        padding: 16
    },
    divider: {
        color: '#e5e7eb',
        bg: '#ffffff',
        padding: 14
    },
    spacer: {
        height: 30,
        bg: '#ffffff',
        padding: 0
    },
    social: {
        text: 'Follow WITU',
        align: 'center',
        color: '#475569',
        bg: '#ffffff',
        padding: 16,
        facebook: '',
        instagram: '',
        linkedin: '',
        x: '',
        youtube: ''
    },
    footer: {
        text: 'You are receiving this email because you subscribed to WITU updates.',
        align: 'center',
        color: '#64748b',
        bg: '#f8fafc',
        padding: 18
    }
};

function uid(prefix = 'id') {
    return prefix + '_' + Date.now() + '_' + Math.random().toString(16).slice(2);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"]/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
    }[char]));
}

function absoluteUrl(url) {
    const value = String(url || '').trim();

    if (!value) {
        return '';
    }

    try {
        return new URL(value, window.location.href).href;
    } catch (error) {
        return value;
    }
}

function previewImageUrl(url) {
    const value = String(url || '').trim();

    if (!value) {
        return '';
    }

    const separator = value.includes('?') ? '&' : '?';
    return value + separator + 'preview=' + imagePreviewNonce;
}

function socialIconSource(platform) {
    const icons = {
        facebook: 'https://cdn.simpleicons.org/facebook/1877F2',
        instagram: 'https://cdn.simpleicons.org/instagram/E4405F',
        linkedin: 'https://cdn.simpleicons.org/linkedin/0A66C2',
        x: 'https://cdn.simpleicons.org/x/000000',
        youtube: 'https://cdn.simpleicons.org/youtube/FF0000'
    };

    return icons[platform] || '';
}

function socialFallbackClass(platform) {
    const classes = {
        facebook: 'fab fa-facebook-f',
        instagram: 'fab fa-instagram',
        linkedin: 'fab fa-linkedin-in',
        x: 'fab fa-x-twitter',
        youtube: 'fab fa-youtube'
    };

    return classes[platform] || 'fas fa-link';
}

function addRow(columnCount) {
    const count = Math.max(1, Math.min(4, parseInt(columnCount, 10) || 1));
    const width = Math.floor(100 / count);

    const row = {
        id: uid('row'),
        bg: '#ffffff',
        bgImage: '',
        bgSize: 'cover',
        bgPosition: 'center center',
        padding: 0,
        columns: []
    };

    for (let i = 0; i < count; i++) {
        row.columns.push({
            id: uid('col'),
            width: i === count - 1 ? 100 - (width * (count - 1)) : width,
            bg: '#ffffff',
            blocks: []
        });
    }

    rows.push(row);
    selected = {
        type: 'column',
        row: rows.length - 1,
        column: 0
    };

    render();
}

document.querySelectorAll('.nl-layout-btn').forEach(button => {
    button.addEventListener('click', () => addRow(button.dataset.layout));
});

document.querySelectorAll('.nl-tool').forEach(tool => {
    tool.addEventListener('dragstart', event => {
        event.dataTransfer.setData('application/x-newsletter-block', tool.dataset.type);
        event.dataTransfer.effectAllowed = 'copy';
    });

    tool.addEventListener('click', () => {
        addBlockToSelectedColumn(tool.dataset.type);
    });
});

function addBlockToSelectedColumn(type) {
    if (!blockDefaults[type]) {
        return;
    }

    let target = selected;

    if (!target || !['column', 'block'].includes(target.type)) {
        if (!rows.length) {
            addRow(1);
        }

        target = {
            type: 'column',
            row: rows.length - 1,
            column: 0
        };
    }

    const rowIndex = target.row;
    const columnIndex = target.column;

    if (!rows[rowIndex] || !rows[rowIndex].columns[columnIndex]) {
        return;
    }

    const block = {
        id: uid('block'),
        type,
        ...JSON.parse(JSON.stringify(blockDefaults[type]))
    };

    rows[rowIndex].columns[columnIndex].blocks.push(block);

    selected = {
        type: 'block',
        row: rowIndex,
        column: columnIndex,
        block: rows[rowIndex].columns[columnIndex].blocks.length - 1
    };

    render();
}

function blockContent(block, emailMode = false) {
    const align = ['left', 'center', 'right'].includes(block.align)
        ? block.align
        : 'left';

    if (block.type === 'heading') {
        const level = ['h1', 'h2', 'h3'].includes(block.level) ? block.level : 'h2';
        const sizes = {
            h1: 30,
            h2: 24,
            h3: 19
        };

        return `<${level} style="margin:0;text-align:${align};color:${escapeHtml(block.color)};font-size:${sizes[level]}px;line-height:1.25;font-family:Arial,Helvetica,sans-serif;">${escapeHtml(block.text)}</${level}>`;
    }

    if (block.type === 'text') {
        return `<div style="text-align:${align};color:${escapeHtml(block.color)};font-size:15px;line-height:1.7;font-family:Arial,Helvetica,sans-serif;">${escapeHtml(block.text).replace(/\n/g, '<br>')}</div>`;
    }

    if (block.type === 'image') {
        if (!block.src) {
            return `<div style="padding:24px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;color:#94a3b8;border-radius:7px;">
                <i class="fas fa-image"></i><br>
                <span style="font-size:12px">Upload or paste an image URL</span>
            </div>`;
        }

        const rawSrc = emailMode
            ? absoluteUrl(block.src)
            : previewImageUrl(block.src);

        const width = Math.max(10, Math.min(100, parseInt(block.width, 10) || 100));
        const radius = Math.max(0, Math.min(40, parseInt(block.radius, 10) || 0));

        let image = `<img src="${escapeHtml(rawSrc)}"
            alt="${escapeHtml(block.alt)}"
            style="display:inline-block;width:${width}%;max-width:100%;height:auto;border:0;border-radius:${radius}px;">`;

        if (block.link) {
            image = `<a href="${escapeHtml(block.link)}" target="_blank" style="text-decoration:none">${image}</a>`;
        }

        return `<div style="text-align:${align};line-height:0">${image}</div>`;
    }

    if (block.type === 'button') {
        const radius = Math.max(0, Math.min(40, parseInt(block.radius, 10) || 0));
        const paddingX = Math.max(8, Math.min(50, parseInt(block.paddingX, 10) || 20));
        const paddingY = Math.max(6, Math.min(30, parseInt(block.paddingY, 10) || 12));

        return `<div style="text-align:${align}">
            <a href="${escapeHtml(block.url)}"
               target="_blank"
               class="nl-email-button"
               style="display:inline-block;background:${escapeHtml(block.buttonBg)};color:${escapeHtml(block.color)};padding:${paddingY}px ${paddingX}px;border-radius:${radius}px;text-decoration:none;font-size:14px;font-weight:700;font-family:Arial,Helvetica,sans-serif;">
                ${escapeHtml(block.text)}
            </a>
        </div>`;
    }

    if (block.type === 'divider') {
        return `<hr style="border:0;border-top:1px solid ${escapeHtml(block.color)};margin:0;">`;
    }

    if (block.type === 'spacer') {
        const height = Math.max(5, Math.min(150, parseInt(block.height, 10) || 30));
        return `<div style="height:${height}px;line-height:${height}px;font-size:1px">&nbsp;</div>`;
    }

    if (block.type === 'social') {
        const platforms = ['facebook', 'instagram', 'linkedin', 'x', 'youtube'];

        const available = platforms.filter(platform => String(block[platform] || '').trim() !== '');

        let icons = '';

        if (available.length) {
            icons = available.map(platform => {
                const link = block[platform];
                const icon = socialIconSource(platform);

                if (emailMode) {
                    return `<a href="${escapeHtml(link)}"
                        target="_blank"
                        style="display:inline-block;text-decoration:none;margin:0 4px;">
                        <img src="${escapeHtml(icon)}"
                             width="28"
                             height="28"
                             alt="${escapeHtml(platform)}"
                             style="display:block;width:28px;height:28px;border:0;">
                    </a>`;
                }

                return `<a href="${escapeHtml(link)}"
                    target="_blank"
                    class="nl-social-item"
                    style="background:#0f172a"
                    title="${escapeHtml(platform)}">
                    <i class="${socialFallbackClass(platform)}"></i>
                </a>`;
            }).join('');
        } else {
            icons = `<span style="font-size:12px;color:#94a3b8">Add social links in Settings</span>`;
        }

        const justify = align === 'center'
            ? 'center'
            : (align === 'right' ? 'flex-end' : 'flex-start');

        return `
            <div style="text-align:${align};color:${escapeHtml(block.color)}">
                ${block.text ? `<div style="font-size:13px;font-weight:700;margin-bottom:10px">${escapeHtml(block.text)}</div>` : ''}
                <div class="nl-social-icons ${align}" style="display:flex;gap:7px;justify-content:${justify};flex-wrap:wrap">
                    ${icons}
                </div>
            </div>
        `;
    }

    if (block.type === 'footer') {
        return `<div style="text-align:${align};color:${escapeHtml(block.color)};font-size:11px;line-height:1.6;font-family:Arial,Helvetica,sans-serif;">
            ${escapeHtml(block.text).replace(/\n/g, '<br>')}
        </div>`;
    }

    return '';
}

function blockHtml(block, rowIndex, columnIndex, blockIndex, interactive = true, emailMode = false) {
    const padding = Math.max(0, Math.min(50, parseInt(block.padding, 10) || 0));
    const isSelected = interactive
        && selected
        && selected.type === 'block'
        && selected.row === rowIndex
        && selected.column === columnIndex
        && selected.block === blockIndex;

    const tools = interactive
        ? `<div class="nl-block-tools">
            <button type="button" onclick="event.stopPropagation();moveBlock(${rowIndex},${columnIndex},${blockIndex},-1)" title="Move up"><i class="fas fa-arrow-up"></i></button>
            <button type="button" onclick="event.stopPropagation();moveBlock(${rowIndex},${columnIndex},${blockIndex},1)" title="Move down"><i class="fas fa-arrow-down"></i></button>
            <button type="button" onclick="event.stopPropagation();duplicateBlock(${rowIndex},${columnIndex},${blockIndex})" title="Duplicate"><i class="fas fa-copy"></i></button>
            <button type="button" onclick="event.stopPropagation();removeBlock(${rowIndex},${columnIndex},${blockIndex})" title="Delete"><i class="fas fa-trash"></i></button>
        </div>`
        : '';

    const click = interactive
        ? `onclick="selectBlock(${rowIndex},${columnIndex},${blockIndex})"`
        : '';

    return `<div class="nl-block ${isSelected ? 'selected' : ''}"
        style="background:${escapeHtml(block.bg || '#ffffff')};padding:${padding}px;"
        ${click}>
        ${tools}
        ${blockContent(block, emailMode)}
    </div>`;
}

function rowHtml(row, rowIndex, interactive = true, emailMode = false) {
    const columns = Array.isArray(row.columns) ? row.columns : [];

    if (!columns.length) {
        return '';
    }

    if (emailMode) {
        const cellWidth = Math.floor(100 / columns.length);

        const cells = columns.map((column, columnIndex) => {
            const blocks = (column.blocks || [])
                .map((block, blockIndex) =>
                    blockHtml(block, rowIndex, columnIndex, blockIndex, false, true)
                )
                .join('');

            return `<td width="${cellWidth}%"
                valign="top"
                style="width:${cellWidth}%;vertical-align:top;background:${escapeHtml(column.bg || '#ffffff')};">
                ${blocks || '&nbsp;'}
            </td>`;
        }).join('');

        const rowBgImage = row.bgImage ? absoluteUrl(row.bgImage) : '';
        const backgroundAttr = rowBgImage ? ` background="${escapeHtml(rowBgImage)}"` : '';
        const rowBackgroundCss = rowBgImage
            ? `background-image:url('${escapeHtml(rowBgImage)}');background-size:${escapeHtml(row.bgSize || 'cover')};background-position:${escapeHtml(row.bgPosition || 'center center')};background-repeat:no-repeat;`
            : '';

        return `<table role="presentation"
            width="100%"
            cellspacing="0"
            cellpadding="0"
            border="0"
            ${backgroundAttr}
            style="width:100%;border-collapse:collapse;background-color:${escapeHtml(row.bg || '#ffffff')};${rowBackgroundCss}">
            <tr>${cells}</tr>
        </table>`;
    }

    const template = columns
        .map(column => `${Math.max(1, parseInt(column.width, 10) || 1)}fr`)
        .join(' ');

    const isSelectedRow = selected
        && selected.type === 'row'
        && selected.row === rowIndex;

    const tools = interactive
        ? `<div class="nl-row-tools">
            <button type="button" onclick="event.stopPropagation();moveRow(${rowIndex},-1)" title="Move row up"><i class="fas fa-arrow-up"></i></button>
            <button type="button" onclick="event.stopPropagation();moveRow(${rowIndex},1)" title="Move row down"><i class="fas fa-arrow-down"></i></button>
            <button type="button" onclick="event.stopPropagation();duplicateRow(${rowIndex})" title="Duplicate row"><i class="fas fa-copy"></i></button>
            <button type="button" onclick="event.stopPropagation();removeRow(${rowIndex})" title="Delete row"><i class="fas fa-trash"></i></button>
        </div>`
        : '';

    const columnsHtml = columns.map((column, columnIndex) => {
        const blocks = (column.blocks || [])
            .map((block, blockIndex) =>
                blockHtml(block, rowIndex, columnIndex, blockIndex, true, false)
            )
            .join('');

        const isSelectedColumn = selected
            && selected.type === 'column'
            && selected.row === rowIndex
            && selected.column === columnIndex;

        return `<div class="nl-column ${isSelectedColumn ? 'selected' : ''}"
            data-row="${rowIndex}"
            data-column="${columnIndex}"
            style="--column-bg:${escapeHtml(column.bg || '#ffffff')}"
            onclick="event.stopPropagation();selectColumn(${rowIndex},${columnIndex})"
            ondragover="columnDragOver(event)"
            ondragleave="columnDragLeave(event)"
            ondrop="columnDrop(event,${rowIndex},${columnIndex})">
            ${blocks || `<div class="nl-column-empty">
                <div>
                    <i class="fas fa-plus-circle"></i>
                    Drop content here
                </div>
            </div>`}
        </div>`;
    }).join('');

    const visualBgImage = row.bgImage ? previewImageUrl(row.bgImage) : '';
    const visualBgCss = visualBgImage
        ? `background-image:url('${escapeHtml(visualBgImage)}');background-size:${escapeHtml(row.bgSize || 'cover')};background-position:${escapeHtml(row.bgPosition || 'center center')};background-repeat:no-repeat;`
        : '';

    return `<div class="nl-row ${isSelectedRow ? 'selected-row' : ''}"
        style="grid-template-columns:${template};background-color:${escapeHtml(row.bg || '#ffffff')};${visualBgCss}padding:${Math.max(0, parseInt(row.padding, 10) || 0)}px;"
        onclick="selectRow(${rowIndex})">
        ${tools}
        ${columnsHtml}
    </div>`;
}

function render() {
    canvas.innerHTML = rows.length
        ? rows.map((row, rowIndex) => rowHtml(row, rowIndex, true, false)).join('')
        : `<div class="nl-empty">
            <div>
                <i class="fas fa-table-columns" style="font-size:40px;margin-bottom:10px"></i>
                <h3 style="margin-bottom:5px">Start with a row layout</h3>
                <p>Choose 1, 2, 3 or 4 columns from the left panel.</p>
            </div>
        </div>`;

    applyPageDesignPreview();
    renderProperties();
    syncBuilder();
}

function applyPageDesignPreview() {
    const surface = document.getElementById('nlPageSurface');
    const inner = document.getElementById('nlPageInner');

    if (surface) {
        surface.style.backgroundColor = pageDesign.bgColor || '#f1f5f9';
        surface.style.backgroundImage = pageDesign.bgImage
            ? `url("${previewImageUrl(pageDesign.bgImage)}")`
            : 'none';
        surface.style.backgroundSize = pageDesign.bgSize || 'cover';
        surface.style.backgroundPosition = pageDesign.bgPosition || 'center center';
        surface.style.backgroundRepeat = 'no-repeat';
    }

    if (inner) {
        inner.style.maxWidth = `${Math.max(320, Math.min(900, parseInt(pageDesign.contentWidth, 10) || 720))}px`;
        inner.style.backgroundColor = pageDesign.contentBg || '#ffffff';
        inner.style.borderRadius = `${Math.max(0, Math.min(40, parseInt(pageDesign.contentRadius, 10) || 0))}px`;
    }
}

function selectRow(rowIndex) {
    selected = {
        type: 'row',
        row: rowIndex
    };

    render();
}

function selectColumn(rowIndex, columnIndex) {
    selected = {
        type: 'column',
        row: rowIndex,
        column: columnIndex
    };

    render();
}

function selectBlock(rowIndex, columnIndex, blockIndex) {
    selected = {
        type: 'block',
        row: rowIndex,
        column: columnIndex,
        block: blockIndex
    };

    render();
}

function columnDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('is-drop-target');
}

function columnDragLeave(event) {
    event.currentTarget.classList.remove('is-drop-target');
}

function columnDrop(event, rowIndex, columnIndex) {
    event.preventDefault();
    event.currentTarget.classList.remove('is-drop-target');

    const type = event.dataTransfer.getData('application/x-newsletter-block');

    if (!blockDefaults[type]) {
        return;
    }

    selected = {
        type: 'column',
        row: rowIndex,
        column: columnIndex
    };

    addBlockToSelectedColumn(type);
}

function removeRow(rowIndex) {
    rows.splice(rowIndex, 1);
    selected = null;
    render();
}

function duplicateRow(rowIndex) {
    const copy = JSON.parse(JSON.stringify(rows[rowIndex]));

    copy.id = uid('row');
    copy.columns.forEach(column => {
        column.id = uid('col');
        (column.blocks || []).forEach(block => {
            block.id = uid('block');
        });
    });

    rows.splice(rowIndex + 1, 0, copy);

    selected = {
        type: 'row',
        row: rowIndex + 1
    };

    render();
}

function moveRow(rowIndex, direction) {
    const target = rowIndex + direction;

    if (target < 0 || target >= rows.length) {
        return;
    }

    [rows[rowIndex], rows[target]] = [rows[target], rows[rowIndex]];

    selected = {
        type: 'row',
        row: target
    };

    render();
}

function removeBlock(rowIndex, columnIndex, blockIndex) {
    rows[rowIndex].columns[columnIndex].blocks.splice(blockIndex, 1);
    selected = {
        type: 'column',
        row: rowIndex,
        column: columnIndex
    };
    render();
}

function duplicateBlock(rowIndex, columnIndex, blockIndex) {
    const blocks = rows[rowIndex].columns[columnIndex].blocks;
    const copy = JSON.parse(JSON.stringify(blocks[blockIndex]));
    copy.id = uid('block');

    blocks.splice(blockIndex + 1, 0, copy);

    selected = {
        type: 'block',
        row: rowIndex,
        column: columnIndex,
        block: blockIndex + 1
    };

    render();
}

function moveBlock(rowIndex, columnIndex, blockIndex, direction) {
    const blocks = rows[rowIndex].columns[columnIndex].blocks;
    const target = blockIndex + direction;

    if (target < 0 || target >= blocks.length) {
        return;
    }

    [blocks[blockIndex], blocks[target]] = [blocks[target], blocks[blockIndex]];

    selected = {
        type: 'block',
        row: rowIndex,
        column: columnIndex,
        block: target
    };

    render();
}

function currentSelectionObject() {
    if (!selected) {
        return null;
    }

    if (selected.type === 'page') {
        return pageDesign;
    }

    const row = rows[selected.row];

    if (!row) {
        return null;
    }

    if (selected.type === 'row') {
        return row;
    }

    const column = row.columns[selected.column];

    if (!column) {
        return null;
    }

    if (selected.type === 'column') {
        return column;
    }

    return column.blocks[selected.block] || null;
}

function inputField(label, key, type = 'text', options = {}) {
    const object = currentSelectionObject();
    const value = object?.[key] ?? '';

    if (type === 'textarea') {
        return `<div class="form-group">
            <label>${label}</label>
            <textarea rows="${options.rows || 4}" data-setting="${key}">${escapeHtml(value)}</textarea>
        </div>`;
    }

    if (type === 'select') {
        const optionHtml = (options.values || [])
            .map(item => `<option value="${escapeHtml(item.value)}" ${String(value) === String(item.value) ? 'selected' : ''}>${escapeHtml(item.label)}</option>`)
            .join('');

        return `<div class="form-group">
            <label>${label}</label>
            <select data-setting="${key}">${optionHtml}</select>
        </div>`;
    }

    const attrs = [];

    if (options.min !== undefined) attrs.push(`min="${options.min}"`);
    if (options.max !== undefined) attrs.push(`max="${options.max}"`);
    if (options.step !== undefined) attrs.push(`step="${options.step}"`);

    return `<div class="form-group">
        <label>${label}</label>
        <input type="${type}" data-setting="${key}" value="${escapeHtml(value)}" ${attrs.join(' ')}>
    </div>`;
}

function renderProperties() {
    const properties = document.getElementById('nlProperties');

    if (!selected) {
        properties.className = 'nl-prop-empty';
        properties.innerHTML = 'Select a row, column or content block to edit it.';
        return;
    }

    const object = currentSelectionObject();

    if (!object) {
        selected = null;
        properties.className = 'nl-prop-empty';
        properties.innerHTML = 'Select a row, column or content block to edit it.';
        return;
    }

    properties.className = '';

    let html = '';

    if (selected.type === 'page') {
        html += `<div class="nl-section-title">Page Design</div>`;
        html += inputField('Page Background Colour', 'bgColor', 'color');

        const pageBgPreview = pageDesign.bgImage
            ? `<div class="nl-background-preview" style="background-image:url('${escapeHtml(previewImageUrl(pageDesign.bgImage))}')"></div>`
            : `<div class="nl-background-preview">No page background image</div>`;

        html += `<div class="form-group">
            <label>Page Background Image</label>
            <input id="pageBackgroundInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
            <small>Upload a background image for the full newsletter page.</small>
        </div>`;
        html += pageBgPreview;
        html += inputField('Background Image URL', 'bgImage', 'url');
        html += inputField('Background Size', 'bgSize', 'select', {
            values: [
                {value: 'cover', label: 'Cover'},
                {value: 'contain', label: 'Contain'},
                {value: 'auto', label: 'Original size'}
            ]
        });
        html += inputField('Background Position', 'bgPosition', 'select', {
            values: [
                {value: 'center center', label: 'Centre'},
                {value: 'center top', label: 'Top Centre'},
                {value: 'center bottom', label: 'Bottom Centre'},
                {value: 'left top', label: 'Top Left'},
                {value: 'right top', label: 'Top Right'}
            ]
        });
        html += inputField('Content Background', 'contentBg', 'color');
        html += inputField('Content Width (px)', 'contentWidth', 'number', {min: 320, max: 900});
        html += inputField('Content Corner Radius', 'contentRadius', 'number', {min: 0, max: 40});
    }

    if (selected.type === 'row') {
        html += `<div class="nl-section-title">Section / Row</div>`;
        html += inputField('Section Background Colour', 'bg', 'color');

        const rowBgPreview = object.bgImage
            ? `<div class="nl-background-preview" style="background-image:url('${escapeHtml(previewImageUrl(object.bgImage))}')"></div>`
            : `<div class="nl-background-preview">No section background image</div>`;

        html += `<div class="form-group">
            <label>Section Background Image</label>
            <input id="rowBackgroundInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
            <small>Applies to this section only.</small>
        </div>`;
        html += rowBgPreview;
        html += inputField('Background Image URL', 'bgImage', 'url');
        html += inputField('Background Size', 'bgSize', 'select', {
            values: [
                {value: 'cover', label: 'Cover'},
                {value: 'contain', label: 'Contain'},
                {value: 'auto', label: 'Original size'}
            ]
        });
        html += inputField('Background Position', 'bgPosition', 'select', {
            values: [
                {value: 'center center', label: 'Centre'},
                {value: 'center top', label: 'Top Centre'},
                {value: 'center bottom', label: 'Bottom Centre'},
                {value: 'left top', label: 'Top Left'},
                {value: 'right top', label: 'Top Right'}
            ]
        });
        html += inputField('Row Padding', 'padding', 'number', {min: 0, max: 50});

        html += `<div class="form-group">
            <label>Columns</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                ${[1,2,3,4].map(count => `
                    <button type="button"
                        class="btn btn-sm"
                        onclick="changeRowColumns(${selected.row},${count})">
                        ${count}
                    </button>
                `).join('')}
            </div>
        </div>`;
    }

    if (selected.type === 'column') {
        html += `<div class="nl-section-title">Column</div>`;
        html += inputField('Column Background', 'bg', 'color');

        html += `<div class="form-group">
            <label>Column Width (%)</label>
            <input
                type="number"
                min="10"
                max="100"
                value="${escapeHtml(object.width || 100)}"
                onchange="setColumnWidth(${selected.row},${selected.column},this.value)"
            >
            <small>Other columns are automatically balanced.</small>
        </div>`;
    }

    if (selected.type === 'block') {
        const block = object;

        html += `<div class="nl-section-title">${escapeHtml(block.type)} block</div>`;

        if (['heading', 'text', 'social', 'footer'].includes(block.type)) {
            html += inputField('Text', 'text', 'textarea');
        }

        if (block.type === 'heading') {
            html += inputField('Heading Size', 'level', 'select', {
                values: [
                    {value: 'h1', label: 'Large'},
                    {value: 'h2', label: 'Medium'},
                    {value: 'h3', label: 'Small'}
                ]
            });
        }

        if (['heading', 'text', 'image', 'button', 'social', 'footer'].includes(block.type)) {
            html += inputField('Alignment', 'align', 'select', {
                values: [
                    {value: 'left', label: 'Left'},
                    {value: 'center', label: 'Centre'},
                    {value: 'right', label: 'Right'}
                ]
            });
        }

        if (['heading', 'text', 'social', 'footer'].includes(block.type)) {
            html += inputField('Text Colour', 'color', 'color');
        }

        if (block.type === 'image') {
            const preview = block.src
                ? `<div class="nl-image-property-preview">
                    <img src="${escapeHtml(previewImageUrl(block.src))}"
                         alt="${escapeHtml(block.alt || 'Image preview')}"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <div style="display:none;color:#dc2626;font-size:12px;padding:15px">
                        Image could not be previewed. Check the URL or upload again.
                    </div>
                </div>`
                : `<div class="nl-image-property-preview" style="color:#94a3b8;font-size:12px">
                    No image selected
                </div>`;

            html += `<div class="form-group">
                <label>Upload Image</label>
                <input id="builderImageInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                <small>JPG, PNG, GIF or WebP. Preview refreshes immediately after upload.</small>
            </div>`;

            html += preview;

            html += inputField('Image URL', 'src', 'url');
            html += inputField('Alt Text', 'alt');
            html += inputField('Click Link', 'link', 'url');
            html += inputField('Image Width (%)', 'width', 'number', {min: 10, max: 100});
            html += inputField('Corner Radius', 'radius', 'number', {min: 0, max: 40});
        }

        if (block.type === 'button') {
            html += inputField('Button Text', 'text');
            html += inputField('Button Link', 'url', 'url');
            html += inputField('Button Colour', 'buttonBg', 'color');
            html += inputField('Button Text Colour', 'color', 'color');
            html += inputField('Corner Radius', 'radius', 'number', {min: 0, max: 40});
            html += inputField('Horizontal Padding', 'paddingX', 'number', {min: 8, max: 50});
            html += inputField('Vertical Padding', 'paddingY', 'number', {min: 6, max: 30});
        }

        if (block.type === 'divider') {
            html += inputField('Line Colour', 'color', 'color');
        }

        if (block.type === 'spacer') {
            html += inputField('Spacer Height', 'height', 'number', {min: 5, max: 150});
        }

        if (block.type === 'social') {
            html += `<hr style="border:0;border-top:1px solid #e5e7eb;margin:12px 0">`;
            html += `<div class="nl-section-title">Social Profiles</div>`;
            html += inputField('Facebook', 'facebook', 'url');
            html += inputField('Instagram', 'instagram', 'url');
            html += inputField('LinkedIn', 'linkedin', 'url');
            html += inputField('X / Twitter', 'x', 'url');
            html += inputField('YouTube', 'youtube', 'url');
        }

        if (block.type !== 'spacer') {
            html += inputField('Background', 'bg', 'color');
            html += inputField('Block Padding', 'padding', 'number', {min: 0, max: 50});
        }
    }

    properties.innerHTML = html;

    properties.querySelectorAll('[data-setting]').forEach(input => {
        const eventName = input.tagName === 'SELECT' || input.type === 'color'
            ? 'change'
            : 'input';

        input.addEventListener(eventName, () => {
            const target = currentSelectionObject();

            if (!target) {
                return;
            }

            target[input.dataset.setting] = input.value;

            if (
                input.type === 'color'
                || input.tagName === 'SELECT'
                || ['bgImage', 'bgSize', 'bgPosition', 'contentBg', 'contentWidth', 'contentRadius'].includes(input.dataset.setting)
            ) {
                render();
            } else {
                renderCanvasOnly();
                applyPageDesignPreview();
                syncBuilder();
            }
        });
    });

    const imageInput = document.getElementById('builderImageInput');

    if (imageInput) {
        imageInput.addEventListener('change', () => uploadBuilderImage(imageInput));
    }

    const pageBackgroundInput = document.getElementById('pageBackgroundInput');
    if (pageBackgroundInput) {
        pageBackgroundInput.addEventListener('change', () => uploadBackgroundImage(pageBackgroundInput, 'page'));
    }

    const rowBackgroundInput = document.getElementById('rowBackgroundInput');
    if (rowBackgroundInput) {
        rowBackgroundInput.addEventListener('change', () => uploadBackgroundImage(rowBackgroundInput, 'row'));
    }
}

function renderCanvasOnly() {
    canvas.innerHTML = rows.length
        ? rows.map((row, rowIndex) => rowHtml(row, rowIndex, true, false)).join('')
        : '';
}

function changeRowColumns(rowIndex, count) {
    const row = rows[rowIndex];

    if (!row) {
        return;
    }

    const current = row.columns.length;

    if (count > current) {
        for (let i = current; i < count; i++) {
            row.columns.push({
                id: uid('col'),
                width: 100 / count,
                bg: '#ffffff',
                blocks: []
            });
        }
    } else if (count < current) {
        const removed = row.columns.splice(count);

        if (removed.length) {
            const destination = row.columns[count - 1];

            removed.forEach(column => {
                destination.blocks.push(...(column.blocks || []));
            });
        }
    }

    const equal = Math.floor(100 / count);

    row.columns.forEach((column, index) => {
        column.width = index === count - 1
            ? 100 - (equal * (count - 1))
            : equal;
    });

    selected = {
        type: 'row',
        row: rowIndex
    };

    render();
}

function setColumnWidth(rowIndex, columnIndex, value) {
    const row = rows[rowIndex];

    if (!row || row.columns.length < 2) {
        return;
    }

    const target = Math.max(10, Math.min(90, parseInt(value, 10) || 50));
    const remaining = 100 - target;
    const otherCount = row.columns.length - 1;
    const each = remaining / otherCount;

    row.columns.forEach((column, index) => {
        column.width = index === columnIndex ? target : each;
    });

    render();
}

async function uploadBuilderImage(input) {
    if (!selected || selected.type !== 'block') {
        return;
    }

    const block = currentSelectionObject();

    if (!block || block.type !== 'image' || !input.files?.[0]) {
        return;
    }

    const file = input.files[0];

    if (!/^image\//.test(file.type)) {
        alert('Please select an image file.');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);

    input.disabled = true;

    try {
        const response = await fetch('ajax/newsletter-image-upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('The image upload endpoint did not return valid JSON.');
        }

        if (!response.ok || !data.success || !data.url) {
            throw new Error(data.message || 'Image upload failed.');
        }

        block.src = data.url;
        imagePreviewNonce = Date.now();

        render();
    } catch (error) {
        alert(error.message || 'Image upload failed.');
    } finally {
        input.disabled = false;
    }
}

async function uploadBackgroundImage(input, targetType) {
    if (!input.files?.[0]) {
        return;
    }

    const file = input.files[0];

    if (!/^image\//.test(file.type)) {
        alert('Please select an image file.');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);

    input.disabled = true;

    try {
        const response = await fetch('ajax/newsletter-image-upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store'
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('The background image upload did not return valid JSON.');
        }

        if (!response.ok || !data.success || !data.url) {
            throw new Error(data.message || 'Background image upload failed.');
        }

        if (targetType === 'page') {
            pageDesign.bgImage = data.url;
        } else {
            const row = rows[selected?.row];
            if (!row) {
                throw new Error('The selected section could not be found.');
            }
            row.bgImage = data.url;
        }

        imagePreviewNonce = Date.now();
        render();
    } catch (error) {
        alert(error.message || 'Background image upload failed.');
    } finally {
        input.disabled = false;
    }
}

function emailHtml() {
    const rowsHtml = rows
        .map((row, rowIndex) => rowHtml(row, rowIndex, false, true))
        .join('');

    const pageBgImage = pageDesign.bgImage ? absoluteUrl(pageDesign.bgImage) : '';
    const backgroundAttr = pageBgImage ? ` background="${escapeHtml(pageBgImage)}"` : '';
    const backgroundCss = pageBgImage
        ? `background-image:url('${escapeHtml(pageBgImage)}');background-size:${escapeHtml(pageDesign.bgSize || 'cover')};background-position:${escapeHtml(pageDesign.bgPosition || 'center center')};background-repeat:no-repeat;`
        : '';

    const contentWidth = Math.max(320, Math.min(900, parseInt(pageDesign.contentWidth, 10) || 720));
    const contentRadius = Math.max(0, Math.min(40, parseInt(pageDesign.contentRadius, 10) || 0));

    return `<table role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        ${backgroundAttr}
        style="width:100%;background-color:${escapeHtml(pageDesign.bgColor || '#f1f5f9')};${backgroundCss}">
        <tr>
            <td align="center" style="padding:24px 10px">
                <table role="presentation"
                    width="${contentWidth}"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="width:100%;max-width:${contentWidth}px;background:${escapeHtml(pageDesign.contentBg || '#ffffff')};border-collapse:separate;border-radius:${contentRadius}px;overflow:hidden;">
                    <tr>
                        <td style="padding:0">
                            ${rowsHtml}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>`;
}

function syncBuilder() {
    const builderJson = document.getElementById('builderJson');
    const bodyHtml = document.getElementById('bodyHtml');

    if (builderJson) {
        builderJson.value = JSON.stringify({
            page: pageDesign,
            rows: rows
        });
    }

    if (bodyHtml) {
        bodyHtml.value = emailHtml();
    }

    const templateJson = document.getElementById('templateBuilderJson');

    if (templateJson) {
        templateJson.value = JSON.stringify({
            page: pageDesign,
            rows: rows
        });
    }
}

function previewNewsletter() {
    syncBuilder();

    document.getElementById('previewContent').innerHTML = rows
        .map((row, rowIndex) => rowHtml(row, rowIndex, false, true))
        .join('');

    const surface = document.getElementById('modalPageSurface');
    const inner = document.getElementById('modalPageInner');

    if (surface) {
        surface.style.backgroundColor = pageDesign.bgColor || '#f1f5f9';
        surface.style.backgroundImage = pageDesign.bgImage
            ? `url("${previewImageUrl(pageDesign.bgImage)}")`
            : 'none';
        surface.style.backgroundSize = pageDesign.bgSize || 'cover';
        surface.style.backgroundPosition = pageDesign.bgPosition || 'center center';
        surface.style.backgroundRepeat = 'no-repeat';
    }

    if (inner) {
        inner.style.maxWidth = `${Math.max(320, Math.min(900, parseInt(pageDesign.contentWidth, 10) || 720))}px`;
        inner.style.backgroundColor = pageDesign.contentBg || '#ffffff';
        inner.style.borderRadius = `${Math.max(0, Math.min(40, parseInt(pageDesign.contentRadius, 10) || 0))}px`;
    }

    showModal('previewModal');
}

function loadTemplate(json) {
    try {
        const parsed = typeof json === 'string' ? JSON.parse(json) : json;

        const parsedRows = Array.isArray(parsed)
            ? parsed
            : (Array.isArray(parsed?.rows) ? parsed.rows : null);

        if (!parsedRows) {
            throw new Error('Template data is invalid.');
        }

        if (rows.length && !confirm('Replace the current newsletter layout with this template?')) {
            return;
        }

        rows = JSON.parse(JSON.stringify(parsedRows));

        if (parsed?.page && typeof parsed.page === 'object') {
            pageDesign = {
                ...pageDesign,
                ...JSON.parse(JSON.stringify(parsed.page))
            };
        }

        selected = null;
        render();
    } catch (error) {
        alert('This template could not be loaded.');
    }
}

document.getElementById('campaignForm').addEventListener('submit', syncBuilder);

const saveTemplateForm = document.getElementById('saveTemplateForm');
if (saveTemplateForm) {
    saveTemplateForm.addEventListener('submit', syncBuilder);
}

document.querySelectorAll('[data-builder-device]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-builder-device]').forEach(item => item.classList.remove('active'));
        button.classList.add('active');

        const width = document.getElementById('builderPreviewWidth');
        width.classList.remove('desktop', 'mobile');
        width.classList.add(button.dataset.builderDevice);
    });
});

document.querySelectorAll('[data-preview-device]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-preview-device]').forEach(item => item.classList.remove('active'));
        button.classList.add('active');

        const width = document.getElementById('modalPreviewWidth');
        width.classList.remove('desktop', 'mobile');
        width.classList.add(button.dataset.previewDevice);
    });
});

const editPageDesignBtn = document.getElementById('editPageDesignBtn');
if (editPageDesignBtn) {
    editPageDesignBtn.addEventListener('click', () => {
        selected = {type: 'page'};
        renderProperties();
    });
}

render();
</script>

<?php elseif ($tab === 'templates'): ?>

<div class="content-header" style="margin-top:5px">
    <div>
        <h3><i class="fas fa-table-cells-large"></i> Newsletter Templates</h3>
        <p class="page-subtitle">
            Reuse layouts for recurring campaigns, announcements and updates.
        </p>
    </div>
</div>

<?php if (!$templates): ?>
    <div class="card empty-state">
        <i class="fas fa-table-cells-large" style="font-size:35px"></i><br>
        No saved templates yet. Open a campaign and choose <strong>Save as Reusable Template</strong>.
    </div>
<?php else: ?>
    <div class="nl-template-grid">
        <?php foreach ($templates as $template): ?>
            <div class="nl-template-card">
                <div style="display:flex;justify-content:space-between;gap:10px">
                    <div>
                        <strong><?= htmlspecialchars($template['name']) ?></strong>
                        <div class="page-subtitle" style="margin-top:3px">
                            <?= htmlspecialchars($template['description'] ?: 'Reusable newsletter layout') ?>
                        </div>
                    </div>

                    <i class="fas fa-table-cells-large" style="color:#f86505;font-size:20px"></i>
                </div>

                <div class="page-subtitle" style="margin-top:12px">
                    Updated <?= htmlspecialchars(date('d M Y', strtotime($template['updated_at']))) ?>
                </div>

                <div style="display:flex;gap:6px;margin-top:12px">
                    <a
                        class="btn btn-sm btn-primary"
                        href="newsletters.php?tab=builder&template=<?= (int) $template['id'] ?>"
                        onclick="sessionStorage.setItem('newsletterTemplate', <?= htmlspecialchars(json_encode($template['builder_json']), ENT_QUOTES) ?>)"
                    >
                        <i class="fas fa-plus"></i>
                        Use
                    </a>

                    <form method="post" onsubmit="return confirm('Delete this template?')">
                        <input type="hidden" name="action" value="delete_template">
                        <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                        <button class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($tab === 'subscribers'): ?>

<div class="card" style="margin-bottom:16px">
    <div class="content-header" style="margin-bottom:12px">
        <div>
            <h3>
                <i class="fas fa-user-plus" style="color:#f86505"></i>
                Add Subscriber
            </h3>
            <p class="page-subtitle">
                Add one person manually or import a CSV audience.
            </p>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="action" value="add_subscriber">

        <div class="form-row">
            <div class="form-group col-4">
                <label>Name</label>
                <input name="subscriber_name" placeholder="Full name">
            </div>

            <div class="form-group col-4">
                <label>Email *</label>
                <input type="email" name="subscriber_email" required placeholder="email@example.com">
            </div>

            <div class="form-group col-4">
                <label>Tags</label>
                <input name="subscriber_tags" placeholder="partner, alumni, donor">
            </div>
        </div>

        <button class="btn btn-primary">
            <i class="fas fa-user-plus"></i>
            Save Subscriber
        </button>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="content-header" style="margin-bottom:12px">
        <div>
            <h3>
                <i class="fas fa-file-csv" style="color:#f86505"></i>
                Import Audience
            </h3>
            <p class="page-subtitle">
                Existing emails are reactivated instead of duplicated.
            </p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import_subscribers">

        <div class="csv-zone">
            <i class="fas fa-cloud-arrow-up" style="font-size:28px;color:#f86505"></i>

            <div class="form-group" style="max-width:520px;margin:12px auto">
                <input type="file" name="subscriber_csv" accept=".csv,text/csv" required>
            </div>

            <button class="btn btn-primary">
                <i class="fas fa-file-import"></i>
                Import CSV Subscribers
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Tags</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Subscribed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$subscribers): ?>
                <tr>
                    <td colspan="7" class="empty-state">No subscribers found.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($subscribers as $subscriber): ?>
                <tr>
                    <td><?= htmlspecialchars($subscriber['name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($subscriber['email']) ?></td>
                    <td><?= htmlspecialchars($subscriber['tags'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $subscriber['status'] === 'active' ? 'badge-success' : 'badge-warning' ?>">
                            <?= htmlspecialchars(ucfirst($subscriber['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($subscriber['source'] ?? 'website') ?></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($subscriber['subscribed_at']))) ?></td>
                    <td>
                        <div style="display:flex;gap:5px">
                            <form method="post">
                                <input type="hidden" name="action" value="toggle_subscriber">
                                <input type="hidden" name="subscriber_id" value="<?= (int) $subscriber['id'] ?>">
                                <input
                                    type="hidden"
                                    name="status"
                                    value="<?= $subscriber['status'] === 'active' ? 'unsubscribed' : 'active' ?>"
                                >
                                <button class="btn btn-sm">
                                    <i class="fas <?= $subscriber['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </button>
                            </form>

                            <form method="post" onsubmit="return confirm('Delete this subscriber?')">
                                <input type="hidden" name="action" value="delete_subscriber">
                                <input type="hidden" name="subscriber_id" value="<?= (int) $subscriber['id'] ?>">
                                <button class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'report'): ?>

<?php if (!$campaign): ?>
    <div class="alert alert-error">
        Campaign not found.
    </div>
<?php else: ?>
    <div class="content-header" style="margin-top:5px">
        <div>
            <h3>
                <i class="fas fa-chart-column"></i>
                <?= htmlspecialchars($campaign['name']) ?>
            </h3>
            <p class="page-subtitle">
                Delivery report for <?= htmlspecialchars($campaign['subject']) ?>
            </p>
        </div>

        <a class="btn" href="?tab=campaigns">
            <i class="fas fa-arrow-left"></i>
            Campaigns
        </a>
    </div>

    <div class="nl-report-grid">
        <div class="stat-card stat-card--blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($reportStats['total']) ?></div>
                <div class="stat-label">Recipients</div>
            </div>
        </div>

        <div class="stat-card stat-card--green">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($reportStats['sent']) ?></div>
                <div class="stat-label">Delivered to Mailer</div>
            </div>
        </div>

        <div class="stat-card stat-card--red">
            <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($reportStats['failed']) ?></div>
                <div class="stat-label">Failed</div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        These figures show send success/failure from your mail server. Open and click tracking require public tracking endpoints and are not fabricated here.
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Error</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$reportLogs): ?>
                    <tr>
                        <td colspan="4" class="empty-state">No delivery records yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($reportLogs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['email']) ?></td>
                        <td>
                            <span class="badge <?= $log['status'] === 'sent' ? 'badge-success' : 'badge-warning' ?>">
                                <?= htmlspecialchars(ucfirst($log['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['error_message'] ?: '—') ?></td>
                        <td><?= htmlspecialchars(date('d M Y, g:i A', strtotime($log['sent_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php elseif ($tab === 'archive'): ?>

<div class="content-header" style="margin-top:8px">
    <div>
        <h3><i class="fas fa-file-pdf"></i> Downloadable Newsletter Archive</h3>
        <p class="page-subtitle">
            Manage PDF issues shown on the public website.
        </p>
    </div>

    <button class="btn btn-primary" onclick="showModal('archiveModal')">
        <i class="fas fa-plus"></i>
        Upload PDF Issue
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Issue</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$archives): ?>
                <tr>
                    <td colspan="6" class="empty-state">
                        No PDF newsletter issues found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($archives as $newsletter): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($newsletter['title']) ?></strong></td>
                    <td><?= htmlspecialchars($newsletter['issue_number']) ?></td>
                    <td><?= number_format(((int) $newsletter['file_size']) / 1048576, 2) ?> MB</td>
                    <td>
                        <span class="badge <?= $newsletter['status'] === 'published' ? 'badge-success' : 'badge-warning' ?>">
                            <?= htmlspecialchars(ucfirst($newsletter['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($newsletter['published_date']))) ?></td>
                    <td>
                        <?php if ($newsletter['pdf_file']): ?>
                            <a
                                class="btn btn-sm"
                                target="_blank"
                                href="../uploads/newsletters/<?= rawurlencode($newsletter['pdf_file']) ?>"
                            >
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="archiveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload PDF Newsletter</h3>
            <button
                type="button"
                class="modal-close"
                onclick="hideModal('archiveModal')"
            >&times;</button>
        </div>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_archive">

            <div class="form-group">
                <label>Title *</label>
                <input name="title" required>
            </div>

            <div class="form-group">
                <label>Issue Number *</label>
                <input name="issue_number" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group col-6">
                    <label>Status</label>
                    <select name="status">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <div class="form-group col-6">
                    <label>Published Date</label>
                    <input
                        type="date"
                        name="published_date"
                        value="<?= date('Y-m-d') ?>"
                    >
                </div>
            </div>

            <div class="form-group">
                <label>PDF File *</label>
                <input type="file" name="pdf_file" accept=".pdf" required>
            </div>

            <div class="form-group">
                <label>Cover Image</label>
                <input type="file" name="cover_image" accept="image/*">
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn"
                    onclick="hideModal('archiveModal')"
                >
                    Cancel
                </button>

                <button class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Save Issue
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
function showModal(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = 'flex';
    }
}

function hideModal(id) {
    const element = document.getElementById(id);
    if (element) {
        element.style.display = 'none';
    }
}

window.addEventListener('click', event => {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});
</script>

<?php include 'layout/footer.php'; ?>
