-- ============================================================================
-- FIX: PRODUCTOS IMPORTADOS NO VISIBLES EN FRONT OFFICE
-- ============================================================================
-- Este script corrige los problemas más comunes que impiden que los productos
-- importados se visualicen en el front office de PrestaShop 9
--
-- ANTES DE EJECUTAR:
-- 1. Haz un BACKUP completo de la base de datos
-- 2. Ejecuta primero DIAGNOSTIC_PRODUCT_VISIBILITY.sql
-- 3. Revisa los problemas detectados
-- 4. Ajusta las variables de configuración abajo
--
-- DESPUÉS DE EJECUTAR:
-- 1. Limpia la caché de PrestaShop
-- 2. Regenera el índice de búsqueda
-- 3. Verifica los productos en el front office
-- ============================================================================

-- CONFIGURACIÓN
SET @shop_id = 1;        -- ID de tu tienda (normalmente 1)
SET @lang_id = 1;        -- ID de idioma principal (1=Español)
SET @id_shop_group = 1;  -- ID del grupo de tiendas

-- ============================================================================
-- PROBLEMA 1: FALTA ps_product_shop (CRÍTICO)
-- ============================================================================
-- Los productos DEBEN tener un registro en ps_product_shop para cada tienda
-- Este es el problema MÁS COMÚN en migraciones
-- ============================================================================

SELECT '>>> PASO 1: Creando registros faltantes en ps_product_shop...' AS 'PROCESO';

INSERT INTO ps_product_shop (
    id_product,
    id_shop,
    id_category_default,
    id_tax_rules_group,
    on_sale,
    online_only,
    ecotax,
    minimal_quantity,
    low_stock_threshold,
    low_stock_alert,
    price,
    wholesale_price,
    unity,
    unit_price_ratio,
    additional_shipping_cost,
    customizable,
    uploadable_files,
    text_fields,
    active,
    redirect_type,
    id_type_redirected,
    available_for_order,
    available_date,
    show_condition,
    condition,
    show_price,
    indexed,
    visibility,
    cache_default_attribute,
    advanced_stock_management,
    date_add,
    date_upd,
    pack_stock_type
)
SELECT 
    p.id_product,
    @shop_id AS id_shop,
    p.id_category_default,
    p.id_tax_rules_group,
    p.on_sale,
    p.online_only,
    p.ecotax,
    p.minimal_quantity,
    COALESCE(p.low_stock_threshold, NULL),
    COALESCE(p.low_stock_alert, 0),
    p.price,
    p.wholesale_price,
    COALESCE(p.unity, ''),
    p.unit_price_ratio,
    p.additional_shipping_cost,
    p.customizable,
    p.uploadable_files,
    p.text_fields,
    p.active,
    COALESCE(p.redirect_type, '404'),
    COALESCE(p.id_type_redirected, 0),
    p.available_for_order,
    p.available_date,
    COALESCE(p.show_condition, 0),
    COALESCE(p.condition, 'new'),
    COALESCE(p.show_price, 1),
    COALESCE(p.indexed, 1),
    COALESCE(p.visibility, 'both'),
    COALESCE(p.cache_default_attribute, 0),
    COALESCE(p.advanced_stock_management, 0),
    p.date_add,
    p.date_upd,
    COALESCE(p.pack_stock_type, 0)
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE ps.id_product IS NULL;

