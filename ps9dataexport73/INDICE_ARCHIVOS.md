# 📁 ÍNDICE DE ARCHIVOS - PS 1.7 → PS 9 Data Export Module

## 📚 Documentación

### Para Usuarios
- **[README_MIGRACION_COMPLETA.md](README_MIGRACION_COMPLETA.md)** - Guía completa paso a paso
  - Proceso de exportación
  - Proceso de importación
  - Solución de problemas
  - Troubleshooting

### Para Desarrolladores
- **[RESUMEN_EJECUTIVO.md](RESUMEN_EJECUTIVO.md)** - Resumen técnico completo
  - Problema original y solución
  - Cambios en el código
  - Archivos modificados
  - Lecciones aprendidas

- **[CHANGELOG.md](CHANGELOG.md)** - Historial de versiones
  - v1.3.2: Solución "Shop id 0 is invalid"
  - v1.3.1: Validación y reparación
  - Versiones anteriores

- **[EJEMPLO_EXPORT_CORRECTO.sql](EJEMPLO_EXPORT_CORRECTO.sql)** - Ejemplo educativo
  - Estructura correcta de exportación
  - Comentarios explicativos
  - Orden de inserción

---

## 💻 Código Fuente

### Módulo Principal
- **[ps9dataexport73.php](ps9dataexport73.php)** - Clase principal del módulo
  - Hooks y controladores
  - AJAX handlers
  - Gestión de archivos

### Servicios (classes/)

#### Exportación
- **[classes/SqlDumpService.php](classes/SqlDumpService.php)** - Generación de SQL
  - Exportación tabla por tabla
  - Formato SQL compatible PS9
  - Manejo de datos grandes

- **[classes/TablePlan.php](classes/TablePlan.php)** - ⭐ MODIFICADO v1.3.2
  - Define qué tablas exportar
  - **CRÍTICO**: Ahora incluye `shop`, `shop_group`, `shop_url`

- **[classes/ImageExportService.php](classes/ImageExportService.php)** - Exportación de imágenes
  - Genera ZIP con imágenes
  - Mantiene estructura de directorios

#### Importación
- **[classes/ImportService.php](classes/ImportService.php)** - ⭐ MODIFICADO v1.3.2
  - Importación con backup automático
  - Rollback si falla
  - **NUEVO**: Integra validación PS9

- **[classes/PS9ValidationService.php](classes/PS9ValidationService.php)** - ⭐ NUEVO v1.3.2
  - Validación post-importación
  - Reparación automática de incompatibilidades
  - Creación de configuración faltante

#### Validación
- **[classes/ValidationService.php](classes/ValidationService.php)** - Validación preflight
  - Verifica datos antes de exportar
  - Detecta incompatibilidades
  - Sugiere reparaciones

- **[classes/SqlWriter.php](classes/SqlWriter.php)** - Writer de SQL
  - Formateo de sentencias SQL
  - Optimización de INSERTs

---

## 🖥️ Interfaz de Usuario

### Templates (views/templates/admin/)
- **[views/templates/admin/configure.tpl](views/templates/admin/configure.tpl)** - Vista principal
  - Tab Exportar
  - Tab Importar
  - Lista de archivos
  - Logs y resultados

### Assets
- **CSS**: Integrado en template
- **JavaScript**: AJAX calls, manejo de UI

---

## 📦 Estructura Completa

```
ps9dataexport73/
│
├── 📄 ps9dataexport73.php          # Módulo principal
├── 📄 index.php                    # Seguridad
│
├── 📁 classes/                     # Servicios
│   ├── SqlDumpService.php         # Export SQL
│   ├── TablePlan.php              # ⭐ Lista de tablas (MODIFICADO)
│   ├── ImageExportService.php     # Export imágenes
│   ├── ImportService.php          # ⭐ Import con validación (MODIFICADO)
│   ├── PS9ValidationService.php   # ⭐ Validación PS9 (NUEVO)
│   ├── ValidationService.php      # Validación preflight
│   ├── SqlWriter.php              # Writer SQL
│   └── index.php                  # Seguridad
│
├── 📁 views/
│   ├── templates/
│   │   ├── admin/
│   │   │   ├── configure.tpl      # Interfaz principal
│   │   │   └── index.php
│   │   └── index.php
│   └── index.php
│
├── 📁 translations/
│   └── index.php
│
└── 📁 docs/                        # Documentación
    ├── README_MIGRACION_COMPLETA.md    # ⭐ Guía usuario
    ├── RESUMEN_EJECUTIVO.md            # ⭐ Resumen técnico
    ├── CHANGELOG.md                    # ⭐ Historial versiones
    ├── EJEMPLO_EXPORT_CORRECTO.sql     # ⭐ Ejemplo SQL
    └── INDICE_ARCHIVOS.md              # ⭐ Este archivo
```

