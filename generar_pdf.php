<?php
header('Content-Type: application/json');

// Leer JSON enviado por Fetch
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "No se recibieron datos JSON válidos."]);
    exit;
}

$imagen_frente = isset($data['imagen_frente']) ? $data['imagen_frente'] : null;
$imagen_dorso = isset($data['imagen_dorso']) ? $data['imagen_dorso'] : null;
$modelo = isset($data['modelo']) ? $data['modelo'] : 'V1';
$storage = isset($data['storage']) ? $data['storage'] : '24';

$mockup = isset($data['mockup']) ? $data['mockup'] : null;
$imagenes_utilizadas = isset($data['imagenes_utilizadas']) ? $data['imagenes_utilizadas'] : [];

// Directorios
$pedidos_dir = __DIR__ . '/pedidos/';
$id_orden = time() . '_' . rand(1000, 9999);
$orden_dir = $pedidos_dir . 'pedido_' . $id_orden . '/';

if (!file_exists($orden_dir)) {
    mkdir($orden_dir, 0777, true);
}

function saveBase64Image($base64String, $outputFile)
{
    if (!$base64String)
        return false;
    $parts = explode(',', $base64String);
    $data = isset($parts[1]) ? base64_decode($parts[1]) : base64_decode($parts[0]);
    if (!$data)
        return false;
    file_put_contents($outputFile, $data);
    return true;
}

$path_frente = $orden_dir . 'frente_impresion.png';
$path_dorso = $orden_dir . 'dorso_impresion.png';
$path_mockup = $orden_dir . 'mockup.png'; // Requerido especificamente "el mockup de la forma que te mandé"

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
    echo json_encode([
        "success" => true,
        "folder_url" => "/pedidos/pedido_" . $id_orden . "/"
    ]);
} else {
    echo json_encode(["success" => false, "error" => "No se recibieron imágenes para guardar."]);
}
?>