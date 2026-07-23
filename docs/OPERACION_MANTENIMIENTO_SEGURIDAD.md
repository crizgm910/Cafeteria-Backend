# Plan de operación, mantenimiento y seguridad

**Corte:** 19 de julio de 2026  
**Arquitectura:** clientes Vanilla JS → API JSON Laravel → Supabase PostgreSQL.  
**Alcance:** MVP de una cafetería; Supabase se utiliza solo como base de datos.

## Responsables sugeridos

| Responsabilidad | Responsable operativo |
|---|---|
| Datos, cuentas y aprobación de cambios | Propietario |
| Caja, conciliación y terminal externa | Responsable de caja |
| Pedidos y tiempos de preparación | Responsable de preparación |
| Existencias, mermas y reabasto | Responsable de inventario |
| Código, migraciones, respaldos y seguridad | Responsable técnico |

Una misma persona puede cubrir varias funciones durante las pruebas, pero debe iniciar sesión con el rol que se está validando.

## Rutina diaria

1. Consultar `GET /api/health` y confirmar `status=ok` y `database=available`.
2. Verificar que no exista un turno de caja abierto del día anterior.
3. Revisar insumos bajo mínimo y registrar reabastos o mermas con nota.
4. Al cierre, comparar efectivo contado contra efectivo esperado.
5. Revisar pedidos pendientes, cancelaciones, diferencias y eventos de auditoría.
6. No borrar registros directamente en Supabase para corregir operaciones.

## Rutina semanal

- Revisar errores Laravel y respuestas 5xx.
- Confirmar que los endpoints públicos no presentan abuso o bloqueos continuos.
- Revisar usuarios activos, roles y cuentas de prueba.
- Confirmar la disponibilidad del respaldo más reciente en Supabase.
- Ejecutar smoke tests de Admin y Landing.
- Revisar espacio, conexiones y consultas lentas en Supabase Observability.

## Rutina mensual

- Ejecutar `composer audit` y revisar avisos de Laravel/PHP.
- Revisar actualizaciones menores; nunca actualizar directamente en producción.
- Ejecutar la suite Laravel completa y `node --check` sobre ambos clientes.
- Revisar los asesores de seguridad y rendimiento de Supabase.
- Probar restauración en un proyecto o esquema desechable.
- Revisar que CORS solo incluya los dominios definitivos.
- Rotar credenciales si hubo exposición o cambio de responsable.

## Rutina trimestral

- Ensayar recuperación completa y documentar tiempo real de recuperación.
- Revisar roles con el principio de menor privilegio.
- Ejecutar prueba de concurrencia y carga representativa.
- Revisar políticas de privacidad, retención y eliminación de datos.
- Revisar compatibilidad de PHP, Laravel y PostgreSQL antes de cualquier actualización mayor.

## Procedimiento de actualización

1. Leer notas de versión y cambios incompatibles de Laravel, PHP, Composer y Supabase.
2. Crear una rama `codex/` o de mantenimiento.
3. Respaldar la base antes de cambiar dependencias o migraciones.
4. Ejecutar la actualización en un ambiente aislado.
5. Aplicar migraciones sobre una base de prueba PostgreSQL 17.
6. Ejecutar pruebas unitarias, funcionales, concurrencia y E2E.
7. Revisar `composer audit`, asesores Supabase y diferencias de esquema.
8. Preparar reversión y ventana de mantenimiento.
9. Publicar únicamente después de aprobación del propietario.

## Monitoreo mínimo

| Señal | Umbral inicial | Acción |
|---|---:|---|
| `/api/health` no responde | 2 comprobaciones consecutivas | Revisar Laravel, red y Supabase |
| Respuestas 5xx | Cualquier repetición | Revisar correlación y logs |
| Inicio de sesión bloqueado | Repetición anormal | Revisar intentos y origen |
| Diferencia de caja | Distinta de $0.00 | Investigar antes de cerrar el día |
| Inventario bajo mínimo | Cualquier insumo | Conteo físico y reabasto |
| Conexiones PostgreSQL | Cerca del límite del plan | Revisar pool y consultas |
| Respaldo faltante | Más de 24 h en operación | Crear respaldo y detener cambios de riesgo |

## Controles de seguridad

- Mantener `APP_DEBUG=false` y `LOG_LEVEL` apropiado fuera de desarrollo.
- Mantener SSL obligatorio para PostgreSQL.
- No incluir contraseña, `service_role`, URI de base ni tokens en Git o clientes.
- Usar cuentas nominales; las cuentas demostrativas se eliminan antes de producción.
- Revocar tokens al desactivar usuarios o cambiar contraseñas.
- Mantener Data API fuera de la arquitectura y sus permisos revocados.
- Proteger con MFA las cuentas que administren Supabase y GitHub.
- Revisar encabezados, CORS, rate limits e idempotencia después de cambios de infraestructura.

## Cambios que requieren nueva aceptación

- Nuevo método de pago o terminal.
- Delivery, CFDI, fidelización o cuentas de cliente.
- Cambio de proveedor de base de datos.
- Nueva sucursal o segregación de datos.
- Impresión automática sin intervención del usuario.
- Cambios de estados, permisos o compensación de inventario.

Referencias: [Production Checklist de Supabase](https://supabase.com/docs/guides/deployment/going-into-prod) y [Shared Responsibility Model](https://supabase.com/docs/guides/deployment/shared-responsibility-model).
