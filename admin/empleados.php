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
    // Limpiar cualquier output previo
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
    
    // Log del error completo
    if ($exception) {
        error_log("Error detallado: " . $exception->getMessage() . " en " . $exception->getFile() . " línea " . $exception->getLine());
        
        // En desarrollo, puedes descomentar esta línea para ver más detalles
        // $errorData["debug"] = $exception->getMessage();
    } else {
        error_log("Error: " . $message);
    }
    
    sendJsonResponse($errorData, 500);
}

try {
    // Verificar que el archivo de conexión existe (una carpeta arriba)
    $conexionFile = '../conexion.php';
    if (!file_exists($conexionFile)) {
        throw new Exception("Archivo de conexión no encontrado: " . $conexionFile);
    }
    
    // Incluir archivo de conexión
    require_once $conexionFile;
    
} catch (Exception $e) {
    handleError("Error incluyendo archivo de conexión", $e);
}

// Verificar que la variable de conexión existe
if (!isset($conn)) {
    handleError("Variable de conexión \$conn no está definida en el archivo de conexión");
}

// Verificar que la conexión es válida
if (!$conn instanceof mysqli) {
    handleError("La variable \$conn no es una instancia válida de MySQLi");
}

// Verificar errores de conexión
if ($conn->connect_error) {
    handleError("Error de conexión a la base de datos: " . $conn->connect_error);
}

try {
    // Configurar charset de la conexión
    $conn->set_charset("utf8");
    
    // Consulta para obtener datos de chips y login
    $query = "
        SELECT 
            id,
            nombre, 
            apellido
        FROM 
            login
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error ejecutando consulta: " . $conn->error);
    }
    
    // Obtener todos los resultados
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            "nombre" => $row['nombre'] ?? '',
            "apellido" => $row['apellido'] ?? '',
            "id" => $row['id'] ?? '',
        ];
    }
    
    // Liberar resultado
    $result->free();
    
    // Enviar respuesta exitosa
    sendJsonResponse([
        "success" => true,
        "data" => $data,
        "message" => count($data) > 0 ? "Datos obtenidos correctamente" : "No se encontraron registros"
    ]);
    
} catch (Exception $e) {
    handleError("Error al obtener datos de la base de datos", $e);
    
} finally {
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>