<?php
/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Cualquier copia no autorizada será reportada.
*/
require_once 'db.php';
header('Content-Type: application/json');

// ============================================
// RATE LIMITING por IP (VULN-01) — máx 10 pedidos por hora por IP
// ============================================
$ip = $_SERVER['REMOTE_ADDR'];
$rate_file = __DIR__ . '/rate_limit.json';
$rate_data = file_exists($rate_file) ? json_decode(file_get_contents($rate_file), true) : [];

if (!is_array($rate_data)) $rate_data = [];

// Limpiar registros expirados (más de 1 hora)
$now = time();
$rate_data = array_filter($rate_data, function ($entry) use ($now) {
    return ($now - $entry['time']) < 3600;
});

// Contar requests de esta IP en la última hora
$ip_count = 0;
foreach ($rate_data as $entry) {
    if ($entry['ip'] === $ip) $ip_count++;
}

if ($ip_count >= 10) {
    http_response_code(429); // Too Many Requests
    echo json_encode(["success" => false, "error" => "Demasiadas solicitudes. Intentá de nuevo más tarde."]);
    exit;
}

// Registrar este request
$rate_data[] = ['ip' => $ip, 'time' => $now];
file_put_contents($rate_file, json_encode($rate_data));

// ============================================
// Leer y validar el payload JSON
// ============================================
$raw_data = file_get_contents('php://input');

// PROTECCIÓN DoS 1: Limitar el tamaño del payload a ~25 MB (suficiente para las fotos de un pedido normal)
if (strlen($raw_data) > 25 * 1024 * 1024) {
    http_response_code(413); // 413 Payload Too Large
    echo json_encode(["success" => false, "error" => "Violación de seguridad: El tamaño del pedido excede el límite permitido."]);
    exit;
}

$data = json_decode($raw_data, true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "No se recibieron datos JSON válidos."]);
    exit;
}

$imagen_frente = isset($data['imagen_frente']) ? $data['imagen_frente'] : null;
$imagen_dorso = isset($data['imagen_dorso']) ? $data['imagen_dorso'] : null;
$mockup = isset($data['mockup']) ? $data['mockup'] : null;
$imagenes_utilizadas = isset($data['imagenes_utilizadas']) ? $data['imagenes_utilizadas'] : [];

// ============================================
// VALIDACIÓN ESTRICTA DE INPUTS (VULN-02, VULN-07)
// ============================================

// Modelo: solo V1 o V2
$modelos_validos = ['V1', 'V2'];
$modelo = isset($data['modelo']) && in_array($data['modelo'], $modelos_validos) ? $data['modelo'] : 'V1';

// Storage: solo 18, 24 o 36
$storage_validos = ['18', '24', '36'];
$storage = isset($data['storage']) && in_array((string) $data['storage'], $storage_validos) ? (string) $data['storage'] : '24';

// Cantidad: entre 1 y 100
$cantidad = isset($data['cantidad']) ? (int) $data['cantidad'] : 1;
if ($cantidad < 1 || $cantidad > 100) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Cantidad fuera de rango (1-100)."]);
    exit;
}

// Nombre: obligatorio, máx 100 caracteres
$nombre_cliente = isset($data['nombre_cliente']) ? trim(substr($data['nombre_cliente'], 0, 100)) : '';
if (empty($nombre_cliente) || strlen($nombre_cliente) < 2) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "El nombre es obligatorio (mín. 2 caracteres)."]);
    exit;
}

// Email: obligatorio, formato válido
$email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) : false;
if (!$email) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Email inválido."]);
    exit;
}
$email = substr($email, 0, 150); // Limitar longitud

// WhatsApp: solo dígitos, 8-20 chars
$whatsapp = isset($data['whatsapp']) ? preg_replace('/[^0-9+]/', '', substr($data['whatsapp'], 0, 20)) : '';
if (empty($whatsapp) || strlen($whatsapp) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Número de WhatsApp inválido (mín. 8 dígitos)."]);
    exit;
}

// PROTECCIÓN DoS 3: Limitar la cantidad de imágenes enviadas
if (is_array($imagenes_utilizadas) && count($imagenes_utilizadas) > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Se superó el límite máximo de imágenes por pedido."]);
    exit;
}

// ============================================
// Directorios de salida
// ============================================
$pedidos_dir = __DIR__ . '/pedidos/';
$id_orden = time() . '_' . rand(1000, 9999);
$nombre_carpeta = 'pedido_' . $id_orden;
$orden_dir = $pedidos_dir . $nombre_carpeta . '/';

$url_carpeta = "/pedidos/" . $nombre_carpeta . "/";
$url_mockup = $url_carpeta . "mockup.png";

if (!file_exists($orden_dir)) {
    mkdir($orden_dir, 0755, true);
}

