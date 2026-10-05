<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();

$admin = is_admin();
$uid = (int)$_SESSION['user_id'];

// Dashboard numbers (only active records are counted)
$totalProducts   = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories WHERE status='active'")->fetchColumn();
$totalSuppliers  = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();
$lowStock        = $pdo->query("SELECT COUNT(*) FROM products WHERE status='active' AND quantity <= minimum_stock")->fetchColumn();
$totalStock      = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM products WHERE status='active'")->fetchColumn();

// Admin sees all transactions, staff sees only their own
$txFilter = $admin ? '' : 'WHERE t.user_id = ' . $uid;
$txCountSql = "SELECT COUNT(*) FROM stock_transactions t " . ($admin ? 'WHERE' : $txFilter . ' AND') . " t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recentCount = $pdo->query($txCountSql)->fetchColumn();

$lowList = $pdo->query("SELECT name, sku, quantity, minimum_stock FROM products
                        WHERE status='active' AND quantity <= minimum_stock ORDER BY quantity ASC LIMIT 8")->fetchAll();
$recent = $pdo->query("SELECT t.*, p.name AS product_name, u.name AS user_name
                       FROM stock_transactions t
                       JOIN products p ON p.id = t.product_id
                       JOIN users u ON u.id = t.user_id
                       $txFilter ORDER BY t.created_at DESC, t.id DESC LIMIT 8")->fetchAll();

$cards = [
    ['Total Products', $totalProducts, 'bi-box', 'primary'],
    ['Total Categories', $totalCategories, 'bi-tags', 'info'],
    ['Total Suppliers', $totalSuppliers, 'bi-truck', 'secondary'],
    ['Low Stock Products', $lowStock, 'bi-exclamation-triangle', 'warning'],
    ['Total Stock', $totalStock, 'bi-stack', 'success'],
    [$admin ? 'Transactions (7 days)' : 'My Transactions (7 days)', $recentCount, 'bi-arrow-left-right', 'dark'],
];

$page_title = 'Dashboard';
require 'includes/header.php';
?>
<h4 class="mb-1">Dashboard</h4>
<p class="text-muted">Welcome, <?= e($_SESSION['name']) ?>.
  <?= $admin ? 'Showing overall inventory statistics.' : 'Showing inventory and your own transactions.' ?></p>

<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card stat-card border-<?= $color ?> h-100">
        <div class="card-body">
          <div class="text-muted small"><i class="bi <?= $icon ?>"></i> <?= $label ?></div>
          <div class="stat-number text-<?= $color ?>"><?= (int)$value ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-header">Low-stock products</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Product</th><th>Qty</th><th>Min</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($lowList as $p): ?>
          <tr><td><?= e($p['name']) ?><div class="small text-muted"><?= e($p['sku']) ?></div></td>
              <td><?= (int)$p['quantity'] ?></td><td><?= (int)$p['minimum_stock'] ?></td>
              <td><?= stock_badge((int)$p['quantity'], (int)$p['minimum_stock']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$lowList): ?><tr><td colspan="4" class="text-center text-muted py-3">No low-stock products.</td></tr><?php endif; ?>
        </tbody></table></div></div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-header">Recent stock transactions</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $t): ?>
          <tr><td><?= e(date('d M Y', strtotime($t['created_at']))) ?></td><td><?= e($t['product_name']) ?></td>
              <td><?= type_badge($t['type']) ?></td><td><?= (int)$t['quantity'] ?></td><td><?= e($t['user_name']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-3">No transactions yet.</td></tr><?php endif; ?>
        </tbody></table></div></div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
