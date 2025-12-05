-- ============================================================================
-- DIAGNOSTIC: PRODUCTOS NO VISIBLES EN FRONT OFFICE DESPUÉS DE MIGRACIÓN
-- ============================================================================
-- Este script compara productos importados vs productos nuevos
-- para identificar diferencias que causan problemas de visibilidad
--
-- INSTRUCCIONES:
-- 1. Identifica un producto IMPORTADO que NO se ve en el front
-- 2. Identifica un producto NUEVO que SÍ se ve correctamente
-- 3. Reemplaza los IDs en las variables @imported_product y @new_product
-- 4. Ejecuta este script completo en phpMyAdmin
-- ============================================================================

-- CONFIGURACIÓN: Cambia estos valores con tus IDs de productos
SET @imported_product = 1;  -- ID de un producto importado que NO se ve
SET @new_product = 100;      -- ID de un producto nuevo que SÍ se ve
SET @shop_id = 1;           -- ID de tu tienda (normalmente 1)
SET @lang_id = 1;           -- ID de tu idioma (1=Español, 2=English)

-- ============================================================================
-- PARTE 1: COMPARACIÓN BÁSICA DE PRODUCTOS
-- ============================================================================

SELECT '=== COMPARACIÓN DE TABLA ps_product ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    p.id_product,
    p.active,
    p.available_for_order,
    p.visibility,
    p.id_category_default,
    p.indexed,
    p.cache_is_pack,
    p.state,
    p.id_shop_default
FROM ps_product p
WHERE p.id_product = @imported_product

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    p.id_product,
    p.active,
    p.available_for_order,
    p.visibility,
    p.id_category_default,
    p.indexed,
    p.cache_is_pack,
    p.state,
    p.id_shop_default
FROM ps_product p
WHERE p.id_product = @new_product;

-- ============================================================================
-- PARTE 2: VERIFICAR ps_product_shop (CRÍTICO)
-- ============================================================================

SELECT '=== COMPARACIÓN DE ps_product_shop (CRÍTICO) ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    ps.id_product,
    ps.id_shop,
    ps.active,
    ps.available_for_order,
    ps.visibility,
    ps.indexed,
    CASE WHEN ps.id_product IS NULL THEN '❌ FALTA REGISTRO' ELSE '✅ EXISTE' END AS estado
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE p.id_product = @imported_product

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    ps.id_product,
    ps.id_shop,
    ps.active,
    ps.available_for_order,
    ps.visibility,
    ps.indexed,
    CASE WHEN ps.id_product IS NULL THEN '❌ FALTA REGISTRO' ELSE '✅ EXISTE' END AS estado
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE p.id_product = @new_product;

-- ============================================================================
-- PARTE 3: VERIFICAR ps_product_lang
-- ============================================================================

SELECT '=== COMPARACIÓN DE ps_product_lang ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    pl.id_product,
    pl.id_lang,
    pl.name,
    pl.link_rewrite,
    LENGTH(pl.description) AS desc_length,
    LENGTH(pl.description_short) AS short_desc_length,
    CASE WHEN pl.id_product IS NULL THEN '❌ FALTA REGISTRO' ELSE '✅ EXISTE' END AS estado
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE p.id_product = @imported_product

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    pl.id_product,
    pl.id_lang,
    pl.name,
    pl.link_rewrite,
    LENGTH(pl.description) AS desc_length,
    LENGTH(pl.description_short) AS short_desc_length,
    CASE WHEN pl.id_product IS NULL THEN '❌ FALTA REGISTRO' ELSE '✅ EXISTE' END AS estado
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE p.id_product = @new_product;

-- ============================================================================
-- PARTE 4: VERIFICAR CATEGORÍAS
-- ============================================================================

SELECT '=== COMPARACIÓN DE CATEGORÍAS (ps_category_product) ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    cp.id_product,
    cp.id_category,
    cp.position,
    c.active AS categoria_activa,
    CASE WHEN cp.id_product IS NULL THEN '❌ SIN CATEGORÍA' ELSE '✅ CON CATEGORÍA' END AS estado
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
LEFT JOIN ps_category c ON cp.id_category = c.id_category
WHERE p.id_product = @imported_product

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    cp.id_product,
    cp.id_category,
    cp.position,
    c.active AS categoria_activa,
    CASE WHEN cp.id_product IS NULL THEN '❌ SIN CATEGORÍA' ELSE '✅ CON CATEGORÍA' END AS estado
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
LEFT JOIN ps_category c ON cp.id_category = c.id_category
WHERE p.id_product = @new_product;

-- ============================================================================
-- PARTE 5: VERIFICAR STOCK
-- ============================================================================

