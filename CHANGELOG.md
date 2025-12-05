# Changelog - Módulo de Migración PrestaShop 1.7.8 → 9

## Versión 1.0.2 - 2025-11-29 🔥 CRÍTICO

### ✅ Corrección CRÍTICA - Adaptación Automática de Prefijos de Tabla

#### Problema Detectado
- **Error**: `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'prestashop.ps_XXXXX' doesn't exist`
- **Causa**: Los archivos exportados de PrestaShop 1.7.6 tienen prefijo `ps_`, pero el PrestaShop 9 de destino puede tener un prefijo diferente configurado en `config/parameters.php`
- **Impacto**: La importación fallaba completamente al no encontrar las tablas

#### Solución Implementada
✅ **Adaptación Automática de Prefijos**
- El sistema ahora detecta automáticamente el prefijo configurado en el destino (`_DB_PREFIX_`)
- Convierte los nombres de tabla del archivo de origen al prefijo del destino
- Soporta múltiples formatos de prefijo: `ps_`, `prestashop_`, `presta_`, `shop_`, etc.
- Búsqueda inteligente case-insensitive de tablas

### 🆕 Nuevo Modo "Skip Duplicates" (RECOMENDADO)

#### Problema de Errores Duplicados
- **Error**: `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry`
- **Causa**: Al importar en modo "append" sobre una base de datos que YA tiene datos, se intentan insertar registros con IDs que ya existen
- **Resultado**: Miles de advertencias molestas aunque la importación funcione

#### Solución - Nuevo Modo
✅ **Skip Duplicates** (Omitir Duplicados)
- **NUEVO modo por defecto** para importación
- Ignora silenciosamente registros que ya existen (por clave primaria)
- Solo importa registros nuevos
- **Sin advertencias molestas** de duplicados
- Ideal cuando ya tienes datos y solo quieres añadir lo que falta

#### Modos de Importación Actualizados
1. **Skip Duplicates** ⭐ RECOMENDADO - Omite duplicados sin errores
2. **Append** - Intenta insertar todo (muestra warnings de duplicados)
3. **Update** - Actualiza registros existentes con nuevos datos
4. **Replace** - ⚠️ PELIGROSO - Borra todo antes de importar

### 📊 Mejoras en Reportes

**Antes:**
```
⚠️ Hay 52,061 advertencias:
- INSERT error in ps_address: SQLSTATE[23000]: 1062 Duplicate entry '2'...
- INSERT error in ps_address: SQLSTATE[23000]: 1062 Duplicate entry '3'...
- INSERT error in ps_address: SQLSTATE[23000]: 1062 Duplicate entry '4'...
[... 52,058 advertencias más ...]
```

**Ahora (modo Skip Duplicates):**
```
✅ Import completed successfully! Tables imported: 48, Rows imported: 125
⚠️ Import warnings:
- Table ps_address: 125 rows inserted, 45,234 duplicates skipped
- Table ps_customer: 50 rows inserted, 6,827 duplicates skipped
```

### 🎯 Interfaz Mejorada

- Selector de modo con **descripciones claras**
- **Ayuda contextual** que cambia según el modo seleccionado
- Colores: ✅ Verde (skip), ⚠️ Amarillo (append), ℹ️ Azul (update), 🔴 Rojo (replace)
- Modo "Skip Duplicates" **seleccionado por defecto**

#### Funciones Añadidas
```php
adaptTableName($tableName)
- Convierte nombre de tabla del formato origen al destino
- Ejemplo: ps_customer → prestashop_ps_customer

findActualTableName($tableName)
- Busca el nombre real de la tabla en la base de datos destino
- Maneja diferencias de mayúsculas/minúsculas
- Devuelve el nombre exacto que existe en la BD
```

#### Archivos Modificados
1. **classes/MigrationService.php**
   - Línea 72-95: Nueva función `adaptTableName()`
   - Línea 97-130: Nueva función `findActualTableName()`
   - Actualizado `importFromJSON()` para usar adaptación
   - Actualizado `importFromSQL()` para reemplazar nombres de tabla en statements
   - Actualizado `importFromMultiTableCSV()` para usar adaptación

2. **controllers/admin/AdminPs178to9migrationController.php**
   - Línea 55: Muestra el prefijo actual en la información
   - Línea 115: Aviso sobre adaptación automática de prefijos

### 📊 Cómo Funciona la Adaptación

**Antes** (Fallaba):
```sql
-- Archivo de exportación 1.7.6
INSERT INTO `ps_customer` VALUES (...);

-- PrestaShop 9 buscaba
prestashop.ps_customer  ❌ No existe (el prefijo real es prestashop_ps_)
```

**Ahora** (Funciona):
```sql
-- Archivo de exportación 1.7.6
INSERT INTO `ps_customer` VALUES (...);

-- El sistema adapta automáticamente
1. Detecta: tabla origen = ps_customer
2. Detecta: prefijo destino = prestashop_ps_
3. Adapta: ps_customer → prestashop_ps_customer
4. Importa en: prestashop_ps_customer ✅
```

### 🎯 Beneficios

✅ **No requiere edición manual** de archivos de exportación  
✅ **Funciona con cualquier prefijo** configurado  
✅ **Compatibilidad total** entre versiones  
✅ **Mensajes claros** sobre tablas adaptadas  

---

## Versión 1.0.1 - 2025-11-29

### ✅ Correcciones

