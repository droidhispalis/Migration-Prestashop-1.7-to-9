# PrestaShop 1.7.6 to 9.0 Migration Tool

🚀 **Complete migration solution** for PrestaShop 1.7.6 → PrestaShop 9.0 including database, images, and theme transfer.

## 📋 Overview

This project provides a **complete migration toolkit** for upgrading from PrestaShop 1.7.6 to PrestaShop 9.0, handling the incompatibilities and strict type requirements introduced in PS 9.

### Components

1. **ps178to9migration module** (PS 1.7.6) - Export module with automatic filtering
2. **SQL Fix Scripts** (PS 9.0) - Post-import corrections for NULL values and data types
3. **Documentation** - Step-by-step migration guide

---

## 🎯 What This Solves

PrestaShop 9.0 introduced breaking changes that prevent direct migration from 1.7.6:

### Removed Columns
- ❌ `meta_keywords` in all `_lang` tables
- ❌ `shipping_number` in `orders` table

### Strict Type Requirements
- ❌ NULL values not accepted in string fields (must be empty strings `''`)
- ❌ NULL values not accepted in numeric fields (must be `0` or valid defaults)
- ❌ Missing configuration: `PS_PRODUCT_SHORT_DESC_LIMIT`
- ❌ Missing gender table data causing order errors

---

## 📦 Installation

### Part 1: Install Export Module (on PS 1.7.6)

```bash
# Upload to your PS 1.7.6 installation
/modules/ps178to9migration/

# Install via Admin Panel
Módulos → Module Manager → Upload a module → ps178to9migration.zip
```

### Part 2: Export Database

1. Go to **Módulos → ps178to9migration**
2. Click **"Exportar Base de Datos"**
3. Download the generated SQL file
4. The module automatically filters incompatible columns

### Part 3: Import to PS 9.0

```bash
# Import via phpMyAdmin
1. Select your PS 9 database
2. Import → Choose SQL file
3. Execute import
```

### Part 4: Run Post-Import Fixes

```sql
-- Execute in phpMyAdmin on PS 9 database
-- File: FIX_PS9_FINAL.sql
-- This fixes all NULL values and missing data
```

### Part 5: Clear Cache

```bash
# SSH
rm -rf /var/www/vhosts/yourdomain.com/httpdocs/var/cache/*

# Or via Admin Panel
Advanced Parameters → Performance → Clear cache
```

---

## 🛠️ Module Features

### Export Module (ps178to9migration)

**Automatically filters incompatible columns during export:**

```php
// Removed from ALL _lang tables
'meta_keywords'

// Removed from orders table
'shipping_number'
```

**Export Options:**
- ✅ Complete database structure
- ✅ All data (products, categories, orders, customers)
- ✅ Filtered for PS 9 compatibility
- ✅ Ready-to-import SQL file

---

## 🔧 SQL Fix Scripts

### FIX_PS9_FINAL.sql

**Comprehensive post-import correction script:**

#### 1. Configuration
```sql
-- Creates missing PS_PRODUCT_SHORT_DESC_LIMIT
INSERT INTO configuration (name, value) VALUES ('PS_PRODUCT_SHORT_DESC_LIMIT', '800');
```

#### 2. Gender Table
```sql
-- Critical for orders - creates gender table with default values
INSERT INTO gender (id_gender, type) VALUES (1, 0), (2, 1), (9, 2);
```

#### 3. Product Corrections
```sql
-- Converts NULL → empty strings for all string fields
UPDATE product SET 
    reference = COALESCE(reference, ''),
    ean13 = COALESCE(ean13, ''),
    upc = COALESCE(upc, ''),
    mpn = COALESCE(mpn, ''),
    isbn = COALESCE(isbn, '');
```

