<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $aksi     = $_POST['a'] ?? 'kumpul';
    $id_tugas = (int) ($_POST['tugas_id'] ?? 0);
    $catatan  = trim($_POST['cat'] ?? '');

    $tugas = q("SELECT " . DL . " AS dl
                FROM lms_dosen.tugas t
                JOIN lms_admin.kelas_mhs km ON km.kelas_id = t.kelas_id AND km.mhs_id = ?
                WHERE t.id = ?", [$id_mhs, $id_mhs, $id_tugas])->fetch();

    if (!$tugas) {
        flash('Tugas tidak ditemukan.');
    } elseif (strtotime($tugas['dl']) < time()) {
        flash('Deadline sudah lewat.');
    } elseif ($aksi === 'hapus') {
        $lama = q("SELECT file FROM lms_mahasiswa.pengumpulan WHERE tugas_id=? AND mhs_id=?", [$id_tugas, $id_mhs])->fetch();
        if ($lama && !empty($lama['file'])) {
            $path_file = dirname(__DIR__) . '/uploads/tugas/' . basename($lama['file']);
            if (is_file($path_file)) {
                unlink($path_file);
            }
            $glob_file = glob(dirname(__DIR__) . '/uploads/tugas/t_' . $id_tugas . '_mhs_' . $id_mhs . '.*');
            for ($k = 0; $k < count($glob_file); $k++) {
                if (is_file($glob_file[$k])) {
                    unlink($glob_file[$k]);
                }
            }
        }
        q("DELETE FROM lms_mahasiswa.pengumpulan WHERE tugas_id=? AND mhs_id=?", [$id_tugas, $id_mhs]);
        flash('Pengumpulan tugas dihapus.');
    } else {
        $ada_file = !empty($_FILES['file']['name']) && (int) $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
        $lama     = q("SELECT file FROM lms_mahasiswa.pengumpulan WHERE tugas_id=? AND mhs_id=?", [$id_tugas, $id_mhs])->fetch();

        if ($ada_file) {
            if ((int) $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                flash('File gagal diunggah.');
                go('mahasiswa/tugas.php');
            }
            $ext   = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $boleh = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'jpg', 'jpeg', 'png'];
            if (!in_array($ext, $boleh, true)) {
                flash('Format file tidak didukung.');
                go('mahasiswa/tugas.php');
            }
            $dir = dirname(__DIR__) . '/uploads/tugas';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $lama_glob = glob($dir . '/t_' . $id_tugas . '_mhs_' . $id_mhs . '.*');
            for ($k = 0; $k < count($lama_glob); $k++) {
                if (is_file($lama_glob[$k])) {
                    unlink($lama_glob[$k]);
                }
            }
            $nama_file = 't_' . $id_tugas . '_mhs_' . $id_mhs . '.' . $ext;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . '/' . $nama_file)) {
                flash('File gagal disimpan.');
                go('mahasiswa/tugas.php');
            }
        } elseif ($lama && !empty($lama['file'])) {
            $nama_file = $lama['file'];
        } else {
            flash('Pilih file tugas terlebih dahulu.');
            go('mahasiswa/tugas.php');
        }

        q("INSERT INTO lms_mahasiswa.pengumpulan(tugas_id, mhs_id, file, catatan) VALUES(?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE file=VALUES(file), catatan=VALUES(catatan), waktu=NOW()",
          [$id_tugas, $id_mhs, $nama_file, $catatan]);
        flash('Tugas dikumpulkan.');
    }

    go('mahasiswa/tugas.php');
}

top('Pengumpulan Tugas', 'mahasiswa/dashboard.php');