// ============================================
// Función de guardado de imagen base64 (VULN-12)
// ============================================
function saveBase64Image($base64String, $outputFile)
{
    if (!$base64String)
        return false;

    // PROTECCIÓN DoS 2: Límite individual de ~10MB en base64 por imagen
    if (strlen($base64String) > 10 * 1024 * 1024) {
        return false;
    }

    // Verificación del header base64
    if (
        strpos($base64String, 'data:image/png') !== 0 &&
        strpos($base64String, 'data:image/jpeg') !== 0 &&
        strpos($base64String, 'data:image/jpg') !== 0
    ) {
        error_log("[TOKYO SECURITY] MIME header inválido en upload: $outputFile");
        return false;
    }

    $parts = explode(',', $base64String);
    $data = isset($parts[1]) ? base64_decode($parts[1]) : base64_decode($parts[0]);
    if (!$data)
        return false;

    // VALIDACIÓN DE CONTENIDO REAL: Verificar magic bytes (VULN-12)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->buffer($data);

    $allowedMimeTypes = ['image/png', 'image/jpeg'];
    if (!in_array($realMime, $allowedMimeTypes)) {
        error_log("[TOKYO SECURITY] Upload con MIME falso. Header dice imagen pero contenido real es: $realMime | Archivo: $outputFile");
        return false;
    }

    // Verificar que realmente sea una imagen procesable con GD
    $img = @imagecreatefromstring($data);
    if (!$img) {
        error_log("[TOKYO SECURITY] Archivo no es una imagen válida para GD: $outputFile");
        return false;
    }
    imagedestroy($img);

    file_put_contents($outputFile, $data);
    return true;
}

$path_frente = $orden_dir . 'frente.png';
$path_dorso = $orden_dir . 'dorso.png';
$path_mockup = $orden_dir . 'mockup.png';

$savedAny = false;

if ($imagen_frente) {
    saveBase64Image($imagen_frente, $path_frente);
    $savedAny = true;
}
if ($imagen_dorso) {
    saveBase64Image($imagen_dorso, $path_dorso);
    $savedAny = true;
}
if ($mockup) {
    saveBase64Image($mockup, $path_mockup);
    $savedAny = true;
}

// Guardar las imágenes originales separadas
if (is_array($imagenes_utilizadas)) {
    foreach ($imagenes_utilizadas as $idx => $base64) {
        $type = 'png';
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $type = strtolower($matches[1]);
            if ($type == 'jpeg')
                $type = 'jpg';
        }
        // Sanitizar la extensión por seguridad extra
        $type = preg_replace('/[^a-z]/', '', $type);
        if (!in_array($type, ['png', 'jpg'])) $type = 'png';

        $img_path = $orden_dir . 'imagen_original_' . ($idx + 1) . '.' . $type;
        saveBase64Image($base64, $img_path);
        $savedAny = true;
    }
}

// ============================================
// Guardar en la Base de Datos
// ============================================
if ($savedAny) {
    try {
        global $pdo, $conn, $conexion;
        $db = null;
        if (isset($pdo))
            $db = $pdo;
        elseif (isset($conn))
            $db = $conn;
        elseif (isset($conexion))
            $db = $conexion;

        if ($db) {
            $stmt = $db->prepare("INSERT INTO pedidos (id_orden, modelo, storage, cantidad, nombre_cliente, email, whatsapp, url_mockup, url_carpeta, estado_pago) VALUES (:id_orden, :modelo, :storage, :cantidad, :nombre_cliente, :email, :whatsapp, :url_mockup, :url_carpeta, 'pendiente')");
            $stmt->execute([
                ':id_orden' => $id_orden,
                ':modelo' => $modelo,
                ':storage' => $storage,
                ':cantidad' => $cantidad,
                ':nombre_cliente' => $nombre_cliente,
                ':email' => $email,
                ':whatsapp' => $whatsapp,
                ':url_mockup' => $url_mockup,
                ':url_carpeta' => $url_carpeta
            ]);

            echo json_encode([
                "success" => true,
                "id_orden" => $id_orden,
                "folder_url" => $url_carpeta
            ]);
        } else {
            error_log("[TOKYO] No se encontró la variable de conexión PDO en db.php");
            echo json_encode(["success" => false, "error" => "Error interno del servidor. Contactá al soporte."]);
        }
    } catch (PDOException $e) {
        // VULN-03: Nunca exponer errores de base de datos al cliente
        error_log("[TOKYO] Error DB en pedido $id_orden: " . $e->getMessage());
        echo json_encode(["success" => false, "error" => "Ocurrió un error interno. Contactá al soporte."]);
    }
} else {
    echo json_encode(["success" => false, "error" => "No se recibieron imágenes para guardar."]);
}
?>