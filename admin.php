<?php
require_once 'admin_auth.php';
check_login();

// Lógica para actualizar estados si se recibe un POST
if (isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE pedidos SET estado_pago = ? WHERE id = ?");
    $stmt->execute([$_POST['nuevo_estado'], $_POST['pedido_id']]);
}

// Traer todos los pedidos
$pedidos = $pdo->query("SELECT * FROM pedidos ORDER BY fecha DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Tokyo Admin | Gestión de Pedidos</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            color: #333;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-download {
            background: #ff7bb4;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body style="background: #f4f4f4; padding: 40px;">
    <h1>Panel de Administración ✨</h1>
    <a href="logout.php">Cerrar Sesión</a>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Cliente</th>
                <th>Modelo</th>
                <th>Estado</th>
                <th>Archivos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td>
                        <?php echo $p['id_orden']; ?>
                    </td>
                    <td>
                        <?php echo $p['nombre_cliente']; ?><br><small>
                            <?php echo $p['whatsapp']; ?>
                        </small>
                    </td>
                    <td>
                        <?php echo $p['modelo']; ?> (
                        <?php echo $p['storage']; ?> fotos)
                    </td>
                    <td><span class="status-badge">
                            <?php echo $p['estado_pago']; ?>
                        </span></td>
                    <td>
                        <a href="<?php echo $p['url_carpeta']; ?>" class="btn-download" target="_blank">Ver Carpeta</a>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                            <select name="nuevo_estado">
                                <option value="pendiente">Pendiente</option>
                                <option value="impreso">Impreso</option>
                                <option value="empaquetado">Empaquetado</option>
                                <option value="entregado">Entregado</option>
                            </select>
                            <button type="submit" name="update_status">Actualizar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>