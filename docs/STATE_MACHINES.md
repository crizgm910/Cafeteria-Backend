# Estados y reglas operativas del MVP

## Pedido y cumplimiento global

`pending → paid → preparing → ready → delivered`

- `pending → preparing` solo es válido si no existe un pago pendiente.
- `pending|paid|preparing|ready → cancelled` requiere permiso y motivo.
- `delivered` y `cancelled` son terminales.
- Reversiones operativas permitidas: `preparing → pending` y `ready → preparing`.
- Al preparar/listar se sincronizan `ticket_items.kds_status` y sus marcas de tiempo.

## Pago

- Público: `pending → approved` al cobrar en caja; `pending → cancelled` si se cancela antes del cobro.
- Confirmado: `approved → refunded` al cancelar.
- Efectivo crea un movimiento `sale`; su devolución crea un único movimiento `refund` en una caja abierta.
- Terminal externa exige referencia de venta y referencia de reembolso.

## Inventario

- El consumo se registra una vez al crear el pedido/venta dentro de la misma transacción.
- Stock insuficiente revierte ticket, ítems, pago y movimientos.
- Cancelar restaura exactamente lo consumido mediante movimientos `adjustment`.
- Una segunda cancelación es inválida y no vuelve a compensar.

## Caja

`open → closed`

- Solo una caja abierta por usuario.
- Movimientos: `sale`, `refund`, `deposit`, `withdrawal`.
- `esperado = fondo inicial + suma(movimientos)`; diferencia = contado − esperado.

## Reservación

`pending → approved → checked_in → seated → completed`

- `pending|approved → cancelled`.
- `approved → no_show`.
- La solicitud pública asigna capacidad disponible, pero permanece `pending` hasta aprobación del personal.
- `cancelled`, `completed` y `no_show` liberan capacidad y son terminales.
- La reasignación exige una mesa compatible, disponible y una versión vigente de la reserva.
