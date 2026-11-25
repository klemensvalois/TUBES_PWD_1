<?php
require '../config.php';
need('dosen');
require '../includes/layout.php';

$id_dosen = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $id_tugas = (int) $_POST['tugas_id'];
    $id_mhs   = (int) $_POST['m'];
    $nilai    = (float) $_POST['nilai'];
    $catatan  = trim($_POST['cat'] ?? '');

    $valid = q("SELECT 1 FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [$id_tugas, $id_dosen])->fetch();
    if ($nilai >= 0 && $nilai <= 100 && $valid) {
        q("INSERT INTO lms_dosen.nilai(tugas_id, mhs_id, nilai, catatan) VALUES(?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE nilai=VALUES(nilai), catatan=VALUES(catatan)",
          [$id_tugas, $id_mhs, $nilai, $catatan]);
        flash('Nilai disimpan.');
    } else {
        flash('Nilai harus 0-100.');
    }

    go('dosen/nilai.php?t=' . $id_tugas);
}

$daftar_tugas   = q("SELECT * FROM lms_dosen.tugas WHERE dosen_id=? ORDER BY id DESC", [$id_dosen])->fetchAll();
$id_tugas_aktif = (int) ($_GET['t'] ?? ($daftar_tugas[0]['id'] ?? 0));

top('Penilaian', 'dosen/nilai.php');

echo '<form method="get" action="nilai.php"><select name="t" onchange="this.form.submit()">';
foreach ($daftar_tugas as $x) {
    $sel = ((int) $x['id'] === $id_tugas_aktif) ? ' selected="selected"' : '';
    echo '<option value="' . $x['id'] . '"' . $sel . '>' . e($x['judul']) . '</option>';
}
echo '</select></form><br />';

$peserta = q("SELECT m.id, m.npm, m.nama, g.file, g.waktu, n.nilai, n.catatan
              FROM lms_dosen.tugas t
              JOIN lms_admin.kelas_mhs km ON km.kelas_id = t.kelas_id
              JOIN lms_mahasiswa.mahasiswa m ON m.id = km.mhs_id
              LEFT JOIN lms_mahasiswa.pengumpulan g ON g.tugas_id = t.id AND g.mhs_id = m.id
              LEFT JOIN lms_dosen.nilai n ON n.tugas_id = t.id AND n.mhs_id = m.id
              WHERE t.id=? AND t.dosen_id=?", [$id_tugas_aktif, $id_dosen])->fetchAll();
?>
<table>
    <tr><th>NPM</th><th>Nama</th><th>Pengumpulan</th><th>Nilai</th></tr>
    <?php foreach ($peserta as $r) { ?>
        <tr>
            <td><?php echo e($r['npm']); ?></td>
            <td><?php echo e($r['nama']); ?></td>
            <td>
                <?php echo $r['file']
                    ? '<a href="' . BASE . '/uploads/' . e($r['file']) . '">Unduh</a> (' . e($r['waktu']) . ')'
                    : '<span class="tag late">Belum</span>'; ?>
            </td>
            <td>
                <form method="post" action="nilai.php">
                    <?php echo csrf(); ?>
                    <input type="hidden" name="tugas_id" value="<?php echo $id_tugas_aktif; ?>" />
                    <input type="hidden" name="m" value="<?php echo $r['id']; ?>" />
                    <input type="text" name="nilai" value="<?php echo e($r['nilai']); ?>" style="width:70px" />
                    <input type="text" name="cat" value="<?php echo e($r['catatan']); ?>" placeholder="Catatan" style="width:160px" />
                    <button type="submit">Simpan</button>
                </form>
            </td>
        </tr>
    <?php } ?>
</table>
<?php
bottom();
?>