# Solución: Productos Importados No Visibles en Front Office

## 🔍 Problema Detectado

Los productos importados desde PrestaShop 1.7.x aparecen correctamente en el **Back Office** pero **NO se visualizan en el Front Office** (vista previa, categorías, búsqueda), mientras que los productos nuevos creados directamente en PS 9 sí funcionan correctamente.

## 🎯 Causa Raíz

Este problema es **muy común** en migraciones de PS 1.7 a PS 9 y se debe principalmente a:

### 1. **FALTA de registros en `ps_product_shop`** (Causa #1 - 90% de casos)
PrestaShop 9 requiere que TODOS los productos tengan un registro en la tabla `ps_product_shop` para CADA tienda. Durante la migración, estos registros pueden:
- No importarse correctamente
- Importarse con `INSERT IGNORE` que los omite si hay conflictos
- Perderse en el proceso de limpieza de datos

### 2. Visibilidad configurada incorrectamente
- Campo `visibility` en `none` o `search` en lugar de `both`
- Diferencias entre `ps_product` y `ps_product_shop`

### 3. Productos sin categoría asignada
- Falta registro en `ps_category_product`
- `id_category_default` NULL o 0

### 4. Link rewrite vacío o inválido
- Campo `link_rewrite` en `ps_product_lang` NULL o vacío
- URLs no generadas correctamente

### 5. Problemas de indexación
- Campo `indexed` en 0
- Índice de búsqueda no regenerado

## 🔧 Solución Paso a Paso

### PASO 1: Diagnóstico

Primero, ejecuta el script de diagnóstico para identificar exactamente qué falla:

```sql
-- Usa este archivo:
DIAGNOSTIC_PRODUCT_VISIBILITY.sql
```

**Instrucciones:**
1. Abre phpMyAdmin
2. Selecciona tu base de datos de PrestaShop 9
3. Abre la pestaña **SQL**
4. Copia y pega el contenido completo de `DIAGNOSTIC_PRODUCT_VISIBILITY.sql`
5. **IMPORTANTE:** Modifica estas líneas al inicio del archivo:
   ```sql
   SET @imported_product = 1;   -- Cambia por un ID de producto importado que NO se ve
   SET @new_product = 100;      -- Cambia por un ID de producto nuevo que SÍ se ve
   SET @shop_id = 1;            -- ID de tu tienda (normalmente 1)
   SET @lang_id = 1;            -- ID idioma (1=Español)
   ```
6. Ejecuta el script (botón "Continuar")
7. **Revisa los resultados** - te mostrará exactamente qué diferencias hay

### PASO 2: Aplicar Correcciones

Una vez identificado el problema, ejecuta el script de corrección:

```sql
-- Usa este archivo:
FIX_PRODUCT_VISIBILITY.sql
```

**⚠️ IMPORTANTE - Antes de ejecutar:**
1. **HAZ BACKUP** de la base de datos:
   ```bash
   mysqldump -u usuario -p nombre_bd > backup_antes_fix.sql
   ```

2. Abre phpMyAdmin → Selecciona tu base de datos → Pestaña SQL

3. Copia y pega el contenido de `FIX_PRODUCT_VISIBILITY.sql`

4. **Verifica la configuración** al inicio:
   ```sql
   SET @shop_id = 1;        -- ID de tu tienda
   SET @lang_id = 1;        -- ID idioma principal
   SET @id_shop_group = 1;  -- ID grupo de tiendas
   ```

5. Ejecuta el script completo

6. Revisa el resultado - debería mostrar cuántos registros se corrigieron

### PASO 3: Limpiar Caché (OBLIGATORIO)

Después de ejecutar el script de corrección, **DEBES** limpiar la caché:

**Opción A - Via SSH (Recomendado):**
```bash
cd /path/to/prestashop
rm -rf var/cache/*
```

**Opción B - Via Back Office:**
1. Ve a **Parámetros Avanzados** → **Rendimiento**
2. Click en **Limpiar caché**
3. Espera confirmación

**Opción C - Via FTP:**
1. Conecta por FTP a tu servidor
2. Navega a `/var/cache/`
3. Elimina las carpetas `prod` y `dev`

### PASO 4: Regenerar Índice de Búsqueda

1. Back Office → **Preferencias** → **Buscar**
2. Scroll hasta "Indexación"
3. Click en **"Regenerar índice completo"**
4. **Espera** - puede tardar varios minutos
5. Verifica que diga "Indexación completada"

### PASO 5: Regenerar SEO y URLs

1. Back Office → **Preferencias** → **SEO y URLs**
2. Click en **"Generar archivo robots.txt"**
3. Click en **"Regenerar .htaccess"** (si usas Apache)

### PASO 6: Verificar Resultados

