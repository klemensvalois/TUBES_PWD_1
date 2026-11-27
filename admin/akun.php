<?php
require '../config.php';
need('admin');
require '../includes/layout.php';

$jenis = (isset($_GET['t']) && $_GET['t'] === 'mahasiswa') ? 'mahasiswa' : 'dosen';

if ($jenis === 'mahasiswa') {
    $tabel     = 'lms_mahasiswa.mahasiswa';
    $kolom_id  = 'npm';
    $label_id  = 'NPM';
    $judul_hal = 'Mahasiswa';
    $label_btn = 'Students';
} else {
    $tabel     = 'lms_dosen.dosen';
    $kolom_id  = 'npp';
    $label_id  = 'NPP';
    $judul_hal = 'Dosen';
    $label_btn = 'Teachers';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $aksi = $_POST['a'] ?? '';

    try {
        if ($aksi === 'add') {
            $no_id  = trim($_POST['id_no'] ?? '');
            $nama   = trim($_POST['nama'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $fak_id = (int) ($_POST['fakultas_id'] ?? 0);
            $prd_id = (int) ($_POST['prodi_id'] ?? 0);
            $pass   = $_POST['pass'] ?? '';

            if ($no_id === '' || $nama === '' || $email === '' || $pass === '') {
                throw new Exception('ID, nama, email, dan password wajib diisi.');
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            q("INSERT INTO {$tabel}({$kolom_id}, nama, email, fakultas_id, prodi_id, password) VALUES(?, ?, ?, ?, ?, ?)",
              [$no_id, $nama, $email, $fak_id, $prd_id, $hash]);
            flash('Akun berhasil ditambahkan.');
        } elseif ($aksi === 'upd') {
            $id     = (int) ($_POST['i'] ?? 0);
            $no_id  = trim($_POST['id_no'] ?? '');
            $nama   = trim($_POST['nama'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $fak_id = (int) ($_POST['fakultas_id'] ?? 0);
            $prd_id = (int) ($_POST['prodi_id'] ?? 0);
            $pass   = $_POST['pass'] ?? '';

            if ($id <= 0 || $no_id === '' || $nama === '' || $email === '') {
                throw new Exception('Data edit tidak lengkap.');
            }

            if ($pass !== '') {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                q("UPDATE {$tabel} SET {$kolom_id}=?, nama=?, email=?, fakultas_id=?, prodi_id=?, password=? WHERE id=?",
                  [$no_id, $nama, $email, $fak_id, $prd_id, $hash, $id]);
            } else {
                q("UPDATE {$tabel} SET {$kolom_id}=?, nama=?, email=?, fakultas_id=?, prodi_id=? WHERE id=?",
                  [$no_id, $nama, $email, $fak_id, $prd_id, $id]);
            }
            flash('Akun berhasil diperbarui.');
        } elseif ($aksi === 'del') {
            $id = (int) ($_POST['i'] ?? 0);
            if ($id > 0) {
                q("DELETE FROM {$tabel} WHERE id=?", [$id]);
                flash('Akun berhasil dihapus.');
            }
        }
    } catch (Exception $ex) {
        flash($ex->getMessage());
    }

    go('admin/akun.php?t=' . $jenis);
}

$id_edit   = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$data_edit = null;
if ($id_edit > 0) {
    $data_edit = q("SELECT * FROM {$tabel} WHERE id=?", [$id_edit])->fetch();
}

$buka_form = ($data_edit !== null || (isset($_GET['new']) && $_GET['new'] === '1'));

$daftar_fak   = q("SELECT id, nama FROM lms_admin.fakultas ORDER BY nama ASC")->fetchAll();
$daftar_prodi = q("SELECT id, nama FROM lms_admin.prodi ORDER BY nama ASC")->fetchAll();

$map_fak = [];
$n_df    = count($daftar_fak);
for ($i = 0; $i < $n_df; $i++) {
    $map_fak[(int) $daftar_fak[$i]['id']] = $daftar_fak[$i]['nama'];
}

$map_prodi = [];
$n_dp      = count($daftar_prodi);
for ($i = 0; $i < $n_dp; $i++) {
    $map_prodi[(int) $daftar_prodi[$i]['id']] = $daftar_prodi[$i]['nama'];
}

$daftar_akun = q("SELECT * FROM {$tabel} ORDER BY id DESC")->fetchAll();

$val_id     = $data_edit['id'] ?? '';
$val_no_id  = $data_edit[$kolom_id] ?? '';
$val_nama   = $data_edit['nama'] ?? '';
$val_email  = $data_edit['email'] ?? '';
$val_fak_id = isset($data_edit['fakultas_id']) ? (int) $data_edit['fakultas_id'] : 0;
$val_prd_id = isset($data_edit['prodi_id']) ? (int) $data_edit['prodi_id'] : 0;

top($judul_hal, 'admin/akun.php?t=' . $jenis);
?>
<div class="box">
    <a class="btn blue" href="akun.php?t=<?php echo e($jenis); ?>&new=1">Tambah <?php echo e($judul_hal); ?></a>
</div>

<?php if ($buka_form) { ?>
<div class="card">
    <b><?php echo $data_edit ? 'Edit' : 'Tambah'; ?> <?php echo e($judul_hal); ?></b>
    <form method="post" action="akun.php?t=<?php echo e($jenis); ?>">
        <?php echo csrf(); ?>
        <input type="hidden" name="a" value="<?php echo $data_edit ? 'upd' : 'add'; ?>" />
        <input type="hidden" name="i" value="<?php echo e($val_id); ?>" />
        <label><?php echo e($label_id); ?> <input type="text" name="id_no" value="<?php echo e($val_no_id); ?>" required="required" /></label>
        <label>Nama <input type="text" name="nama" value="<?php echo e($val_nama); ?>" required="required" /></label>
        <label>Email <input type="email" name="email" value="<?php echo e($val_email); ?>" required="required" /></label>
        <label>Fakultas
            <select name="fakultas_id">
                <option value="0">-- Pilih Fakultas --</option>
                <?php
                for ($i = 0; $i < $n_df; $i++) {
                    $f_id = (int) $daftar_fak[$i]['id'];
                    $sel  = ($f_id === $val_fak_id) ? ' selected="selected"' : '';
                    echo '<option value="' . $f_id . '"' . $sel . '>' . e($daftar_fak[$i]['nama']) . '</option>';
                }
                ?>
            </select>
        </label>
        <label>Prodi
            <select name="prodi_id">
                <option value="0">-- Pilih Prodi --</option>
                <?php
                for ($i = 0; $i < $n_dp; $i++) {
                    $p_id = (int) $daftar_prodi[$i]['id'];
                    $sel  = ($p_id === $val_prd_id) ? ' selected="selected"' : '';
                    echo '<option value="' . $p_id . '"' . $sel . '>' . e($daftar_prodi[$i]['nama']) . '</option>';
                }
                ?>
            </select>
        </label>
        <label>Password <input type="password" name="pass" <?php echo $data_edit ? '' : 'required="required"'; ?> /></label>
        <button type="submit">Simpan</button>
        <a class="btn" href="akun.php?t=<?php echo e($jenis); ?>">Batal</a>
    </form>
</div>
<?php } ?>

<div class="box">
    <b>Daftar <?php echo e($label_btn); ?></b>
    <table class="tbl">
        <tr>
            <th><?php echo e($label_id); ?></th>
            <th>Nama</th>
            <th>Email</th>
            <th>Fakultas</th>
            <th>Prodi</th>
            <th>Aksi</th>
        </tr>
        <?php
        $n_akun = count($daftar_akun);
        for ($i = 0; $i < $n_akun; $i++) {
            $r        = $daftar_akun[$i];
            $inisial  = ini($r['nama'] ?? '');
            $f_id     = isset($r['fakultas_id']) ? (int) $r['fakultas_id'] : 0;
            $p_id     = isset($r['prodi_id']) ? (int) $r['prodi_id'] : 0;
            $fak_nama = $map_fak[$f_id] ?? '';
            $prd_nama = $map_prodi[$p_id] ?? '';
            $umur_val = $r['umur'] ?? '-';
            $gen_val  = $r['jk'] ?? ($r['gender'] ?? '-');
            $link_ed  = 'akun.php?t=' . $jenis . '&edit=' . (int) $r['id'];
            ?>
            <tr
                data-pk="<?php echo (int) $r['id']; ?>"
                data-id="<?php echo e($r[$kolom_id] ?? ''); ?>"
                data-ini="<?php echo e($inisial); ?>"
                data-nama="<?php echo e($r['nama'] ?? ''); ?>"
                data-fak="<?php echo e($fak_nama); ?>"
                data-prodi="<?php echo e($prd_nama); ?>"
                data-umur="<?php echo e($umur_val); ?>"
                data-gen="<?php echo e($gen_val); ?>"
                data-edit="<?php echo e($link_ed); ?>">
                <td>
                    <span class="who">
                        <span class="av"><?php echo e($inisial); ?></span>
                        <?php echo e($r[$kolom_id] ?? ''); ?>
                    </span>
                </td>
                <td><?php echo e($r['nama'] ?? ''); ?></td>
                <td><?php echo e($r['email'] ?? ''); ?></td>
                <td><?php echo e($fak_nama); ?></td>
                <td><?php echo e($prd_nama); ?></td>
                <td>
                    <a class="btn" href="<?php echo e($link_ed); ?>">Edit</a>
                    <form method="post" action="akun.php?t=<?php echo e($jenis); ?>" style="display:inline">
                        <?php echo csrf(); ?>
                        <input type="hidden" name="a" value="del" />
                        <input type="hidden" name="i" value="<?php echo (int) $r['id']; ?>" />
                        <button class="del" type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

<aside class="detail" id="detail" style="display:none">
    <span id="d_id"></span>
    <p class="big" id="d_av"></p>
    <b id="d_nama"></b><br />
    <small class="mut" id="d_sub"></small><br /><br />
    <a id="d_edit" class="btn blue" href="#">Edit</a>
    <form method="post" action="akun.php?t=<?php echo e($jenis); ?>" style="display:inline">
        <?php echo csrf(); ?>
        <input type="hidden" name="a" value="del" />
        <input type="hidden" id="d_i" name="i" value="" />
        <button class="del" type="submit">Hapus</button>
    </form>
    <dl>
        <dt>Fakultas</dt><dd id="d_fak"></dd>
        <dt>Prodi</dt><dd id="d_prodi"></dd>
        <dt>Umur</dt><dd id="d_umur"></dd>
        <dt>Jenis Kelamin</dt><dd id="d_gen"></dd>
    </dl>
</aside>

<?php
bottom();
?>