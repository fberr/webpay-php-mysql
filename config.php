<?php
require __DIR__ . '/vendor/autoload.php';

use Transbank\Webpay\Options;

// Cargar variables de entorno desde .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Leer valores de Webpay desde el .env
$apiKey       = $_ENV['WB_API_KEY'] ?? '';
$commerceCode = $_ENV['WB_COMMERCE_CODE'] ?? '';
$environment  = $_ENV['WB_ENVIRONMENT'] ?? 'INTEGRACION';

// Mapear environment a constante de Options
switch (strtoupper($environment)) {
    case 'PRODUCCION':
        $env = Options::ENVIRONMENT_PRODUCTION;
        break;
    case 'INTEGRACION':
    default:
        $env = Options::ENVIRONMENT_INTEGRATION;
        break;
}

$options = new Options($apiKey, $commerceCode, $env);

