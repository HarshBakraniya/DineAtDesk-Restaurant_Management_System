<?php
/**
 * Shared helpers used by every API endpoint.
 */
require_once __DIR__ . '/../config/db.php';

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return $data ?? [];
}

function requireLogin() {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
    ];
}

function requireRole(array $allowedRoles) {
    $user = requireLogin();
    if (!in_array($user['role'], $allowedRoles, true)) {
        jsonResponse(['error' => 'Forbidden — insufficient role'], 403);
    }
    return $user;
}

function setCorsHeaders() {
    // Same-origin deployment assumed. Adjust if frontend is hosted separately.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Allows access if the request comes from a logged-in staff session,
 * OR if the correct order_token is supplied (for guest customers).
 */
function verifyOrderAccess($db, $orderId, $providedToken) {
    startSecureSession();
    if (!empty($_SESSION['user_id'])) {
        return true; // staff session — always allowed
    }
    $stmt = $db->prepare('SELECT order_token FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    if (!$row || !$row['order_token'] || !$providedToken) {
        return false;
    }
    return hash_equals($row['order_token'], $providedToken);
}

