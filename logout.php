<?php
require 'config.php';
session_unset();
session_destroy();
go('index.xhtml');
?>