SELECT CONCAT('✅ Registros creados en ps_product_shop: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 2: VISIBILIDAD INCORRECTA
-- ============================================================================
-- Asegura que los productos tengan visibility = 'both'
-- ============================================================================

SELECT '>>> PASO 2: Corrigiendo visibilidad de productos...' AS 'PROCESO';

-- Actualizar en ps_product
UPDATE ps_product 
SET visibility = 'both'
WHERE visibility IN ('none', 'search', 'catalog')
  AND active = 1;

SELECT CONCAT('✅ Productos actualizados en ps_product: ', ROW_COUNT()) AS 'RESULTADO';

-- Actualizar en ps_product_shop
UPDATE ps_product_shop
SET visibility = 'both'
WHERE visibility IN ('none', 'search', 'catalog')
  AND active = 1
  AND id_shop = @shop_id;

SELECT CONCAT('✅ Productos actualizados en ps_product_shop: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 3: PRODUCTOS SIN CATEGORÍA
-- ============================================================================
-- Asigna categoría por defecto (Home = 2) a productos sin categoría
-- ============================================================================

SELECT '>>> PASO 3: Asignando categorías faltantes...' AS 'PROCESO';

-- Insertar en ps_category_product
INSERT INTO ps_category_product (id_category, id_product, position)
SELECT 
    COALESCE(p.id_category_default, 2) AS id_category,
    p.id_product,
    COALESCE(
        (SELECT MAX(position) + 1 
         FROM ps_category_product cp2 
         WHERE cp2.id_category = COALESCE(p.id_category_default, 2)),
        1
    ) AS position
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
WHERE cp.id_product IS NULL;

SELECT CONCAT('✅ Productos asignados a categorías: ', ROW_COUNT()) AS 'RESULTADO';

-- Actualizar id_category_default si es NULL
UPDATE ps_product
SET id_category_default = 2
WHERE id_category_default IS NULL OR id_category_default = 0;

SELECT CONCAT('✅ Categorías por defecto actualizadas: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 4: LINK_REWRITE VACÍO O NULL
-- ============================================================================
-- Genera link_rewrite automáticamente desde el nombre del producto
-- ============================================================================

SELECT '>>> PASO 4: Generando link_rewrite faltantes...' AS 'PROCESO';

UPDATE ps_product_lang pl
JOIN ps_product p ON pl.id_product = p.id_product
SET pl.link_rewrite = LOWER(
    REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(TRIM(pl.name), ' ', '-'),
                        'á', 'a'),
                    'é', 'e'),
                'í', 'i'),
            'ó', 'o'),
        'ú', 'u'),
    'ñ', 'n')
)
WHERE pl.link_rewrite IS NULL 
   OR pl.link_rewrite = ''
   OR pl.link_rewrite = '-';

