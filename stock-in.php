<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();                                 // admin and staff can both add stock

$products  = $pdo->query("SELECT id, name, sku, quantity, supplier_id FROM products WHERE status='active' ORDER BY name")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
$reasons   = ['Purchase', 'Return from customer', 'Stock adjustment', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty       = $_POST['quantity'] ?? '';
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $price     = $_POST['purchase_price'] ?? '';
    $reason    = $_POST['reason'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');
    $date      = $_POST['date'] ?? '';

    $errors = [];
    $p = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ? AND status='active'"); $p->execute([$productId]);
    if (!$p->fetchColumn()) $errors[] = 'Please select a valid product.';
    if (!ctype_digit((string)$qty) || (int)$qty <= 0) $errors[] = 'Quantity must be a positive whole number.';
    $s = $pdo->prepare("SELECT name FROM suppliers WHERE id = ? AND status='active'"); $s->execute([$supplierId]);
    $supplierName = $s->fetchColumn();
    if (!$supplierName) $errors[] = 'Please select a valid supplier.';
    if (!is_numeric($price) || $price <= 0) $errors[] = 'Purchase price must be a positive number.';
    if (!in_array($reason, $reasons, true)) $errors[] = 'Please select a reason.';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) $errors[] = 'Please enter a valid date (not in the future).';

    if ($errors) {
        flash('danger', implode('<br>', $errors));
    } else {
        try {
            // Supplier and purchase price are kept inside the notes text of the transaction
            $fullNotes = 'Supplier: ' . $supplierName . ' | Purchase price: ' . number_format((float)$price, 2)
                       . ($notes !== '' ? ' | ' . $notes : '');
            record_stock($pdo, $productId, (int)$_SESSION['user_id'], 'STOCK_IN', (int)$qty,
                         $reason, $fullNotes, $date . ' ' . date('H:i:s'));
            flash('success', 'Stock added successfully.');
        } catch (PDOException $ex) {
            flash('danger', 'Database error. Please try again.');
        } catch (Exception $ex) {
            flash('danger', $ex->getMessage());
        }
    }
    header('Location: stock-in.php');
    exit;
}

$page_title = 'Stock In';
require 'includes/header.php';
?>
<h4 class="mb-3">Stock In</h4>
<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" class="row g-3">
  <div class="col-12"><label class="form-label">Product *</label>
    <select name="product_id" id="productSelect" class="form-select" required>
      <option value="">Select product</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" data-supplier="<?= $p['supplier_id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) - Stock: <?= (int)$p['quantity'] ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label">Quantity *</label><input type="number" name="quantity" min="1" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Purchase Price (₹) *</label><input type="number" name="purchase_price" step="0.01" min="0.01" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Supplier *</label>
    <select name="supplier_id" id="supplierSelect" class="form-select" required>
      <option value="">Select supplier</option>
      <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label">Date *</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Reason *</label>
    <select name="reason" class="form-select" required><?php foreach ($reasons as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
  <div class="col-12"><button class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> Add Stock</button></div>
</form></div></div>
<script>
// Convenience: pre-select the product's usual supplier
document.getElementById('productSelect').addEventListener('change', function () {
  var sup = this.options[this.selectedIndex].dataset.supplier;
  if (sup) document.getElementById('supplierSelect').value = sup;
});
</script>
<?php require 'includes/footer.php'; ?>
