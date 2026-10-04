<?php
// Encabezados para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: DELETE, POST");
header("Content-Type: application/json");

// Manejo de preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Conexión a base de datos
include_once '../conexion.php'; // ajusta la ruta si es necesario

// Obtener datos del cuerpo de la petición
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se reciba el ID
if (isset($data['id']) && !empty($data['id'])) {
    $id = $data['id'];

    // Consulta preparada para eliminar el usuario por ID
    $stmt = $conn->prepare("DELETE FROM login WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Verificar si se eliminó algún registro
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "Usuario eliminado exitosamente."]);
        } else {
            echo json_encode(["success" => false, "message" => "No se encontró un usuario con ese ID."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error al eliminar el usuario."]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "ID no proporcionado o inválido."]);
}

$conn->close();
?>