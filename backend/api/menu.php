<?php
/**
 * Menu endpoint
 * GET    /api/menu.php                     -> list items (with category name)
 * GET    /api/menu.php?categories=1         -> list categories
 * POST   /api/menu.php                      -> create item (admin/manager)
 * PUT    /api/menu.php?id=5                 -> update item (admin/manager)
 * DELETE /api/menu.php?id=5                 -> delete item (admin/manager)
 * PUT    /api/menu.php?id=5&toggle=1        -> toggle availability (any logged-in staff)
 */
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Menu browsing is public (needed for guest self-ordering); mutations below stay protected

    if (isset($_GET['categories'])) {
        $rows = $db->query('SELECT id, name, sort_order FROM categories ORDER BY sort_order ASC')->fetchAll();
        jsonResponse($rows);
    }

    $rows = $db->query('
        SELECT m.id, m.name, m.price, m.is_available, m.image_path,
               c.id AS category_id, c.name AS category_name
        FROM menu_items m
        JOIN categories c ON c.id = m.category_id
        ORDER BY c.sort_order ASC, m.name ASC
    ')->fetchAll();
    jsonResponse($rows);
}

if ($method === 'POST') {
    requireRole(['admin', 'manager']);
    $input = getJsonInput();

    $name = trim($input['name'] ?? '');
    $price = $input['price'] ?? null;
    $categoryId = $input['category_id'] ?? null;

    if ($name === '' || !is_numeric($price) || !$categoryId) {
        jsonResponse(['error' => 'name, price, and category_id are required'], 422);
    }

    $stmt = $db->prepare('INSERT INTO menu_items (category_id, name, price, is_available) VALUES (?, ?, ?, ?)');
    $stmt->execute([$categoryId, $name, $price, $input['is_available'] ?? 1]);

    jsonResponse(['id' => $db->lastInsertId(), 'message' => 'Menu item created'], 201);
}

if ($method === 'PUT') {
    requireLogin();
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'id is required'], 422);

    // Quick availability toggle — allowed for any logged-in staff (e.g. kitchen marking item out of stock)
    if (isset($_GET['toggle'])) {
        $stmt = $db->prepare('UPDATE menu_items SET is_available = NOT is_available WHERE id = ?');
        $stmt->execute([$id]);
        jsonResponse(['message' => 'Availability toggled']);
    }

    // Full edit — admin/manager only
    requireRole(['admin', 'manager']);
    $input = getJsonInput();

    $fields = [];
    $params = [];
    foreach (['name', 'price', 'category_id', 'is_available'] as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = ?";
            $params[] = $input[$f];
        }
    }
    if (empty($fields)) jsonResponse(['error' => 'No fields to update'], 422);

    $params[] = $id;
    $stmt = $db->prepare('UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);

    jsonResponse(['message' => 'Menu item updated']);
}

if ($method === 'DELETE') {
    requireRole(['admin', 'manager']);
    $id = $_GET['id'] ?? null;
    if (!$id) jsonResponse(['error' => 'id is required'], 422);

    $stmt = $db->prepare('DELETE FROM menu_items WHERE id = ?');
    $stmt->execute([$id]);
    jsonResponse(['message' => 'Menu item deleted']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
