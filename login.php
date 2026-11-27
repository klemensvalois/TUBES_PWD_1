<?php
require 'config.php';

if (!empty($_SESSION['u'])) {
    go('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = $_POST['role'] ?? '';
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = null;
    if ($role === 'mahasiswa') {
        $user = q("SELECT * FROM lms_mahasiswa.mahasiswa WHERE npm=? OR email=?", [$login_id, $login_id])->fetch();
    } elseif ($role === 'dosen') {
        $user = q("SELECT * FROM lms_dosen.dosen WHERE npp=? OR email=?", [$login_id, $login_id])->fetch();
    } elseif ($role === 'admin') {
        $user = q("SELECT * FROM lms_admin.admin WHERE username=? OR email=?", [$login_id, $login_id])->fetch();
    }

    if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
        unset($user['password']);
        $user['role'] = $role;
        $_SESSION['u'] = $user;
        go('dashboard.php');
    }

    go('index.xhtml?err=' . urlencode('Role, ID/Email, atau Password tidak sesuai.'));
}

go('index.xhtml');
?>