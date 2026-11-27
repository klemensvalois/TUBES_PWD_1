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
        } elseif ($aksi === 'updfak') {
            q("UPDATE lms_admin.fakultas SET nama=? WHERE id=?", [trim($post['nama']), (int) $post['i']]);
        } elseif ($aksi === 'delfak') {
            q("DELETE FROM lms_admin.fakultas WHERE id=?", [(int) $post['i']]);
        } elseif ($aksi === 'addprodi') {
            q("INSERT INTO lms_admin.prodi(fakultas_id, nama) VALUES(?, ?)", [(int) $post['fak'], trim($post['nama'])]);
        } elseif ($aksi === 'updprodi') {
            q("UPDATE lms_admin.prodi SET fakultas_id=?, nama=? WHERE id=?",
              [(int) $post['fak'], trim($post['nama']), (int) $post['i']]);
        } elseif ($aksi === 'delprodi') {
            q("DELETE FROM lms_admin.prodi WHERE id=?", [(int) $post['i']]);
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

$daftar_fak   = q("SELECT * FROM lms_admin.fakultas ORDER BY id ASC")->fetchAll();
$daftar_prodi = q("SELECT p.*, f.nama AS nama_fak
                   FROM lms_admin.prodi p
                   LEFT JOIN lms_admin.fakultas f ON f.id = p.fakultas_id
                   ORDER BY p.id ASC")->fetchAll();

$n_fak   = count($daftar_fak);
$n_prodi = count($daftar_prodi);
$n_dosen = count($daftar_dosen);
?>
<h1>Fakultas &amp; Prodi</h1>
<div class="box">
    <form method="post" action="kelas.php" class="row2">
        <?php echo csrf(); ?>
        <input type="hidden" name="a" value="addfak" />
        <input type="text" name="nama" placeholder="Nama fakultas baru" required="required" />
        <button type="submit">Tambah fakultas</button>
    </form>
    <br />
    <form method="post" action="kelas.php" class="row2">
        <?php echo csrf(); ?>
        <input type="hidden" name="a" value="addprodi" />
        <select name="fak">
            <?php
            for ($i = 0; $i < $n_fak; $i++) {
                $f = $daftar_fak[$i];
                echo '<option value="' . (int) $f['id'] . '">' . e($f['nama']) . '</option>';
            }
            ?>
        </select>
        <input type="text" name="nama" placeholder="Nama prodi baru" required="required" />
        <button type="submit">Tambah prodi</button>
    </form>
    <br />
    <b>Daftar Fakultas</b>
    <table class="tbl">
        <tr><th>Nama Fakultas</th><th>Aksi</th></tr>
        <?php
        for ($i = 0; $i < $n_fak; $i++) {
            $f = $daftar_fak[$i];
            ?>
            <tr>
                <td>
                    <form method="post" action="kelas.php" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="updfak" />
                        <input type="hidden" name="i" value="<?php echo (int) $f['id']; ?>" />
                        <input type="text" name="nama" value="<?php echo e($f['nama']); ?>" required="required" />
                        <button type="submit">Simpan</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="kelas.php" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="delfak" />
                        <input type="hidden" name="i" value="<?php echo (int) $f['id']; ?>" />
                        <button class="del" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
    <br />
    <b>Daftar Prodi</b>
    <table class="tbl">
        <tr><th>Fakultas</th><th>Nama Prodi</th><th>Aksi</th></tr>
        <?php
        for ($i = 0; $i < $n_prodi; $i++) {
            $p = $daftar_prodi[$i];
            ?>
            <tr>
                <td><?php echo e($p['nama_fak'] ?? ''); ?></td>
                <td>
                    <form method="post" action="kelas.php" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="updprodi" />
                        <input type="hidden" name="i" value="<?php echo (int) $p['id']; ?>" />
                        <select name="fak">
                            <?php
                            for ($j = 0; $j < $n_fak; $j++) {
                                $f   = $daftar_fak[$j];
                                $sel = ((int) $f['id'] === (int) $p['fakultas_id']) ? ' selected="selected"' : '';
                                echo '<option value="' . (int) $f['id'] . '"' . $sel . '>' . e($f['nama']) . '</option>';
                            }
                            ?>
                        </select>
                        <input type="text" name="nama" value="<?php echo e($p['nama']); ?>" required="required" />
                        <button type="submit">Simpan</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="kelas.php" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="delprodi" />
                        <input type="hidden" name="i" value="<?php echo (int) $p['id']; ?>" />
                        <button class="del" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

<h1>Kelas</h1>
<form method="post" action="kelas.php" class="card">
    <?php echo csrf(); ?>
    <input type="hidden" name="a" value="add" />
    <b>Buat kelas</b>
    <label>Kode <input type="text" name="kode" required="required" /></label>
    <label>Nama mata kuliah <input type="text" name="nama" required="required" /></label>
    <label>Dosen
        <select name="dosen">
            <?php
            for ($i = 0; $i < $n_dosen; $i++) {
                $d = $daftar_dosen[$i];
                echo '<option value="' . (int) $d['id'] . '">' . e($d['nama']) . '</option>';
            }
            ?>
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
$n_kelas = count($daftar_kelas);

for ($i = 0; $i < $n_kelas; $i++) {
    $k       = $daftar_kelas[$i];
    $peserta = q("SELECT m.id, m.npm, m.nama
                  FROM lms_admin.kelas_mhs km
                  JOIN lms_mahasiswa.mahasiswa m ON m.id = km.mhs_id
                  WHERE km.kelas_id=?", [$k['id']])->fetchAll();
    $n_peserta = count($peserta);
    ?>
    <div class="box">
        <b><?php echo e($k['nama']); ?></b> (<?php echo e($k['kode']); ?>, <?php echo e($k['tahun_ajaran']); ?>) &mdash; <?php echo e($k['dosen']); ?>
        <form method="post" action="kelas.php" style="display:inline">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="del" />
            <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
            <button class="del" type="submit">Hapus kelas</button>
        </form>
        <form method="post" action="kelas.php" style="margin-top:8px">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="enroll" />
            <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
            <input type="text" name="who" placeholder="NPM / email mahasiswa" style="width:240px" />
            <button type="submit">Daftarkan</button>
        </form>
        <?php if ($n_peserta > 0) { ?>
            <table class="tbl" style="margin-top:10px">
                <tr>
                    <th>NPM</th>
                    <th>Nama</th>
                    <th>Aksi</th>
                </tr>
                <?php
                for ($j = 0; $j < $n_peserta; $j++) {
                    $m = $peserta[$j];
                    ?>
                    <tr>
                        <td><?php echo e($m['npm']); ?></td>
                        <td><?php echo e($m['nama']); ?></td>
                        <td>
                            <form method="post" action="kelas.php" style="display:inline">
                                <?php echo csrf(); ?>
                                <input type="hidden" name="a" value="unenroll" />
                                <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
                                <input type="hidden" name="m" value="<?php echo (int) $m['id']; ?>" />
                                <button class="del" type="submit">Keluarkan</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
    <?php
}

bottom();
?>