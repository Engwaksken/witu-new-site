<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

$content = null;
$res = $conn->query("SELECT * FROM partner WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($res) $content = $res->fetch_assoc();

if (!$content) {
    $content = [
        'page_title'       => 'Partner With Us',
        'page_subtitle'    => 'Collaborate with us to expand access and opportunities for women in technology.',
        'intro_title'      => 'Become a Partner',
        'intro_text'       => 'We welcome partnerships with organisations, institutions, and companies that share our mission.',
        'right_title'      => 'Why Partner?',
        'other_ways_title' => 'Partnership Contact',
        'other_email'      => 'info@witu.org',
        'other_phone'      => '+256 392 177 980',
        'featured_image'   => ''
    ];
}

$blocks = [];
$res = $conn->query("SELECT * FROM partner_blocks WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if ($res) while ($row = $res->fetch_assoc()) $blocks[] = $row;

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = clean($conn, $_POST['full_name'] ?? '');
    $email            = clean($conn, $_POST['email'] ?? '');
    $phone            = clean($conn, $_POST['phone'] ?? '');
    $partnership_type = clean($conn, $_POST['partnership_type'] ?? '');
    $message          = trim($_POST['message'] ?? '');

    if ($full_name !== '' && $email !== '' && $phone !== '') {
        $stmt = $conn->prepare("
            INSERT INTO partner_submissions (full_name, email, phone, extra_field, message, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("sssss", $full_name, $email, $phone, $partnership_type, $message);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'An error occurred while submitting the form. Please try again.';
        }
        $stmt->close();
    } else {
        $error = 'Please fill in all required fields.';
    }
}

$site_name = 'WITU';
$res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_name' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $site_name = $row['setting_value'];

$menu_pages = [];
$res = $conn->query("SELECT * FROM pages WHERE show_in_menu=1 AND status='published' ORDER BY menu_order ASC");
if ($res) while ($row = $res->fetch_assoc()) $menu_pages[] = $row;

$page_title = 'Partner - ' . $site_name;
include 'header.php';

$heroImg = !empty($content['featured_image']) ? 'uploads/partner/' . $content['featured_image'] : '';
$heroStyle = $heroImg
    ? "background-image:linear-gradient(135deg, rgba(253,101,1,.75) 0%, rgba(253,101,1,.35) 100%), url('" . htmlspecialchars($heroImg, ENT_QUOTES) . "'); background-size:cover; background-position:center;"
    : "background:linear-gradient(135deg,#fd6501 0%, #fd6501a8 100%);";
?>

<section class="donate-hero" style="<?php echo $heroStyle; ?>">
    <div class="container">
        <h1><?php echo htmlspecialchars($content['page_title']); ?></h1>
        <?php if (!empty(trim($content['page_subtitle'] ?? ''))): ?>
            <p><?php echo nl2br(htmlspecialchars($content['page_subtitle'])); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="donate-wrap">
    <div class="container">
        <div class="donate-grid">

            <div class="panel">
                <h2><?php echo htmlspecialchars($content['intro_title']); ?></h2>

                <?php if (!empty(trim($content['intro_text'] ?? ''))): ?>
                    <p class="lead"><?php echo nl2br(htmlspecialchars($content['intro_text'])); ?></p>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Thank you for your interest in partnering with us. We have received your request and will get back to you soon.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <div class="form-group">
                        <label for="field-full_name">Organisation Name *</label>
                        <input id="field-full_name" autocomplete="organization" type="text" name="full_name" required value="<?php echo !$success ? htmlspecialchars($_POST['full_name'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="field-email">Email Address *</label>
                        <input id="field-email" autocomplete="email" type="email" name="email" required value="<?php echo !$success ? htmlspecialchars($_POST['email'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="field-phone">Phone Number *</label>
                        <input id="field-phone" autocomplete="tel" type="tel" name="phone" required value="<?php echo !$success ? htmlspecialchars($_POST['phone'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="field-partnership_type">Partnership Type</label>
                        <input id="field-partnership_type" type="text" name="partnership_type" placeholder="e.g. Funding, Training, Technology Support" value="<?php echo !$success ? htmlspecialchars($_POST['partnership_type'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="field-message">Partnership Details</label>
                        <textarea id="field-message" name="message" rows="5"><?php echo !$success ? htmlspecialchars($_POST['message'] ?? '') : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Submit Partnership Request
                    </button>
                </form>
            </div>

            <div class="panel">
                <h2><?php echo htmlspecialchars($content['right_title']); ?></h2>

                <?php if (empty($blocks)): ?>
                    <p class="lead">No content blocks added yet.</p>
                <?php else: ?>
                    <div class="why-stack">
                        <?php foreach ($blocks as $b): ?>
                            <?php
                                $bg = trim($b['bg_color'] ?? '') ?: '#eff6ff';
                                $bd = trim($b['border_color'] ?? '') ?: '#fd6501';
                                $tc = trim($b['title_color'] ?? '') ?: '#fd6501';
                            ?>
                            <div class="why-card" style="background:<?php echo htmlspecialchars($bg, ENT_QUOTES); ?>; border-left:4px solid <?php echo htmlspecialchars($bd, ENT_QUOTES); ?>;">
                                <h3 style="color:<?php echo htmlspecialchars($tc, ENT_QUOTES); ?>;">
                                    <?php if (!empty($b['icon_class'])): ?>
                                        <i class="<?php echo htmlspecialchars($b['icon_class']); ?> why-icon"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($b['title']); ?>
                                </h3>
                                <p><?php echo nl2br(htmlspecialchars($b['body'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>