<?php
// Each menu item lists the roles allowed to see it.
$menu = [
    ['dashboard.php',    'Dashboard',    'bi-speedometer2',      ['admin', 'staff']],
    ['products.php',     'Products',     'bi-box',               ['admin', 'staff']],
    ['categories.php',   'Categories',   'bi-tags',              ['admin']],
    ['suppliers.php',    'Suppliers',    'bi-truck',             ['admin', 'staff']],
    ['staff.php',        'Staff',        'bi-people',            ['admin']],
    ['stock-in.php',     'Stock In',     'bi-box-arrow-in-down', ['admin', 'staff']],
    ['stock-out.php',    'Stock Out',    'bi-box-arrow-up',      ['admin', 'staff']],
    ['transactions.php', 'Transactions', 'bi-arrow-left-right',  ['admin', 'staff']],
    ['reports.php',      'Reports',      'bi-bar-chart',         ['admin']],
];
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <ul class="nav flex-column">
    <?php foreach ($menu as [$file, $label, $icon, $roles]): ?>
      <?php if (in_array($_SESSION['role'], $roles)): ?>
        <li class="nav-item">
          <a class="nav-link <?= $current === $file ? 'active' : '' ?>" href="<?= $file ?>">
            <i class="bi <?= $icon ?>"></i> <?= $label ?></a>
        </li>
      <?php endif; ?>
    <?php endforeach; ?>
    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
  </ul>
</aside>
