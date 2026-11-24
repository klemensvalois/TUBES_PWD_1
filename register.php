<?php
/**
 * register.php - registrasi mahasiswa baru. Password disimpan sebagai hash bcrypt.
 */
require 'config.php';
require 'includes/layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $p = $_POST;

    $valid = filter_var($p['email'], FILTER_VALIDATE_EMAIL)
          && strlen($p['pw']) >= 8
          && preg_match('/^\d{6,12}$/', $p['npm']);

    if (!$valid) {
        $error = 'Data tidak valid (NPM berupa angka, email benar, password minimal 8 karakter).';
    } else {
        try {
            q("INSERT INTO lms_mahasiswa.mahasiswa (npm, nama, email, telp, fakultas_id, prodi_id, password)
               VALUES (?, ?, ?, ?, ?, ?, ?)",
              [$p['npm'], trim($p['nama']), $p['email'], $p['telp'], (int) $p['fak'], (int) $p['prodi'],
               password_hash($p['pw'], PASSWORD_BCRYPT)]);
            flash('Registrasi berhasil, silakan login.');
            go('index.php');
        } catch (PDOException $ex) {
            $error = 'NPM atau email sudah terdaftar.';
        }
    }
}

$fakultas = q("SELECT * FROM lms_admin.fakultas")->fetchAll();
$prodi    = q("SELECT * FROM lms_admin.prodi")->fetchAll();

auth_top('Registrasi');
?>
<form method="post" action="register.php">
    <h1>Registrasi Mahasiswa</h1>
    <?php
    if ($error) {
        echo '<div class="flash bad">' . e($error) . '</div>';
    }
    echo csrf();
    ?>

    <label>Nama <input type="text" name="nama" required="required" /></label>
    <label>NPM <input type="text" name="npm" required="required" /></label>
    <label>Email <input type="text" name="email" required="required" /></label>
    <label>No. telepon <input type="text" name="telp" /></label>
    <label>Fakultas
        <select name="fak">
            <?php
            foreach ($fakultas as $f) {
                echo '<option value="' . $f['id'] . '">' . e($f['nama']) . '</option>';
            }
            ?>
        </select>
    </label>
    <label>Program studi
        <select name="prodi">
            <?php
            foreach ($prodi as $f) {
                echo '<option value="' . $f['id'] . '">' . e($f['nama']) . '</option>';
            }
            ?>
        </select>
    </label>
    <label>Password <input type="password" name="pw" required="required" /></label>

    <button type="submit">Daftar</button>
    <p><a href="index.php">Kembali ke login</a></p>
</form>
<?php
auth_bottom();
?>