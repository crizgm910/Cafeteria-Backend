# Pendientes del backend y base de datos

> Documento histórico de la auditoría del 11 de julio. El estado vigente se controla en `EJECUCION_PLAN_TGR.md`; varios elementos marcados aquí como pendientes ya fueron implementados y probados.

Fecha de revisión estática: 11 de julio de 2026

Alcance: API Laravel, controladores, modelos, migraciones, autenticación y contratos consumidos por los frontends.

> MySQL/XAMPP estaba apagado durante esta revisión. Los puntos de código se comprobaron estáticamente y con PHPUnit usando SQLite en memoria. La sección final contiene las verificaciones pendientes contra la base MySQL real.

## P0 — Integridad de inventario, pedidos y pagos

- [x] **Impedir cantidades cero o negativas en movimientos de inventario.**
  - `InventoryTransactionController::store()` acepta cualquier valor `numeric`.
  - Una venta o merma negativa cambia de signo y puede aumentar el stock.
  - Exigir una cantidad mayor que cero para venta, merma y reabastecimiento; definir por separado la semántica de ajustes.

- [x] **Garantizar rollback en todas las salidas de una transacción.**
  - El controlador retorna `400` desde dentro de una transacción cuando el stock queda negativo, sin ejecutar `DB::rollBack()`.
  - Usar `DB::transaction()` y lanzar una excepción de dominio en lugar de retornar desde el callback.

- [x] **Restaurar inventario al cancelar un pedido.**
  - Cambiar el ticket a `cancelled` no compensa los movimientos `sale`.
  - Registrar movimientos inversos, evitar doble restitución y hacerlo dentro de una transacción.

- [x] **Implementar una máquina de estados para tickets.**
  - Actualmente cualquier estado permitido puede reemplazar al anterior.
  - Definir transiciones válidas, por ejemplo `pending → paid/preparing → ready → delivered` y reglas de cancelación.
  - Bloquear cambios sobre pedidos entregados o cancelados salvo flujo administrativo explícito.

- [ ] **Separar creación del pedido y aprobación del pago.**
  - Todo método diferente de efectivo se registra como `card_terminal` y `approved` sin pasarela ni terminal.
  - Para tarjeta, crear pago `pending`, procesar con proveedor y aprobar solo mediante confirmación verificable/webhook.

- [x] **Validar estrictamente `payment_method`.**
  - Actualmente acepta cualquier cadena.
  - Usar una lista cerrada coherente con `payments.gateway_provider`.

- [x] **Validar que el producto esté activo durante checkout.**
  - `exists:products,id` también admite productos desactivados.

- [x] **Validar que cada complemento pertenezca al producto.**
  - El cliente puede enviar el ID de cualquier `add_on` existente.
  - Rechazar complementos no asociados en `product_add_ons`.

- [x] **Evitar complementos duplicados en el mismo artículo.**
  - Validar `items.*.add_ons.*` como `distinct` y considerar una restricción única en `ticket_item_add_ons`.

- [x] **Persistir correctamente subtotal, impuestos y descuentos.**
  - Checkout solo actualiza `tickets.total`; `subtotal`, `tax` y `discount` quedan en cero.
  - Definir si los precios incluyen IVA y calcular/importar los campos fiscales de forma consistente.

- [x] **No filtrar excepciones internas al cliente.**
  - Checkout devuelve `$e->getMessage()` y `APP_DEBUG=true` expone trazas en desarrollo.
  - Registrar el detalle internamente y devolver errores de dominio seguros con códigos estables.

## P0 — Reservas y esquema

- [x] **Alinear el estado `completed` de reservas.**
  - El controlador acepta `completed`, pero el enum MySQL solo contiene `pending`, `approved`, `ready` y `cancelled`.
  - Crear una migración que añada el estado o retirar la transición.

- [x] **Cambiar fecha y hora de reservas a tipos nativos.**
  - `date` y `time` son cadenas y el comentario de la migración no coincide con el formato enviado por el frontend.
  - Migrar a `date` y `time`, añadiendo índices adecuados.

