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

// ---------------------------------------------------------
// COORDENADAS PARA LA INYECCIÓN EN EL PDF (UNIDADES EN MM)
// Ajustar estos valores manualmente para encajar en la plantilla real
// ---------------------------------------------------------
// Imagen Frente
$x_frente = 10;
$y_frente = 10;
$w_frente = 90;
$h_frente = 50;

// Imagen Dorso
$x_dorso = 110;
$y_dorso = 10;
$w_dorso = 90;
$h_dorso = 50;

// Directorios
$uploads_dir = __DIR__ . '/uploads/';
$pedidos_dir = __DIR__ . '/pedidos_impresion/';

if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}
if (!file_exists($pedidos_dir)) {
    mkdir($pedidos_dir, 0777, true);
}

// Función auxiliar para guardar Base64 como PNG temporal
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

$id_orden = time() . '_' . rand(1000, 9999);
$path_frente = $uploads_dir . 'frente_' . $id_orden . '.png';
$path_dorso = $uploads_dir . 'dorso_' . $id_orden . '.png';

if ($imagen_frente)
    saveBase64Image($imagen_frente, $path_frente);
if ($imagen_dorso)
    saveBase64Image($imagen_dorso, $path_dorso);

// ---------------------------------------------------------
// INTEGRACIÓN CON TCPDF Y FPDI
// ---------------------------------------------------------
// ACÁ ESTÁ EL CAMBIO: El autoload está activado
require_once __DIR__ . '/vendor/autoload.php';

try {
    // ACÁ ESTÁ EL CAMBIO: Se borró el bloque de simulación
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false);

    // Plantilla Base PDF a inyectar
    $plantilla = __DIR__ . '/plantilla_base.pdf';

    if (file_exists($plantilla)) {
        $pdf->setSourceFile($plantilla);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);

        $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
        $pdf->useTemplate($tplId);
    } else {
        // Crea una A4 Horizontal de rescate si no está la plantilla
        $pdf->AddPage('L', 'A4');
    }

    // Dibujar el Frente en el PDF
    if (file_exists($path_frente)) {
        $pdf->Image($path_frente, $x_frente, $y_frente, $w_frente, $h_frente, 'PNG');
    }

    // Dibujar el Dorso en el PDF
    if (file_exists($path_dorso)) {
        $pdf->Image($path_dorso, $x_dorso, $y_dorso, $w_dorso, $h_dorso, 'PNG');
    }

    // Escribir texto complementario (ej. tipo de almacenamiento)
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(10, 70); // Posición debajo de las fotos
    $pdf->Cell(0, 10, "Modelo: $modelo - Capacidad: $storage fotos", 0, 1, 'L');

    // Guardar archivo final
    $pdf_filename = 'orden_' . $id_orden . '.pdf';
    $pdf_output = $pedidos_dir . $pdf_filename;

    $pdf->Output($pdf_output, 'F');

    // Devolvemos el JSON de éxito
    echo json_encode([
        "success" => true,
        "message" => "PDF Generado con éxito",
        "pdf_url" => "/pedidos_impresion/" . $pdf_filename
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>