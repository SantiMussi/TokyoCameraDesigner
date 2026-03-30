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
// COORDENADAS PARA LA CÁMARA EN VERTICAL (UNIDADES EN MM)
// ---------------------------------------------------------
// El canvas es apaisado, pero en el PDF irá rotado 90 grados.
// Por lo tanto, definimos el tamaño final que ocupará en el PDF:
$w_print = 55; // Ancho ocupado en el PDF
$h_print = 95; // Alto ocupado en el PDF

// Coordenadas para el Dorso (Arriba)
$x_dorso = 20; // Posición X dorso
$y_dorso = 10; // Posición Y dorso

// Coordenadas para el Frente (Abajo)
$x_frente = 20; // Posición X frente
$y_frente = 110; // Posición Y frente

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

    // Dibujar el Dorso (Arriba)
    // Usamos StartTransform y Rotate porque TCPDF no tiene parámetro de rotación directo en Image()
    if (file_exists($path_dorso)) {
        $pdf->StartTransform();
        // Calculamos el centro de la caja destino en el PDF
        $cx = $x_dorso + ($w_print / 2);
        $cy = $y_dorso + ($h_print / 2);
        
        // Rotamos 90 grados (cambiar a -90 si se necesita orientar al revés)
        $pdf->Rotate(90, $cx, $cy);
        
        // Como la imagen origen (del canvas) es apaisada y la queremos encajar 
        // en el espacio vertical, invertimos ancho y alto para dibujarla
        $img_w = $h_print; // 95
        $img_h = $w_print; // 55
        
        $img_x = $cx - ($img_w / 2);
        $img_y = $cy - ($img_h / 2);
        
        $pdf->Image($path_dorso, $img_x, $img_y, $img_w, $img_h, 'PNG');
        $pdf->StopTransform();
    }

    // Dibujar el Frente (Abajo)
    if (file_exists($path_frente)) {
        $pdf->StartTransform();
        $cx = $x_frente + ($w_print / 2);
        $cy = $y_frente + ($h_print / 2);
        
        $pdf->Rotate(90, $cx, $cy);
        
        $img_w = $h_print;
        $img_h = $w_print;
        
        $img_x = $cx - ($img_w / 2);
        $img_y = $cy - ($img_h / 2);
        
        $pdf->Image($path_frente, $img_x, $img_y, $img_w, $img_h, 'PNG');
        $pdf->StopTransform();
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