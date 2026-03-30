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
// Ajustar estos valores midiendo con Illustrator sobre el esqueleto
// ---------------------------------------------------------
$w_frente = 90; // Ancho del diseño frente
$h_frente = 50; // Alto del diseño frente
$w_dorso = 90;  // Ancho del diseño dorso
$h_dorso = 50;  // Alto del diseño dorso

// CÁMARA 1 (Arriba Izquierda)
$c1_xf = 10;
$c1_yf = 10;
$c1_xd = 110;
$c1_yd = 10;

// CÁMARA 2 (Arriba Derecha)
$c2_xf = 210;
$c2_yf = 10;
$c2_xd = 310;
$c2_yd = 10;

// CÁMARA 3 (Abajo Izquierda)
$c3_xf = 10;
$c3_yf = 150;
$c3_xd = 110;
$c3_yd = 150;

// CÁMARA 4 (Abajo Derecha)
$c4_xf = 210;
$c4_yf = 150;
$c4_xd = 310;
$c4_yd = 150;

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

    // Selección automática de plantilla según modelo (V1 o V2)
    $plantilla = __DIR__ . '/plantilla_base_' . $modelo . '.pdf';

    if (file_exists($plantilla)) {
        $pdf->setSourceFile($plantilla);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);
        $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
        $pdf->useTemplate($tplId);
    } else {
        $pdf->AddPage('L', 'A4');
    }

    // Estampado de las 4 Cámaras
    if (file_exists($path_frente) && file_exists($path_dorso)) {
        // Cámara 1
        $pdf->Image($path_frente, $c1_xf, $c1_yf, $w_frente, $h_frente, 'PNG');
        $pdf->Image($path_dorso, $c1_xd, $c1_yd, $w_dorso, $h_dorso, 'PNG');

        // Cámara 2
        $pdf->Image($path_frente, $c2_xf, $c2_yf, $w_frente, $h_frente, 'PNG');
        $pdf->Image($path_dorso, $c2_xd, $c2_yd, $w_dorso, $h_dorso, 'PNG');

        // Cámara 3
        $pdf->Image($path_frente, $c3_xf, $c3_yf, $w_frente, $h_frente, 'PNG');
        $pdf->Image($path_dorso, $c3_xd, $c3_yd, $w_dorso, $h_dorso, 'PNG');

        // Cámara 4
        $pdf->Image($path_frente, $c4_xf, $c4_yf, $w_frente, $h_frente, 'PNG');
        $pdf->Image($path_dorso, $c4_xd, $c4_yd, $w_dorso, $h_dorso, 'PNG');
    }

    // Texto de info de la orden
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