#### 4. Order Corrections
```sql
-- Ensures all orders have valid reference and payment method
UPDATE orders SET 
    reference = CASE WHEN reference IS NULL OR reference = '' 
                THEN CONCAT('ORD', LPAD(id_order, 6, '0'))
                ELSE reference END,
    payment = COALESCE(payment, 'Unknown'),
    module = COALESCE(module, 'unknown');
```

#### 5. Customer Corrections
```sql
-- Assigns default gender to all customers
UPDATE customer SET id_gender = 9 
WHERE id_gender IS NULL OR id_gender NOT IN (1, 2, 9);
```

#### 6. Category, Address, Cart, Carrier
- Fixes NULL values in category names and link_rewrite
- Ensures addresses have valid data
- Corrects cart secure_keys
- Updates carrier information

---

## 📊 What Gets Fixed

| Issue | Before | After |
|-------|--------|-------|
| Product mpn | `NULL` | `''` (empty string) |
| Order reference | `NULL` | `'ORD000123'` |
| Customer gender | `NULL` | `9` (prefer not to say) |
| Category name | `NULL` | `'Categoría X'` |
| Address postcode | `NULL` | `'00000'` |
| Cart secure_key | `NULL` | MD5 hash |
| Configuration | Missing | Created |

---

## ⚠️ Common Errors Fixed

### Before Fix
```
TypeError: Argument #4 ($mpn) must be of type string, null given
Warning: Undefined array key 7 (gender)
Error 500 on cart/order/invoice pages
```

### After Fix
```
✅ All products load correctly
✅ Orders display properly
✅ Carts accessible
✅ Invoices generate without errors
```

---

## 📖 Step-by-Step Migration Guide

### Prerequisites
- PrestaShop 1.7.6 source installation
- PrestaShop 9.0 target installation (fresh)
- phpMyAdmin access
- FTP/SSH access

### Migration Process

#### 1️⃣ Prepare PS 1.7.6
```bash
# Backup current database
mysqldump -u user -p database > backup_ps176.sql

# Install ps178to9migration module
Upload to /modules/ → Install via Admin Panel
```

#### 2️⃣ Export Data
```bash
# Via ps178to9migration module
Admin → Modules → ps178to9migration
Click "Exportar Base de Datos"
Download: prestashop_export_YYYY-MM-DD.sql
```

#### 3️⃣ Transfer Images & Theme
```bash
# Copy img/ folder
/img/p/ → PS 9 /img/p/
/img/c/ → PS 9 /img/c/
/img/m/ → PS 9 /img/m/

# Copy theme (if compatible)
/themes/yourtheme/ → PS 9 /themes/yourtheme/
```

#### 4️⃣ Import to PS 9
```bash
# phpMyAdmin
1. Create new database (if not exists)
2. Select database
3. Import → prestashop_export_YYYY-MM-DD.sql
4. Wait for completion
```

#### 5️⃣ Run SQL Fixes
```sql
-- Execute FIX_PS9_FINAL.sql in phpMyAdmin
-- This takes 1-2 minutes
-- Verifies completion with SELECT counts
```

#### 6️⃣ Clear Cache
```bash
# SSH
rm -rf var/cache/*

# Admin Panel
Advanced Parameters → Performance → Clear cache
```

#### 7️⃣ Verify
```bash
✓ Access Admin Panel
✓ Open Products page
✓ Open Orders page
✓ Open Customers page
✓ Generate an invoice
✓ View cart details
```

---

## 🐛 Troubleshooting

### Products showing errors
```sql
-- Re-run product section from FIX_PS9_FINAL.sql
UPDATE product SET mpn = COALESCE(mpn, ''), ...
```

### Orders missing
```sql
-- Check if orders table exists
SELECT COUNT(*) FROM orders;

-- Re-run order fixes
UPDATE orders SET reference = ...
```

### Gender errors
```sql
-- Verify gender table
SELECT * FROM gender;

-- Re-create if missing
INSERT INTO gender (id_gender, type) VALUES (1, 0), (2, 1), (9, 2);
```

