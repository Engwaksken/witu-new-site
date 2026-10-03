<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/includes/admin-enhancements.php';
adminTrackActivity($conn);

$has_permission = false;
if (function_exists('hasPermission')) {
    if (hasPermission($conn, 'manage_home') || hasPermission($conn, 'manage_content')) {
        $has_permission = true;
    }
} else {
    $has_permission = true;
}
if (!$has_permission) {
    die('<h2>Access Denied</h2><p>You don\'t have permission to manage home settings.</p>');
}

$page_title = 'Home Page Builder';


$chk = $conn->query("SELECT COUNT(*) AS n FROM agency_cards");
if ($chk && $chk->fetch_assoc()['n'] == 0) {
    $conn->query("
        INSERT INTO agency_cards (title, description, icon, sort_order, is_active) VALUES
        ('Confidence','Participants build self-belief, resilience, and motivation, empowering them to pursue education, employment, and entrepreneurship opportunities with confidence.','fas fa-heart',1,1),
        ('Voice','Participants strengthen communication, self-advocacy, and decision-making skills, empowering them to navigate challenges and shape their futures with confidence.','fas fa-comments',2,1),
        ('Leadership','Participants develop leadership, responsibility, and problem-solving skills, empowering them to succeed in work, business, and community settings.','fas fa-star',3,1)
    ");
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_home') {
        $id                   = intval($_POST['id'] ?? 0);
        $hero_headline        = clean($conn, $_POST['hero_headline']        ?? '');
        $hero_subtext         = clean($conn, $_POST['hero_subtext']         ?? '');
        $hero_text_align      = clean($conn, $_POST['hero_text_align']      ?? 'center');
        $hero_text_valign     = clean($conn, $_POST['hero_text_valign']     ?? 'center');
        $primary_cta_text     = clean($conn, $_POST['primary_cta_text']     ?? '');
        $primary_cta_link     = clean($conn, $_POST['primary_cta_link']     ?? '');
        $secondary_cta_text   = clean($conn, $_POST['secondary_cta_text']   ?? '');
        $secondary_cta_link   = clean($conn, $_POST['secondary_cta_link']   ?? '');
        $get_involved_text    = clean($conn, $_POST['get_involved_text']    ?? 'Get Involved');
        $get_involved_link    = clean($conn, $_POST['get_involved_link']    ?? '');
        $hero_overlay_opacity = max(0, min(1, floatval($_POST['hero_overlay_opacity'] ?? 0.48)));
        $is_active            = isset($_POST['is_active']) ? 1 : 0;

        if ($hero_headline === '' || $hero_subtext === '') {
            setFlashMessage('error', 'Hero headline and subtext are required.');
            header('Location: home.php'); exit;
        }
        if ($is_active === 1) $conn->query("UPDATE home SET is_active=0");

        foreach (['hero_text_align VARCHAR(10) DEFAULT "center"',
                  'hero_text_valign VARCHAR(10) DEFAULT "center"',
                  'hero_overlay_opacity FLOAT DEFAULT 0.48'] as $col) {
            @$conn->query("ALTER TABLE home ADD COLUMN $col");
        }

        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE home SET hero_headline=?,hero_subtext=?,
                 hero_text_align=?,hero_text_valign=?,hero_overlay_opacity=?,
                 primary_cta_text=?,primary_cta_link=?,
                 secondary_cta_text=?,secondary_cta_link=?,
                 get_involved_text=?,get_involved_link=?,is_active=? WHERE id=?"
            );
            $stmt->bind_param('ssssdssssssii',
                $hero_headline,$hero_subtext,
                $hero_text_align,$hero_text_valign,$hero_overlay_opacity,
                $primary_cta_text,$primary_cta_link,
                $secondary_cta_text,$secondary_cta_link,
                $get_involved_text,$get_involved_link,$is_active,$id);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO home (hero_headline,hero_subtext,
                 hero_text_align,hero_text_valign,hero_overlay_opacity,
                 primary_cta_text,primary_cta_link,
                 secondary_cta_text,secondary_cta_link,
                 get_involved_text,get_involved_link,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('ssssdsssssi',
                $hero_headline,$hero_subtext,
                $hero_text_align,$hero_text_valign,$hero_overlay_opacity,
                $primary_cta_text,$primary_cta_link,
                $secondary_cta_text,$secondary_cta_link,
                $get_involved_text,$get_involved_link,$is_active);
        }
        $stmt->execute();
        setFlashMessage('success', 'Home page saved successfully.');
        header('Location: home.php'); exit;
    }

   
    if ($action === 'save_agency_card') {
        $cid         = intval($_POST['card_id'] ?? 0);
        $title       = clean($conn, $_POST['card_title']       ?? '');
        $description = clean($conn, $_POST['card_description'] ?? '');
        $icon        = clean($conn, $_POST['card_icon']        ?? 'fas fa-star');
        $sort_order  = intval($_POST['card_sort_order']        ?? 0);
        $is_active   = isset($_POST['card_is_active']) ? 1 : 0;

        if ($title === '') {
            setFlashMessage('error', 'Card title is required.');
            header('Location: home.php#agency'); exit;
        }
        if ($cid > 0) {
            $stmt = $conn->prepare(
                "UPDATE agency_cards SET title=?,description=?,icon=?,sort_order=?,is_active=? WHERE id=?"
            );
            $stmt->bind_param('sssiii', $title,$description,$icon,$sort_order,$is_active,$cid);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO agency_cards (title,description,icon,sort_order,is_active) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param('sssii', $title,$description,$icon,$sort_order,$is_active);
        }
        $stmt->execute();
        setFlashMessage('success', $cid > 0 ? 'Card updated.' : 'Card added.');
        header('Location: home.php#agency'); exit;
    }

    /* -- Delete agency card -- */
    if ($action === 'delete_agency_card') {
        $cid = intval($_POST['card_id'] ?? 0);
        if ($cid > 0) {
            $stmt = $conn->prepare("DELETE FROM agency_cards WHERE id=?");
            $stmt->bind_param('i', $cid);
            $stmt->execute();
        }
        setFlashMessage('success', 'Card deleted.');
        header('Location: home.php#agency'); exit;
    }

    /* -- Toggle active -- */
    if ($action === 'toggle_agency_card') {
        $cid = intval($_POST['card_id'] ?? 0);
        if ($cid > 0) {
            $conn->query("UPDATE agency_cards SET is_active = 1 - is_active WHERE id=$cid");
        }
        header('Location: home.php#agency'); exit;
    }
}

