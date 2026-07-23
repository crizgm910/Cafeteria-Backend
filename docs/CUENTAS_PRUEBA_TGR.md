# Cuentas de prueba por rol

Estas cuentas existen exclusivamente para validar permisos y recorridos del MVP. No representan empleados reales y deben eliminarse o desactivarse antes de producción.

| Rol | Correo de prueba | Alcance principal |
|---|---|---|
| Gerencia | `gerencia.pruebas@example.test` | Catálogo, inventario, pedidos, reservaciones, reportes y auditoría |
| Caja | `caja.pruebas@example.test` | POS, caja, cobro y cancelación autorizada |
| Preparación | `preparacion.pruebas@example.test` | Kitchen/KDS y avance de preparación |
| Inventario | `inventario.pruebas@example.test` | Consulta, reabasto, merma y ajustes de inventario |

La cuenta propietaria existente se conserva y no se documenta aquí su contraseña.

## Contraseña

Las cuatro cuentas usan temporalmente la contraseña de pruebas proporcionada por el propietario. El valor no se guarda en Git ni en esta documentación.

Para recrearlas o restablecerlas:

```powershell
$env:TGR_DEMO_STAFF_PASSWORD='<valor temporal>'
C:\xampp\php\php.exe artisan db:seed --class=DemoStaffSeeder --force
```

El seeder es idempotente: actualiza las cuatro cuentas, asigna exactamente un rol a cada una y revoca sus tokens anteriores.

## Cierre de pruebas

Antes de producción:

1. Desactivar o eliminar las cuatro cuentas.
2. Revocar sus tokens.
3. Crear cuentas nominales para cada empleado.
4. Entregar contraseñas individuales por un canal seguro.
5. Confirmar que cada persona solo conserva los permisos necesarios.