- [x] **Validar fechas y horarios de reserva en el servidor.**
  - Rechazar fechas pasadas, formatos inválidos, horarios cerrados y número de personas fuera de capacidad.

- [ ] **Implementar disponibilidad y prevención de sobreventa de mesas.**
  - No existe modelo de mesas, capacidad por horario ni control de reservas simultáneas.

## P1 — Funciones backend faltantes

- [ ] **CRUD de categorías.**
  - Solo existe `GET /api/categories`; faltan crear, editar, activar/desactivar y validar eliminación.

- [x] **Administración de recetas por producto.**
  - Existen endpoints y panel para consultar/sincronizar ingredientes y cantidades.
  - El recetario maestro cubre los 20 productos y el menú excluye artículos sin receta o sin stock para una porción.

- [ ] **CRUD de complementos y asociación con productos.**
  - Existen modelos y tablas `add_ons`/`product_add_ons`, pero no hay controlador ni rutas administrativas.

- [ ] **CRUD de estaciones de cocina.**
  - Existen tabla y modelo `kitchen_stations`, pero no hay endpoints.
  - El CRUD de productos tampoco permite asignar `kitchen_station_id`.

- [ ] **Administración de mermas con motivo y responsable.**
  - Existe `wastes`, pero no hay controlador, rutas ni relación con `User`.
  - El movimiento genérico `waste` no guarda razón, notas ni quién reportó.

- [ ] **Historial paginado de movimientos de inventario.**
  - `InventoryTransactionController::index()` existe, pero no tiene ruta.
  - Añadir filtros por insumo, tipo, fecha, referencia y usuario.

- [ ] **Endpoints de pagos y reembolsos.**
  - No hay consulta detallada, conciliación, devolución ni webhook de proveedor.

- [ ] **Flujo de facturación.**
  - Existen tabla y modelo `invoices`, pero no hay controlador, validación fiscal, timbrado o cancelación.

- [ ] **Datos completos del cliente y entrega.**
  - Checkout solo guarda `customer_name`; teléfono, correo y dirección capturados en la landing no tienen columnas ni validación.
  - Añadir entidad/datos de contacto con una política clara de privacidad y retención.

- [ ] **Detalle y seguimiento público del pedido.**
  - Checkout devuelve UUID y total, pero no `ticket_number` ni un mecanismo seguro de consulta de estado.

- [ ] **Actualización separada del estado KDS por artículo/estación.**
  - `ticket_items.kds_status` existe, pero el API solo cambia el estado global del ticket.

- [ ] **Reportes y KPIs agregados.**
  - El panel descarga todos los tickets para calcular ventas.
  - Añadir endpoint de resumen por periodo, estado, origen, forma de pago y producto.

- [ ] **Paginación y búsqueda en colecciones administrativas.**
  - Tickets, productos, ingredientes, reservas y categorías usan `get()` sin límites.

## P1 — Autorización y seguridad

- [ ] **Diseñar roles y permisos.**
  - `users` no tiene rol y no existen Policies/Gates.
  - Definir al menos administrador, gerente, caja, cocina y consulta; proteger precios, inventario, usuarios y cancelaciones.

- [ ] **Asignar abilities a tokens Sanctum.**
  - Login crea siempre `admin_token` sin abilities ni expiración explícita.

- [ ] **Añadir rate limiting.**
  - Aplicar límites específicos a login, checkout y reservas públicas.

- [ ] **Implementar expiración y gestión de sesiones.**
  - Añadir expiración de tokens, listado/revocación de sesiones y limpieza de tokens antiguos.

- [ ] **Definir CORS por ambiente.**
  - Permitir únicamente los orígenes reales de Admin, Landing y Kiosco en producción.

- [ ] **Sustituir `$guarded = []` por `$fillable` explícito.**
  - Reducir riesgo de asignación masiva accidental en modelos de dominio.

- [ ] **Usar Form Requests, Policies y API Resources.**
  - No existen estas capas; validación, autorización, serialización y lógica están concentradas en controladores.

- [ ] **Normalizar el formato JSON de éxito y error.**
  - Actualmente se mezclan `message`, `error`, modelos directos y distintas estructuras.
  - Definir contrato estable y códigos de error de dominio.

