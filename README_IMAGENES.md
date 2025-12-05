# Exportación e Importación de Imágenes

## ✅ NUEVA FUNCIONALIDAD INTEGRADA

El módulo ahora incluye **exportación e importación automática de imágenes** sin necesidad de scripts externos.

---

## 📤 EXPORTAR IMÁGENES

### Desde PrestaShop 1.7.6:

1. Accede al módulo **PS Migration 1.7.8 to 9**
2. Ve a la sección **"Export Images"**
3. Haz clic en **"Export Images (ZIP)"**
4. Se descargará automáticamente un archivo: `images_export_YYYY-MM-DD_HH-MM-SS.zip`

### ¿Qué incluye el ZIP?

El archivo ZIP contiene todas las imágenes de:
- ✅ **Productos** (`/img/p/`)
- ✅ **Categorías** (`/img/c/`)
- ✅ **Fabricantes** (`/img/m/`)
- ✅ **Proveedores** (`/img/su/`)
- ✅ **Transportistas** (`/img/s/`)
- ✅ **Tiendas físicas** (`/img/st/`)

---

## 📥 IMPORTAR IMÁGENES

### En PrestaShop 9:

1. Sube el archivo ZIP por FTP a: `/modules/ps178to9migration/exports/` (opcional)
2. Accede al módulo en PrestaShop 9
3. Ve a la sección **"Import Images"**
4. Selecciona el archivo ZIP descargado
5. Haz clic en **"Import Images"**
6. Verás una barra de progreso en tiempo real
7. Al finalizar verás: **"X images imported successfully!"**

### Características:

- ✅ **Subida con AJAX** - Sin problemas de HTTPS
- ✅ **Barra de progreso** - Ves el porcentaje en tiempo real
- ✅ **Extracción automática** - Descomprime y coloca las imágenes en las carpetas correctas
- ✅ **Permisos automáticos** - Aplica chmod 644 a cada imagen
- ✅ **Informe detallado** - Te dice cuántas imágenes se importaron

---

## 🔄 PROCESO COMPLETO DE MIGRACIÓN

### 1. En PrestaShop 1.7.6:

```
1. Exportar base de datos (SQL) → tablas_1_7_6_topreileve3d.sql
2. Exportar imágenes (ZIP)     → images_export_2025-11-30_14-30-00.zip
```

### 2. En PrestaShop 9:

```
1. Importar base de datos (modo: Replace)
2. Importar imágenes (ZIP)
3. Regenerar miniaturas (Diseño > Imágenes)
4. Limpiar caché (Configuración > Rendimiento)
```

---

## ⚠️ NOTAS IMPORTANTES

- El archivo ZIP puede ser muy grande (cientos de MB)
- Asegúrate de tener suficiente espacio en el servidor
- La importación puede tardar varios minutos dependiendo del número de imágenes
- Si el hosting tiene límite de subida, contacta con soporte para aumentarlo

---

## 🎯 VENTAJAS

✅ **Todo integrado** - No necesitas FTP ni scripts externos  
✅ **Multiplataforma** - Funciona en Windows, Linux, Mac  
✅ **Profesional** - Usa las mismas técnicas que herramientas comerciales  
✅ **Seguro** - Usa AJAX para evitar problemas de HTTPS  
✅ **Completo** - Exporta TODAS las carpetas de imágenes automáticamente

---

## 📝 EJEMPLO DE USO

**Antes:**
```
1. Exportar SQL → OK
2. Conectar por FTP
3. Descargar /img/p/ (30 minutos)
4. Descargar /img/c/ (5 minutos)
5. Descargar /img/m/ (2 minutos)
6. Conectar al servidor nuevo por FTP
7. Subir todas las carpetas (40 minutos)
Total: ~1.5 horas + riesgo de errores
```

**Ahora:**
```
1. Exportar SQL → OK
2. Exportar imágenes → 1 clic (crea ZIP automático)
3. Importar SQL en PS9 → OK
4. Importar ZIP en PS9 → 1 clic (extrae todo automáticamente)
Total: ~5 minutos + 0 errores
```

---

## 🐛 SOLUCIÓN DE PROBLEMAS

**Error: "File too large"**
- Aumenta `upload_max_filesize` y `post_max_size` en php.ini
- O contacta con tu hosting

**Error: "Could not extract images"**
- Verifica que el ZIP no esté corrupto
- Verifica permisos de la carpeta /img/ en el servidor

**Faltan algunas imágenes**
- Ejecuta la exportación de nuevo
- Verifica que las imágenes existan en el PS 1.7.6 original

---

✅ **Migración completa y profesional sin complicaciones**
