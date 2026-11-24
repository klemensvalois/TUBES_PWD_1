<?php
require '../config.php';
need('admin');
require '../includes/layout.php';

$is_dosen = (isset($_GET['t']) && $_GET['t'] === 'dosen');
$tabel    = $is_dosen ? 'lms_dosen.dosen' : 'lms_mahasiswa.mahasiswa';
$kolom_id = $is_dosen ? 'npp' : 'npm';
$label    = $is_dosen ? 'Dosen' : 'Mahasiswa';
$halaman  = 'admin/akun.php?t=' . ($is_dosen ? 'dosen' : 'mahasiswa');

$query_utama = "SELECT a.*, p.nama AS prodi, f.nama AS fak
                FROM $tabel a
                LEFT JOIN lms_admin.prodi p ON p.id = a.prodi_id
                LEFT JOIN lms_admin.fakultas f ON f.id = a.fakultas_id
                ORDER BY a.nama";

if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=' . $label . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, [$kolom_id, 'nama', 'email', 'telp', 'fakultas', 'prodi']);
    foreach (q($query_utama) as $row) {
        fputcsv($out, [$row[$kolom_id], $row['nama'], $row['email'], $row['telp'], $row['fak'], $row['prodi']]);
    }
    exit;
}

function simpan_akun($is_dosen, $tabel, $data)
{
    $pw    = isset($data['pw']) ? $data['pw'] : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $nama  = isset($data['nama']) ? trim($data['nama']) : '';
    $id_no = isset($data['id']) ? trim($data['id']) : '';

    if (strlen($pw) < 8 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email/password tidak valid (min. 8 karakter).');
    }

    $hash  = password_hash($pw, PASSWORD_BCRYPT);
    $fak   = isset($data['fak']) ? (int) $data['fak'] : 1;
    $prodi = isset($data['prodi']) ? (int) $data['prodi'] : 1;

    if ($is_dosen) {
        $telp = isset($data['telp']) ? trim($data['telp']) : '';
        q("INSERT INTO $tabel(npp, nama, email, telp, fakultas_id, prodi_id, password) VALUES(?, ?, ?, ?, ?, ?, ?)",
          [$id_no, $nama, $email, $telp, $fak, $prodi, $hash]);
    } else {
        $gender = isset($data['gender']) ? $data['gender'] : '';
        $umur   = isset($data['umur']) ? (int) $data['umur'] : 0;
        q("INSERT INTO $tabel(npm, nama, email, fakultas_id, prodi_id, gender, umur, password) VALUES(?, ?, ?, ?, ?, ?, ?, ?)",
          [$id_no, $nama, $email, $fak, $prodi, $gender, $umur, $hash]);
    }
}

