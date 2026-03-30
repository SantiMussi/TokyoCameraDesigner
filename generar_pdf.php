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
// COORDENADAS PARA UNA SOLA CÁMARA (UNIDADES EN MM)
// ---------------------------------------------------------
$w_frente = 90; // Ancho
$h_frente = 50; // Alto
$w_dorso = 90;
$h_dorso = 50;

$x_frente = 10; // Posición X frente
$y_frente = 10; // Posición Y frente

$x_dorso = 110; // Posición X dorso
$y_dorso = 10;  // Posición Y dorso

// Directorios
$uploads_dir = __DIR__ . '/uploads/';
$pedidos_dir = __DIR__ . '/pedidos_impresion/';

if (!file_exists($uploads_dir))
    mkdir($uploads_dir, 0777, true);
if (!file_exists($pedidos_dir))
    mkdir($pedidos_dir, 0777, true);

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

require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false);

    $plantilla = __DIR__ . '/plantilla_base_' . $modelo . '.pdf';

    if (file_exists($plantilla)) {
        $pdf->setSourceFile($plantilla);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);
        $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
        $pdf->useTemplate($tplId);
    } else {
        $pdf->AddPage('L', 'A4');
        $size = ['height' => 210]; // Backup por si falla
    }

    // Dibujar el Frente
    if (file_exists($path_frente)) {
        $pdf->Image($path_frente, $x_frente, $y_frente, $w_frente, $h_frente, 'PNG');
    }

    // Dibujar el Dorso
    if (file_exists($path_dorso)) {
        $pdf->Image($path_dorso, $x_dorso, $y_dorso, $w_dorso, $h_dorso, 'PNG');
    }

    // Info de la orden al pie
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(10, $size['height'] - 15);
    $pdf->Cell(0, 10, "Orden: $id_orden | Modelo: $modelo | Fotos: $storage", 0, 0, 'L');

    $pdf_filename = 'orden_' . $id_orden . '.pdf';
    $pdf_output = $pedidos_dir . $pdf_filename;
    $pdf->Output($pdf_output, 'F');

    echo json_encode([
        "success" => true,
        "pdf_url" => "/pedidos_impresion/" . $pdf_filename
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>