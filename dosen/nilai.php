<?php
require '../config.php';
need('dosen');
require '../includes/layout.php';

$id_dosen = (int) $_SESSION['u']['id'];

// Simpan nilai & catatan mahasiswa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $tid     = (int) ($_POST['t'] ?? 0);
    $mid     = (int) ($_POST['m'] ?? 0);
    $k_back  = (int) ($_POST['k'] ?? 0);
    $w_back  = (int) ($_POST['w'] ?? 0);
    $nilai   = trim($_POST['nilai'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    $cek_t = q("SELECT id, kelas_id, week FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [$tid, $id_dosen])->fetch();
    if (!$cek_t || $mid <= 0) {
        flash('Data tugas atau mahasiswa tidak valid.');
        go('dosen/nilai.php');
    }

    if ($nilai === '' || !is_numeric($nilai) || (float) $nilai < 0 || (float) $nilai > 100) {
        flash('Nilai harus berupa angka antara 0 sampai 100.');
    } else {
        q("INSERT INTO lms_dosen.nilai(tugas_id, mhs_id, nilai, catatan)
           VALUES(?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE nilai=VALUES(nilai), catatan=VALUES(catatan)",
          [$tid, $mid, (float) $nilai, $catatan]);
        flash('Nilai berhasil disimpan.');
    }

    $k_go = $k_back > 0 ? $k_back : (int) $cek_t['kelas_id'];
    $w_go = $w_back > 0 ? $w_back : (int) $cek_t['week'];
    go('dosen/nilai.php?k=' . $k_go . '&w=' . $w_go . '&t=' . $tid);
}

// 1. Ambil semua kelas yang diampu dosen
$daftar_kelas = q("SELECT id, kode, nama, tahun_ajaran, jml_week
                   FROM lms_admin.kelas
                   WHERE dosen_id=?
                   ORDER BY id DESC", [$id_dosen])->fetchAll();

$id_kelas = isset($_GET['k']) ? (int) $_GET['k'] : 0;
$week     = isset($_GET['w']) ? (int) $_GET['w'] : 0;
$id_tugas = isset($_GET['t']) ? (int) $_GET['t'] : 0;

// Jika dibuka langsung dari tombol "Nilai" di dosen/kelas.php (?t=ID tanpa ?k=)
if ($id_tugas > 0 && $id_kelas === 0) {
    $t_init = q("SELECT kelas_id, week FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [$id_tugas, $id_dosen])->fetch();
    if ($t_init) {
        $id_kelas = (int) $t_init['kelas_id'];
        $week     = (int) $t_init['week'];
    }
}

// Pastikan kelas yang dipilih milik dosen; jika belum pilih, pakai kelas pertama
$kelas_aktif = null;
$n_kelas     = count($daftar_kelas);
for ($i = 0; $i < $n_kelas; $i++) {
    if ((int) $daftar_kelas[$i]['id'] === $id_kelas) {
        $kelas_aktif = $daftar_kelas[$i];
        break;
    }
}
if (!$kelas_aktif && $n_kelas > 0) {
    $kelas_aktif = $daftar_kelas[0];
    $id_kelas    = (int) $kelas_aktif['id'];
}

// 2. Hitung jumlah tugas per week di kelas aktif
$jml_week        = $kelas_aktif ? max(1, (int) ($kelas_aktif['jml_week'] ?? 1)) : 1;
$tugas_per_week  = [];
if ($kelas_aktif) {
    $rows_w = q("SELECT week, COUNT(*) AS jml
                 FROM lms_dosen.tugas
                 WHERE kelas_id=? AND dosen_id=?
                 GROUP BY week", [$id_kelas, $id_dosen])->fetchAll();
    for ($i = 0; $i < count($rows_w); $i++) {
        $tugas_per_week[(int) $rows_w[$i]['week']] = (int) $rows_w[$i]['jml'];
    }
}

// Jika week belum dipilih (0), default ke week pertama yang punya tugas (atau Week 1)
if ($week <= 0 || $week > $jml_week) {
    $week = 1;
    if (!empty($tugas_per_week)) {
        $weeks_ada = array_keys($tugas_per_week);
        sort($weeks_ada);
        $week = (int) $weeks_ada[0];
    }
}

// 3. Ambil daftar tugas di kelas & week yang dipilih
$daftar_tugas = [];
if ($kelas_aktif) {
    $daftar_tugas = q("SELECT id, judul, deadline
                       FROM lms_dosen.tugas
                       WHERE kelas_id=? AND dosen_id=? AND week=?
                       ORDER BY id ASC", [$id_kelas, $id_dosen, $week])->fetchAll();
}

// Pastikan tugas aktif ada di daftar tugas kelas & week ini
$tugas_aktif = null;
$n_tugas     = count($daftar_tugas);
for ($i = 0; $i < $n_tugas; $i++) {
    if ((int) $daftar_tugas[$i]['id'] === $id_tugas) {
        $tugas_aktif = $daftar_tugas[$i];
        break;
    }
}
if (!$tugas_aktif && $n_tugas > 0) {
    $tugas_aktif = $daftar_tugas[0];
    $id_tugas    = (int) $tugas_aktif['id'];
} elseif (!$tugas_aktif) {
    $id_tugas = 0;
}

// 4. Ambil daftar mahasiswa + status pengumpulan + nilai untuk tugas aktif
$daftar_mhs = [];
if ($kelas_aktif && $tugas_aktif) {
    $daftar_mhs = q("SELECT m.id, m.npm, m.nama,
                            p.file AS file_kumpul, p.waktu AS waktu_kumpul, p.catatan AS catatan_mhs,
                            n.nilai, n.catatan AS catatan_dosen
                     FROM lms_admin.kelas_mhs km
                     JOIN lms_mahasiswa.mahasiswa m ON m.id = km.mhs_id
                     LEFT JOIN lms_mahasiswa.pengumpulan p ON p.tugas_id = ? AND p.mhs_id = m.id
                     LEFT JOIN lms_dosen.nilai n ON n.tugas_id = ? AND n.mhs_id = m.id
                     WHERE km.kelas_id = ?
                     ORDER BY m.npm ASC", [$id_tugas, $id_tugas, $id_kelas])->fetchAll();
}

top('Penilaian', 'dosen/nilai.php');

if (!$kelas_aktif) {
    echo '<div class="box">Belum ada kelas yang diampu.</div>';
    bottom();
    exit;
}
?>

<div class="box">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <!-- Pilih Kelas -->
        <form method="get" action="nilai.php" style="flex:2;min-width:220px;">
            <label style="display:block;font-weight:600;margin-bottom:4px;">Pilih Kelas
                <select name="k" onchange="this.form.submit()">
                    <?php
                    for ($i = 0; $i < $n_kelas; $i++) {
                        $k   = $daftar_kelas[$i];
                        $sel = ((int) $k['id'] === $id_kelas) ? ' selected="selected"' : '';
                        echo '<option value="' . (int) $k['id'] . '"' . $sel . '>'
                           . e($k['nama'] . ' (' . $k['kode'] . ' - ' . $k['tahun_ajaran'] . ')')
                           . '</option>';
                    }
                    ?>
                </select>
            </label>
        </form>

        <!-- Pilih Week -->
        <form method="get" action="nilai.php" style="flex:1;min-width:150px;">
            <input type="hidden" name="k" value="<?php echo $id_kelas; ?>" />
            <label style="display:block;font-weight:600;margin-bottom:4px;">Pilih Week
                <select name="w" onchange="this.form.submit()">
                    <?php
                    for ($w_i = 1; $w_i <= $jml_week; $w_i++) {
                        $jml_t = $tugas_per_week[$w_i] ?? 0;
                        $sel   = ($w_i === $week) ? ' selected="selected"' : '';
                        $info  = ($jml_t > 0) ? (' (' . $jml_t . ' tugas)') : '';
                        echo '<option value="' . $w_i . '"' . $sel . '>Week ' . $w_i . $info . '</option>';
                    }
                    ?>
                </select>
            </label>
        </form>

        <!-- Pilih Tugas di Week tersebut -->
        <form method="get" action="nilai.php" style="flex:2;min-width:220px;">
            <input type="hidden" name="k" value="<?php echo $id_kelas; ?>" />
            <input type="hidden" name="w" value="<?php echo $week; ?>" />
            <label style="display:block;font-weight:600;margin-bottom:4px;">Pilih Tugas (Week <?php echo $week; ?>)
                <select name="t" onchange="this.form.submit()">
                    <?php if ($n_tugas === 0) { ?>
                        <option value="0">-- Tidak ada tugas di Week <?php echo $week; ?> --</option>
                    <?php } else {
                        for ($i = 0; $i < $n_tugas; $i++) {
                            $t   = $daftar_tugas[$i];
                            $sel = ((int) $t['id'] === $id_tugas) ? ' selected="selected"' : '';
                            echo '<option value="' . (int) $t['id'] . '"' . $sel . '>' . e($t['judul']) . '</option>';
                        }
                    } ?>
                </select>
            </label>
        </form>
    </div>
</div>

<?php if (!$tugas_aktif) { ?>
    <div class="box">
        <p class="mut">Belum ada tugas pada <b><?php echo e($kelas_aktif['nama']); ?></b> &mdash; <b>Week <?php echo $week; ?></b>.</p>
    </div>
<?php } else { ?>
    <div class="box">
        <div style="margin-bottom:10px;">
            <b><?php echo e($tugas_aktif['judul']); ?></b>
            <small class="mut">&mdash; <?php echo e($kelas_aktif['nama']); ?> &middot; Week <?php echo $week; ?> &middot; Deadline: <?php echo e($tugas_aktif['deadline']); ?></small>
        </div>

        <?php if (empty($daftar_mhs)) { ?>
            <p class="mut">Belum ada mahasiswa yang terdaftar di kelas ini.</p>
        <?php } else { ?>
            <table class="tbl">
                <tr>
                    <th>NPM</th>
                    <th>Nama</th>
                    <th>Pengumpulan</th>
                    <th>Nilai</th>
                </tr>
                <?php
                $n_mhs = count($daftar_mhs);
                for ($i = 0; $i < $n_mhs; $i++) {
                    $m = $daftar_mhs[$i];
                    ?>
                    <tr>
                        <td><?php echo e($m['npm']); ?></td>
                        <td><?php echo e($m['nama']); ?></td>
                        <td>
                            <?php if (!empty($m['file_kumpul'])) { ?>
                                <a class="btn" href="<?php echo BASE . '/uploads/' . e($m['file_kumpul']); ?>" target="_blank">Unduh</a>
                                <small class="mut"><?php echo e($m['waktu_kumpul']); ?></small>
                            <?php } else { ?>
                                <span class="tag late">Belum</span>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" action="nilai.php" style="display:flex;gap:6px;align-items:center;">
                                <?php echo csrf(); ?>
                                <input type="hidden" name="k" value="<?php echo $id_kelas; ?>" />
                                <input type="hidden" name="w" value="<?php echo $week; ?>" />
                                <input type="hidden" name="t" value="<?php echo $id_tugas; ?>" />
                                <input type="hidden" name="m" value="<?php echo (int) $m['id']; ?>" />
                                <input type="number" name="nilai" min="0" max="100" step="0.01" value="<?php echo e($m['nilai'] ?? ''); ?>" style="width:80px;" placeholder="0-100" />
                                <input type="text" name="catatan" value="<?php echo e($m['catatan_dosen'] ?? ''); ?>" placeholder="Catatan" style="width:180px;" />
                                <button type="submit">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
<?php } ?>

<?php
bottom();
?>