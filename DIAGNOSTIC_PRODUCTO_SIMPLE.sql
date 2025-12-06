-- ============================================================================
-- DIAGNOSTIC SIMPLE DEL PRODUCTO - SIN ps_category_group
-- ============================================================================
-- Version sin verificar ps_category_group
-- ============================================================================

-- CONFIGURACION
SET @producto_id = 523;  -- Cambia por el ID del producto problemático
SET @shop_id = 1;
SET @lang_id = 1;

-- ============================================================================
-- 1. INFO BASICA
-- ============================================================================

SELECT 'INFO BASICA DEL PRODUCTO' AS seccion;

SELECT 
    id_product,
    active AS activo,
    available_for_order AS disponible,
    visibility AS visibilidad,
    id_category_default AS categoria_defecto
FROM ps_product 
WHERE id_product = @producto_id;

-- ============================================================================
-- 2. VERIFICAR ps_product_shop
-- ============================================================================

SELECT 'VERIFICAR ps_product_shop (CRITICO)' AS seccion;

SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '*** FALTA EN ps_product_shop - ESTE ES EL PROBLEMA ***'
        ELSE 'OK: Existe en ps_product_shop'
    END AS diagnostico
FROM ps_product_shop
WHERE id_product = @producto_id AND id_shop = @shop_id;

SELECT 
    id_product,
    id_shop,
    active AS activo_shop,
    visibility AS visibilidad_shop
FROM ps_product_shop
WHERE id_product = @producto_id AND id_shop = @shop_id;

-- ============================================================================
-- 3. VERIFICAR NOMBRE
-- ============================================================================

SELECT 'NOMBRE Y URL' AS seccion;

SELECT 
    id_product,
    id_lang,
    name AS nombre,
    link_rewrite AS url
FROM ps_product_lang
WHERE id_product = @producto_id;

-- ============================================================================
-- 4. VERIFICAR CATEGORIAS
-- ============================================================================

SELECT 'CATEGORIAS ASIGNADAS' AS seccion;

SELECT 
    cp.id_product,
    cp.id_category,
    c.active AS categoria_activa,
    cl.name AS nombre_categoria
FROM ps_category_product cp
JOIN ps_category c ON cp.id_category = c.id_category
JOIN ps_category_lang cl ON c.id_category = cl.id_category AND cl.id_lang = @lang_id
WHERE cp.id_product = @producto_id;

-- ============================================================================
-- RESUMEN
-- ============================================================================

SELECT 'RESUMEN DEL DIAGNOSTICO' AS seccion;

SELECT 
    'Existe en ps_product' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END AS resultado
FROM ps_product WHERE id_product = @producto_id

UNION ALL

SELECT 
    'Activo en ps_product' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO - ACTIVAR' END AS resultado
FROM ps_product WHERE id_product = @producto_id AND active = 1

UNION ALL

SELECT 
    'Existe en ps_product_shop' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO - CRITICO: EJECUTAR FIX' END AS resultado
FROM ps_product_shop WHERE id_product = @producto_id AND id_shop = @shop_id

UNION ALL

SELECT 
    'Activo en ps_product_shop' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO - ACTIVAR' END AS resultado
FROM ps_product_shop WHERE id_product = @producto_id AND id_shop = @shop_id AND active = 1

UNION ALL

SELECT 
    'Tiene categoria asignada' AS verificacion,
    CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO - ASIGNAR CATEGORIA' END AS resultado
FROM ps_category_product WHERE id_product = @producto_id;

-- ============================================================================
-- FIN
-- ============================================================================
