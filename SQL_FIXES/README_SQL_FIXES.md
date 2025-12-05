# SQL Fix Scripts for PrestaShop 9.0

Post-import correction scripts to fix NULL values and missing data after migrating from PrestaShop 1.7.6.

---

## 📋 Available Scripts

### 1. FIX_PS9_FINAL.sql ⭐ **RECOMMENDED**

**Complete comprehensive fix** - Use this one first.

**What it fixes:**
- ✅ Configuration: PS_PRODUCT_SHORT_DESC_LIMIT
- ✅ Gender table creation and default assignments
- ✅ Product fields (reference, mpn, ean13, upc, isbn, etc.)
- ✅ Product language fields (name, description, link_rewrite)
- ✅ Category fields (name, link_rewrite, descriptions)
- ✅ Order fields (reference, payment, module, secure_key)
- ✅ Order details (product names, quantities)
- ✅ Customer fields (firstname, lastname, email, gender)
- ✅ Address fields (alias, company, address1, city, postcode)
- ✅ Cart fields (secure_key, gift settings)
- ✅ Carrier fields (name, shipping_method)

**Execution time:** ~1-2 minutes  
**Tables affected:** 10+  
**Records processed:** All rows in affected tables

---

### 2. FIX_PRODUCT_STRINGS.sql

**Product-specific fixes only.**

Use if you only have product errors and everything else works.

**What it fixes:**
- Product reference, mpn, ean13, upc, isbn
- Product descriptions and names
- Product link_rewrite
- Product meta fields

---

### 3. FIX_GENDER_ORDERS.sql

**Gender and order fixes only.**

Use if you have "Undefined array key 7" errors.

**What it fixes:**
- Creates gender table
- Assigns default gender to customers
- Updates order references

---

## 🚀 How to Use

### Step 1: Backup Database

```bash
# Create backup before running fixes
mysqldump -u user -p database > backup_before_fixes.sql
```

### Step 2: Execute Script

**Via phpMyAdmin:**
```
1. Select your PrestaShop 9 database
2. Go to "SQL" tab
3. Copy entire content of FIX_PS9_FINAL.sql
4. Paste in SQL editor
5. Click "Continuar" / "Go"
6. Wait for completion (1-2 minutes)
```

**Via MySQL Command Line:**
```bash
mysql -u username -p database_name < FIX_PS9_FINAL.sql
```

### Step 3: Verify Results

The script outputs verification counts at the end:

```sql
=== CORRECCIÓN COMPLETADA ===
Productos: 1234
Productos con nombre: 1234
Categorías: 56
Pedidos: 789
Clientes: 456
Clientes con género: 456
Carritos: 123
Direcciones: 234
```

### Step 4: Clear Cache

**SSH:**
```bash
rm -rf /var/www/vhosts/yourdomain.com/httpdocs/var/cache/*
```

**Admin Panel:**
```
Advanced Parameters → Performance → Clear cache
```

---

## 🔍 What Each Section Does

### Configuration Section
```sql
-- Creates missing configuration entry
INSERT INTO configuration (name, value) VALUES 
('PS_PRODUCT_SHORT_DESC_LIMIT', '800');
```

**Why needed:** PS 9 requires this configuration for product short descriptions.

---

### Gender Section
```sql
-- Creates gender table
INSERT INTO gender (id_gender, type) VALUES 
(1, 0),  -- Male
(2, 1),  -- Female
(9, 2);  -- Prefer not to say

-- Assigns default to all customers
UPDATE customer SET id_gender = 9 
WHERE id_gender IS NULL;
```

**Why needed:** Orders in PS 9 require valid customer gender. Missing gender causes "Undefined array key 7" error.

---

### Product Section
```sql
-- Converts NULL to empty strings
UPDATE product SET 
    reference = COALESCE(reference, ''),
    mpn = COALESCE(mpn, ''),
    ean13 = COALESCE(ean13, ''),
    upc = COALESCE(upc, ''),
    isbn = COALESCE(isbn, '');
```

**Why needed:** PS 9 Product constructor expects `string`, not `null`. NULL values cause TypeError.

---

### Order Section
```sql
-- Ensures valid order references
UPDATE orders SET 
    reference = CASE 
        WHEN reference IS NULL OR reference = '' 
        THEN CONCAT('ORD', LPAD(id_order, 6, '0'))
        ELSE reference 
    END;
```

**Why needed:** Orders must have reference. Auto-generates format: ORD000123

---

### Category Section
```sql
-- Fixes category names and URLs
UPDATE category_lang SET 
    name = CASE 
        WHEN name IS NULL OR name = '' 
        THEN CONCAT('Categoría ', id_category)
        ELSE name 
    END,
    link_rewrite = CASE 
        WHEN link_rewrite IS NULL OR link_rewrite = '' 
        THEN CONCAT('category-', id_category)
        ELSE link_rewrite 
    END;
```

**Why needed:** Categories showing only IDs instead of names.

---

### Customer Section
```sql
-- Ensures valid customer data
UPDATE customer SET 
    firstname = CASE 
        WHEN firstname IS NULL OR firstname = '' 
        THEN 'Cliente'
        ELSE firstname 
    END,
    email = CASE 
        WHEN email IS NULL OR email = '' 
        THEN CONCAT('customer', id_customer, '@migrated.local')
        ELSE email 
    END;
```

