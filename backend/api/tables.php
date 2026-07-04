<?php
/**
 * Tables endpoint
 * GET /api/tables.php              -> list all tables with status
 * PUT /api/tables.php?id=3         -> update table status { status: 'free'|'occupied'|'reserved' }
 */
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $db->query('SELECT id, table_number, capacity, status FROM restaurant_tables ORDER BY table_number ASC')->fetchAll();
    jsonResponse($rows);
}

if ($method === 'PUT') {
    requireLogin();
    $id = $_GET['id'] ?? null;
    $input = getJsonInput();
    $status = $input['status'] ?? null;

    if (!$id || !in_array($status, ['free', 'occupied', 'reserved'], true)) {
        jsonResponse(['error' => 'Valid id and status are required'], 422);
    }

    $stmt = $db->prepare('UPDATE restaurant_tables SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    jsonResponse(['message' => 'Table status updated']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
