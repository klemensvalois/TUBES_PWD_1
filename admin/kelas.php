<?php
require '../config.php';
need('admin');
require '../includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $post = $_POST;
    $aksi = $post['a'] ?? '';

    try {
        if ($aksi === 'add') {
            q("INSERT INTO lms_admin.kelas(kode, nama, dosen_id, tahun_ajaran) VALUES(?, ?, ?, ?)",
              [trim($post['kode']), trim($post['nama']), (int) $post['dosen'], trim($post['ta'])]);
        } elseif ($aksi === 'addfak') {
            q("INSERT INTO lms_admin.fakultas(nama) VALUES(?)", [trim($post['nama'])]);
        } elseif ($aksi === 'addprodi') {
            q("INSERT INTO lms_admin.prodi(fakultas_id, nama) VALUES(?, ?)", [(int) $post['fak'], trim($post['nama'])]);
        } elseif ($aksi === 'del') {
            q("DELETE FROM lms_admin.kelas WHERE id=?", [(int) $post['i']]);
        } elseif ($aksi === 'enroll') {
            $dicari   = trim($post['who']);
            $kelas_id = (int) $post['i'];

            $mhs   = q("SELECT id, prodi_id FROM lms_mahasiswa.mahasiswa WHERE npm=? OR email=?", [$dicari, $dicari])->fetch();
            $kelas = q("SELECT d.prodi_id FROM lms_admin.kelas k JOIN lms_dosen.dosen d ON d.id = k.dosen_id WHERE k.id=?", [$kelas_id])->fetch();

            if (!$mhs) {
                throw new Exception('Mahasiswa tidak ditemukan.');
            }
            if ($kelas && (int) $mhs['prodi_id'] !== (int) $kelas['prodi_id']) {
                throw new Exception('Prodi mahasiswa tidak sesuai dengan prodi dosen.');
            }

            q("INSERT IGNORE INTO lms_admin.kelas_mhs VALUES(?, ?)", [$kelas_id, $mhs['id']]);
        } elseif ($aksi === 'unenroll') {
            q("DELETE FROM lms_admin.kelas_mhs WHERE kelas_id=? AND mhs_id=?", [(int) $post['i'], (int) $post['m']]);
        }
        flash('Berhasil.');
    } catch (Exception $ex) {
        flash($ex instanceof PDOException ? 'Kode kelas sudah ada di tahun ajaran ini.' : $ex->getMessage());
    }

    go('admin/kelas.php');
}

$daftar_dosen = q("SELECT id, nama FROM lms_dosen.dosen ORDER BY nama")->fetchAll();
top('Settings: Fakultas, Prodi & Kelas', 'admin/kelas.php', false);
$daftar_fak = q('SELECT * FROM lms_admin.fakultas')->fetchAll();

echo '<h1>Fakultas &amp; Prodi</h1><div class="box">';
echo '<form method="post" action="kelas.php" class="row2">' . csrf() . '<input type="hidden" name="a" value="addfak" /><input type="text" name="nama" placeholder="Nama fakultas baru" required="required" /><button type="submit">Tambah fakultas</button></form><br />';
echo '<form method="post" action="kelas.php" class="row2">' . csrf() . '<input type="hidden" name="a" value="addprodi" /><select name="fak">';
foreach ($daftar_fak as $f) {
    echo '<option value="' . $f['id'] . '">' . e($f['nama']) . '</option>';
}
echo '</select><input type="text" name="nama" placeholder="Nama prodi baru" required="required" /><button type="submit">Tambah prodi</button></form>';
echo '</div><h1>Kelas</h1>';
?>
<form method="post" action="kelas.php" class="card">
    <?php echo csrf(); ?>
    <input type="hidden" name="a" value="add" />
    <b>Buat kelas</b>
    <label>Kode <input type="text" name="kode" required="required" /></label>
    <label>Nama mata kuliah <input type="text" name="nama" required="required" /></label>
    <label>Dosen
        <select name="dosen">
            <?php foreach ($daftar_dosen as $d) { ?>
                <option value="<?php echo $d['id']; ?>"><?php echo e($d['nama']); ?></option>
            <?php } ?>
        </select>
    </label>
    <label>Tahun ajaran <input type="text" name="ta" value="Gasal TA 2026-2027" /></label>
    <button type="submit">Buat</button>
</form>

<?php
$daftar_kelas = q("SELECT k.*, d.nama AS dosen
                   FROM lms_admin.kelas k
                   LEFT JOIN lms_dosen.dosen d ON d.id = k.dosen_id
                   ORDER BY k.id DESC")->fetchAll();

foreach ($daftar_kelas as $k) {
    $peserta = q("SELECT m.id, m.npm, m.nama
                  FROM lms_admin.kelas_mhs km
                  JOIN lms_mahasiswa.mahasiswa m ON m.id = km.mhs_id
                  WHERE km.kelas_id=?", [$k['id']])->fetchAll();
    ?>
    <div class="box">
        <b><?php echo e($k['nama']); ?></b> (<?php echo e($k['kode']); ?>, <?php echo e($k['tahun_ajaran']); ?>) &mdash; <?php echo e($k['dosen']); ?>
        <form method="post" action="kelas.php" style="display:inline">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="del" />
            <input type="hidden" name="i" value="<?php echo $k['id']; ?>" />
            <button class="del" type="submit">Hapus kelas</button>
        </form>
        <form method="post" action="kelas.php">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="enroll" />
            <input type="hidden" name="i" value="<?php echo $k['id']; ?>" />
            <input type="text" name="who" placeholder="NPM / email mahasiswa" style="width:240px" />
            <button type="submit">Daftarkan</button>
        </form>
        <?php foreach ($peserta as $m) { ?>
            <form method="post" action="kelas.php" style="display:inline">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="unenroll" />
                <input type="hidden" name="i" value="<?php echo $k['id']; ?>" />
                <input type="hidden" name="m" value="<?php echo $m['id']; ?>" />
                <span class="tag"><?php echo e($m['npm'] . ' ' . $m['nama']); ?> <button class="del" type="submit" style="padding:0 6px">x</button></span>
            </form>
        <?php } ?>
    </div>
    <?php
}

bottom();
?>