<?php
/**
 * Pacenet REST API - System Users Management
 * Role-Based Access Control (RBAC):
 * - owner, admin: Full management rights (list, create, update, delete)
 * - other roles: restricted
 */
require_once(__DIR__ . '/common.php');
$currentUser = checkAdminAuth(true);
session_write_close();

$dataFile = __DIR__ . '/../data/system_users.json';

function loadUsers($file) {
    if (!file_exists($file)) return array();
    $raw = @file_get_contents($file);
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : array();
}

function saveUsers($file, $users) {
    @file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

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

// 1. LIST USERS
if ($action === 'list' || ($method === 'GET' && empty($action))) {
    $users = loadUsers($dataFile);
    $safeUsers = array();
    foreach ($users as $u) {
        $safeUsers[] = array(
            'id' => $u['id'] ?? '',
            'username' => $u['username'] ?? '',
            'name' => $u['name'] ?? '',
            'role' => $u['role'] ?? 'staff_noc',
            'kiosk_name' => $u['kiosk_name'] ?? '',
            'phone' => $u['phone'] ?? '',
            'status' => $u['status'] ?? 'active',
            'created_at' => $u['created_at'] ?? '',
            'last_login' => $u['last_login'] ?? null
        );
    }
    jsonResponse(true, $safeUsers, 'Daftar pengguna sistem berhasil dimuat');
}

// Ensure write permissions for modifications
checkWritePermission();

// 2. CREATE USER
if ($action === 'create' || ($method === 'POST' && $action === 'create')) {
    $username = strtolower(trim($body['username'] ?? ''));
    $password = trim($body['password'] ?? '');
    $name = trim($body['name'] ?? '');
    $role = strtolower(trim($body['role'] ?? 'reseller'));
    $kioskName = trim($body['kiosk_name'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $status = trim($body['status'] ?? 'active');

    if (empty($username) || empty($password)) {
        jsonResponse(false, null, 'Username dan password wajib diisi.', 400);
    }

    if (strlen($username) < 3) {
        jsonResponse(false, null, 'Username minimal 3 karakter.', 400);
    }

    if (strlen($password) < 4) {
        jsonResponse(false, null, 'Password minimal 4 karakter.', 400);
    }

    $validRoles = array('owner', 'admin', 'manager', 'reseller', 'staff_noc', 'finance', 'demo');
    if (!in_array($role, $validRoles)) {
        jsonResponse(false, null, 'Role tidak valid.', 400);
    }

    $users = loadUsers($dataFile);
    foreach ($users as $u) {
        if (strtolower($u['username']) === $username) {
            jsonResponse(false, null, "Username '$username' sudah terdaftar.", 400);
        }
    }

    $newUser = array(
        'id' => 'usr_' . substr(md5(uniqid($username, true)), 0, 8),
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'password_plain' => $password,
        'role' => $role,
        'name' => !empty($name) ? $name : ucfirst($username),
        'kiosk_name' => $kioskName,
        'phone' => $phone,
        'status' => ($status === 'inactive') ? 'inactive' : 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    );

    $users[] = $newUser;
    saveUsers($dataFile, $users);

    jsonResponse(true, array(
        'id' => $newUser['id'],
        'username' => $newUser['username'],
        'role' => $newUser['role'],
        'name' => $newUser['name']
    ), "Pengguna '$username' berhasil ditambahkan.");
}

// 3. UPDATE USER
if ($action === 'update' || ($method === 'POST' && $action === 'update')) {
    $id = trim($body['id'] ?? '');
    $name = trim($body['name'] ?? '');
    $role = strtolower(trim($body['role'] ?? ''));
    $kioskName = trim($body['kiosk_name'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $status = trim($body['status'] ?? '');
    $newPassword = trim($body['password'] ?? '');

    if (empty($id)) {
        jsonResponse(false, null, 'ID Pengguna wajib disertakan.', 400);
    }

    $users = loadUsers($dataFile);
    $foundIdx = -1;
    for ($i = 0; $i < count($users); $i++) {
        if (($users[$i]['id'] ?? '') === $id) {
            $foundIdx = $i;
            break;
        }
    }

    if ($foundIdx === -1) {
        jsonResponse(false, null, 'Pengguna tidak ditemukan.', 404);
    }

    $validRoles = array('owner', 'admin', 'manager', 'reseller', 'staff_noc', 'finance', 'demo');
    if (!empty($role) && in_array($role, $validRoles)) {
        $users[$foundIdx]['role'] = $role;
    }

    if (!empty($name)) {
        $users[$foundIdx]['name'] = $name;
    }

    if (isset($body['kiosk_name'])) {
        $users[$foundIdx]['kiosk_name'] = $kioskName;
    }

    if (isset($body['phone'])) {
        $users[$foundIdx]['phone'] = $phone;
    }

    if (!empty($status)) {
        $users[$foundIdx]['status'] = ($status === 'inactive') ? 'inactive' : 'active';
    }

    if (!empty($newPassword)) {
        if (strlen($newPassword) < 4) {
            jsonResponse(false, null, 'Password minimal 4 karakter.', 400);
        }
        $users[$foundIdx]['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $users[$foundIdx]['password_plain'] = $newPassword;
    }

    saveUsers($dataFile, $users);
    jsonResponse(true, null, 'Data pengguna berhasil diperbarui.');
}

// 4. DELETE USER
if ($action === 'delete' || ($method === 'POST' && $action === 'delete')) {
    $id = trim($body['id'] ?? $_GET['id'] ?? '');
    if (empty($id)) {
        jsonResponse(false, null, 'ID Pengguna wajib disertakan.', 400);
    }

    $users = loadUsers($dataFile);
    $updatedUsers = array();
    $deletedName = '';

    foreach ($users as $u) {
        if (($u['id'] ?? '') === $id) {
            if ($u['username'] === $currentUser) {
                jsonResponse(false, null, 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.', 400);
            }
            if ($u['username'] === 'owner' || $u['username'] === 'admin') {
                jsonResponse(false, null, 'Akun sistem utama tidak boleh dihapus.', 400);
            }
            $deletedName = $u['username'];
            continue;
        }
        $updatedUsers[] = $u;
    }

    if (empty($deletedName)) {
        jsonResponse(false, null, 'Pengguna tidak ditemukan.', 404);
    }

    saveUsers($dataFile, $updatedUsers);
    jsonResponse(true, null, "Pengguna '$deletedName' berhasil dihapus.");
}

jsonResponse(false, null, 'Aksi tidak valid.', 400);
