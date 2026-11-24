<?php
require '../config.php';
need('dosen');
require '../includes/layout.php';

$id_dosen = $_SESSION['u']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $post = $_POST;
    $aksi = isset($post['a']) ? $post['a'] : '';

    if ($aksi === 'add') {
        $id_kelas = (int) $post['kelas'];
        $milik    = q("SELECT 1 FROM lms_admin.kelas WHERE id=? AND dosen_id=?", [$id_kelas, $id_dosen])->fetch();
        if ($milik) {
            q("INSERT INTO lms_dosen.materi(kelas_id, dosen_id, judul, isi) VALUES(?, ?, ?, ?)",
              [$id_kelas, $id_dosen, trim($post['judul']), $post['isi']]);
        }
    } elseif ($aksi === 'del') {
        q("DELETE FROM lms_dosen.materi WHERE id=? AND dosen_id=?", [(int) $post['i'], $id_dosen]);
    }

    flash('Berhasil.');
    go('dosen/materi.php');
}

$kelas_dosen  = q("SELECT * FROM lms_admin.kelas WHERE dosen_id=?", [$id_dosen])->fetchAll();
$daftar_materi = q("SELECT m.*, k.nama AS kelas
                    FROM lms_dosen.materi m
                    JOIN lms_admin.kelas k ON k.id = m.kelas_id
                    WHERE m.dosen_id=?
                    ORDER BY m.id DESC", [$id_dosen])->fetchAll();

top('Materi', 'dosen/materi.php');
?>
<form method="post" action="materi.php" class="card">
    <?php echo csrf(); ?>
    <input type="hidden" name="a" value="add" />
    <label>Kelas
        <select name="kelas">
            <?php
            foreach ($kelas_dosen as $k) {
                echo '<option value="' . $k['id'] . '">' . e($k['nama']) . '</option>';
            }
            ?>
        </select>
    </label>
    <label>Judul <input type="text" name="judul" required="required" /></label>
    <label>Isi / tautan materi <textarea name="isi" rows="4" cols="50"></textarea></label>
    <button type="submit">Bagikan materi</button>
</form>

<?php foreach ($daftar_materi as $m) { ?>
    <div class="box">
        <b><?php echo e($m['judul']); ?></b> <small>(<?php echo e($m['kelas']); ?>)</small>
        <p><?php echo nl2br(e($m['isi'])); ?></p>
        <form method="post" action="materi.php">
            <?php echo csrf(); ?>
            <input type="hidden" name="a" value="del" />
            <input type="hidden" name="i" value="<?php echo $m['id']; ?>" />
            <button class="del" type="submit">Hapus</button>
        </form>
    </div>
<?php } ?>

<?php
bottom();
?>