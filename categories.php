<?php
require 'includes/db.php';
require 'includes/auth.php';
require_admin();                                 // staff cannot open this page

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

            $dup = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND id <> ?");
            $dup->execute([$name, $id]);
            if ($name === '') {
                flash('danger', 'Category name is required.');
            } elseif ($dup->fetchColumn()) {
                flash('danger', 'A category with this name already exists.');
            } elseif ($id > 0) {
                $pdo->prepare("UPDATE categories SET name=?, description=?, status=? WHERE id=?")->execute([$name, $desc, $status, $id]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?,?,?)")->execute([$name, $desc, $status]);
                flash('success', 'Category added.');
            }
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE categories SET status = IF(status='active','inactive','active') WHERE id = ?")
                ->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Category status changed.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'Database error. Please try again.');
    }
    header('Location: categories.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare("SELECT * FROM categories WHERE name LIKE ? ORDER BY id DESC");
$st->execute(["%$q%"]);
$rows = $st->fetchAll();

$page_title = 'Categories';
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Categories</h4>
  <button class="btn btn-primary" data-add data-title="Add Category" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-plus-lg"></i> Add Category</button>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input name="q" class="form-control" placeholder="Search category" value="<?= e($q) ?>"></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-secondary">Search</button><a href="categories.php" class="btn btn-outline-secondary">Reset</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td><td><?= status_badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary" data-edit="<?= e(json_encode($r)) ?>" data-title="Edit Category" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-pencil"></i></button>
        <form method="post" class="d-inline" data-confirm="Change the status of this category?">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-<?= $r['status'] === 'active' ? 'danger' : 'success' ?>"><i class="bi <?= $r['status'] === 'active' ? 'bi-x-circle' : 'bi-check-circle' ?>"></i></button>
        </form></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No categories found.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
  <div class="modal-header"><h5 class="modal-title">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
    <div class="mb-3"><label class="form-label">Category Name *</label><input name="name" class="form-control" required maxlength="100"></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div><label class="form-label">Status</label><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div></div>
<?php require 'includes/footer.php'; ?>
