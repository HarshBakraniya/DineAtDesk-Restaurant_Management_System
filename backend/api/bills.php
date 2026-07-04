<?php
/**
 * Bills endpoint
 * GET  /api/bills.php                    -> list bills
 * GET  /api/bills.php?order_id=12        -> get bill for a specific order
 * POST /api/bills.php                    -> generate bill { order_id, tax_percent, discount }
 * PUT  /api/bills.php?id=4               -> mark as paid { payment_method }
 */
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
   
    if (!isset($_GET['id'])) {
        requireLogin();
    }

    if (isset($_GET['order_id'])) {
        if (!verifyOrderAccess($db, $_GET['order_id'], $_GET['token'] ?? null)) {
            jsonResponse(['error' => 'Not authorized to view this bill'], 403);
        }
        $stmt = $db->prepare('SELECT * FROM bills WHERE order_id = ?');
        $stmt->execute([$_GET['order_id']]);
        $bill = $stmt->fetch();
        if (!$bill) jsonResponse(['error' => 'No bill found for this order'], 404);
        jsonResponse($bill);
    }


    $rows = $db->query('
        SELECT b.id, b.total, b.is_paid, b.payment_method, b.created_at,
               o.id AS order_id, t.table_number
        FROM bills b
        JOIN orders o ON o.id = b.order_id
        JOIN restaurant_tables t ON t.id = o.table_id
        ORDER BY b.created_at DESC
    ')->fetchAll();
    jsonResponse($rows);
}

if ($method === 'POST') {
    requireRole(['waiter', 'admin', 'manager']);
    $input = getJsonInput();

    $orderId = $input['order_id'] ?? null;
    $taxPercent = $input['tax_percent'] ?? 5;
    $discount = $input['discount'] ?? 0;

    if (!$orderId) jsonResponse(['error' => 'order_id is required'], 422);

    // Prevent duplicate bills if "Generate bill" is clicked more than once
    $existing = $db->prepare('SELECT id, subtotal, tax, discount, total FROM bills WHERE order_id = ?');
    $existing->execute([$orderId]);
    if ($dup = $existing->fetch()) {
        jsonResponse($dup, 200);
    }

    $stmt = $db->prepare('SELECT COALESCE(SUM(quantity * price_at_order), 0) AS subtotal FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $subtotal = (float) $stmt->fetch()['subtotal'];

    if ($subtotal <= 0) jsonResponse(['error' => 'Order has no billable items'], 422);

    $tax = round($subtotal * ($taxPercent / 100), 2);
    $total = round($subtotal + $tax - $discount, 2);

    $insert = $db->prepare('INSERT INTO bills (order_id, subtotal, tax, discount, total, is_paid) VALUES (?, ?, ?, ?, ?, 0)');
    $insert->execute([$orderId, $subtotal, $tax, $discount, $total]);

    jsonResponse([
        'id' => $db->lastInsertId(),
        'subtotal' => $subtotal,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $total,
    ], 201);
}

if ($method === 'PUT') {
    $id = $_GET['id'] ?? null;
    $input = getJsonInput();
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $token = $input['token'] ?? null;

    if (!$id || !in_array($paymentMethod, ['cash', 'card', 'upi'], true)) {
        jsonResponse(['error' => 'Valid id and payment_method are required'], 422);
    }

    $orderCheck = $db->prepare('SELECT order_id FROM bills WHERE id = ?');
    $orderCheck->execute([$id]);
    $billRow = $orderCheck->fetch();
    if (!$billRow || !verifyOrderAccess($db, $billRow['order_id'], $token)) {
        jsonResponse(['error' => 'Not authorized to pay this bill'], 403);
    }


    $stmt = $db->prepare('UPDATE bills SET is_paid = 1, payment_method = ? WHERE id = ?');
    $stmt->execute([$paymentMethod, $id]);

    // Mark the related order as paid (also frees the table — see orders.php PUT logic)
    $orderStmt = $db->prepare('SELECT order_id FROM bills WHERE id = ?');
    $orderStmt->execute([$id]);
    $bill = $orderStmt->fetch();
    if ($bill) {
        $update = $db->prepare('UPDATE orders SET status = "paid" WHERE id = ?');
        $update->execute([$bill['order_id']]);
        $freeTable = $db->prepare('
            UPDATE restaurant_tables t
            JOIN orders o ON o.table_id = t.id
            SET t.status = "free"
            WHERE o.id = ?
        ');
        $freeTable->execute([$bill['order_id']]);
    }

    jsonResponse(['message' => 'Bill marked as paid']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
