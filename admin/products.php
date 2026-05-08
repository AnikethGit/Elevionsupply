<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/admin.php';
require_admin();

$search   = get('search');
$catFilter = get('category', 'all');
$statusFilter = get('status', 'all');
$page     = max(1, (int)get('page', 1));
$limit    = 20;
$offset   = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($catFilter !== 'all') { $where[] = 'p.category_id = ?'; $params[] = (int)$catFilter; }
if ($statusFilter === 'active')   { $where[] = 'p.is_active = 1'; }
if ($statusFilter === 'inactive') { $where[] = 'p.is_active = 0'; }
if ($statusFilter === 'featured') { $where[] = 'p.is_featured = 1'; }
if ($statusFilter === 'low_stock'){ $where[] = 'p.stock_quantity <= 5'; }

$w = implode(' AND ', $where);

$cnt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $w");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();

$stmt = db()->prepare("SELECT p.*, c.name AS category_name
    FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE $w ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = get_categories();
$pages = (int)ceil($total / $limit);

// Stats
$stats = db()->query("SELECT
    COUNT(*) total,
    SUM(is_active=1) active,
    SUM(is_featured=1) featured,
    SUM(stock_quantity=0) out_of_stock
    FROM products")->fetch();

$pageTitle = 'Admin — Products';
$extraCss  = ['account.css','admin.css'];
require_once '../includes/header.php';
?>
<div class="page-hero"><h1>Product Management</h1><p>Admin Panel</p></div>
<div class="admin-wrap">

    <!-- Admin nav tabs -->
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:20px">
        <a href="/admin/orders.php"   class="btn btn-outline btn-sm"><i class="fas fa-receipt"></i> Orders</a>
        <a href="/admin/products.php" class="btn btn-outline btn-sm" style="background:var(--primary);color:#fff;border-color:var(--primary)"><i class="fas fa-box-open"></i> Products</a>
    </div>
    <?php if (get('saved')): ?><div class="alert alert-success" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> Product saved successfully!</div><?php endif; ?>
    <!-- Top bar -->
    <div class="admin-topbar">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <form method="GET" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="category" value="<?= e($catFilter) ?>">
                <input type="hidden" name="status"   value="<?= e($statusFilter) ?>">
                <div style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid var(--gray-200);border-radius:var(--radius-md);background:var(--white)">
                    <i class="fas fa-search" style="color:var(--gray-500);font-size:12px"></i>
                    <input type="text" name="search" placeholder="Name or SKU…" value="<?= e($search) ?>"
                           style="border:none;outline:none;font-size:13px;width:200px">
                </div>
                <select name="category" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:var(--radius-md);font-size:13px">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= (int)$catFilter===$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($search || $catFilter !== 'all' || $statusFilter !== 'all'): ?>
                <a href="/admin/products.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <a href="/admin/products/form.php" class="btn btn-accent"><i class="fas fa-plus"></i> Add Product</a>
    </div>

    <!-- Stats -->
    <div class="admin-stats">
        <div class="admin-stat"><div class="label">Total Products</div><div class="value"><?= number_format($stats['total']) ?></div></div>
        <div class="admin-stat"><div class="label">Active</div><div class="value"><?= (int)$stats['active'] ?></div></div>
        <div class="admin-stat"><div class="label">Featured</div><div class="value"><?= (int)$stats['featured'] ?></div></div>
        <div class="admin-stat"><div class="label">Out of Stock</div><div class="value" style="color:<?= (int)$stats['out_of_stock']>0?'#e53e3e':'inherit' ?>"><?= (int)$stats['out_of_stock'] ?></div></div>
    </div>

    <!-- Status tabs -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
        <?php foreach (['all'=>'All','active'=>'Active','inactive'=>'Inactive','featured'=>'Featured','low_stock'=>'Low Stock'] as $k=>$l): ?>
        <a href="?status=<?= $k ?><?= $search?'&search='.urlencode($search):'' ?><?= $catFilter!=='all'?'&category='.$catFilter:'' ?>"
           class="filter-tab <?= $statusFilter===$k?'active':'' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="admin-table-wrap">
        <?php if (empty($products)): ?>
        <div class="empty-state"><div class="empty-icon">📦</div><h3>No products found</h3><a href="/admin/products/form.php" class="btn btn-primary" style="margin-top:12px">Add First Product</a></div>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr>
                <th style="width:40px"></th>
                <th>Product</th><th>SKU</th><th>Category</th>
                <th style="text-align:right">Price</th><th style="text-align:center">Stock</th>
                <th style="text-align:center">Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php
            $icons = ['Smartphones'=>'📱','Earbuds & Audio'=>'🎧','Laptops'=>'💻','Computer Parts'=>'🖥️','Accessories'=>'🔌','Wearables'=>'⌚'];
            foreach ($products as $p):
            ?>
            <tr>
                <td style="font-size:22px;text-align:center"><?= $icons[$p['category_name']] ?? '📦' ?></td>
                <td>
                    <strong style="display:block"><?= e($p['name']) ?></strong>
                    <small style="color:var(--gray-500)"><?= $p['badge'] ? '<span style="background:var(--accent);color:var(--primary);border-radius:4px;padding:1px 6px;font-size:10px;font-weight:700">'.e($p['badge']).'</span>' : '' ?> <?= $p['is_featured']?'⭐':'' ?></small>
                </td>
                <td style="font-family:monospace;font-size:12px;color:var(--gray-500)"><?= e($p['sku']) ?></td>
                <td><?= e($p['category_name'] ?? '—') ?></td>
                <td style="text-align:right" class="num">
                    <?php if ($p['sale_price']): ?>
                    <span style="text-decoration:line-through;color:var(--gray-500);font-size:11px"><?= money($p['price']) ?></span><br>
                    <span style="color:#276749"><?= money($p['sale_price']) ?></span>
                    <?php else: echo money($p['price']); endif; ?>
                </td>
                <td style="text-align:center">
                    <?php if ($p['stock_quantity'] == 0): ?>
                    <span class="badge badge-red">Out of Stock</span>
                    <?php elseif ($p['stock_quantity'] <= 5): ?>
                    <span class="badge badge-orange"><?= $p['stock_quantity'] ?> left</span>
                    <?php else: ?>
                    <span style="font-size:13px;color:#276749;font-weight:600"><?= $p['stock_quantity'] ?></span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center">
                    <form method="POST" action="/api/admin/products/toggle.php" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="field" value="is_active">
                        <button type="submit" class="btn btn-sm" style="padding:4px 10px;border:1px solid <?= $p['is_active']?'#48bb78':'#e53e3e' ?>;color:<?= $p['is_active']?'#276749':'#c53030' ?>;background:<?= $p['is_active']?'rgba(72,187,120,.1)':'rgba(229,62,62,.1)' ?>">
                            <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                        </button>
                    </form>
                </td>
                <td style="white-space:nowrap">
                    <a href="/admin/products/form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
                    <form method="POST" action="/api/admin/products/delete.php" style="display:inline"
                          onsubmit="return confirm('Delete «<?= e(addslashes($p['name'])) ?>»? This cannot be undone.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="border:1px solid #e53e3e;color:#e53e3e;background:none"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination" style="margin-top:16px">
        <?php if ($page > 1): ?><a href="?page=<?=$page-1?>&status=<?=$statusFilter?>&category=<?=$catFilter?>&search=<?=urlencode($search)?>" class="page-btn">← Prev</a><?php endif; ?>
        <span style="font-size:13px;color:var(--gray-500)">Page <?=$page?> of <?=$pages?></span>
        <?php if ($page < $pages): ?><a href="?page=<?=$page+1?>&status=<?=$statusFilter?>&category=<?=$catFilter?>&search=<?=urlencode($search)?>" class="page-btn">Next →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
