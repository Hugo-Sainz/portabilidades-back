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

$id = $_GET['id'] ?? null;

try {
    // Configurar charset de la conexión
    $conn->set_charset("utf8");
    
    // Consulta con LEFT JOIN para incluir usuarios sin chips
    // y subconsultas para contar chips por estatus
    $query = "
        SELECT 
            l.id,
            l.nombre,
            l.apellido,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM chips c 
                 WHERE c.id_login = l.id 
                 AND c.estatus = 'asignado'), 
                0
            ) AS chips_asignados,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM chips c 
                 WHERE c.id_login = l.id 
                 AND c.estatus = 'usado'), 
                0
            ) AS chips_usados,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM chips c 
                 WHERE c.id_login = l.id), 
                0
            ) AS total_chips
        FROM 
            login l
        WHERE
            l.id = $id
        ORDER BY 
            l.nombre, l.apellido
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error ejecutando consulta: " . $conn->error);
    }
    
    // Obtener todos los resultados
    $usuarios = [];
    $resumen = [
        'total_usuarios' => 0,
        'usuarios_con_chips' => 0,
        'usuarios_sin_chips' => 0,
        'total_chips_asignados' => 0,
        'total_chips_usados' => 0,
        'total_chips_general' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $usuario = [
            "id" => (int)$row['id'],
            "nombre" => $row['nombre'] ?? '',
            "apellido" => $row['apellido'] ?? '',
            "chips_asignados" => (int)$row['chips_asignados'],
            "chips_usados" => (int)$row['chips_usados'],
            "total_chips" => (int)$row['total_chips']
        ];
        
        $usuarios[] = $usuario;
        
        // Actualizar resumen
        $resumen['total_usuarios']++;
        $resumen['total_chips_asignados'] += $usuario['chips_asignados'];
        $resumen['total_chips_usados'] += $usuario['chips_usados'];
        $resumen['total_chips_general'] += $usuario['total_chips'];
        
        if ($usuario['total_chips'] > 0) {
            $resumen['usuarios_con_chips']++;
        } else {
            $resumen['usuarios_sin_chips']++;
        }
    }
    
    // Liberar resultado
    $result->free();
    
    // Enviar respuesta exitosa
    sendJsonResponse([
        "success" => true,
        "data" => $usuarios,
        "resumen" => $resumen,
        "message" => count($usuarios) > 0 ? "Datos obtenidos correctamente" : "No se encontraron usuarios"
    ]);
    
} catch (Exception $e) {
    handleError("Error al obtener datos de usuarios y chips", $e);
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>