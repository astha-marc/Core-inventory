<?php
require 'includes/db.php';
require 'includes/auth.php';
require_admin();                                 // reports are for admin only

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

// Inventory report (current stock)
$inventory = $pdo->query("SELECT p.name, p.sku, p.quantity, p.minimum_stock, c.name AS category_name, s.name AS supplier_name
                          FROM products p JOIN categories c ON c.id = p.category_id JOIN suppliers s ON s.id = p.supplier_id
                          WHERE p.status = 'active' ORDER BY p.name")->fetchAll();

// Optional: export the inventory report as CSV (must run before any HTML output)
if (($_GET['export'] ?? '') === 'inventory') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Product', 'SKU', 'Category', 'Supplier', 'Current Stock', 'Minimum Stock', 'Status']);
    foreach ($inventory as $r) {
        fputcsv($out, [$r['name'], $r['sku'], $r['category_name'], $r['supplier_name'], $r['quantity'],
                       $r['minimum_stock'], stock_label((int)$r['quantity'], (int)$r['minimum_stock'])]);
    }
    fclose($out);
    exit;
}

// Transaction report (filtered by date)
$where = []; $params = [];
if ($from !== '') { $where[] = 'DATE(created_at) >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 'DATE(created_at) <= ?'; $params[] = $to; }
$sql = "SELECT COALESCE(SUM(CASE WHEN type='STOCK_IN'  THEN quantity ELSE 0 END),0) AS total_in,
               COALESCE(SUM(CASE WHEN type='STOCK_OUT' THEN quantity ELSE 0 END),0) AS total_out,
               COUNT(*) AS total_tx
        FROM stock_transactions" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$st = $pdo->prepare($sql);
$st->execute($params);
$summary = $st->fetch();

$page_title = 'Reports';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Reports</h4>
  <div class="no-print">
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <a class="btn btn-outline-success btn-sm" href="reports.php?export=inventory"><i class="bi bi-filetype-csv"></i> Export Inventory CSV</a>
  </div>
</div>

<h5>Transaction Report</h5>
<form class="row g-2 mb-3 no-print" method="get">
  <div class="col-md-3"><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-secondary">Filter</button><a href="reports.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card border-success"><div class="card-body"><div class="text-muted small">Total Stock In (units)</div><div class="stat-number text-success h3 mb-0"><?= (int)$summary['total_in'] ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-danger"><div class="card-body"><div class="text-muted small">Total Stock Out (units)</div><div class="stat-number text-danger h3 mb-0"><?= (int)$summary['total_out'] ?></div></div></div></div>
  <div class="col-md-4"><div class="card border-primary"><div class="card-body"><div class="text-muted small">Number of Transactions</div><div class="stat-number text-primary h3 mb-0"><?= (int)$summary['total_tx'] ?></div></div></div></div>
</div>

<h5>Inventory Report</h5>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead class="table-light"><tr><th>Product</th><th>Category</th><th>Supplier</th><th>Current Stock</th><th>Minimum Stock</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($inventory as $r): ?>
    <tr><td><?= e($r['name']) ?><div class="small text-muted"><?= e($r['sku']) ?></div></td><td><?= e($r['category_name']) ?></td><td><?= e($r['supplier_name']) ?></td>
      <td><?= (int)$r['quantity'] ?></td><td><?= (int)$r['minimum_stock'] ?></td><td><?= stock_badge((int)$r['quantity'], (int)$r['minimum_stock']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$inventory): ?><tr><td colspan="6" class="text-center text-muted py-4">No products.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php require 'includes/footer.php'; ?>
