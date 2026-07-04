<?php
/**
 * Orders endpoint
 * GET  /api/orders.php                  -> list orders (optionally ?status=pending)
 * GET  /api/orders.php?id=12            -> single order with items
 * POST /api/orders.php                  -> create order { table_id, items: [{menu_item_id, quantity, notes}] }
 * PUT  /api/orders.php?id=12            -> update status { status: 'preparing'|'served'|'paid'|'cancelled' }
 */
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    
    if (!isset($_GET['id'])) {
        requireLogin();
    }

    if (isset($_GET['id'])) {
        $orderId = $_GET['id'];

         if (!verifyOrderAccess($db, $orderId, $_GET['token'] ?? null)) {
            jsonResponse(['error' => 'Not authorized to view this order'], 403);
        }

        $stmt = $db->prepare('
            SELECT o.id, o.status, o.created_at, o.updated_at,
                   t.table_number, u.name AS waiter_name
            FROM orders o
            JOIN restaurant_tables t ON t.id = o.table_id
            JOIN users u ON u.id = o.waiter_id
            WHERE o.id = ?
        ');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) jsonResponse(['error' => 'Order not found'], 404);

        $itemsStmt = $db->prepare('
            SELECT oi.id, oi.quantity, oi.price_at_order, oi.notes, mi.name AS item_name
            FROM order_items oi
            JOIN menu_items mi ON mi.id = oi.menu_item_id
            WHERE oi.order_id = ?
        ');
        $itemsStmt->execute([$orderId]);
        $order['items'] = $itemsStmt->fetchAll();

        jsonResponse($order);
    }

    // List, newest first, optional status filter
    $status = $_GET['status'] ?? null;
    $sql = '
        SELECT o.id, o.status, o.created_at, t.table_number, u.name AS waiter_name,
               (SELECT COALESCE(SUM(oi.quantity * oi.price_at_order), 0) FROM order_items oi WHERE oi.order_id = o.id) AS order_total
        FROM orders o
        JOIN restaurant_tables t ON t.id = o.table_id
        JOIN users u ON u.id = o.waiter_id
    ';
    $params = [];
    if ($status) {
        $sql .= ' WHERE o.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY o.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $input = getJsonInput();

    if (!empty($input['guest'])) {
        // Public self-order — no staff login required
        $waiterId = 5; // <-- the "Customer Self-Order" user ID from Step A
    } else {
        $user = requireRole(['waiter', 'admin', 'manager']);
        $waiterId = $user['id'];
    }

    $orderToken = !empty($input['guest']) ? bin2hex(random_bytes(16)) : null;
    $tableId = $input['table_id'] ?? null;
    $items = $input['items'] ?? [];

    if (!$tableId || empty($items)) {
        jsonResponse(['error' => 'table_id and at least one item are required'], 422);
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO orders (table_id, waiter_id, status, order_token) VALUES (?, ?, ?, ?)');
        $stmt->execute([$tableId, $waiterId, 'pending', $orderToken]);
        $orderId = $db->lastInsertId();

        $priceStmt = $db->prepare('SELECT price FROM menu_items WHERE id = ?');
        $itemStmt = $db->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_order, notes) VALUES (?, ?, ?, ?, ?)');

        foreach ($items as $item) {
            $priceStmt->execute([$item['menu_item_id']]);
            $menuItem = $priceStmt->fetch();
            if (!$menuItem) continue;

            $itemStmt->execute([
                $orderId,
                $item['menu_item_id'],
                $item['quantity'] ?? 1,
                $menuItem['price'],
                $item['notes'] ?? null,
            ]);
        }

        $tableStmt = $db->prepare('UPDATE restaurant_tables SET status = ? WHERE id = ?');
        $tableStmt->execute(['occupied', $tableId]);

        $db->commit();
        jsonResponse(['id' => $orderId, 'token' => $orderToken, 'message' => 'Order created'], 201);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to create order'], 500);
    }
}

if ($method === 'PUT') {
    requireLogin();
    $id = $_GET['id'] ?? null;
    $input = getJsonInput();
    $status = $input['status'] ?? null;

    $validStatuses = ['pending', 'preparing', 'served', 'paid', 'cancelled'];
    if (!$id || !in_array($status, $validStatuses, true)) {
        jsonResponse(['error' => 'Valid id and status are required'], 422);
    }

    $stmt = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    // Free up the table automatically when an order is paid or cancelled
    if (in_array($status, ['paid', 'cancelled'], true)) {
        $stmt2 = $db->prepare('
            UPDATE restaurant_tables t
            JOIN orders o ON o.table_id = t.id
            SET t.status = "free"
            WHERE o.id = ?
        ');
        $stmt2->execute([$id]);
    }

    jsonResponse(['message' => 'Order status updated']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
