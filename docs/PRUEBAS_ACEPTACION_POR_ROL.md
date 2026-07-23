# Pruebas de aceptación por rol

Utilizar únicamente datos ficticios. Las cuentas están documentadas en `CUENTAS_PRUEBA_TGR.md` y comparten temporalmente la contraseña de pruebas proporcionada por el propietario.

## Propietario

- Iniciar y cerrar sesión.
- Consultar todos los módulos.
- Crear y editar un usuario.
- Confirmar que no puede desactivar al último propietario.
- Consultar reportes y auditoría.

## Gerencia

- Consultar pedidos, catálogo, inventario, reservaciones, reportes y auditoría.
- Crear/editar categoría y producto.
- Configurar receta y complementos.
- Confirmar que no puede administrar usuarios.
- Crear, editar y desactivar un área y una mesa.
- Configurar un horario y un bloqueo temporal.
- Aprobar, reasignar, registrar llegada, sentar y finalizar una reservación.

## Caja

- Abrir turno con fondo inicial.
- Registrar entrada y retiro con nota.
- Registrar venta POS en efectivo y calcular cambio.
- Registrar terminal con referencia externa.
- Cobrar un pedido público pendiente.
- Cancelar una venta autorizada y comprobar reverso.
- Cerrar caja y comprobar diferencia.
- Confirmar que no puede editar catálogo ni consultar auditoría.

## Preparación

- Ver pedidos pagados.
- Cambiar `paid → preparing → ready → delivered` según permisos disponibles.
- Revisar notas y ticket de cocina.
- Confirmar que no puede cobrar, cancelar ni modificar inventario.

## Inventario

- Consultar existencias y filtros.
- Registrar reabasto, merma y ajuste.
- Confirmar que un movimiento no permite stock negativo.
- Confirmar que no puede operar caja, usuarios o reportes.

## Landing pública

- Consultar 20 productos y sus imágenes.
- Filtrar, buscar y personalizar.
- Agregar, modificar y quitar productos del carrito.
- Finalizar pedido con nombre y teléfono ficticios.
- Consultar estado mediante folio y token.
- Abrir comprobante de impresión y cancelar el selector.
- Abrir el aviso de privacidad de pruebas.
- Consultar áreas y horarios disponibles según fecha y número de personas.
- Enviar una solicitud de reservación y comprobar que se muestra como recibida, no como confirmada.

## Evidencia de cierre

Guardar fecha, rol, navegador, resultado, folio de prueba y captura solamente cuando no exponga tokens, contraseñas ni datos personales reales.
