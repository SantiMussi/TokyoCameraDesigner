<?php
// ============================================
// security_headers.php — Headers de Seguridad
// Incluir al inicio de cada archivo PHP público
// ============================================

// Configuración segura de cookies de sesión (VULN-11)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Descomentar si el sitio usa HTTPS:
// ini_set('session.cookie_secure', 1);
?>
