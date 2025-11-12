<?php
session_start();
session_destroy();
header("Location: Staj/login.php");
exit;
?>