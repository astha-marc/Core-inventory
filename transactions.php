<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();
$admin = is_admin();

// Filters
$fp = (int)($_GET['product'] ?? 0);
$ft = $_GET['type'] ?? '';
$fu = (int)($_GET['staff'] ?? 0);
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$where = []; $params = [];
// Staff can only see their own transactions; admin can see everything
if (!$admin) { $where[] = 't.user_id = ?'; $params[] = (int)$_SESSION['user_id']; }
elseif ($fu > 0) { $where[] = 't.user_id = ?'; $params[] = $fu; }
if ($fp > 0) { $where[] = 't.product_id = ?'; $params[] = $fp; }
if (in_array($ft, ['STOCK_IN', 'STOCK_OUT'], true)) { $where[] = 't.type = ?'; $params[] = $ft; }
if ($from !== '') { $where[] = 'DATE(t.created_at) >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 'DATE(t.created_at) <= ?'; $params[] = $to; }

$sql = "SELECT t.*, p.name AS product_name, p.sku, u.name AS user_name
        FROM stock_transactions t
        JOIN products p ON p.id = t.product_id
        JOIN users u ON u.id = t.user_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY t.created_at DESC, t.id DESC LIMIT 300";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();
$users = $admin ? $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll() : [];

$page_title = 'Transactions';
require 'includes/header.php';
?>
<h4 class="mb-1">Transaction History</h4>
<?php if (!$admin): ?><p class="text-muted">Showing your own transactions.</p><?php endif; ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="product" class="form-select"><option value="0">All products</option>
    <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" <?= $fp == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select name="type" class="form-select"><option value="">All types</option>
    <option value="STOCK_IN" <?= $ft === 'STOCK_IN' ? 'selected' : '' ?>>Stock In</option>
    <option value="STOCK_OUT" <?= $ft === 'STOCK_OUT' ? 'selected' : '' ?>>Stock Out</option></select></div>
  <?php if ($admin): ?>
  <div class="col-md-2"><select name="staff" class="form-select"><option value="0">All users</option>
    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $fu == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= e($from) ?>" title="From date"></div>
  <div class="col-md-2"><input type="date" name="to" class="form-control" value="<?= e($to) ?>" title="To date"></div>
  <div class="col-md-1 d-flex gap-2"><button class="btn btn-secondary">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm align-middle mb-0">
  <thead class="table-light"><tr><th>ID</th><th>Product</th><th>User</th><th>Type</th><th>Qty</th><th>Previous</th><th>New</th><th>Reason</th><th>Notes</th><th>Date</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $t): ?>
    <tr><td><?= $t['id'] ?></td><td><?= e($t['product_name']) ?><div class="small text-muted"><?= e($t['sku']) ?></div></td>
      <td><?= e($t['user_name']) ?></td><td><?= type_badge($t['type']) ?></td><td><?= (int)$t['quantity'] ?></td>
      <td><?= (int)$t['previous_quantity'] ?></td><td><?= (int)$t['new_quantity'] ?></td><td><?= e($t['reason']) ?></td>
      <td class="small"><?= e($t['notes']) ?></td><td class="text-nowrap"><?= e(date('d M Y H:i', strtotime($t['created_at']))) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No transactions found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<p class="text-muted small mt-2">Showing up to the latest 300 records.</p>
<?php require 'includes/footer.php'; ?>
