================================================================
  MÓDULO PS178TO9MIGRATION v1.0.0 - COMPATIBLE CON PRESTASHOP 9
================================================================

CAMBIOS EN ESTA VERSIÓN:
========================
✓ Filtra automáticamente columna "meta_keywords" durante exportación
✓ Filtra automáticamente columna "shipping_number" durante exportación
✓ Usa INSERT IGNORE para tablas _lang, _shop, order_detail
✓ Sin BOM - Archivo PHP limpio
✓ SQL exportado es DIRECTAMENTE compatible con PrestaShop 9

INSTALACIÓN:
============
1. Desinstala la versión anterior del módulo (si existe)
2. Sube ps178to9migration.zip a tu PrestaShop 1.7.6
3. Instala el módulo desde el panel de administración
4. Si da error 500, verifica:
   - PHP 7.2+ está instalado
   - Permisos correctos en carpeta modules/
   - No hay archivos corruptos

CÓMO USAR:
==========
1. En PrestaShop 1.7.6:
   - Ve a Módulos > PS Migration 1.7 to 9
   - Selecciona "Export All Tables"
   - Formato: SQL
   - Descarga el archivo .sql

2. El SQL exportado:
   - Ya NO contiene meta_keywords
   - Ya NO contiene shipping_number
   - Usa INSERT IGNORE automáticamente
   - Es compatible con PrestaShop 9

3. En PrestaShop 9:
   - Importa el SQL directamente
   - NO necesitas scripts de procesamiento
   - Categorías tendrán descripciones
   - Productos tendrán nombres completos
   - Pedidos se importarán correctamente

SOLUCIÓN DE PROBLEMAS:
======================
Si el módulo da error 500 al instalar:
1. Verifica log de errores PHP en /var/log/
2. Asegúrate que no hay otro módulo con el mismo nombre
3. Verifica permisos: chmod 755 modules/ps178to9migration/
4. Verifica que PHP puede escribir en cache/ y logs/

SOPORTE:
========
Versión: 1.0.0
Fecha: 2025-12-04
Compatible: PrestaShop 1.7.x a 9.x
