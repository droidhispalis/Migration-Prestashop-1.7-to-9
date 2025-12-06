-- ============================================================================
-- CREAR TABLA ps_category_group SI NO EXISTE
-- ============================================================================
-- Esta tabla es CRITICA para los permisos de visualización de productos
-- Si no existe, los productos darán error "No tiene acceso a este producto"
-- ============================================================================

-- Verificar si existe
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'La tabla ps_category_group YA EXISTE - No se creara'
        ELSE 'La tabla ps_category_group NO EXISTE - Se creara ahora'
    END AS estado
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ps_category_group';

-- Crear tabla si no existe
CREATE TABLE IF NOT EXISTS `ps_category_group` (
  `id_category` int(10) unsigned NOT NULL,
  `id_group` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_category`,`id_group`),
  KEY `id_category` (`id_category`),
  KEY `id_group` (`id_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Tabla ps_category_group verificada/creada' AS resultado;

-- ============================================================================
-- ASIGNAR PERMISOS A TODAS LAS CATEGORIAS
-- ============================================================================
-- Dar acceso a los 3 grupos principales: Visitantes (1), Invitados (2), Clientes (3)
-- ============================================================================

-- Limpiar registros existentes para evitar duplicados
DELETE FROM ps_category_group;

SELECT 'Asignando permisos a todas las categorias...' AS proceso;

-- Insertar permisos para todas las categorías activas
INSERT INTO ps_category_group (id_category, id_group)
SELECT 
    c.id_category,
    g.id_group
FROM ps_category c
CROSS JOIN (
    SELECT 1 AS id_group
    UNION ALL SELECT 2
    UNION ALL SELECT 3
) g
WHERE c.active = 1;

SELECT CONCAT('Permisos asignados: ', ROW_COUNT(), ' registros creados') AS resultado;

-- ============================================================================
-- VERIFICACION
-- ============================================================================

SELECT 'VERIFICACION DE PERMISOS' AS seccion;

SELECT 
    'Categorias con permisos' AS metrica,
    COUNT(DISTINCT id_category) AS cantidad
FROM ps_category_group

UNION ALL

SELECT 
    'Total de permisos asignados' AS metrica,
    COUNT(*) AS cantidad
FROM ps_category_group

UNION ALL

SELECT 
    'Categorias activas' AS metrica,
    COUNT(*) AS cantidad
FROM ps_category
WHERE active = 1

UNION ALL

SELECT 
    'Grupos con acceso' AS metrica,
    COUNT(DISTINCT id_group) AS cantidad
FROM ps_category_group;

-- Mostrar algunas categorías con sus permisos
SELECT 'EJEMPLO DE PERMISOS ASIGNADOS (primeras 10 categorias)' AS seccion;

SELECT 
    c.id_category,
    cl.name AS nombre_categoria,
    GROUP_CONCAT(DISTINCT cg.id_group ORDER BY cg.id_group) AS grupos_con_acceso,
    CASE 
        WHEN COUNT(DISTINCT cg.id_group) >= 3 THEN 'OK - Todos los grupos'
        WHEN COUNT(DISTINCT cg.id_group) > 0 THEN 'PARCIAL - Falta algun grupo'
        ELSE 'ERROR - Sin permisos'
    END AS estado
FROM ps_category c
LEFT JOIN ps_category_lang cl ON c.id_category = cl.id_category AND cl.id_lang = 1
LEFT JOIN ps_category_group cg ON c.id_category = cg.id_category
WHERE c.active = 1
GROUP BY c.id_category
LIMIT 10;

-- ============================================================================
-- INSTRUCCIONES POST-EJECUCION
-- ============================================================================

SELECT '
╔═══════════════════════════════════════════════════════════════════╗
║     TABLA ps_category_group CREADA Y CONFIGURADA                  ║
╚═══════════════════════════════════════════════════════════════════╝

PERMISOS ASIGNADOS:
- Visitantes (Grupo 1): Usuarios no registrados
- Invitados (Grupo 2): Invitados sin cuenta  
- Clientes (Grupo 3): Clientes registrados

TODAS las categorias activas ahora tienen acceso para estos 3 grupos.

PROXIMOS PASOS:

1. LIMPIAR CACHE (OBLIGATORIO):
   Back Office > Parametros Avanzados > Rendimiento > Limpiar cache

2. REGENERAR INDICE:
   Back Office > Preferencias > Buscar > Regenerar indice completo

3. VERIFICAR PRODUCTOS:
   - Ve al Front Office
   - Navega por categorias
   - Verifica que los productos YA SE VEN

4. Si aun hay problemas, ejecuta:
   FIX_SIMPLE.sql (para corregir ps_product_shop)

═══════════════════════════════════════════════════════════════════

' AS INSTRUCCIONES;

-- ============================================================================
-- FIN
-- ============================================================================
