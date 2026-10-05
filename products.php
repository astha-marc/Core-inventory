<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();
$admin = is_admin();

// ---------- Handle form submissions (admin only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$admin) access_denied();               // backend role check
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $id    = (int)($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $sku   = strtoupper(trim($_POST['sku'] ?? ''));
            $cat   = (int)($_POST['category_id'] ?? 0);
            $sup   = (int)($_POST['supplier_id'] ?? 0);
            $price = $_POST['price'] ?? '';
            $qty   = $_POST['quantity'] ?? '0';
            $min   = $_POST['minimum_stock'] ?? '';
            $desc  = trim($_POST['description'] ?? '');
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

            // Server-side validation
            $errors = [];
            if ($name === '' || $sku === '') $errors[] = 'Product name and SKU are required.';
            if (!is_numeric($price) || $price <= 0) $errors[] = 'Price must be a positive number.';
            if (!ctype_digit((string)$min)) $errors[] = 'Minimum stock must be 0 or more.';
            if ($id === 0 && !ctype_digit((string)$qty)) $errors[] = 'Initial quantity must be 0 or more.';

            $c = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?"); $c->execute([$cat]);
            if (!$c->fetchColumn()) $errors[] = 'Please select a valid category.';
            $s = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE id = ?"); $s->execute([$sup]);
            if (!$s->fetchColumn()) $errors[] = 'Please select a valid supplier.';

            $u = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?"); $u->execute([$sku, $id]);
            if ($u->fetchColumn()) $errors[] = 'This SKU already exists. SKU must be unique.';

            if ($errors) {
                flash('danger', implode('<br>', $errors));
            } elseif ($id > 0) {
                // Quantity is NOT edited here - it only changes through Stock In / Stock Out
                $pdo->prepare("UPDATE products SET name=?, sku=?, category_id=?, supplier_id=?, price=?,
                               minimum_stock=?, description=?, status=? WHERE id=?")
                    ->execute([$name, $sku, $cat, $sup, $price, $min, $desc, $status, $id]);
                flash('success', 'Product updated successfully.');
            } else {
                $pdo->prepare("INSERT INTO products (name, sku, category_id, supplier_id, price, quantity,
                               minimum_stock, description, status) VALUES (?,?,?,?,?,0,?,?,?)")
                    ->execute([$name, $sku, $cat, $sup, $price, $min, $desc, $status]);
                $newId = (int)$pdo->lastInsertId();
                // Opening stock is saved as a normal STOCK_IN transaction
                if ((int)$qty > 0) {
                    record_stock($pdo, $newId, (int)$_SESSION['user_id'], 'STOCK_IN', (int)$qty,
                                 'Opening stock', 'Initial stock when product was added', date('Y-m-d H:i:s'));
                }
                flash('success', 'Product added successfully.');
            }
        } elseif ($action === 'toggle') {
            // "Delete" = deactivate, so old transactions are never broken
            $pdo->prepare("UPDATE products SET status = IF(status='active','inactive','active') WHERE id = ?")
                ->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Product status changed.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'Database error. Please try again.');
    } catch (Exception $ex) {
        flash('danger', $ex->getMessage());
    }
    header('Location: products.php');
    exit;
}

