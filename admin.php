<?php
require_once 'admin_auth.php';
check_login();

if (isset($_POST['ajax_update_status'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("UPDATE pedidos SET estado_pago = ? WHERE id = ?");
        $stmt->execute([$_POST['nuevo_estado'], $_POST['pedido_id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

if (isset($_POST['delete_pedido'])) {
    $pedido_id = $_POST['pedido_id'];

    $stmt = $pdo->prepare("SELECT url_carpeta FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();

    if ($pedido && !empty($pedido['url_carpeta'])) {
        $ruta_relativa = ltrim($pedido['url_carpeta'], '/');
        if (strpos($ruta_relativa, '..') === false) {
            $dir_path = __DIR__ . '/' . $ruta_relativa;
            if (is_dir($dir_path)) {
                $files = glob($dir_path . '/*');
                foreach ($files as $file) {
                    if (is_file($file))
                        unlink($file);
                }
                rmdir($dir_path);
            }
        }
    }

    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);

    header("Location: admin.php");
    exit;
}

$pedidos = $pdo->query("SELECT * FROM pedidos ORDER BY fecha DESC")->fetchAll();

$pedidos_js = [];
$stats = ['total' => count($pedidos), 'pendiente' => 0, 'impreso' => 0, 'empaquetado' => 0, 'entregado' => 0];

foreach ($pedidos as $p) {
    $estado_lower = strtolower($p['estado_pago']);
    if (isset($stats[$estado_lower]))
        $stats[$estado_lower]++;

    $ruta_relativa = ltrim($p['url_carpeta'], '/');
    $dir_path = __DIR__ . '/' . $ruta_relativa;

    $files = [];
    if (is_dir($dir_path)) {
        $scanned_files = array_diff(scandir($dir_path), array('..', '.'));
        foreach ($scanned_files as $file) {
            $files[] = '/' . $ruta_relativa . $file;
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
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>

    <header>
        <h1>Panel de Administración Tokyo Shop</h1>
        <a href="logout.php" class="btn-logout">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
            Cerrar Sesión
        </a>
    </header>

    <div class="dashboard-cards">
        <div class="card">
            <h3>Total Pedidos</h3>
            <p><?= $stats['total'] ?></p>
        </div>
        <div class="card">
            <h3>Pendientes</h3>
            <p class="text-warning"><?= $stats['pendiente'] ?></p>
        </div>
        <div class="card">
            <h3>Para Entregar</h3>
            <p class="text-info"><?= $stats['empaquetado'] ?></p>
        </div>
        <div class="card">
            <h3>Entregados</h3>
            <p class="text-success"><?= $stats['entregado'] ?></p>
        </div>
    </div>

    <div class="controls-container">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Buscar por cliente, orden o modelo...">
        </div>
        <div class="filter-tabs">
            <button class="tab-btn active" data-filter="all">Todos</button>
            <button class="tab-btn" data-filter="pendiente">Pendientes</button>
            <button class="tab-btn" data-filter="impreso">Impresos</button>
            <button class="tab-btn" data-filter="empaquetado">Empaquetados</button>
            <button class="tab-btn" data-filter="entregado">Entregados</button>
        </div>
    </div>

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
            <tbody id="tableBody">
                <?php foreach ($pedidos as $p):
                    $estadoClass = strtolower($p['estado_pago']);
                    $searchData = strtolower($p['nombre_cliente'] . ' ' . $p['id_orden'] . ' ' . $p['modelo']);
                    ?>
                        <tr class="pedido-row" data-estado="<?= $estadoClass ?>" data-search="<?= htmlspecialchars($searchData) ?>">
                            <td>
                                <strong class="text-primary">#<?= htmlspecialchars($p['id_orden']) ?></strong>
                            </td>
                            <td>
                                <strong class="d-block mb-1"><?= htmlspecialchars($p['nombre_cliente']) ?></strong>
                                <span class="text-muted text-sm">
                                    📞 <?= htmlspecialchars($p['whatsapp']) ?><br>
                                    ✉️ <?= htmlspecialchars($p['email']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-500 mb-1 d-flex gap-1">
                                    <span class="badge-qty"><?= isset($p['cantidad']) ? htmlspecialchars($p['cantidad']) : '1' ?>x</span>
                                    Cámara <?= htmlspecialchars($p['modelo']) ?>
                                </div>
                                <span class="text-muted text-sm">📸 <?= htmlspecialchars($p['storage']) ?> fotos</span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $estadoClass ?>" id="badge-<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['estado_pago']) ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn-primary" onclick="openModal('<?= htmlspecialchars($p['id_orden']) ?>')">
                                    Ver Archivos
                                </button>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <select class="form-select status-select" data-id="<?= $p['id'] ?>">
                                        <option value="pendiente" <?= $estadoClass == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="impreso" <?= $estadoClass == 'impreso' ? 'selected' : '' ?>>Impreso</option>
                                        <option value="empaquetado" <?= $estadoClass == 'empaquetado' ? 'selected' : '' ?>>Empaquetado</option>
                                        <option value="entregado" <?= $estadoClass == 'entregado' ? 'selected' : '' ?>>Entregado</option>
                                    </select>
                                
                                    <form method="POST" id="deleteForm_<?= $p['id'] ?>" class="m-0"
                                        onsubmit="event.preventDefault(); customConfirm('¿Eliminar pedido y archivos?', () => { document.getElementById('deleteForm_<?= $p['id'] ?>').submit(); });">
                                        <input type="hidden" name="delete_pedido" value="1">
                                        <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-delete" title="Eliminar pedido">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="fileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Pedido <span id="modalOrderId" class="text-primary"></span></h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="mockup-container" id="mockupSection" style="display:none;">
                    <div class="mockup-info">
                        <h3>Mockup del Diseño</h3>
                        <a id="btnDescargarMockup" href="" download class="btn-download">⬇️ Descargar Mockup</a>
                    </div>
                    <div class="mockup-img-wrapper"><img id="mockupImg" src="" alt="Mockup"></div>
                </div>

                <div class="prints-grid">
                    <div class="print-card" id="frenteCard" style="display:none;">
                        <h3>Frente</h3>
                        <img id="frenteImg" src="" alt="Frente">
                        <a id="btnDescargarFrente" href="" download class="btn-download">⬇️ Bajar</a>
                    </div>
                    <div class="print-card" id="dorsoCard" style="display:none;">
                        <h3>Dorso</h3>
                        <img id="dorsoImg" src="" alt="Dorso">
                        <a id="btnDescargarDorso" href="" download class="btn-download">⬇️ Bajar</a>
                    </div>
                </div>

                <div class="originals-section">
                    <h3>Originales (<span id="originalsCount">0</span>)</h3>
                    <ul class="originals-grid" id="originalesList"></ul>
                </div>
            </div>
        </div>
    </div>

    <div id="customConfirm" class="confirm-overlay">
        <div class="confirm-box">
            <div class="confirm-icon">❓</div>
            <h3>Confirmar</h3>
            <p id="customConfirmMsg"></p>
            <div class="confirm-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('customConfirm').style.display='none'">Cancelar</button>
                <button type="button" id="customConfirmOk" class="btn-confirm">Confirmar</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast">Guardado correctamente</div>

    <script>
        const PEDIDOS_ARCHIVOS = <?= json_encode($pedidos_js) ?>;

        // Búsqueda
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.pedido-row').forEach(row => {
                const matchSearch = row.dataset.search.includes(term);
                const activeFilter = document.querySelector('.tab-btn.active').dataset.filter;
                const matchFilter = activeFilter === 'all' || row.dataset.estado === activeFilter;
                row.style.display = matchSearch && matchFilter ? '' : 'none';
            });
        });

        // Filtros por Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const term = document.getElementById('searchInput').value.toLowerCase();
                
                document.querySelectorAll('.pedido-row').forEach(row => {
                    const matchSearch = row.dataset.search.includes(term);
                    const matchFilter = filter === 'all' || row.dataset.estado === filter;
                    row.style.display = matchSearch && matchFilter ? '' : 'none';
                });
            });
        });

        // Actualización AJAX
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', async function() {
                const pedidoId = this.dataset.id;
                const nuevoEstado = this.value;
                const formData = new FormData();
                
                formData.append('ajax_update_status', '1');
                formData.append('pedido_id', pedidoId);
                formData.append('nuevo_estado', nuevoEstado);

                try {
                    const res = await fetch('admin.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    
                    if (data.success) {
                        const badge = document.getElementById(`badge-${pedidoId}`);
                        const row = badge.closest('.pedido-row');
                        
                        badge.className = `status-badge status-${nuevoEstado}`;
                        badge.innerText = select.options[select.selectedIndex].text;
                        row.dataset.estado = nuevoEstado;
                        
                        showToast();
                        document.querySelector('.tab-btn.active').click(); 
                    }
                } catch (err) {
                    alert("Error al actualizar estado");
                }
            });
        });

        function showToast() {
            const toast = document.getElementById("toast");
            toast.classList.add("show");
            setTimeout(() => toast.classList.remove("show"), 3000);
        }

        // Modal de Archivos
        function openModal(idOrden) {
            const data = PEDIDOS_ARCHIVOS[idOrden];
            if (!data) return;

            document.getElementById('modalOrderId').innerText = '#' + idOrden;
            document.getElementById('mockupSection').style.display = 'none';
            document.getElementById('frenteCard').style.display = 'none';
            document.getElementById('dorsoCard').style.display = 'none';
            const ul = document.getElementById('originalesList');
            ul.innerHTML = '';

            let count = 0;
            (data.files_list || []).forEach(file => {
                const filename = file.split('/').pop().toLowerCase();
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
                    count++;
                    const rawName = file.split('/').pop();
                    ul.innerHTML += `
                        <li class="original-item">
                            <img src="${file}" alt="${rawName}">
                            <div class="text-sm mb-1 text-truncate">${rawName}</div>
                            <a href="${file}" download="${rawName}" class="btn-download">⬇️ Bajar</a>
                        </li>`;
                }
            });

            if (count === 0) ul.innerHTML = '<li class="empty-warning">No hay fotos originales.</li>';
            document.getElementById('originalsCount').innerText = count;
            document.getElementById('fileModal').style.display = 'block';
        }

        function closeModal() { document.getElementById('fileModal').style.display = 'none'; }
        window.onclick = e => { if (e.target.classList.contains('modal')) closeModal(); }
        document.addEventListener('keydown', e => { if (e.key === "Escape") { closeModal(); document.getElementById('customConfirm').style.display = 'none'; }});

        function customConfirm(msg, onConfirm) {
            const modal = document.getElementById('customConfirm');
            document.getElementById('customConfirmMsg').innerText = msg;
            modal.style.display = 'flex';
            document.getElementById('customConfirmOk').onclick = () => { modal.style.display = 'none'; onConfirm(); };
        }
    </script>
</body>
</html>