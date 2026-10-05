<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();                                 // admin and staff can both remove stock

$products = $pdo->query("SELECT id, name, sku, quantity FROM products WHERE status='active' ORDER BY name")->fetchAll();
$reasons  = ['Sold', 'Issued', 'Damaged', 'Returned to supplier', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty    = $_POST['quantity'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $notes  = trim($_POST['notes'] ?? '');
    $date   = $_POST['date'] ?? '';

    $errors = [];
    $p = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ? AND status='active'"); $p->execute([$productId]);
    if (!$p->fetchColumn()) $errors[] = 'Please select a valid product.';
    if (!ctype_digit((string)$qty) || (int)$qty <= 0) $errors[] = 'Quantity must be a positive whole number.';
    if (!in_array($reason, $reasons, true)) $errors[] = 'Please select a reason.';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) $errors[] = 'Please enter a valid date (not in the future).';

    if ($errors) {
        flash('danger', implode('<br>', $errors));
    } else {
        try {
            // record_stock() checks that requested quantity <= available quantity
            record_stock($pdo, $productId, (int)$_SESSION['user_id'], 'STOCK_OUT', (int)$qty,
                         $reason, $notes, $date . ' ' . date('H:i:s'));
            flash('success', 'Stock removed successfully.');
        } catch (PDOException $ex) {
            flash('danger', 'Database error. Please try again.');
        } catch (Exception $ex) {
            flash('danger', 'Error: ' . $ex->getMessage());   // e.g. "Insufficient stock available."
        }
    }
    header('Location: stock-out.php');
    exit;
}

$page_title = 'Stock Out';
require 'includes/header.php';
?>
<h4 class="mb-3">Stock Out</h4>
<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" class="row g-3">
  <div class="col-12"><label class="form-label">Product *</label>
    <select name="product_id" class="form-select" required>
      <option value="">Select product</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) - Available: <?= (int)$p['quantity'] ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label">Quantity *</label><input type="number" name="quantity" min="1" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Date *</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Reason *</label>
    <select name="reason" class="form-select" required><?php foreach ($reasons as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
  <div class="col-12"><button class="btn btn-danger"><i class="bi bi-box-arrow-up"></i> Remove Stock</button></div>
</form></div></div>
<?php require 'includes/footer.php'; ?>