**Why needed:** Customers must have firstname and valid email.

---

### Address Section
```sql
-- Fixes address fields
UPDATE address SET 
    address1 = CASE 
        WHEN address1 IS NULL OR address1 = '' 
        THEN 'Dirección no especificada'
        ELSE address1 
    END,
    postcode = CASE 
        WHEN postcode IS NULL OR postcode = '' 
        THEN '00000'
        ELSE postcode 
    END;
```

**Why needed:** Addresses require minimum valid data.

---

### Cart Section
```sql
-- Fixes cart secure keys
UPDATE cart SET 
    secure_key = CASE 
        WHEN secure_key IS NULL OR secure_key = '' 
        THEN MD5(CONCAT(id_cart, COALESCE(id_customer, 0)))
        ELSE secure_key 
    END;
```

**Why needed:** Carts need secure_key for security validation.

---

## ⚠️ Common Issues

### Script fails midway

**Symptom:** Error message during execution

**Solutions:**
1. Check error message for specific table/column
2. Verify database prefix matches (change `top_` if needed)
3. Run in smaller sections

---

### No changes visible

**Symptom:** Script completes but errors persist

**Causes:**
- Cache not cleared
- Wrong database selected
- Prefix mismatch

**Solutions:**
```bash
# Clear cache
rm -rf var/cache/*

# Verify database prefix
SELECT * FROM configuration WHERE name = 'PS_SHOP_NAME';

# Check if changes applied
SELECT COUNT(*) FROM product WHERE mpn IS NULL;  # Should be 0
```

---

### Performance slow

**Symptom:** Script takes 5+ minutes

**Causes:**
- Large database (100k+ products)
- Slow server
- No indexes

**Solutions:**
```sql
-- Run section by section instead of all at once
-- Comment out sections already completed
```

---

## 📊 Expected Results

### Before Fix
```
Products: TypeError mpn must be string, null given
Orders: Warning: Undefined array key 7
Carts: HTTP 500 Error
Invoices: HTTP 500 Error
Categories: Showing only IDs
```

### After Fix
```
Products: ✅ All load correctly, mpn = '' (empty string)
Orders: ✅ All display, customer gender = 9
Carts: ✅ Accessible, secure_key valid
Invoices: ✅ Generate correctly
Categories: ✅ Show proper names
```

---

## 🔄 If Errors Persist

### Enable Debug Mode
```php
// config/defines.inc.php
define('_PS_MODE_DEV_', true);
```

### Check Error Logs
```bash
# Location
/var/logs/

# Recent errors
tail -f var/logs/$(ls -t var/logs/ | head -1)
```

### Verify Specific Tables
```sql
-- Check products
SELECT id_product, reference, mpn, ean13 
FROM product 
WHERE mpn IS NULL OR reference IS NULL 
LIMIT 10;

-- Check customers
SELECT id_customer, firstname, lastname, id_gender 
FROM customer 
WHERE id_gender IS NULL OR id_gender NOT IN (1,2,9) 
LIMIT 10;

-- Check orders
SELECT id_order, reference, payment 
FROM orders 
WHERE reference IS NULL OR reference = '' 
LIMIT 10;
```

---

## 🎯 Customization

### Change Database Prefix

If your database uses a different prefix (e.g., `ps_` instead of `top_`):

```bash
# Find and replace in script
top_product → ps_product
top_category → ps_category
top_orders → ps_orders
# etc.
```

### Adjust Default Values

```sql
-- Change default gender
UPDATE customer SET id_gender = 1  -- Male instead of 9

-- Change default postcode
SET @default_postcode = '28001';  -- Custom postcode

-- Change product reference format
CONCAT('PROD', LPAD(id_product, 8, '0'))  -- PROD00000123
```

---

## 📁 File Details

| File | Size | Tables | Purpose |
|------|------|--------|---------|
| FIX_PS9_FINAL.sql | ~15KB | 10+ | Complete fix |
| FIX_PRODUCT_STRINGS.sql | ~3KB | 2 | Products only |
| FIX_GENDER_ORDERS.sql | ~2KB | 3 | Gender/Orders |

---

## ✅ Verification Checklist

After running FIX_PS9_FINAL.sql:

- [ ] Configuration table has PS_PRODUCT_SHORT_DESC_LIMIT
- [ ] Gender table exists with 3 rows (1, 2, 9)
- [ ] All customers have valid id_gender (1, 2, or 9)
- [ ] All products have reference (not NULL)
- [ ] All products have mpn = '' (not NULL)
- [ ] All orders have reference (not NULL or '')
- [ ] All categories have name (not NULL or '')
- [ ] All addresses have address1 (not NULL or '')
- [ ] All carts have secure_key (not NULL or '')
- [ ] Cache cleared
- [ ] Products page loads without errors
- [ ] Orders page loads without errors
- [ ] Invoices generate successfully

---

**Last Updated:** December 2025  
**Compatible With:** PrestaShop 9.0.x  
**Database Prefix:** `top_` (customizable)
