<?php

class CSVImportService
{
    private $errors = array();
    private $warnings = array();
    private $imported = 0;
    private $skipped = 0;
    
    public function import($csvFile, $entityType, $truncate = false)
    {
        $this->errors = array();
        $this->warnings = array();
        $this->imported = 0;
        $this->skipped = 0;
        
        if (!file_exists($csvFile)) {
            throw new Exception("CSV file not found: $csvFile");
        }
        
        switch ($entityType) {
            case 'categories':
                $result = $this->importCategories($csvFile, $truncate);
                break;
            case 'brands':
                $result = $this->importBrands($csvFile, $truncate);
                break;
            case 'suppliers':
                $result = $this->importSuppliers($csvFile, $truncate);
                break;
            case 'products':
                $result = $this->importProducts($csvFile, $truncate);
                break;
            case 'customers':
                $result = $this->importCustomers($csvFile, $truncate);
                break;
            case 'addresses':
                $result = $this->importAddresses($csvFile, $truncate);
                break;
            default:
                throw new Exception("Unknown entity type: $entityType");
        }
        
        return array(
            'ok' => true,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        );
    }
    
    private function importCategories($csvFile, $truncate)
    {
        if ($truncate) {
            // Delete all categories except root
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'category WHERE id_category > 2');
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'category_lang WHERE id_category > 2');
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'category_shop WHERE id_category > 2');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        // Read header
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            throw new Exception("CSV file is empty or invalid");
        }
        
        $line = 1;
        $parentMap = array(); // Map parent names to IDs
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                // Find parent ID
                $parentId = 2; // Default to Home
                if (!empty($row['Parent category'])) {
                    $parentName = trim($row['Parent category']);
                    
                    // Si es Home o Root, usar ID 2
                    if ($parentName === 'Home' || $parentName === 'Root' || $parentName === '2' || $parentName === '1') {
                        $parentId = 2;
                    } else {
                        // Buscar en el mapa local primero
                        if (isset($parentMap[$parentName])) {
                            $parentId = $parentMap[$parentName];
                        } else {
                            // Buscar en la base de datos
                            try {
                                $sql = 'SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                                        INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category)
                                        WHERE cl.name = \''.pSQL($parentName).'\' 
                                        LIMIT 1';
                                $foundId = (int)Db::getInstance()->getValue($sql);
                                
                                if ($foundId > 0) {
                                    $parentId = $foundId;
                                    $parentMap[$parentName] = $foundId;
                                } else {
                                    $parentId = 2;
                                    $this->warnings[] = "Line $line: Parent '$parentName' not found, using Home";
                                }
                            } catch (Exception $sqlEx) {
                                $parentId = 2;
                                $this->warnings[] = "Line $line: SQL error finding parent '$parentName', using Home";
                            }
                        }
                    }
                }
                
                // NUNCA usar ID del CSV - buscar por nombre si existe
                $existingId = 0;
                try {
                    $sql = 'SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                            INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category)
                            WHERE cl.name = \''.pSQL($row['Name *']).'\' 
                            LIMIT 1';
                    $existingId = (int)Db::getInstance()->getValue($sql);
                } catch (Exception $sqlEx) {
                    $existingId = 0;
                }
                
                if ($existingId) {
                    // Update existing category
                    $category = new Category($existingId);
                } else {
                    // Create new category
                    $category = new Category();
                }
                
                $category->id_parent = $parentId;
                $category->active = !empty($row['Active (0/1)']) ? (int)$row['Active (0/1)'] : 1;
                
                // CRITICAL: Inicializar arrays ANTES de asignar
                $category->name = array();
                $category->link_rewrite = array();
                $category->description = array();
                $category->meta_title = array();
                $category->meta_description = array();
                
                // Get all languages
                $languages = Language::getLanguages(false);
                $defaultLangId = Configuration::get('PS_LANG_DEFAULT');
                
                foreach ($languages as $lang) {
                    $langId = $lang['id_lang'];
                    $categoryName = !empty($row['Name *']) ? trim($row['Name *']) : 'Category';
                    
                    $category->name[$langId] = $categoryName;
                    
                    // Generar URL amigable
                    if (!empty($row['Friendly URL'])) {
                        $category->link_rewrite[$langId] = Tools::str2url($row['Friendly URL']);
                    } else {
                        $category->link_rewrite[$langId] = Tools::str2url($categoryName);
                    }
                    
                    $category->description[$langId] = !empty($row['Description']) ? $row['Description'] : '';
                    $category->meta_title[$langId] = !empty($row['Meta title']) ? $row['Meta title'] : '';
                    $category->meta_description[$langId] = !empty($row['Meta description']) ? $row['Meta description'] : '';
                }
                
                // Intentar guardar
                try {
                    if ($existingId) {
                        $result = $category->update();
                    } else {
                        $result = $category->add();
                    }
                    
                    if ($result) {
                        $parentMap[$category->name[$defaultLangId]] = $category->id;
                        $this->imported++;
                    } else {
                        $errors = $category->validateFields(false, true);
                        $this->errors[] = "Line $line: Failed to save category '{$row['Name *']}' - " . implode(', ', $errors);
                        $this->skipped++;
                    }
                } catch (Exception $ex) {
                    $this->errors[] = "Line $line: Exception saving category - " . $ex->getMessage();
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
        Category::regenerateEntireNtree();
    }
    
    private function importBrands($csvFile, $truncate)
    {
        if ($truncate) {
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'manufacturer');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'manufacturer_lang');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'manufacturer_shop');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, ';');
        $line = 1;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                // Buscar marca por nombre, no por ID
                $existingId = (int)Db::getInstance()->getValue(
                    'SELECT id_manufacturer FROM `'._DB_PREFIX_.'manufacturer` WHERE name = \''.pSQL($row['Name *']).'\' LIMIT 1'
                );
                
                if ($existingId) {
                    $brand = new Manufacturer($existingId);
                } else {
                    $brand = new Manufacturer();
                }
                
                $brand->name = !empty($row['Name *']) ? $row['Name *'] : 'Brand';
                $brand->active = !empty($row['Active (0/1)']) ? (int)$row['Active (0/1)'] : 1;
                
                // Multi-lang fields
                $brand->short_description = array();
                $brand->description = array();
                $brand->meta_title = array();
                $brand->meta_description = array();
                
                $languages = Language::getLanguages(false);
                foreach ($languages as $lang) {
                    $langId = $lang['id_lang'];
                    $brand->short_description[$langId] = !empty($row['Short description']) ? $row['Short description'] : '';
                    $brand->description[$langId] = !empty($row['Description']) ? $row['Description'] : '';
                    $brand->meta_title[$langId] = !empty($row['Meta title']) ? $row['Meta title'] : '';
                    $brand->meta_description[$langId] = !empty($row['Meta description']) ? $row['Meta description'] : '';
                }
                
                if ($brand->save()) {
                    $this->imported++;
                } else {
                    $this->errors[] = "Line $line: Failed to save brand '".$row['Name']."'";
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
    }
    
    private function importSuppliers($csvFile, $truncate)
    {
        if ($truncate) {
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'supplier');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'supplier_lang');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'supplier_shop');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, ';');
        $line = 1;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                // Buscar supplier por nombre, no por ID
                $existingId = (int)Db::getInstance()->getValue(
                    'SELECT id_supplier FROM `'._DB_PREFIX_.'supplier` WHERE name = \''.pSQL($row['Name *']).'\' LIMIT 1'
                );
                
                if ($existingId) {
                    $supplier = new Supplier($existingId);
                } else {
                    $supplier = new Supplier();
                }
                
                $supplier->name = !empty($row['Name *']) ? $row['Name *'] : 'Supplier';
                $supplier->active = !empty($row['Active (0/1)']) ? (int)$row['Active (0/1)'] : 1;
                
                $supplier->description = array();
                $supplier->meta_title = array();
                $supplier->meta_description = array();
                
                $languages = Language::getLanguages(false);
                foreach ($languages as $lang) {
                    $langId = $lang['id_lang'];
                    $supplier->description[$langId] = !empty($row['Description']) ? $row['Description'] : '';
                    $supplier->meta_title[$langId] = !empty($row['Meta title']) ? $row['Meta title'] : '';
                    $supplier->meta_description[$langId] = !empty($row['Meta description']) ? $row['Meta description'] : '';
                }
                
                if ($supplier->save()) {
                    $this->imported++;
                } else {
                    $this->errors[] = "Line $line: Failed to save supplier '".$row['Name']."'";
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
    }
    
    private function importProducts($csvFile, $truncate)
    {
        if ($truncate) {
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'product');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'product_lang');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'product_shop');
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'category_product');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, ';');
        $line = 1;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                // Buscar producto por referencia, no por ID
                $existingId = 0;
                if (!empty($row['Reference #'])) {
                    $existingId = (int)Db::getInstance()->getValue(
                        'SELECT id_product FROM `'._DB_PREFIX_.'product` WHERE reference = \''.pSQL($row['Reference #']).'\' LIMIT 1'
                    );
                }
                
                if ($existingId) {
                    $product = new Product($existingId);
                } else {
                    $product = new Product();
                }
                
                // Campos básicos obligatorios
                $product->active = !empty($row['Active (0/1)']) ? (int)$row['Active (0/1)'] : 1;
                $product->reference = !empty($row['Reference #']) ? trim($row['Reference #']) : '';
                $product->ean13 = !empty($row['EAN13']) ? trim($row['EAN13']) : '';
                $product->upc = !empty($row['UPC']) ? trim($row['UPC']) : '';
                $product->price = !empty($row['Price tax excluded']) ? (float)str_replace(',', '.', $row['Price tax excluded']) : 0;
                $product->wholesale_price = !empty($row['Wholesale price']) ? (float)str_replace(',', '.', $row['Wholesale price']) : 0;
                $product->quantity = !empty($row['Quantity']) ? (int)$row['Quantity'] : 0;
                
                // Campos obligatorios adicionales para PS9
                $product->id_tax_rules_group = !empty($row['Tax rule ID']) ? (int)$row['Tax rule ID'] : 0;
                $product->id_shop_default = (int)Configuration::get('PS_SHOP_DEFAULT');
                $product->redirect_type = '404';
                $product->visibility = !empty($row['Visibility']) ? $row['Visibility'] : 'both';
                $product->available_for_order = 1;
                $product->show_price = 1;
                $product->online_only = 0;
                $product->condition = 'new';
                
                // Categories - puede venir como IDs numéricos o nombres
                $catIds = array();
                if (!empty($row['Categories (x,y,z...)'])) {
                    $catValues = array_map('trim', explode(',', $row['Categories (x,y,z...)']));
                    
                    foreach ($catValues as $catValue) {
                        if (is_numeric($catValue)) {
                            // Es un ID numérico - verificar que existe
                            $catId = (int)$catValue;
                            $exists = Db::getInstance()->getValue(
                                'SELECT id_category FROM `'._DB_PREFIX_.'category` WHERE id_category = '.$catId
                            );
                            if ($exists) {
                                $catIds[] = $catId;
                            }
                        } else {
                            // Es un nombre - buscar por nombre
                            try {
                                $sql = 'SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                                        INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category)
                                        WHERE cl.name = \''.pSQL($catValue).'\' 
                                        LIMIT 1';
                                $catId = (int)Db::getInstance()->getValue($sql);
                                
                                if ($catId) {
                                    $catIds[] = $catId;
                                }
                            } catch (Exception $sqlEx) {
                                $this->warnings[] = "Line $line: SQL error finding category '$catValue'";
                            }
                        }
                    }
                    
                    if (empty($catIds)) {
                        $catIds[] = 2; // Default to Home
                    }
                    
                    $product->id_category_default = $catIds[0];
                }
                
                // Multi-lang fields - INICIALIZAR arrays
                $product->name = array();
                $product->description = array();
                $product->description_short = array();
                $product->link_rewrite = array();
                $product->meta_title = array();
                $product->meta_description = array();
                
                $languages = Language::getLanguages(false);
                $defaultLangId = Configuration::get('PS_LANG_DEFAULT');
                
                foreach ($languages as $lang) {
                    $langId = $lang['id_lang'];
                    $productName = !empty($row['Name *']) ? trim($row['Name *']) : 'Product';
                    
                    $product->name[$langId] = $productName;
                    $product->description[$langId] = !empty($row['Description']) ? $row['Description'] : '';
                    $product->description_short[$langId] = !empty($row['Summary']) ? $row['Summary'] : '';
                    
                    if (!empty($row['URL rewritten'])) {
                        $product->link_rewrite[$langId] = Tools::str2url($row['URL rewritten']);
                    } else {
                        $product->link_rewrite[$langId] = Tools::str2url($productName);
                    }
                    
                    $product->meta_title[$langId] = !empty($row['Meta title']) ? $row['Meta title'] : '';
                    $product->meta_description[$langId] = !empty($row['Meta description']) ? $row['Meta description'] : '';
                }
                
                // Intentar guardar
                try {
                    if ($existingId) {
                        $result = $product->update();
                    } else {
                        $result = $product->add();
                    }
                    
                    if ($result) {
                        // Assign categories
                        if (!empty($catIds)) {
                            $product->updateCategories($catIds);
                        }
                        
                        // Update stock
                        StockAvailable::setQuantity($product->id, 0, $product->quantity);
                        $this->imported++;
                    } else {
                        $errors = $product->validateFields(false, true);
                        $this->errors[] = "Line $line: Failed to save product '{$row['Name *']}' - " . implode(', ', $errors);
                        $this->skipped++;
                    }
                } catch (Exception $ex) {
                    $this->errors[] = "Line $line: Exception saving product - " . $ex->getMessage();
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
    }
    
    private function importCustomers($csvFile, $truncate)
    {
        if ($truncate) {
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'customer WHERE id_customer > 1');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, ';');
        $line = 1;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                if (empty($row['Email'])) {
                    $this->warnings[] = "Line $line: Email required, skipping";
                    $this->skipped++;
                    continue;
                }
                
                // Check if customer exists
                $existingId = (int)Db::getInstance()->getValue(
                    'SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email = \''.pSQL($row['Email']).'\' LIMIT 1'
                );
                
                if ($existingId) {
                    $customer = new Customer($existingId);
                } else {
                    $customer = new Customer();
                }
                
                $customer->email = $row['Email'];
                $customer->firstname = !empty($row['First name']) ? $row['First name'] : 'Customer';
                $customer->lastname = !empty($row['Last name']) ? $row['Last name'] : 'Customer';
                $customer->active = !empty($row['Active (0/1)']) ? (int)$row['Active (0/1)'] : 1;
                
                if (!$existingId && !empty($row['Password'])) {
                    $customer->passwd = Tools::hash($row['Password']);
                }
                
                if ($customer->save()) {
                    $this->imported++;
                } else {
                    $this->errors[] = "Line $line: Failed to save customer '".$row['Email']."'";
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
    }
    
    private function importAddresses($csvFile, $truncate)
    {
        if ($truncate) {
            Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'address');
        }
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file");
        }
        
        // Skip UTF-8 BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 0, ';');
        $line = 1;
        
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $line++;
            
            if (count($data) < count($header)) {
                $this->warnings[] = "Line $line: Not enough columns, skipping";
                $this->skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            
            try {
                // NUNCA usar ID - siempre crear nueva dirección
                // Las direcciones se duplican intencionalmente para evitar conflictos
                $address = new Address();
                
                // Find customer
                if (!empty($row['Email'])) {
                    $customerId = (int)Db::getInstance()->getValue(
                        'SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email = \''.pSQL($row['Email']).'\' LIMIT 1'
                    );
                    
                    if (!$customerId) {
                        $this->warnings[] = "Line $line: Customer '".$row['Email']."' not found, skipping";
                        $this->skipped++;
                        continue;
                    }
                    
                    $address->id_customer = $customerId;
                }
                
                // Find country
                $countryId = 0;
                if (!empty($row['Country'])) {
                    $countryId = (int)Db::getInstance()->getValue(
                        'SELECT id_country FROM `'._DB_PREFIX_.'country` WHERE iso_code = \''.pSQL($row['Country']).\' 
                         OR name = \''.pSQL($row['Country']).'\' LIMIT 1'
                    );
                }
                
                if (!$countryId) {
                    $countryId = Configuration::get('PS_COUNTRY_DEFAULT');
                }
                
                $address->id_country = $countryId;
                $address->alias = !empty($row['Alias']) ? $row['Alias'] : 'Address';
                $address->firstname = !empty($row['First name']) ? $row['First name'] : '';
                $address->lastname = !empty($row['Last name']) ? $row['Last name'] : '';
                $address->address1 = !empty($row['Address']) ? $row['Address'] : '';
                $address->address2 = !empty($row['Address (2)']) ? $row['Address (2)'] : '';
                $address->postcode = !empty($row['Zip/Postal code']) ? $row['Zip/Postal code'] : '';
                $address->city = !empty($row['City']) ? $row['City'] : '';
                $address->phone = !empty($row['Phone']) ? $row['Phone'] : '';
                $address->phone_mobile = !empty($row['Mobile phone']) ? $row['Mobile phone'] : '';
                
                if ($address->save()) {
                    $this->imported++;
                } else {
                    $this->errors[] = "Line $line: Failed to save address";
                    $this->skipped++;
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Line $line: ".$e->getMessage();
                $this->skipped++;
            }
        }
        
        fclose($handle);
    }
}
