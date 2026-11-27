<?php
require '../config.php';
need('mahasiswa');
require '../includes/layout.php';

$id_mhs   = $_SESSION['u']['id'];
$id_kelas = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$kelas = q("SELECT k.*
            FROM lms_admin.kelas k
            JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
            WHERE k.id = ?", [$id_mhs, $id_kelas])->fetch();

if (!$kelas) {
    $kelas = q("SELECT k.*
                FROM lms_admin.kelas k
                JOIN lms_admin.kelas_mhs km ON km.kelas_id = k.id AND km.mhs_id = ?
                ORDER BY k.id DESC LIMIT 1", [$id_mhs])->fetch();
    $id_kelas = $kelas ? (int) $kelas['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kelas) {
    chk();
    $aksi     = $_POST['a'] ?? '';
    $id_tugas = (int) ($_POST['tugas_id'] ?? 0);
    $dir      = dirname(__DIR__) . '/uploads/tugas';

    if ($aksi === 'kumpul') {
        $cek_t   = q("SELECT id FROM lms_dosen.tugas WHERE id=? AND kelas_id=?", [$id_tugas, $id_kelas])->fetch();
        $catatan = trim($_POST['catatan'] ?? '');
        $ext     = '';
        $ada_f   = !empty($_FILES['berkas']['name']) && (int) $_FILES['berkas']['error'] === UPLOAD_ERR_OK;

        if (!$cek_t) {
            flash('Tugas tidak ditemukan.');
        } elseif ($catatan === '' && !$ada_f) {
            flash('Isi catatan atau pilih file tugas dulu.');
        } else {
            if ($ada_f) {
                $ext   = strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION));
                $boleh = ['pdf', 'doc', 'docx', 'zip', 'rar', 'txt', 'jpg', 'png'];
                if (!in_array($ext, $boleh, true)) {
                    flash('Format file tugas tidak didukung.');
                    go('mahasiswa/kelas.php?id=' . $id_kelas);
                }
            }

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $nama_f = '';
            if ($ada_f) {
                $lama = glob($dir . '/t_' . $id_tugas . '_mhs_' . $id_mhs . '.*');
                for ($k = 0; $k < count($lama); $k++) {
                    if (is_file($lama[$k])) {
                        unlink($lama[$k]);
                    }
                }
                $nama_f = 't_' . $id_tugas . '_mhs_' . $id_mhs . '.' . $ext;
                if (!move_uploaded_file($_FILES['berkas']['tmp_name'], $dir . '/' . $nama_f)) {
                    $nama_f = '';
                }
            }

            $ada_k = q("SELECT id, file FROM lms_mahasiswa.pengumpulan WHERE tugas_id=? AND mhs_id=?", [$id_tugas, $id_mhs])->fetch();
            if ($ada_k) {
                if ($nama_f === '') {
                    $nama_f = $ada_k['file'];
                }
                q("UPDATE lms_mahasiswa.pengumpulan SET file=?, catatan=?, waktu=NOW() WHERE id=? AND mhs_id=?",
                  [$nama_f, $catatan, (int) $ada_k['id'], $id_mhs]);
            } else {
                q("INSERT INTO lms_mahasiswa.pengumpulan(tugas_id, mhs_id, file, catatan, waktu) VALUES(?, ?, ?, ?, NOW())",
                  [$id_tugas, $id_mhs, $nama_f, $catatan]);
            }

            flash('Tugas berhasil dikumpulkan.');
        }
    } elseif ($aksi === 'batal') {
        $lama = glob($dir . '/t_' . $id_tugas . '_mhs_' . $id_mhs . '.*');
        for ($k = 0; $k < count($lama); $k++) {
            if (is_file($lama[$k])) {
                unlink($lama[$k]);
            }
        }
        q("DELETE FROM lms_mahasiswa.pengumpulan WHERE tugas_id=? AND mhs_id=?", [$id_tugas, $id_mhs]);
        flash('Pengumpulan tugas dihapus.');
    }

    go('mahasiswa/kelas.php?id=' . $id_kelas);
}

top('Kelas', 'mahasiswa/kelas.php');

if (!$kelas) {
    echo '<div class="box">Kamu belum terdaftar di kelas mana pun.</div>';
    bottom();
    exit;
}

$jml_week = max(1, (int) ($kelas['jml_week'] ?? 1));
$buka_t   = isset($_GET['kumpul']) ? (int) $_GET['kumpul'] : 0;

$materi = q("SELECT * FROM lms_dosen.materi WHERE kelas_id=? ORDER BY id ASC", [$id_kelas])->fetchAll();
$tugas  = q("SELECT t.*, p.file AS f_kumpul, p.catatan, p.waktu
             FROM lms_dosen.tugas t
             LEFT JOIN lms_mahasiswa.pengumpulan p ON p.tugas_id = t.id AND p.mhs_id = ?
             WHERE t.kelas_id=?
             ORDER BY t.id ASC", [$id_mhs, $id_kelas])->fetchAll();

$n_m = count($materi);
$n_t = count($tugas);
?>

<div class="box">
    <b><?php echo e($kelas['nama']); ?></b> <small>(<?php echo e($kelas['kode']); ?> &mdash; <?php echo e($kelas['tahun_ajaran']); ?>)</small>
</div>

<?php
for ($w = 1; $w <= $jml_week; $w++) {
    $ada_isi = false;
    ?>
    <div class="box">
        <b>Week <?php echo $w; ?></b>

        <?php
        for ($i = 0; $i < $n_m; $i++) {
            $m = $materi[$i];
            if ((int) $m['week'] === $w) {
                $ada_isi = true;
                $f_cek   = glob(dirname(__DIR__) . '/uploads/materi/m_' . (int) $m['id'] . '.*');
                $f_nama  = ($f_cek && isset($f_cek[0])) ? basename($f_cek[0]) : '';
                ?>
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid #eee">
                    <span class="tag">Materi</span> <b><?php echo e($m['judul']); ?></b>
                    <?php if ($m['isi'] !== '') { ?>
                        <p><?php echo nl2br(e($m['isi'])); ?></p>
                    <?php } ?>
                    <?php if ($f_nama !== '') { ?>
                        <p><a class="btn" href="<?php echo BASE . '/uploads/materi/' . e($f_nama); ?>" download="<?php echo e($f_nama); ?>">Unduh Materi (<?php echo e($f_nama); ?>)</a></p>
                    <?php } ?>
                </div>
                <?php
            }
        }

        for ($i = 0; $i < $n_t; $i++) {
            $t = $tugas[$i];
            if ((int) $t['week'] === $w) {
                $ada_isi = true;
                $sudah   = !empty($t['waktu']);
                ?>
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid #eee">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <span class="tag late">Tugas</span> <b><?php echo e($t['judul']); ?></b>
                            <small>(Deadline: <?php echo e($t['deadline']); ?>)</small>
                            <?php if ($sudah) { ?>
                                <span class="tag">Sudah Dikumpul</span>
                            <?php } ?>
                        </div>
                        <div>
                            <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>&amp;kumpul=<?php echo (int) $t['id']; ?>">
                                <?php echo $sudah ? 'Edit Tugas' : 'Kumpul Tugas'; ?>
                            </a>
                            <?php if ($sudah) { ?>
                                <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="display:inline;margin-left:4px">
                                    <?php echo csrf(); ?>
                                    <input type="hidden" name="a" value="batal" />
                                    <input type="hidden" name="tugas_id" value="<?php echo (int) $t['id']; ?>" />
                                    <button class="del" type="submit">Hapus</button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if ($t['deskripsi'] !== '') { ?>
                        <p><?php echo nl2br(e($t['deskripsi'])); ?></p>
                    <?php } ?>

                    <?php if ($sudah) { ?>
                        <p class="mut"><small>Dikumpulkan: <?php echo e($t['waktu']); ?></small></p>
                        <?php if (!empty($t['catatan'])) { ?>
                            <p><?php echo nl2br(e($t['catatan'])); ?></p>
                        <?php } ?>
                        <?php if (!empty($t['f_kumpul'])) { ?>
                            <p><a class="btn" href="<?php echo BASE . '/uploads/tugas/' . e($t['f_kumpul']); ?>" download="<?php echo e($t['f_kumpul']); ?>">Unduh File Tugas (<?php echo e($t['f_kumpul']); ?>)</a></p>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($buka_t === (int) $t['id']) { ?>
                        <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" enctype="multipart/form-data" class="card" style="margin-top:10px">
                            <?php echo csrf(); ?>
                            <input type="hidden" name="a" value="kumpul" />
                            <input type="hidden" name="tugas_id" value="<?php echo (int) $t['id']; ?>" />
                            <b>Input Tugas: <?php echo e($t['judul']); ?></b>
                            <label>File Tugas <input type="file" name="berkas" /></label>
                            <label>Catatan <textarea name="catatan" rows="2" cols="50"><?php echo e($t['catatan'] ?? ''); ?></textarea></label>
                            <button type="submit">Kirim Tugas</button>
                            <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>">Batal</a>
                        </form>
                    <?php } ?>
                </div>
                <?php
            }
        }

        if (!$ada_isi) {
            echo '<p class="mut" style="margin-top:8px">Belum ada materi atau tugas di week ini.</p>';
        }
        ?>
    </div>
    <?php
}

bottom();
?>