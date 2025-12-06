-- ============================================================================
-- FIX SIMPLE: PRODUCTOS INVISIBLES - VERSION COMPATIBLE
-- ============================================================================
-- Version simplificada sin campos que pueden no existir en todas las versiones
-- ============================================================================

-- CONFIGURACIÓN
SET @shop_id = 1;
SET @lang_id = 1;
SET @id_shop_group = 1;

-- ============================================================================
-- PASO 1: CREAR ps_product_shop FALTANTES (MAS IMPORTANTE)
-- ============================================================================

INSERT IGNORE INTO ps_product_shop (
    id_product,
    id_shop,
    id_category_default,
    id_tax_rules_group,
    price,
    wholesale_price,
    active,
    available_for_order,
    visibility,
    indexed,
    date_add,
    date_upd
)
SELECT 
    p.id_product,
    @shop_id,
    COALESCE(p.id_category_default, 2),
    COALESCE(p.id_tax_rules_group, 0),
    p.price,
    p.wholesale_price,
    p.active,
    p.available_for_order,
    'both',
    0,
    p.date_add,
    p.date_upd
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE ps.id_product IS NULL;

SELECT CONCAT('Paso 1 completado: ', ROW_COUNT(), ' registros creados en ps_product_shop') AS Resultado;

-- ============================================================================
-- PASO 2: CORREGIR VISIBILIDAD
-- ============================================================================

UPDATE ps_product 
SET visibility = 'both'
WHERE visibility IN ('none', 'search', 'catalog') AND active = 1;

SELECT CONCAT('Paso 2a completado: ', ROW_COUNT(), ' productos con visibilidad corregida') AS Resultado;

UPDATE ps_product_shop
SET visibility = 'both'
WHERE visibility IN ('none', 'search', 'catalog') AND active = 1 AND id_shop = @shop_id;

SELECT CONCAT('Paso 2b completado: ', ROW_COUNT(), ' productos_shop con visibilidad corregida') AS Resultado;

-- ============================================================================
-- PASO 3: ASIGNAR CATEGORIAS FALTANTES
-- ============================================================================

INSERT IGNORE INTO ps_category_product (id_category, id_product, position)
SELECT 
    COALESCE(p.id_category_default, 2),
    p.id_product,
    1
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
WHERE cp.id_product IS NULL AND p.active = 1;

SELECT CONCAT('Paso 3 completado: ', ROW_COUNT(), ' productos asignados a categorias') AS Resultado;

-- ============================================================================
-- PASO 4: GENERAR LINK_REWRITE
-- ============================================================================

UPDATE ps_product_lang pl
SET pl.link_rewrite = LOWER(
    REPLACE(
        REPLACE(
            REPLACE(
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
                'ñ', 'n'),
            'Á', 'A'),
        'É', 'E'),
    'Í', 'I')
)
WHERE (pl.link_rewrite IS NULL OR pl.link_rewrite = '' OR pl.link_rewrite = '-')
  AND pl.name IS NOT NULL AND pl.name != '';

SELECT CONCAT('Paso 4 completado: ', ROW_COUNT(), ' URLs generadas') AS Resultado;

-- ============================================================================
-- PASO 5: ACTUALIZAR CATEGORIA POR DEFECTO
-- ============================================================================

UPDATE ps_product
SET id_category_default = 2
WHERE id_category_default IS NULL OR id_category_default = 0;

SELECT CONCAT('Paso 5 completado: ', ROW_COUNT(), ' categorias por defecto actualizadas') AS Resultado;

-- ============================================================================
-- PASO 6: MARCAR PARA REINDEXAR
-- ============================================================================

UPDATE ps_product SET indexed = 0 WHERE active = 1;
UPDATE ps_product_shop SET indexed = 0 WHERE active = 1 AND id_shop = @shop_id;

SELECT 'Paso 6 completado: Productos marcados para reindexacion' AS Resultado;

-- ============================================================================
-- PASO 7: ACTIVAR PRODUCTOS
-- ============================================================================

UPDATE ps_product_shop
SET active = 1
WHERE id_shop = @shop_id
  AND id_product IN (SELECT id_product FROM ps_product WHERE active = 1);

SELECT CONCAT('Paso 7 completado: ', ROW_COUNT(), ' productos activados en shop') AS Resultado;

-- ============================================================================
-- VERIFICACION FINAL
-- ============================================================================

SELECT '=== VERIFICACION FINAL ===' AS Estado;

SELECT 
    'Productos activos' AS Tipo,
    COUNT(*) AS Cantidad
FROM ps_product WHERE active = 1

UNION ALL

SELECT 
    'Con ps_product_shop' AS Tipo,
    COUNT(DISTINCT ps.id_product) AS Cantidad
FROM ps_product_shop ps
WHERE ps.id_shop = @shop_id AND ps.active = 1

UNION ALL

SELECT 
    'Con categoria' AS Tipo,
    COUNT(DISTINCT cp.id_product) AS Cantidad
FROM ps_category_product cp
JOIN ps_product p ON cp.id_product = p.id_product
WHERE p.active = 1

UNION ALL

SELECT 
    'Con visibility=both' AS Tipo,
    COUNT(*) AS Cantidad
FROM ps_product WHERE active = 1 AND visibility = 'both';

-- ============================================================================
-- INSTRUCCIONES FINALES
-- ============================================================================

SELECT '
CORRECCION COMPLETADA!

AHORA DEBES:

1. LIMPIAR CACHE:
   Back Office > Parametros Avanzados > Rendimiento > Limpiar cache

2. REGENERAR INDICE:
   Back Office > Preferencias > Buscar > Regenerar indice completo

3. VERIFICAR EN FRONT OFFICE:
   - Navega por las categorias
   - Busca productos
   - Verifica que se vean correctamente

Si aun no se ven, ejecuta DIAGNOSTIC_SIMPLE.sql de nuevo
' AS INSTRUCCIONES;

-- ============================================================================
-- FIN
-- ============================================================================
