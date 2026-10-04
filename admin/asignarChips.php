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

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError("Método no permitido. Use POST.");
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

try {
    // Configurar charset de la conexión
    $conn->set_charset("utf8");
    
    // Obtener datos del FormData
    $id_login = $_POST['id_login'] ?? null;
    $estatus = $_POST['estatus'] ?? null;
    $fecha_asignado = $_POST['fecha_asignado'] ?? null;
    $codigos_array = $_POST['codigos'] ?? null;
    
    // Validar datos requeridos
    if (empty($id_login)) {
        throw new Exception("El campo id_login es requerido");
    }
    
    if (empty($estatus)) {
        throw new Exception("El campo estatus es requerido");
    }
    
    if (empty($fecha_asignado)) {
        throw new Exception("El campo fecha_asignado es requerido");
    }
    
    if (empty($codigos_array)) {
        throw new Exception("El array de códigos es requerido");
    }
    
    // Si codigos viene como string (JSON), convertirlo a array
    if (is_string($codigos_array)) {
        $codigos_array = json_decode($codigos_array, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar el array de códigos JSON");
        }
    }
    
    // Validar que sea un array
    if (!is_array($codigos_array) || empty($codigos_array)) {
        throw new Exception("Los códigos deben ser un array no vacío");
    }
    
    // Validar formato de fecha
    $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_asignado);
    if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_asignado) {
        throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD");
    }
    
    // Comenzar transacción
    $conn->begin_transaction();
    
    $codigos_actualizados = [];
    $codigos_no_encontrados = [];
    $errores = [];
    
    // Preparar consulta para verificar existencia
    $stmt_check = $conn->prepare("SELECT icc FROM chips WHERE icc = ?");
    if (!$stmt_check) {
        throw new Exception("Error preparando consulta de verificación: " . $conn->error);
    }
    
    // Preparar consulta para actualizar
    $stmt_update = $conn->prepare("UPDATE chips SET id_login = ?, estatus = ?, fecha_asignado = ? WHERE icc = ?");
    if (!$stmt_update) {
        throw new Exception("Error preparando consulta de actualización: " . $conn->error);
    }
    
    // Procesar cada código
    foreach ($codigos_array as $codigo) {
        $codigo = trim($codigo);
        
        if (empty($codigo)) {
            continue; // Saltar códigos vacíos
        }
        
        try {
            // Verificar si el código existe
            $stmt_check->bind_param("s", $codigo);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows === 0) {
                $codigos_no_encontrados[] = $codigo;
                continue;
            }
            
            // Actualizar el registro
            $stmt_update->bind_param("ssss", $id_login, $estatus, $fecha_asignado, $codigo);
            
            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    $codigos_actualizados[] = $codigo;
                } else {
                    $errores[] = "Código $codigo: No se realizaron cambios (posiblemente los datos ya eran iguales)";
                }
            } else {
                $errores[] = "Código $codigo: Error al actualizar - " . $stmt_update->error;
            }
            
        } catch (Exception $e) {
            $errores[] = "Código $codigo: " . $e->getMessage();
        }
    }
    
    // Cerrar statements
    $stmt_check->close();
    $stmt_update->close();
    
    // Si hay actualizaciones exitosas, hacer commit
    if (!empty($codigos_actualizados)) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
    
    // Preparar respuesta
    $response = [
        "success" => !empty($codigos_actualizados),
        "message" => "Proceso completado",
        "resultado" => [
            "total_procesados" => count($codigos_array),
            "actualizados" => count($codigos_actualizados),
            "no_encontrados" => count($codigos_no_encontrados),
            "errores" => count($errores)
        ],
        "detalles" => [
            "codigos_actualizados" => $codigos_actualizados,
            "codigos_no_encontrados" => $codigos_no_encontrados,
            "errores" => $errores
        ],
        "datos_actualizacion" => [
            "id_login" => $id_login,
            "estatus" => $estatus,
            "fecha_asignado" => $fecha_asignado
        ]
    ];
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    handleError("Error al procesar actualización", $e);
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>