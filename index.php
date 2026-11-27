<?php
require 'config.php';
if (!empty($_SESSION['u'])) {
    go('dashboard.php');
}
go('index.xhtml');
?>