#### Error de exportación SQL corregido
- **Problema**: Error SQL "SQLSTATE[42000]: Syntax error or access violation: 1064 ... near 'LIMIT 1'"
- **Causa**: El método `getValue()` añadía automáticamente `LIMIT 1` a la consulta `SHOW CREATE TABLE`, lo cual es sintácticamente incorrecto
- **Solución**: Reemplazado `getValue()` por `executeS()` para obtener correctamente el CREATE TABLE statement
- **Archivo modificado**: `classes/MigrationService.php` línea 206

### 🚀 Nuevas Funcionalidades

#### 1. Importación Completa (JSON, CSV, SQL)
- ✅ Importación desde archivos JSON
- ✅ Importación desde archivos SQL
- ✅ Importación desde archivos CSV (formato multi-tabla)
- ✅ Importación desde archivos ZIP (múltiples archivos)

#### 2. Validación Inteligente de Esquema
- **Adaptación automática de campos**: El sistema detecta diferencias entre el esquema de la versión 1.7.6 y 9.x
- **Filtrado de campos incompatibles**: Los campos que no existen en la versión destino se omiten automáticamente
- **Valores por defecto inteligentes**: Se asignan valores por defecto para campos requeridos que faltan
- **Reporte detallado**: Muestra qué campos fueron omitidos y por qué

#### 3. Modos de Importación
- **Append** (Añadir): Mantiene los datos existentes, añade nuevos registros
- **Update** (Actualizar): Actualiza registros existentes usando `ON DUPLICATE KEY UPDATE`
- **Replace** (Reemplazar): ⚠️ Elimina todos los datos existentes antes de importar

#### 4. Seguridad y Backup
- **Backup automático**: Crea copias de seguridad de cada tabla antes de importar
- **Validación opcional**: Puede activarse/desactivarse según necesidad
- **Manejo robusto de errores**: Continúa con otras tablas si una falla

### 📋 Características Técnicas

#### Validación y Filtrado de Campos
```php
// El sistema ahora:
1. Lee el esquema de la tabla destino (PrestaShop 9)
2. Compara con los datos de origen (PrestaShop 1.7.6)
3. Filtra campos incompatibles automáticamente
4. Añade valores por defecto para campos nuevos requeridos
5. Reporta todos los campos omitidos al usuario
```

#### Compatibilidad de Tipos
- Detecta tipos de datos (int, varchar, text, date, etc.)
- Asigna valores seguros por defecto según el tipo
- Respeta campos NULL vs NOT NULL
- Omite claves primarias auto-increment

#### Manejo Mejorado de SQL
- **SET statements**: Se ejecutan pero errores se ignoran (configuración)
- **DROP TABLE**: Solo se ejecuta en modo "replace"
- **CREATE TABLE**: Solo se ejecuta en modo "replace"
- **INSERT**: Manejo robusto con reporte de errores por tabla

### 🎨 Interfaz de Usuario

#### Nueva Sección de Importación
- Formulario de carga de archivos con validación
- Selector de modo de importación con descripciones claras
- Opciones de validación y backup con explicaciones
- Alertas de advertencia sobre riesgos
- Soporte para archivos: .json, .sql, .csv, .zip

#### Mensajes Mejorados
- ✅ Confirmaciones con estadísticas (tablas importadas, filas importadas)
- ⚠️ Advertencias detalladas sobre campos omitidos
- ❌ Errores específicos con información útil para debugging

### 📊 Reporte de Resultados

Al finalizar la importación, el usuario recibe:
```
✅ Import completed successfully! Tables imported: 15, Rows imported: 2,450

⚠️ Import warnings:
- Some fields were skipped due to schema differences
- Table ps_customer: old_field_1, deprecated_field_2
- Table ps_product: legacy_column
```

### 🔧 Archivos Modificados

1. **classes/MigrationService.php**
   - Línea 206: Corrección exportación SQL
   - Línea 290-355: Nueva función `importFromJSON()` mejorada
   - Línea 357-426: Nueva función `importFromSQL()` mejorada
   - Línea 428-446: Nueva función `importFromCSV()`
   - Línea 448-542: Nueva función `importFromMultiTableCSV()`
   - Línea 620-730: Nueva función `validateAndFilterTableData()`

2. **controllers/admin/AdminPs178to9migrationController.php**
   - Línea 42: Añadido renderizado de formulario de importación
   - Línea 103-157: Nuevo método `renderImportForm()`
   - Línea 162-167: Añadido procesamiento de importación en `postProcess()`
   - Línea 225-299: Nuevo método `processImport()`

### ⚠️ Notas Importantes

1. **Siempre haga backup** antes de importar datos
2. Use el modo "Append" o "Update" para datos de producción
3. El modo "Replace" es **destructivo** - elimina todos los datos existentes
4. Los campos incompatibles se omiten automáticamente - revise el reporte
5. Los archivos CSV deben usar el formato multi-tabla generado por la exportación

### 🔜 Próximas Mejoras

- [ ] Vista previa de datos antes de importar
- [ ] Selector individual de tablas para importación
- [ ] Logs detallados descargables
- [ ] Programación de migraciones automáticas
- [ ] Validación de integridad referencial

---

## Uso Rápido

### Exportar
1. Seleccionar formato (JSON, SQL, CSV)
2. Elegir modo (archivo único o ZIP)
3. Clic en "Export All Tables"

### Importar
1. Seleccionar archivo (.json, .sql, .csv, .zip)
2. Elegir modo de importación
3. ✅ Activar validación (recomendado)
4. ✅ Activar backup (recomendado)
5. Clic en "Import Data"
6. Revisar reporte de resultados
