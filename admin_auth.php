<?php
session_start();
require_once 'db.php';

function check_login()
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>