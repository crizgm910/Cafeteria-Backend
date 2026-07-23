# The Gentlemen's Roast — API

Backend de The Gentlemen's Roast: punto de venta, caja, cocina, catálogo, inventario, pedidos públicos, usuarios, reportes y reservaciones. Laravel es la única fuente de verdad; los navegadores nunca se conectan directamente a Supabase.

## Tecnología y componentes

- PHP 8.2 o superior y Laravel 12.
- PostgreSQL de Supabase como base remota; SQLite para las pruebas rápidas.
- Laravel Sanctum para sesiones del personal, con expiración configurable.
- API JSON bajo `/api`; Admin y Landing son repositorios Vanilla JS separados.

## Instalación local

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8000
```

Configure en `.env` la conexión PostgreSQL, `APP_URL`, `APP_TIMEZONE`, `TGR_ALLOWED_ORIGINS` y `SANCTUM_EXPIRATION`. Nunca coloque credenciales de Supabase en Admin o Landing ni confirme `.env` en Git.

Para cargar únicamente las áreas y mesas de demostración sin reinicializar catálogo:

```powershell
php artisan db:seed --class=ReservationDemoSeeder --force
```

## Verificación

```powershell
php artisan test
php artisan migrate:status
php artisan route:list --path=api
```

La documentación operativa está en [`docs`](docs). Los puntos de entrada recomendados son:

- [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md)
- [`docs/STATE_MACHINES.md`](docs/STATE_MACHINES.md)
- [`docs/PRUEBAS_ACEPTACION_POR_ROL.md`](docs/PRUEBAS_ACEPTACION_POR_ROL.md)
- [`docs/LISTA_PREPRODUCCION_TGR.md`](docs/LISTA_PREPRODUCCION_TGR.md)
- [`docs/RESPALDO_Y_RECUPERACION_SUPABASE.md`](docs/RESPALDO_Y_RECUPERACION_SUPABASE.md)

## Reglas de arquitectura

1. Precios, stock, permisos, estados y disponibilidad se calculan en Laravel.
2. Operaciones económicas, de inventario y capacidad usan transacciones e idempotencia cuando corresponde.
3. Las rutas administrativas requieren token y permiso explícito.
4. Las respuestas de `/api/*`, incluidos los errores, son JSON.
5. Los datos con historial se desactivan en vez de eliminarse cuando así lo exige el dominio.

Este repositorio contiene datos de demostración. Antes de producción deben sustituirse datos comerciales, usuarios, costos y existencias, y completarse la lista de preproducción.
