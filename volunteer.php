<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

$content = null;
$res = $conn->query("SELECT * FROM volunteer WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($res) $content = $res->fetch_assoc();

if (!$content) {
    $content = [
        'page_title'       => 'Volunteer With Us',
        'page_subtitle'    => 'Join our mission and make a meaningful impact in communities.',
        'intro_title'      => 'Become a Volunteer',
        'intro_text'       => 'Share your time, skills, and passion to support women and girls in technology.',
        'right_title'      => 'Why Volunteer?',
        'other_ways_title' => 'Contact Us',
        'other_email'      => 'info@witu.org',
        'other_phone'      => '+256 392 177 980',
        'featured_image'   => ''
    ];
}

$blocks = [];
$res = $conn->query("SELECT * FROM volunteer_blocks WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if ($res) while ($row = $res->fetch_assoc()) $blocks[] = $row;

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = clean($conn, $_POST['full_name'] ?? '');
    $email         = clean($conn, $_POST['email'] ?? '');
    $phone         = clean($conn, $_POST['phone'] ?? '');
    $interest_area = clean($conn, $_POST['interest_area'] ?? '');
    $message       = trim($_POST['message'] ?? '');

    if ($full_name !== '' && $email !== '' && $phone !== '') {
        $stmt = $conn->prepare("
            INSERT INTO volunteer_submissions (full_name, email, phone, extra_field, message, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("sssss", $full_name, $email, $phone, $interest_area, $message);

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

$page_title = 'Volunteer - ' . $site_name;
include 'header.php';

$heroImg = !empty($content['featured_image']) ? 'uploads/volunteer/' . $content['featured_image'] : '';
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
                        Thank you for your interest in volunteering. We have received your submission and will contact you soon.
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <div class="form-group">
                        <label for="field-full_name">Full Name *</label>
                        <input id="field-full_name" autocomplete="name" type="text" name="full_name" required value="<?php echo !$success ? htmlspecialchars($_POST['full_name'] ?? '') : ''; ?>">
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
                        <label for="field-interest_area">Area of Interest</label>
                        <input id="field-interest_area" type="text" name="interest_area" placeholder="e.g. Mentorship, Training, Events" value="<?php echo !$success ? htmlspecialchars($_POST['interest_area'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="field-message">Why would you like to volunteer?</label>
                        <textarea id="field-message" name="message" rows="5"><?php echo !$success ? htmlspecialchars($_POST['message'] ?? '') : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Submit Volunteer Request
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