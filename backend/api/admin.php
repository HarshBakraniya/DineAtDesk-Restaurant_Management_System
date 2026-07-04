<?php
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();

$db = getDB();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset_data') {
    requireRole(['admin']);
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $db->exec('TRUNCATE TABLE bills');
        $db->exec('TRUNCATE TABLE order_items');
        $db->exec('TRUNCATE TABLE orders');
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        $db->exec('UPDATE restaurant_tables SET status = "free"');
        jsonResponse(['message' => 'All orders, bills, and revenue cleared. Menu, tables, and staff accounts kept.']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Reset failed'], 500);
    }
}

jsonResponse(['error' => 'Unknown action'], 404);