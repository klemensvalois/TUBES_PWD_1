<?php
require 'config.php';
require 'includes/layout.php';

$error = '';
$nama  = '';
$npm   = '';
$email = '';
$telp  = '';
$fak   = 0;
$prodi = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $nama  = trim($_POST['nama'] ?? '');
    $npm   = trim($_POST['npm'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telp  = trim($_POST['telp'] ?? '');
    $fak   = (int) ($_POST['fak'] ?? 0);
    $prodi = (int) ($_POST['prodi'] ?? 0);
    $pw    = $_POST['pw'] ?? '';

    if ($nama === '' || $npm === '' || $email === '' || $pw === '') {
        $error = 'Nama, NPM, email, dan password wajib diisi.';
    } elseif (!preg_match('/^\d{6,12}$/', $npm)) {
        $error = 'NPM harus berupa angka 6 sampai 12 digit.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($fak <= 0 || $prodi <= 0) {
        $error = 'Silakan pilih fakultas dan program studi.';
    } else {
        // Cek apakah prodi sesuai dengan fakultas yang dipilih
        $cekProdi = q("SELECT id FROM lms_admin.prodi WHERE id = ? AND fakultas_id = ?", [$prodi, $fak])->fetch();
        // Cek duplikat NPM dan email secara terpisah
        $cekNpm   = q("SELECT id FROM lms_mahasiswa.mahasiswa WHERE npm = ?", [$npm])->fetch();
        $cekEmail = q("SELECT id FROM lms_mahasiswa.mahasiswa WHERE email = ?", [$email])->fetch();

        if (!$cekProdi) {
            $error = 'Program studi tidak sesuai dengan fakultas yang dipilih.';
        } elseif ($cekNpm) {
            $error = 'NPM sudah terdaftar.';
        } elseif ($cekEmail) {
            $error = 'Email sudah terdaftar.';
        } else {
            try {
                q(
                    "INSERT INTO lms_mahasiswa.mahasiswa (npm, nama, email, telp, fakultas_id, prodi_id, password)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$npm, $nama, $email, $telp, $fak, $prodi, password_hash($pw, PASSWORD_BCRYPT)]
                );
                go('index.xhtml?msg=' . urlencode('Registrasi berhasil, silakan login.'));
                go('index.php');
            } catch (PDOException $ex) {
                $error = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
            }
        }
    }
}

$fakultas = q("SELECT * FROM lms_admin.fakultas")->fetchAll();
$prodiList = q("SELECT * FROM lms_admin.prodi")->fetchAll();

auth_top('Registrasi');
?>
<form method="post" action="register.php" id="form-register">
    <h1>Registrasi Mahasiswa</h1>
    <?php
    if ($error !== '') {
        echo '<div class="flash bad">' . e($error) . '</div>';
    }
    echo csrf();
    ?>

    <fieldset>
        <legend>Data Akun Mahasiswa</legend>

        <p>
            <label for="reg_nama">Nama
                <input type="text" id="reg_nama" name="nama" value="<?php echo e($nama); ?>" />
            </label>
        </p>

        <p>
            <label for="reg_npm">NPM
                <input type="text" id="reg_npm" name="npm" value="<?php echo e($npm); ?>" />
            </label>
            <span id="pesan_npm"></span>
        </p>

        <p>
            <label for="reg_email">Email
                <input type="text" id="reg_email" name="email" value="<?php echo e($email); ?>" />
            </label>
            <span id="pesan_email"></span>
        </p>

        <p>
            <label for="reg_telp">No. telepon
                <input type="text" id="reg_telp" name="telp" value="<?php echo e($telp); ?>" />
            </label>
        </p>

        <p>
            <label for="reg_fak">Fakultas
                <select id="reg_fak" name="fak">
                    <?php foreach ($fakultas as $f) { ?>
                        <option value="<?php echo $f['id']; ?>"<?php if ($fak == $f['id']) { echo ' selected="selected"'; } ?>>
                            <?php echo e($f['nama']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>

        <p>
            <label for="reg_prodi">Program studi
                <select id="reg_prodi" name="prodi">
                    <?php foreach ($prodiList as $p) { ?>
                        <option value="<?php echo $p['id']; ?>" data-fak="<?php echo $p['fakultas_id']; ?>"<?php if ($prodi == $p['id']) { echo ' selected="selected"'; } ?>>
                            <?php echo e($p['nama']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>

        <p>
            <label for="reg_pw">Password
                <input type="password" id="reg_pw" name="pw" />
            </label>
        </p>

        <p>
            <button type="submit" id="btn_daftar">Daftar</button>
        </p>
    </fieldset>

    <p><a href="index.xhtml">Kembali ke login</a></p>
</form>
<?php
bottom();
?>