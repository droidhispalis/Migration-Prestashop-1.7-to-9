-- ============================================================================
-- DIAGNOSTIC SIMPLE: PRODUCTOS NO VISIBLES - VERSION RAPIDA
-- ============================================================================
-- Este script NO requiere configurar IDs de productos
-- Muestra directamente todos los problemas detectados
-- ============================================================================

-- Configuración de tu tienda (ajusta si es necesario)
SET @shop_id = 1;
SET @lang_id = 1;

-- ============================================================================
-- PROBLEMA #1: PRODUCTOS SIN ps_product_shop (CRÍTICO - 90% de casos)
-- ============================================================================

SELECT 'PROBLEMA CRITICO: Productos sin ps_product_shop' AS diagnostico;

SELECT 
    p.id_product,
    p.active,
    pl.name AS nombre_producto,
    'FALTA en ps_product_shop - NO SE VERA EN FRONT' AS problema
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE ps.id_product IS NULL
  AND p.active = 1
LIMIT 50;

SELECT 
    'Total productos activos sin ps_product_shop' AS resumen,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE ps.id_product IS NULL AND p.active = 1;

-- ============================================================================
-- PROBLEMA #2: PRODUCTOS SIN CATEGORÍA
-- ============================================================================

SELECT 'Productos sin categoria asignada' AS diagnostico;

SELECT 
    p.id_product,
    pl.name AS nombre_producto,
    p.id_category_default,
    'Sin categoria - NO APARECERAN en navegacion' AS problema
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE cp.id_category IS NULL
  AND p.active = 1
LIMIT 50;

SELECT 
    'Total productos activos sin categoria' AS resumen,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
WHERE cp.id_category IS NULL AND p.active = 1;

-- ============================================================================
-- PROBLEMA #3: VISIBILIDAD INCORRECTA
-- ============================================================================

SELECT 'Productos con visibilidad incorrecta' AS diagnostico;

SELECT 
    p.id_product,
    pl.name AS nombre_producto,
    p.visibility,
    'Visibilidad debe ser both' AS problema
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE p.visibility IN ('none', 'search', 'catalog')
  AND p.active = 1
LIMIT 50;

SELECT 
    'Total productos con visibilidad incorrecta' AS resumen,
    COUNT(*) AS cantidad
FROM ps_product p
WHERE p.visibility IN ('none', 'search', 'catalog')
  AND p.active = 1;

-- ============================================================================
-- PROBLEMA #4: SIN LINK_REWRITE
-- ============================================================================

SELECT 'Productos sin URL (link_rewrite)' AS diagnostico;

SELECT 
    p.id_product,
    pl.name AS nombre_producto,
    pl.link_rewrite,
    'Sin URL - dara error 404' AS problema
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = @lang_id
WHERE (pl.link_rewrite IS NULL OR pl.link_rewrite = '' OR pl.link_rewrite = '-')
  AND p.active = 1
LIMIT 50;

SELECT 
    'Total productos sin URL valida' AS resumen,
    COUNT(*) AS cantidad
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product
WHERE (pl.link_rewrite IS NULL OR pl.link_rewrite = '' OR pl.link_rewrite = '-')
  AND p.active = 1;

-- ============================================================================
-- RESUMEN FINAL
-- ============================================================================

SELECT 'RESUMEN DE TODOS LOS PROBLEMAS DETECTADOS' AS resumen_final;

SELECT 
    'Productos sin ps_product_shop' AS tipo_problema,
    COUNT(*) AS cantidad,
    'CRITICO - Ejecutar FIX inmediatamente' AS prioridad
FROM ps_product p
LEFT JOIN ps_product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = @shop_id
WHERE ps.id_product IS NULL AND p.active = 1

UNION ALL

SELECT 
    'Productos sin categoria' AS tipo_problema,
    COUNT(*) AS cantidad,
    'ALTA' AS prioridad
FROM ps_product p
LEFT JOIN ps_category_product cp ON p.id_product = cp.id_product
WHERE cp.id_category IS NULL AND p.active = 1

UNION ALL

SELECT 
    'Productos con visibility incorrecta' AS tipo_problema,
    COUNT(*) AS cantidad,
    'MEDIA' AS prioridad
FROM ps_product p
WHERE p.visibility IN ('none', 'search', 'catalog') AND p.active = 1

UNION ALL

SELECT 
    'Productos sin link_rewrite' AS tipo_problema,
    COUNT(*) AS cantidad,
    'ALTA' AS prioridad
FROM ps_product p
LEFT JOIN ps_product_lang pl ON p.id_product = pl.id_product
WHERE (pl.link_rewrite IS NULL OR pl.link_rewrite = '' OR pl.link_rewrite = '-')
  AND p.active = 1;

-- ============================================================================
-- INSTRUCCIONES
-- ============================================================================

SELECT '
╔═══════════════════════════════════════════════════════════════════╗
║              DIAGNOSTICO COMPLETADO                               ║
╚═══════════════════════════════════════════════════════════════════╝

INTERPRETACION DE RESULTADOS:

Si ves productos en "SIN ps_product_shop":
  → Este es el problema MAS COMUN (90% de casos)
  → Los productos NO apareceran en el front office
  → SOLUCION: Ejecutar FIX_PRODUCT_VISIBILITY.sql

Si ves productos en "SIN categoria":
  → Los productos no apareceran en la navegacion
  → SOLUCION: El script FIX los asignara automaticamente

Si ves productos con "visibility incorrecta":
  → Pueden no aparecer en busqueda o categorias
  → SOLUCION: El script FIX los cambiara a "both"

PROXIMO PASO:
  → Ejecuta FIX_PRODUCT_VISIBILITY.sql para corregir TODO automaticamente

' AS INSTRUCCIONES;

-- ============================================================================
-- FIN DEL DIAGNOSTICO RAPIDO
-- ============================================================================
