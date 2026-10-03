<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';


$donate = null;
$res = $conn->query("SELECT * FROM donate WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($res) $donate = $res->fetch_assoc();

// sensible defaults if not configured yet
if (!$donate) {
  $donate = [
    'page_title' => 'Support Our Cause',
    'page_subtitle' => 'Your donation makes a real difference',
    'intro_title' => 'Make a Donation',
    'intro_text' => 'Your contribution helps us continue our important work and make a lasting impact on communities.',
    'right_title' => 'Why Donate?',
    'other_ways_title' => 'Other Ways to Give',
    'other_email' => 'info@witu.org',
    'other_phone' => '+256 392 177 980',
    'featured_image' => '',
  ];
}


$blocks = [];
$res = $conn->query("SELECT * FROM donate_blocks WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if ($res) while ($row = $res->fetch_assoc()) $blocks[] = $row;


$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $donor_name  = clean($conn, $_POST['donor_name'] ?? '');
  $donor_email = clean($conn, $_POST['donor_email'] ?? '');
  $amount_raw  = trim($_POST['amount'] ?? '');
  $payment_method = clean($conn, $_POST['payment_method'] ?? 'online');

  // allow commas/spaces
  $amount_clean = str_replace([',', ' '], '', $amount_raw);
  $amount = (float)$amount_clean;

  if ($donor_name !== '' && $donor_email !== '' && $amount > 0) {
    $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, amount, payment_method, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("ssds", $donor_name, $donor_email, $amount, $payment_method);
    if ($stmt->execute()) {
      $success = true;
    } else {
      $error = 'An error occurred. Please try again.';
    }
    $stmt->close();
  } else {
    $error = 'Please fill in all required fields and enter a valid amount.';
  }
}


$site_name = 'WITU';
$res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_name' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $site_name = $row['setting_value'];

$menu_pages = [];
$res = $conn->query("SELECT * FROM pages WHERE show_in_menu=1 AND status='published' ORDER BY menu_order ASC");
if ($res) while ($row = $res->fetch_assoc()) $menu_pages[] = $row;

$page_title = 'Donate - ' . $site_name;
include 'header.php';


$heroImg = !empty($donate['featured_image']) ? 'uploads/donate/' . $donate['featured_image'] : '';
$heroStyle = $heroImg
  ? "background-image:linear-gradient(135deg, rgba(253,101,1,.75) 0%, rgba(253,101,1,.35) 100%), url('".htmlspecialchars($heroImg, ENT_QUOTES)."'); background-size:cover; background-position:center;"
  : "background:linear-gradient(135deg,#fd6501 0%, #fd6501a8 100%);";
?>

<!-- HERO -->
<section class="donate-hero" style="<?php echo $heroStyle; ?>">
  <div class="container">
    <h1><?php echo htmlspecialchars($donate['page_title'] ?? 'Support Our Cause'); ?></h1>
    <?php if (!empty(trim($donate['page_subtitle'] ?? ''))): ?>
      <p><?php echo nl2br(htmlspecialchars($donate['page_subtitle'])); ?></p>
    <?php endif; ?>
  </div>
</section>

<!-- CONTENT -->
<section class="donate-wrap">
  <div class="container">
    <div class="donate-grid">

      <!-- LEFT: Form -->
      <div class="panel">
        <h2><?php echo htmlspecialchars($donate['intro_title'] ?? 'Make a Donation'); ?></h2>

        <?php if (!empty(trim($donate['intro_text'] ?? ''))): ?>
          <p class="lead"><?php echo nl2br(htmlspecialchars($donate['intro_text'])); ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success">
            Thank you for your generous donation! We've received your pledge and will contact you with payment details.
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

<form action="https://www.paypal.com/donate" method="post" target="_top">
<input type="hidden" name="hosted_button_id" value="Q2PUY6HX6W6BG" />
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
</form>

    
      </div>

      <!-- RIGHT: Why donate -->
      <div class="panel">
        <h2><?php echo htmlspecialchars($donate['right_title'] ?? 'Why Donate?'); ?></h2>

        <?php if (empty($blocks)): ?>
          <p class="lead">No "Why Donate" blocks added yet.</p>
        <?php else: ?>
          <div class="why-stack">
            <?php foreach ($blocks as $b): ?>
              <?php
                $bg  = trim($b['bg_color'] ?? '') ?: '#eff6ff';
                $bd  = trim($b['border_color'] ?? '') ?: '#fd6501';
                $tc  = trim($b['title_color'] ?? '') ?: '#fd6501';
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

       <!-- <div class="other-ways">
          <h4><?php echo htmlspecialchars($donate['other_ways_title'] ?? 'Other Ways to Give'); ?></h4>
          <ul>
            <?php if (!empty(trim($donate['other_email'] ?? ''))): ?>
              <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($donate['other_email']); ?></li>
            <?php endif; ?>
            <?php if (!empty(trim($donate['other_phone'] ?? ''))): ?>
              <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($donate['other_phone']); ?></li>
            <?php endif; ?>
          </ul>
        </div>-->

      </div>

    </div>
  </div>
</section>

<script>
function selectAmount(val){
  const input = document.getElementById('customAmount');
  input.value = String(val);

  document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
  // mark the clicked button
  const btns = Array.from(document.querySelectorAll('.amount-btn'));
  const hit = btns.find(b => (b.textContent || '').replace(/[^0-9]/g,'') === String(val));
  if (hit) hit.classList.add('selected');
}
</script>

<?php include 'footer.php'; ?>