// ---------- Search and filters ----------
$q  = trim($_GET['q'] ?? '');
$fc = (int)($_GET['category'] ?? 0);
$fs = $_GET['stock'] ?? '';
$where = []; $params = [];
if (!$admin) $where[] = "p.status = 'active'";         // staff only see active products
if ($q !== '') { $where[] = "(p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($fc > 0)   { $where[] = "p.category_id = ?"; $params[] = $fc; }
if ($fs === 'out') $where[] = "p.quantity = 0";
if ($fs === 'low') $where[] = "p.quantity > 0 AND p.quantity <= p.minimum_stock";
if ($fs === 'in')  $where[] = "p.quantity > p.minimum_stock";

$sql = "SELECT p.*, c.name AS category_name, s.name AS supplier_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN suppliers s ON s.id = p.supplier_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY p.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();

$page_title = 'Products';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Products</h4>
  <?php if ($admin): ?>
    <button class="btn btn-primary" data-add data-title="Add Product" data-bs-toggle="modal" data-bs-target="#productModal">
      <i class="bi bi-plus-lg"></i> Add Product</button>
  <?php endif; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search name or SKU" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="category" class="form-select">
    <option value="0">All categories</option>
    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $fc == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-md-3"><select name="stock" class="form-select">
    <option value="">All stock levels</option>
    <option value="in"  <?= $fs === 'in'  ? 'selected' : '' ?>>In Stock</option>
    <option value="low" <?= $fs === 'low' ? 'selected' : '' ?>>Low Stock</option>
    <option value="out" <?= $fs === 'out' ? 'selected' : '' ?>>Out of Stock</option>
  </select></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-secondary">Filter</button><a href="products.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>ID</th><th>Name</th><th>SKU</th><th>Category</th><th>Supplier</th><th>Price</th><th>Qty</th><th>Min</th><th>Stock</th>
    <?php if ($admin): ?><th>Status</th><?php endif; ?><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($products as $p):
      $p['stock_label'] = stock_label((int)$p['quantity'], (int)$p['minimum_stock']);
      $json = e(json_encode($p)); ?>
    <tr>
      <td><?= $p['id'] ?></td><td><?= e($p['name']) ?></td><td><?= e($p['sku']) ?></td>
      <td><?= e($p['category_name']) ?></td><td><?= e($p['supplier_name']) ?></td>
      <td>₹<?= number_format($p['price'], 2) ?></td><td><?= (int)$p['quantity'] ?></td><td><?= (int)$p['minimum_stock'] ?></td>
      <td><?= stock_badge((int)$p['quantity'], (int)$p['minimum_stock']) ?></td>
      <?php if ($admin): ?><td><?= status_badge($p['status']) ?></td><?php endif; ?>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-info" data-view="<?= $json ?>" data-bs-toggle="modal" data-bs-target="#viewModal"><i class="bi bi-eye"></i></button>
        <?php if ($admin): ?>
          <button class="btn btn-sm btn-outline-primary" data-edit="<?= $json ?>" data-title="Edit Product" data-bs-toggle="modal" data-bs-target="#productModal"><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" data-confirm="Change the status of this product?">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-outline-<?= $p['status'] === 'active' ? 'danger' : 'success' ?>">
              <i class="bi <?= $p['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i></button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="11" class="text-center text-muted py-4">No products found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>

<!-- View details (everyone) -->
<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Product Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><dl class="row mb-0">
    <dt class="col-4">Name</dt><dd class="col-8" data-field="name"></dd>
    <dt class="col-4">SKU</dt><dd class="col-8" data-field="sku"></dd>
    <dt class="col-4">Category</dt><dd class="col-8" data-field="category_name"></dd>
    <dt class="col-4">Supplier</dt><dd class="col-8" data-field="supplier_name"></dd>
    <dt class="col-4">Price</dt><dd class="col-8" data-field="price"></dd>
    <dt class="col-4">Quantity</dt><dd class="col-8" data-field="quantity"></dd>
    <dt class="col-4">Min. stock</dt><dd class="col-8" data-field="minimum_stock"></dd>
    <dt class="col-4">Stock status</dt><dd class="col-8" data-field="stock_label"></dd>
    <dt class="col-4">Description</dt><dd class="col-8" data-field="description"></dd>
    <dt class="col-4">Created</dt><dd class="col-8" data-field="created_at"></dd>
  </dl></div></div></div></div>

<?php if ($admin): ?>
<!-- Add / Edit (admin only) -->
<div class="modal fade" id="productModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form method="post">
    <div class="modal-header"><h5 class="modal-title">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
      <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
      <div class="col-md-8"><label class="form-label">Product Name *</label><input name="name" class="form-control" required maxlength="150"></div>
      <div class="col-md-4"><label class="form-label">SKU *</label><input name="sku" class="form-control" required maxlength="50"></div>
      <div class="col-md-6"><label class="form-label">Category *</label><select name="category_id" class="form-select" required>
        <option value="">Select</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Supplier *</label><select name="supplier_id" class="form-select" required>
        <option value="">Select</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">Price (₹) *</label><input type="number" step="0.01" min="0.01" name="price" class="form-control" required></div>
      <div class="col-md-3"><label class="form-label">Quantity</label><input type="number" min="0" name="quantity" value="0" class="form-control" data-lock-on-edit>
        <div class="form-text">Changed via Stock In/Out</div></div>
      <div class="col-md-3"><label class="form-label">Minimum Stock *</label><input type="number" min="0" name="minimum_stock" class="form-control" required></div>
      <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">
        <option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Product</button></div>
  </form></div></div></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
