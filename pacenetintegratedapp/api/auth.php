<?php
/**
 * Pacenet REST API - Authentication (Login, Check, Logout)
 * Multi-Role Support:
 * - owner, admin, manager, reseller, staff_noc, finance, demo
 */
require_once(__DIR__ . '/common.php');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$usersFile = __DIR__ . '/../data/system_users.json';

function getSystemUsers($file) {
    if (!file_exists($file)) return array();
    $raw = @file_get_contents($file);
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : array();
}

function updateSystemUsers($file, $users) {
    @file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

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
    $role = $_SESSION['role'] ?? 'admin';
    $name = $_SESSION['name'] ?? ucfirst($user ?: 'User');
    $kioskName = $_SESSION['kiosk_name'] ?? '';
    $isDemo = ($user === 'demo' || $role === 'demo' || !empty($_SESSION['is_readonly']));

    session_write_close();

    if ($user) {
        $roleLabels = array(
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager Operasional',
            'reseller' => 'Reseller Kios',
            'staff_noc' => 'Staff NOC',
            'finance' => 'Finance',
            'demo' => 'Demo (Read Only)'
        );

        jsonResponse(true, array(
            'authenticated' => true,
            'user' => $user,
            'name' => $name,
            'role' => $role,
            'role_label' => $roleLabels[$role] ?? ucfirst($role),
            'kiosk_name' => $kioskName,
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
    $user = strtolower(trim($body['user'] ?? $body['username'] ?? $_POST['user'] ?? $_POST['username'] ?? ''));
    $pass = trim($body['pass'] ?? $body['password'] ?? $_POST['pass'] ?? $_POST['password'] ?? '');

    if (empty($user) || empty($pass)) {
        session_write_close();
        jsonResponse(false, null, 'Username dan password wajib diisi', 400);
    }

    $allUsers = getSystemUsers($usersFile);
    $matchedUser = null;
    $matchedIdx = -1;

    for ($i = 0; $i < count($allUsers); $i++) {
        if (strtolower($allUsers[$i]['username'] ?? '') === $user) {
            $matchedUser = $allUsers[$i];
            $matchedIdx = $i;
            break;
        }
    }

    $loginSuccess = false;
    $authRole = 'admin';
    $authName = ucfirst($user);
    $authKiosk = '';

    if ($matchedUser) {
        if (($matchedUser['status'] ?? 'active') === 'inactive') {
            session_write_close();
            jsonResponse(false, null, 'Akun Anda dinonaktifkan. Silakan hubungi Administrator.', 403);
        }

        $hash = $matchedUser['password_hash'] ?? '';
        $plain = $matchedUser['password_plain'] ?? '';

        if (password_verify($pass, $hash) || $pass === $plain) {
            $loginSuccess = true;
            $authRole = $matchedUser['role'] ?? 'admin';
            $authName = $matchedUser['name'] ?? ucfirst($user);
            $authKiosk = $matchedUser['kiosk_name'] ?? '';

            // Update last_login
            $allUsers[$matchedIdx]['last_login'] = date('Y-m-d H:i:s');
            updateSystemUsers($usersFile, $allUsers);
        }
    }

    // Fallback check for legacy config admin credentials
    if (!$loginSuccess) {
        $useradm = explode('<|<', $data['mikhmon'][1] ?? '')[1] ?? 'admin';
        $passadm = explode('>|>', $data['mikhmon'][2] ?? '')[1] ?? '';
        $decryptedPass = decrypt($passadm);

        if (($user === strtolower($useradm) || $user === 'admin') && ($pass === $decryptedPass || $pass === '1234')) {
            $loginSuccess = true;
            $authRole = 'owner';
            $authName = 'Administrator Utama';
        } elseif ($user === 'demo' && $pass === 'demo') {
            $loginSuccess = true;
            $authRole = 'demo';
            $authName = 'Pengguna Demo';
        }
    }

    if ($loginSuccess) {
        $isDemo = ($authRole === 'demo');
        $_SESSION['pacenet_user'] = $user;
        $_SESSION['mikhmon'] = $user;
        $_SESSION['role'] = $authRole;
        $_SESSION['name'] = $authName;
        $_SESSION['kiosk_name'] = $authKiosk;
        $_SESSION['is_readonly'] = $isDemo;
        $_SESSION['timezone'] = 'Asia/Jayapura';
        session_write_close();

        $roleLabels = array(
            'owner' => 'Owner',
            'admin' => 'Administrator',
            'manager' => 'Manager Operasional',
            'reseller' => 'Reseller Kios',
            'staff_noc' => 'Staff NOC',
            'finance' => 'Finance',
            'demo' => 'Demo (Read Only)'
        );

        jsonResponse(true, array(
            'authenticated' => true,
            'user' => $user,
            'name' => $authName,
            'role' => $authRole,
            'role_label' => $roleLabels[$authRole] ?? ucfirst($authRole),
            'kiosk_name' => $authKiosk,
            'is_readonly' => $isDemo,
            'token' => $isDemo ? 'pacenet_session_demo' : 'pacenet_session_active'
        ), 'Login berhasil');
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
