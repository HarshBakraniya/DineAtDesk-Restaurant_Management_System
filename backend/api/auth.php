<?php
/**
 * Auth endpoint
 * POST /api/auth.php?action=login   { email, password }
 * POST /api/auth.php?action=logout
 * GET  /api/auth.php?action=me
 */
require_once __DIR__ . '/../includes/functions.php';
setCorsHeaders();
startSecureSession();

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        $input = getJsonInput();
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            jsonResponse(['error' => 'Email and password are required'], 422);
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT id, name, password, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['error' => 'Invalid email or password'], 401);
        }
        if (!$user['is_active']) {
            jsonResponse(['error' => 'This account has been disabled'], 403);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        jsonResponse([
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ]);
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        jsonResponse(['message' => 'Logged out']);
        break;

    case 'me':
        if (empty($_SESSION['user_id'])) {
            jsonResponse(['error' => 'Not authenticated'], 401);
        }
        jsonResponse([
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
        ]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 404);
}
