<?php
/**
 * Settings - shop details (name, phone, logo, receipt text, invoice terms) and the dropdown lists used by
 * Add Mobile. Everything is stored in the database tables `settings` and `app_options`; no data is hardcoded
 * in the code. Admin only.
 */
session_start();
$page_title = 'Settings';
$base_url = '../../';
require_once '../../includes/functions.php';

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    redirect('../../index.php', 'Only the admin can open Settings.', 'error');
}

// Metadata only (labels / input rules) - the VALUES live in the database.
$FIELDS = [
    'shop_name'      => ['Shop name', 'text', 100, 1, 'shop'],
    'shop_tagline'   => ['Tagline (printed under the name)', 'text', 100, 2, 'shop'],
    'shop_phone'     => ['Phone / Cell', 'text', 40, 3, 'shop'],
    'shop_address'   => ['Address', 'text', 200, 4, 'shop'],
    'receipt_thanks' => ['Thank-you line on receipts', 'text', 120, 6, 'receipt'],
    'invoice_terms'  => ['Invoice terms (one per line)', 'textarea', 2000, 7, 'receipt'],
];
$LISTS = [
    'phone_condition' => 'Phone condition',
    'pta_status'      => 'PTA status',
    'warranty'        => 'Warranty',
    'accessory'       => 'Accessories',
    'ram'             => 'RAM',
    'storage'         => 'Storage',
    'network_note'    => 'SIM / network notes',
];

function save_setting(PDO $pdo, $key, $value, $label, $group, $sort) {
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value, label, group_name, sort_order) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value, $label, $group, $sort]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_shop') {
        foreach ($FIELDS as $key => [$label, $type, $max, $sort, $group]) {
            $v = trim((string)($_POST[$key] ?? ''));
            if (mb_strlen($v) > $max) redirect('index.php', "$label is too long (max $max characters).", 'error');
            if ($key === 'shop_phone' && $v !== '' && !preg_match('/^[0-9+\-\s(),\/]{5,40}$/', $v)) redirect('index.php', 'Phone number looks invalid.', 'error');
            save_setting($pdo, $key, $v, $label, $group, $sort);
        }
        // logo: remove, or upload a new image
        if (!empty($_POST['remove_logo'])) {
            save_setting($pdo, 'shop_logo', '', 'Logo image path', 'shop', 5);
        } elseif (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['logo'];
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) redirect('index.php', 'Logo upload failed. Try again.', 'error');
            if ($f['size'] > 1024 * 1024) redirect('index.php', 'Logo must be 1 MB or smaller.', 'error');
            $info = @getimagesize($f['tmp_name']);
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
            if (!$ext) redirect('index.php', 'Logo must be a PNG, JPG or WEBP image.', 'error');
            $dir = __DIR__ . '/../../assets/img';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = 'logo_' . date('Ymd_His') . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) redirect('index.php', 'Could not save the logo file.', 'error');
            save_setting($pdo, 'shop_logo', 'assets/img/' . $name, 'Logo image path', 'shop', 5);
        }
        redirect('index.php', 'Settings saved.');
    }

    if ($action === 'add_option') {
        $type = $_POST['option_type'] ?? '';
        $val = trim((string)($_POST['option_value'] ?? ''));
        if (!isset($LISTS[$type])) redirect('index.php#lists', 'Unknown list.', 'error');
        if ($val === '' || mb_strlen($val) > 120) redirect('index.php#lists', 'Enter a value (max 120 characters).', 'error');
        $st = $pdo->prepare("SELECT id FROM app_options WHERE option_type = ? AND option_value = ?"); $st->execute([$type, $val]);
        if ($st->fetchColumn()) redirect('index.php#lists', "'$val' is already in " . $LISTS[$type] . '.', 'error');
        $mx = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM app_options WHERE option_type = ?"); $mx->execute([$type]);
        $pdo->prepare("INSERT INTO app_options (option_type, option_value, sort_order) VALUES (?,?,?)")->execute([$type, $val, (int)$mx->fetchColumn() + 10]);
        redirect('index.php#lists', "Added to " . $LISTS[$type] . '.');
    }

    if ($action === 'delete_option') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM app_options WHERE id = ?")->execute([$id]);
        redirect('index.php#lists', 'Removed. Phones already saved with this value keep it.');
    }
    redirect('index.php', 'Unknown action.', 'error');
}

