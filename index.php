<?php
require 'config.php';
if (!empty($_SESSION['u'])) {
    go('dashboard.php');
}
require 'includes/layout.php';

function find_login_user($role, $loginId)
{
    $roleMap = [
        'mahasiswa' => [
            'db'     => 'lms_mahasiswa',
            'tables' => ['mahasiswa', 'users', 'akun'],
            'cols'   => ['npm', 'nim', 'username', 'email'],
        ],
        'dosen' => [
            'db'     => 'lms_dosen',
            'tables' => ['dosen', 'users', 'akun'],
            'cols'   => ['npp', 'nip', 'username', 'email'],
        ],
        'admin' => [
            'db'     => 'lms_admin',
            'tables' => ['admin', 'users', 'akun'],
            'cols'   => ['username', 'email', 'id'],
        ],
    ];

    if (!isset($roleMap[$role])) {
        return null;
    }

    $cfg    = $roleMap[$role];
    $dbName = $cfg['db'];

    foreach ($cfg['tables'] as $tbl) {
        try {
            $colStmt = q("SHOW COLUMNS FROM `{$dbName}`.`{$tbl}`");
            $columns = array_column($colStmt->fetchAll(), 'Field');
        } catch (Throwable $e) {
            continue;
        }

        if (empty($columns)) {
            continue;
        }

        $matchCols = [];
        $params    = [];
        foreach ($cfg['cols'] as $c) {
            if (in_array($c, $columns, true)) {
                $matchCols[] = "`{$c}` = ?";
                $params[]    = $loginId;
            }
        }

        if (empty($matchCols)) {
            continue;
        }

        $sql = "SELECT * FROM `{$dbName}`.`{$tbl}` WHERE (" . implode(' OR ', $matchCols) . ") LIMIT 1";
        $row = q($sql, $params)->fetch();
        if ($row) {
            if (empty($row['role'])) {
                $row['role'] = $role;
            }
            return $row;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();

    $role     = isset($_POST['role']) ? strtolower(trim($_POST['role'])) : '';
    $login_id = isset($_POST['login_id']) ? trim($_POST['login_id']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($role === '' || $login_id === '' || $password === '') {
        flash('Lengkapi peran, ID/email, dan password terlebih dahulu.');
        go('index.php');
    }

    $row   = find_login_user($role, $login_id);
    $valid = false;

    if ($row) {
        $passCol = null;
        foreach (['password', 'password_hash', 'pass', 'kata_sandi'] as $cand) {
            if (array_key_exists($cand, $row)) {
                $passCol = $cand;
                break;
            }
        }

        if ($passCol !== null) {
            $stored = (string) $row[$passCol];
            $valid  = password_verify($password, $stored)
                   || hash_equals($stored, $password)
                   || (strlen($stored) === 32 && hash_equals(strtolower($stored), md5($password)));
        }
    }

    if ($valid && $row) {
        unset($row['password'], $row['password_hash'], $row['pass'], $row['kata_sandi']);
        $_SESSION['u'] = $row;
        go('dashboard.php');
    }

    flash('Role, ID/Email, atau Password tidak sesuai.');
    go('index.php');
}

auth_top('Masuk LMS');
$pesan = flash();
?>

<div class="auth-logo">
    <img src="<?php echo e(BASE . '/images/UAJY-LOGOGRAM_-01.png'); ?>" alt="Logo UAJY" />
</div>

<form method="post" action="">
    <?php echo csrf(); ?>

    <label for="role">Masuk sebagai
        <select id="role" name="role">
            <option value="mahasiswa" selected="selected">Mahasiswa</option>
            <option value="dosen">Dosen</option>
            <option value="admin">Admin</option>
        </select>
    </label>

    <label for="login_id">NPM / NPP / Username / Email
        <input type="text" id="login_id" name="login_id" value="" />
    </label>

    <label for="password">Password
        <input type="password" id="password" name="password" value="" />
    </label>

    <?php if ($pesan): ?>
        <div class="flash bad"><?php echo e($pesan); ?></div>
    <?php endif; ?>

    <button type="submit">Login</button>
</form>

<p><a href="register.php">Registrasi mahasiswa baru</a></p>

<?php
bottom();