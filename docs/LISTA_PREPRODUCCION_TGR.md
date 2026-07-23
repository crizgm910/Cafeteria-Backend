# Lista de preproducción

## Completado para demostración

- [x] Arquitectura Cliente-Servidor estricta.
- [x] Laravel como API JSON y Supabase solo como PostgreSQL.
- [x] RBAC y cuentas de prueba para cinco roles.
- [x] Flujo Landing → cobro → Kitchen → entrega.
- [x] Cancelación y restauración de inventario.
- [x] POS, caja, reportes y auditoría.
- [x] Datos comerciales y aviso de privacidad marcados como demostrativos.
- [x] Ticket preparado para 80 mm y flujo hasta selector de impresión.
- [x] Pruebas backend, frontend, concurrencia y responsive.
- [x] Reservaciones con áreas configurables, mesas, horarios, bloqueos, disponibilidad y estados.
- [x] Movimientos manuales de inventario con actor, motivo y existencia anterior/posterior.

## Obligatorio antes de publicar

- [ ] Obtener revisión jurídica del aviso de privacidad definitivo.
- [ ] Instalar `pg_dump` PostgreSQL 17 y ensayar restauración lógica.
- [ ] Alojar Landing y Admin en Sites y Laravel en un servicio PHP/Docker, si se confirma esa topología.
- [ ] Configurar URLs HTTPS, `APP_DEBUG=false`, CORS y secretos definitivos; el dominio propio es opcional.
- [ ] Definir monitoreo y responsable de alertas.
- [ ] Consolidar cambios en commits coordinados de los tres repositorios.

## Exclusiones aceptadas para la demostración

- [x] Conservar existencias, costos y datos comerciales sintéticos, claramente identificados como demo.
- [x] Conservar cuentas de prueba en lugar de usuarios nominales.
- [x] Omitir impresora física; la prueba termina en el selector y formato térmico.

## Posibles actualizaciones

Delivery, fidelización, suscripciones, CFDI, cuentas de cliente, aplicación móvil, múltiples sucursales, proveedores, IoT y analítica predictiva. No bloquean el MVP actual.
