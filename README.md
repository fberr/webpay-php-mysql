# 🛒 Proyecto Demo – Integración Webpay Plus con PHP - transbank-sdk: 5.0

Este proyecto es una **implementación educativa y funcional** de un flujo de pagos con **Transbank Webpay Plus**, desarrollada en **PHP puro** y con persistencia en base de datos MySQL.

El objetivo es demostrar **cómo crear órdenes, iniciar pagos, procesar el retorno de Webpay y mantener consistencia entre órdenes y pagos**, siguiendo buenas prácticas usadas en ecommerce reales.
Se recomienda utilizar ngrok para pruebas en servidores locales.

---

## 📂 Estructura del proyecto

El proyecto está compuesto por los siguientes archivos principales:

```text
/
├── index.php
├── create_order.php
├── create_transaction.php
├── webpay_retorno.php
├── config.php
└── db.php
```

---

## 🔁 Flujo general del sistema

1️⃣ El usuario ingresa al sitio
2️⃣ Se crea una orden en el sistema
3️⃣ Se inicia una transacción Webpay
4️⃣ El usuario es redirigido a Webpay
5️⃣ Webpay retorna el resultado del pago
6️⃣ El sistema confirma y registra el pago

---

## 📄 Descripción de cada archivo

---

### 🏠 `index.php`

Es el **punto de entrada del sistema**.

* Inicia la sesión del usuario
* Simula la página principal de una tienda
* Permite iniciar el flujo de compra

```php
session_start();
```

Desde aquí se accede a `create_order.php`, simulando la acción de “Comprar”.

---

### 🧾 `create_order.php`

Este archivo representa el **checkout inicial**.

Responsabilidades:

* Simular un carrito de compras
* Calcular el total de la orden
* Crear un registro en la tabla `orders`
* Crear los registros en `order_items`
* Generar un `buyOrder` único
* Redirigir al inicio del pago

Estados iniciales:

* `orders.status = pending`

Este archivo **no interactúa con Webpay**, solo prepara la orden.

---

### 💳 `create_transaction.php`

Este archivo inicia la **transacción de pago con Webpay**.

Responsabilidades:

* Recibir el `order_id`
* Validar que la orden exista y esté pendiente
* Crear la transacción Webpay (`create`)
* Obtener el `token` y la `url`
* Registrar el pago en la tabla `payments`
* Mostrar un resumen del pago
* Redirigir al usuario a Webpay

Estados iniciales:

* `payments.status = initialized`

---

### 🔄 `webpay_retorno.php`

Este archivo maneja el **retorno desde Webpay** y es el más crítico del sistema.

Responsabilidades:

* Detectar si el pago fue:

  * Aprobado
  * Rechazado
  * Cancelado por el usuario
* Ejecutar `commit(token)` cuando corresponde
* Evitar doble procesamiento
* Actualizar tablas `payments` y `orders`
* Guardar datos relevantes del pago:

  * Código de autorización
  * Tipo de pago
  * Cuotas
  * Fecha de transacción
  * Respuesta completa de Webpay

Estados finales posibles:

| Escenario      | orders.status | payments.status |
| -------------- | ------------- | --------------- |
| Pago aprobado  | paid          | approved        |
| Pago rechazado | failed        | rejected        |
| Pago cancelado | cancelled     | cancelled       |
| Error técnico  | pending       | error           |

---

### ⚙️ `config.php`

Archivo de **configuración de Webpay**.

Contiene:

* Credenciales del comercio
* Certificados (según ambiente)
* Configuración de ambiente (integración / producción)
* Opciones usadas por el SDK de Transbank

Este archivo **no contiene lógica de negocio**.

---

### 🗄️ `db.php`

Archivo de **conexión a la base de datos**.

Responsabilidades:

* Crear la conexión MySQL (`mysqli`)
* Centralizar credenciales de BD
* Reutilizar la conexión en todo el proyecto

---

## 🧠 Conceptos clave del diseño

* **Las órdenes y los pagos son entidades distintas**
* Una orden puede tener múltiples intentos de pago
* Nunca se eliminan registros, solo se actualizan estados
* El `buyOrder` es único y trazable
* El sistema evita confirmaciones duplicadas
* La cancelación del usuario se maneja explícitamente

---


## 🎯 Objetivo del proyecto

Este proyecto sirve como:

* Base para un ecommerce real
* Ejemplo educativo de integración Webpay
* Referencia para manejo de pagos en PHP
* Punto de partida para agregar:

  * Carrito real
  * Usuarios
  * Historial de pedidos
  * Reintentos de pago

---

## 🔑 Datos obligatorios para iniciar una transacción

Antes de redirigir al usuario a Webpay, es obligatorio definir los siguientes valores:

```php
$buyOrder  = 'ORD-' . time();
$sessionId = session_id();
$amount    = 19990;
$returnUrl = 'http://localhost:8888/php-transbank/webpay_retorno.php';
```

### 🧾 Descripción de cada variable

| Variable      | Descripción                                                                                                                    |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| **buyOrder**  | Identificador único de la orden. Es generado por el desarrollador y permite asociar el pago con una orden interna del sistema. |
| **sessionId** | Identificador de sesión generado por PHP. Permite relacionar la transacción con la sesión del usuario.                         |
| **amount**    | Monto total a cobrar, expresado como número entero en pesos chilenos (CLP), sin decimales.                                     |
| **returnUrl** | URL a la que Webpay redirigirá al usuario una vez finalizado el proceso de pago (aprobado, rechazado o cancelado).             |

> ⚠️ **Importante:**
> El `buyOrder` debe ser **único por transacción** y no puede repetirse.

---

## 🔧 Creación del cliente Webpay

El cliente de Webpay se inicializa usando las opciones configuradas previamente en el archivo `config.php`:

