<?php
/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Cualquier copia no autorizada será reportada.
*/
require_once 'security_headers.php';
session_start();
require_once 'db.php';

$error = ' ';

$ip = $_SERVER['REMOTE_ADDR'];
$intentos_file = __DIR__ . '/intentos_login.json';
$intentos_data = [];

if (file_exists($intentos_file)) {
    $intentos_data = json_decode(file_get_contents($intentos_file), true);
    if (!is_array($intentos_data)) {
        $intentos_data = [];
    }
}

if (!isset($intentos_data[$ip])) {
    $intentos_data[$ip] = ['intentos' => 0, 'ultimo_intento' => 0];
}

$datos_ip = $intentos_data[$ip];
$bloqueado = false;
$tiempo_bloqueo = 900; // 15 minutos en segundos
$max_intentos = 5;

// Chequear estado de bloqueo actual
if ($datos_ip['intentos'] >= $max_intentos) {
    if (time() - $datos_ip['ultimo_intento'] < $tiempo_bloqueo) {
        $bloqueado = true;
        $restante = ceil(($tiempo_bloqueo - (time() - $datos_ip['ultimo_intento'])) / 60);
        $error = "Demasiados intentos fallidos. Tu IP está bloqueada por $restante minutos.";
    } else {
        // Expiró el tiempo de castigo
        $datos_ip['intentos'] = 0;
    }
}

// Generar token CSRF para el formulario de login (VULN-05)
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    // Validación CSRF del login (VULN-05)
    if (!isset($_POST['login_csrf']) || !hash_equals($_SESSION['login_csrf'], $_POST['login_csrf'])) {
        $error = 'Error de seguridad. Recargá la página.';
    } else {
        $user = $_POST['user'];
        $pass = $_POST['pass'];

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE usuario = ?");
        $stmt->execute([$user]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($pass, $admin['password'])) {
            // Login exitoso: Resetear contador para la IP
            $datos_ip['intentos'] = 0;
            $datos_ip['ultimo_intento'] = time();
            $intentos_data[$ip] = $datos_ip;
            file_put_contents($intentos_file, json_encode($intentos_data, JSON_PRETTY_PRINT));

            // 1. Prevención Fijación de Sesión (Session Fixation)
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            header('Location: admin.php'); // Te manda al dashboard
            exit;
        } else {
            // Login incorrecto: Sumar intento
            $datos_ip['intentos'] += 1;
            $datos_ip['ultimo_intento'] = time();
            $intentos_data[$ip] = $datos_ip;
            file_put_contents($intentos_file, json_encode($intentos_data, JSON_PRETTY_PRINT));

            $error = 'Usuario o contraseña incorrectos.';
        }
    }

    // Rotar token CSRF después de cada intento
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>TokyoShop | Panel Admin</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        body {
            background: #ff7bb4;
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-sizing: border-box;
        }

        button {
            background: #faff60;
            color: #ff7bb4;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <h2 style="color: #ff7bb4;">Tokyo Admin ✨</h2>
        <?php if ($error): ?>
            <p style="color:red; font-size:12px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="login_csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="text" name="user" placeholder="Usuario" required>
            <input type="password" name="pass" placeholder="Contraseña" required>
            <button type="submit">ENTRAR</button>
        </form>
    </div>
</body>

</html>