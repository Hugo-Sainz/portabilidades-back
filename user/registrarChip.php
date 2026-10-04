<?php
// Iniciar buffer de salida para capturar cualquier output no deseado
ob_start();

// Limpiar cualquier output previo
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Incluir archivo de conexión desde carpeta superior
require_once '../conexion.php'; // Ajusta la ruta según el nombre de tu archivo

// Verificar que la conexión no tenga output
if ($conn->connect_error) {
    echo json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]);
    exit();
}

try {
    // Obtener los datos JSON del request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validar que se recibieron los datos
    if (!$data) {
        throw new Exception('No se recibieron datos válidos');
    }
    
    // Validar campos requeridos
    $requiredFields = [
        'numTelefono', 'nip', 'nombreCliente', 'curpCliente', 
        'icc', 'nombrePromotor', 'numPortabilidad', 
        'interconexion', 'capturada', 'id_login'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // 1. Verificar si el ICC ya existe en data_porta
    $checkDataPortaSql = "SELECT id FROM data_porta WHERE icc = ?";
    $checkDataPortaStmt = $conn->prepare($checkDataPortaSql);
    
    if (!$checkDataPortaStmt) {
        throw new Exception('Error al preparar consulta de verificación data_porta: ' . $conn->error);
    }
    
    $checkDataPortaStmt->bind_param('s', $data['icc']);
    $checkDataPortaStmt->execute();
    $resultDataPorta = $checkDataPortaStmt->get_result();
    
    if ($resultDataPorta->num_rows > 0) {
        $checkDataPortaStmt->close();
        $checkDataPortaStmt = null;
        throw new Exception('Este ICC ya ha sido registrado previamente en data_porta');
    }
    
    $checkDataPortaStmt->close();
    $checkDataPortaStmt = null;
    
    // 2. Verificar si el ICC existe en la tabla chips
    $checkChipsSql = "SELECT id, estatus FROM chips WHERE icc = ? AND id_login = ?";
    $checkChipsStmt = $conn->prepare($checkChipsSql);
    
    if (!$checkChipsStmt) {
        throw new Exception('Error al preparar consulta de verificación chips: ' . $conn->error);
    }
    
    $checkChipsStmt->bind_param('ss', $data['icc'], $data['id_login']);
    $checkChipsStmt->execute();
    $resultChips = $checkChipsStmt->get_result();
    
    if ($resultChips->num_rows === 0) {
        $checkChipsStmt->close();
        $checkChipsStmt = null;
        throw new Exception('El ICC no existe en la tabla chips para este usuario');
    }
    
    // Verificar el estatus del chip
    $chipData = $resultChips->fetch_assoc();
    if ($chipData['estatus'] === 'usado') {
        $checkChipsStmt->close();
        $checkChipsStmt = null;
        throw new Exception('Este chip ya ha sido usado previamente');
    }
    
    $checkChipsStmt->close();
    $checkChipsStmt = null;
    
    // Preparar la consulta SQL
    $sql = "INSERT INTO data_porta (
        num_telefono, 
        nip, 
        nombreCliente, 
        curpCliente, 
        icc, 
        nombrePromotor, 
        numPorta, 
        interconexion, 
        capturada, 
        id_login
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Preparar y ejecutar la consulta
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    // Bind parameters (todos son strings)
    $stmt->bind_param(
        'ssssssssss', 
        $data['numTelefono'],
        $data['nip'],
        $data['nombreCliente'],
        $data['curpCliente'],
        $data['icc'],
        $data['nombrePromotor'],
        $data['numPortabilidad'],
        $data['interconexion'],
        $data['capturada'],
        $data['id_login']
    );
    
    // Ejecutar la consulta
    if ($stmt->execute()) {
        $insertedId = $conn->insert_id;
        
        // Cerrar el primer statement
        $stmt->close();
        $stmt = null; // Marcar como null para evitar doble cierre
        
        // Ahora actualizar la tabla chips
        $updateSql = "UPDATE chips SET estatus = 'usado', fecha_usado = NOW() WHERE icc = ? AND id_login = ?";
        $updateStmt = $conn->prepare($updateSql);
        
        if (!$updateStmt) {
            throw new Exception('Error al preparar la consulta de actualización: ' . $conn->error);
        }
        
        $updateStmt->bind_param('ss', $data['icc'], $data['id_login']);
        
        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            
            // Cerrar el segundo statement
            $updateStmt->close();
            $updateStmt = null; // Marcar como null
            
            echo json_encode([
                'success' => true,
                'message' => 'Datos guardados correctamente y chip actualizado',
                'id' => $insertedId,
                'chip_updated' => $affectedRows > 0 ? true : false,
                'affected_rows' => $affectedRows
            ]);
        } else {
            $updateError = $updateStmt->error;
            $updateStmt->close();
            $updateStmt = null;
            throw new Exception('Error al actualizar el chip: ' . $updateError);
        }
        
    } else {
        $insertError = $stmt->error;
        $stmt->close();
        $stmt = null;
        throw new Exception('Error al insertar los datos: ' . $insertError);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} finally {
    // Cerrar statements solo si existen y no son null
    if (isset($checkDataPortaStmt) && $checkDataPortaStmt instanceof mysqli_stmt) {
        $checkDataPortaStmt->close();
    }
    if (isset($checkChipsStmt) && $checkChipsStmt instanceof mysqli_stmt) {
        $checkChipsStmt->close();
    }
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($updateStmt) && $updateStmt instanceof mysqli_stmt) {
        $updateStmt->close();
    }
    // Cerrar conexión si existe
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>