$filter_kelas = (int) ($_GET['k'] ?? 0);
$edit_t       = isset($_GET['edit_t']) ? (int) $_GET['edit_t'] : 0;
$daftar_tugas = q("SELECT t.*, k.nama AS kelas, " . DL . " AS dl, g.file, g.catatan, g.waktu
                   FROM lms_dosen.tugas t
                   JOIN lms_admin.kelas k ON k.id = t.kelas_id
                   JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
                   LEFT JOIN lms_mahasiswa.pengumpulan g ON g.tugas_id = t.id AND g.mhs_id = ?
                   ORDER BY dl", [$id_mhs, $id_mhs, $id_mhs])->fetchAll();

foreach ($daftar_tugas as $t) {
    if ($filter_kelas > 0 && (int) $t['kelas_id'] !== $filter_kelas) {
        continue;
    }

    $tid       = (int) $t['id'];
    $ts_dl     = strtotime($t['dl']);
    $is_late   = ($ts_dl < time());
    $sudah_ada = !empty($t['waktu']);
    $f_nama    = !empty($t['file']) ? basename($t['file']) : '';
    $buka_form = (!$is_late) && (!$sudah_ada || $edit_t === $tid);
    $box       = (!$sudah_ada && !$is_late && ($ts_dl - time()) < 86400) ? 'box warn' : 'box';
    ?>
    <div id="t<?php echo $tid; ?>" class="<?php echo $box; ?>">
        <b><?php echo e($t['judul']); ?></b> <small>(<?php echo e($t['kelas']); ?>)</small>
        <?php if ($t['dl'] !== $t['deadline']) { ?>
            <span class="tag ext">Waktu tambahan dari dosen</span>
        <?php } ?>
        <p><?php echo nl2br(e($t['deskripsi'])); ?></p>
        Batas: <?php echo e($t['dl']); ?>
        <?php echo $is_late ? '<span class="tag late">Lewat</span>' : '<span class="cd" data-dl="' . ($ts_dl * 1000) . '"></span>'; ?>
        <?php if ($sudah_ada) { ?>
            <div style="margin-top:6px">Terkumpul <?php echo e($t['waktu']); ?></div>
            <?php if (!empty($t['catatan'])) { ?>
                <p class="mut"><small>Catatan: <?php echo e($t['catatan']); ?></small></p>
            <?php } ?>
            <?php if ($f_nama !== '') { ?>
                <p style="margin-top:8px">
                    <a class="btn" href="<?php echo BASE . '/uploads/tugas/' . e($f_nama); ?>" target="_blank">Lihat File (<?php echo e($f_nama); ?>)</a>
                </p>
            <?php } ?>
        <?php } ?>

        <?php if (!$is_late && $sudah_ada && $edit_t !== $tid) { ?>
            <div style="margin-top:10px">
                <a class="btn" href="tugas.php?edit_t=<?php echo $tid; ?>#t<?php echo $tid; ?>">Edit Tugas</a>
                <form method="post" action="tugas.php" style="display:inline;margin-left:6px">
                    <?php echo csrf(); ?>
                    <input type="hidden" name="a" value="hapus" />
                    <input type="hidden" name="tugas_id" value="<?php echo $tid; ?>" />
                    <button class="del" type="submit">Delete</button>
                </form>
            </div>
        <?php } ?>

        <?php if ($buka_form) { ?>
            <form method="post" action="tugas.php" enctype="multipart/form-data" class="submit-tugas-form" style="margin-top:10px">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="kumpul" />
                <input type="hidden" name="tugas_id" value="<?php echo $tid; ?>" />
                <label>File jawaban <input type="file" name="file" /></label>
                <label>Catatan <input type="text" name="cat" value="<?php echo e($t['catatan'] ?? ''); ?>" placeholder="Catatan" /></label>
                <button type="submit"><?php echo $sudah_ada ? 'Simpan Perubahan' : 'Kumpulkan'; ?></button>
                <?php if ($sudah_ada) { ?>
                    <a class="btn" href="tugas.php#t<?php echo $tid; ?>">Batal</a>
                <?php } ?>
            </form>
        <?php } ?>
    </div>
    <?php
}

bottom();
?>