1. **Limpia caché del navegador** (Ctrl + F5)
2. Ve al **Front Office** de tu tienda
3. Busca un producto que antes no se veía
4. Verifica que aparezca en:
   - Búsqueda
   - Categorías
   - Página del producto (URL directa)
5. Verifica imágenes y descripciones

## 📊 ¿Qué Hace el Script de Corrección?

El script `FIX_PRODUCT_VISIBILITY.sql` realiza 8 correcciones automáticas:

| # | Corrección | Descripción |
|---|------------|-------------|
| 1 | **ps_product_shop** | Crea registros faltantes copiando datos de ps_product |
| 2 | **Visibilidad** | Cambia visibility a 'both' en productos activos |
| 3 | **Categorías** | Asigna categoría Home (2) a productos sin categoría |
| 4 | **Link Rewrite** | Genera URLs automáticamente desde el nombre |
| 5 | **Stock** | Crea configuración de stock si falta |
| 6 | **Indexación** | Marca productos para reindexar |
| 7 | **Activación** | Activa productos que deberían estar activos |
| 8 | **Atributos** | Limpia cache de atributos inválidos |

## 🐛 Si Aún No Funciona

### Verificación Manual

Comprueba manualmente un producto que no se ve:

```sql
-- Reemplaza 123 con el ID del producto
SET @product_id = 123;

-- ¿Existe en ps_product_shop?
SELECT * FROM ps_product_shop WHERE id_product = @product_id;

-- ¿Tiene categoría?
SELECT * FROM ps_category_product WHERE id_product = @product_id;

-- ¿Tiene nombre y link_rewrite?
SELECT * FROM ps_product_lang WHERE id_product = @product_id;

-- ¿Está activo?
SELECT id_product, active, visibility FROM ps_product WHERE id_product = @product_id;
```

### Problemas Adicionales

#### ❌ Imágenes no se ven
**Causa:** Las imágenes no se importaron con el SQL.

**Solución:** Copiar carpeta `/img/` completa desde PS 1.7 a PS 9 via FTP/SSH:
```bash
# Desde servidor PS 1.7
cd /path/to/ps17/
tar -czf images.tar.gz img/p/

# Copiar a PS 9
scp images.tar.gz user@ps9:/path/to/ps9/
cd /path/to/ps9/
tar -xzf images.tar.gz
chown -R www-data:www-data img/
```

#### ❌ Error 500 al ver producto
**Causa:** Datos NULL en campos que requieren valores en PS 9.

**Solución:** Ejecuta también `FIX_ALL_PS9_COMPLETE.sql`

#### ❌ URL da 404
**Causa:** Falta regenerar URLs amigables.

**Solución:**
1. Back Office → SEO y URLs
2. Regenerar .htaccess
3. Limpiar caché

## 📁 Archivos de Solución

Los siguientes archivos están disponibles en el repositorio:

```
📄 DIAGNOSTIC_PRODUCT_VISIBILITY.sql  - Diagnóstico completo
📄 FIX_PRODUCT_VISIBILITY.sql         - Corrección automática
📄 README_PRODUCT_VISIBILITY.md       - Esta guía
📄 FIX_ALL_PS9_COMPLETE.sql          - Corrección de valores NULL
```

## ⚠️ Prevención Futura

Para evitar este problema en futuras migraciones:

### En el Módulo Exportador (PS 1.7):
✅ Ya incluye `INSERT IGNORE` para evitar duplicados

### En el Módulo Importador (PS 9):
✅ Ejecutar siempre los scripts de corrección después de importar

### Recomendación:
Después de CADA importación en PS 9:
1. Ejecutar `FIX_PRODUCT_VISIBILITY.sql`
2. Ejecutar `FIX_ALL_PS9_COMPLETE.sql`
3. Limpiar caché
4. Regenerar índice

## 📞 Soporte

Si después de seguir todos los pasos el problema persiste:

1. **Ejecuta el diagnóstico** y copia los resultados
2. **Verifica los logs** de PrestaShop en `/var/logs/`
3. **Abre un issue** en GitHub con:
   - Resultados del diagnóstico
   - Mensajes de error (si los hay)
   - Versión de PrestaShop 9
   - Versión de PHP

## ✅ Checklist de Solución

- [ ] Backup de la base de datos realizado
- [ ] Script de diagnóstico ejecutado
- [ ] Problemas identificados
- [ ] Script de corrección ejecutado
- [ ] Caché limpiada
- [ ] Índice de búsqueda regenerado
- [ ] SEO y URLs regenerados
- [ ] Productos verificados en Front Office
- [ ] Imágenes copiadas (si faltaban)
- [ ] Todo funciona correctamente ✨

---

**🎉 Si esta guía te ayudó, dale una ⭐ al repositorio!**
