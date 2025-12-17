<?php
// Conexión a MySQL usando mysqli (PHP puro)
require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Leer configuración desde variables de entorno
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'php-transbank';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die('Error de conexión a la BD: ' . $conn->connect_error);
}

// Usar charset utf8mb4
$conn->set_charset('utf8mb4');
