<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: logout.php
 * Description: Backend for logging out
 ******************************************************/
session_start();
session_unset();
session_destroy();
header("Location: Kuweblogin.html");
exit();
?>
