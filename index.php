<?php
require 'includes/db.php';
require 'includes/auth.php';

// Already logged in? Go to the dashboard.
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();

        // Check password hash and that the account is active
        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid email or password, or the account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - CoreInventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
  <div class="card shadow-sm" style="width:100%;max-width:380px">
    <div class="card-body p-4">
      <h3 class="text-center mb-1">CoreInventory</h3>
      <p class="text-center text-muted mb-4">Sign in to continue</p>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3"><label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required autofocus></div>
        <div class="mb-3"><label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required></div>
        <button class="btn btn-primary w-100">Login</button>
      </form>
    </div>
  </div>
</body>
</html>