---

## 🔧 Archivos de Configuración

No requiere configuración adicional. El módulo detecta automáticamente:
- Prefijo de base de datos
- ID de shop
- ID de idioma
- Dominio actual

---

## 📋 Archivos Generados en Runtime

### En PS 1.7 (Exportación)
```
download/ps9-export/
├── ps1_7_to_ps9_export_YYYYMMDD_HHMMSS.sql  # SQL exportado
└── images_export_YYYYMMDD_HHMMSS.zip        # Imágenes (opcional)
```

### En PS 9 (Importación)
```
download/ps9-export/
├── backup_before_import_YYYYMMDD_HHMMSS.sql  # Backup automático
└── *.sql                                      # Archivos subidos
```

---

## 🎯 Archivos Clave por Tarea

### ¿Quieres modificar qué tablas se exportan?
→ [classes/TablePlan.php](classes/TablePlan.php)

### ¿Quieres añadir validaciones PS9?
→ [classes/PS9ValidationService.php](classes/PS9ValidationService.php)

### ¿Quieres modificar el proceso de importación?
→ [classes/ImportService.php](classes/ImportService.php)

### ¿Quieres cambiar la interfaz?
→ [views/templates/admin/configure.tpl](views/templates/admin/configure.tpl)

### ¿Quieres entender qué se exporta?
→ [EJEMPLO_EXPORT_CORRECTO.sql](EJEMPLO_EXPORT_CORRECTO.sql)

---

## 📊 Métricas del Código

- **Archivos PHP**: 8
- **Archivos documentación**: 4
- **Líneas de código**: ~2,500
- **Clases**: 7
- **Métodos públicos**: 35+

---

## ✅ Checklist de Archivos Importantes

### Para Instalar
- [x] ps9dataexport73.php
- [x] classes/*.php
- [x] views/templates/admin/configure.tpl

### Para Entender
- [x] README_MIGRACION_COMPLETA.md
- [x] RESUMEN_EJECUTIVO.md
- [x] EJEMPLO_EXPORT_CORRECTO.sql

### Para Desarrollo
- [x] CHANGELOG.md
- [x] INDICE_ARCHIVOS.md (este archivo)

---

## 🔄 Flujo de Archivos

```
EXPORTACIÓN (PS 1.7)
┌──────────────────────┐
│ ps9dataexport73.php  │ → Usuario hace clic "Exportar"
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│   TablePlan.php      │ → Define qué tablas exportar
└──────────┬───────────┘   (incluye shop, shop_group, shop_url)
           │
           ▼
┌──────────────────────┐
│ SqlDumpService.php   │ → Genera SQL
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  archivo.sql         │ → Usuario descarga
└──────────────────────┘

IMPORTACIÓN (PS 9)
┌──────────────────────┐
│ ps9dataexport73.php  │ → Usuario sube SQL
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  ImportService.php   │ → 1. Backup
└──────────┬───────────┘   2. Importar SQL
           │                3. Validar PS9
           ▼
┌──────────────────────┐
│PS9ValidationService │ → Validar y reparar
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│   Resultado + Log    │ → Mostrar al usuario
└──────────────────────┘
```

---

## 📞 Contacto y Soporte

Para reportar bugs o solicitar features:
1. Revisar [CHANGELOG.md](CHANGELOG.md)
2. Consultar [README_MIGRACION_COMPLETA.md](README_MIGRACION_COMPLETA.md)
3. Si el problema persiste: crear issue con logs completos

---

**Última actualización**: 31 Enero 2025  
**Versión del módulo**: 1.3.2