/* ----------------------------------------------
   LOAD DATA
---------------------------------------------- */
$home = null;
$res  = $conn->query("SELECT * FROM home WHERE is_active=1 ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) $home = $row;
else {
    $res = $conn->query("SELECT * FROM home ORDER BY id DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) $home = $row;
}
$defaults = [
    'id'=>0,'hero_headline'=>'Women in Technology Uganda',
    'hero_subtext'=>'Empowering Women Through Digital Skills, Entrepreneurship, and Global Connections',
    'hero_text_align'=>'center','hero_text_valign'=>'center','hero_overlay_opacity'=>0.48,
    'primary_cta_text'=>'Our Programs','primary_cta_link'=>'programs.php',
    'secondary_cta_text'=>'Support Our Cause','secondary_cta_link'=>'donate.php',
    'get_involved_text'=>'Get Involved','get_involved_link'=>'get-involved.php','is_active'=>1,
];
$home = $home ? array_merge($defaults, $home) : $defaults;

$agency_cards = [];
$res = $conn->query("SELECT * FROM agency_cards ORDER BY sort_order ASC, id ASC");
if ($res) while ($row = $res->fetch_assoc()) $agency_cards[] = $row;

function hv($v){ return htmlspecialchars($v ?? '', ENT_QUOTES); }

include 'layout/header.php';
include 'layout/sidebar.php';
?>



<?php $flash = getFlashMessage(); if ($flash): ?>
<div class="hb-flash <?php echo $flash['type']; ?>">
    <i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle'; ?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<div class="hb-wrap">

  <!-- ---------- SIDEBAR ---------- -->
  <div class="hb-sidebar">
    <div class="hb-sidebar-head">
      <h2><i class="fas fa-home" style="color:#f86505"></i> Home Page Builder</h2>
      <p>Edit content &amp; see a live preview on the right</p>
    </div>

    <div class="hb-tabs">
      <div class="hb-tab active" data-tab="hero"    onclick="hbTab(this,'hero')"><i class="fas fa-home"></i> Hero</div>
      <div class="hb-tab"        data-tab="agency"  onclick="hbTab(this,'agency')" id="agencyTab"><i class="fas fa-hands-helping"></i> Agency</div>
      <div class="hb-tab"        data-tab="sections" onclick="hbTab(this,'sections')"><i class="fas fa-th-large"></i> Sections</div>
      <div class="hb-tab"        data-tab="cta"     onclick="hbTab(this,'cta')"><i class="fas fa-bullseye"></i> CTA</div>
    </div>

    <!-- -- HERO FORM -- -->
    <form method="POST" id="hbForm">
      <input type="hidden" name="action" value="save_home">
      <input type="hidden" name="id" id="hbId" value="<?php echo (int)$home['id']; ?>">

      <div class="hb-panel active" id="tab-hero">

        <div class="hb-group">
          <label>Hero Headline *</label>
          <input type="text" name="hero_headline" id="ph_headline" required
                 value="<?php echo hv($home['hero_headline']); ?>" oninput="pvUpdate()">
        </div>

        <div class="hb-group">
          <label>Hero Subtext *</label>
          <textarea name="hero_subtext" id="ph_subtext" rows="3"
                    oninput="pvUpdate()"><?php echo hv($home['hero_subtext']); ?></textarea>
        </div>

        <hr class="hb-divider">
       <div class="hb-sublabel">Text Position</div>

<!-- Horizontal Alignment -->
<div class="hb-pos-label">Horizontal Align</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;width:140px;margin-bottom:16px;">
    <?php
    foreach([
        'left'   => '<i class="fas fa-align-left"></i>',
        'center' => '<i class="fas fa-align-center"></i>',
        'right'  => '<i class="fas fa-align-right"></i>'
    ] as $v => $icon):
    ?>
    <div class="hb-pos-cell <?php echo $home['hero_text_align'] === $v ? 'active' : ''; ?>"
         data-halign="<?php echo $v; ?>"
         onclick="setHAlign(this,'<?php echo $v; ?>')"
         title="<?php echo ucfirst($v); ?>">
        <?php echo $icon; ?>
    </div>
    <?php endforeach; ?>
</div>

<input type="hidden"
       name="hero_text_align"
       id="ph_halign"
       value="<?php echo hv($home['hero_text_align']); ?>">

<!-- Vertical Alignment -->
<div class="hb-pos-label">Vertical Align</div>
<div style="display:grid;grid-template-columns:1fr;gap:5px;width:50px;margin-bottom:16px;">
    <?php
    foreach([
        'flex-start' => '<i class="fas fa-arrow-up"></i>',
        'center'     => '<i class="fas fa-dot-circle"></i>',
        'flex-end'   => '<i class="fas fa-arrow-down"></i>'
    ] as $v => $icon):
    ?>
    <div class="hb-pos-cell <?php echo $home['hero_text_valign'] === $v ? 'active' : ''; ?>"
         data-valign="<?php echo $v; ?>"
         onclick="setVAlign(this,'<?php echo $v; ?>')"
         title="<?php echo str_replace('flex-', '', $v); ?>">
        <?php echo $icon; ?>
    </div>
    <?php endforeach; ?>
</div>

<input type="hidden"
       name="hero_text_valign"
       id="ph_valign"
       value="<?php echo hv($home['hero_text_valign']); ?>">
        <hr class="hb-divider">
        <div class="hb-sublabel">Overlay Darkness</div>
        <div class="hb-group">
          <div class="hb-range-row">
            <input type="range" name="hero_overlay_opacity" id="ph_overlay"
                   min="0" max="0.85" step="0.01"
                   value="<?php echo $home['hero_overlay_opacity']; ?>"
                   oninput="pvUpdate();document.getElementById('ovVal').textContent=Math.round(this.value*100)+'%'">
            <span class="hb-range-val" id="ovVal"><?php echo round($home['hero_overlay_opacity']*100); ?>%</span>
          </div>
        </div>

        <hr class="hb-divider">
        <div class="hb-sublabel">Hero Buttons</div>
        <div class="hb-cta-row">
          <div class="hb-group"><label>Primary text</label>
            <input type="text" name="primary_cta_text" value="<?php echo hv($home['primary_cta_text']); ?>" oninput="pvUpdate()"></div>
          <div class="hb-group"><label>Primary link</label>
            <input type="text" name="primary_cta_link" value="<?php echo hv($home['primary_cta_link']); ?>"></div>
        </div>
        <div class="hb-cta-row">
          <div class="hb-group"><label>Secondary text</label>
            <input type="text" name="secondary_cta_text" value="<?php echo hv($home['secondary_cta_text']); ?>" oninput="pvUpdate()"></div>
          <div class="hb-group"><label>Secondary link</label>
            <input type="text" name="secondary_cta_link" value="<?php echo hv($home['secondary_cta_link']); ?>"></div>
        </div>

        <div class="hb-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" <?php echo $home['is_active']?'checked':''; ?>>
            <span>Set as active home content</span>
          </label>
          <small>Only one record can be active at a time</small>
        </div>

      </div><!-- /hero panel -->

      <div class="hb-save-bar">
        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Hero</button>
        <span style="font-size:11px;color:#9ca3af;">Hero &amp; CTA changes only</span>
      </div>
    </form>

    <!-- -- AGENCY PANEL (outside main form - its own forms) -- -->
    <div class="hb-panel" id="tab-agency" style="padding-bottom:20px;">
      <p style="font-size:12px;color:#6b7280;margin-bottom:14px;">
        The <strong>Skills, Agency &amp; Opportunity</strong> section on the home page. Add, edit or reorder the three cards.
      </p>

      <div class="ac-list" id="acList">
        <?php foreach ($agency_cards as $ac): ?>
        <div class="ac-item <?php echo !$ac['is_active']?'inactive':''; ?>" data-id="<?php echo $ac['id']; ?>">
          <span class="ac-drag" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
          <span class="ac-icon-badge"><i class="<?php echo hv($ac['icon']); ?>"></i></span>
          <div class="ac-body">
            <strong><?php echo hv($ac['title']); ?></strong>
            <small><?php echo hv($ac['description']); ?></small>
          </div>
          <div class="ac-actions">
            <button class="ac-btn <?php echo $ac['is_active']?'ac-btn-toggle':'ac-btn-toggle off'; ?>"
                    onclick="toggleCard(<?php echo $ac['id']; ?>)"
                    title="<?php echo $ac['is_active']?'Deactivate':'Activate'; ?>">
              <i class="fas fa-<?php echo $ac['is_active']?'eye':'eye-slash'; ?>"></i>
            </button>
            <button class="ac-btn ac-btn-edit" onclick="openCardModal(<?php echo htmlspecialchars(json_encode($ac), ENT_QUOTES); ?>)">
              <i class="fas fa-pen"></i>
            </button>
            <button class="ac-btn ac-btn-del" onclick="deleteCard(<?php echo $ac['id']; ?>,'<?php echo hv($ac['title']); ?>')">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <button class="ac-add-btn" onclick="openCardModal(null)">
        <i class="fas fa-plus"></i> Add Card
      </button>

      <div class="hb-section-card" style="margin-top:16px;">
        <h4><i class="fas fa-info-circle" style="color:#f86505;margin-right:5px;"></i> Opportunity Callout</h4>
        <p>The orange-border callout box below the three cards ("Personalized Support &amp; Opportunity Connections") is static content managed in the page template.</p>
      </div>
    </div><!-- /agency panel -->

   <!-- -- SECTIONS PANEL -- -->
<div class="hb-panel" id="tab-sections">

    <p style="font-size:12px;color:#6b7280;margin-bottom:14px;">
        These sections are managed in their own admin pages.
    </p>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-images" style="color:#f86505;margin-right:5px;"></i>
            Hero Sliders
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>Background images &amp; slide-level headlines in the hero carousel.</p>
        <a href="sliders.php" class="btn btn-sm" style="margin-top:8px;font-size:12px;">
            <i class="fas fa-cog"></i> Manage Sliders
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-bullseye" style="color:#f86505;margin-right:5px;"></i>
            Focus Areas
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>Cards in the "Our Focus Areas" grid with icons or images.</p>
        <a href="focus-areas.php" class="btn btn-sm" style="margin-top:8px;font-size:12px;">
            <i class="fas fa-cog"></i> Manage Focus Areas
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-route" style="color:#f86505;margin-right:5px;"></i>
            Participant Journey
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>3-step journey cards + the continuous mentorship callout box.</p>
        <a href="participant-journey.php" class="btn btn-sm" style="margin-top:8px;font-size:12px;">
            <i class="fas fa-cog"></i> Manage Journey
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-quote-left" style="color:#f86505;margin-right:5px;"></i>
            Testimonials
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>Participant stories shown in the carousel with photo &amp; program info.</p>
        <a href="testimonials.php" class="btn btn-sm" style="margin-top:8px;font-size:12px;">
            <i class="fas fa-cog"></i> Manage Testimonials
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-handshake" style="color:#f86505;margin-right:5px;"></i>
            Partners
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>Partner logos shown in the partners row with optional website links.</p>
        <a href="partners.php" class="btn btn-sm" style="margin-top:8px;font-size:12px;">
            <i class="fas fa-cog"></i> Manage Partners
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </a>
    </div>

    <div class="hb-section-card">
        <h4>
            <i class="fas fa-hands-helping" style="color:#f86505;margin-right:5px;"></i>
            Agency Cards
            <span class="hb-section-badge badge-db">Database</span>
        </h4>
        <p>Skills, Agency &amp; Opportunity cards - manage in the Agency tab.</p>

        <button class="btn btn-sm"
                style="margin-top:8px;font-size:12px;"
                onclick="hbTab(document.querySelector('[data-tab=agency]'),'agency')">
            <i class="fas fa-external-link-alt"></i>
            Go to Agency Tab
            <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
        </button>
    </div>

</div>
<!-- /sections panel -->

    <!-- -- CTA PANEL -- -->
    <form method="POST">
      <input type="hidden" name="action" value="save_home">
      <input type="hidden" name="id" value="<?php echo (int)$home['id']; ?>">
      <!-- carry all other fields as hidden so only CTA fields matter -->
      <input type="hidden" name="hero_headline"        value="<?php echo hv($home['hero_headline']); ?>">
      <input type="hidden" name="hero_subtext"         value="<?php echo hv($home['hero_subtext']); ?>">
      <input type="hidden" name="hero_text_align"      value="<?php echo hv($home['hero_text_align']); ?>">
      <input type="hidden" name="hero_text_valign"     value="<?php echo hv($home['hero_text_valign']); ?>">
      <input type="hidden" name="hero_overlay_opacity" value="<?php echo $home['hero_overlay_opacity']; ?>">
      <input type="hidden" name="primary_cta_text"     value="<?php echo hv($home['primary_cta_text']); ?>">
      <input type="hidden" name="primary_cta_link"     value="<?php echo hv($home['primary_cta_link']); ?>">
      <input type="hidden" name="secondary_cta_text"   value="<?php echo hv($home['secondary_cta_text']); ?>">
      <input type="hidden" name="secondary_cta_link"   value="<?php echo hv($home['secondary_cta_link']); ?>">
      <?php if ($home['is_active']): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>

      <div class="hb-panel" id="tab-cta">
        <p style="font-size:12px;color:#6b7280;margin-bottom:14px;">
          The orange "Get Involved" banner at the bottom of the home page.
        </p>
        <div class="hb-group"><label>Button text</label>
          <input type="text" name="get_involved_text" value="<?php echo hv($home['get_involved_text']); ?>" oninput="pvUpdate()"></div>
        <div class="hb-group"><label>Button link</label>
          <input type="text" name="get_involved_link" value="<?php echo hv($home['get_involved_link']); ?>"></div>
      </div>

      <div class="hb-save-bar">
        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save CTA</button>
      </div>
    </form>

  </div><!-- /sidebar -->

  <!-- ---------- PREVIEW ---------- -->
  <div class="hb-preview-area">
    <div class="hb-preview-bar">
      <span><i class="fas fa-eye"></i> Live Preview</span>
      <div class="pv-url"><i class="fas fa-globe" style="margin-right:5px;"></i> index.php (home)</div>
      <div class="hb-preview-vp">
        <button class="hb-vp-btn active" onclick="setViewport('desktop',this)"><i class="fas fa-desktop"></i> Desktop</button>
        <button class="hb-vp-btn"        onclick="setViewport('tablet',this)"><i class="fas fa-tablet-alt"></i> Tablet</button>
        <button class="hb-vp-btn"        onclick="setViewport('mobile',this)"><i class="fas fa-mobile-alt"></i> Mobile</button>
      </div>
    </div>
    <div class="hb-preview-frame-wrap" id="pvFrameWrap">
      <iframe id="pvFrame" src="" style="width:100%;height:100%;" title="Home Preview"></iframe>
    </div>
  </div>
</div>

<!-- ----------------------------------------------
     AGENCY CARD MODAL
---------------------------------------------- -->
<div id="acModalOverlay" class="ac-modal-overlay" style="display:none;">
  <div class="ac-modal">
    <div class="ac-modal-head">
      <h3 id="acModalTitle">Add Card</h3>
      <button class="ac-modal-close" onclick="closeCardModal()">&times;</button>
    </div>
    <form method="POST" id="acForm">
      <input type="hidden" name="action"       value="save_agency_card">
      <input type="hidden" name="card_id"      id="acCardId" value="0">
      <div class="ac-modal-body">

        <div class="hb-group">
          <label>Title *</label>
          <input type="text" name="card_title" id="acTitle" required placeholder="e.g. Confidence">
        </div>

        <div class="hb-group">
          <label>Description</label>
          <textarea name="card_description" id="acDesc" rows="4"
                    placeholder="Describe what participants gain from this pillar"></textarea>
        </div>

        <div class="hb-group">
          <label>Icon</label>
          <input type="hidden" name="card_icon" id="acIcon" value="fas fa-star">
          <div class="icon-picker" id="iconPicker">
            <?php
            $icons = [
              'fas fa-heart','fas fa-star','fas fa-comments','fas fa-bolt',
              'fas fa-users','fas fa-lightbulb','fas fa-graduation-cap',
              'fas fa-briefcase','fas fa-rocket','fas fa-hands-helping',
              'fas fa-trophy','fas fa-shield-alt','fas fa-leaf','fas fa-globe',
              'fas fa-laptop-code','fas fa-chart-line','fas fa-handshake',
              'fas fa-microphone','fas fa-book-open','fas fa-paint-brush','fas fa-tools',
            ];
            foreach ($icons as $ico): ?>
            <div class="icon-opt" data-icon="<?php echo $ico; ?>" title="<?php echo $ico; ?>"
                 onclick="pickIcon(this,'<?php echo $ico; ?>')">
              <i class="<?php echo $ico; ?>"></i>
            </div>
            <?php endforeach; ?>
          </div>
          <small>Click to select an icon</small>
        </div>

        <div class="hb-cta-row">
          <div class="hb-group">
            <label>Sort order</label>
            <input type="number" name="card_sort_order" id="acSort" value="0" min="0" max="99">
          </div>
          <div class="hb-group">
            <label style="display:flex;align-items:center;gap:8px;margin-top:20px;cursor:pointer;">
              <input type="checkbox" name="card_is_active" id="acActive" value="1" checked>
              <span>Active</span>
            </label>
          </div>
        </div>

      </div>
      <div class="ac-modal-foot">
        <button type="button" class="btn-cancel" onclick="closeCardModal()">Cancel</button>
        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Card</button>
      </div>
    </form>
  </div>
</div>


<form method="POST" id="acDeleteForm" style="display:none;">
  <input type="hidden" name="action"  value="delete_agency_card">
  <input type="hidden" name="card_id" id="acDeleteId" value="">
</form>


<form method="POST" id="acToggleForm" style="display:none;">
  <input type="hidden" name="action"  value="toggle_agency_card">
  <input type="hidden" name="card_id" id="acToggleId" value="">
</form>


<script>

let agencyCards = <?php echo json_encode(array_values($agency_cards)); ?>;


const PV = {
  headline:        <?php echo json_encode($home['hero_headline']); ?>,
  subtext:         <?php echo json_encode($home['hero_subtext']); ?>,
  halign:          <?php echo json_encode($home['hero_text_align']); ?>,
  valign:          <?php echo json_encode($home['hero_text_valign']); ?>,
  overlay:         <?php echo json_encode((float)$home['hero_overlay_opacity']); ?>,
  primaryText:     <?php echo json_encode($home['primary_cta_text']); ?>,
  primaryLink:     <?php echo json_encode($home['primary_cta_link']); ?>,
  secondaryText:   <?php echo json_encode($home['secondary_cta_text']); ?>,
  secondaryLink:   <?php echo json_encode($home['secondary_cta_link']); ?>,
  getInvolvedText: <?php echo json_encode($home['get_involved_text']); ?>,
  getInvolvedLink: <?php echo json_encode($home['get_involved_link']); ?>,
};

function readPV() {
  const gid = id => document.getElementById(id);
  const gn  = n  => { const el = document.querySelector('[name="'+n+'"]'); return el ? el.value : ''; };
  return {
    headline:        gid('ph_headline')?.value  || PV.headline,
    subtext:         gid('ph_subtext')?.value   || PV.subtext,
    halign:          gid('ph_halign')?.value    || PV.halign,
    valign:          gid('ph_valign')?.value    || PV.valign,
    overlay:         parseFloat(gid('ph_overlay')?.value ?? PV.overlay),
    primaryText:     gn('primary_cta_text')     || PV.primaryText,
    primaryLink:     gn('primary_cta_link')     || PV.primaryLink,
    secondaryText:   gn('secondary_cta_text')   || PV.secondaryText,
    secondaryLink:   gn('secondary_cta_link')   || PV.secondaryLink,
    getInvolvedText: gn('get_involved_text')    || PV.getInvolvedText,
    getInvolvedLink: gn('get_involved_link')    || PV.getInvolvedLink,
  };
}

function buildPreviewHTML(d) {
  const esc = s => String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const jc = {left:'flex-start',center:'center',right:'flex-end'}[d.halign] || 'center';
  const ta = d.halign || 'center';
  const ai = d.valign || 'center';

  const activeCards = agencyCards.filter(c => c.is_active == 1 || c.is_active === true);
  const agencyHTML = activeCards.map(c => `
    <div class="agency-card">
      <div class="agency-icon"><i class="${esc(c.icon||'fas fa-star')}"></i></div>
      <h3>${esc(c.title)}</h3>
      <p>${esc(c.description)}</p>
    </div>`).join('');

  return `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--orange:#f97316;--orange-dk:#ea580c;--dark:#172033;--muted:#5f6b7a;--light:#f8fafc;--cream:#fff4ec;--white:#fff;--border:#e5e7eb;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,sans-serif;color:var(--dark);background:var(--white);line-height:1.6;}
a{text-decoration:none;color:inherit;}
.container{width:min(1120px,92%);margin:0 auto;}
header{background:#fff;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;}
.nav{display:flex;align-items:center;justify-content:space-between;height:64px;}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;}
.logo-mark{width:38px;height:38px;border-radius:50%;background:var(--orange);display:grid;place-items:center;color:#fff;font-weight:800;}
nav ul{display:flex;gap:22px;list-style:none;font-size:13px;color:var(--muted);}

/* hero */
.hero{min-height:560px;display:flex;align-items:${ai};
  background:linear-gradient(rgba(0,0,0,${d.overlay}),rgba(0,0,0,${d.overlay})),
  url("") center 25%/cover no-repeat;}
.hero-inner{width:100%;display:flex;justify-content:${jc};padding:50px 0;}
.hero-text{max-width:760px;text-align:${ta};color:#fff;}
.hero-text h1{font-size:clamp(28px,5vw,60px);line-height:1.05;margin-bottom:18px;font-weight:800;}
.hero-text p{font-size:17px;color:#f3f4f6;margin-bottom:28px;max-width:680px;${ta==='center'?'margin-left:auto;margin-right:auto;':''}}
.hero-btns{display:flex;flex-wrap:wrap;gap:14px;justify-content:${jc};}
.btn{display:inline-block;padding:11px 22px;border-radius:8px;font-weight:700;font-size:14px;transition:.2s;}
.btn-primary{background:var(--orange);color:#fff;}
.btn-outline{border:2px solid #fff;color:#fff;}

/* impact */
.impact{background:var(--light);padding:44px 0;}
.impact-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
.impact-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px 14px;text-align:center;box-shadow:0 8px 24px rgba(15,23,42,.06);}
.impact-card strong{display:block;font-size:28px;font-weight:800;color:var(--orange);margin-bottom:6px;}

/* section */
.section{padding:70px 0;}
.section-title{text-align:center;margin-bottom:38px;}
.section-title h2{font-size:30px;margin-bottom:8px;}
.section-title p{color:var(--muted);max-width:640px;margin:0 auto;font-size:15px;}

/* focus */
.focus-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.focus-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.06);text-align:center;}
.focus-icon{width:52px;height:52px;background:var(--cream);border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;color:var(--orange);font-size:20px;}
.focus-card h3{font-size:17px;margin-bottom:8px;}
.focus-card p{font-size:13px;color:var(--muted);}

/* agency */
.agency{background:var(--cream);padding:70px 0;}
.agency-grid{display:grid;grid-template-columns:repeat(${Math.min(activeCards.length||3,3)},1fr);gap:22px;margin-bottom:24px;}
.agency-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:28px;box-shadow:0 10px 28px rgba(15,23,42,.06);}
.agency-icon{width:48px;height:48px;background:var(--cream);border-radius:50%;display:grid;place-items:center;margin-bottom:14px;color:var(--orange);font-size:20px;}
.agency-card h3{font-size:21px;color:var(--orange-dk);margin-bottom:10px;}
.agency-card p{font-size:13px;color:var(--muted);line-height:1.65;}
.opportunity-callout{background:#fff;border-left:6px solid var(--orange);border-radius:14px;padding:28px 32px;box-shadow:0 10px 28px rgba(15,23,42,.06);}
.opportunity-callout h3{font-size:20px;color:var(--orange-dk);margin-bottom:8px;}
.opportunity-callout p{font-size:13px;color:var(--muted);line-height:1.75;}

/* cta */
.cta-section{background:linear-gradient(135deg,var(--orange),#fb923c);color:#fff;padding:70px 0;text-align:center;}
.cta-section h2{font-size:32px;margin-bottom:12px;}
.cta-section p{max-width:600px;margin:0 auto 24px;color:#fff7ed;}
.cta-buttons{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;}
.btn-lg{padding:13px 26px;font-size:15px;}

/* journey */
.journey-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
.journey-card{background:var(--light);border:1px solid var(--border);border-radius:14px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.06);}
.step-num{width:44px;height:44px;background:var(--orange);color:#fff;border-radius:50%;display:grid;place-items:center;font-weight:800;margin-bottom:14px;}
.journey-card h3{font-size:16px;margin-bottom:8px;}
.journey-card p{font-size:13px;color:var(--muted);}
.support-box{max-width:760px;margin:36px auto 0;background:#fff;border-left:5px solid var(--orange);border-radius:14px;padding:24px 28px;box-shadow:0 8px 22px rgba(15,23,42,.06);}
.support-box h3{color:var(--orange-dk);margin-bottom:8px;}
.support-box p{color:var(--muted);font-size:13px;}

/* testimonials */
.t-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
.t-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;box-shadow:0 6px 18px rgba(15,23,42,.05);}
.t-top{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
.t-photo{width:44px;height:44px;border-radius:50%;background:#e5e7eb;flex-shrink:0;}
.t-meta strong{display:block;font-size:13px;}
.t-meta small{color:var(--muted);font-size:11px;}
.t-quote{font-size:13px;color:#374151;font-style:italic;}

/* partners */
.partner-row{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;}
.partner-logo{background:var(--light);border:1px solid var(--border);border-radius:10px;min-height:60px;padding:10px 18px;display:grid;place-items:center;color:var(--muted);font-weight:700;font-size:12px;}

footer{background:#050505;color:#fff;padding:48px 0 20px;}
.footer-inner{display:grid;grid-template-columns:2fr 1fr 1fr 1.4fr;gap:28px;margin-bottom:32px;}
footer h4{margin-bottom:12px;}
footer p,footer li{color:#cbd5e1;font-size:13px;list-style:none;margin-bottom:6px;}
.copyright{border-top:1px solid #1f2937;padding-top:18px;text-align:center;color:#94a3b8;font-size:12px;}

@media(max-width:760px){
  .impact-grid,.focus-grid,.agency-grid,.journey-grid,.t-grid,.footer-inner{grid-template-columns:1fr;}
  nav ul{display:none;}
}
</style></head><body>

<header><div class="container nav">
  <div class="logo"><div class="logo-mark">W</div><div>Women in<br>Technology Uganda</div></div>
  <nav><ul><li>About</li><li>Programs</li><li>Impact</li><li>Get Involved</li></ul></nav>
</div></header>

<section class="hero"><div class="container hero-inner"><div class="hero-text">
  <h1>${esc(d.headline)}</h1>
  <p>${esc(d.subtext)}</p>
  <div class="hero-btns">
    ${d.primaryText  ? `<a href="${esc(d.primaryLink)}"  class="btn btn-primary">${esc(d.primaryText)}</a>`  : ''}
    ${d.secondaryText? `<a href="${esc(d.secondaryLink)}" class="btn btn-outline">${esc(d.secondaryText)}</a>`: ''}
  </div>
</div></div></section>

<section class="impact"><div class="container impact-grid">
  <div class="impact-card"><strong>20,000+</strong>Women and girls reached</div>
  <div class="impact-card"><strong>56</strong>Districts across Uganda</div>
  <div class="impact-card"><strong>10,000+</strong>Women connected to dignified work</div>
  <div class="impact-card"><strong>1,000+</strong>Entrepreneurs supported</div>
</div></section>

<section class="section"><div class="container">
  <div class="section-title"><h2>Our Focus Areas</h2><p>Explore our initiatives making a real impact across Uganda</p></div>
  <div class="focus-grid">
    <div class="focus-card"><div class="focus-icon"><i class="fas fa-laptop-code"></i></div><h3>Digital Skills</h3><p>Equipping women with market-relevant digital competencies.</p></div>
    <div class="focus-card"><div class="focus-icon"><i class="fas fa-briefcase"></i></div><h3>Workforce Development</h3><p>Pathways from training to dignified employment.</p></div>
    <div class="focus-card"><div class="focus-icon"><i class="fas fa-rocket"></i></div><h3>Entrepreneurship</h3><p>Supporting women-led businesses with mentorship &amp; finance.</p></div>
  </div>
</div></section>

<section class="agency"><div class="container">
  <div class="section-title"><h2>Skills, Agency &amp; Opportunity Pathways</h2><p>Combining technical training with agency building, personalized support, and direct connections to opportunities.</p></div>
  <div class="agency-grid">${agencyHTML || '<p style="color:var(--muted);text-align:center;grid-column:1/-1;">No active cards - add some in the Agency tab.</p>'}</div>
  <div class="opportunity-callout">
    <h3>Personalized Support &amp; Opportunity Connections</h3>
    <p>Through mentorship, AI-powered assessments and coaching, employer partnerships, and entrepreneurship support, participants receive personalized guidance tailored to their goals, current stage, and individual needs.</p>
  </div>
</div></section>

<section class="cta-section"><div class="container">
  <h2>Get Involved</h2>
  <p>Join our mission and help us create lasting change</p>
  <div class="cta-buttons">
    ${d.getInvolvedText ? `<a href="${esc(d.getInvolvedLink)}" class="btn btn-primary btn-lg">${esc(d.getInvolvedText)}</a>` : ''}
    <a href="donate.php" class="btn btn-outline btn-lg">Donate</a>
  </div>
</div></section>

<section class="section" style="background:var(--white)"><div class="container">
  <div class="section-title"><h2>The Participant Journey</h2><p>A structured pathway from skills to economic independence</p></div>
  <div class="journey-grid">
    <div class="journey-card"><div class="step-num">1</div><h3>Skills Training &amp; Agency Building</h3><p>3-6 months of intensive digital skills training combined with confidence, leadership, and decision-making development.</p></div>
    <div class="journey-card"><div class="step-num">2</div><h3>Work Experience &amp; Exposure</h3><p>Practical experience through internships, employer partnerships, and mentorship.</p></div>
    <div class="journey-card"><div class="step-num">3</div><h3>Economic Outcomes</h3><p>Participants move into employment, entrepreneurship, freelancing, or further education pathways.</p></div>
  </div>
  <div class="support-box"><h3>Continuous Mentorship &amp; Career Support</h3><p>Mentorship and career support run throughout the entire journey.</p></div>
</div></section>

<section class="section" style="background:var(--light)"><div class="container">
  <div class="section-title"><h2>In Their Own Words</h2><p>Participant stories and experiences</p></div>
  <div class="t-grid">
    <div class="t-card"><div class="t-top"><div class="t-photo"></div><div class="t-meta"><strong>Participant Name</strong><small>ElevateHer360 . Kampala</small></div></div><p class="t-quote">"WITU gave me the digital skills and confidence to pursue meaningful work."</p></div>
    <div class="t-card"><div class="t-top"><div class="t-photo"></div><div class="t-meta"><strong>Entrepreneur</strong><small>Elevate Accelerator . Gulu</small></div></div><p class="t-quote">"Through mentorship I was able to grow my business and access new markets."</p></div>
    <div class="t-card"><div class="t-top"><div class="t-photo"></div><div class="t-meta"><strong>WITU Alumna</strong><small>ElevateHer360 . Jinja</small></div></div><p class="t-quote">"The training gave me practical skills, professional support, and a pathway to income."</p></div>
  </div>
</div></section>

<section class="section"><div class="container">
  <div class="section-title"><h2>Partners</h2><p>Organizations supporting our work</p></div>
  <div class="partner-row">
    <div class="partner-logo">Google</div><div class="partner-logo">Adobe</div>
    <div class="partner-logo">UNICEF</div><div class="partner-logo">Mastercard Foundation</div>
    <div class="partner-logo">NITA-U</div><div class="partner-logo">Stanbic Bank</div>
  </div>
</div></section>

<footer><div class="container">
  <div class="footer-inner">
    <div><h4>WITU</h4><p>Advancing opportunity for women &amp; girls through technology, entrepreneurship, and digital inclusion.</p></div>
    <div><h4>Quick Links</h4><ul><li>Home</li><li>About</li><li>Programs</li><li>Impact</li></ul></div>
    <div><h4>Get Involved</h4><ul><li>Donate</li><li>Partner With Us</li><li>Volunteer</li><li>Hire Graduates</li></ul></div>
    <div><h4>Contact</h4><p>info@witu.org</p><p>+256 392 177 980</p><p>Bukoto, Kampala, Uganda</p></div>
  </div>
  <div class="copyright"> <?php echo date('Y'); ?> Women in Technology Uganda. All rights reserved.</div>
</div></footer>

</body></html>`;
}

function pvUpdate() {
  const d    = readPV();
  const html = buildPreviewHTML(d);
  const blob = new Blob([html], {type:'text/html'});
  document.getElementById('pvFrame').src = URL.createObjectURL(blob);
}

/* tabs */
function hbTab(el, name) {
  document.querySelectorAll('.hb-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.hb-panel').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}

/* alignment */
function setHAlign(el, v) {
  document.querySelectorAll('[data-halign]').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('ph_halign').value = v;
  pvUpdate();
}
function setVAlign(el, v) {
  document.querySelectorAll('[data-valign]').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('ph_valign').value = v;
  pvUpdate();
}

/* viewport */
function setViewport(vp, btn) {
  document.querySelectorAll('.hb-vp-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const frame = document.getElementById('pvFrame');
  if (vp==='desktop')      { frame.style.width='100%';  frame.style.height='100%'; }
  else if (vp==='tablet')  { frame.style.width='768px'; frame.style.height='1024px'; }
  else                     { frame.style.width='390px'; frame.style.height='844px'; }
}

/* -- Agency card modal -- */
function openCardModal(card) {
  document.getElementById('acCardId').value    = card ? card.id          : 0;
  document.getElementById('acTitle').value     = card ? card.title       : '';
  document.getElementById('acDesc').value      = card ? card.description : '';
  document.getElementById('acSort').value      = card ? card.sort_order  : 0;
  document.getElementById('acActive').checked  = card ? (card.is_active == 1) : true;
  const icon = card ? (card.icon || 'fas fa-star') : 'fas fa-star';
  document.getElementById('acIcon').value = icon;
  document.querySelectorAll('.icon-opt').forEach(o => {
    o.classList.toggle('active', o.dataset.icon === icon);
  });
  document.getElementById('acModalTitle').textContent = card ? 'Edit Card' : 'Add Card';
  document.getElementById('acModalOverlay').style.display = 'flex';
}
function closeCardModal() {
  document.getElementById('acModalOverlay').style.display = 'none';
}
function pickIcon(el, icon) {
  document.querySelectorAll('.icon-opt').forEach(o => o.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('acIcon').value = icon;
}

/* delete */
function deleteCard(id, title) {
  if (!confirm('Delete "' + title + '"?')) return;
  document.getElementById('acDeleteId').value = id;
  document.getElementById('acDeleteForm').submit();
}
/* toggle */
function toggleCard(id) {
  document.getElementById('acToggleId').value = id;
  document.getElementById('acToggleForm').submit();
}

/* close modal on overlay click */
document.getElementById('acModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeCardModal();
});
document.addEventListener('keydown', e => { if (e.key==='Escape') closeCardModal(); });

/* jump to agency tab if URL has #agency */
if (location.hash === '#agency') {
  hbTab(document.getElementById('agencyTab'), 'agency');
}

pvUpdate();
</script>

<?php include 'layout/footer.php'; ?>