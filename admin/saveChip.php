<?php
// IMPORTANTE: No mostrar errores en output para evitar contaminar el JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Log errores a archivo en lugar de mostrarlos
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_errors.log');

// Buffer de salida para capturar cualquier output no deseado
ob_start();

// Habilita CORS y define el tipo de contenido
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean(); // Limpiar buffer antes de enviar respuesta
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

// Obtener datos desde JSON
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Log para debugging
// file_put_contents("log.txt", "Input raw: " . $input . "\n", FILE_APPEND);
// file_put_contents("log.txt", "Data decoded: " . print_r($data, true) . "\n", FILE_APPEND);

// Verificar si los datos fueron decodificados correctamente
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Error al decodificar JSON: " . json_last_error_msg()
    ]);
    exit();
}

$codigos = $data["codigos"] ?? [];

if (empty($codigos)) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No se recibieron códigos"
    ]);
    exit();
}

$respuestas = [];
$errores = [];

foreach ($codigos as $index => $codigo) {
    if (!empty($codigo)) {
        $stmt = $conn->prepare("INSERT INTO chips (icc, estatus) VALUES (?, 'stock')");
        
        if (!$stmt) {
            $errores[] = ["codigo" => $codigo, "error" => "Error preparando consulta: " . $conn->error];
            continue;
        }
        
        $stmt->bind_param("s", $codigo);
        
        if ($stmt->execute()) {
            $respuestas[] = ["codigo" => $codigo, "status" => "insertado", "id" => $conn->insert_id];
        } else {
            $errores[] = ["codigo" => $codigo, "error" => "Error ejecutando consulta: " . $stmt->error];
        }
        
        $stmt->close();
    } else {
        $errores[] = ["index" => $index, "error" => "Código vacío"];
    }
}

// Limpiar cualquier output buffer antes de enviar JSON
ob_clean();

// Respuesta en JSON
echo json_encode([
    "success" => count($respuestas) > 0,
    "insertados" => count($respuestas),
    "errores" => count($errores),
    "resultados" => $respuestas,
    "errores_detalle" => $errores
]);
?>