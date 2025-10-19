<?php
require_once("db.php");
require_once("User.php");

$user = new User();
$user->logout();
?>