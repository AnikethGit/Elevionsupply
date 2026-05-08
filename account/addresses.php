<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_login();
$user = auth_user();

// Handle form submit
if (is_post()) {
    verify_csrf_form('/account/addresses.php');

    $action = post('action');
    if ($action === 'add' || $action === 'edit') {
        $addrId = (int)post('addr_id');
        $data   = [post('type'), post('first_name'), post('last_name'), post('street_address'), post('apt_suite'), post('city'), post('state_province'), post('postal_code'), post('country'), post('phone'), post('is_default') ? 1 : 0];
        if ($action === 'add') {
            $stmt = db()->prepare("INSERT INTO addresses (user_id,type,first_name,last_name,street_address,apt_suite,city,state_province,postal_code,country,phone,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$user['id'], ...$data]);
            $addrId = (int)db()->lastInsertId(); // capture immediately after INSERT
        } else {
            $stmt = db()->prepare("UPDATE addresses SET type=?,first_name=?,last_name=?,street_address=?,apt_suite=?,city=?,state_province=?,postal_code=?,country=?,phone=?,is_default=? WHERE id=? AND user_id=?");
            $stmt->execute([...$data, $addrId, $user['id']]);
        }
        if (post('is_default')) db()->prepare("UPDATE addresses SET is_default=0 WHERE user_id=? AND id!=?")->execute([$user['id'], $addrId]);
        redirect('/account/addresses.php?saved=1');
    } elseif ($action === 'delete') {
        db()->prepare("DELETE FROM addresses WHERE id=? AND user_id=?")->execute([(int)post('addr_id'), $user['id']]);
        redirect('/account/addresses.php');
    }
}

$stmt = db()->prepare("SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC");
$stmt->execute([$user['id']]);
$addresses = $stmt->fetchAll();

$editAddr  = null;
if (get('edit')) {
    $stmt = db()->prepare("SELECT * FROM addresses WHERE id=? AND user_id=?");
    $stmt->execute([(int)get('edit'), $user['id']]);
    $editAddr = $stmt->fetch() ?: null;
}

$pageTitle = 'My Addresses';
$extraCss  = ['account.css'];
require_once '../includes/header.php';
$countries = ['United States','Canada','United Kingdom','Australia','Germany','France','India','Japan'];
?>
<div class="page-hero"><h1>My Addresses</h1><p>Manage your shipping and billing addresses</p></div>
<div class="sidebar-layout">
    <aside>
        <nav class="sidebar-nav">
            <a href="/account/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/account/orders.php"><i class="fas fa-box"></i> Orders</a>
            <a href="/account/addresses.php" class="active"><i class="fas fa-map-marker-alt"></i> Addresses</a>
            <a href="/account/settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>
    <main class="account-main">
        <?php if (get('saved')): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Address saved successfully!</div><?php endif; ?>

        <?php if (get('new') || $editAddr): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-map-marker-alt"></i> <?= $editAddr ? 'Edit Address' : 'Add New Address' ?></div>
            <form method="POST" class="addr-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editAddr ? 'edit' : 'add' ?>">
                <?php if ($editAddr): ?><input type="hidden" name="addr_id" value="<?= $editAddr['id'] ?>"><?php endif; ?>
                <div class="form-group"><label>Type</label>
                    <select name="type"><?php foreach(['shipping'=>'Shipping','billing'=>'Billing','both'=>'Both'] as $v=>$l): ?><option value="<?=$v?>" <?=($editAddr['type']??'')===$v?'selected':''?>><?=$l?></option><?php endforeach; ?></select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?= e($editAddr['first_name'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?= e($editAddr['last_name'] ?? '') ?>" required></div>
                </div>
                <div class="form-group"><label>Street Address *</label><input type="text" name="street_address" value="<?= e($editAddr['street_address'] ?? '') ?>" required></div>
                <div class="form-group"><label>Apt, Suite, etc.</label><input type="text" name="apt_suite" value="<?= e($editAddr['apt_suite'] ?? '') ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label>City *</label><input type="text" name="city" value="<?= e($editAddr['city'] ?? '') ?>" required></div>
                    <div class="form-group"><label>State / Province</label><input type="text" name="state_province" value="<?= e($editAddr['state_province'] ?? '') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Postal Code *</label><input type="text" name="postal_code" value="<?= e($editAddr['postal_code'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Country</label><select name="country"><?php foreach($countries as $c): ?><option value="<?=$c?>" <?=($editAddr['country']??'United States')===$c?'selected':''?>><?=$c?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= e($editAddr['phone'] ?? '') ?>"></div>
                <label class="checkbox-label"><input type="checkbox" name="is_default" <?=($editAddr['is_default']??0)?'checked':''?>> Set as default address</label>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
                    <a href="/account/addresses.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Address</button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <a href="?new=1" class="btn btn-primary" style="align-self:flex-start;display:inline-flex;margin-bottom:4px"><i class="fas fa-plus"></i> Add New Address</a>
        <?php endif; ?>

        <?php if (empty($addresses) && !get('new') && !$editAddr): ?>
        <div class="empty-state"><div class="empty-icon">📍</div><h3>No addresses saved</h3><p>Add a shipping or billing address to speed up checkout.</p></div>
        <?php else: ?>
        <div class="addr-grid">
            <?php foreach ($addresses as $addr): ?>
            <div class="addr-card <?= $addr['is_default'] ? 'default' : '' ?>">
                <?php if ($addr['is_default']): ?><span class="default-badge">Default</span><?php endif; ?>
                <div class="addr-type"><i class="fas fa-<?= $addr['type']==='billing'?'credit-card':'truck' ?>"></i> <?= ucfirst($addr['type']) ?></div>
                <div class="addr-body">
                    <strong><?= e($addr['first_name']) ?> <?= e($addr['last_name']) ?></strong>
                    <p><?= e($addr['street_address']) ?><?= $addr['apt_suite']?', '.e($addr['apt_suite']):'' ?></p>
                    <p><?= e($addr['city']) ?><?= $addr['state_province']?', '.e($addr['state_province']):'' ?> <?= e($addr['postal_code']) ?></p>
                    <p><?= e($addr['country']) ?></p>
                    <?php if ($addr['phone']): ?><p><?= e($addr['phone']) ?></p><?php endif; ?>
                </div>
                <div class="addr-actions">
                    <a href="?edit=<?= $addr['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this address?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="addr_id" value="<?= $addr['id'] ?>">
                        <button class="btn btn-sm" style="border:1px solid #e53e3e;color:#e53e3e;background:none"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once '../includes/footer.php'; ?>