$tujuan_redirect = $halaman;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $post = $_POST;
    $aksi = isset($post['a']) ? $post['a'] : '';

    try {
        if ($aksi === 'add') {
            simpan_akun($is_dosen, $tabel, $post);
            flash("$label ditambahkan.");
            if (!empty($post['more'])) {
                $tujuan_redirect .= '&new=1';
            }
        } elseif ($aksi === 'import' && !$is_dosen) {
            $jumlah = 0;
            if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
                $fh = fopen($_FILES['csv']['tmp_name'], 'r');
                fgetcsv($fh);
                while (($r = fgetcsv($fh)) !== false) {
                    if (count($r) < 4) {
                        continue;
                    }
                    try {
                        simpan_akun(false, $tabel, [
                            'id'     => $r[0],
                            'nama'   => $r[1],
                            'email'  => $r[2],
                            'pw'     => $r[3],
                            'fak'    => isset($r[4]) ? $r[4] : 1,
                            'prodi'  => isset($r[5]) ? $r[5] : 1,
                            'gender' => isset($r[6]) ? $r[6] : '',
                            'umur'   => isset($r[7]) ? $r[7] : 0
                        ]);
                        $jumlah++;
                    } catch (Exception $e_row) {
                    }
                }
                fclose($fh);
            }
            flash("$jumlah mahasiswa diimpor (kolom: npm,nama,email,password,fakultas_id,prodi_id,gender,umur).");
        } elseif ($aksi === 'upd') {
            $id_target = (int) $post['i'];
            $nama_baru  = trim($post['nama']);
            $email_baru = trim($post['email']);
            if ($is_dosen) {
                q("UPDATE $tabel SET nama=?, email=?, telp=? WHERE id=?",
                  [$nama_baru, $email_baru, $post['telp'], $id_target]);
            } else {
                q("UPDATE $tabel SET nama=?, email=?, gender=?, umur=? WHERE id=?",
                  [$nama_baru, $email_baru,$post['gender'], (int) $post['umur'],$id_target]);
            }
            if (!empty($post['pw'])) {
                if (strlen($post['pw']) < 8) {
                    throw new Exception('Password minimal 8 karakter.');
                }
                q("UPDATE $tabel SET password=? WHERE id=?",
                  [password_hash($post['pw'], PASSWORD_BCRYPT),$id_target]);
            }
            flash('Data diperbarui.');
        } elseif ($aksi === 'del') {
            $id_target = (int)$post['i'];
            q("DELETE FROM $tabel WHERE id=?", [$id_target]);
            if (!$is_dosen) {
                q("DELETE FROM lms_admin.kelas_mhs WHERE mhs_id=?", [$id_target]);
            }
            flash('Data dihapus.');
        }
    } catch (Exception $ex) {
        flash($ex instanceof PDOException ? 'ID atau email sudah dipakai.' : $ex->getMessage());
    }

    go($tujuan_redirect);
}

$daftar_fakultas = q("SELECT * FROM lms_admin.fakultas")->fetchAll();
$daftar_prodi    = q("SELECT * FROM lms_admin.prodi")->fetchAll();
$data_edit       = isset($_GET['edit']) ? q("SELECT * FROM $tabel WHERE id=?", [(int) $_GET['edit']])->fetch() : null;
$mode_baru       = isset($_GET['new']);

function render_opsi($list, $terpilih = 0)
{$html = '';
    foreach ($list as $item) {$selected = ($item['id'] ==$terpilih) ? ' selected="selected"' : '';
        $html .= '<option value="' . $item['id'] . '"' . $selected . '>' . e($item['nama']) . '</option>';
    }
    return $html;
}

top("Kelola $label", $halaman, false);

if ($is_dosen && ($mode_baru ||$data_edit)) {
    $val_id    = isset($data_edit['id']) ? $data_edit['id'] : '';$val_nama  = isset($data_edit['nama']) ?$data_edit['nama'] : '';
    $val_telp  = isset($data_edit['telp']) ? $data_edit['telp'] : '';$val_email = isset($data_edit['email']) ?$data_edit['email'] : '';
    ?>
    <div style="max-width:560px;margin:30px auto">
        <h1><?php echo $data_edit ? 'Edit' : 'Tambah'; ?> Dosen</h1>
        <form method="post" action="<?php echo BASE . '/' . $halaman; ?>">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="<?php echo $data_edit ? 'upd' : 'add'; ?>" />
            <input type="hidden" name="i" value="<?php echo $val_id; ?>" />
            <label>Nama Lengkap<input type="text" name="nama" value="<?php echo e($val_nama); ?>" required="required" /></label>
            <?php if (!$data_edit) { ?>
                <label>NPP<input type="text" name="id" required="required" style="width:50%" /></label>
            <?php } ?>
            <div class="row2">
                <label>No. telepon<input type="text" name="telp" value="<?php echo e($val_telp); ?>" /></label>
                <label>E-mail<input type="text" name="email" value="<?php echo e($val_email); ?>" required="required" /></label>
            </div>
            <?php if (!$data_edit) { ?>
                <div class="row2">
                    <label>Fakultas<select name="fak"><?php echo render_opsi($daftar_fakultas); ?></select></label>
                    <label>Program studi<select name="prodi"><?php echo render_opsi($daftar_prodi); ?></select></label>
                </div>
            <?php } ?>
            <label>Password <?php echo $data_edit ? '(kosongkan jika tidak diganti)' : ''; ?>
                <input type="password" name="pw" style="width:50%" <?php echo $data_edit ? '' : 'required="required"'; ?> />
            </label>
            <button type="submit" class="gray"><?php echo $data_edit ? 'Simpan' : 'Tambah Dosen'; ?></button>
            <a class="btn del" href="<?php echo BASE . '/' . $halaman; ?>">Batalkan</a>
        </form>
    </div>
    <?php
    bottom();
    exit;
}

