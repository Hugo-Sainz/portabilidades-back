<?php
// Encabezados para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST");
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

// Validar campos necesarios
if (
    isset($data['usuario'], $data['password'], $data['nombre'], $data['apellido'], $data['rol'], $data['numJefe'], $data['id'])
) {
    $usuario   = $data['usuario'];
    $password  = password_hash($data['password'], PASSWORD_DEFAULT); // encriptar contraseña
    $nombre    = $data['nombre'];
    $apellido  = $data['apellido'];
    $rol       = $data['rol'];
    $numJefe   = $data['numJefe'];
    $id        = $data['id'];

    // Consulta preparada para insertar datos
    $stmt = $conn->prepare("INSERT INTO login (user, password, nombre, apellido, rol, num_jefe, id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $usuario, $password, $nombre, $apellido, $rol, $numJefe, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Usuario registrado exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al insertar el usuario."]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Datos incompletos."]);
}

$conn->close();
?>