```php
$transaction = new Transaction($options);
```

---

## 🚀 Creación de la transacción de pago

Una vez definidos los datos, se solicita a Webpay la creación de la transacción:

```php
$response = $transaction->create(
    $buyOrder,
    $sessionId,
    $amount,
    $returnUrl
);
```

Si la solicitud es exitosa, Webpay responderá con:

* Un **token de transacción**
* Una **URL oficial de Webpay**

---

## 🔐 Token y URL de Webpay

```php
$token = $response->getToken();
$url   = $response->getUrl();
```

### ¿Qué es el token?

* Es un identificador único generado por Webpay.
* Representa la transacción creada.
* Debe enviarse a Webpay para que el usuario pueda realizar el pago.

### ¿Qué es la URL?

* Es la URL oficial de Webpay.
* El usuario debe ser redirigido a esta URL para completar el pago.

---

## 🔄 Flujo completo del proceso Webpay

El flujo estándar de una transacción Webpay Plus es el siguiente:

1️⃣ **Tu sistema** crea la transacción (`create`)
2️⃣ **Webpay** responde con:

* `token`
* `url`
  3️⃣ **El usuario** es redirigido a la URL de Webpay
  4️⃣ **El usuario realiza el pago**
  5️⃣ **Webpay redirige de vuelta** a tu `returnUrl` enviando `token_ws`
  6️⃣ **Tu sistema confirma el pago** ejecutando `commit(token)`

---

## ✅ Resultado final

* La orden queda asociada a un pago
* El sistema puede determinar si el pago fue:

  * Aprobado
  * Rechazado
  * Cancelado por el usuario

Este flujo es el **estándar oficial recomendado por Transbank** para integraciones Webpay Plus.

# 🗄️ Estructura de Base de Datos

Este proyecto utiliza **MySQL** para persistir la información relacionada con **órdenes, productos y pagos Webpay**.


---

## 📌 Tablas principales

El sistema se basa en tres tablas principales:

* `orders`
* `order_items`
* `payments`

---

## 📦 Tabla `orders`

Almacena la información principal de cada compra realizada en el sistema.

```sql
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buy_order VARCHAR(100) NOT NULL UNIQUE,
    session_id VARCHAR(100) NOT NULL,
    total_amount INT NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### 📄 Campos

| Campo          | Descripción                               |
| -------------- | ----------------------------------------- |
| `id`           | Identificador interno de la orden         |
| `buy_order`    | Código único de la orden enviado a Webpay |
| `session_id`   | Identificador de sesión del usuario       |
| `total_amount` | Monto total de la compra                  |
| `status`       | Estado de la orden                        |
| `created_at`   | Fecha de creación                         |

---

## 🧾 Tabla `order_items`

Contiene el detalle de los productos asociados a una orden.

```sql
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    unit_price INT NOT NULL,
    total_price INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

### 📄 Campos

| Campo          | Descripción            |
| -------------- | ---------------------- |
| `id`           | Identificador del ítem |
| `order_id`     | Relación con la orden  |
| `product_name` | Nombre del producto    |
| `quantity`     | Cantidad               |
| `unit_price`   | Precio unitario        |
| `total_price`  | Precio total del ítem  |

---

## 💳 Tabla `payments`

Registra cada intento de pago realizado para una orden.

```sql
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    token_ws VARCHAR(100),
    amount INT NOT NULL,
    status ENUM(
        'initialized',
        'approved',
        'rejected',
        'cancelled',
        'error'
    ) DEFAULT 'initialized',
    response VARCHAR(255),
    authorization_code VARCHAR(50),
    payment_type VARCHAR(20),
    installments INT,
    response_code INT,
    response_json JSON,
    transaction_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);
```

### 📄 Campos

| Campo                | Descripción                    |
| -------------------- | ------------------------------ |
| `id`                 | Identificador del pago         |
| `order_id`           | Orden asociada                 |
| `token_ws`           | Token generado por Webpay      |
| `amount`             | Monto del pago                 |
| `status`             | Estado del pago                |
| `response`           | Mensaje resumido del resultado |
| `authorization_code` | Código de autorización Webpay  |
| `payment_type`       | Tipo de pago (VD, VN, etc.)    |
| `installments`       | Número de cuotas               |
| `response_code`      | Código de respuesta Webpay     |
| `response_json`      | Respuesta completa de Webpay   |
| `transaction_date`   | Fecha del pago                 |
| `created_at`         | Fecha de creación              |

---

## 🔁 Relación entre tablas

```text
orders (1) ────< order_items (N)
orders (1) ────< payments (N)
```

* Una orden puede tener **varios productos**
* Una orden puede tener **múltiples intentos de pago**
* Un pago siempre pertenece a una sola orden

---

## 📊 Estados del sistema

### 🟦 Estados de `orders`

| Estado      | Significado                      |
| ----------- | -------------------------------- |
| `pending`   | Orden creada, pago no finalizado |
| `paid`      | Pago aprobado                    |
| `failed`    | Pago rechazado                   |
| `cancelled` | Pago cancelado por el usuario    |

---

### 🟨 Estados de `payments`

| Estado        | Significado                   |
| ------------- | ----------------------------- |
| `initialized` | Pago creado, sin confirmar    |
| `approved`    | Pago aprobado                 |
| `rejected`    | Pago rechazado                |
| `cancelled`   | Pago cancelado por el usuario |
| `error`       | Error técnico                 |

---

## 🧠 Decisiones de diseño

* No se eliminan registros (auditoría completa)
* Los estados reflejan la realidad del negocio
* `buy_order` es único y trazable
* `response_json` permite depuración y análisis
* La separación entre órdenes y pagos permite reintentos

---