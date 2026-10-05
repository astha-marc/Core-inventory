<?php
// Database connection using PDO (prepared statements are used everywhere).
$pdo = new PDO('mysql:host=localhost;port=3307;dbname=coreinventory;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/**
 * Core stock logic used by Stock In, Stock Out and opening stock.
 * Runs inside a DB transaction so the product quantity and the
 * transaction record are always saved together (or not at all).
 */
function record_stock(PDO $pdo, int $productId, int $userId, string $type, int $qty,
                      string $reason, string $notes, string $dateTime): void
{
    $pdo->beginTransaction();
    try {
        // Lock the product row so two users cannot change it at the same time
        $st = $pdo->prepare("SELECT quantity FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
        $st->execute([$productId]);
        $previous = $st->fetchColumn();
        if ($previous === false) {
            throw new Exception('Product not found or inactive.');
        }
        $previous = (int)$previous;

        // Stock In adds, Stock Out subtracts
        $new = ($type === 'STOCK_IN') ? $previous + $qty : $previous - $qty;
        if ($new < 0) {
            throw new Exception('Insufficient stock available.');   // stock can never be negative
        }

        $pdo->prepare("UPDATE products SET quantity = ? WHERE id = ?")->execute([$new, $productId]);
        $pdo->prepare("INSERT INTO stock_transactions
            (product_id, user_id, type, quantity, previous_quantity, new_quantity, reason, notes, created_at)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$productId, $userId, $type, $qty, $previous, $new, $reason, $notes, $dateTime]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