$vals = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $r) $vals[$r['setting_key']] = (string)$r['setting_value'];
$opts = [];
foreach ($pdo->query("SELECT id, option_type, option_value FROM app_options ORDER BY option_type, sort_order, id") as $r) $opts[$r['option_type']][] = $r;
$logo = $vals['shop_logo'] ?? '';

require_once '../../includes/header.php';
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>

<div class="row">
  <div class="col-lg-6 mb-4">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-store"></i> Shop Details</h6></div>
      <div class="card-body">
        <p class="small text-muted">These show on the sidebar, login page, invoices, receipts and ledger prints. An empty field is simply not printed.</p>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
          <input type="hidden" name="action" value="save_shop">
          <?php foreach ($FIELDS as $key => [$label, $type, $max]): ?>
            <div class="form-group">
              <label><?= $h($label) ?></label>
              <?php if ($type === 'textarea'): ?>
                <textarea name="<?= $key ?>" class="form-control" rows="4" maxlength="<?= $max ?>"><?= $h($vals[$key] ?? '') ?></textarea>
              <?php else: ?>
                <input type="text" name="<?= $key ?>" class="form-control" maxlength="<?= $max ?>" value="<?= $h($vals[$key] ?? '') ?>"<?= $key === 'shop_name' ? ' placeholder="HS Mobiles"' : '' ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <div class="form-group">
            <label>Logo</label>
            <?php if ($logo !== '' && is_file(__DIR__ . '/../../' . $logo)): ?>
              <div class="mb-2"><img src="../../<?= $h($logo) ?>" alt="<?= $h($vals['shop_name'] ?: 'HS Mobiles') ?> Logo" style="max-height:70px;max-width:240px;width:auto;height:auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:6px;"></div>
              <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="rmLogo" name="remove_logo" value="1"><label class="custom-control-label" for="rmLogo">Remove logo</label></div>
            <?php else: ?><div class="small text-muted mb-1">No logo saved.</div><?php endif; ?>
            <input type="file" name="logo" class="form-control-file" accept="image/png,image/jpeg,image/webp">
            <small class="text-muted">PNG / JPG / WEBP, up to 1 MB.</small>
          </div>
          <button class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6 mb-4" id="lists">
    <div class="card shadow">
      <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Add Mobile - Dropdown Lists</h6></div>
      <div class="card-body">
        <p class="small text-muted">The choices shown on the Add Mobile page. Removing a choice only removes it from the dropdown.</p>
        <?php foreach ($LISTS as $type => $title): ?>
          <div class="mb-3 pb-3 border-bottom">
            <div class="font-weight-bold mb-1"><?= $h($title) ?> <span class="badge badge-light border"><?= count($opts[$type] ?? []) ?></span></div>
            <div class="mb-2">
              <?php foreach ($opts[$type] ?? [] as $o): ?>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="delete_option"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <span class="badge badge-pill badge-secondary p-2 mb-1"><?= $h($o['option_value']) ?>
                    <button class="btn btn-link btn-sm p-0 ml-1 text-white" title="Remove" onclick="return confirm('Remove this choice?');"><i class="fas fa-times"></i></button></span></form>
              <?php endforeach; ?>
              <?php if (empty($opts[$type])): ?><span class="small text-muted">No choices saved.</span><?php endif; ?>
            </div>
            <form method="post" class="form-inline"><input type="hidden" name="action" value="add_option"><input type="hidden" name="option_type" value="<?= $h($type) ?>">
              <input type="text" name="option_value" class="form-control form-control-sm mr-2" maxlength="120" placeholder="Add new..." style="min-width:200px" required>
              <button class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Add</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
