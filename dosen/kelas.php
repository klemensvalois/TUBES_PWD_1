<?php
require '../config.php';
need('dosen');
require '../includes/layout.php';

$id_dosen = $_SESSION['u']['id'];
$id_kelas = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$kelas = q("SELECT * FROM lms_admin.kelas WHERE id=? AND dosen_id=?", [$id_kelas, $id_dosen])->fetch();
if (!$kelas) {
    $kelas = q("SELECT * FROM lms_admin.kelas WHERE dosen_id=? ORDER BY id DESC LIMIT 1", [$id_dosen])->fetch();
    $id_kelas = $kelas ? (int) $kelas['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kelas) {
    chk();
    $aksi = $_POST['a'] ?? '';
    $week = max(1, (int) ($_POST['week'] ?? 1));
    $jml_now = max(1, (int) ($kelas['jml_week'] ?? 1));
    $dir = dirname(__DIR__) . '/uploads/materi';

    if ($aksi === 'addweek') {
        q("UPDATE lms_admin.kelas SET jml_week = jml_week + 1 WHERE id=? AND dosen_id=?", [$id_kelas, $id_dosen]);
        flash('Week baru ditambahkan.');
    } elseif ($aksi === 'delweek') {
        if ($jml_now <= 1) {
            flash('Minimal harus ada 1 week.');
        } else {
            $m_cek = q("SELECT COUNT(*) c FROM lms_dosen.materi WHERE kelas_id=? AND dosen_id=? AND week=?", [$id_kelas, $id_dosen, $jml_now])->fetch()['c'];
            $t_cek = q("SELECT COUNT(*) c FROM lms_dosen.tugas WHERE kelas_id=? AND dosen_id=? AND week=?", [$id_kelas, $id_dosen, $jml_now])->fetch()['c'];
            if ((int) $m_cek > 0 || (int) $t_cek > 0) {
                flash('Week ' . $jml_now . ' masih ada isinya.');
            } else {
                q("UPDATE lms_admin.kelas SET jml_week = jml_week - 1 WHERE id=? AND dosen_id=?", [$id_kelas, $id_dosen]);
                flash('Week ' . $jml_now . ' dihapus.');
            }
        }
    } elseif ($aksi === 'addmateri') {
        $judul = trim($_POST['judul'] ?? '');
        $isi   = trim($_POST['isi'] ?? '');
        if ($judul === '') {
            flash('Judul materi wajib diisi.');
        } else {
            $ext = '';
            $ada_file = !empty($_FILES['berkas']['name']) && (int) $_FILES['berkas']['error'] === UPLOAD_ERR_OK;
            if ($ada_file) {
                $ext = strtolower(pathinfo($_FILES['berkas']['name'], PATHINFO_EXTENSION));
                $boleh = ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'zip'];
                if (!in_array($ext, $boleh, true)) {
                    flash('Format file harus PDF, PPT/PPTX, DOC/DOCX, atau ZIP.');
                    go('dosen/kelas.php?id=' . $id_kelas);
                }
            }
            q("INSERT INTO lms_dosen.materi(kelas_id, dosen_id, week, judul, isi) VALUES(?, ?, ?, ?, ?)", [$id_kelas, $id_dosen, $week, $judul, $isi]);
            $id_baru = (int) q("SELECT LAST_INSERT_ID() AS id")->fetch()['id'];
            if ($ada_file && $id_baru > 0) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                move_uploaded_file($_FILES['berkas']['tmp_name'], $dir . '/m_' . $id_baru . '.' . $ext);
            }
            flash('Materi ditambahkan ke Week ' . $week . '.');
        }
    } elseif ($aksi === 'delmateri') {
        $id_m = (int) ($_POST['i'] ?? 0);
        $lama = glob($dir . '/m_' . $id_m . '.*');
        for ($k = 0; $k < count($lama); $k++) {
            if (is_file($lama[$k])) {
                unlink($lama[$k]);
            }
        }
        q("DELETE FROM lms_dosen.materi WHERE id=? AND dosen_id=?", [$id_m, $id_dosen]);
        flash('Materi dihapus.');
    } elseif ($aksi === 'addtugas') {
        $judul = trim($_POST['judul'] ?? '');
        $desc  = trim($_POST['desc'] ?? '');
        $ts_dl = strtotime($_POST['dl'] ?? '');
        if ($judul === '' || !$ts_dl) {
            flash('Judul dan deadline wajib diisi.');
        } else {
            q("INSERT INTO lms_dosen.tugas(kelas_id, dosen_id, week, judul, deskripsi, deadline) VALUES(?, ?, ?, ?, ?, ?)", [$id_kelas, $id_dosen, $week, $judul, $desc, date('Y-m-d H:i:s', $ts_dl)]);
            flash('Tugas ditambahkan ke Week ' . $week . '.');
        }
    } elseif ($aksi === 'updtugas') {
        $id_t  = (int) ($_POST['i'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $desc  = trim($_POST['desc'] ?? '');
        $ts_dl = strtotime($_POST['dl'] ?? '');
        if ($judul === '' || !$ts_dl) {
            flash('Judul dan deadline wajib diisi.');
        } else {
            q("UPDATE lms_dosen.tugas SET judul=?, deskripsi=?, deadline=? WHERE id=? AND kelas_id=? AND dosen_id=?",
              [$judul, $desc, date('Y-m-d H:i:s', $ts_dl), $id_t, $id_kelas, $id_dosen]);
            flash('Tugas diperbarui.');
        }
    } elseif ($aksi === 'deltugas') {
        q("DELETE FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [(int) ($_POST['i'] ?? 0), $id_dosen]);
        flash('Tugas dihapus.');
    } elseif ($aksi === 'ext') {
        $id_tugas = (int) ($_POST['i'] ?? 0);
        $ts_dl    = strtotime($_POST['dl'] ?? '');
        $dicari   = trim($_POST['who'] ?? '');
        $alasan   = trim($_POST['alasan'] ?? '');

        $tugas = q("SELECT id FROM lms_dosen.tugas WHERE id=? AND kelas_id=? AND dosen_id=?", [$id_tugas, $id_kelas, $id_dosen])->fetch();
        if (!$tugas || !$ts_dl || $dicari === '') {
            flash('Data perpanjangan tidak valid.');
        } else {
            $mhs = q("SELECT m.id
                      FROM lms_mahasiswa.mahasiswa m
                      JOIN lms_admin.kelas_mhs km ON km.mhs_id = m.id
                      WHERE km.kelas_id = ? AND (m.npm = ? OR m.email = ?)", [$id_kelas, $dicari, $dicari])->fetch();
            if (!$mhs) {
                flash('Mahasiswa tidak ditemukan atau tidak terdaftar di kelas ini.');
            } else {
                q("INSERT INTO lms_dosen.perpanjangan(tugas_id, mhs_id, deadline_baru, alasan) VALUES(?, ?, ?, ?)",
                  [$id_tugas, (int) $mhs['id'], date('Y-m-d H:i:s', $ts_dl), $alasan]);
                flash('Waktu tambahan berhasil diberikan.');
            }
        }
    } elseif ($aksi === 'delext') {
        q("DELETE p FROM lms_dosen.perpanjangan p
           JOIN lms_dosen.tugas t ON t.id = p.tugas_id
           WHERE p.id=? AND t.kelas_id=? AND t.dosen_id=?", [(int) ($_POST['i'] ?? 0), $id_kelas, $id_dosen]);
        flash('Waktu tambahan dicabut.');
    }
    go('dosen/kelas.php?id=' . $id_kelas);
}

top('Kelas', 'dosen/kelas.php');

if (!$kelas) {
    echo '<div class="box">Belum ada kelas yang diampu.</div>';
    bottom();
    exit;
}

$jml_week = max(1, (int) ($kelas['jml_week'] ?? 1));
$add_m    = isset($_GET['add_m']) ? (int) $_GET['add_m'] : 0;
$add_t    = isset($_GET['add_t']) ? (int) $_GET['add_t'] : 0;
$edit_t   = isset($_GET['edit_t']) ? (int) $_GET['edit_t'] : 0;
$ext_t    = isset($_GET['ext_t']) ? (int) $_GET['ext_t'] : 0;

$materi   = q("SELECT * FROM lms_dosen.materi WHERE kelas_id=? AND dosen_id=? ORDER BY id ASC", [$id_kelas, $id_dosen])->fetchAll();
$tugas    = q("SELECT * FROM lms_dosen.tugas WHERE kelas_id=? AND dosen_id=? ORDER BY id ASC", [$id_kelas, $id_dosen])->fetchAll();
$ext_rows = q("SELECT p.*, m.npm, m.nama
               FROM lms_dosen.perpanjangan p
               JOIN lms_dosen.tugas t ON t.id = p.tugas_id
               JOIN lms_mahasiswa.mahasiswa m ON m.id = p.mhs_id
               WHERE t.kelas_id = ? AND t.dosen_id = ?
               ORDER BY p.id DESC", [$id_kelas, $id_dosen])->fetchAll();

$ext_by_tugas = [];
foreach ($ext_rows as $xr) {
    $ext_by_tugas[(int) $xr['tugas_id']][] = $xr;
}

$n_m = count($materi);
$n_t = count($tugas);
?>

<div class="box">
    <b><?php echo e($kelas['nama']); ?></b> <small>(<?php echo e($kelas['kode']); ?> &mdash; <?php echo e($kelas['tahun_ajaran']); ?>)</small>
    <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="display:inline;margin-left:10px">
        <?php echo csrf(); ?>
        <input type="hidden" name="a" value="addweek" />
        <button type="submit">+ Tambah Week</button>
    </form>
    <?php if ($jml_week > 1) { ?>
        <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="display:inline;margin-left:6px">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="delweek" />
            <button class="del" type="submit">Hapus Week <?php echo $jml_week; ?></button>
        </form>
    <?php } ?>
</div>

<?php
for ($w = 1; $w <= $jml_week; $w++) {
    $ada_isi = false;
    ?>
    <div class="box">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <b>Week <?php echo $w; ?></b>
            <div>
                <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>&amp;add_m=<?php echo $w; ?>">+ Materi</a>
                <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>&amp;add_t=<?php echo $w; ?>">+ Tugas</a>
            </div>
        </div>

        <?php if ($add_m === $w) { ?>
            <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" enctype="multipart/form-data" class="card" style="margin-top:10px">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="addmateri" />
                <input type="hidden" name="week" value="<?php echo $w; ?>" />
                <b>Tambah Materi (Week <?php echo $w; ?>)</b>
                <label>Judul <input type="text" name="judul" required="required" /></label>
                <label>Isi / Tautan <textarea name="isi" rows="3" cols="50"></textarea></label>
                <label>File (PDF, PPT, DOC, ZIP) <input type="file" name="berkas" accept=".pdf,.ppt,.pptx,.doc,.docx,.zip" /></label>
                <button type="submit">Simpan</button>
                <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>">Batal</a>
            </form>
        <?php } ?>

        <?php if ($add_t === $w) { ?>
            <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" class="card" style="margin-top:10px">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="addtugas" />
                <input type="hidden" name="week" value="<?php echo $w; ?>" />
                <b>Tambah Tugas (Week <?php echo $w; ?>)</b>
                <label>Judul <input type="text" name="judul" required="required" /></label>
                <label>Deskripsi <textarea name="desc" rows="3" cols="50"></textarea></label>
                <label>Deadline <input type="datetime-local" name="dl" required="required" /></label>
                <button type="submit">Simpan</button>
                <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>">Batal</a>
            </form>
        <?php } ?>

        <?php
        for ($i = 0; $i < $n_m; $i++) {
            $m = $materi[$i];
            if ((int) $m['week'] === $w) {
                $ada_isi = true;
                $f_cek = glob(dirname(__DIR__) . '/uploads/materi/m_' . (int) $m['id'] . '.*');
                $f_nama = ($f_cek && isset($f_cek[0])) ? basename($f_cek[0]) : '';
                ?>
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid #eee">
                    <span class="tag">Materi</span> <b><?php echo e($m['judul']); ?></b>
                    <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="display:inline;margin-left:6px">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="delmateri" />
                        <input type="hidden" name="i" value="<?php echo (int) $m['id']; ?>" />
                        <button class="del" type="submit">Hapus</button>
                    </form>
                    <?php if ($m['isi'] !== '') { ?>
                        <p><?php echo nl2br(e($m['isi'])); ?></p>
                    <?php } ?>
                    <?php if ($f_nama !== '') { ?>
                        <p><a class="btn" href="<?php echo BASE . '/uploads/materi/' . e($f_nama); ?>" target="_blank">Unduh (<?php echo e($f_nama); ?>)</a></p>
                    <?php } ?>
                </div>
                <?php
            }
        }

        for ($i = 0; $i < $n_t; $i++) {
            $t = $tugas[$i];
            if ((int) $t['week'] === $w) {
                $ada_isi = true;
                $tid = (int) $t['id'];
                $daftar_ext = $ext_by_tugas[$tid] ?? [];
                ?>
                <div style="margin-top:10px;padding-top:8px;border-top:1px solid #eee">
                    <span class="tag late">Tugas</span> <b><?php echo e($t['judul']); ?></b>
                    <small>(Deadline: <?php echo e($t['deadline']); ?>)</small>
                    <a class="btn" href="nilai.php?t=<?php echo $tid; ?>">Nilai</a>
                    <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>&amp;edit_t=<?php echo $tid; ?>">Edit</a>
                    <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>&amp;ext_t=<?php echo $tid; ?>">Perpanjang</a>
                    <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="deltugas" />
                        <input type="hidden" name="i" value="<?php echo $tid; ?>" />
                        <button class="del" type="submit">Hapus</button>
                    </form>
                    <p><?php echo nl2br(e($t['deskripsi'])); ?></p>

                    <?php foreach ($daftar_ext as $x) { ?>
                        <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" style="margin-top:4px">
                            <?php echo csrf(); ?>
                            <input type="hidden" name="a" value="delext" />
                            <input type="hidden" name="i" value="<?php echo (int) $x['id']; ?>" />
                            <span class="tag ext"><?php echo e($x['npm'] . ' ' . $x['nama'] . ' -> ' . $x['deadline_baru']); ?></span>
                            <?php if (!empty($x['alasan'])) { ?>
                                <small>(<?php echo e($x['alasan']); ?>)</small>
                            <?php } ?>
                            <button class="del" type="submit" style="padding:0 8px">Cabut</button>
                        </form>
                    <?php } ?>

                    <?php if ($edit_t === $tid) { ?>
                        <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" class="card" style="margin-top:10px">
                            <?php echo csrf(); ?>
                            <input type="hidden" name="a" value="updtugas" />
                            <input type="hidden" name="i" value="<?php echo $tid; ?>" />
                            <b>Edit Tugas</b>
                            <label>Judul <input type="text" name="judul" value="<?php echo e($t['judul']); ?>" required="required" /></label>
                            <label>Deskripsi <textarea name="desc" rows="3" cols="50"><?php echo e($t['deskripsi']); ?></textarea></label>
                            <label>Deadline <input type="datetime-local" name="dl" value="<?php echo date('Y-m-d\TH:i', strtotime($t['deadline'])); ?>" required="required" /></label>
                            <button type="submit">Simpan Perubahan</button>
                            <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>">Batal</a>
                        </form>
                    <?php } ?>

                    <?php if ($ext_t === $tid) { ?>
                        <form method="post" action="kelas.php?id=<?php echo $id_kelas; ?>" class="card" style="margin-top:10px">
                            <?php echo csrf(); ?>
                            <input type="hidden" name="a" value="ext" />
                            <input type="hidden" name="i" value="<?php echo $tid; ?>" />
                            <b>Beri Waktu Tambahan: <?php echo e($t['judul']); ?></b>
                            <label>NPM atau Email Mahasiswa <input type="text" name="who" placeholder="NPM atau email mahasiswa" required="required" /></label>
                            <label>Deadline Baru <input type="datetime-local" name="dl" required="required" /></label>
                            <label>Alasan <input type="text" name="alasan" placeholder="Alasan (sakit, dll)" /></label>
                            <button type="submit">Simpan Perpanjangan</button>
                            <a class="btn" href="kelas.php?id=<?php echo $id_kelas; ?>">Batal</a>
                        </form>
                    <?php } ?>
                </div>
                <?php
            }
        }

        if (!$ada_isi && $add_m !== $w && $add_t !== $w) {
            echo '<p class="mut" style="margin-top:8px">Belum ada materi atau tugas di week ini.</p>';
        }
        ?>
    </div>
    <?php
}

bottom();
?>