# Procedimiento MySQL → Supabase PostgreSQL

Supabase se usa únicamente como PostgreSQL administrado. Laravel conserva API, reglas, autenticación Sanctum y migraciones.

1. Crear un proyecto Supabase exclusivo de TGR y obtener conexión directa IPv6 o Supavisor sesión IPv4, siempre con SSL.
2. Desactivar Data API si el plan lo permite; en su defecto, retirar privilegios a `anon`/`authenticated` y no distribuir claves en los frontends.
3. Configurar `DB_CONNECTION=pgsql` y las variables `DB_*` del destino. Configurar `LEGACY_DB_*` con el MariaDB/MySQL de origen en modo de solo lectura.
4. Ejecutar `php artisan migrate --force` sobre PostgreSQL vacío.
5. Ejecutar `php artisan tgr:migrate-legacy-data --dry-run` y revisar conteos.
6. Ejecutar `php artisan tgr:migrate-legacy-data` una sola vez. El comando rechaza destinos de negocio no vacíos, preserva IDs/UUID, normaliza `kiosk → public_web`, clasifica pagos históricos y omite tokens/sesiones/cachés.
7. Ejecutar `php artisan tgr:verify-legacy-migration` y resolver cualquier diferencia.
8. Crear el primer propietario mediante variables temporales `TGR_OWNER_*` y `php artisan db:seed --class=AdminUserSeeder`; retirar después la contraseña del entorno.
9. Ejecutar `php artisan test` contra un proyecto de prueba PostgreSQL y los recorridos E2E.
10. Guardar conteos, hash del respaldo y evidencia de restauración antes del corte.

Respaldo de origen verificado el 17-07-2026: `C:\proyectos\backups\TGR\cafeteria_app_pre_supabase_20260717_220648.sql`, SHA-256 `2BB3E7A89BEBF5232DE216DCC7E06397FE2D176B4EBFC3CA127D01B394D20167`.
