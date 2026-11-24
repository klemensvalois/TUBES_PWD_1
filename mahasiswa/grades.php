<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs = $_SESSION['u']['id'];

$kelas_list = q("SELECT k.id, k.nama, k.kode, d.nama AS dosen
                 FROM lms_admin.kelas k
                 JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
                 LEFT JOIN lms_dosen.dosen d ON d.id = k.dosen_id", [$id_mhs])->fetchAll();

$tugas_list = q("SELECT t.kelas_id AS kid, " . DL . " AS dl, g.waktu, n.nilai
                 FROM lms_dosen.tugas t
                 JOIN lms_admin.kelas_mhs km ON km.kelas_id = t.kelas_id AND km.mhs_id = ?
                 LEFT JOIN lms_mahasiswa.pengumpulan g ON g.tugas_id = t.id AND g.mhs_id = ?
                 LEFT JOIN lms_dosen.nilai n ON n.tugas_id = t.id AND n.mhs_id = ?",
                [$id_mhs, $id_mhs, $id_mhs, $id_mhs])->fetchAll();

$total_tugas   = 0;
$total_kumpul  = 0;
$total_tepat   = 0;
$semua_nilai   = [];
$nilai_per_mk  = [];

foreach ($tugas_list as $item) {
    $kid = $item['kid'];
    if (!isset($nilai_per_mk[$kid])) {
        $nilai_per_mk[$kid] = [];
    }
    $total_tugas++;

    if (!empty($item['waktu'])) {
        $total_kumpul++;
        if (strtotime($item['waktu']) <= strtotime($item['dl'])) {
            $total_tepat++;
        }
    }

    if ($item['nilai'] !== null && $item['nilai'] !== '') {
        $angka = (float) $item['nilai'];
        $nilai_per_mk[$kid][] = $angka;
        $semua_nilai[] = $angka;
    }
}

$rata_skala5 = [];
foreach ($kelas_list as $k) {
    $kid = $k['id'];
    $arr = isset($nilai_per_mk[$kid]) ? $nilai_per_mk[$kid] : [];
    if (count($arr) > 0) {
        $rata_100 = array_sum($arr) / count($arr);
        $rata_skala5[$kid] = $rata_100 / 20;
    } else {
        $rata_skala5[$kid] = null;
    }
}

$rata_terisi = array_filter($rata_skala5, function ($v) {
    return $v !== null;
});
$rata_keseluruhan = (count($rata_terisi) > 0) ? (array_sum($rata_terisi) / count($rata_terisi)) : 0;

$mk_terbaik = '-';
if (count($rata_terisi) > 0) {
    $salinan = $rata_terisi;
    arsort($salinan);
    $id_terbaik = key($salinan);
    foreach ($kelas_list as $k) {
        if ($k['id'] == $id_terbaik) {
            $mk_terbaik = $k['nama'];
            break;
        }
    }
}

$rasio_tepat      = ($total_kumpul > 0) ? ($total_tepat / $total_kumpul) : 0;
$rasio_lengkap    = ($total_tugas > 0) ? ($total_kumpul / $total_tugas) : 0;
$rasio_nilai      = (count($semua_nilai) > 0) ? ((array_sum($semua_nilai) / count($semua_nilai)) / 100) : 0;
if (count($semua_nilai) > 1) {
    $rentang = max($semua_nilai) - min($semua_nilai);
    $rasio_konsisten = max(0, 1 - ($rentang / 100));
} else {
    $rasio_konsisten = (count($semua_nilai) === 1) ? 1 : 0;
}

$skor_radar = [$rasio_tepat, $rasio_lengkap, $rasio_nilai, $rasio_konsisten];
$label_radar = ['Ketepatan waktu', 'Kelengkapan', 'Nilai', 'Konsistensi'];
$titik_radar = [];
foreach ($skor_radar as $idx => $skor) {
    $sudut = -M_PI / 2 + ($idx * M_PI / 2);
    $x = round(110 + cos($sudut) * 80 * $skor);
    $y = round(110 + sin($sudut) * 80 * $skor);
    $titik_radar[] = $x . ',' . $y;
}

$persen_selesai = ($total_tugas > 0) ? round(($total_kumpul / $total_tugas) * 100) : 0;

top('Grades', 'mahasiswa/grades.php', false);

echo '<div class="stats">';
echo '<div class="stat">Rata-rata Nilai (skala 5)<b>' . number_format($rata_keseluruhan, 1) . '</b></div>';
echo '<div class="stat">Nilai Tertinggi<b>' . e($mk_terbaik) . '</b></div>';
echo '<div class="stat">Penyelesaian Tugas<b>' . $persen_selesai . '%</b></div>';
echo '<div class="stat">Tugas Terkumpul<b>' . $total_kumpul . '</b></div>';
echo '</div>';

echo '<div class="charts"><div class="box"><b style="font-size:11px">Rekap Performa Mahasiswa</b><table><tr><th>Mata Kuliah</th><th>Kode</th><th>Dosen</th><th>Nilai</th><th>Keterangan</th></tr>';
foreach ($kelas_list as $k) {
    $nilai_mk = $rata_skala5[$k['id']];
    if ($nilai_mk === null) {
        $teks_nilai = '-';
        $ket = ['-', ''];
    } else {
        $teks_nilai = number_format($nilai_mk, 1);
        if ($nilai_mk >= 4.5) {
            $ket = ['Excellent', 'ex'];
        } elseif ($nilai_mk >= 4) {
            $ket = ['Good', 'good'];
        } else {
            $ket = ['Needs Attention', 'na'];
        }
    }
    echo '<tr><td>' . e($k['nama']) . '</td><td>' . e($k['kode']) . '</td><td>' . e($k['dosen']) . '</td><td>' . $teks_nilai . '</td><td><span class="tag ' . $ket[1] . '">' . $ket[0] . '</span></td></tr>';
}
echo '</table></div>';

echo '<div class="box" style="width:300px"><b style="font-size:11px">Radar Kompetensi</b><svg viewBox="0 0 220 220" width="100%">';
echo '<polygon points="110,30 190,110 110,190 30,110" fill="none" stroke="#ccc"/>';
echo '<polygon points="110,70 150,110 110,150 70,110" fill="none" stroke="#ddd"/>';
echo '<polygon points="' . implode(' ', $titik_radar) . '" fill="#fdd68b" fill-opacity=".7" stroke="#4a6fd1" stroke-dasharray="3"/>';
echo '<text x="110" y="22" font-size="9" text-anchor="middle" fill="#f5a623">' . $label_radar[0] . '</text>';
echo '<text x="206" y="113" font-size="9" text-anchor="end" fill="#f5a623">' . $label_radar[1] . '</text>';
echo '<text x="110" y="208" font-size="9" text-anchor="middle" fill="#f5a623">' . $label_radar[2] . '</text>';
echo '<text x="4" y="113" font-size="9" fill="#f5a623">' . $label_radar[3] . '</text>';
echo '</svg></div></div>';

echo '<div class="box"><b style="font-size:11px">Rata-rata Nilai per Mata Kuliah</b><br /><br /><div class="bars">';
foreach ($kelas_list as $k) {
    $nilai_mk = ($rata_skala5[$k['id']] !== null) ? $rata_skala5[$k['id']] : 0;
    $tinggi   = round(($nilai_mk / 5) * 100);
    echo '<div style="height:' . $tinggi . '%"><span>' . e($k['nama']) . '</span></div>';
}
echo '</div><br /><br /></div>';

bottom();
?>