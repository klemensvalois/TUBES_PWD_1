<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $id_tugas = (int) $_POST['tugas_id'];
    $catatan  = trim($_POST['cat'] ?? '');

    $tugas = q("SELECT " . DL . " AS dl
                FROM lms_dosen.tugas t
                JOIN lms_admin.kelas_mhs km ON km.kelas_id = t.kelas_id AND km.mhs_id = ?
                WHERE t.id = ?", [$id_mhs, $id_mhs, $id_tugas])->fetch();

    if (!$tugas) {
        flash('Tugas tidak ditemukan.');
    } elseif (strtotime($tugas['dl']) < time()) {
        flash('Deadline sudah lewat.');
    } else {
        $nama_file = upload($_FILES['file'] ?? null);
        if (!$nama_file) {
            flash('File gagal (pdf/doc/zip/dll, maks 5 MB).');
        } else {
            q("INSERT INTO lms_mahasiswa.pengumpulan(tugas_id, mhs_id, file, catatan) VALUES(?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE file=VALUES(file), catatan=VALUES(catatan), waktu=NOW()",
              [$id_tugas, $id_mhs, $nama_file, $catatan]);
            flash('Tugas dikumpulkan.');
        }
    }

    go('mahasiswa/tugas.php');
}

top('Tugas & Materi', 'mahasiswa/dashboard.php');

$filter_kelas = (int) ($_GET['k'] ?? 0);
$daftar_tugas = q("SELECT t.*, k.nama AS kelas, " . DL . " AS dl, g.file, g.waktu
                   FROM lms_dosen.tugas t
                   JOIN lms_admin.kelas k ON k.id = t.kelas_id
                   JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
                   LEFT JOIN lms_mahasiswa.pengumpulan g ON g.tugas_id = t.id AND g.mhs_id = ?
                   ORDER BY dl", [$id_mhs, $id_mhs, $id_mhs])->fetchAll();

foreach ($daftar_tugas as $t) {
    if ($filter_kelas > 0 && (int) $t['kelas_id'] !== $filter_kelas) {
        continue;
    }

    $ts_dl   = strtotime($t['dl']);
    $is_late = ($ts_dl < time());
    $box     = (!$t['file'] && !$is_late && ($ts_dl - time()) < 86400) ? 'box warn' : 'box';
    ?>
    <div id="t<?php echo $t['id']; ?>" class="<?php echo $box; ?>">
        <b><?php echo e($t['judul']); ?></b> <small>(<?php echo e($t['kelas']); ?>)</small>
        <?php if ($t['dl'] !== $t['deadline']) { ?>
            <span class="tag ext">Waktu tambahan dari dosen</span>
        <?php } ?>
        <p><?php echo nl2br(e($t['deskripsi'])); ?></p>
        Batas: <?php echo e($t['dl']); ?>
        <?php echo $is_late ? '<span class="tag late">Lewat</span>' : '<span class="cd" data-dl="' . ($ts_dl * 1000) . '"></span>'; ?>
        <?php if ($t['file']) { ?>
            <div style="margin-top:6px">Terkumpul <?php echo e($t['waktu']); ?></div>
        <?php } ?>

        <?php if (!$is_late) { ?>
            <form method="post" action="tugas.php" enctype="multipart/form-data" class="submit-tugas-form">
                <?php echo csrf(); ?>
                <input type="hidden" name="tugas_id" value="<?php echo $t['id']; ?>" />
                <label>File jawaban <input type="file" name="file" /></label>
                <label>Catatan <input type="text" name="cat" placeholder="Catatan" /></label>
                <button type="submit"><?php echo $t['file'] ? 'Kumpulkan ulang' : 'Kumpulkan'; ?></button>
            </form>
        <?php } ?>
    </div>
    <?php
}

echo '<h2>Materi</h2>';
$daftar_materi = q("SELECT m.*, k.nama AS kelas
                    FROM lms_dosen.materi m
                    JOIN lms_admin.kelas k ON k.id = m.kelas_id
                    JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
                    ORDER BY m.id DESC", [$id_mhs])->fetchAll();

foreach ($daftar_materi as $m) {
    echo '<div class="box"><b>' . e($m['judul']) . '</b> <small>(' . e($m['kelas']) . ')</small><p>' . nl2br(e($m['isi'])) . '</p></div>';
}

bottom();
?>