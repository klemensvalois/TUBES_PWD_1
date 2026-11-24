<?php
/**
 * cek.php - halaman diagnosa. Buka http://localhost/lms/cek.php lalu HAPUS file ini setelah selesai.
 */
require 'config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "BASE terdeteksi : " . BASE . "\n";
foreach (['style.css', 'app.js'] as $f) {
    echo "assets/$f : " . (is_file(__DIR__ . '/assets/' . $f) ? 'ADA (' . filesize(__DIR__ . '/assets/' . $f) . ' byte)' : 'TIDAK ADA') . "\n";
}
echo "Alamat CSS      : " . asset('style.css') . "  <- buka alamat ini di browser, harus tampil kode CSS\n\n";

$tabel = [
    'lms_admin'     => ['admin', 'fakultas', 'prodi', 'kelas', 'kelas_mhs'],
    'lms_dosen'     => ['dosen', 'materi', 'tugas', 'perpanjangan', 'nilai'],
    'lms_mahasiswa' => ['mahasiswa', 'pengumpulan']
];

foreach ($tabel as $dbName => $list) {
    foreach ($list as $t) {
        try {
            db()->query("SELECT 1 FROM $dbName.$t LIMIT 1");
            echo "OK      $dbName.$t\n";
        } catch (Exception $ex) {
            echo "HILANG  $dbName.$t\n";
        }
    }
}
?>