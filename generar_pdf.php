<?php
/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Cualquier copia no autorizada será reportada.
*/
require_once 'db.php';
header('Content-Type: application/json');

// Leer JSON enviado por Fetch
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
$modelo = isset($data['modelo']) ? $data['modelo'] : 'V1';
$storage = isset($data['storage']) ? $data['storage'] : '24';
$nombre_cliente = isset($data['nombre_cliente']) ? $data['nombre_cliente'] : '';
$email = isset($data['email']) ? $data['email'] : '';
$whatsapp = isset($data['whatsapp']) ? $data['whatsapp'] : '';
$cantidad = isset($data['cantidad']) ? (int) $data['cantidad'] : 1;

$mockup = isset($data['mockup']) ? $data['mockup'] : null;
$imagenes_utilizadas = isset($data['imagenes_utilizadas']) ? $data['imagenes_utilizadas'] : [];

// PROTECCIÓN DoS 3: Limitar la cantidad de imágenes enviadas
if (is_array($imagenes_utilizadas) && count($imagenes_utilizadas) > 50) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Se superó el límite máximo de imágenes por pedido."]);
    exit;
}

// Directorios
$pedidos_dir = __DIR__ . '/pedidos/';
$id_orden = time() . '_' . rand(1000, 9999);
$nombre_carpeta = 'pedido_' . $id_orden;
$orden_dir = $pedidos_dir . $nombre_carpeta . '/';

$url_carpeta = "/pedidos/" . $nombre_carpeta . "/";
$url_mockup = $url_carpeta . "mockup.png";

if (!file_exists($orden_dir)) {
    mkdir($orden_dir, 0755, true);
}

function saveBase64Image($base64String, $outputFile)
{
    if (!$base64String)
        return false;

    // PROTECCIÓN DoS 2: Límite individual de ~10MB en base64 por imagen
    if (strlen($base64String) > 10 * 1024 * 1024) {
        return false;
    }

    // Tarea de seguridad: Verificación estricta de MIME type
    if (
        strpos($base64String, 'data:image/png') !== 0 &&
        strpos($base64String, 'data:image/jpeg') !== 0 &&
        strpos($base64String, 'data:image/jpg') !== 0
    ) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Violación de seguridad: MIME type no está permitido. Sólo PNG o JPEG."]);
        exit;
    }

    $parts = explode(',', $base64String);
    $data = isset($parts[1]) ? base64_decode($parts[1]) : base64_decode($parts[0]);
    if (!$data)
        return false;

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
        $img_path = $orden_dir . 'imagen_original_' . ($idx + 1) . '.' . $type;
        saveBase64Image($base64, $img_path);
        $savedAny = true;
    }
}

if ($savedAny) {
    // Guardar en la Base de Datos
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
            echo json_encode(["success" => false, "error" => "No se encontró la variable de conexión PDO en db.php ($pdo, $conn, o $conexion)."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "error" => "Error de base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "error" => "No se recibieron imágenes para guardar."]);
}
?>