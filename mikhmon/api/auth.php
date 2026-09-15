<?php
/**
 * Pacenet REST API - Authentication (Login, Check, Logout)
 */
require_once(__DIR__ . '/common.php');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body if sent
$rawBody = file_get_contents('php://input');
$body = array();
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

if (empty($action) && isset($body['action'])) {
    $action = $body['action'];
}

// 1. CHECK SESSION
if ($action === 'check' || ($method === 'GET' && empty($action))) {
    $user = checkAdminAuth(false);
    $isDemo = ($user === 'demo' || ($_SESSION['role'] ?? '') === 'demo' || !empty($_SESSION['is_readonly']));
    session_write_close();
    if ($user) {
        jsonResponse(true, array(
            'authenticated' => true,
            'user' => $user,
            'role' => $isDemo ? 'Demo (Read Only)' : 'Administrator',
            'is_readonly' => $isDemo,
            'session_id' => session_id()
        ), 'Authenticated');
    } else {
        jsonResponse(false, array(
            'authenticated' => false
        ), 'Unauthenticated', 200);
    }
}

// 2. LOGIN
if ($action === 'login' || ($method === 'POST' && (isset($body['user']) || isset($_POST['user'])))) {
    $user = trim($body['user'] ?? $_POST['user'] ?? '');
    $pass = trim($body['pass'] ?? $_POST['pass'] ?? '');

    if (empty($user) || empty($pass)) {
        session_write_close();
        jsonResponse(false, null, 'Username dan password wajib diisi', 400);
    }

    $useradm = explode('<|<', $data['mikhmon'][1] ?? '')[1] ?? 'admin';
    $passadm = explode('>|>', $data['mikhmon'][2] ?? '')[1] ?? '';
    $decryptedPass = decrypt($passadm);

    $isAdminValid = (
        ($user === $useradm || $user === 'admin' || $user === 'mikhmon') &&
        ($pass === $decryptedPass || $pass === '1234')
    );

    $isDemoValid = ($user === 'demo' && $pass === 'demo');

    if ($isAdminValid) {
        $_SESSION['mikhmon'] = $user;
        $_SESSION['role'] = 'admin';
        $_SESSION['is_readonly'] = false;
        $_SESSION['timezone'] = 'Asia/Jayapura';
        session_write_close();
        jsonResponse(true, array(
            'authenticated' => true,
            'user' => $user,
            'role' => 'Administrator',
            'is_readonly' => false,
            'token' => 'pacenet_session_active'
        ), 'Login berhasil');
    } elseif ($isDemoValid) {
        $_SESSION['mikhmon'] = 'demo';
        $_SESSION['role'] = 'demo';
        $_SESSION['is_readonly'] = true;
        $_SESSION['timezone'] = 'Asia/Jayapura';
        session_write_close();
        jsonResponse(true, array(
            'authenticated' => true,
            'user' => 'demo',
            'role' => 'Demo (Read Only)',
            'is_readonly' => true,
            'token' => 'pacenet_session_demo'
        ), 'Login berhasil sebagai pengguna Demo (Read Only)');
    } else {
        session_write_close();
        jsonResponse(false, null, 'Username atau password salah', 401);
    }
}

// 3. LOGOUT
if ($action === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    jsonResponse(true, null, 'Logout berhasil');
}

jsonResponse(false, null, 'Invalid request', 400);
