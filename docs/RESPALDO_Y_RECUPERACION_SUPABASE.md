# Respaldo y recuperación de Supabase PostgreSQL

## Objetivo

Recuperar el MVP después de borrado accidental, migración defectuosa o pérdida de disponibilidad sin conectar los frontends directamente a Supabase.

## Estrategia

1. **Respaldo administrado:** verificar diariamente en Supabase Dashboard la fecha del respaldo disponible para el plan contratado.
2. **Respaldo lógico antes de cambios:** ejecutar `pg_dump` con un cliente PostgreSQL 17 antes de migraciones, actualizaciones mayores o carga masiva.
3. **Respaldo del código:** mantener los tres repositorios Git y la documentación en versiones coordinadas.
4. **Ensayo de recuperación:** restaurar en un proyecto o esquema temporal, nunca encima de `public` durante la prueba.

## Respaldo lógico recomendado

El equipo aún no tiene `pg_dump` instalado en la estación actual. Antes de producción se debe instalar el cliente oficial PostgreSQL 17 y ejecutar:

```powershell
$env:PGPASSWORD='<contraseña desde un almacén seguro>'
pg_dump --host '<pooler-host>' --port 5432 --username '<pooler-user>' `
  --dbname postgres --format custom --no-owner --no-acl `
  --file 'TGR_YYYYMMDD_HHMMSS.dump'
Remove-Item Env:PGPASSWORD
```

No se debe escribir la contraseña en el nombre del archivo, scripts, historial o documentación.

## Verificación del respaldo

```powershell
pg_restore --list 'TGR_YYYYMMDD_HHMMSS.dump'
Get-FileHash 'TGR_YYYYMMDD_HHMMSS.dump' -Algorithm SHA256
```

Registrar fecha, responsable, tamaño, SHA-256, versión de PostgreSQL y motivo.

## Ensayo de recuperación

1. Crear proyecto o base PostgreSQL 17 desechable.
2. Restaurar con `pg_restore --clean --if-exists --no-owner --no-acl`.
3. Configurar Laravel temporalmente contra el destino.
4. Ejecutar `php artisan migrate:status`.
5. Comparar conteos y totales de productos, ingredientes, tickets y pagos.
6. Ejecutar las 59 pruebas del backend y un recorrido E2E.
7. Eliminar el ambiente temporal.

## Objetivos iniciales

- RPO de pruebas: 24 horas.
- RTO de pruebas: 4 horas.
- Producción: definir RPO/RTO con el propietario según el costo de perder pedidos.

## Estado actual

- PostgreSQL 17 verificado.
- Migraciones completas recreadas en esquemas temporales.
- Migración y aceptación E2E sobre esquema temporal ya ensayadas.
- Existe respaldo histórico previo a Supabase de la base MariaDB.
- Falta instalar `pg_dump` 17 y ensayar una restauración desde un respaldo lógico de Supabase.

Referencia: [Backups de Supabase](https://supabase.com/docs/guides/platform/backups).
