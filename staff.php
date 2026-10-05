<?php
require 'includes/db.php';
require 'includes/auth.php';
require_admin();                                 // admin only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

            $errors = [];
            if ($name === '') $errors[] = 'Name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if ($phone !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', $phone)) $errors[] = 'Enter a valid phone number.';
            if ($id === 0 && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if ($id > 0 && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
            $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
            $dup->execute([$email, $id]);
            if ($dup->fetchColumn()) $errors[] = 'This email is already registered.';

            if ($errors) {
                flash('danger', implode('<br>', $errors));
            } elseif ($id > 0) {
                // Only users with the staff role can be edited here (admin account stays safe)
                $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, status=? WHERE id=? AND role='staff'")
                    ->execute([$name, $email, $phone, $status, $id]);
                if ($password !== '') {
                    $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='staff'")
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                flash('success', 'Staff member updated.');
            } else {
                // New users always get the "staff" role
                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?, 'staff', ?)")
                    ->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $status]);
                flash('success', 'Staff member added.');
            }
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id = ? AND role='staff'")
                ->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Staff status changed.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'Database error. Please try again.');
    }
    header('Location: staff.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare("SELECT id, name, email, phone, role, status, created_at FROM users
                     WHERE role='staff' AND (name LIKE ? OR email LIKE ?) ORDER BY id DESC");
$st->execute(["%$q%", "%$q%"]);
$rows = $st->fetchAll();

$page_title = 'Staff';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Staff</h4>
  <button class="btn btn-primary" data-add data-title="Add Staff" data-bs-toggle="modal" data-bs-target="#staffModal"><i class="bi bi-plus-lg"></i> Add Staff</button>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input name="q" class="form-control" placeholder="Search name or email" value="<?= e($q) ?>"></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-secondary">Search</button><a href="staff.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['phone']) ?></td>
      <td><?= e(ucfirst($r['role'])) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary" data-edit="<?= e(json_encode($r)) ?>" data-title="Edit Staff" data-bs-toggle="modal" data-bs-target="#staffModal"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Activate / deactivate this staff member?">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-<?= $r['status'] === 'active' ? 'danger' : 'success' ?>"><i class="bi <?= $r['status'] === 'active' ? 'bi-person-x' : 'bi-person-check' ?>"></i></button>
        </form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No staff found.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
  <div class="modal-header"><h5 class="modal-title">Add Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
    <div class="col-12"><label class="form-label">Name *</label><input name="name" class="form-control" required maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required maxlength="120"></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="20"></div>
    <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" autocomplete="new-password">
      <div class="form-text">Required for new staff. Leave blank to keep the current password.</div></div>
    <div class="col-md-3"><label class="form-label">Role</label><input class="form-control" value="Staff" disabled></div>
    <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div></div>
<?php require 'includes/footer.php'; ?>