$daftar_akun = q($query_utama)->fetchAll();
echo '<div style="display:flex;justify-content:space-between;align-items:center"><h1>' . ($is_dosen ? 'Dosen' : 'Mahasiswa') . '</h1><div><a class="link" href="' . BASE . '/' . $halaman . '&amp;export=1">Export CSV</a>';
echo $is_dosen
    ? '<a class="btn blue" href="' . BASE . '/' . $halaman . '&amp;new=1">Add Teachers</a>'
    : '<button class="blue" type="button" onclick="openM()">Tambah Mahasiswa</button>';
echo '</div></div>';

if (!$daftar_akun) {
    echo '<div class="empty"><svg width="200" height="120" viewBox="0 0 200 120"><ellipse cx="100" cy="85" rx="55" ry="28" fill="#ddd"/><circle cx="62" cy="70" r="18" fill="#ccc"/><path d="M20 100h160" stroke="#333"/><text x="150" y="30" font-size="16">Z</text><text x="168" y="14" font-size="12">z</text></svg><h3>Belum ada ' . strtolower($label) . ' saat ini</h3><p>' .$label . ' akan muncul di sini setelah mereka mendaftar di kampus Anda.</p></div>';
} else {
    echo '<div class="split"><div class="l"><div class="tbar"><span class="mut">Add filter &#9662;</span><div class="s"><input id="filter" placeholder="Search for a ' . strtolower($label) . ' by name or email" type="text" /></div></div><table id="tbl"><tr><th>Name</th><th>' . strtoupper($kolom_id) . '</th><th>Email address</th><th>Fakultas</th><th>Prodi</th></tr>';
    foreach ($daftar_akun as$r) {
        $umur_val = isset($r['umur']) ? $r['umur'] : '';$gen_val  = isset($r['gender']) ?$r['gender'] : '';
        echo '<tr class="row" data-pk="' . $r['id'] . '" data-id="' . e($r[$kolom_id]) . '" data-ini="' . e(ini($r['nama'])) . '" data-nama="' . e($r['nama']) . '" data-fak="' . e($r['fak']) . '" data-prodi="' . e($r['prodi']) . '" data-umur="' . e($umur_val) . '" data-gen="' . e($gen_val) . '" data-edit="' . BASE . '/' . $halaman . '&amp;edit=' .$r['id'] . '"><td><span class="who"><span class="av">' . e(ini($r['nama'])) . '</span>' . e($r['nama']) . '</span></td><td>' . e($r[$kolom_id]) . '</td><td>' . e($r['email']) . '</td><td>' . e($r['fak']) . '</td><td>' . e($r['prodi']) . '</td></tr>';
    }
    echo '</table></div><div class="detail" id="detail" style="display:none"><div id="d_id"></div><div class="big" id="d_av"></div><b id="d_nama"></b><br /><small class="mut" id="d_sub"></small><br /><br /><a id="d_edit" class="btn blue" href="#">Edit</a> <form method="post" action="' . BASE . '/' . $halaman . '" style="display:inline">' . csrf() . '<input type="hidden" name="a" value="del" /><input type="hidden" id="d_i" name="i" value="" /><button class="del" type="submit">Hapus</button></form>';
    echo '<dl><dt>Fakultas</dt><dd id="d_fak"></dd><dt>Prodi</dt><dd id="d_prodi"></dd><dt>Umur</dt><dd id="d_umur"></dd><dt>Jenis Kelamin</dt><dd id="d_gen"></dd></dl></div></div>';
}

