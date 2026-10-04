<?php
header('Content-Type: application/json'); // Para que el cliente sepa que la respuesta es en JSON
header("Access-Control-Allow-Origin: *"); // O usa el puerto específico si prefieres
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Datos de conexión a la base de datos
require_once 'conexion.php';

// Obtener datos del JSON enviado
$jsonData = json_decode(file_get_contents('php://input'), true);

// Verificar que se envíen los campos 'correo' y 'pass'
if (isset($jsonData['correo']) && isset($jsonData['pass'])) {
    $correo = $conn->real_escape_string($jsonData['correo']);
    $pass = $conn->real_escape_string($jsonData['pass']);

    // Realiza la consulta
    $sql ="SELECT * FROM login WHERE user = '$correo' ";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();

        // Comparar usando password_verify
        if (password_verify($pass, $usuario['password'])) {
            echo json_encode(['Status' => 'Success', 'Data' => $usuario]);
        } else {
            echo json_encode(['Status' => 'Error', 'Message' => 'Contraseña incorrecta']);
        }
    } else {
        echo json_encode(['Status' => 'Error', 'Message' => 'Usuario no encontrado']);
    }
} else {
    // Datos faltantes
    echo json_encode(['Status' => 'Error', 'Message' => 'Datos faltantes']);
}

// Cerrar conexión
$conn->close();
?>