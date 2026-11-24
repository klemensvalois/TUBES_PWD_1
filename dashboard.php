<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

$u   = $_SESSION['u'];
$id  = $u['id'];
$h   = (int) date('G');
$sal = $h < 11 ? 'Pagi' : ($h < 15 ? 'Siang' : ($h < 18 ? 'Sore' : 'Malam'));

if ($u['role'] === 'admin') {
    top('Dashboard', 'dashboard.php');
    echo '<div class="stats" style="grid-template-columns:repeat(3,1fr)">';
    foreach (['Mahasiswa' => 'lms_mahasiswa.mahasiswa', 'Dosen' => 'lms_dosen.dosen', 'Kelas aktif' => 'lms_admin.kelas'] as $n => $t) {
        echo '<div class="stat">' . $n . '<b>' . q("SELECT COUNT(*) c FROM $t")->fetch()['c'] . '</b></div>';
    }
    echo '</div>';

    echo '<h2>Mahasiswa terbaru</h2>';
    $r = q("SELECT nama,npm,email FROM lms_mahasiswa.mahasiswa ORDER BY id DESC LIMIT 5")->fetchAll();
    if (!$r) {
        echo '<div class="empty"><svg width="200" height="120" viewBox="0 0 200 120"><ellipse cx="100" cy="85" rx="55" ry="28" fill="#ddd"/><circle cx="62" cy="70" r="18" fill="#ccc"/><path d="M20 100h160" stroke="#333"/><text x="150" y="30" font-size="16">Z</text><text x="168" y="14" font-size="12">z</text></svg><h3>Belum ada mahasiswa saat ini</h3><p>Mahasiswa akan muncul di sini setelah mereka mendaftar di kampus Anda.</p></div>';
    } else {
        echo '<table><tr><th>Nama</th><th>NPM</th><th>Email</th></tr>';
        foreach ($r as $x) {
            echo '<tr><td>' . e($x['nama']) . '</td><td>' . e($x['npm']) . '</td><td>' . e($x['email']) . '</td></tr>';
        }
        echo '</table>';
    }

    echo '<p><a class="btn blue" href="' . BASE . '/admin/akun.php?t=mahasiswa&amp;new=1">Tambah Mahasiswa</a></p>';
    bottom();
    exit;
}

top('Home', 'dashboard.php', false);

if ($u['role'] === 'mahasiswa') {
    $ks = q("SELECT k.*,d.nama dosen FROM lms_admin.kelas k JOIN lms_admin.kelas_mhs km ON km.kelas_id=k.id AND km.mhs_id=? LEFT JOIN lms_dosen.dosen d ON d.id=k.dosen_id", [$id])->fetchAll();
} else {
    $ks = q("SELECT k.*,d.nama dosen FROM lms_admin.kelas k LEFT JOIN lms_dosen.dosen d ON d.id=k.dosen_id WHERE k.dosen_id=?", [$id])->fetchAll();
}

$first = explode(' ', $u['nama'])[0];
echo '<div class="split"><div class="l"><div class="hello">Halo, Selamat ' . $sal . ' ' . e($first) . '</div><div class="sub">Kamu sudah berhasil melewati semester-semester sebelumnya; tantangan hari ini juga pasti lewat.</div>';

foreach ($ks as $k) {
    $href = BASE . ($u['role'] === 'dosen' ? '/dosen/tugas.php' : '/mahasiswa/tugas.php?k=' . $k['id']);
    echo '<a class="course" href="' . $href . '"><div class="hd"></div><div class="bd"><b>' . e($k['nama']) . ' (' . e($k['tahun_ajaran']) . ')</b><small>' . e($k['nama']) . ' (' . e($k['tahun_ajaran']) . ')</small><em>Dosen: ' . e($k['dosen']) . '</em></div></a>';
}

if (!$ks) {
    echo '<div class="box">Belum ada kelas. Hubungi admin untuk didaftarkan.</div>';
}

echo '</div>';
panel($id, $u['role']);
echo '</div>';
bottom();
?>