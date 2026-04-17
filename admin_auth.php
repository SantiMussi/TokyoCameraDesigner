<?php
require_once 'security_headers.php';
session_start();
require_once 'db.php';

function check_login()
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // Generar token CSRF si no existe en la sesión
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>