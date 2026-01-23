# PS9 CSV Export/Import Module

## 🔧 Instalación

1. **Desinstala** cualquier módulo anterior (ps9dataexport73, ps178to9migration, etc.)
2. **Instala** ps9csvexport.zip
3. Ve a **Módulos → Export CSV for PS9**

## 📤 EXPORTACIÓN (desde PS 1.7.6)

### Orden correcto:
1. **Categories** - Exporta primero
2. **Brands** - Marcas/fabricantes
3. **Suppliers** - Proveedores  
4. **Products** - Productos (requiere categorías ya importadas)
5. **Customers** - Clientes
6. **Addresses** - Direcciones (requiere clientes ya importados)

### ⚠️ IMPORTANTE:
- El CSV **NO incluye IDs** - PS9 los auto-genera
- Las categorías se exportan por **NOMBRE**, no por ID
- Los productos referencian categorías por **ID numérico**

## 📥 IMPORTACIÓN (en PS9)

### Usando el módulo (recomendado):

1. **Instala** el mismo módulo en PS9
2. Ve a **Módulos → Export CSV for PS9**
3. En sección "Import CSV":
   - **Upload CSV file**: Selecciona archivo
   - **Entity type**: Elige tipo (Categories, Products, etc.)
   - **Delete existing data**: ⚠️ Solo marca si quieres borrar datos existentes
4. Click **Upload & Import**

El módulo te mostrará:
- ✅ Registros importados
- ⚠️ Advertencias
- ❌ Errores (con número de línea)

### O usando importador nativo de PS9:

1. Ve a **Parámetros Avanzados → Importar**
2. Selecciona tipo de entidad
3. Sube CSV
4. Configura:
   - Separador: **;** (punto y coma)
   - Múltiples valores separador: **,** (coma)
   - **Delete all [entity] before import**: NO marcar (causará errores)
5. Mapea columnas automáticamente
6. Importa

## ⚠️ Problemas conocidos y soluciones

### Categorías no visibles después de importar:
```sql
-- Ejecutar en phpMyAdmin:
INSERT IGNORE INTO ps_category_shop (id_category, id_shop, position)
SELECT id_category, 1, position FROM ps_category 
WHERE id_category NOT IN (SELECT id_category FROM ps_category_shop WHERE id_shop = 1);
```

### Productos aparecen como "Product" sin datos:
- **Causa**: El importador nativo de PS9 no maneja bien multi-idioma
- **Solución**: Usa el módulo para importar, NO el importador nativo

### Error "ID debe ser único":
- **Causa**: El CSV incluye IDs que ya existen
- **Solución**: Usa la última versión del módulo que NO exporta IDs

### Error "unit_price no es válida":
- **Causa**: Campo unit_price con valor 0
- **Solución**: La última versión deja este campo vacío

## 🔍 Verificar importación correcta

### Categorías:
```sql
SELECT COUNT(*) FROM ps_category WHERE id_category > 2;
SELECT COUNT(*) FROM ps_category_lang;
SELECT COUNT(*) FROM ps_category_shop;
```
Deben ser iguales (o category_shop = category_lang si monotienda)

### Productos:
```sql
SELECT COUNT(*) FROM ps_product;
SELECT COUNT(*) FROM ps_product_lang;
SELECT COUNT(*) FROM ps_category_product;
```
- product_lang debe ser = product * idiomas
- category_product debe tener al menos 1 por producto

## 📊 Estadísticas esperadas

Para una tienda con:
- 1725 productos
- 14 categorías
- 1 idioma

Deberías tener:
- `ps_product`: 1725 registros
- `ps_product_lang`: 1725 registros
- `ps_category`: 16 registros (14 tuyas + Home + Root)
- `ps_category_lang`: 16 registros
- `ps_category_product`: ~1725+ registros

## 🆘 Soporte

Si después de importar:
- Productos sin nombre → Problema con product_lang
- Categorías invisibles → Problema con category_shop
- Productos sin categoría → Problema con category_product

Contacta con desarrollador.
