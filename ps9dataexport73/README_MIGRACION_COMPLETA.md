# PS 1.7 → PS 9 Data Export Module - Guía Completa

## ⚠️ IMPORTANTE: Migración Completa y Segura

Este módulo exporta e importa datos de PrestaShop 1.7 a PrestaShop 9 con validación automática y rollback.

## 🔧 Correcciones Implementadas (v1.3.2)

### ✅ Problema Resuelto: "Shop id 0 is invalid"

**Causa raíz**: PrestaShop 9 requiere que:
1. Exista un `shop` con `id_shop >= 1`
2. Todos los registros en tablas `*_shop` tengan `id_shop` válido (no 0, no NULL)
3. Exista configuración de `shop_group` y `shop_url`

**Solución implementada**:
1. **Exportación**: Ahora incluye tablas `shop`, `shop_group`, `shop_url`
2. **Validación automática**: Después de importar, se ejecuta `PS9ValidationService` que:
   - Crea `shop` ID 1 si no existe
   - Crea `shop_group` ID 1 si no existe  
   - Crea `shop_url` para el dominio actual
   - Corrige todos los `id_shop = 0` o `NULL` a `id_shop = 1`
   - Valida integridad referencial

## 📋 Proceso de Migración Paso a Paso

### PASO 1: Exportar desde PrestaShop 1.7

1. Instalar módulo en PS 1.7
2. Ir a **Módulos** → **PS 1.7 → PS 9 Data Export**
3. Seleccionar qué exportar:
   - ✅ **Catálogo** (productos, categorías, imágenes, stock)
   - ✅ **Clientes** (opcional)
   - ✅ **Pedidos** (opcional)
4. Clic en **Exportar SQL**
5. Descargar archivo `.sql` generado

**⚠️ CRÍTICO**: No edites el archivo SQL exportado. Contiene las tablas necesarias para PS9.

### PASO 2: Preparar PrestaShop 9

1. **Instalar PrestaShop 9** limpio
2. **Crear backup completo** de la base de datos
3. **Configurar PHP**:
   - `upload_max_filesize = 256M`
   - `post_max_size = 256M`
   - `max_execution_time = 600`
   - `memory_limit = 2048M`

### PASO 3: Importar en PrestaShop 9

1. Instalar este módulo en PS 9
2. Ir a **Módulos** → **PS 1.7 → PS 9 Data Export**
3. **Tab "Importar"**
4. Subir archivo `.sql` exportado desde PS 1.7
5. Clic en **Importar**

**El módulo automáticamente**:
- ✅ Crea backup antes de importar
- ✅ Importa los datos
- ✅ Valida compatibilidad PS9
- ✅ Corrige `id_shop` inválidos
- ✅ Verifica integridad referencial
- ❌ Si falla: rollback automático

### PASO 4: Verificación Post-Importación

1. **Limpiar caché**: `php bin/console cache:clear`
2. **Verificar catálogo**: Ir a Productos → ver que aparecen
3. **Verificar categorías**: Ir a Categorías → verificar
4. **Verificar stock**: Productos deben mostrar stock correcto

## 🛠️ Tablas Exportadas/Importadas

### Catálogo (COMPLETO)
```
✅ shop, shop_group, shop_url (NUEVO - CRÍTICO PARA PS9)
✅ category, category_lang, category_shop, category_group, category_product
✅ product, product_lang, product_shop
✅ product_attribute, product_attribute_shop, product_attribute_combination
✅ specific_price
✅ manufacturer, manufacturer_lang
✅ supplier, supplier_lang, product_supplier
✅ image, image_lang, image_shop
✅ stock_available
✅ feature, feature_lang, feature_value, feature_value_lang, feature_product
✅ attribute, attribute_lang, attribute_group, attribute_group_lang
✅ tag, product_tag
```

### Clientes (opcional)
```
✅ customer, customer_group
✅ address
✅ group, group_lang
```

### Pedidos (opcional)
```
✅ cart, cart_product
✅ orders, order_detail, order_invoice, order_payment
✅ order_slip, order_slip_detail
```

## ❌ Solución de Problemas

### Error: "Shop id 0 is invalid"

**YA CORREGIDO** en v1.3.2. Si aún aparece:

1. Verificar que importaste desde PS 1.7 (no desde backup manual)
2. Ejecutar en phpMyAdmin:
```sql
-- Verificar configuración
SELECT * FROM tu_prefijo_shop WHERE id_shop = 1;
SELECT * FROM tu_prefijo_shop_group WHERE id_shop_group = 1;
SELECT * FROM tu_prefijo_shop_url WHERE id_shop = 1;

-- Si no existen, el módulo los crea automáticamente
```

### Error: "Cannot access catalog"

Limpiar caché completamente:
```bash
rm -rf var/cache/*
php bin/console cache:clear --env=prod
php bin/console cache:clear --env=dev
```

### Import fallido

El módulo hace **rollback automático**. Verifica:
1. Log de errores en la interfaz del módulo
2. Archivo de backup generado: `download/ps9-export/backup_before_import_*.sql`
3. Si necesitas restaurar manualmente: importa el backup en phpMyAdmin

## 📊 Validaciones Automáticas

El módulo valida:

1. **Shop Configuration**:
   - Existe `shop` con `id_shop = 1`
   - Existe `shop_group` con `id_shop_group = 1`
   - Existe `shop_url` para el dominio

2. **ID Shop Fields**:
   - Todas las tablas `*_shop` tienen `id_shop >= 1`
   - No hay registros con `id_shop = 0` o `NULL`

3. **Referential Integrity**:
   - Todos los `id_shop` existen en la tabla `shop`
   - No hay registros huérfanos

## 🔄 Changelog

### v1.3.2 (2025-01-31)
- ✅ **CRÍTICO**: Añadidas tablas `shop`, `shop_group`, `shop_url` a la exportación
- ✅ Nuevo servicio `PS9ValidationService` para validación post-importación
- ✅ Reparación automática de `id_shop = 0` → `id_shop = 1`
- ✅ Creación automática de configuración de shop si no existe
- ✅ Validación de integridad referencial
- ✅ Rollback automático si la importación falla

### v1.3.1
- Validación preflight
- Reparación de datos incompatibles
- Export SQL data-only

## 📞 Soporte

Si encuentras problemas:

1. Verificar logs en la interfaz del módulo
2. Comprobar `var/logs/` de PrestaShop
3. Revisar archivo de backup generado
4. Si el problema persiste: crear issue con:
   - Versión PS origen y destino
   - Log de error completo
   - Tamaño del archivo SQL

## ⚠️ NOTAS IMPORTANTES

1. **NO uses este módulo para copiar TODO** entre instalaciones. Solo para migrar DATOS de catálogo/clientes/pedidos.

2. **Las imágenes se exportan por separado**: Descarga el ZIP de imágenes y súbelas manualmente a `/img/` de PS9.

3. **Configuración de tienda NO se exporta**: Tendrás que reconfigurar manualmente:
   - Métodos de pago
   - Transportes
   - Módulos
   - Temas

4. **Siempre hacer backup** antes de importar.

5. **Verificar PHP limits** antes de importar archivos grandes.

## 🎯 Resultado Esperado

Después de una importación exitosa:

✅ Productos visibles en catálogo
✅ Categorías funcionando
✅ Stock correcto
✅ Imágenes vinculadas (si subiste el ZIP)
✅ Clientes importados (si seleccionaste)
✅ Pedidos importados (si seleccionaste)
✅ NO hay error "Shop id 0 is invalid"
✅ Backoffice accesible y funcional
