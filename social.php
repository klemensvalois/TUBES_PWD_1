<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

top('Social', 'social.php');
echo '<div class="box">Fitur komunitas kelas (forum diskusi) akan hadir di versi berikutnya.</div>';
bottom();
?>