<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $id_tugas = isset($_POST['tugas_id']) ? (int) $_POST['tugas_id'] : (int) ($_POST['t'] ?? 0);
    $catatan  = isset($_POST['cat']) ? trim($_POST['cat']) : '';

    $tugas = q("SELECT " . DL . " AS dl
                FROM lms_dosen.tugas t
                JOIN lms_admin.kelas_mhs km ON km.kelas_id = t.kelas_id AND km.mhs_id = ?
                WHERE t.id = ?", [$id_mhs, $id_mhs, $id_tugas])->fetch();

    if (!$tugas) {
        flash('Tugas tidak ditemukan.');
    } elseif (strtotime($tugas['dl']) < time()) {
        flash('Deadline sudah lewat.');
    } else {
        $berkas_input = isset($_FILES['file']) ? $_FILES['file'] : null;
        $nama_file = upload($berkas_input);

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

top('Tugas & Materi', 'mahasiswa/dashboard.php', true, ['tables-badges.css']);

$filter_kelas = isset($_GET['k']) ? (int) $_GET['k'] : 0;

$sql_tugas = "SELECT t.*, k.nama AS kelas, " . DL . " AS dl, g.file, g.waktu
              FROM lms_dosen.tugas t
              JOIN lms_admin.kelas k ON k.id = t.kelas_id
              JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
              LEFT JOIN lms_mahasiswa.pengumpulan g ON g.tugas_id = t.id AND g.mhs_id = ?
              ORDER BY dl";
$daftar_tugas = q($sql_tugas, [$id_mhs, $id_mhs, $id_mhs])->fetchAll();

foreach ($daftar_tugas as $t) {
    if ($filter_kelas > 0 && (int) $t['kelas_id'] !== $filter_kelas) {
        continue;
    }

    $ts_dl        = strtotime($t['dl']);
    $is_ext       = ($t['dl'] !== $t['deadline']);
    $is_late      = ($ts_dl < time());
    $hampir_dl    = (!$t['file'] && !$is_late && ($ts_dl - time()) < 86400);
    $kelas_box    = $hampir_dl ? 'box warn' : 'box';
    $label_tombol = $t['file'] ? 'Kumpulkan ulang' : 'Kumpulkan';
    ?>
    <div id="t<?php echo (int) $t['id']; ?>" class="<?php echo $kelas_box; ?>">
        <b><?php echo e($t['judul']); ?></b> <small>(<?php echo e($t['kelas']); ?>)</small>
        <?php
        if ($is_ext) {
            echo ' <span class="tag ext">Waktu tambahan dari dosen</span>';
        }
        ?>
        <p><?php echo nl2br(e($t['deskripsi'])); ?></p>
        <div>
            Batas: <?php echo e($t['dl']); ?>
            <?php
            if ($is_late) {
                echo ' <span class="tag late">Lewat</span>';
            } else {
                echo ' <span class="cd" data-dl="' . ($ts_dl * 1000) . '"></span>';
            }
            ?>
        </div>
        <?php
        if ($t['file']) {
            echo '<div style="margin-top: 6px; font-size: 12px;">Terkumpul ' . e($t['waktu']) . '</div>';
        }
        ?>
        <?php if (!$is_late) { ?>
            <form method="post" action="tugas.php" enctype="multipart/form-data" class="submit-tugas-form">
                <?php echo csrf(); ?>
                <input type="hidden" name="tugas_id" value="<?php echo (int) $t['id']; ?>" />

                <label>File jawaban
                    <input type="file" name="file" />
                </label>

                <label>Catatan
                    <input type="text" name="cat" placeholder="Catatan" />
                </label>

                <button type="submit"><?php echo e($label_tombol); ?></button>
            </form>
        <?php } ?>
    </div>
    <?php
}

echo '<h2>Materi</h2>';

$sql_materi = "SELECT m.*, k.nama AS kelas
               FROM lms_dosen.materi m
               JOIN lms_admin.kelas k ON k.id = m.kelas_id
               JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
               ORDER BY m.id DESC";
$daftar_materi = q($sql_materi, [$id_mhs])->fetchAll();

foreach ($daftar_materi as $m) {
    echo '<div class="box"><b>' . e($m['judul']) . '</b> <small>(' . e($m['kelas']) . ')</small><p>' . nl2br(e($m['isi'])) . '</p></div>';
}

bottom();