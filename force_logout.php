<?php
session_start();
session_unset();
session_destroy();
header('Location: logeo_del_prototipo.php');
exit;
?>