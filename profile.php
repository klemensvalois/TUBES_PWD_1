<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

$u    = $_SESSION['u'];
$role = $u['role'];
$id   = $u['id'];

if ($role === 'mahasiswa') {
    $tabel = 'lms_mahasiswa.mahasiswa';
} elseif ($role === 'dosen') {
    $tabel = 'lms_dosen.dosen';
} else {
    $tabel = 'lms_admin.admin';
}

$user = q("SELECT * FROM {$tabel} WHERE id = ?", [$id])->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();

    // 1. Simpan foto hasil crop (dikirim dari canvas dalam bentuk base64)
    if (isset($_POST['foto_crop']) && $_POST['foto_crop'] !== '') {
        $dataUrl = $_POST['foto_crop'];

        if (preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $dataUrl, $m)) {
            $dataBase64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
            $isiGambar  = base64_decode($dataBase64);

            if ($isiGambar !== false && strlen($isiGambar) <= 2 * 1024 * 1024 && @getimagesizefromstring($isiGambar) !== false) {
                $folder = __DIR__ . '/uploads/profil';
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }

                // Hapus foto lama jika ada
                if (!empty($user['foto'])) {
                    $pathLama = $folder . '/' . $user['foto'];
                    if (is_file($pathLama)) {
                        unlink($pathLama);
                    }
                }

                $namaFile = 'profil_' . $role . '_' . $id . '_' . time() . '.jpg';
                file_put_contents($folder . '/' . $namaFile, $isiGambar);

                q("UPDATE {$tabel} SET foto = ? WHERE id = ?", [$namaFile, $id]);
                $_SESSION['u']['foto'] = $namaFile;
                flash('Foto profil berhasil diperbarui.');
            } else {
                flash('Gambar tidak valid atau ukuran melebihi 2 MB.');
            }
        } else {
            flash('Format gambar harus JPG atau PNG.');
        }
        go('profile.php');

    // 2. Hapus foto profil
    } elseif (isset($_POST['hapus_foto'])) {
        if (!empty($user['foto'])) {
            $pathLama = __DIR__ . '/uploads/profil/' . $user['foto'];
            if (is_file($pathLama)) {
                unlink($pathLama);
            }
            q("UPDATE {$tabel} SET foto = NULL WHERE id = ?", [$id]);
            $_SESSION['u']['foto'] = null;
            flash('Foto profil berhasil dihapus.');
        }
        go('profile.php');

    // 3. Simpan perubahan biodata & password
    } else {
        $nama  = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telp  = trim($_POST['hp'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if ($nama === '' || $email === '') {
            flash('Nama dan Email wajib diisi.');
            go('profile.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Format email tidak valid.');
            go('profile.php');
        }
        if ($pass !== '' && strlen($pass) < 8) {
            flash('Password baru minimal 8 karakter.');
            go('profile.php');
        }

        $cekEmail = q("SELECT id FROM {$tabel} WHERE email = ? AND id <> ?", [$email, $id])->fetch();
        if ($cekEmail) {
            flash('Email sudah digunakan akun lain.');
            go('profile.php');
        }

        if ($pass !== '') {
            q(
                "UPDATE {$tabel} SET nama = ?, email = ?, telp = ?, password = ? WHERE id = ?",
                [$nama, $email, $telp, password_hash($pass, PASSWORD_BCRYPT), $id]
            );
        } else {
            q(
                "UPDATE {$tabel} SET nama = ?, email = ?, telp = ? WHERE id = ?",
                [$nama, $email, $telp, $id]
            );
        }

        $_SESSION['u']['nama']  = $nama;
        $_SESSION['u']['email'] = $email;
        $_SESSION['u']['telp']  = $telp;

        flash('Perubahan profil berhasil disimpan.');
        go('profile.php');
    }
}

$punyaFoto = !empty($user['foto']);
$urlFoto   = $punyaFoto ? (BASE . '/uploads/profil/' . e($user['foto'])) : '';

top('Edit Profil', 'profile.php', true, ['profile.css']);
?>

<div class="box">
    <!-- Bagian Avatar Bulat + Menu Popup -->
    <div class="pr-avatar-wrap">
        <div class="pr-avatar-btn" id="btn-avatar" title="Klik untuk ubah foto profil">
            <?php if ($punyaFoto) { ?>
                <img src="<?php echo $urlFoto; ?>" alt="Foto Profil" class="foto-profil" />
            <?php } else { ?>
                <span class="av av-besar"><?php echo e(ini($user['nama'])); ?></span>
            <?php } ?>
            <span class="pr-avatar-overlay">Ubah Foto</span>
        </div>

        <h2 class="pr-nama-tengah"><?php echo e($user['nama']); ?></h2>
        <p class="mut pr-role-tengah"><?php echo e(ucfirst($role)); ?></p>

        <!-- Menu Dropdown Popup -->
        <div class="menu-foto" id="menu-foto">
            <?php if ($punyaFoto) { ?>
                <button type="button" class="menu-item" id="aksi-lihat">Lihat foto</button>
            <?php } ?>
            <button type="button" class="menu-item" id="aksi-unggah">Unggah foto</button>
            <?php if ($punyaFoto) { ?>
                <button type="button" class="menu-item menu-hapus" id="aksi-hapus">Hapus foto</button>
            <?php } ?>
        </div>
    </div>

    <!-- Input file tersembunyi + Form kirim hasil crop & hapus foto -->
    <input type="file" id="input-foto-file" accept="image/jpeg, image/png, image/gif" style="display: none;" />

    <form method="post" action="profile.php" id="form-simpan-crop" style="display: none;">
        <?php echo csrf(); ?>
        <input type="hidden" name="foto_crop" id="foto_crop" value="" />
    </form>

    <form method="post" action="profile.php" id="form-hapus-foto" style="display: none;">
        <?php echo csrf(); ?>
        <input type="hidden" name="hapus_foto" value="1" />
    </form>

    <!-- Form Edit Data Profil -->
    <form method="post" action="profile.php">
        <?php echo csrf(); ?>

        <div class="prow">
            <label for="f_nama">Nama Anda
                <input type="text" id="f_nama" name="nama" value="<?php echo e($user['nama']); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_nama')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_email">Email
                <input type="text" id="f_email" name="email" value="<?php echo e($user['email']); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_email')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_hp">Phone Number
                <input type="text" id="f_hp" name="hp" value="<?php echo e($user['telp'] ?? ''); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_hp')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_pass">Password baru (kosongkan jika tidak diganti)
                <input type="password" id="f_pass" name="password" value="" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_pass')">Edit</span>
        </div>

        <div style="margin-top: 14px;">
            <button type="submit" id="sv" class="blue" style="display: none;">Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Modal 1: Seret Gambar untuk Menyesuaikan (Crop) -->
<div class="modal-bg" id="modal-crop">
    <div class="crop-card">
        <div class="crop-header">
            <button type="button" class="crop-close" id="btn-tutup-crop">&times;</button>
            <span class="crop-title">Seret gambar untuk menyesuaikan</span>
            <button type="button" class="crop-reupload" id="btn-unggah-ulang">Unggah</button>
        </div>

        <div class="crop-stage" id="crop-stage">
            <img src="" alt="Preview Foto" id="crop-img" />
            <div class="crop-circle"></div>

            <div class="crop-zoom-box">
                <button type="button" id="btn-zoom-in">+</button>
                <button type="button" id="btn-zoom-out">&minus;</button>
            </div>
        </div>

        <div class="crop-footer">
            <button type="button" class="crop-save" id="btn-simpan-crop" title="Simpan foto">&#10003;</button>
        </div>
    </div>
</div>

<!-- Modal 2: Lihat Foto Profil -->
<?php if ($punyaFoto) { ?>
<div class="modal-bg" id="modal-lihat">
    <div class="lihat-card">
        <button type="button" class="lihat-close" id="btn-tutup-lihat">&times;</button>
        <img src="<?php echo $urlFoto; ?>" alt="Foto Profil Besar" class="lihat-img" />
    </div>
</div>
<?php } ?>

<?php
bottom();
?>