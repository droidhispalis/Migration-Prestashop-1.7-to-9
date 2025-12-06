-- ============================================================================
-- FIX PARA UN PRODUCTO ESPECIFICO QUE NO SE VE
-- ============================================================================
-- Este script corrige un producto individual
-- ============================================================================

-- CONFIGURACION - CAMBIA ESTOS VALORES
SET @producto_id = 523;  -- ID del producto que no se ve
SET @shop_id = 1;
SET @lang_id = 1;

-- ============================================================================
-- PASO 1: CREAR ps_product_shop SI NO EXISTE
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
    1,  -- Activar
    1,  -- Disponible para pedidos
    'both',  -- Visibilidad
    0,  -- Marcar para reindexar
    p.date_add,
    NOW()
FROM ps_product p
WHERE p.id_product = @producto_id
  AND NOT EXISTS (
      SELECT 1 FROM ps_product_shop ps 
      WHERE ps.id_product = @producto_id AND ps.id_shop = @shop_id
  );

SELECT CONCAT('Paso 1: ', ROW_COUNT(), ' registro creado en ps_product_shop') AS resultado;

-- ============================================================================
-- PASO 2: ACTIVAR PRODUCTO
-- ============================================================================

UPDATE ps_product 
SET active = 1, visibility = 'both'
WHERE id_product = @producto_id;

SELECT 'Paso 2: Producto activado en ps_product' AS resultado;

-- ============================================================================
-- PASO 3: ACTIVAR EN SHOP
-- ============================================================================

UPDATE ps_product_shop
SET active = 1, visibility = 'both', indexed = 0
WHERE id_product = @producto_id AND id_shop = @shop_id;

SELECT 'Paso 3: Producto activado en ps_product_shop' AS resultado;

-- ============================================================================
-- PASO 4: ASIGNAR CATEGORIA SI NO TIENE
-- ============================================================================

INSERT IGNORE INTO ps_category_product (id_category, id_product, position)
SELECT 
    COALESCE(p.id_category_default, 2),
    @producto_id,
    1
FROM ps_product p
WHERE p.id_product = @producto_id
  AND NOT EXISTS (
      SELECT 1 FROM ps_category_product cp 
      WHERE cp.id_product = @producto_id
  );

SELECT CONCAT('Paso 4: ', ROW_COUNT(), ' categoria asignada') AS resultado;

-- ============================================================================
-- PASO 5: GENERAR URL SI FALTA
-- ============================================================================

UPDATE ps_product_lang pl
SET pl.link_rewrite = LOWER(
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
    'ú', 'u')
)
WHERE pl.id_product = @producto_id
  AND (pl.link_rewrite IS NULL OR pl.link_rewrite = '' OR pl.link_rewrite = '-');

SELECT CONCAT('Paso 5: ', ROW_COUNT(), ' URLs generadas') AS resultado;

-- ============================================================================
-- VERIFICACION FINAL
-- ============================================================================

SELECT 'VERIFICACION FINAL DEL PRODUCTO' AS seccion;

SELECT 
    'Activo en ps_product' AS verificacion,
    CASE WHEN active = 1 THEN 'SI' ELSE 'NO' END AS estado
FROM ps_product WHERE id_product = @producto_id

UNION ALL

SELECT 
    'Activo en ps_product_shop' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END AS estado
FROM ps_product_shop 
WHERE id_product = @producto_id AND id_shop = @shop_id AND active = 1

UNION ALL

SELECT 
    'Tiene categoria' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END AS estado
FROM ps_category_product WHERE id_product = @producto_id

UNION ALL

SELECT 
    'Tiene URL' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END AS estado
FROM ps_product_lang 
WHERE id_product = @producto_id 
  AND link_rewrite IS NOT NULL 
  AND link_rewrite != '';

-- ============================================================================
-- INSTRUCCIONES
-- ============================================================================

SELECT '
CORRECCION COMPLETADA PARA EL PRODUCTO!

AHORA DEBES:

1. LIMPIAR CACHE:
   Back Office > Parametros Avanzados > Rendimiento > Limpiar cache

2. Verificar el producto en el front office

3. Si aun no se ve, puede ser problema de permisos de categoria
   (necesitarias crear la tabla ps_category_group)

' AS INSTRUCCIONES;

-- ============================================================================
-- FIN
-- ============================================================================
