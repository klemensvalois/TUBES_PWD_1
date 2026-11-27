<?php
require '../config.php';
need('dosen');
require '../includes/layout.php';

$id_dosen = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $aksi = $_POST['a'] ?? '';

    try {
        if ($aksi === 'add' || $aksi === 'upd') {
            $judul = trim($_POST['judul'] ?? '');
            $desc  = trim($_POST['desc'] ?? '');
            $ts_dl = strtotime($_POST['dl'] ?? '');

            if ($judul === '' || !$ts_dl) {
                throw new Exception('Judul dan deadline wajib diisi dengan benar.');
            }
            $dl_sql = date('Y-m-d H:i:s', $ts_dl);

            if ($aksi === 'add') {
                $id_kelas = (int) ($_POST['kelas'] ?? 0);
                $milik    = q("SELECT 1 FROM lms_admin.kelas WHERE id=? AND dosen_id=?", [$id_kelas, $id_dosen])->fetch();
                if (!$milik) {
                    throw new Exception('Bukan kelas Anda.');
                }
                q("INSERT INTO lms_dosen.tugas(kelas_id, dosen_id, judul, deskripsi, deadline) VALUES(?, ?, ?, ?, ?)",
                  [$id_kelas, $id_dosen, $judul, $desc, $dl_sql]);
            } else {
                q("UPDATE lms_dosen.tugas SET judul=?, deskripsi=?, deadline=? WHERE id=? AND dosen_id=?",
                  [$judul, $desc, $dl_sql, (int) ($_POST['i'] ?? 0), $id_dosen]);
            }
        } elseif ($aksi === 'del') {
            q("DELETE FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [(int) ($_POST['i'] ?? 0), $id_dosen]);
        } elseif ($aksi === 'ext') {
            $id_tugas = (int) ($_POST['i'] ?? 0);
            $ts_dl    = strtotime($_POST['dl'] ?? '');
            $dicari   = trim($_POST['who'] ?? '');
            $alasan   = trim($_POST['alasan'] ?? '');

            $tugas = q("SELECT id FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [$id_tugas, $id_dosen])->fetch();
            if (!$tugas || !$ts_dl || $dicari === '') {
                throw new Exception('Data perpanjangan tidak valid.');
            }

            $mhs = q("SELECT m.id
                      FROM lms_mahasiswa.mahasiswa m
                      JOIN lms_admin.kelas_mhs km ON km.mhs_id = m.id
                      JOIN lms_dosen.tugas t ON t.kelas_id = km.kelas_id AND t.id=?
                      WHERE m.npm=? OR m.email=?", [$id_tugas, $dicari, $dicari])->fetch();
            if (!$mhs) {
                throw new Exception('Mahasiswa tidak ditemukan atau tidak terdaftar di kelas tugas ini.');
            }

            q("INSERT INTO lms_dosen.perpanjangan(tugas_id, mhs_id, deadline_baru, alasan) VALUES(?, ?, ?, ?)",
              [$id_tugas, $mhs['id'], date('Y-m-d H:i:s', $ts_dl), $alasan]);
        } elseif ($aksi === 'delext') {
            q("DELETE p FROM lms_dosen.perpanjangan p
               JOIN lms_dosen.tugas t ON t.id = p.tugas_id
               WHERE p.id=? AND t.dosen_id=?", [(int) ($_POST['i'] ?? 0), $id_dosen]);
        }

        flash('Berhasil disimpan.');
    } catch (Exception $ex) {
        flash($ex->getMessage());
    }

    go('dosen/tugas.php');
}

$daftar_kelas = q("SELECT * FROM lms_admin.kelas WHERE dosen_id=?", [$id_dosen])->fetchAll();
$data_edit    = isset($_GET['edit']) ? q("SELECT * FROM lms_dosen.tugas WHERE id=? AND dosen_id=?", [(int) $_GET['edit'], $id_dosen])->fetch() : null;
$daftar_tugas = q("SELECT t.*, k.nama AS kelas
                   FROM lms_dosen.tugas t
                   JOIN lms_admin.kelas k ON k.id = t.kelas_id
                   WHERE t.dosen_id=?
                   ORDER BY t.deadline DESC", [$id_dosen])->fetchAll();

$val_id    = $data_edit['id'] ?? '';
$val_judul = $data_edit['judul'] ?? '';
$val_desc  = $data_edit['deskripsi'] ?? '';
$val_dl    = isset($data_edit['deadline']) ? date('Y-m-d\TH:i', strtotime($data_edit['deadline'])) : '';

top('Tugas & Deadline', 'dosen/tugas.php');
?>
<form method="post" action="tugas.php" class="card">
    <?php echo csrf(); ?>
    <input type="hidden" name="a" value="<?php echo $data_edit ? 'upd' : 'add'; ?>" />
    <input type="hidden" name="i" value="<?php echo $val_id; ?>" />
    <b><?php echo $data_edit ? 'Edit' : 'Buat'; ?> tugas</b>
    <?php if (!$data_edit) { ?>
        <label>Kelas
            <select name="kelas">
                <?php foreach ($daftar_kelas as $k) { ?>
                    <option value="<?php echo $k['id']; ?>"><?php echo e($k['nama']); ?></option>
                <?php } ?>
            </select>
        </label>
    <?php } ?>
    <label>Judul <input type="text" name="judul" value="<?php echo e($val_judul); ?>" required="required" /></label>
    <label>Deskripsi <textarea name="desc" rows="3" cols="50"><?php echo e($val_desc); ?></textarea></label>
    <label>Deadline umum <input type="datetime-local" name="dl" value="<?php echo $val_dl; ?>" required="required" /></label>
    <button type="submit">Simpan</button>
</form>

<?php foreach ($daftar_tugas as $t) { ?>
    <div class="box">
        <b><?php echo e($t['judul']); ?></b> <small>(<?php echo e($t['kelas']); ?>)</small> &mdash; deadline umum <?php echo e($t['deadline']); ?>
        <a class="btn" href="tugas.php?edit=<?php echo $t['id']; ?>">Edit</a>
        <form method="post" action="tugas.php" style="display:inline">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="del" />
            <input type="hidden" name="i" value="<?php echo $t['id']; ?>" />
            <button class="del" type="submit">Hapus</button>
        </form>
        <form method="post" action="tugas.php" style="margin-top:10px">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="ext" />
            <input type="hidden" name="i" value="<?php echo $t['id']; ?>" />
            <input type="text" name="who" placeholder="NPM atau email mahasiswa" style="width:210px" />
            <input type="datetime-local" name="dl" style="width:200px" />
            <input type="text" name="alasan" placeholder="Alasan (sakit, dll)" style="width:200px" />
            <button type="submit">Beri waktu tambahan</button>
        </form>
        <?php
        $list_ext = q("SELECT p.*, m.npm, m.nama
                       FROM lms_dosen.perpanjangan p
                       JOIN lms_mahasiswa.mahasiswa m ON m.id = p.mhs_id
                       WHERE p.tugas_id=?", [$t['id']])->fetchAll();
        foreach ($list_ext as $x) {
            ?>
            <form method="post" action="tugas.php">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="delext" />
                <input type="hidden" name="i" value="<?php echo $x['id']; ?>" />
                <span class="tag ext"><?php echo e($x['npm'] . ' ' . $x['nama'] . ' &rarr; ' . $x['deadline_baru']); ?></span>
                <button class="del" type="submit" style="padding:0 8px">Cabut</button>
            </form>
        <?php } ?>
    </div>
<?php } ?>

<?php
bottom();
?>