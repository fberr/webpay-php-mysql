<?php
session_start();
require 'db.php';

// Carrito de ejemplo
$cart = [
    ['name' => 'Producto A', 'qty' => 1, 'price' => 19990],
    ['name' => 'Producto B', 'qty' => 1, 'price' => 9990],
];

// Calcular total
$total = 0;
foreach ($cart as $item) {
    $total += $item['qty'] * $item['price'];
}

// Obtiene el identificador único de la sesión PHP actual
$sessionId = session_id();

// Crear orden (SIN buy_order)
$stmt = $conn->prepare("
    INSERT INTO orders (session_id, total_amount, status)
    VALUES (?, ?, 'pending')
");
// s = string, i = integer
$stmt->bind_param('si', $sessionId, $total);
$stmt->execute();

// obtiene el id de la orden recién creada - solo funciona con inserts
$orderId = $stmt->insert_id;

// Generar buyOrder usando orderId
$buyOrder = 'ORD-' . time() . '-' . $orderId;

// Actualizar la orden con buyOrder
$stmt = $conn->prepare("
    UPDATE orders
    SET buy_order = ?
    WHERE id = ?
");
$stmt->bind_param('si', $buyOrder, $orderId);
$stmt->execute();

// Crear items/productos de la orden
$stmtItem = $conn->prepare("
    INSERT INTO order_items
    (order_id, product_name, quantity, unit_price, total_price)
    VALUES (?, ?, ?, ?, ?)
");

foreach ($cart as $item) {
    $totalItem = $item['qty'] * $item['price'];
    $stmtItem->bind_param(
        'isiii',
        $orderId,
        $item['name'],
        $item['qty'],
        $item['price'],
        $totalItem
    );
    $stmtItem->execute();
}

// Redirigir a checkout
 header("Location: create_transaction.php?order_id=$orderId");
exit;
