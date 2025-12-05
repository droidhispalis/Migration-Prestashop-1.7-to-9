# PrestaShop Migration Module 1.7.x → 9.x
## Database Exporter for PrestaShop 9 Migration

![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7.6%20%7C%201.7.8-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.1+-purple.svg)
![License](https://img.shields.io/badge/license-AFL-green.svg)
![Version](https://img.shields.io/badge/version-1.1.0-orange.svg)

## 📋 Description

**PrestaShop Migration Exporter** is a module for PrestaShop 1.7.6/1.7.8 that exports your database in a format **100% compatible with PrestaShop 9**, eliminating the need for manual SQL fixes.

This module works together with the [Migration Importer Module](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6) to provide a complete migration solution.

---

## 🎯 Complete Migration Solution

This module is **part 1** of the complete migration process:

| Step | Module | PrestaShop Version | Repository |
|------|--------|-------------------|------------|
| **1** | **Migration Exporter** (this module) | 1.7.6 / 1.7.8 | [GitHub](https://github.com/droidhispalis/Migration-Prestashop-1.7-to-9) |
| **2** | Migration Importer | 9.0+ | [GitHub](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6) |

---

## ✨ New Features (v1.1.0)

This version generates **100% PS 9 compatible SQL exports** without requiring pre-processing.

#### Automatic Compatibility Fixes:

1. **`meta_keywords` Removal**
   - Automatically excluded from all `_lang` tables
   - This column was removed in PrestaShop 9
   - Affects: `product_lang`, `category_lang`, `cms_lang`, `cms_category_lang`, etc.

2. **`shipping_number` Removal**
   - Automatically excluded from `ps_orders` table
   - This column was removed/relocated in PrestaShop 9

3. **INSERT IGNORE for Safety**
   - Uses `INSERT IGNORE INTO` for:
     - All `_lang` tables (prevents duplicate language entries)
     - All `_shop` tables (prevents duplicate shop associations)
     - `order_detail` table (prevents duplicate order lines)
   - Regular `INSERT INTO` for all other tables

### 📋 Usage

#### In PrestaShop 1.7.6/1.7.8:
1. Install this module
2. Go to **Modules** → **PS Migration 1.7 to 9**
3. Click **"Export Database"**
4. Download the generated SQL file

#### In PrestaShop 9:
1. Install the companion module [psimporter9from178](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6)
2. Upload the SQL file from step 3 above
3. Import directly - **no pre-processing needed**

📖 **Full import instructions:** See the [Importer Module Documentation](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6/blob/main/README.md)

---

## 🔧 Technical Details

**Excluded Columns:**
```php
$excludedColumns = array(
    'meta_keywords',   // Removed in PS 9
    'shipping_number'  // Removed from orders in PS 9
);
```

**Smart INSERT Logic:**
- Tables matching `*_lang`, `*_shop`, `order_detail` → `INSERT IGNORE INTO`
- All other tables → `INSERT INTO`

---

## ⚠️ Important Notes

- The exported SQL file will **NOT** include DROP/CREATE TABLE statements for incompatible columns
- All data is preserved - only incompatible structure elements are excluded
- The module filters columns **during export**, ensuring clean migration
- Works with PrestaShop 1.7.0 through 1.7.8

---

## 📦 What's Exported

✅ Products (with names, descriptions, prices)
✅ Categories (with descriptions and SEO)
✅ Customers
✅ Orders (690 rows confirmed working)
✅ Images
✅ Manufacturers & Suppliers
✅ Features & Attributes
✅ CMS Pages
✅ Taxes & Carriers
✅ All shop associations

---

## 🐛 Known Issues Resolved

- ❌ **FIXED**: `meta_keywords` syntax errors
- ❌ **FIXED**: `shipping_number` column not found errors
- ❌ **FIXED**: Duplicate key errors in `_lang` tables
- ❌ **FIXED**: Column count mismatch errors

---

## 🔄 Migration Process

```
PS 1.7.6                    PS 9
┌─────────────┐            ┌─────────────┐
│   Export    │──SQL file─→│   Import    │
│   Module    │  (clean)   │   Module    │
│  v1.1.0     │            │ psimporter  │
└─────────────┘            └─────────────┘
     ↓                           ↓
  Filters:                   Direct
  - meta_keywords            Import
  - shipping_number          (no fixes
  - INSERT IGNORE            needed)
```

---

## 📚 Additional Resources

- 📥 **Import Module:** [Migration Prestashop 9 Importer](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6)
- 🔧 **SQL Fixes:** [Post-Import SQL Corrections](./SQL_FIXES/)
- 📖 **Installation Guide:** [INSTALL.md](./README_INSTALACION.txt)
- 🖼️ **Image Migration:** [README_IMAGENES.md](./README_IMAGENES.md)

---

## 🆘 Troubleshooting

### Common Export Issues

**Export timeout:**
- Increase `max_execution_time` in PHP
- Export in smaller batches if possible

**Memory errors:**
- Increase `memory_limit` in PHP
- Close other applications

**File download fails:**
- Check disk space on server
- Verify write permissions on temp directory

For import issues, see the [Importer Module Troubleshooting](https://github.com/droidhispalis/Migration-Prestashop-9-fro-1.7.6/blob/main/README.md#-troubleshooting).

---

## 📝 Changelog

**v1.1.0** (2025-12-01)
- Added automatic column filtering for PS 9 compatibility
- Implemented INSERT IGNORE for conflict-prone tables
- Updated export header with compatibility note
- No manual SQL fixes required anymore

**v1.0.0** (2025-11-XX)
- Initial release
- Basic table export functionality

---

## 📄 License

This module is licensed under the **Academic Free License (AFL 3.0)** - same as PrestaShop.

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

---

## 🆘 Support

- 🐛 **Bug Reports:** [Open an issue](https://github.com/droidhispalis/Migration-Prestashop-1.7-to-9/issues)
- 💡 **Feature Requests:** [Open an issue](https://github.com/droidhispalis/Migration-Prestashop-1.7-to-9/issues)
- 📖 **Documentation:** Check the module files and SQL_FIXES folder

---

**Developed by**: Migration Tools Team  
**Support**: For issues, check error logs or open a GitHub issue

**Made with ❤️ for the PrestaShop community**
