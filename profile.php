<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

function resolve_profile_target($u)
{
    $role = isset($u['role']) ? strtolower((string) $u['role']) : '';
    $roleMap = [
        'mahasiswa' => [
            'db'     => 'lms_mahasiswa',
            'tables' => ['mahasiswa', 'users', 'akun'],
        ],
        'dosen' => [
            'db'     => 'lms_dosen',
            'tables' => ['dosen', 'users', 'akun'],
        ],
        'admin' => [
            'db'     => 'lms_admin',
            'tables' => ['admin', 'users', 'akun'],
        ],
    ];

    if (!isset($roleMap[$role])) {
        return null;
    }

    $dbName = $roleMap[$role]['db'];
    foreach ($roleMap[$role]['tables'] as $tbl) {
        try {
            $colStmt = q("SHOW COLUMNS FROM `{$dbName}`.`{$tbl}`");
            $cols    = array_column($colStmt->fetchAll(), 'Field');
        } catch (Throwable $e) {
            continue;
        }

        if (!empty($cols)) {
            return [
                'db'   => $dbName,
                'tbl'  => $tbl,
                'cols' => $cols,
            ];
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();

    $u      = $_SESSION['u'];
    $nama   = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $email  = isset($_POST['email']) ? trim($_POST['email']) : '';
    $hp     = isset($_POST['hp']) ? trim($_POST['hp']) : '';
    $pass   = isset($_POST['password']) ? $_POST['password'] : '';

    if ($nama === '' || $email === '') {
        flash('Nama dan Email wajib diisi.');
        go('profile.php');
    }

    $target = resolve_profile_target($u);
    if (!$target) {
        flash('Target tabel profil tidak ditemukan.');
        go('profile.php');
    }

    $dbName = $target['db'];
    $tbl    = $target['tbl'];
    $cols   = $target['cols'];

    $sets   = [];
    $params = [];

    if (in_array('nama', $cols, true)) {
        $sets[]   = '`nama` = ?';
        $params[] = $nama;
    } elseif (in_array('nama_lengkap', $cols, true)) {
        $sets[]   = '`nama_lengkap` = ?';
        $params[] = $nama;
    }

    if (in_array('email', $cols, true)) {
        $sets[]   = '`email` = ?';
        $params[] = $email;
    }

    $hpCol = null;
    foreach (['hp', 'no_hp', 'telepon', 'telp', 'phone'] as $candHp) {
        if (in_array($candHp, $cols, true)) {
            $hpCol    = $candHp;
            $sets[]   = "`{$candHp}` = ?";
            $params[] = $hp;
            break;
        }
    }

    if ($pass !== '') {
        foreach (['password', 'password_hash', 'pass', 'kata_sandi'] as $candPass) {
            if (in_array($candPass, $cols, true)) {
                $sets[]   = "`{$candPass}` = ?";
                $params[] = password_hash($pass, PASSWORD_DEFAULT);
                break;
            }
        }
    }

    $whereCol = null;
    $whereVal = null;
    foreach (['id', 'id_user', 'npm', 'npp', 'username'] as $candKey) {
        if (in_array($candKey, $cols, true) && !empty($u[$candKey])) {
            $whereCol = $candKey;
            $whereVal = $u[$candKey];
            break;
        }
    }
    if ($whereCol === null && in_array('email', $cols, true) && !empty($u['email'])) {
        $whereCol = 'email';
        $whereVal = $u['email'];
    }

    if (!empty($sets) && $whereCol !== null && $whereVal !== null) {
        $params[] = $whereVal;
        $sql = "UPDATE `{$dbName}`.`{$tbl}` SET " . implode(', ', $sets) . " WHERE `{$whereCol}` = ? LIMIT 1";
        q($sql, $params);

        $_SESSION['u']['nama']  = $nama;
        $_SESSION['u']['email'] = $email;
        if ($hpCol !== null) {
            $_SESSION['u'][$hpCol] = $hp;
        }
        $_SESSION['u']['hp'] = $hp;

        flash('Perubahan profil berhasil disimpan.');
    } else {
        flash('Tidak ada kolom yang diperbarui.');
    }

    go('profile.php');
}

$u        = $_SESSION['u'];
$inisial  = function_exists('ini') ? ini($u['nama']) : strtoupper(substr($u['nama'], 0, 2));
$idTampil = isset($u['id_user']) ? $u['id_user'] : (isset($u['id']) ? $u['id'] : '');
$subInfo  = (isset($u['fakultas']) && $idTampil !== '')
    ? $u['fakultas'] . ' | ' . $idTampil
    : (isset($u['role']) ? ucfirst($u['role']) : '');
$hpVal    = isset($u['hp']) ? $u['hp'] : (isset($u['no_hp']) ? $u['no_hp'] : (isset($u['telepon']) ? $u['telepon'] : ''));

top('Profile', 'profile.php', true, ['profile.css']);
?>

<div class="box">
    <form method="post" action="">
        <?php echo csrf(); ?>

        <div class="pr-head">
            <span class="av"><?php echo e($inisial); ?></span>
            <div>
                <h2><?php echo e($u['nama']); ?></h2>
                <p class="mut"><?php echo e($subInfo); ?></p>
            </div>
        </div>

        <div class="prow">
            <label for="f_nama">Nama Anda
                <input type="text" id="f_nama" name="nama" value="<?php echo e($u['nama']); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_nama')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_email">Email
                <input type="text" id="f_email" name="email" value="<?php echo e(isset($u['email']) ? $u['email'] : ''); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_email')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_hp">Phone Number
                <input type="text" id="f_hp" name="hp" value="<?php echo e($hpVal); ?>" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_hp')">Edit</span>
        </div>

        <div class="prow">
            <label for="f_pass">Password baru
                <input type="password" id="f_pass" name="password" value="" placeholder="**********" readonly="readonly" />
            </label>
            <span class="pill" onclick="ed('f_pass')">Edit</span>
        </div>

        <div style="margin-top: 14px;">
            <button type="submit" id="sv" class="blue" style="display: none;">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php
bottom();