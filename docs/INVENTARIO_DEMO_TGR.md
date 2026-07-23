# Inventario sintético de demostración TGR

**Fecha de carga:** 19 de julio de 2026  
**Moneda de referencia:** MXN  
**Importante:** estas cantidades, mínimos y costos son datos inventados para demostrar el funcionamiento del POS, recetas, disponibilidad y alertas. No sustituyen conteos físicos, compras ni facturas.

| SKU | Insumo | Unidad | Existencia demo | Mínimo demo | Costo demo/unidad |
|---|---|---:|---:|---:|---:|
| ING-VASO8 | Vaso 8oz | piezas | 300 | 75 | $1.80 |
| ING-VASO12 | Vaso 12oz | piezas | 400 | 100 | $2.10 |
| ING-CHOCOLATE | Salsa de Chocolate | mililitros | 3,000 | 600 | $0.18 |
| ING-HELADO-VAINILLA | Helado de Vainilla | gramos | 5,000 | 1,000 | $0.16 |
| ING-CROISSANT-ALM | Croissant de Almendra | piezas | 40 | 10 | $24.00 |
| ING-PAIN-CHOC | Pan au Chocolat | piezas | 40 | 10 | $25.00 |
| ING-TARTA-VASCA | Porción de Tarta de Queso Vasco | piezas | 24 | 6 | $32.00 |
| ING-MUFFIN-ARAND | Muffin de Arándanos | piezas | 36 | 8 | $18.00 |
| ING-GALLETA-BELGA | Galleta de Chispas Belga | piezas | 60 | 15 | $10.00 |
| ING-ECLAIR-VAINILLA | Eclair de Vainilla | piezas | 30 | 8 | $26.00 |
| ING-MATCHA | Matcha Ceremonial | gramos | 500 | 100 | $1.20 |
| ING-CHAI | Concentrado de Té Chai | mililitros | 3,000 | 600 | $0.12 |
| ING-INF-FRUTOS | Sachet de Infusión de Frutos Rojos | piezas | 80 | 20 | $8.00 |
| ING-AGUA-MIN500 | Agua Mineral Premium 500ml | piezas | 96 | 24 | $12.00 |
| ING-PEPINO | Pepino | gramos | 6,000 | 1,500 | $0.04 |
| ING-MANZANA-VERDE | Manzana Verde | gramos | 8,000 | 2,000 | $0.05 |
| ING-APIO | Apio | gramos | 5,000 | 1,200 | $0.04 |
| ING-ESPINACA | Espinaca | gramos | 3,000 | 700 | $0.08 |
| ING-LIMON | Limón Verde | gramos | 5,000 | 1,000 | $0.05 |
| ING-JENGIBRE | Jengibre | gramos | 1,500 | 300 | $0.12 |

## Implementación

- Seeder: `database/seeders/DemoInventorySeeder.php`.
- Cada carga inicial genera una transacción `restock` con UUID determinista.
- Volver a ejecutar el seeder no duplica existencias ni movimientos.
- Los seis insumos que ya tenían datos previos no fueron sobrescritos.
- Antes de producción, reemplazar estos valores mediante conteo físico y facturas.
