<?php
// IMPORTANTE: No mostrar errores en output para evitar contaminar el JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log errores a archivo en lugar de mostrarlos
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Buffer de salida para capturar cualquier output no deseado
ob_start();

// Habilita CORS y define el tipo de contenido
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    http_response_code(200);
    exit();
}

// Conexión a la base de datos
try {
    include_once __DIR__ . '/../conexion.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error incluyendo conexión: " . $e->getMessage()
    ]);
    exit();
}

// Verificar conexión
if (!isset($conn) || !$conn) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Variable de conexión no definida"
    ]);
    exit();
}

if ($conn->connect_error) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error de conexión: " . $conn->connect_error
    ]);
    exit();
}

try {
    // Consulta para contar los diferentes estatus
    $query = "
        SELECT 
            estatus,
            COUNT(*) as cantidad
        FROM chips 
        WHERE estatus IN ('stock', 'asignado', 'usado')
        GROUP BY estatus
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    // Inicializar contadores
    $conteos = [
        'stock' => 0,
        'asignado' => 0,
        'usado' => 0
    ];
    
    // Procesar resultados
    while ($row = $result->fetch_assoc()) {
        $conteos[$row['estatus']] = (int)$row['cantidad'];
    }
    
    // Calcular total
    $total = array_sum($conteos);
    
    // Limpiar cualquier output buffer antes de enviar JSON
    ob_clean();
    
    // Respuesta exitosa
    echo json_encode([
        "success" => true,
        "data" => [
            "stock" => $conteos['stock'],
            "asignado" => $conteos['asignado'],
            "usado" => $conteos['usado'],
            "total" => $total
        ],
        "message" => "Conteos obtenidos correctamente"
    ]);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en conteo de chips: " . $e->getMessage());
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Error al obtener conteos: " . $e->getMessage()
    ]);
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>