SELECT '=== COMPARACIÓN DE STOCK (ps_stock_available) ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    sa.id_product,
    sa.quantity,
    sa.out_of_stock,
    CASE WHEN sa.id_product IS NULL THEN '❌ SIN STOCK CONFIG' ELSE '✅ TIENE STOCK CONFIG' END AS estado
FROM ps_product p
LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = @shop_id
WHERE p.id_product = @imported_product

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    sa.id_product,
    sa.quantity,
    sa.out_of_stock,
    CASE WHEN sa.id_product IS NULL THEN '❌ SIN STOCK CONFIG' ELSE '✅ TIENE STOCK CONFIG' END AS estado
FROM ps_product p
LEFT JOIN ps_stock_available sa ON p.id_product = sa.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = @shop_id
WHERE p.id_product = @new_product;

-- ============================================================================
-- PARTE 6: VERIFICAR URL REWRITE
-- ============================================================================

SELECT '=== COMPARACIÓN DE URL REWRITE (ps_meta_lang) ===' AS 'DIAGNOSTIC';

SELECT 
    'IMPORTADO' AS tipo,
    ml.id_meta,
    ml.id_lang,
    ml.url_rewrite,
    CASE WHEN ml.id_meta IS NULL THEN '❌ SIN URL REWRITE' ELSE '✅ TIENE URL REWRITE' END AS estado
FROM ps_product p
LEFT JOIN ps_meta_lang ml ON ml.id_lang = @lang_id
WHERE p.id_product = @imported_product
LIMIT 1

UNION ALL

SELECT 
    'NUEVO' AS tipo,
    ml.id_meta,
    ml.id_lang,
    ml.url_rewrite,
    CASE WHEN ml.id_meta IS NULL THEN '❌ SIN URL REWRITE' ELSE '✅ TIENE URL REWRITE' END AS estado
FROM ps_product p
LEFT JOIN ps_meta_lang ml ON ml.id_lang = @lang_id
WHERE p.id_product = @new_product
LIMIT 1;

-- ============================================================================
-- PARTE 7: BÚSQUEDA DE PROBLEMAS COMUNES EN TODOS LOS PRODUCTOS IMPORTADOS
-- ============================================================================

SELECT '=== PRODUCTOS SIN ps_product_shop (PROBLEMA CRÍTICO) ===' AS 'DIAGNOSTIC';

SELECT 
    p.id_product,
    p.active,
    pl.name,
    'FALTA en ps_product_shop' AS problema
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE ps.id_product IS NULL
LIMIT 20;

-- ----------------------------------------------------------------------------

SELECT '=== PRODUCTOS SIN CATEGORÍA ===' AS 'DIAGNOSTIC';

SELECT 
    p.id_product,
    pl.name,
    'Sin categoría asignada' AS problema
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE cp.id_category IS NULL
LIMIT 20;

-- ----------------------------------------------------------------------------

SELECT '=== PRODUCTOS CON visibility = none ===' AS 'DIAGNOSTIC';

SELECT 
    p.id_product,
    pl.name,
    p.visibility,
    'Visibilidad = none' AS problema
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE p.visibility = 'none'
LIMIT 20;

-- ----------------------------------------------------------------------------

SELECT '=== PRODUCTOS SIN link_rewrite ===' AS 'DIAGNOSTIC';

SELECT 
    p.id_product,
    pl.name,
    pl.link_rewrite,
    'link_rewrite vacío o NULL' AS problema
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE pl.link_rewrite IS NULL OR pl.link_rewrite = ''
LIMIT 20;

-- ============================================================================
-- RESUMEN FINAL
-- ============================================================================

SELECT '=== RESUMEN DE PROBLEMAS DETECTADOS ===' AS 'DIAGNOSTIC';

SELECT 
    'Productos sin ps_product_shop' AS tipo_problema,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE ps.id_product IS NULL

UNION ALL

SELECT 
    'Productos sin categoría' AS tipo_problema,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
WHERE cp.id_category IS NULL

UNION ALL

SELECT 
    'Productos con visibility=none' AS tipo_problema,
    COUNT(*) AS cantidad
FROM ps_product p
WHERE p.visibility = 'none'

UNION ALL

SELECT 
    'Productos sin link_rewrite' AS tipo_problema,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product
WHERE pl.link_rewrite IS NULL OR pl.link_rewrite = '';

-- ============================================================================
-- FIN DEL DIAGNÓSTICO
-- ============================================================================
-- 
-- PRÓXIMOS PASOS:
-- 1. Revisa los resultados de este diagnóstico
-- 2. Identifica qué problemas afectan a tus productos importados
-- 3. Ejecuta el script de corrección correspondiente: FIX_PRODUCT_VISIBILITY.sql
-- ============================================================================
