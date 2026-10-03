<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers.php';

$success = false;
$error   = '';

$hero = getHeroImage($conn, 'contact');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please try again.';
    } else {

        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if ($email === '') {
            $errors[] = 'Email is required';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email format';
        }

        if ($phone !== '' && !isValidPhone($phone)) {
            $errors[] = 'Invalid phone number format';
        }

        if ($message === '') {
            $errors[] = 'Message is required';
        } elseif (strlen($message) < 10) {
            $errors[] = 'Message must be at least 10 characters';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO contact_submissions
                    (name, email, phone, subject, message, ip_address)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");

            $ip = getClientIP();
            $stmt->bind_param("ssssss", $name, $email, $phone, $subject, $message, $ip);

            if ($stmt->execute()) {
                $success = true;
                $_POST = [];
            } else {
                $error = 'Failed to send message. Please try again later.';
            }

            $stmt->close();
        } else {
            $error = implode(', ', $errors);
        }
    }
}

$page_title       = 'Contact Us';
$meta_description = "Get in touch with us. We'd love to hear from you.";
$body_class       = 'contact-page';

$siteEmail   = getSetting($conn, 'site_email', 'info@witu.org');
$siteAddress = getSetting($conn, 'site_address', '123 Main St, City');
$mapQuery    = urlencode($siteAddress);

include 'header.php';
?>

<style>


</style>

<section class="ct-hero <?php echo $hero ? 'has-bg' : ''; ?>">

  <?php if ($hero): ?>
    <div class="ct-hero-bg"
         style="background-image:url('<?php echo htmlspecialchars($hero['src'], ENT_QUOTES); ?>');"
         aria-hidden="true"></div>
  <?php endif; ?>

  <div class="ct-hero-slash" aria-hidden="true"></div>
  <div class="ct-hero-grain" aria-hidden="true"></div>

  <div class="ct-container">
    <div class="ct-hero-inner">
      <p class="ct-hero-eyebrow">Get In Touch</p>

      <h1>
        <?php echo ($hero && !empty($hero['title']))
            ? nl2br(htmlspecialchars($hero['title']))
            : 'Contact Us'; ?>
      </h1>

      <?php if ($hero && !empty($hero['subtitle'])): ?>
        <p class="ct-hero-sub"><?php echo nl2br(htmlspecialchars($hero['subtitle'])); ?></p>
      <?php else: ?>
        <p class="ct-hero-sub">We'd love to hear from you. Get in touch with our team.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="ct-container">

  <div class="ct-main">

    <div class="ct-form-panel">
      <h2>Send us a Message</h2>
      <p>Fill out the form below and we'll get back to you as soon as possible.</p>

      <?php if ($success): ?>

        <div class="ct-alert success" style="animation-name:ctSlideDown;">
          <i class="fas fa-check-circle"></i>
          <div>
            <strong>Message Sent Successfully!</strong>
            <span>Thank you for contacting us. We'll get back to you soon.</span>
          </div>
        </div>

        <div class="ct-send-another">
          <p>Want to send another message?</p>
          <a href="contact.php"><i class="fas fa-plus"></i> Send Another Message</a>
        </div>

      <?php else: ?>

        <?php if ($error): ?>
          <div class="ct-alert error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
              <strong>Error</strong>
              <span><?php echo htmlspecialchars($error); ?></span>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" id="contactForm">
          <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

          <div class="ct-form-row">
            <div class="ct-form-group">
              <label for="field-name">Name <span>*</span></label>
              <input id="field-name" autocomplete="name" type="text"
                     name="name"
                     required
                     value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                     placeholder="Your full name">
            </div>

            <div class="ct-form-group">
              <label for="field-email">Email <span>*</span></label>
              <input id="field-email" autocomplete="email" type="email"
                     name="email"
                     required
                     value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                     placeholder="you@example.com">
            </div>
          </div>

          <div class="ct-form-row">
            <div class="ct-form-group">
              <label for="field-phone">Phone</label>
              <input id="field-phone" autocomplete="tel" type="tel"
                     name="phone"
                     value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                     placeholder="+256 567-8900">
            </div>

            <div class="ct-form-group">
              <label for="field-subject">Subject</label>
              <input id="field-subject" type="text"
                     name="subject"
                     value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                     placeholder="How can we help?">
            </div>
          </div>

          <div class="ct-form-group">
            <label for="field-message">Message <span>*</span></label>
            <textarea id="field-message" name="message"
                      required
                      placeholder="Tell us more about your inquiry..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            <div class="ct-field-hint">Minimum 10 characters</div>
          </div>

          <button type="submit" class="ct-submit" id="ctSubmitBtn">
            <i class="fas fa-paper-plane"></i> Send Message
          </button>
        </form>

      <?php endif; ?>
    </div>

    <div class="ct-info-col">
      <h2>Contact Information</h2>

      <div class="ct-hours">
        <h3><i class="fas fa-envelope"></i> Email Us</h3>
        <p>Send us an email anytime</p>
        <a href="mailto:<?php echo htmlspecialchars($siteEmail, ENT_QUOTES); ?>"
           style="color:var(--orange);font-weight:700;">
          <?php echo htmlspecialchars($siteEmail); ?>
        </a>

        <!--<h3><i class="fas fa-map-marker-alt"></i> Visit Us</h3>
        <p style="color:var(--amber);font-weight:700;font-size:.9rem;line-height:1.6;">
          <?php echo nl2br(htmlspecialchars($siteAddress)); ?>
        </p> -->

        <h3><i class="far fa-clock"></i> Office Hours</h3>
        <p>
          <?php
            $hours = getSetting(
                $conn,
                'contact_hours',
                "Monday - Friday: 8:00 AM - 5:00 PM\nSaturday - Sunday: Closed"
            );
            echo nl2br(htmlspecialchars($hours));
          ?>
        </p>
      </div>

      <div class="ct-map-card">
        <div class="ct-map-header">
          <h3><i class="fas fa-location-dot"></i> Our Location</h3>
          <p><?php echo nl2br(htmlspecialchars($siteAddress)); ?></p>
        </div>

        <iframe
          class="ct-map-frame"
          loading="lazy"
          allowfullscreen
          referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps?q=<?php echo $mapQuery; ?>&output=embed">
        </iframe>

        <!--<div class="ct-map-actions">
          <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $mapQuery; ?>"
             target="_blank"
             rel="noopener noreferrer">
            <i class="fas fa-route"></i> Open in Google Maps
          </a>
        </div> -->
      </div>

    </div>

  </div>

</div>

<script>
document.getElementById('contactForm')?.addEventListener('submit', function (e) {
    const msg = this.querySelector('[name="message"]').value;

    if (msg.length < 10) {
        e.preventDefault();
        alert('Message must be at least 10 characters long.');
        return;
    }

    const btn = document.getElementById('ctSubmitBtn');

    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
        btn.disabled = true;

        setTimeout(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
        }, 8000);
    }
});

<?php if ($success): ?>
setTimeout(function () {
    var alertBox = document.querySelector('.ct-alert.success');

    if (alertBox) {
        alertBox.style.opacity = '0';
        alertBox.style.transition = 'opacity .4s';

        setTimeout(function () {
            alertBox.remove();
        }, 400);
    }
}, 10000);
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>