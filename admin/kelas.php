<?php
require '../config.php';
need('admin');
require '../includes/layout.php';

function get_table_columns_safe($dbName, $tableName)
{
    try {
        $stmt = q("SHOW COLUMNS FROM `{$dbName}`.`{$tableName}`");
        return array_column($stmt->fetchAll(), 'Field');
    } catch (Throwable $e) {
        return [];
    }
}

function get_user_program_info($dbName, $tableName, $row)
{
    $cols = get_table_columns_safe($dbName, $tableName);
    $info = [];

    foreach (['prodi', 'program_studi', 'jurusan', 'prodi_id', 'id_prodi'] as $col) {
        if (in_array($col, $cols, true) && isset($row[$col]) && trim((string) $row[$col]) !== '') {
            $info[$col] = trim((string) $row[$col]);
        }
    }

    foreach (['fakultas', 'fakultas_id', 'id_fakultas'] as $col) {
        if (in_array($col, $cols, true) && isset($row[$col]) && trim((string) $row[$col]) !== '') {
            $info[$col] = trim((string) $row[$col]);
        }
    }

    return $info;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $post = $_POST;
    $aksi = isset($post['a']) ? $post['a'] : '';

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

            $mhs = q("SELECT * FROM lms_mahasiswa.mahasiswa WHERE npm=? OR email=?", [$dicari, $dicari])->fetch();
            if (!$mhs) {
                throw new Exception('Mahasiswa tidak ditemukan.');
            }

            $kelas = q("SELECT * FROM lms_admin.kelas WHERE id=?", [$kelas_id])->fetch();
            if (!$kelas) {
                throw new Exception('Kelas tidak ditemukan.');
            }

            $dosen = null;
            if (!empty($kelas['dosen_id'])) {
                $dosen = q("SELECT * FROM lms_dosen.dosen WHERE id=?", [(int) $kelas['dosen_id']])->fetch();
            }

            $mhsInfo   = get_user_program_info('lms_mahasiswa', 'mahasiswa', $mhs);
            $dosenInfo = $dosen ? get_user_program_info('lms_dosen', 'dosen', $dosen) : [];

            $prodiMismatch = false;
            foreach (['prodi', 'program_studi', 'jurusan', 'prodi_id', 'id_prodi'] as $pCol) {
                if (isset($mhsInfo[$pCol], $dosenInfo[$pCol])) {
                    if (strcasecmp($mhsInfo[$pCol], $dosenInfo[$pCol]) !== 0) {
                        $prodiMismatch = true;
                        break;
                    }
                }
            }

            if (!$prodiMismatch) {
                foreach (['fakultas', 'fakultas_id', 'id_fakultas'] as $fCol) {
                    if (isset($mhsInfo[$fCol], $dosenInfo[$fCol])) {
                        if (strcasecmp($mhsInfo[$fCol], $dosenInfo[$fCol]) !== 0) {
                            $prodiMismatch = true;
                            break;
                        }
                    }
                }
            }

            if ($prodiMismatch) {
                throw new Exception('Prodi/fakultas mahasiswa tidak sesuai dengan prodi/fakultas dosen kelas ini.');
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

echo '<h1>Fakultas &amp; Prodi</h1>';
echo '<div class="box">';
echo '<form method="post" action="kelas.php" class="row2">' . csrf() . '<input type="hidden" name="a" value="addfak" /><input type="text" name="nama" placeholder="Nama fakultas baru" required="required" /><button type="submit">Tambah fakultas</button></form><br />';
echo '<form method="post" action="kelas.php" class="row2">' . csrf() . '<input type="hidden" name="a" value="addprodi" /><select name="fak">';
foreach ($daftar_fak as $f) {
    echo '<option value="' . (int) $f['id'] . '">' . e($f['nama']) . '</option>';
}
echo '</select><input type="text" name="nama" placeholder="Nama prodi baru" required="required" /><button type="submit">Tambah prodi</button></form>';
echo '</div>';
echo '<h1>Kelas</h1>';
?>
<form method="post" action="kelas.php" class="card">
    <?php echo csrf(); ?>
    <input type="hidden" name="a" value="add" />
    <b>Buat kelas</b>
    <label>Kode <input type="text" name="kode" required="required" /></label>
    <label>Nama mata kuliah <input type="text" name="nama" required="required" /></label>
    <label>Dosen
        <select name="dosen">
            <?php
            foreach ($daftar_dosen as $d) {
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
            <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
            <button class="del" type="submit">Hapus kelas</button>
        </form>
        <form method="post" action="kelas.php">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="enroll" />
            <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
            <input type="text" name="who" placeholder="NPM / email mahasiswa" style="width:240px" />
            <button type="submit">Daftarkan</button>
        </form>
        <?php foreach ($peserta as $m) { ?>
            <form method="post" action="kelas.php" style="display:inline">
                <?php echo csrf(); ?>
                <input type="hidden" name="a" value="unenroll" />
                <input type="hidden" name="i" value="<?php echo (int) $k['id']; ?>" />
                <input type="hidden" name="m" value="<?php echo (int) $m['id']; ?>" />
                <span class="tag"><?php echo e($m['npm'] . ' ' . $m['nama']); ?> <button class="del" type="submit" style="padding:0 6px">x</button></span>
            </form>
        <?php } ?>
    </div>
    <?php
}

bottom();
?>