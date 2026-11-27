<?php
require 'config.php';
header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['npm'])) {
    $npm = trim($_GET['npm']);
    if ($npm === '') {
        echo 'kosong';
        exit;
    }
    $ada = q("SELECT id FROM lms_mahasiswa.mahasiswa WHERE npm = ?", [$npm])->fetch();
    echo $ada ? 'dipakai' : 'tersedia';
    exit;
}

if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    if ($email === '') {
        echo 'kosong';
        exit;
    }
    $ada = q("SELECT id FROM lms_mahasiswa.mahasiswa WHERE email = ?", [$email])->fetch();
    echo $ada ? 'dipakai' : 'tersedia';
    exit;
}
?>