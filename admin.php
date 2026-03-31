<?php
/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Cualquier copia no autorizada será reportada.
*/
require_once 'admin_auth.php';
check_login();

// Lógica para eliminar pedido si se recibe un POST
if (isset($_POST['delete_pedido'])) {
    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$_POST['pedido_id']]);

    if (!empty($_POST['carpeta'])) {
        $ruta_relativa = ltrim($_POST['carpeta'], '/');
        $dir_path = __DIR__ . '/' . $ruta_relativa;
        if (is_dir($dir_path)) {
            $files = glob($dir_path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir_path);
        }
    }
    header("Location: admin.php");
    exit;
}

// Lógica para actualizar estados si se recibe un POST
if (isset($_POST['update_status'])) {
    $stmt = $pdo->prepare("UPDATE pedidos SET estado_pago = ? WHERE id = ?");
    $stmt->execute([$_POST['nuevo_estado'], $_POST['pedido_id']]);
}

// Traer todos los pedidos
$pedidos = $pdo->query("SELECT * FROM pedidos ORDER BY fecha DESC")->fetchAll();

// Preparar lista de archivos de cada pedido para el JS
$pedidos_js = [];
foreach ($pedidos as $p) {
    // Evitar barra doble si url_carpeta ya empieza con /
    $ruta_relativa = ltrim($p['url_carpeta'], '/');
    $dir_path = __DIR__ . '/' . $ruta_relativa;

    $files = [];
    if (is_dir($dir_path)) {
        $scanned_files = array_diff(scandir($dir_path), array('..', '.'));
        foreach ($scanned_files as $file) {
            // Asegurar que la ruta comience con '/' para URLs
            $url_base = '/' . $ruta_relativa;
            $files[] = $url_base . $file;
        }
    }
    $pedidos_js[$p['id_orden']] = [
        'id_orden' => $p['id_orden'],
        'files_list' => $files
    ];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tokyo Admin | Gestión de Pedidos</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/admin.css">
    
</head>

<body>

    <header>
        <h1>Panel de Administración Tokyo Shop</h1>
        <a href="logout.php" class="btn-logout">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Cerrar Sesión
        </a>
    </header>

    <div class="table-container">
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
                            <strong
                                style="color:var(--primary); font-size:15px;">#<?php echo htmlspecialchars($p['id_orden']); ?></strong>
                        </td>
                        <td>
                            <strong
                                style="font-size: 15px; display:block; margin-bottom:4px;"><?php echo htmlspecialchars($p['nombre_cliente']); ?></strong>
                            <span style="color: #718096; font-size: 13px;">
                                📞 <?php echo htmlspecialchars($p['whatsapp']); ?><br>
                                ✉️ <?php echo htmlspecialchars($p['email']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 500; margin-bottom:4px; display: flex; align-items: center; gap: 6px;">
                                <span style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700;">
                                    <?php echo isset($p['cantidad']) ? htmlspecialchars($p['cantidad']) : '1'; ?>x
                                </span>
                                Cámara <?php echo htmlspecialchars($p['modelo']); ?>
                            </div>
                            <span style="color: #718096; font-size: 13px;">📸 <?php echo htmlspecialchars($p['storage']); ?>
                                fotos</span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($p['estado_pago']); ?>">
                                <?php echo htmlspecialchars($p['estado_pago']); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn-primary"
                                onclick="openModal('<?php echo htmlspecialchars($p['id_orden']); ?>')">
                                Ver Archivos
                            </button>
                        </td>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <form method="POST" class="form-status" style="margin:0;">
                                    <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($p['id']); ?>">
                                    <select name="nuevo_estado" class="form-select">
                                        <option value="pendiente" <?php echo $p['estado_pago'] == 'pendiente' ? 'selected' : ''; ?>>
                                            Pendiente</option>
                                        <option value="impreso" <?php echo $p['estado_pago'] == 'impreso' ? 'selected' : ''; ?>>
                                            Impreso</option>
                                        <option value="empaquetado" <?php echo $p['estado_pago'] == 'empaquetado' ? 'selected' : ''; ?>>Empaquetado</option>
                                        <option value="entregado" <?php echo $p['estado_pago'] == 'entregado' ? 'selected' : ''; ?>>
                                            Entregado</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-update">Guardar</button>
                                </form>
                                <form method="POST" style="margin:0;"
                                    id="deleteForm_<?php echo htmlspecialchars($p['id']); ?>"
                                    onsubmit="event.preventDefault(); customConfirm('¿Estás seguro de que deseas eliminar este pedido y todos sus archivos? Esta acción no se puede deshacer.', () => { document.getElementById('deleteForm_<?php echo htmlspecialchars($p['id']); ?>').submit(); }, 'Eliminar pedido', '🗑️');">
                                    <input type="hidden" name="delete_pedido" value="1">
                                    <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($p['id']); ?>">
                                    <input type="hidden" name="carpeta"
                                        value="<?php echo htmlspecialchars($p['url_carpeta']); ?>">
                                    <button type="submit" class="btn-delete" title="Eliminar pedido">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de gestion de archivos -->
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Archivos del Pedido <span id="modalOrderId" style="color: var(--primary);"></span></h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">

                <!-- Mockup Section -->
                <div class="mockup-container" id="mockupSection" style="display:none;">
                    <div class="mockup-info">
                        <h3>Mockup del Diseño</h3>
                        <p>Esta es la previsualización del diseño que hizo el cliente en el frontend.</p>
                        <a id="btnDescargarMockup" href="" download="mockup.png" class="btn-download">⬇️ Descargar
                            Mockup</a>
                    </div>
                    <div class="mockup-img-wrapper">
                        <img id="mockupImg" src="" alt="Mockup">
                    </div>
                </div>

                <!-- Frente / Dorso Section -->
                <div class="prints-grid">
                    <div class="print-card" id="frenteCard" style="display:none;">
                        <h3>Impresión: Frente</h3>
                        <img id="frenteImg" src="" alt="Frente">
                        <a id="btnDescargarFrente" href="" download="frente.png" class="btn-download">⬇️ Descargar
                            Frente</a>
                    </div>
                    <div class="print-card" id="dorsoCard" style="display:none;">
                        <h3>Impresión: Dorso</h3>
                        <img id="dorsoImg" src="" alt="Dorso">
                        <a id="btnDescargarDorso" href="" download="dorso.png" class="btn-download">⬇️ Descargar
                            Dorso</a>
                    </div>
                </div>

                <!-- Originals Section -->
                <div class="originals-section">
                    <h3>Fotos Originales Subidas (<span id="originalsCount">0</span>)</h3>
                    <ul class="originals-grid" id="originalesList">
                        <!-- Las fotos se inyectan acá con JS -->
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="customConfirm"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:10001; justify-content:center; align-items:center; backdrop-filter: blur(4px); font-family: 'Inter', sans-serif;">
        <div
            style="background:white; padding:30px; border-radius:16px; width:90%; max-width:400px; text-align:center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: pop 0.3s ease-out;">
            <div id="customConfirmIcon" style="font-size:40px; margin-bottom:10px;">❓</div>
            <h3 id="customConfirmTitle" style="margin:0 0 10px 0; color:#333; font-weight:700; font-size:20px;">
                Confirmar</h3>
            <p id="customConfirmMsg" style="margin:0 0 20px 0; color:#666; font-size:15px; line-height:1.5;"></p>
            <div style="display:flex; justify-content:center; gap:10px;">
                <button type="button" onclick="document.getElementById('customConfirm').style.display='none'"
                    style="flex:1; background:#f1f1f1; color:#555; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition:0.2s;">Cancelar</button>
                <button type="button" id="customConfirmOk"
                    style="flex:1; background:#ef4444; color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; font-size:15px; transition:0.2s;">Confirmar</button>
            </div>
        </div>
    </div>
    

    <!-- Data para el JS -->
    <script>
        const PEDIDOS_ARCHIVOS = <?php echo json_encode($pedidos_js); ?>;

        function openModal(idOrden) {
            const data = PEDIDOS_ARCHIVOS[idOrden];
            if (!data) return;

            // Resetear Modal
            document.getElementById('modalOrderId').innerText = '#' + idOrden;
            document.getElementById('mockupSection').style.display = 'none';
            document.getElementById('frenteCard').style.display = 'none';
            document.getElementById('dorsoCard').style.display = 'none';
            document.getElementById('originalesList').innerHTML = '';

            let originalsCount = 0;

            if (data.files_list && data.files_list.length > 0) {
                data.files_list.forEach(file => {
                    const parts = file.split('/');
                    const filename = parts[parts.length - 1].toLowerCase();

                    if (filename.includes('mockup')) {
                        document.getElementById('mockupImg').src = file;
                        document.getElementById('btnDescargarMockup').href = file;
                        document.getElementById('mockupSection').style.display = 'flex';
                    } else if (filename.includes('frente')) {
                        document.getElementById('frenteImg').src = file;
                        document.getElementById('btnDescargarFrente').href = file;
                        document.getElementById('frenteCard').style.display = 'block';
                    } else if (filename.includes('dorso')) {
                        document.getElementById('dorsoImg').src = file;
                        document.getElementById('btnDescargarDorso').href = file;
                        document.getElementById('dorsoCard').style.display = 'block';
                    } else if (filename.includes('imagen_original')) {
                        originalsCount++;
                        const rawName = parts[parts.length - 1];
                        const li = document.createElement('li');
                        li.className = 'original-item';
                        li.innerHTML = `
                            <img src="${file}" alt="${rawName}">
                            <div style="font-size:12px; margin-bottom:8px; color:#718096; word-break:break-all;">${rawName}</div>
                            <a href="${file}" download="${rawName}" class="btn-download">⬇️ Bajar</a>
                        `;
                        document.getElementById('originalesList').appendChild(li);
                    }
                });
            }

            if (originalsCount === 0) {
                document.getElementById('originalesList').innerHTML = '<li class="empty-warning">No hay fotos originales asociadas a este pedido.</li>';
            }

            document.getElementById('originalsCount').innerText = originalsCount;
            document.getElementById('fileModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('fileModal').style.display = 'none';
        }

        // Cerrar al clickear afuera del modal
        window.onclick = function (event) {
            const modal = document.getElementById('fileModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Cerrar con Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === "Escape") {
                closeModal();
                document.getElementById('customConfirm').style.display = 'none';
            }
        });

        // Modal Function
        function customConfirm(msg, onConfirm, title = 'Confirmar', icon = '❓') {
            const modal = document.getElementById('customConfirm');
            if (!modal) { if (confirm(msg)) onConfirm(); return; }
            document.getElementById('customConfirmTitle').innerText = title;
            document.getElementById('customConfirmMsg').innerText = msg;
            document.getElementById('customConfirmIcon').innerText = icon;
            modal.style.display = 'flex';

            document.getElementById('customConfirmOk').onclick = function () {
                modal.style.display = 'none';
                onConfirm();
            };
        }
    </script>
</body>

</html>