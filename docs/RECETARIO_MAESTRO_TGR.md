# Recetario maestro de inventario — The Gentlemen's Roast

**Corte:** 19 de julio de 2026  
**Alcance:** cantidades descontadas por una porción vendida. No sustituye las fichas de preparación, inocuidad, alérgenos ni rendimiento por lote.

## Criterios

- Espresso estándar de la casa: dosis doble de 18 g de café.
- El agua de extracción o infusión no se descuenta como inventario porque actualmente es un servicio, no un insumo almacenado.
- Panadería y postres se controlan como producto terminado por pieza. El sistema todavía no tiene producción por lotes para descontar harina, mantequilla y rellenos al hornear.
- Los insumos creados por este recetario comienzan con existencia y costo en cero. Deben cargarse mediante un movimiento de reabastecimiento y con el costo real del proveedor.
- Un producto sin existencias suficientes para una porción queda fuera del menú público; Laravel mantiene la decisión de disponibilidad.

## Recetas de consumo

| SKU | Producto | Insumos descontados por venta |
|---|---|---|
| LAT-01 | Café Latte Reserva | Café 18 g; leche 250 ml; vaso 16 oz 1 pza |
| CAP-01 | Cappuccino Clásico | Café 18 g; leche 120 ml; vaso 8 oz 1 pza |
| COL-01 | Cold Brew Ahumado | Café 29 g; vaso 16 oz 1 pza |
| ESP-02 | Espresso Doble | Café 18 g; vaso 8 oz 1 pza |
| AME-01 | Americano Intenso | Café 18 g; vaso 12 oz 1 pza |
| FLA-01 | Flat White Místico | Café 18 g; leche 100 ml; vaso 8 oz 1 pza |
| MOC-01 | Mocha Dorado | Café 18 g; leche 200 ml; salsa de chocolate 30 ml; vaso 12 oz 1 pza |
| MAC-01 | Macchiato | Café 18 g; leche 20 ml; vaso 8 oz 1 pza |
| AFF-01 | Affogato | Café 18 g; helado de vainilla 80 g |
| CRO-01 | Croissant de Almendra | Croissant terminado 1 pza |
| CHO-01 | Pan au Chocolat | Pan terminado 1 pza |
| CHE-01 | Tarta de Queso Vasco | Porción terminada 1 pza |
| MUF-01 | Muffin de Arándanos | Muffin terminado 1 pza |
| GAL-01 | Galleta de Chispas Belga | Galleta terminada 1 pza |
| ECL-01 | Eclair de Vainilla | Eclair terminado 1 pza |
| MAT-01 | Té Matcha Ceremonial | Matcha 2.5 g; vaso 8 oz 1 pza |
| CHA-01 | Chai Latte Especiado | Concentrado chai 30 ml; leche 300 ml; vaso 16 oz 1 pza |
| INF-01 | Infusión de Frutos Rojos | Sachet de infusión 1 pza; vaso 16 oz 1 pza |
| AGU-01 | Agua Mineral Premium | Botella de 500 ml 1 pza |
| JUG-01 | Jugo Verde | Pepino 100 g; manzana 100 g; apio 70 g; espinaca 30 g; limón 15 g; jengibre 5 g; vaso 16 oz 1 pza |

## Preparación resumida

- **Espresso y derivados:** extraer la dosis correspondiente; vaporizar la leche según la bebida y montar inmediatamente.
- **Cold brew:** elaborar a proporción 1:14 (29 g de café por aproximadamente 400 ml de agua), filtrar y servir frío. El sistema descuenta café por porción porque todavía no existe inventario de semielaborados por lote.
- **Flat white:** espresso con aproximadamente 100 ml de leche microespumada y una capa fina de espuma.
- **Mocha:** integrar chocolate y espresso; terminar con leche vaporizada.
- **Macchiato:** marcar el espresso con una cantidad pequeña de espuma de leche.
- **Affogato:** colocar una porción de helado y verter el espresso al servir.
- **Matcha:** tamizar y batir 2.5 g con agua caliente sin hervir hasta formar espuma.
- **Chai latte:** integrar 30 ml de concentrado y completar con leche vaporizada.
- **Infusión de frutos rojos:** infusionar el sachet con agua recién hervida siguiendo el tiempo del fabricante.
- **Jugo verde:** procesar en frío los vegetales y frutas; servir de inmediato y aplicar el procedimiento sanitario del establecimiento.

## Fuentes de referencia

- [Nespresso: Flat White](https://www.nespresso.com/ch/en/recipes/flat-white) — 40 ml de espresso y 90 ml de leche.
- [Nespresso: guía de bebidas clásicas](https://www.nespresso.com/uk/en/coffee-recipes) — composición de cappuccino, macchiato, mocha, flat white y affogato.
- [Nespresso: Affogato](https://www.nespresso.com/recipes/sg/en/23156AFF-affogato.html) — espresso y una porción de helado.
- [Toddy: protocolo de evaluación de cold brew](https://wholesale.toddycafe.com/downloads/Toddy_cold_brew_cupping_protocol.pdf) — proporción lista para beber 1:14.
- [Monin: Chai Espresso Latte](https://monin.us/products/chai-espresso-latte) — 1 oz/30 ml de concentrado en vaso de 16 oz.
- [Matcha.com: preparación para cafeterías](https://bulk.matcha.com/pages/how-baristas-can-quickly-prepare-matcha-for-their-cafe-or-business) — 2–3 g de matcha por bebida.
- [UK Tea & Infusions Association](https://www.tea.co.uk/faqs-about-infusions) — infusiones herbales y frutales preparadas primero con agua recién hervida.
- [Vitamix: Ginger Greens Juice](https://www.vitamix.com/us/en_us/recipes/ginger-greens-juice-r02446) — base de pepino, apio, manzana y hojas verdes.

Las cantidades son una estandarización operativa inicial. Deben validarse mediante cata, merma real, tamaño final de vaso y fichas técnicas de los proveedores antes de fijar costos y mínimos definitivos.