## P1 — Consistencia y auditoría

- [ ] **Auditar cambios de inventario realizados desde CRUD.**
  - `IngredientController::update()` permite editar `current_stock` directamente sin crear `inventory_transactions`.
  - Todo cambio de stock debe pasar por el servicio transaccional.

- [ ] **Registrar el usuario responsable de movimientos y estados.**
  - Los movimientos no tienen `user_id`; actividades guardan un nombre de texto, no una FK auditable.

- [x] **Añadir idempotencia al checkout.**
  - La landing envía `Idempotency-Key` y Laravel conserva una única orden ante reintentos concurrentes.

- [ ] **Garantizar unicidad robusta de `ticket_number`.**
  - Se generan seis caracteres aleatorios y se depende de la restricción única; manejar colisiones con reintento controlado.

- [ ] **Definir precisión y reglas para unidades.**
  - Stock y recetas admiten dos decimales; confirmar si gramos/mililitros requieren otra precisión y evitar mezclar unidades incompatibles.

- [ ] **Añadir índices para consultas frecuentes.**
  - Revisar índices sobre estados/fechas de tickets, reservas, movimientos y pagos.

- [ ] **Corregir relaciones incompletas.**
  - Añadir relaciones faltantes como recetas inversas, usuario de merma, actividades por usuario y referencias de movimientos.

- [ ] **Manejar eliminaciones restringidas con respuestas 409.**
  - Eliminar un insumo referenciado puede producir una excepción SQL genérica.

## P2 — Pruebas y arquitectura

- [ ] **Crear un servicio transaccional de checkout.**
  - Separar validación, cálculo de precio, inventario, pago y creación de ticket del controlador HTTP.

- [ ] **Crear un servicio único de inventario.**
  - Centralizar bloqueos, movimientos, compensaciones, stock resultante y auditoría.

- [ ] **Ampliar pruebas automatizadas.**
  - Checkout exitoso y sin inventario.
  - Concurrencia sobre el último stock disponible.
  - Producto inactivo y complemento ajeno/duplicado.
  - Cancelación y restitución de inventario.
  - Transiciones válidas e inválidas.
  - Pagos pendientes/aprobados/reembolsados.
  - Reservas pasadas, capacidad y estados.
  - Roles y permisos.

- [ ] **Eliminar pruebas de ejemplo o convertirlas en smoke tests útiles.**

- [ ] **Documentar la API.**
  - Añadir OpenAPI/Swagger con payloads, respuestas, autenticación y códigos de error.

## Verificaciones pendientes con XAMPP/MySQL encendido

- [x] Ejecutar `php artisan migrate:status` contra `cafeteria_app`.
- [x] Comparar el esquema MySQL real con todas las migraciones versionadas.
- [ ] Ejecutar migraciones desde una base vacía y probar rollback.
- [ ] Ejecutar seeders y comprobar que no dependan de datos manuales.
- [x] Probar login → `/api/user` → categorías → logout → token rechazado.
- [x] Probar checkout real con receta y comprobar ticket, items, pago y movimientos dentro de la misma transacción.
- [x] Probar inventario insuficiente y confirmar que no queden tickets, pagos o movimientos parciales.
- [x] Probar dos checkouts concurrentes sobre el último stock disponible.
- [x] Verificar enums reales de MySQL, especialmente reservas y tickets.
- [ ] Revisar integridad de claves foráneas y registros huérfanos.
- [ ] Revisar duplicados y valores negativos en existencias/movimientos.
- [ ] Ejecutar `php artisan test` también con una base MySQL exclusiva de pruebas.

## Estado de la revisión sin MySQL

- Sintaxis PHP validada en los controladores y rutas modificados.
- Laravel reconoce 22 rutas API.
- PHPUnit: 4 pruebas y 14 aserciones correctas usando SQLite en memoria.
- Login, validación de sesión, categorías protegidas, logout y revocación tienen cobertura automatizada.
- No se modificó el esquema ni se ejecutaron migraciones contra la base MySQL real.
