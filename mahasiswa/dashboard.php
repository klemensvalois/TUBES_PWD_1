<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs = $_SESSION['u']['id'];
$sort   = (isset($_GET['s']) && $_GET['s'] === 'matkul') ? 'matkul' : 'tanggal';
$order  = ($sort === 'matkul') ? 'k.nama, dl' : 'dl';

$sql = "SELECT t.id, t.judul, k.nama AS kelas, k.tahun_ajaran AS ta, " . DL . " AS dl,
               (SELECT COUNT(*) FROM lms_mahasiswa.pengumpulan g WHERE g.tugas_id = t.id AND g.mhs_id = ?) AS sdh
        FROM lms_dosen.tugas t
        JOIN lms_admin.kelas k ON k.id = t.kelas_id
        JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
        ORDER BY " . $order;
$rows = q($sql, [$id_mhs, $id_mhs, $id_mhs])->fetchAll();

$marks = [];
foreach ($rows as $r) {
    if (date('Ym', strtotime($r['dl'])) === date('Ym')) {
        $tgl = (int) date('j', strtotime($r['dl']));
        $marks[$tgl] = true;
    }
}

top('Dashboard', 'mahasiswa/dashboard.php', false);

$sel_matkul = ($sort === 'matkul') ? ' selected="selected"' : '';
echo '<div class="split"><div class="l">';
echo '<form method="get" action="dashboard.php" class="bar2">';
echo '<select name="s" onchange="this.form.submit()" style="width:200px">';
echo '<option value="tanggal">Urutkan berdasarkan: Tanggal</option>';
echo '<option value="matkul"' . $sel_matkul . '>Urutkan berdasarkan: Mata kuliah</option>';
echo '</select>';
echo '<input id="filter" placeholder="Cari" type="text" />';
echo '</form>';

echo '<div class="box">';
$grup_terakhir = '';
foreach ($rows as $r) {
    $grup = ($sort === 'matkul') ? $r['kelas'] : hari($r['dl']);
    if ($grup !== $grup_terakhir) {
        echo '<div class="dgroup">' . e($grup) . '</div>';
        $grup_terakhir = $grup;
    }
    $badge = $r['sdh']
        ? '<span class="tag ex">Terkumpul</span>'
        : '<span class="tm">' . date('H:i', strtotime($r['dl'])) . '</span>';
    echo '<a class="dl-item" href="' . BASE . '/mahasiswa/tugas.php#t' . $r['id'] . '">';
    echo '<span class="ic red">&#9635;</span>';
    echo '<span class="tt">' . e($r['judul']) . '<small>Batas Pengumpulan- ' . e($r['kelas']) . ' (' . e($r['ta']) . ')</small></span>';
    echo $badge . ' &rsaquo;</a>';
}

if (!$rows) {
    echo 'Belum ada tugas.';
}

echo '</div>';
cal($marks);
echo '</div>';
panel($id_mhs, 'mahasiswa');
echo '</div>';
bottom();
?>