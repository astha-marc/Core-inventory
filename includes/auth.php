<?php
// Session + role-based access control helpers.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Escape output to prevent XSS
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// One-time messages shown after a redirect (messages must not contain raw user input)
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }

function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }

// Only logged-in users may continue
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

// Show an Access Denied page (HTTP 403)
function access_denied(): void {
    http_response_code(403);
    $page_title = 'Access Denied';
    require __DIR__ . '/header.php';
    echo '<div class="alert alert-danger"><h4 class="alert-heading">Access Denied</h4>
          <p>You do not have permission to access this page or action.</p>
          <a href="dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a></div>';
    require __DIR__ . '/footer.php';
    exit;
}

// Pages/actions that only the admin may use
function require_admin(): void {
    require_login();
    if (!is_admin()) access_denied();
}

// Stock status calculated from quantity and minimum stock level
function stock_label(int $qty, int $min): string {
    if ($qty === 0) return 'Out of Stock';
    if ($qty <= $min) return 'Low Stock';
    return 'In Stock';
}
function stock_badge(int $qty, int $min): string {
    $label = stock_label($qty, $min);
    $class = ['Out of Stock' => 'bg-danger', 'Low Stock' => 'bg-warning text-dark', 'In Stock' => 'bg-success'][$label];
    return '<span class="badge ' . $class . '">' . $label . '</span>';
}
function status_badge(string $status): string {
    return $status === 'active' ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive</span>';
}
function type_badge(string $type): string {
    return $type === 'STOCK_IN' ? '<span class="badge bg-success">STOCK IN</span>'
                                : '<span class="badge bg-danger">STOCK OUT</span>';
}