SELECT CONCAT('✅ Link rewrite generados: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 5: STOCK NO CONFIGURADO
-- ============================================================================
-- Crea registros en ps_stock_available para productos sin configuración
-- ============================================================================

SELECT '>>> PASO 5: Configurando stock faltante...' AS 'PROCESO';

INSERT INTO ps_stock_available (
    id_product,
    id_product_attribute,
    id_shop,
    id_shop_group,
    quantity,
    depends_on_stock,
    out_of_stock,
    physical_quantity,
    reserved_quantity,
    location
)
SELECT 
    p.id_product,
    0 AS id_product_attribute,
    @shop_id AS id_shop,
    @id_shop_group AS id_shop_group,
    COALESCE(p.quantity, 0) AS quantity,
    0 AS depends_on_stock,
    COALESCE(p.out_of_stock, 2) AS out_of_stock,
    COALESCE(p.quantity, 0) AS physical_quantity,
    0 AS reserved_quantity,
    '' AS location
FROM ps_product p
LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product 
    AND sa.id_product_attribute = 0 
    AND sa.id_shop = @shop_id
WHERE sa.id_product IS NULL;

SELECT CONCAT('✅ Configuraciones de stock creadas: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 6: ÍNDICE DE BÚSQUEDA
-- ============================================================================
-- Marca productos para reindexación
-- ============================================================================

SELECT '>>> PASO 6: Marcando productos para reindexación...' AS 'PROCESO';

UPDATE ps_product 
SET indexed = 0
WHERE active = 1;

UPDATE ps_product_shop
SET indexed = 0
WHERE active = 1 AND id_shop = @shop_id;

SELECT '✅ Productos marcados para reindexación' AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 7: ACTIVAR PRODUCTOS
-- ============================================================================
-- Asegura que los productos estén activos
-- ============================================================================

SELECT '>>> PASO 7: Activando productos importados...' AS 'PROCESO';

UPDATE ps_product 
SET active = 1
WHERE active = 0 
  AND id_product NOT IN (
      SELECT id_product 
      FROM ps_product 
      WHERE state = 0 OR state IS NULL
  );

UPDATE ps_product_shop
SET active = 1
WHERE active = 0 
  AND id_shop = @shop_id
  AND id_product IN (SELECT id_product FROM ps_product WHERE active = 1);

SELECT CONCAT('✅ Productos activados: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- PROBLEMA 8: ATRIBUTOS POR DEFECTO
-- ============================================================================
-- Limpia cache_default_attribute si no hay combinaciones
-- ============================================================================

SELECT '>>> PASO 8: Limpiando cache de atributos...' AS 'PROCESO';

UPDATE ps_product p
LEFT JOIN ps_product_attribute pa ON p.id_product = pa.id_product
SET p.cache_default_attribute = 0
WHERE pa.id_product IS NULL AND p.cache_default_attribute != 0;

UPDATE ps_product_shop ps
LEFT JOIN ps_product_attribute pa ON ps.id_product = pa.id_product
SET ps.cache_default_attribute = 0
WHERE pa.id_product IS NULL 
  AND ps.cache_default_attribute != 0
  AND ps.id_shop = @shop_id;

SELECT CONCAT('✅ Cache de atributos limpiado: ', ROW_COUNT()) AS 'RESULTADO';

-- ============================================================================
-- VERIFICACIÓN FINAL
-- ============================================================================

SELECT '>>> VERIFICACIÓN FINAL...' AS 'PROCESO';

SELECT 
    'Total productos activos' AS verificacion,
    COUNT(*) AS cantidad
FROM ps_product 
WHERE active = 1

UNION ALL

SELECT 
    'Con ps_product_shop' AS verificacion,
    COUNT(DISTINCT ps.id_product) AS cantidad
FROM ps_product_shop ps
WHERE ps.id_shop = @shop_id AND ps.active = 1

UNION ALL

SELECT 
    'Con categoría asignada' AS verificacion,
    COUNT(DISTINCT cp.id_product) AS cantidad
FROM ps_category_product cp
JOIN ps_product p ON cp.id_product = p.id_product
WHERE p.active = 1

UNION ALL

SELECT 
    'Con visibilidad = both' AS verificacion,
    COUNT(*) AS cantidad
FROM ps_product
WHERE active = 1 AND visibility = 'both'

UNION ALL

SELECT 
    'Con link_rewrite válido' AS verificacion,
    COUNT(DISTINCT pl.id_product) AS cantidad
FROM ps_product_lang pl
JOIN ps_product p ON pl.id_product = p.id_product
WHERE p.active = 1 
  AND pl.link_rewrite IS NOT NULL 
  AND pl.link_rewrite != '';

-- ============================================================================
-- INSTRUCCIONES POST-EJECUCIÓN
-- ============================================================================

SELECT '
╔═══════════════════════════════════════════════════════════════════╗
║            CORRECCIONES APLICADAS EXITOSAMENTE                    ║
╚═══════════════════════════════════════════════════════════════════╝

PRÓXIMOS PASOS OBLIGATORIOS:

1️⃣ LIMPIAR CACHÉ (MUY IMPORTANTE)
   Via SSH:
   cd /path/to/prestashop
   rm -rf var/cache/*
   
   O via Back Office:
   Parámetros Avanzados → Rendimiento → Limpiar caché

2️⃣ REGENERAR ÍNDICE DE BÚSQUEDA
   Back Office → Preferencias → Buscar
   Click en "Regenerar índice completo"
   Esperar a que termine (puede tardar varios minutos)

3️⃣ REGENERAR HTACCESS Y ROBOTS.TXT
   Back Office → Preferencias → SEO y URLs
   Click en "Generar archivo robots.txt"
   Click en "Regenerar .htaccess"

4️⃣ VERIFICAR PRODUCTOS
   - Ve al Front Office
   - Busca algunos productos importados
   - Verifica que aparezcan en categorías
   - Prueba la búsqueda

5️⃣ SI AÚN NO SE VEN:
   - Ejecuta de nuevo DIAGNOSTIC_PRODUCT_VISIBILITY.sql
   - Revisa los logs de error de PrestaShop en /var/logs/
   - Verifica permisos de carpetas de imágenes

═══════════════════════════════════════════════════════════════════

' AS 'INSTRUCCIONES';

-- ============================================================================
-- FIN DEL SCRIPT DE CORRECCIÓN
-- ============================================================================
