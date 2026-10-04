<?php
    // Datos de conexión a la base de datos
    // $host = 'localhost';
    // $dbName = 'portabilidades';
    // $username = 'root';
    // $password = '';

    $host = "localhost"; // Cambia esto si es necesario
    $dbName = "u673640837_mau"; // Cambia esto si es necesario
    $username = "u673640837_hugo"; // Cambia esto si es necesario
    $password = "UpGns?0Hn"; // Cambia esto si es necesario

    // Crear conexión
    $conn = new mysqli($host, $username, $password, $dbName);

    // Verificar la conexión
    if ($conn->connect_error) {
        // echo json_encode(['Status' => 'Error', 'Message' => 'Error de conexión: ' . $conn->connect_error]);
        exit;
    }
    mysqli_report(MYSQLI_REPORT_OFF);