# 🔄 BBDD-PS9-MIGRATION

Módulo profesional para migrar datos de **PrestaShop 1.7.x** a **PrestaShop 9.0.x** con validación automática y reparación de incompatibilidades.

## ⚡ Características

- ✅ **Exportación completa**: Catálogo, clientes, pedidos
- ✅ **Validación PS9**: Detecta y corrige incompatibilidades automáticamente
- ✅ **Backup automático**: Crea respaldo antes de importar
- ✅ **Rollback**: Restaura automáticamente si falla
- ✅ **Compatible PHP 7.3+**: Funciona en hosting legacy
- ✅ **Solución "Shop id 0"**: Corrige el error crítico de PS9

## 📦 Instalación

### En PrestaShop 1.7.x (Origen)
1. Descargar `ps9dataexport73.zip`
2. Ir a **Módulos** → **Subir módulo**
3. Instalar y configurar

### En PrestaShop 9.0.x (Destino)
1. Descargar `ps9dataexport73.zip`
2. Ir a **Módulos** → **Subir módulo**
3. Instalar y configurar

## 🚀 Uso

### Exportar desde PS 1.7
1. Ir a **Módulos** → **PS 1.7 → PS 9 Data Export**
2. Seleccionar qué exportar (Catálogo, Clientes, Pedidos)
3. Clic en **"3) Export SQL"**
4. Descargar archivo `.sql` generado

### Importar en PS 9
1. Ir a **Módulos** → **PS 1.7 → PS 9 Data Export**
2. Subir archivo `.sql` desde PS 1.7
3. Clic en **"IMPORTAR"**
4. El módulo valida y repara automáticamente
5. Limpiar caché: `php bin/console cache:clear`

## 📚 Documentación

- **[Guía Completa](ps9dataexport73/README_MIGRACION_COMPLETA.md)** - Proceso paso a paso
- **[Resumen Ejecutivo](ps9dataexport73/RESUMEN_EJECUTIVO.md)** - Explicación técnica
- **[Changelog](ps9dataexport73/CHANGELOG.md)** - Historial de versiones
- **[Próximos Pasos](ps9dataexport73/PROXIMOS_PASOS.md)** - Qué hacer ahora

## 🔧 Requisitos

- PrestaShop 1.7.x (origen) o 9.0.x (destino)
- PHP 7.3+ (compatible hasta PHP 8.2)
- MySQL 5.6+
- Límites PHP recomendados:
  - `upload_max_filesize = 256M`
  - `post_max_size = 256M`
  - `memory_limit = 2048M`
  - `max_execution_time = 600`

## ⚠️ Problemas Conocidos y Soluciones

### "Shop id 0 is invalid"
**RESUELTO en v1.3.2+**. El módulo crea automáticamente la configuración de shop necesaria y corrige todos los `id_shop` inválidos.

### Catálogo inaccesible después de importar
Limpiar caché:
```bash
php bin/console cache:clear
```

### Archivo descargado es HTML en lugar de SQL
**RESUELTO en v1.3.4**. El módulo ahora intercepta descargas correctamente.

## 📊 Tablas Exportadas

### Catálogo Completo
- shop, shop_group, shop_url (configuración crítica PS9)
- category, category_lang, category_shop, category_product
- product, product_lang, product_shop
- product_attribute, product_attribute_shop
- stock_available, specific_price
- image, image_lang, image_shop
- manufacturer, supplier
- feature, attribute
- tag

### Clientes (Opcional)
- customer, customer_group
- address, group

### Pedidos (Opcional)
- cart, orders
- order_detail, order_invoice, order_payment

## 🛠️ Desarrollo

### Estructura del Proyecto
```
ps9dataexport73/
├── classes/
│   ├── TablePlan.php              # Define tablas a exportar
│   ├── SqlDumpService.php         # Genera SQL
│   ├── ImportService.php          # Importa con backup
│   └── PS9ValidationService.php   # Valida y repara PS9
├── views/templates/admin/
│   └── configure.tpl              # Interfaz usuario
└── ps9dataexport73.php            # Módulo principal
```

### Versiones

- **v1.3.4** (Actual): Fix descarga archivos SQL
- **v1.3.2**: Solución "Shop id 0 is invalid"
- **v1.3.1**: Validación y reparación preflight
- **v1.3.0**: Export/Import básico

## 📄 Licencia

Propietario - Uso interno

## 👨‍💻 Autor

Desarrollado para migración profesional PrestaShop

## 🐛 Reportar Issues

Para reportar problemas:
1. Revisar [documentación completa](ps9dataexport73/README_MIGRACION_COMPLETA.md)
2. Verificar [changelog](ps9dataexport73/CHANGELOG.md)
3. Incluir versión de PS, logs de error, tamaño del archivo SQL

## ⚡ Quick Start

```bash
# 1. Exportar desde PS 1.7
# - Ir al módulo
# - Clic "Export SQL"
# - Descargar archivo

# 2. Importar en PS 9
# - Ir al módulo
# - Subir archivo SQL
# - Clic "IMPORTAR"
# - Esperar validación automática

# 3. Limpiar caché
php bin/console cache:clear

# 4. Verificar
# - Ir a Catálogo → Productos
# - Verificar que aparecen correctamente
```

## ✅ Checklist Post-Migración

- [ ] Productos visibles en catálogo
- [ ] Categorías funcionando
- [ ] Stock correcto
- [ ] NO hay error "Shop id 0 is invalid"
- [ ] Backoffice accesible
- [ ] Imágenes vinculadas (si subiste ZIP)
- [ ] Clientes importados (si seleccionaste)
- [ ] Pedidos importados (si seleccionaste)

---

**Última actualización**: Enero 2026  
**Versión**: 1.3.4
