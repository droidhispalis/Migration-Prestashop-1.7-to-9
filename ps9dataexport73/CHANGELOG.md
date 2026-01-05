# CHANGELOG - PS 1.7 → PS 9 Data Export Module

## [1.3.2] - 2025-01-31

### 🔥 CRÍTICO - Solución definitiva para "Shop id 0 is invalid"

#### ✅ Añadido
- **Exportación de tablas críticas de shop**:
  - `shop` - Configuración de tiendas
  - `shop_group` - Grupos de tiendas
  - `shop_url` - URLs de tiendas
  
- **Nuevo servicio `PS9ValidationService.php`**:
  - Validación automática post-importación
  - Detección de configuración de shop faltante
  - Reparación automática de `id_shop` inválidos
  - Verificación de integridad referencial
  
- **Proceso de validación automática**:
  1. Verifica que existe `shop` con `id_shop = 1`
  2. Verifica que existe `shop_group` con `id_shop_group = 1`
  3. Verifica que existe `shop_url` para el dominio actual
  4. Corrige todos los `id_shop = 0` o `NULL` a `id_shop = 1`
  5. Valida que todos los `id_shop` existen en la tabla `shop`
  
- **Creación automática de configuración**:
  - Si falta `shop` ID 1, lo crea automáticamente
  - Si falta `shop_group` ID 1, lo crea automáticamente
  - Si falta `shop_url`, lo crea con el dominio actual

#### 🔧 Modificado
- `TablePlan.php::catalog()`:
  - Añadidas 3 tablas al inicio: `shop`, `shop_group`, `shop_url`
  - **ANTES**: Exportaba solo productos/categorías sin configuración de shop
  - **AHORA**: Exporta configuración completa de shop necesaria para PS9

- `ImportService.php::importFromFile()`:
  - Integrado `PS9ValidationService` al final del proceso de importación
  - Validación y reparación automática después de importar
  - Reporte detallado de errores, warnings y correcciones aplicadas
  
- `ps9dataexport73.php`:
  - Versión actualizada a 1.3.2
  - Descripción actualizada: "Complete migration with PS9 validation + auto-repair + shop config"

#### 📋 Documentación
- **README_MIGRACION_COMPLETA.md**: Guía paso a paso completa
  - Proceso de exportación desde PS 1.7
  - Proceso de importación en PS 9
  - Explicación del problema "Shop id 0 is invalid"
  - Solución implementada
  - Troubleshooting
  
#### 🐛 Corregido
- **BUG CRÍTICO**: PrestaShop 9 quedaba inutilizable después de importar
  - **Causa**: Faltaban tablas `shop`, `shop_group`, `shop_url` en la exportación
  - **Efecto**: PS9 usaba `id_shop = 0` por defecto, provocando ShopException
  - **Solución**: Exportación completa + validación automática + reparación

- **BUG**: Registros con `id_shop = 0` en tablas `*_shop`
  - **Causa**: PS 1.7.6 permitía `id_shop = 0`, PS 9 no
  - **Solución**: Corrección automática a `id_shop = 1` en todas las tablas

- **BUG**: Catálogo inaccesible después de importar
  - **Causa**: Falta de configuración de shop válida
  - **Solución**: Creación automática de `shop`, `shop_group`, `shop_url`

#### ⚠️ Notas de Migración

**Si tienes versión 1.3.1 o anterior**:

1. **NO reinstales PrestaShop 9** - puedes repararlo
2. Actualiza a v1.3.2
3. Si ya importaste con versión anterior:
   - Ejecuta en phpMyAdmin:
     ```sql
     -- Ver si faltan tablas shop
     SELECT COUNT(*) FROM tu_prefijo_shop WHERE id_shop = 1;
     SELECT COUNT(*) FROM tu_prefijo_shop_group WHERE id_shop_group = 1;
     ```
   - Si devuelven 0, re-importa desde PS 1.7 con v1.3.2

**Para nuevas migraciones**:

1. Exporta desde PS 1.7 usando v1.3.2
2. Importa en PS 9 limpio
3. El módulo validará y reparará automáticamente

---

## [1.3.1] - 2025-12-22

### ✅ Añadido
- Validación preflight de datos antes de exportar
- Detección de registros incompatibles con PS9
- Reparación automática de datos incompatibles
- Export SQL data-only (sin CREATE TABLE)
- Export de imágenes en ZIP
- Upload e importación de archivos SQL
- Backup automático antes de importar
- Rollback automático si falla importación

### 🔧 Modificado
- Interfaz dividida en tabs: Exportar / Importar
- Mejor manejo de archivos grandes
- Límites PHP configurables

---

## [1.3.0] - 2025-12-20

### ✅ Añadido
- Export SQL básico de catálogo
- Export de clientes (opcional)
- Export de pedidos (opcional)
- Generación de archivos SQL descargables

---

## [1.2.0] - 2025-12-15

### ✅ Añadido
- Módulo inicial con export básico
- Soporte para PHP 7.3+
- Compatible con PrestaShop 1.7.6

---

## Notas

### Breaking Changes

- **v1.3.2**: Cambia el formato de exportación (añade tablas shop). **NO compatible** con imports de v1.3.1 o anterior.
  - Si ya exportaste con v1.3.1, re-exporta desde PS 1.7 con v1.3.2

### Deprecations

- Ninguna

### Known Issues

- **Imágenes**: El módulo NO copia físicamente las imágenes. Debes subir manualmente el ZIP de imágenes generado.
- **Módulos**: Los módulos instalados NO se exportan. Debes reinstalarlos en PS9.
- **Configuración**: La configuración de la tienda NO se exporta (transportes, pagos, etc.).

### Roadmap

- [ ] v1.4.0: Export incremental (solo cambios desde última exportación)
- [ ] v1.5.0: Migración de módulos configurados
- [ ] v1.6.0: Interfaz CLI para migraciones automatizadas
- [ ] v2.0.0: Soporte para PrestaShop 8.x → 9.x
