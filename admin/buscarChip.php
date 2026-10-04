<?php
// Desactivar mostrar errores en pantalla para evitar contaminar el JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
        "error" => $message,
        "encontrado" => false
    ];
    
    if ($exception) {
        error_log("Error detallado: " . $exception->getMessage() . " en " . $exception->getFile() . " línea " . $exception->getLine());
    } else {
        error_log("Error: " . $message);
    }
    
    sendJsonResponse($errorData, 500);
}

// Verificar que sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    handleError("Método no permitido. Use GET.");
}

try {
    // Incluir archivo de conexión
    $conexionFile = '../conexion.php'; // Ajusta la ruta según tu estructura
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
    
    // Obtener el parámetro query de la URL
    $query = $_GET['query'] ?? '';
    
    // Validar que se proporcionó un ICC para buscar
    if (empty($query)) {
        sendJsonResponse([
            "success" => false,
            "error" => "Debe proporcionar un código ICC para buscar",
            "encontrado" => false,
            "mensaje" => "Código ICC requerido"
        ], 400);
    }
    
    // Limpiar el código ICC (quitar espacios)
    $icc_buscar = trim($query);
    
    // Consulta para buscar el ICC con información del usuario asignado
    $sql = "
        SELECT 
            c.id,
            c.icc,
            c.id_login,
            c.estatus,
            c.fecha_asignado,
            c.fecha_usado,
            l.nombre,
            l.apellido
        FROM 
            chips c
        LEFT JOIN 
            login l ON c.id_login = l.id
        WHERE 
            c.icc = ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $icc_buscar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // El ICC no existe en la base de datos
        sendJsonResponse([
            "success" => true,
            "encontrado" => false,
            "icc_buscado" => $icc_buscar,
            "mensaje" => "El chip con ICC '$icc_buscar' no está registrado en el sistema",
            "estatus_descripcion" => "No registrado"
        ]);
    }
    
    // El ICC existe, obtener sus datos
    $chip = $result->fetch_assoc();
    $stmt->close();
    
    // Determinar el mensaje según el estatus
    $mensaje = "";
    $estatus_descripcion = "";
    
    switch (strtolower($chip['estatus'])) {
        case 'stock':
            $mensaje = "El chip está disponible en stock";
            $estatus_descripcion = "Disponible en stock";
            break;
            
        case 'asignado':
            if ($chip['nombre'] && $chip['apellido']) {
                $mensaje = "El chip está asignado a: " . $chip['nombre'] . " " . $chip['apellido'];
                $estatus_descripcion = "Asignado a " . $chip['nombre'] . " " . $chip['apellido'];
            } else {
                $mensaje = "El chip está asignado pero no se encontró información del usuario";
                $estatus_descripcion = "Asignado (usuario no encontrado)";
            }
            break;
            
        case 'usado':
            if ($chip['nombre'] && $chip['apellido']) {
                $mensaje = "El chip ha sido usado por: " . $chip['nombre'] . " " . $chip['apellido'];
                $estatus_descripcion = "Usado por " . $chip['nombre'] . " " . $chip['apellido'];
            } else {
                $mensaje = "El chip ha sido usado pero no se encontró información del usuario";
                $estatus_descripcion = "Usado (usuario no encontrado)";
            }
            break;
            
        default:
            $mensaje = "El chip tiene un estatus desconocido: " . $chip['estatus'];
            $estatus_descripcion = "Estatus: " . $chip['estatus'];
            break;
    }
    
    // Preparar respuesta completa
    $response = [
        "success" => true,
        "encontrado" => true,
        "icc_buscado" => $icc_buscar,
        "mensaje" => $mensaje,
        "estatus_descripcion" => $estatus_descripcion,
        "datos_chip" => [
            "id" => (int)$chip['id'],
            "icc" => $chip['icc'],
            "estatus" => $chip['estatus'],
            "fecha_asignado" => $chip['fecha_asignado'],
            "fecha_usado" => $chip['fecha_usado']
        ]
    ];
    
    // Agregar datos del usuario si existe
    if ($chip['id_login'] && $chip['nombre']) {
        $response["usuario_asignado"] = [
            "id" => (int)$chip['id_login'],
            "nombre" => $chip['nombre'],
            "apellido" => $chip['apellido'],
            "nombre_completo" => $chip['nombre'] . " " . $chip['apellido']
        ];
    }
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    handleError("Error al buscar el chip", $e);
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>