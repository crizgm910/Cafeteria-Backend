# Contrato API del MVP TGR

Base local: `http://127.0.0.1:8000/api`. Todas las respuestas son JSON. Las rutas administrativas requieren `Authorization: Bearer <token>` de Laravel Sanctum; ningún navegador se conecta a Supabase.

## Encabezados comunes

- `Accept: application/json`.
- `X-Correlation-ID`: UUID opcional; Laravel genera y devuelve uno si falta.
- `Idempotency-Key`: obligatorio en checkout, venta POS, cobro de pedido público y reservación.
- Códigos comunes: `401` sin sesión, `403` sin permiso, `409` conflicto idempotente, `422` validación/regla de dominio y `429` límite de tasa.

## Canal público

- `GET /menu`: categorías y productos activos, con receta y complementos activos.
- `POST /checkout`: invitado, nombre y teléfono obligatorios, correo opcional, `dine_in|takeout`, método único `pay_at_pickup`. Laravel calcula total, descuenta receta y crea pago pendiente.
- `GET /orders/{folio}/status?token=...`: estado mínimo; un folio sin token válido responde como no encontrado.
- `GET /reservation-availability`: áreas públicas y horarios con capacidad real para fecha y personas.
- `POST /reservations`: recibe una solicitud idempotente, asigna la mesa compatible más pequeña y evita solapamientos dentro de una transacción. La respuesta distingue solicitud recibida de confirmación.

## Personal

- `POST /login`, `GET /user`, `POST /logout`.
- Pedidos/KDS: `GET /tickets`, `PATCH /tickets/{id}/status`.
- Cobro de pedido público: `POST /tickets/{id}/collect-payment` con caja abierta.
- POS: `POST /pos/sales` con efectivo o terminal externa registrada.
- Caja: `GET /cash-register/current`, `POST /open`, `/movements`, `/close`.
- Catálogo: recursos `categories`, `products`, `add-ons`; configuración de receta y complementos bajo `/products/{id}`.
- Inventario: `ingredients` e `inventory/transactions`.
- Reservaciones: `service-areas`, `dining-tables`, `reservation-schedules`, `reservation-blocks`, reasignación y máquina de estados.
- Gobierno: `users`, `roles`, `reports/daily`, `audit-events`.

## Evidencia de pago

- `awaiting_collection`: intención pública, no es pago.
- `cashier_confirmation`: efectivo contado por personal con caja abierta.
- `external_terminal_manual`: referencia capturada de una terminal externa; no equivale a verificación automática de proveedor.
- `legacy_unverified`: dato histórico preservado, excluido de cobros conciliados.

Los comprobantes emitidos por la aplicación son no fiscales. CFDI y pasarelas en línea quedan como posibles actualizaciones.
