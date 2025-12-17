<?php
session_start();

require 'config.php';
require 'db.php';

use Transbank\Webpay\WebpayPlus\Transaction;

// Validar order_id
if (!isset($_GET['order_id'])) {
    die('order_id no recibido');
}

$orderId = (int) $_GET['order_id'];

// Obtener pedido desde la BD
$stmt = $conn->prepare("
    SELECT id, buy_order, total_amount, status
    FROM orders
    WHERE id = ? AND status = 'pending'
");
$stmt->bind_param('i', $orderId);
$stmt->execute();

// get_result devuelve un objeto mysqli_result.
// fetch_assoc Toma la primera fila y la devuelve como array asociativo
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Pedido no válido o ya procesado');
}

// asignamos valores a variables
$buyOrder  = $order['buy_order'];
$amount    = $order['total_amount'];
$sessionId = session_id();

// Obtener items del pedido
$stmtItems = $conn->prepare("
    SELECT product_name, quantity, unit_price, total_price
    FROM order_items
    WHERE order_id = ?
");
$stmtItems->bind_param('i', $orderId);
$stmtItems->execute();

// get_result() → devuelve un objeto resultado
// fetch_all() → obtiene todas las filas
// MYSQLI_ASSOC → cada fila es un array asociativo
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    die('El pedido no tiene productos');
}

// URL de retorno
$returnUrl = 'https://unceriferous-benjamin-endocardial.ngrok-free.dev/php-transbank/webpay_retorno.php';

// Crear transacción Webpay con las opciones definidas en config.php
$transaction = new Transaction($options);

// le pide a webpay crear una transacción de pago
$response = $transaction->create(
    $buyOrder,
    $sessionId,
    $amount,
    $returnUrl
);

// luego de crear la transacción, se obtiene el token y la URL
// Token: Identificador único de la transacción en Webpay, lo genera Webpay Transbank
$token = $response->getToken();
// URL oficial de Webpay, donde debes redirigir al usuario, cambia según ambiente
$url   = $response->getUrl();

// Registrar pago en la tabla payments
$stmt = $conn->prepare("
    INSERT INTO payments (order_id, token_ws, amount)
    VALUES (?, ?, ?)
");
$stmt->bind_param('isi', $orderId, $token, $amount);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Webpay</title>
    <style>
        table {
            border-collapse: collapse;
            width: 60%;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        tfoot td {
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>Resumen del pago</h2>

<p><strong>Orden:</strong> <?= htmlspecialchars($buyOrder) ?></p>

<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Precio unitario</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>
                    $<?= number_format($item['unit_price'], 0, ',', '.') ?>
                </td>
                <td>
                    $<?= number_format($item['total_price'], 0, ',', '.') ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" >Total</td>
            <td>
                $<?= number_format($amount, 0, ',', '.') ?>
            </td>
        </tr>
    </tfoot>
</table>

<br>

<form method="POST" action="<?= htmlspecialchars($url) ?>">
    <input type="hidden" name="token_ws" value="<?= htmlspecialchars($token) ?>">
    <button type="submit">Pagar con Webpay</button>
</form>

</body>
</html>
