<?php
session_start();

// Zona horaria Chile
date_default_timezone_set('America/Santiago');

require 'config.php';
require 'db.php';

use Transbank\Webpay\WebpayPlus\Transaction;

// Obtener token
$token = $_POST['token_ws']
      ?? $_GET['token_ws']
      ?? null;

// pago cancelado por el usuario
if (isset($_GET['TBK_TOKEN']) && !$token) {

    $buyOrder = $_GET['TBK_ORDEN_COMPRA'] ?? null;

    if ($buyOrder) {

        // Cancelar orden
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = 'cancelled'
            WHERE buy_order = ?
        ");
        $stmt->bind_param('s', $buyOrder);
        $stmt->execute();

        // Cancelar pagos asociados
         $stmt = $conn->prepare("
            UPDATE payments
            SET status = 'cancelled'
            WHERE order_id = (
                SELECT id FROM orders WHERE buy_order = ?
            )
        ");
        $stmt->bind_param('s', $buyOrder);
        $stmt->execute();

        // Cancelar pagos asociados
        $stmt = $conn->prepare("
            UPDATE payments
            SET status = 'cancelled'
            WHERE order_id = (
                SELECT id FROM orders WHERE buy_order = ?
            )
        ");
        $stmt->bind_param('s', $buyOrder);
        $stmt->execute();
    }

    echo '<h2>❌ Pago cancelado</h2>';
    echo '<p>El pago fue cancelado por el usuario.</p>';
    exit;
}

if (!$token) {
    die('Token no recibido');
}

// Buscar pago en payments
$stmt = $conn->prepare("
    SELECT id, order_id, status
    FROM payments
    WHERE token_ws = ?
");
$stmt->bind_param('s', $token);
$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die('Pago no encontrado');
}


// Evitar doble procesamiento
if ($payment['status'] === 'approved') {
    die('Este pago ya fue procesado');
}

// Commit Webpay
try {
    $transaction = new Transaction($options);
    $response = $transaction->commit($token);
} catch (Exception $e) {

    error_log('Webpay error: ' . $e->getMessage());

    $stmt = $conn->prepare("
        UPDATE payments
        SET status = 'error',
            response = 'Error al confirmar el pago',
            response_json = ?
        WHERE id = ?
    ");
    $errorJson = json_encode(['error' => $e->getMessage()]);
    $stmt->bind_param('si', $errorJson, $payment['id']);
    $stmt->execute();

    die('Error al procesar el pago.');
}


// Extraer datos
$responseCode      = $response->getResponseCode();
$isApproved        = ($responseCode === 0);

$paymentStatus     = $isApproved ? 'approved' : 'rejected';
$orderStatus       = $isApproved ? 'paid'     : 'failed';

$authorizationCode = $response->getAuthorizationCode();
$paymentType       = $response->getPaymentTypeCode();
$installments      = $response->getInstallmentsNumber();
$amount            = $response->getAmount();

$transactionDate   = date('Y-m-d H:i:s');


// Guardar respuesta
$responseText = $isApproved ? 'Pago aprobado' : 'Pago rechazado';
$responseJson = json_encode($response, JSON_UNESCAPED_UNICODE);

// var_dump($responseJson);

// Actualizar payments
$stmt = $conn->prepare("
    UPDATE payments
    SET status = ?,
        response = ?,
        authorization_code = ?,
        payment_type = ?,
        installments = ?,
        response_code = ?,
        response_json = ?,
        transaction_date = ?
    WHERE id = ?
");

$stmt->bind_param(
    'ssssisssi',
    $paymentStatus,
    $responseText,
    $authorizationCode,
    $paymentType,
    $installments,
    $responseCode,
    $responseJson,
    $transactionDate,
    $payment['id']
);
$stmt->execute();

// Actualizar orders
$stmt = $conn->prepare("
    UPDATE orders
    SET status = ?
    WHERE id = ?
");
$stmt->bind_param('si', $orderStatus, $payment['order_id']);
$stmt->execute();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado del pago</title>
</head>
<body>

<?php if ($isApproved): ?>
    <h2>✅ Pago aprobado</h2>
    <p>Tu pago fue procesado correctamente.</p>
    <p><strong>Orden:</strong> <?= htmlspecialchars($response->getBuyOrder()) ?></p>
    <p><strong>Monto:</strong> $<?= number_format($amount, 0, ',', '.') ?></p>
    <p><strong>Código autorización:</strong> <?= htmlspecialchars($authorizationCode) ?></p>
<?php else: ?>
    <h2>❌ Pago rechazado</h2>
    <p>El pago no pudo ser procesado.</p>
<?php endif; ?>

</body>
</html>
