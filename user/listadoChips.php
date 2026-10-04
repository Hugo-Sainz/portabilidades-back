<?php
// Desactivar mostrar errores en pantalla para evitar contaminar el JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($data, $httpCode = 200) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Función para manejar errores
function handleError($message, $exception = null) {
    $errorData = [
        "success" => false,
        "error" => $message
    ];
    
    if ($exception) {
        error_log("Error detallado: " . $exception->getMessage() . " en " . $exception->getFile() . " línea " . $exception->getLine());
    } else {
        error_log("Error: " . $message);
    }
    
    sendJsonResponse($errorData, 500);
}

try {
    // Incluir archivo de conexión
    $conexionFile = '../conexion.php';
    if (!file_exists($conexionFile)) {
        throw new Exception("Archivo de conexión no encontrado: " . $conexionFile);
    }
    
    require_once $conexionFile;
    
} catch (Exception $e) {
    handleError("Error incluyendo archivo de conexión", $e);
}

// Verificar conexión
if (!isset($conn) || !$conn instanceof mysqli) {
    handleError("Error: Conexión a base de datos no válida");
}

if ($conn->connect_error) {
    handleError("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Obtener el ID del parámetro GET
$id = $_GET['id'] ?? null;

// Validar que se haya proporcionado el ID
if ($id === null || $id === '') {
    handleError("Error: El parámetro 'id' es requerido");
}

// Validar que el ID sea numérico
if (!is_numeric($id)) {
    handleError("Error: El parámetro 'id' debe ser un número válido");
}

try {
    // Configurar charset de la conexión
    $conn->set_charset("utf8");
    
    // Preparar la consulta con parámetros para evitar inyección SQL
    $query = "SELECT icc FROM chips WHERE id_login = ? AND estatus = 'asignado'";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    // Vincular parámetros
    $stmt->bind_param("i", $id);
    
    // Ejecutar consulta
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Obtener todos los resultados
    $iccs = [];
    
    while ($row = $result->fetch_assoc()) {
        $iccs[] = $row['icc'];
    }
    
    // Liberar recursos
    $stmt->close();
    
    // Enviar respuesta exitosa
    sendJsonResponse([
        "success" => true,
        "data" => $iccs,
        "total" => count($iccs),
        "message" => count($iccs) > 0 ? "ICCs obtenidos correctamente" : "No se encontraron ICCs asignados para este usuario"
    ]);
    
} catch (Exception $e) {
    handleError("Error al obtener ICCs asignados", $e);
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>