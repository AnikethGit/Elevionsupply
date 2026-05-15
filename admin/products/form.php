<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin.php';
require_admin();

$id      = (int)get('id');
$product = null;
$specs   = [];

if ($id) {
    $stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) redirect('/admin/products.php');
    $specs             = json_decode($product['specifications'] ?? '{}', true) ?: [];
    $product['images'] = json_decode($product['images'] ?? '[]', true) ?: [];
}

$categories = get_categories();
$isEdit     = (bool)$product;
$pageTitle  = $isEdit ? 'Edit: '.e($product['name']) : 'Add Product';
$extraCss   = ['account.css','admin.css'];
require_once '../../includes/header.php';
?>
<div class="page-hero"><h1><?= $isEdit ? 'Edit Product' : 'Add New Product' ?></h1><p>Admin Panel</p></div>
<div class="admin-wrap">
    <a href="/admin/products.php" style="display:inline-flex;align-items:center;gap:8px;color:var(--gray-500);font-size:13px;font-weight:600;margin-bottom:20px"><i class="fas fa-arrow-left"></i> Back to Products</a>

    <div id="alertBox" style="display:none;margin-bottom:16px"></div>

    <form id="productForm">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $product['id'] ?>"><?php endif; ?>

        <div class="create-layout">
            <!-- LEFT -->
            <div class="create-main">

                <!-- Images -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-images"></i> Product Images
                        <span style="margin-left:auto;font-size:12px;color:var(--gray-500);font-weight:400">First image = primary. JPG, PNG, WEBP, GIF · Max 5 MB each</span>
                    </div>

                    <!-- Drop zone -->
                    <div id="dropZone" onclick="document.getElementById('imgPicker').click()"
                         style="border:2px dashed var(--gray-300);border-radius:var(--radius-lg);padding:32px;text-align:center;cursor:pointer;transition:all var(--transition);margin-bottom:16px;background:var(--gray-50)">
                        <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:var(--gray-300);display:block;margin-bottom:10px"></i>
                        <p style="font-size:14px;color:var(--gray-500);margin:0">Click or drag &amp; drop images here</p>
                        <input type="file" id="imgPicker" accept="image/*" multiple style="display:none">
                    </div>

                    <!-- Preview grid -->
                    <div id="imgGrid" style="display:flex;flex-wrap:wrap;gap:10px">
                        <?php foreach ($product['images'] ?? [] as $i => $img): ?>
                        <div class="img-thumb" data-path="<?= e($img) ?>">
                            <img src="/<?= e(ltrim($img,'/')) ?>" alt="image <?= $i+1 ?>">
                            <button type="button" onclick="removeImg(this)" title="Remove"><i class="fas fa-times"></i></button>
                            <?php if ($i===0): ?><span class="primary-badge">Primary</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p id="imgHint" style="font-size:12px;color:var(--gray-500);margin-top:8px;display:<?= empty($product['images'])? 'block':'none' ?>">
                        No images yet — product will show a category emoji as placeholder.
                    </p>
                    <div id="uploadProgress" style="display:none;margin-top:10px">
                        <div style="height:4px;background:var(--gray-200);border-radius:2px;overflow:hidden">
                            <div id="uploadBar" style="height:100%;background:var(--accent);width:0;transition:width .3s"></div>
                        </div>
                        <p id="uploadStatus" style="font-size:12px;color:var(--gray-500);margin-top:4px"></p>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-tag"></i> Basic Information</div>
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" id="nameInput" value="<?= e($product['name'] ?? '') ?>"
                               placeholder="e.g. Samsung Galaxy S24 Ultra" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>SKU * <small style="color:var(--gray-500)">(unique identifier)</small></label>
                            <input type="text" name="sku" id="skuInput" value="<?= e($product['sku'] ?? '') ?>"
                                   placeholder="e.g. SAM-S24U-256" required style="font-family:monospace">
                        </div>
                        <div class="form-group">
                            <label>URL Slug * <small style="color:var(--gray-500)">(auto-generated)</small></label>
                            <input type="text" name="slug" id="slugInput" value="<?= e($product['slug'] ?? '') ?>"
                                   placeholder="e.g. samsung-galaxy-s24-ultra" required style="font-family:monospace">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"
                                  style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:var(--radius-md);font-family:var(--font-body);font-size:14px;resize:vertical"
                                  placeholder="Product description shown on the product page…"><?= e($product['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Pricing & Stock -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-dollar-sign"></i> Pricing &amp; Stock</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Regular Price ($) *</label>
                            <input type="number" name="price" step="0.01" min="0"
                                   value="<?= $product['price'] ?? '' ?>" placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label>Sale Price ($) <small style="color:var(--gray-500)">(optional)</small></label>
                            <input type="number" name="sale_price" step="0.01" min="0"
                                   value="<?= $product['sale_price'] ?? '' ?>" placeholder="Leave blank for no sale">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock_quantity" min="0"
                               value="<?= $product['stock_quantity'] ?? '0' ?>" required>
                    </div>
                </div>

                <!-- Specifications -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-list"></i> Specifications
                        <button type="button" onclick="addSpec()" class="btn btn-outline btn-sm" style="margin-left:auto">
                            <i class="fas fa-plus"></i> Add Row
                        </button>
                    </div>
                    <div id="specsContainer">
                        <?php if (empty($specs)): ?>
                        <!-- one blank row by default -->
                        <div class="spec-row-input form-row" style="margin-bottom:8px">
                            <div class="form-group"><input type="text" name="spec_key[]"   placeholder="e.g. Display" style="font-size:13px"></div>
                            <div class="form-group" style="display:flex;gap:8px">
                                <input type="text" name="spec_val[]" placeholder="e.g. 6.8-inch AMOLED" style="font-size:13px;flex:1">
                                <button type="button" onclick="removeSpec(this)" class="btn btn-sm" style="border:1px solid #e53e3e;color:#e53e3e;background:none;flex-shrink:0"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <?php else: foreach ($specs as $k => $v): ?>
                        <div class="spec-row-input form-row" style="margin-bottom:8px">
                            <div class="form-group"><input type="text" name="spec_key[]" value="<?= e($k) ?>" placeholder="Key" style="font-size:13px"></div>
                            <div class="form-group" style="display:flex;gap:8px">
                                <input type="text" name="spec_val[]" value="<?= e($v) ?>" placeholder="Value" style="font-size:13px;flex:1">
                                <button type="button" onclick="removeSpec(this)" class="btn btn-sm" style="border:1px solid #e53e3e;color:#e53e3e;background:none;flex-shrink:0"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="create-side">

                <!-- Organisation -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-folder"></i> Organisation</div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">— None —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($product['category_id'] ?? null)==$cat['id']?'selected':'' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Badge</label>
                        <select name="badge">
                            <option value="">— None —</option>
                            <?php foreach (['Hot','New','Sale'] as $b): ?>
                            <option value="<?= $b ?>" <?= ($product['badge'] ?? '')===$b?'selected':'' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;margin-bottom:10px">
                        <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured'])?'checked':'' ?>>
                        <span>Featured product (shown on homepage)</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" <?= !isset($product) || !empty($product['is_active'])?'checked':'' ?>>
                        <span>Active (visible in catalog)</span>
                    </label>
                </div>

                <!-- Ratings -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-star"></i> Ratings</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Rating <small>(0–5)</small></label>
                            <input type="number" name="rating" step="0.1" min="0" max="5"
                                   value="<?= $product['rating'] ?? '0' ?>" placeholder="0.0">
                        </div>
                        <div class="form-group">
                            <label>Review Count</label>
                            <input type="number" name="review_count" min="0"
                                   value="<?= $product['review_count'] ?? '0' ?>" placeholder="0">
                        </div>
                    </div>
                </div>

                <!-- Save -->
                <button type="button" onclick="saveProduct()" class="btn btn-primary" style="width:100%;padding:14px">
                    <i class="fas fa-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Product' ?>
                </button>
                <?php if ($isEdit): ?>
                <a href="/product.php?id=<?= $product['id'] ?>" target="_blank" class="btn btn-outline" style="width:100%;text-align:center;margin-top:8px">
                    <i class="fas fa-external-link-alt"></i> View on Store
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<style>
.img-thumb { position:relative; width:100px; height:100px; border-radius:var(--radius-md); overflow:hidden; border:2px solid var(--gray-200); flex-shrink:0; }
.img-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.img-thumb button { position:absolute; top:4px; right:4px; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:22px; height:22px; font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.img-thumb button:hover { background:rgba(229,62,62,.85); }
.img-thumb .primary-badge { position:absolute; bottom:0; left:0; right:0; background:var(--accent); color:var(--primary); font-size:9px; font-weight:700; text-align:center; padding:2px 0; text-transform:uppercase; }
#dropZone:hover,.drop-over { border-color:var(--accent)!important; background:rgba(86,207,225,.06)!important; }
</style>
<script>
const csrf      = document.querySelector('meta[name="csrf-token"]').content;
const nameInput = document.getElementById('nameInput');
const slugInput = document.getElementById('slugInput');

// ── Slug auto-gen ────────────────────────────────────────────────
nameInput.addEventListener('input', function() {
    if (!slugInput.dataset.manual)
        slugInput.value = this.value.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-');
});
slugInput.addEventListener('input', () => slugInput.dataset.manual = '1');

// ── Spec rows ────────────────────────────────────────────────────
function addSpec() {
    const c = document.getElementById('specsContainer');
    const d = document.createElement('div');
    d.className = 'spec-row-input form-row'; d.style.marginBottom = '8px';
    d.innerHTML = `
        <div class="form-group"><input type="text" name="spec_key[]" placeholder="e.g. Processor" style="font-size:13px"></div>
        <div class="form-group" style="display:flex;gap:8px">
            <input type="text" name="spec_val[]" placeholder="e.g. Apple A18 Pro" style="font-size:13px;flex:1">
            <button type="button" onclick="removeSpec(this)" class="btn btn-sm"
                    style="border:1px solid #e53e3e;color:#e53e3e;background:none;flex-shrink:0"><i class="fas fa-times"></i></button>
        </div>`;
    c.appendChild(d); d.querySelector('input').focus();
}
function removeSpec(btn) { btn.closest('.spec-row-input').remove(); }

// ── Image upload ─────────────────────────────────────────────────
const dropZone  = document.getElementById('dropZone');
const imgPicker = document.getElementById('imgPicker');
const imgGrid   = document.getElementById('imgGrid');

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drop-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drop-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drop-over');
    uploadFiles([...e.dataTransfer.files]);
});
imgPicker.addEventListener('change', () => { uploadFiles([...imgPicker.files]); imgPicker.value = ''; });

async function uploadFiles(files) {
    const valid = files.filter(f => f.type.startsWith('image/'));
    if (!valid.length) return;
    const prog   = document.getElementById('uploadProgress');
    const bar    = document.getElementById('uploadBar');
    const status = document.getElementById('uploadStatus');
    prog.style.display = 'block';

    for (let i = 0; i < valid.length; i++) {
        bar.style.width = Math.round(((i) / valid.length) * 100) + '%';
        status.textContent = `Uploading ${i+1} of ${valid.length}: ${valid[i].name}`;

        const fd = new FormData();
        fd.append('image', valid[i]);
        fd.append('slug',  slugInput.value || 'product');

        try {
            const res  = await fetch('/api/admin/products/upload.php', {
                method:'POST', headers:{'X-CSRF-Token': csrf}, body: fd
            });
            const data = await res.json();
            if (data.success) addThumb(data.path);
            else { showAlert(`Upload failed: ${data.message}`, 'error'); }
        } catch { showAlert('Network error during upload.', 'error'); }
    }
    bar.style.width = '100%';
    status.textContent = `Done — ${valid.length} image(s) uploaded.`;
    setTimeout(() => { prog.style.display = 'none'; bar.style.width = '0'; }, 2500);
    updateHint();
}

function addThumb(path) {
    const isPrimary = imgGrid.querySelectorAll('.img-thumb').length === 0;
    const d = document.createElement('div');
    d.className = 'img-thumb'; d.dataset.path = path;
    d.innerHTML = `<img src="/${path}" alt="product image">
        <button type="button" onclick="removeImg(this)" title="Remove"><i class="fas fa-times"></i></button>
        ${isPrimary ? '<span class="primary-badge">Primary</span>' : ''}`;
    imgGrid.appendChild(d);
    updateHint();
}

function removeImg(btn) {
    btn.closest('.img-thumb').remove();
    // Mark first remaining as primary
    const thumbs = imgGrid.querySelectorAll('.img-thumb');
    thumbs.forEach((t, i) => {
        const badge = t.querySelector('.primary-badge');
        if (i === 0 && !badge) {
            const b = document.createElement('span');
            b.className = 'primary-badge'; b.textContent = 'Primary';
            t.appendChild(b);
        } else if (i > 0 && badge) badge.remove();
    });
    updateHint();
}

function updateHint() {
    const hint = document.getElementById('imgHint');
    hint.style.display = imgGrid.querySelectorAll('.img-thumb').length ? 'none' : 'block';
}

// ── Save ─────────────────────────────────────────────────────────
async function saveProduct() {
    const form   = document.getElementById('productForm');
    const fd     = new FormData(form);
    const body   = {};
    const keys   = fd.getAll('spec_key[]');
    const vals   = fd.getAll('spec_val[]');
    const specs  = {};
    keys.forEach((k,i) => { if (k.trim()) specs[k.trim()] = vals[i]?.trim() ?? ''; });

    for (const [k,v] of fd.entries()) {
        if (k === 'spec_key[]' || k === 'spec_val[]') continue;
        body[k] = v;
    }
    body.specifications = specs;
    body.is_featured = form.querySelector('[name=is_featured]')?.checked ? 1 : 0;
    body.is_active   = form.querySelector('[name=is_active]')?.checked   ? 1 : 0;

    // Collect current image paths in display order
    body.images = [...imgGrid.querySelectorAll('.img-thumb')]
                    .map(d => d.dataset.path).filter(Boolean);

    try {
        const res  = await fetch('/api/admin/products/save.php', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) window.location.href = '/admin/products.php?saved=1';
        else showAlert(data.message || 'Save failed.', 'error');
    } catch { showAlert('Network error.', 'error'); }
}

function showAlert(msg, type) {
    const b = document.getElementById('alertBox');
    b.className = 'alert alert-' + (type==='error'?'error':'success');
    b.innerHTML = `<i class="fas fa-${type==='error'?'exclamation-circle':'check-circle'}"></i> ${msg}`;
    b.style.display = 'flex';
    window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<?php require_once '../../includes/footer.php'; ?>
