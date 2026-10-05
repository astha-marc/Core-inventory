<?php
require 'includes/db.php';
require 'includes/auth.php';
require_login();
$admin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$admin) access_denied();                // staff can only view suppliers
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($name === '') $errors[] = 'Supplier name is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) $errors[] = 'Enter a valid phone number.';
            $dup = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE name = ? AND id <> ?");
            $dup->execute([$name, $id]);
            if ($dup->fetchColumn()) $errors[] = 'A supplier with this name already exists.';

            if ($errors) {
                flash('danger', implode('<br>', $errors));
            } elseif ($id > 0) {
                $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, status=? WHERE id=?")
                    ->execute([$name, $contact, $phone, $email, $address, $status, $id]);
                flash('success', 'Supplier updated.');
            } else {
                $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, email, address, status) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $contact, $phone, $email, $address, $status]);
                flash('success', 'Supplier added.');
            }
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE suppliers SET status = IF(status='active','inactive','active') WHERE id = ?")
                ->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Supplier status changed.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'Database error. Please try again.');
    }
    header('Location: suppliers.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM suppliers WHERE (name LIKE ? OR contact_person LIKE ?)" . ($admin ? '' : " AND status='active'") . " ORDER BY id DESC";
$st = $pdo->prepare($sql);
$st->execute(["%$q%", "%$q%"]);
$rows = $st->fetchAll();

$page_title = 'Suppliers';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Suppliers</h4>
  <?php if ($admin): ?><button class="btn btn-primary" data-add data-title="Add Supplier" data-bs-toggle="modal" data-bs-target="#supModal"><i class="bi bi-plus-lg"></i> Add Supplier</button><?php endif; ?>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input name="q" class="form-control" placeholder="Search supplier or contact person" value="<?= e($q) ?>"></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-secondary">Search</button><a href="suppliers.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Address</th><th>Status</th><?php if ($admin): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td>
      <td><?= e($r['email']) ?></td><td><?= e($r['address']) ?></td><td><?= status_badge($r['status']) ?></td>
      <?php if ($admin): ?><td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary" data-edit="<?= e(json_encode($r)) ?>" data-title="Edit Supplier" data-bs-toggle="modal" data-bs-target="#supModal"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Change the status of this supplier?">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-<?= $r['status'] === 'active' ? 'danger' : 'success' ?>"><i class="bi <?= $r['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i></button>
        </form></td><?php endif; ?></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php if ($admin): ?>
<div class="modal fade" id="supModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
  <div class="modal-header"><h5 class="modal-title">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
    <div class="col-12"><label class="form-label">Supplier Name *</label><input name="name" class="form-control" required maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">Contact Person</label><input name="contact_person" class="form-control" maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="20"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="120"></div>
    <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div></div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