if (!$is_dosen) {
    $m_id     = isset($data_edit['id']) ? $data_edit['id'] : '';$m_nama   = isset($data_edit['nama']) ?$data_edit['nama'] : '';
    $m_gender = isset($data_edit['gender']) ? $data_edit['gender'] : '';$m_umur   = isset($data_edit['umur']) ?$data_edit['umur'] : '';
    $m_email  = isset($data_edit['email']) ? $data_edit['email'] : '';$ov_class = ($mode_baru ||$data_edit) ? 'ov show' : 'ov';

    echo '<div id="ov" class="' . $ov_class . '"><div class="modal"><h1 style="font-size:24px">' . ($data_edit ? 'Edit' : 'Tambah') . ' Mahasiswa</h1>';
    if (!$data_edit) {
        echo '<div class="tabs"><a id="k1" class="on" onclick="tab(1)">Manual</a><a id="k2" onclick="tab(2)">Import CSV</a></div><br />';
    }
    ?>
    <div id="t1">
        <form method="post" action="<?php echo BASE . '/' . $halaman; ?>">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="<?php echo $data_edit ? 'upd' : 'add'; ?>" />
            <input type="hidden" name="i" value="<?php echo $m_id; ?>" />
            <div class="row2">
                <label style="flex:2">Name<input type="text" name="nama" value="<?php echo e($m_nama); ?>" required="required" /></label>
                <label>Gender
                    <select name="gender">
                        <option value="Cowok"<?php echo ($m_gender === 'Cowok') ? ' selected="selected"' : ''; ?>>Cowok</option>
                        <option value="Cewek"<?php echo ($m_gender === 'Cewek') ? ' selected="selected"' : ''; ?>>Cewek</option>
                    </select>
                </label>
                <label>Umur<input type="text" name="umur" value="<?php echo e($m_umur); ?>" /></label>
            </div>
            <?php if (!$data_edit) { ?>
                <div class="row2">
                    <label>Fakultas<select name="fak"><?php echo render_opsi($daftar_fakultas); ?></select></label>
                    <label>Prodi<select name="prodi"><?php echo render_opsi($daftar_prodi); ?></select></label>
                </div>
                <div class="row2">
                    <label>NPM<input type="text" name="id" required="required" /></label>
                    <label>Email address<input type="text" name="email" required="required" /></label>
                </div>
            <?php } else { ?>
                <label>Email address<input type="text" name="email" value="<?php echo e($m_email); ?>" required="required" /></label>
            <?php } ?>
            <label>Password <?php echo $data_edit ? '(kosongkan jika tidak diganti)' : ''; ?>
                <input type="password" name="pw" style="width:50%" <?php echo $data_edit ? '' : 'required="required"'; ?> />
            </label>
            <?php if (!$data_edit) { ?>
                <label><input type="checkbox" name="more" value="1" style="width:auto" /> Tambah Lain</label>
            <?php } ?>
            <button type="submit" class="gray">Masukan</button>
            <a class="btn del" href="<?php echo BASE . '/' . $halaman; ?>">Batalkan</a>
        </form>
    </div>
    <div id="t2" style="display:none">
        <form method="post" action="<?php echo BASE . '/' . $halaman; ?>" enctype="multipart/form-data">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="import" />
            <p class="mut">CSV dengan header: npm,nama,email,password,fakultas_id,prodi_id,gender,umur</p>
            <input type="file" name="csv" accept=".csv" /><br /><br />
            <button type="submit" class="gray">Impor</button>
            <a class="btn del" href="<?php echo BASE . '/' . $halaman; ?>">Batalkan</a>
        </form>
    </div>
    </div></div>
    <?php
}

bottom();
?>