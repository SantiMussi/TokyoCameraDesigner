<?php
/* PROPIEDAD DE TOKYO SHOP - BUENOS AIRES, ARGENTINA
Diseño y Desarrollo por Santiago M. (2026)
Script de Mantenimiento de Limpieza (Cron/Manual)
*/

// Forzar visualización de errores solo si se ejecuta manualmente para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuración
$dias_limite = 30; // Eliminar carpetas que tengan más de X días de antigüedad
$directorio_pedidos = __DIR__ . '/pedidos/';

// Tiempo actual menos $dias_limite (En segundos)
$limite_tiempo = time() - ($dias_limite * 24 * 60 * 60);

echo "[ MANTENIMIENTO: INICIANDO LIMPIEZA DE PEDIDOS VIEJOS ]\n";
echo "Buscando pedidos anteriores a: " . date("Y-m-d H:i:s", $limite_tiempo) . "\n\n";

$carpetas_borradas = 0;
$espacio_liberado = 0;

if (is_dir($directorio_pedidos)) {
    // Escanear contenido de la carpeta de pedidos
    $elementos = scandir($directorio_pedidos);

    foreach ($elementos as $elemento) {
        $ruta_completa = $directorio_pedidos . $elemento;

        // Ignorar . y .. y asegurarse de que es una carpeta que empiece por 'pedido_'
        if ($elemento != "." && $elemento != ".." && is_dir($ruta_completa) && strpos($elemento, 'pedido_') === 0) {
            
            // Comprobar la fecha de la última modificación (o de creación si no se modificó)
            $fecha_carpeta = filemtime($ruta_completa);

            // Si es más vieja que el límite calculado
            if ($fecha_carpeta < $limite_tiempo) {
                echo "-> Carpeta '$elemento' es antigua (" . date("Y-m-d", $fecha_carpeta) . "). Iniciando borrado...\n";

                // Escanear archivos dentro
                $archivos = glob($ruta_completa . '/*');
                $size_carpeta = 0;

                foreach ($archivos as $archivo) {
                    if (is_file($archivo)) {
                        $size_carpeta += filesize($archivo);
                        unlink($archivo); // Borrar el archivo
                    }
                }

                if (rmdir($ruta_completa)) { // Borrar la carpeta vacía
                    $carpetas_borradas++;
                    $espacio_liberado += $size_carpeta;
                    echo "   [OK] Carpeta y sus archivos borrados. Espacio liberado: " . round($size_carpeta / 1024 / 1024, 2) . " MB\n";
                } else {
                    echo "   [ERROR] No se pudo borrar la carpeta.\n";
                }
            }
        }
    }
} else {
    echo "¡Error! El directorio '$directorio_pedidos' no existe.\n";
}

echo "\n-------------------------------------------------\n";
echo "RESUMEN DE LIMPIEZA:\n";
echo "- Carpetas borradas: $carpetas_borradas\n";
echo "- Espacio total liberado: " . round($espacio_liberado / 1024 / 1024, 2) . " MB\n";
echo "-------------------------------------------------\n";
?>