### Error 500 on any page
```php
// Enable debug mode
// File: config/defines.inc.php
define('_PS_MODE_DEV_', true);

// Check error logs
/var/logs/
```

---

## 📂 Repository Structure

```
ps178to9migration/
├── classes/
│   └── MigrationService.php      # Core export logic
├── controllers/
│   └── admin/
│       └── AdminPs178to9migrationController.php
├── translations/
│   ├── en.txt
│   └── es.txt
├── views/
│   └── templates/
│       └── admin/
│           └── configure.tpl      # Admin interface
├── config.xml                     # Module configuration
├── ps178to9migration.php          # Main module file
├── logo.png
├── README.md
├── CHANGELOG.md
└── SQL_FIXES/
    ├── FIX_PS9_FINAL.sql         # Main fix script
    ├── FIX_PRODUCT_STRINGS.sql   # Products only
    ├── FIX_GENDER_ORDERS.sql     # Gender only
    └── README_SQL_FIXES.md       # SQL documentation
```

---

## 🔍 Technical Details

### Incompatible Columns Removed

**meta_keywords** (removed from PS 1.8+)
```sql
-- Affected tables (_lang suffix):
category_lang, cms_lang, cms_category_lang, product_lang
manufacturer_lang, supplier_lang, meta_lang
```

**shipping_number** (removed from PS 9)
```sql
-- Affected table:
orders
```

### Type Strictness Changes

**PS 1.7.6** (PHP 7.x)
```php
// Accepted NULL values
public function __construct($reference = null, $mpn = null) {}
```

**PS 9.0** (PHP 8.x)
```php
// Requires explicit types - NULL not accepted
public function __construct(string $reference, string $mpn) {}
```

### NULL → Default Value Mapping

| Type | PS 1.7.6 | PS 9.0 Required |
|------|----------|-----------------|
| string | `NULL` | `''` |
| int | `NULL` | `0` or valid ID |
| decimal | `NULL` | `0.000000` |
| boolean | `NULL` | `0` or `1` |
| text | `NULL` | `''` |

---

## 🎓 Lessons Learned

### Issue #1: meta_keywords
**Problem:** Column removed in PS 1.8+  
**Solution:** Filter during export (prevents import errors)

### Issue #2: shipping_number
**Problem:** Column removed in PS 9  
**Solution:** Filter during export

### Issue #3: NULL string values
**Problem:** PHP 8 strict typing rejects NULL for string parameters  
**Solution:** Convert all NULL → `''` post-import

### Issue #4: Missing gender
**Problem:** Orders require customer gender, but data missing  
**Solution:** Create gender table + assign default value (9)

### Issue #5: Missing configuration
**Problem:** PS_PRODUCT_SHORT_DESC_LIMIT not set  
**Solution:** Insert into configuration table

---

## 🤝 Contributing

Contributions welcome! If you encounter issues or have improvements:

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## 📝 License

MIT License - Free to use and modify

---

## 👨‍💻 Author

Created for PrestaShop 1.7.6 → 9.0 migration projects

---

## 🌟 Acknowledgments

- PrestaShop community for migration insights
- Testing on production migration scenarios
- Real-world problem solving and iteration

---

## 📞 Support

If you encounter issues:

1. Check **Troubleshooting** section above
2. Enable debug mode and check error logs
3. Review SQL fix script execution
4. Verify all steps completed correctly
5. Open an issue with error details

---

## ✅ Success Criteria

After completing migration, you should have:

- ✅ All products visible and editable
- ✅ All categories with correct names
- ✅ All orders accessible and complete
- ✅ All customers with valid data
- ✅ Invoices generating correctly
- ✅ Carts functioning properly
- ✅ No TypeError exceptions
- ✅ No HTTP 500 errors
- ✅ Clean error logs

---

**Version:** 1.0.0  
**Last Updated:** December 2025  
**Tested On:** PrestaShop 1.7.6.8 → PrestaShop 9.0.1
