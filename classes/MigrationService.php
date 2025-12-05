<?php
/**
 * Migration Service - Handles database export and compatibility
 */

class MigrationService
{
    private $db;
    private $compatibleTables = array(
        // Customers & Addresses
        'address' => 'Customer addresses',
        'customer' => 'Customer data',
        'customer_group' => 'Customer groups',
        
        // Categories - CRITICAL for catalog
        'category' => 'Product categories',
        'category_lang' => 'Category translations',
        'category_shop' => 'Category shop association',
        'category_product' => 'Category-Product relations',
        
        // Products - CRITICAL
        'product' => 'Products',
        'product_lang' => 'Product translations',
        'product_shop' => 'Product shop association',
        'product_attribute' => 'Product combinations',
        'product_attribute_shop' => 'Product attribute shop',
        'product_attribute_combination' => 'Combination attributes',
        'product_attribute_image' => 'Product combination images',
        
        // Stock - CRITICAL for product display
        'stock_available' => 'Stock availability',
        
        // Images - CRITICAL
        'image' => 'Product images',
        'image_lang' => 'Image translations',
        'image_shop' => 'Image shop association',
        
        // Manufacturers & Suppliers
        'manufacturer' => 'Manufacturers/Brands',
        'manufacturer_lang' => 'Manufacturer translations',
        'manufacturer_shop' => 'Manufacturer shop association',
        'supplier' => 'Suppliers',
        'supplier_lang' => 'Supplier translations',
        'supplier_shop' => 'Supplier shop association',
        
        // Product Features
        'feature' => 'Product features',
        'feature_lang' => 'Feature translations',
        'feature_shop' => 'Feature shop association',
        'feature_product' => 'Product-Feature relations',
        'feature_value' => 'Feature values',
        'feature_value_lang' => 'Feature value translations',
        
        // Product Attributes (size, color, etc)
        'attribute_group' => 'Attribute groups',
        'attribute_group_lang' => 'Attribute group translations',
        'attribute_group_shop' => 'Attribute group shop',
        'attribute' => 'Attributes',
        'attribute_lang' => 'Attribute translations',
        'attribute_shop' => 'Attribute shop association',
        
        // Product Tags
        'tag' => 'Product tags',
        'product_tag' => 'Product-Tag relations',
        
        // SEO & URLs
        'meta' => 'SEO meta data',
        'meta_lang' => 'Meta translations',
        
        // Orders
        'orders' => 'Customer orders',
        'order_detail' => 'Order line items',
        'order_invoice' => 'Order invoices',
        'order_payment' => 'Order payments',
        'order_carrier' => 'Order shipping',
        'order_history' => 'Order status history',
        'order_state' => 'Order states',
        'order_state_lang' => 'Order state translations',
        
        // Cart
        'cart' => 'Shopping carts',
        'cart_product' => 'Cart products',
        
        // CMS
        'cms' => 'CMS pages',
        'cms_lang' => 'CMS translations',
        'cms_category' => 'CMS categories',
        'cms_category_lang' => 'CMS category translations',
        
        // Taxes
        'tax' => 'Taxes',
        'tax_lang' => 'Tax translations',
        'tax_rule' => 'Tax rules',
        'tax_rules_group' => 'Tax rule groups',
        'tax_rules_group_shop' => 'Tax rule group shop',
        
        // Shipping
        'carrier' => 'Shipping carriers',
        'carrier_lang' => 'Carrier translations',
        'carrier_shop' => 'Carrier shop association',
        
        // Geographic
        'zone' => 'Geographic zones',
        'country' => 'Countries',
        'country_lang' => 'Country translations',
        'country_shop' => 'Country shop association',
        'state' => 'States/Provinces',
        
        // Currency & Language
        'currency' => 'Currencies',
        'currency_shop' => 'Currency shop association',
        'lang' => 'Languages',
        'lang_shop' => 'Language shop association',
        
        // Configuration
        'configuration' => 'Configuration settings',
        'configuration_lang' => 'Configuration translations',
        
        // System
        'hook' => 'Hooks',
        'module' => 'Installed modules',
        'shop' => 'Shops (multistore)',
        'shop_group' => 'Shop groups',
        'shop_url' => 'Shop URLs',
    );

    public function __construct()
    {
        $this->db = Db::getInstance();
    }

    /**
     * Adapt table name from source prefix to target prefix
     */
    private function adaptTableName($tableName)
    {
        // Si la tabla ya tiene el prefijo correcto del destino, devolverla tal cual
        if (strpos($tableName, _DB_PREFIX_) === 0) {
            return $tableName;
        }
        
        // Detectar prefijos comunes de origen
        $commonPrefixes = array('ps_', 'prestashop_', 'presta_', 'shop_');
        
        foreach ($commonPrefixes as $prefix) {
            if (strpos($tableName, $prefix) === 0) {
                // Extraer el nombre de la tabla sin prefijo
                $tableNameWithoutPrefix = substr($tableName, strlen($prefix));
                // Aplicar el prefijo del destino
                return _DB_PREFIX_ . $tableNameWithoutPrefix;
            }
        }
        
        // Si no tiene ningún prefijo conocido, añadir el prefijo del destino
        return _DB_PREFIX_ . $tableName;
    }

    /**
     * Get actual table name in target database (case-insensitive search)
     */
    private function findActualTableName($tableName)
    {
        $adaptedName = $this->adaptTableName($tableName);
        
        // Verificar si existe con el nombre adaptado
        if ($this->tableExists($adaptedName)) {
            return $adaptedName;
        }
        
        // Buscar en todas las tablas de la base de datos
        try {
            $tables = $this->db->executeS('SHOW TABLES');
            $tableNameWithoutPrefix = str_replace(_DB_PREFIX_, '', $adaptedName);
            
            foreach ($tables as $table) {
                $tableName = reset($table);
                $currentTableWithoutPrefix = str_replace(_DB_PREFIX_, '', $tableName);
                
                // Comparación case-insensitive del nombre sin prefijo
                if (strtolower($currentTableWithoutPrefix) === strtolower($tableNameWithoutPrefix)) {
                    return $tableName;
                }
            }
        } catch (Exception $e) {
            // Si falla, devolver el nombre adaptado
        }
        
        return $adaptedName;
    }

    public function getCompatibleTables()
    {
        $tables = array();
        foreach ($this->compatibleTables as $table => $description) {
            $tables[] = array(
                'name' => _DB_PREFIX_ . $table,
                'description' => $description,
                'exists' => $this->tableExists(_DB_PREFIX_ . $table),
            );
        }
        return $tables;
    }

    public function tableExists($tableName)
    {
        // Convertimos a string y limpiamos espacios
        $tableName = trim((string) $tableName);

        // Si viene vacío, claramente no existe
        if ($tableName === '') {
            return false;
        }

        // VERIFICACIÓN REAL: Intentar contar registros de la tabla
        try {
            // Método más simple: intentar hacer un SELECT
            $this->db->executeS('SELECT 1 FROM `' . pSQL($tableName) . '` LIMIT 1');
            return true;
        } catch (Exception $e) {
            // Si da error, la tabla no existe
            return false;
        }
    }
    
    /**
     * Get detailed table info for debugging
     */
    public function getTableInfo($tableName)
    {
        if (!$this->tableExists($tableName)) {
            return array(
                'exists' => false,
                'rows' => 0,
            );
        }
        
        try {
            $count = $this->db->getValue('SELECT COUNT(*) FROM `' . pSQL($tableName) . '`');
            return array(
                'exists' => true,
                'rows' => (int)$count,
            );
        } catch (Exception $e) {
            return array(
                'exists' => true,
                'rows' => 0,
                'error' => $e->getMessage(),
            );
        }
    }




    public function exportToSingleFile($selectedTables, $format, $exportAll = false)
    {
        $tables = $exportAll ? $this->getAllCompatibleTableNames() : $selectedTables;
        
        if (empty($tables)) {
            throw new Exception('No tables selected for export');
        }

        switch ($format) {
            case 'json':
                return $this->exportToJSON($tables);
            case 'sql':
                return $this->exportToSQL($tables);
            case 'csv':
                return $this->exportToCSV($tables, true); // Single CSV with all tables
            default:
                throw new Exception('Invalid export format');
        }
    }

    public function exportToMultipleFiles($selectedTables, $format, $exportAll = false)
    {
        $tables = $exportAll ? $this->getAllCompatibleTableNames() : $selectedTables;
        
        if (empty($tables)) {
            throw new Exception('No tables selected for export');
        }

        $files = array();
        foreach ($tables as $table) {
            $tableName = str_replace(_DB_PREFIX_, '', $table);
            $filename = $tableName . '.' . $format;
            
            switch ($format) {
                case 'json':
                    $files[$filename] = $this->exportToJSON([$table]);
                    break;
                case 'sql':
                    $files[$filename] = $this->exportToSQL([$table]);
                    break;
                case 'csv':
                    $files[$filename] = $this->exportToCSV([$table], false);
                    break;
            }
        }
        
        return $files;
    }

    public function getAllCompatibleTableNames()
    {
        $tables = array();
        $notFound = array();
        
        foreach (array_keys($this->compatibleTables) as $table) {
            $fullName = _DB_PREFIX_ . $table;
            if ($this->tableExists($fullName)) {
                $tables[] = $fullName;
            } else {
                $notFound[] = $fullName;
            }
        }
        
        // Debug: escribir en log si hay tablas no encontradas
        if (!empty($notFound) && defined('_PS_ROOT_DIR_')) {
            $logFile = _PS_ROOT_DIR_ . '/var/logs/migration_export.log';
            $logDir = dirname($logFile);
            
            if (!file_exists($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            
            $logContent = date('Y-m-d H:i:s') . " - Export Debug\n";
            $logContent .= "Tablas encontradas: " . count($tables) . "\n";
            $logContent .= "Tablas NO encontradas: " . count($notFound) . "\n";
            $logContent .= "Tablas encontradas: " . implode(', ', array_slice($tables, 0, 10)) . "...\n";
            $logContent .= "Tablas faltantes: " . implode(', ', array_slice($notFound, 0, 10)) . "...\n\n";
            
            @file_put_contents($logFile, $logContent, FILE_APPEND);
        }
        
        return $tables;
    }

    private function exportToJSON($tables)
    {
        $data = array(
            'export_date' => date('Y-m-d H:i:s'),
            'ps_version_source' => _PS_VERSION_,
            'db_prefix' => _DB_PREFIX_,
            'tables' => [],
        );

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $sql = 'SELECT * FROM `' . pSQL($table) . '`';
            $rows = $this->db->executeS($sql);
            
            $data['tables'][$table] = array(
                'row_count' => count($rows),
                'data' => $rows ?: [],
            );
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function exportToSQL($tables)
    {
        $sql = "-- PrestaShop Migration Export\n";
        $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Source Version: " . _PS_VERSION_ . "\n";
        $sql .= "-- Compatible with: PrestaShop 9\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Columnas incompatibles con PS 9
        $incompatibleColumns = array('meta_keywords', 'shipping_number');

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            // Get table structure
            $createTableResult = $this->db->executeS('SHOW CREATE TABLE `' . pSQL($table) . '`');
            if (!empty($createTableResult)) {
                $createTable = $createTableResult[0]['Create Table'];
                
                $sql .= "-- Table: $table\n";
                $sql .= "DROP TABLE IF EXISTS `" . pSQL($table) . "`;\n";
                $sql .= $createTable . ";\n\n";

                // Get table data
                $rows = $this->db->executeS('SELECT * FROM `' . pSQL($table) . '`');
                
                if (!empty($rows)) {
                    $sql .= "-- Data for table: $table (" . count($rows) . " rows)\n";
                    
                    // Filtrar columnas incompatibles
                    $allColumns = array_keys($rows[0]);
                    $columns = array();
                    
                    foreach ($allColumns as $col) {
                        if (!in_array($col, $incompatibleColumns)) {
                            $columns[] = $col;
                        }
                    }
                    
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    
                    // Usar INSERT IGNORE para tablas _lang, _shop, order_detail
                    $useIgnore = (strpos($table, '_lang') !== false || 
                                 strpos($table, '_shop') !== false || 
                                 strpos($table, 'order_detail') !== false);
                    
                    $insertCommand = $useIgnore ? 'INSERT IGNORE INTO' : 'INSERT INTO';
                    
                    // Batch inserts
                    $batchSize = 100;
                    $batches = array_chunk($rows, $batchSize);
                    
                    foreach ($batches as $batch) {
                        $valueGroups = array();
                        foreach ($batch as $row) {
                            $values = array();
                            foreach ($columns as $col) {
                                $value = $row[$col];
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $escapedValue = str_replace(
                                        array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                                        array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
                                        $value
                                    );
                                    $values[] = "'" . $escapedValue . "'";
                                }
                            }
                            $valueGroups[] = '(' . implode(', ', $values) . ')';
                        }
                        $sql .= "$insertCommand `" . pSQL($table) . "` ($columnList) VALUES\n" . implode(",\n", $valueGroups) . ";\n";
                    }
                    $sql .= "\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $sql;
    }

    private function exportToCSV($tables, $singleFile = false)
    {
        if ($singleFile) {
            // All tables in one CSV with table identifier
            $csv = "table_name,row_data\n";
            
            foreach ($tables as $table) {
                if (!$this->tableExists($table)) {
                    continue;
                }

                $rows = $this->db->executeS('SELECT * FROM `' . pSQL($table) . '`');
                foreach ($rows as $row) {
                    $csv .= '"' . $table . '","' . str_replace('"', '""', json_encode($row)) . "\"\n";
                }
            }
            
            return $csv;
        } else {
            // Single table CSV
            $table = $tables[0];
            if (!$this->tableExists($table)) {
                return '';
            }

            $rows = $this->db->executeS('SELECT * FROM `' . pSQL($table) . '`');
            
            if (empty($rows)) {
                return '';
            }

            // Header
            $csv = implode(',', array_keys($rows[0])) . "\n";
            
            // Data
            foreach ($rows as $row) {
                $values = array();
                foreach ($row as $value) {
                    $values[] = '"' . str_replace('"', '""', $value) . '"';
                }
                $csv .= implode(',', $values) . "\n";
            }
            
            return $csv;
        }
    }

    /**
     * Import from JSON file
     */
    public function importFromJSON($jsonContent, $mode = 'append', $validate = true, $backup = true)
    {
        $data = json_decode($jsonContent, true);
        
        if (!$data || !isset($data['tables'])) {
            throw new Exception('Invalid JSON format');
        }

        $result = array(
            'tables_imported' => 0,
            'rows_imported' => 0,
            'warnings' => [],
            'skipped_fields' => [],
        );

        foreach ($data['tables'] as $tableName => $tableData) {
            // Adaptar nombre de tabla al prefijo del destino
            $targetTableName = $this->findActualTableName($tableName);
            
            if (!$this->tableExists($targetTableName)) {
                $result['warnings'][] = "Table $tableName (searched as $targetTableName) does not exist in target database";
                continue;
            }

            if ($backup) {
                $this->backupTable($targetTableName);
            }

            if ($validate) {
                $validationResult = $this->validateAndFilterTableData($targetTableName, $tableData['data']);
                if (!empty($validationResult['errors'])) {
                    $result['warnings'][] = "Validation errors for $targetTableName: " . implode(', ', $validationResult['errors']);
                }
                if (!empty($validationResult['skipped_fields'])) {
                    $result['skipped_fields'][$targetTableName] = $validationResult['skipped_fields'];
                }
                $tableData['data'] = $validationResult['data'];
            }

            if ($mode === 'replace') {
                $this->db->execute('TRUNCATE TABLE `' . pSQL($targetTableName) . '`');
            }

            $insertResult = $this->insertTableData($targetTableName, $tableData['data'], $mode);
            
            if (is_array($insertResult)) {
                $rowsInserted = $insertResult['inserted'];
                
                if ($rowsInserted > 0) {
                    $result['tables_imported']++;
                    $result['rows_imported'] += $rowsInserted;
                }
                
                // Solo reportar duplicados si son muchos
                if ($insertResult['duplicates'] > 0 && $mode === 'append') {
                    $result['warnings'][] = sprintf(
                        "Table %s: %d rows inserted, %d duplicates skipped",
                        $targetTableName,
                        $rowsInserted,
                        $insertResult['duplicates']
                    );
                }
                
                if ($insertResult['errors'] > 0) {
                    $result['warnings'][] = sprintf(
                        "Table %s: %d errors occurred during import",
                        $targetTableName,
                        $insertResult['errors']
                    );
                }
            } else {
                // Compatibilidad con retorno antiguo (número)
                $rowsInserted = $insertResult;
                if ($rowsInserted > 0) {
                    $result['tables_imported']++;
                    $result['rows_imported'] += $rowsInserted;
                }
            }
        }

        return $result;
    }

    /**
     * Import from SQL file
     */
    public function importFromSQL($sqlContent, $mode = 'append', $validate = true, $backup = true)
    {
        $result = array(
            'tables_imported' => 0,
            'rows_imported' => 0,
            'warnings' => [],
        );

        // Split SQL into individual statements
        $statements = $this->splitSQLStatements($sqlContent);
        
        $affectedTables = array();
        $skipCreateTable = ($mode !== 'replace'); // Skip CREATE TABLE if not in replace mode
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
                continue;
            }

            // Skip SET statements
            if (preg_match('/^SET\s+/i', $statement)) {
                try {
                    $this->db->execute($statement);
                } catch (Exception $e) {
                    // Ignore SET statement errors
                }
                continue;
            }

            // Handle DROP TABLE statements
            if (preg_match('/DROP\s+TABLE/i', $statement)) {
                if ($mode === 'replace') {
                    try {
                        $this->db->execute($statement);
                    } catch (Exception $e) {
                        $result['warnings'][] = 'DROP TABLE warning: ' . $e->getMessage();
                    }
                }
                continue;
            }

            // Handle CREATE TABLE statements
            if (preg_match('/CREATE\s+TABLE/i', $statement)) {
                if ($skipCreateTable) {
                    continue; // Skip CREATE TABLE if not in replace mode
                }
                try {
                    $this->db->execute($statement);
                } catch (Exception $e) {
                    $result['warnings'][] = 'CREATE TABLE warning: ' . $e->getMessage();
                }
                continue;
            }

            // Extract table name from INSERT statements
            if (preg_match('/INSERT\s+INTO\s+`?([\w_]+)`?/i', $statement, $matches)) {
                $sourceTableName = $matches[1];
                $targetTableName = $this->findActualTableName($sourceTableName);
                
                if (!in_array($targetTableName, $affectedTables)) {
                    $affectedTables[] = $targetTableName;
                    
                    if ($backup) {
                        $this->backupTable($targetTableName);
                    }
                    
                    // TRUNCATE table in replace mode
                    if ($mode === 'replace') {
                        try {
                            $this->db->execute('TRUNCATE TABLE `' . pSQL($targetTableName) . '`');
                            $result['warnings'][] = 'Table truncated: ' . $targetTableName;
                        } catch (Exception $e) {
                            $result['warnings'][] = 'Cannot truncate table ' . $targetTableName . ': ' . $e->getMessage();
                        }
                    }
                }

                // Reemplazar el nombre de tabla en el statement si es necesario
                if ($sourceTableName !== $targetTableName) {
                    $statement = preg_replace(
                        '/INSERT\s+INTO\s+`?' . preg_quote($sourceTableName, '/') . '`?/i',
                        'INSERT INTO `' . $targetTableName . '`',
                        $statement
                    );
                }
                
                // Modificar INSERT según el modo
                if ($mode === 'skip-duplicates') {
                    // Cambiar INSERT por INSERT IGNORE
                    $statement = preg_replace('/^INSERT\s+INTO/i', 'INSERT IGNORE INTO', $statement);
                } elseif ($mode === 'update') {
                    // Agregar ON DUPLICATE KEY UPDATE
                    // Extraer columnas del INSERT
                    if (preg_match('/\(([^)]+)\)\s*VALUES/i', $statement, $colMatches)) {
                        $columns = array_map('trim', explode(',', str_replace('`', '', $colMatches[1])));
                        $updates = array();
                        foreach ($columns as $col) {
                            $cleanCol = trim($col);
                            $updates[] = '`' . $cleanCol . '` = VALUES(`' . $cleanCol . '`)';
                        }
                        $statement .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
                    }
                }

                try {
                    $this->db->execute($statement);
                    $affected = $this->db->Affected_Rows();
                    if ($affected > 0 || $mode === 'skip-duplicates') {
                        $result['rows_imported'] += ($affected > 0) ? $affected : 0;
                    }
                } catch (Exception $e) {
                    // Solo reportar errores que NO sean duplicados en modo skip-duplicates
                    $isDuplicateError = (strpos($e->getMessage(), '1062 Duplicate entry') !== false || 
                                        strpos($e->getMessage(), 'Duplicate entry') !== false);
                    
                    if (!$isDuplicateError || $mode !== 'skip-duplicates') {
                        $result['warnings'][] = 'INSERT error in ' . $targetTableName . ' (from ' . $sourceTableName . '): ' . $e->getMessage();
                    }
                }
            }
        }

        $result['tables_imported'] = count($affectedTables);

        return $result;
    }

    /**
     * Import from CSV file
     */
    public function importFromCSV($csvContent, $mode = 'append', $validate = true, $backup = true)
    {
        $result = array(
            'tables_imported' => 0,
            'rows_imported' => 0,
            'warnings' => [],
            'skipped_fields' => [],
        );

        $lines = explode("\n", $csvContent);
        if (empty($lines)) {
            throw new Exception('Empty CSV file');
        }

        // Check if it's a single-file CSV (with table_name column) or single-table CSV
        $firstLine = str_getcsv($lines[0]);
        
        if ($firstLine[0] === 'table_name' && count($firstLine) === 2 && $firstLine[1] === 'row_data') {
            // Multi-table CSV format
            return $this->importFromMultiTableCSV($lines, $mode, $validate, $backup);
        } else {
            // Single-table CSV format (needs table name to be specified separately)
            throw new Exception('Single-table CSV import requires table name specification. Use multi-table CSV format instead.');
        }
    }

    /**
     * Import from multi-table CSV format
     */
    private function importFromMultiTableCSV($lines, $mode, $validate, $backup)
    {
        $result = array(
            'tables_imported' => 0,
            'rows_imported' => 0,
            'warnings' => [],
            'skipped_fields' => [],
        );

        $tableData = array();
        
        // Skip header line
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 2) {
                continue;
            }

            $tableName = $row[0];
            $rowDataJson = $row[1];
            
            $rowData = json_decode($rowDataJson, true);
            if (!$rowData) {
                $result['warnings'][] = "Invalid JSON data for table $tableName";
                continue;
            }

            if (!isset($tableData[$tableName])) {
                $tableData[$tableName] = array();
            }
            $tableData[$tableName][] = $rowData;
        }

        // Process each table
        foreach ($tableData as $tableName => $rows) {
            // Adaptar nombre de tabla al prefijo del destino
            $targetTableName = $this->findActualTableName($tableName);
            
            if (!$this->tableExists($targetTableName)) {
                $result['warnings'][] = "Table $tableName (searched as $targetTableName) does not exist in target database";
                continue;
            }

            if ($backup) {
                $this->backupTable($targetTableName);
            }

            if ($validate) {
                $validationResult = $this->validateAndFilterTableData($targetTableName, $rows);
                if (!empty($validationResult['errors'])) {
                    $result['warnings'][] = "Validation errors for $targetTableName: " . implode(', ', $validationResult['errors']);
                }
                if (!empty($validationResult['skipped_fields'])) {
                    $result['skipped_fields'][$targetTableName] = $validationResult['skipped_fields'];
                }
                $rows = $validationResult['data'];
            }

            if ($mode === 'replace') {
                try {
                    $this->db->execute('TRUNCATE TABLE `' . pSQL($targetTableName) . '`');
                } catch (Exception $e) {
                    $result['warnings'][] = "Error truncating $targetTableName: " . $e->getMessage();
                    continue;
                }
            }

            $insertResult = $this->insertTableData($targetTableName, $rows, $mode);
            
            if (is_array($insertResult)) {
                $rowsInserted = $insertResult['inserted'];
                
                if ($rowsInserted > 0) {
                    $result['tables_imported']++;
                    $result['rows_imported'] += $rowsInserted;
                }
                
                // Solo reportar duplicados si son muchos
                if ($insertResult['duplicates'] > 0 && $mode === 'append') {
                    $result['warnings'][] = sprintf(
                        "Table %s: %d rows inserted, %d duplicates skipped",
                        $targetTableName,
                        $rowsInserted,
                        $insertResult['duplicates']
                    );
                }
                
                if ($insertResult['errors'] > 0) {
                    $result['warnings'][] = sprintf(
                        "Table %s: %d errors occurred during import",
                        $targetTableName,
                        $insertResult['errors']
                    );
                }
            } else {
                // Compatibilidad con retorno antiguo
                $rowsInserted = $insertResult;
                if ($rowsInserted > 0) {
                    $result['tables_imported']++;
                    $result['rows_imported'] += $rowsInserted;
                }
            }
        }

        return $result;
    }

    /**
     * Import from ZIP file (multiple files)
     */
    public function importFromZip($zipPath, $mode = 'append', $validate = true, $backup = true)
    {
        $result = array(
            'tables_imported' => 0,
            'rows_imported' => 0,
            'warnings' => [],
        );

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }

        $tempDir = _PS_CACHE_DIR_ . 'migration_import_' . time() . '/';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $zip->extractTo($tempDir);
        $zip->close();

        // Process each file in the ZIP
        $files = scandir($tempDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $tempDir . $file;
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $content = file_get_contents($filePath);

            try {
                if ($extension === 'json') {
                    $fileResult = $this->importFromJSON($content, $mode, $validate, $backup);
                } elseif ($extension === 'sql') {
                    $fileResult = $this->importFromSQL($content, $mode, $validate, $backup);
                } else {
                    continue;
                }

                $result['tables_imported'] += $fileResult['tables_imported'];
                $result['rows_imported'] += $fileResult['rows_imported'];
                $result['warnings'] = array_merge($result['warnings'], $fileResult['warnings']);

            } catch (Exception $e) {
                $result['warnings'][] = "Error processing $file: " . $e->getMessage();
            }
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        return $result;
    }

    /**
     * Backup a table before import
     */
    private function backupTable($tableName)
    {
        $backupTableName = $tableName . '_backup_' . date('YmdHis');
        
        try {
            $this->db->execute('CREATE TABLE `' . pSQL($backupTableName) . '` LIKE `' . pSQL($tableName) . '`');
            $this->db->execute('INSERT INTO `' . pSQL($backupTableName) . '` SELECT * FROM `' . pSQL($tableName) . '`');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate table data structure
     */
    private function validateTableData($tableName, $data)
    {
        $errors = array();
        
        if (empty($data)) {
            return $errors;
        }

        // Get table columns
        $columns = $this->db->executeS('SHOW COLUMNS FROM `' . pSQL($tableName) . '`');
        $columnNames = array_column($columns, 'Field');

        // Check first row for column compatibility
        $firstRow = reset($data);
        foreach (array_keys($firstRow) as $dataColumn) {
            if (!in_array($dataColumn, $columnNames)) {
                $errors[] = "Column '$dataColumn' does not exist in table $tableName";
            }
        }

        return $errors;
    }

    /**
     * Validate and filter table data to match target schema
     * Removes incompatible columns instead of failing
     */
    private function validateAndFilterTableData($tableName, $data)
    {
        $result = array(
            'data' => array(),
            'errors' => array(),
            'skipped_fields' => array(),
        );
        
        if (empty($data)) {
            return $result;
        }

        // Get table columns in target database
        try {
            $columns = $this->db->executeS('SHOW COLUMNS FROM `' . pSQL($tableName) . '`');
            $targetColumns = array();
            $columnTypes = array();
            
            foreach ($columns as $column) {
                $targetColumns[] = $column['Field'];
                $columnTypes[$column['Field']] = array(
                    'type' => $column['Type'],
                    'null' => $column['Null'] === 'YES',
                    'key' => $column['Key'],
                    'default' => $column['Default'],
                );
            }
        } catch (Exception $e) {
            $result['errors'][] = "Cannot read table structure: " . $e->getMessage();
            return $result;
        }

        // Process each row
        foreach ($data as $row) {
            $filteredRow = array();
            
            foreach ($row as $fieldName => $value) {
                if (in_array($fieldName, $targetColumns)) {
                    // Field exists in target, keep it
                    $filteredRow[$fieldName] = $value;
                } else {
                    // Field doesn't exist in target, skip it
                    if (!in_array($fieldName, $result['skipped_fields'])) {
                        $result['skipped_fields'][] = $fieldName;
                    }
                }
            }
            
            // Add default values for required fields that are missing
            foreach ($targetColumns as $targetCol) {
                if (!isset($filteredRow[$targetCol])) {
                    $colInfo = $columnTypes[$targetCol];
                    
                    // Skip auto-increment primary keys
                    if ($colInfo['key'] === 'PRI' && strpos($colInfo['type'], 'int') !== false) {
                        continue;
                    }
                    
                    // Use default value or NULL
                    if ($colInfo['default'] !== null) {
                        $filteredRow[$targetCol] = $colInfo['default'];
                    } elseif ($colInfo['null']) {
                        $filteredRow[$targetCol] = null;
                    } else {
                        // Required field with no default - use safe default based on type
                        if (strpos($colInfo['type'], 'int') !== false) {
                            $filteredRow[$targetCol] = 0;
                        } elseif (strpos($colInfo['type'], 'char') !== false || strpos($colInfo['type'], 'text') !== false) {
                            $filteredRow[$targetCol] = '';
                        } elseif (strpos($colInfo['type'], 'date') !== false) {
                            $filteredRow[$targetCol] = '0000-00-00';
                        } elseif (strpos($colInfo['type'], 'timestamp') !== false) {
                            $filteredRow[$targetCol] = '0000-00-00 00:00:00';
                        }
                    }
                }
            }
            
            if (!empty($filteredRow)) {
                $result['data'][] = $filteredRow;
            }
        }

        return $result;
    }

    /**
     * Insert data into table
     */
    private function insertTableData($tableName, $data, $mode = 'append')
    {
        $rowsInserted = 0;
        $duplicateErrors = 0;
        $otherErrors = 0;
        $maxWarningsPerTable = 5; // Máximo de warnings a mostrar por tabla

        foreach ($data as $row) {
            $columns = array_keys($row);
            $values = array_values($row);

            // Construir INSERT base
            $insertType = ($mode === 'skip-duplicates') ? 'INSERT IGNORE INTO' : 'INSERT INTO';
            $sql = $insertType . ' `' . pSQL($tableName) . '` (`' . implode('`, `', array_map('pSQL', $columns)) . '`) VALUES (';
            
            $valueParts = array();
            foreach ($values as $value) {
                if ($value === null) {
                    $valueParts[] = 'NULL';
                } else {
                    $valueParts[] = "'" . pSQL($value, true) . "'";
                }
            }
            
            $sql .= implode(', ', $valueParts) . ')';

            // ON DUPLICATE KEY UPDATE solo para modo 'update'
            if ($mode === 'update') {
                $sql .= ' ON DUPLICATE KEY UPDATE ';
                $updates = array();
                foreach ($columns as $col) {
                    $updates[] = '`' . pSQL($col) . '` = VALUES(`' . pSQL($col) . '`)';
                }
                $sql .= implode(', ', $updates);
            }

            try {
                $result = $this->db->execute($sql);
                
                // En modo skip-duplicates con INSERT IGNORE, puede retornar false sin error
                if ($mode === 'skip-duplicates') {
                    // INSERT IGNORE retorna true si insertó, false si omitió duplicado
                    // Obtener número de filas afectadas para saber si se insertó
                    $affectedRows = $this->db->Affected_Rows();
                    if ($affectedRows > 0) {
                        $rowsInserted++;
                    } else {
                        $duplicateErrors++; // Se omitió por duplicado, pero no es error
                    }
                } elseif ($result) {
                    $rowsInserted++;
                }
            } catch (Exception $e) {
                // Detectar si es error de duplicado
                if (strpos($e->getMessage(), '1062 Duplicate entry') !== false || 
                    strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $duplicateErrors++;
                    // No mostrar warnings de duplicados
                } else {
                    $otherErrors++;
                    // Solo mostrar algunos errores, no todos
                    if ($otherErrors <= $maxWarningsPerTable) {
                        // El error se reportará en el nivel superior si es necesario
                    }
                }
                continue;
            }
        }

        // Retornar información sobre la inserción
        return array(
            'inserted' => $rowsInserted,
            'duplicates' => $duplicateErrors,
            'errors' => $otherErrors,
        );
    }

    /**
     * Split SQL content into individual statements
     */
    private function splitSQLStatements($sql)
    {
        $statements = array();
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sql[$i - 1] !== '\\') {
                $inString = false;
            }

            $current .= $char;

            if (!$inString && $char === ';') {
                $statements[] = trim($current);
                $current = '';
            }
        }

        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }

        return $statements;
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Export images to ZIP file
     * @return array ['success' => bool, 'file' => string, 'size' => int, 'images_count' => int]
     */
    public function exportImages()
    {
        $result = array(
            'success' => false,
            'file' => null,
            'size' => 0,
            'images_count' => 0,
            'errors' => array()
        );

        try {
            // Aumentar límites para operaciones grandes
            @ini_set('memory_limit', '2048M');
            @ini_set('max_execution_time', '1800'); // 30 minutos
            @set_time_limit(1800);
            
            // Desactivar output buffering para mostrar progreso
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            
            for ($i = 0; $i < ob_get_level(); $i++) {
                ob_end_flush();
            }
            ob_implicit_flush(1);
            
            $zipFilename = 'images_export_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = _PS_MODULE_DIR_ . 'ps178to9migration/exports/' . $zipFilename;
            
            // Crear directorio exports si no existe
            $exportsDir = dirname($zipPath);
            if (!file_exists($exportsDir)) {
                mkdir($exportsDir, 0777, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Could not create ZIP file');
            }

            $imageFolders = array(
                'p' => _PS_PROD_IMG_DIR_,      // Product images
                'c' => _PS_CAT_IMG_DIR_,       // Category images
                'm' => _PS_MANU_IMG_DIR_,      // Manufacturer images
                'su' => _PS_SUPP_IMG_DIR_,     // Supplier images
            );

            $totalImages = 0;

            foreach ($imageFolders as $folder => $path) {
                if (file_exists($path)) {
                    $count = $this->addFolderToZip($zip, $path, 'img/' . $folder);
                    $totalImages += $count;
                }
            }

            $zip->close();

            if ($totalImages === 0) {
                @unlink($zipPath);
                $result['errors'][] = 'No images found to export';
                return $result;
            }

            $result['success'] = true;
            $result['file'] = $zipFilename;
            $result['size'] = filesize($zipPath);
            $result['images_count'] = $totalImages;

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Export images with progress tracking
     * @param string $statusFile Path to status file
     * @return array Result
     */
    public function exportImagesWithProgress($statusFile)
    {
        $result = array(
            'success' => false,
            'file' => null,
            'size' => 0,
            'images_count' => 0,
            'errors' => array()
        );

        try {
            @set_time_limit(600);
            @ini_set('max_execution_time', '600');
            @ini_set('memory_limit', '512M');
            
            $zipFilename = 'images_export_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = _PS_MODULE_DIR_ . 'ps178to9migration/exports/' . $zipFilename;
            
            $exportsDir = dirname($zipPath);
            if (!file_exists($exportsDir)) {
                mkdir($exportsDir, 0777, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Could not create ZIP file');
            }

            $imageFolders = array(
                'p' => array('path' => _PS_PROD_IMG_DIR_, 'name' => 'Product images'),
                'c' => array('path' => _PS_CAT_IMG_DIR_, 'name' => 'Category images'),
                'm' => array('path' => _PS_MANU_IMG_DIR_, 'name' => 'Manufacturer images'),
                'su' => array('path' => _PS_SUPP_IMG_DIR_, 'name' => 'Supplier images'),
            );

            // Contar total de archivos
            $totalFiles = 0;
            foreach ($imageFolders as $folder => $info) {
                if (file_exists($info['path'])) {
                    $totalFiles += $this->countFiles($info['path']);
                }
            }

            $this->updateStatus($statusFile, 'processing', 0, $totalFiles, 'Starting export...');

            $processedFiles = 0;

            foreach ($imageFolders as $folder => $info) {
                if (file_exists($info['path'])) {
                    $this->updateStatus($statusFile, 'processing', $processedFiles, $totalFiles, 'Exporting ' . $info['name'] . '...');
                    $count = $this->addFolderToZipWithProgress($zip, $info['path'], 'img/' . $folder, $statusFile, $processedFiles, $totalFiles);
                    $processedFiles += $count;
                }
            }

            $zip->close();

            if ($processedFiles === 0) {
                @unlink($zipPath);
                $result['errors'][] = 'No images found to export';
                $this->updateStatus($statusFile, 'error', 0, 0, 'No images found');
                return $result;
            }

            $result['success'] = true;
            $result['file'] = $zipFilename;
            $result['size'] = filesize($zipPath);
            $result['images_count'] = $processedFiles;

            $this->updateStatus($statusFile, 'completed', $processedFiles, $processedFiles, 'Export completed');

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->updateStatus($statusFile, 'error', 0, 0, $e->getMessage());
        }

        return $result;
    }

    /**
     * Count files in directory recursively
     */
    private function countFiles($dir)
    {
        $count = 0;
        if (!is_dir($dir)) {
            return 0;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            
            if (is_dir($path)) {
                $count += $this->countFiles($path);
            } else {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Update status file
     */
    private function updateStatus($statusFile, $status, $current, $total, $message)
    {
        $data = array(
            'status' => $status,
            'current' => $current,
            'total' => $total,
            'message' => $message,
            'updated' => time()
        );

        @file_put_contents($statusFile, json_encode($data));
    }

    /**
     * Add folder to ZIP with progress updates
     */
    private function addFolderToZipWithProgress($zip, $folderPath, $zipPath, $statusFile, &$processedFiles, $totalFiles)
    {
        $count = 0;
        
        if (!is_dir($folderPath)) {
            return $count;
        }

        $items = @scandir($folderPath);
        if ($items === false) {
            return $count;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $folderPath . '/' . $item;
            $itemZipPath = $zipPath . '/' . $item;

            if (is_dir($itemPath)) {
                $count += $this->addFolderToZipWithProgress($zip, $itemPath, $itemZipPath, $statusFile, $processedFiles, $totalFiles);
            } else {
                if ($zip->addFile($itemPath, $itemZipPath)) {
                    $count++;
                    $processedFiles++;
                    
                    // Actualizar estado cada 50 archivos
                    if ($processedFiles % 50 === 0) {
                        $this->updateStatus($statusFile, 'processing', $processedFiles, $totalFiles, 'Processing images: ' . $processedFiles . '/' . $totalFiles);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Add folder contents to ZIP recursively
     * @param ZipArchive $zip
     * @param string $folderPath Real path
     * @param string $zipPath Path inside ZIP
     * @return int Number of files added
     */
    private function addFolderToZip($zip, $folderPath, $zipPath)
    {
        static $fileCounter = 0; // Contador estático para progreso
        $count = 0;
        
        if (!is_dir($folderPath)) {
            return $count;
        }

        $items = @scandir($folderPath);
        if ($items === false) {
            return $count;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $folderPath . '/' . $item;
            $itemZipPath = $zipPath . '/' . $item;

            if (is_dir($itemPath)) {
                // Añadir subdirectorio recursivamente
                $count += $this->addFolderToZip($zip, $itemPath, $itemZipPath);
            } else {
                // Añadir archivo
                if (@$zip->addFile($itemPath, $itemZipPath)) {
                    $count++;
                    $fileCounter++;
                    
                    // No enviar output - interfiere con descarga
                }
            }
        }

        return $count;
    }

    /**
     * Import images from ZIP file
     * @param string $zipPath Path to ZIP file
     * @return array ['success' => bool, 'images_imported' => int, 'errors' => array]
     */
    public function importImages($zipPath)
    {
        $result = array(
            'success' => false,
            'images_imported' => 0,
            'errors' => array(),
            'warnings' => array()
        );

        try {
            if (!file_exists($zipPath)) {
                throw new Exception('ZIP file not found: ' . $zipPath);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('Could not open ZIP file');
            }

            $imgBaseDir = _PS_ROOT_DIR_ . '/img/';
            $totalExtracted = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Saltar directorios
                if (substr($filename, -1) === '/') {
                    continue;
                }
                
                // Verificar que sea una ruta de imagen válida
                if (strpos($filename, 'img/') !== 0) {
                    continue;
                }

                // Extraer el archivo
                $targetPath = _PS_ROOT_DIR_ . '/' . $filename;
                $targetDir = dirname($targetPath);

                // Crear directorio si no existe
                if (!file_exists($targetDir)) {
                    if (!mkdir($targetDir, 0755, true)) {
                        $result['warnings'][] = 'Could not create directory: ' . $targetDir;
                        continue;
                    }
                }

                // Extraer archivo
                $content = $zip->getFromIndex($i);
                if ($content !== false && $content !== '') {
                    if (file_put_contents($targetPath, $content)) {
                        $totalExtracted++;
                        // Aplicar permisos correctos
                        @chmod($targetPath, 0644);
                    } else {
                        $result['warnings'][] = 'Could not write file: ' . $filename;
                    }
                } else {
                    if ($content !== '') {
                        $result['warnings'][] = 'Could not read from ZIP: ' . $filename;
                    }
                }
            }

            $zip->close();

            $result['success'] = true;
            $result['images_imported'] = $totalExtracted;

            if ($totalExtracted === 0) {
                $result['warnings'][] = 'No images were extracted from the ZIP file';
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Exporta el tema activo de PrestaShop 1.7.6 a un ZIP
     * 
     * @return array Resultado con 'success', 'file_path', 'theme_name', 'size'
     */
    public function exportTheme()
    {
        $result = array(
            'success' => false,
            'file_path' => '',
            'theme_name' => '',
            'size' => 0,
            'warnings' => array(),
            'errors' => array()
        );

        try {
            // Obtener tema activo - Compatible con PS 1.7.6 y PS 9
            // Método 1: Desde tabla shop (PS 1.7.6)
            $sql = 'SELECT t.* FROM ' . _DB_PREFIX_ . 'theme t 
                    INNER JOIN ' . _DB_PREFIX_ . 'shop s ON s.id_theme = t.id_theme 
                    WHERE s.id_shop = 1 LIMIT 1';
            $theme = $this->db->getRow($sql);
            
            $themeName = null;
            
            if ($theme && isset($theme['directory'])) {
                $themeName = $theme['directory'];
            } else {
                // Método 2: Buscar tema en el directorio themes
                $themesDir = _PS_THEME_DIR_;
                if (is_dir($themesDir)) {
                    $themes = scandir($themesDir);
                    foreach ($themes as $dir) {
                        if ($dir !== '.' && $dir !== '..' && is_dir($themesDir . $dir)) {
                            // Tomar el primer tema encontrado que no sea "default"
                            if ($dir !== 'default' && file_exists($themesDir . $dir . '/config/theme.yml')) {
                                $themeName = $dir;
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!$themeName) {
                // Método 3: Buscar directorio del tema activo
                $themesDir = _PS_ROOT_DIR_ . '/themes/';
                if (is_dir($themesDir)) {
                    $themes = scandir($themesDir);
                    foreach ($themes as $dir) {
                        if ($dir !== '.' && $dir !== '..' && $dir !== 'default' && is_dir($themesDir . $dir)) {
                            $themeName = $dir;
                            break;
                        }
                    }
                }
            }
            
            if (!$themeName) {
                $result['errors'][] = 'No theme found in: ' . (_PS_ROOT_DIR_ . '/themes/');
                return $result;
            }

            // Construir ruta correcta del tema
            $themePath = _PS_ROOT_DIR_ . '/themes/' . $themeName;

            if (!file_exists($themePath)) {
                $result['errors'][] = 'Theme directory not found: ' . $themePath;
                return $result;
            }

            // Crear archivo ZIP
            $zipFilename = 'theme_' . $themeName . '_' . date('Y-m-d_His') . '.zip';
            $zipPath = _PS_CACHE_DIR_ . $zipFilename;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $result['errors'][] = 'Could not create ZIP file';
                return $result;
            }

            // Agregar todos los archivos del tema al ZIP
            $this->addDirectoryToZip($zip, $themePath, 'theme/' . $themeName);

            // Agregar archivo de metadata del tema
            $metadata = array(
                'theme_name' => $themeName,
                'exported_from' => 'PrestaShop 1.7.6',
                'export_date' => date('Y-m-d H:i:s'),
                'notes' => 'This theme requires adaptation for PrestaShop 9'
            );

            $zip->addFromString('theme_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

            // Agregar instrucciones de adaptación
            $instructions = "THEME MIGRATION INSTRUCTIONS\n";
            $instructions .= "==============================\n\n";
            $instructions .= "This theme was exported from PrestaShop 1.7.6\n";
            $instructions .= "It requires adaptation for PrestaShop 9:\n\n";
            $instructions .= "1. Update theme.yml to version 9 format\n";
            $instructions .= "2. Update composer.json dependencies\n";
            $instructions .= "3. Adapt Smarty templates to new structure\n";
            $instructions .= "4. Update JavaScript/CSS assets\n";
            $instructions .= "5. Test all templates and modules\n\n";
            $instructions .= "Theme: " . $themeName . "\n";
            $instructions .= "Exported: " . date('Y-m-d H:i:s') . "\n";

            $zip->addFromString('README_MIGRATION.txt', $instructions);

            $zip->close();

            $result['success'] = true;
            $result['file_path'] = $zipPath;
            $result['theme_name'] = $themeName;
            $result['size'] = filesize($zipPath);
            $result['warnings'][] = 'Theme requires manual adaptation for PrestaShop 9';

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Agrega un directorio completo al ZIP recursivamente
     */
    private function addDirectoryToZip($zip, $sourceDir, $zipPath)
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipPath . '/' . substr($filePath, strlen($sourceDir) + 1);
                
                // Normalizar rutas para Windows
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Obtiene módulos asociados al tema
     */
    private function getThemeModules($themeId)
    {
        $modules = array();
        
        try {
            $sql = 'SELECT m.name, m.version 
                    FROM ' . _DB_PREFIX_ . 'module m
                    INNER JOIN ' . _DB_PREFIX_ . 'theme_meta tm ON 1=1
                    WHERE tm.id_theme = ' . (int)$themeId;
            
            $result = $this->db->executeS($sql);
            if ($result) {
                $modules = $result;
            }
        } catch (Exception $e) {
            // Si falla, continuar sin módulos
        }

        return $modules;
    }

    /**
     * Importa un tema exportado (requiere adaptación manual)
     * NOTA: Solo extrae archivos, NO activa el tema automáticamente
     * 
     * @param string $zipPath Ruta al ZIP del tema
     * @return array Resultado
     */
    public function importTheme($zipPath)
    {
        $result = array(
            'success' => false,
            'theme_name' => '',
            'files_extracted' => 0,
            'warnings' => array(),
            'errors' => array()
        );

        try {
            if (!file_exists($zipPath)) {
                $result['errors'][] = 'ZIP file not found';
                return $result;
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $result['errors'][] = 'Could not open ZIP file';
                return $result;
            }

            // Leer metadata
            $metadataJson = $zip->getFromName('theme_metadata.json');
            if ($metadataJson) {
                $metadata = json_decode($metadataJson, true);
                $result['theme_name'] = $metadata['theme_name'];
            }

            // Extraer a directorio temporal primero
            $tempDir = _PS_CACHE_DIR_ . 'theme_import_' . time();
            mkdir($tempDir, 0755, true);

            $extractedFiles = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                // Solo extraer archivos del tema
                if (strpos($filename, 'theme/') === 0) {
                    $content = $zip->getFromIndex($i);
                    $targetPath = $tempDir . '/' . $filename;
                    $targetDir = dirname($targetPath);
                    
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    
                    if (file_put_contents($targetPath, $content)) {
                        $extractedFiles++;
                    }
                }
            }

            $zip->close();

            $result['success'] = true;
            $result['files_extracted'] = $extractedFiles;
            $result['temp_dir'] = $tempDir;
            
            $result['warnings'][] = 'Theme extracted to temporary directory';
            $result['warnings'][] = 'MANUAL STEPS REQUIRED:';
            $result['warnings'][] = '1. Review and adapt theme files for PrestaShop 9';
            $result['warnings'][] = '2. Update theme.yml configuration';
            $result['warnings'][] = '3. Update composer.json dependencies';
            $result['warnings'][] = '4. Copy adapted theme to themes/ directory';
            $result['warnings'][] = '5. Install theme from backoffice';
            $result['warnings'][] = 'Temp directory: ' . $tempDir;

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Adapta automáticamente un tema de PS 1.7.6 a PS 9
     * Realiza conversiones básicas de archivos de configuración
     * 
     * @param string $themePath Ruta al directorio del tema
     * @return array Resultado con cambios realizados
     */
    public function adaptThemeToPS9($themePath)
    {
        $result = array(
            'success' => false,
            'changes' => array(),
            'warnings' => array(),
            'errors' => array()
        );

        try {
            if (!is_dir($themePath)) {
                $result['errors'][] = 'Theme directory not found';
                return $result;
            }

            // 1. Adaptar theme.yml
            $themeYmlPath = $themePath . '/config/theme.yml';
            if (file_exists($themeYmlPath)) {
                $this->adaptThemeYml($themeYmlPath, $result);
            } else {
                $result['warnings'][] = 'theme.yml not found';
            }

            // 2. Adaptar composer.json
            $composerPath = $themePath . '/composer.json';
            if (file_exists($composerPath)) {
                $this->adaptComposerJson($composerPath, $result);
            } else {
                $result['warnings'][] = 'composer.json not found';
            }

            // 3. Adaptar templates Smarty
            $this->adaptSmartyTemplates($themePath . '/templates', $result);

            // 4. Crear archivo de migración
            $migrationNotes = $this->createMigrationNotes($result['changes']);
            file_put_contents($themePath . '/MIGRATION_NOTES.txt', $migrationNotes);
            
            $result['success'] = true;
            $result['changes'][] = 'Created MIGRATION_NOTES.txt';

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Adapta theme.yml a formato PS 9
     */
    private function adaptThemeYml($filePath, &$result)
    {
        $content = file_get_contents($filePath);
        $original = $content;

        // Actualizar versión mínima de PrestaShop
        $content = preg_replace(
            '/compatibility:\s*min:\s*["\']?[\d.]+["\']?/',
            'compatibility: min: "9.0.0"',
            $content
        );

        // Actualizar versión máxima
        $content = preg_replace(
            '/max:\s*["\']?[\d.]+["\']?/',
            'max: "9.9.9"',
            $content
        );

        if ($content !== $original) {
            file_put_contents($filePath . '.backup', $original);
            file_put_contents($filePath, $content);
            $result['changes'][] = 'Updated theme.yml compatibility to PS 9';
        }
    }

    /**
     * Adapta composer.json a dependencias PS 9
     */
    private function adaptComposerJson($filePath, &$result)
    {
        $json = json_decode(file_get_contents($filePath), true);
        if (!$json) {
            $result['warnings'][] = 'Could not parse composer.json';
            return;
        }

        $original = $json;

        // Actualizar dependencia de PrestaShop
        if (isset($json['require'])) {
            foreach ($json['require'] as $package => $version) {
                if (strpos($package, 'prestashop') !== false) {
                    $json['require'][$package] = '^9.0';
                }
            }
        }

        // Actualizar versión de PHP mínima
        if (isset($json['require']['php'])) {
            $json['require']['php'] = '>=8.1';
        }

        if ($json !== $original) {
            file_put_contents($filePath . '.backup', file_get_contents($filePath));
            file_put_contents($filePath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $result['changes'][] = 'Updated composer.json dependencies';
        }
    }

    /**
     * Adapta templates Smarty a PS 9
     */
    private function adaptSmartyTemplates($templatesPath, &$result)
    {
        if (!is_dir($templatesPath)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templatesPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $changedFiles = 0;

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'tpl') {
                $content = file_get_contents($file->getRealPath());
                $original = $content;

                // Actualizar referencias de hooks obsoletos
                $content = str_replace('{hook h=\'displayNav\'}', '{hook h=\'displayNav1\'}', $content);
                $content = str_replace('{hook h=\'displayTop\'}', '{hook h=\'displayNavFullWidth\'}', $content);

                // Actualizar helpers obsoletos
                $content = str_replace('$link->getPageLink', '$urls.pages', $content);

                if ($content !== $original) {
                    file_put_contents($file->getRealPath() . '.backup', $original);
                    file_put_contents($file->getRealPath(), $content);
                    $changedFiles++;
                }
            }
        }

        if ($changedFiles > 0) {
            $result['changes'][] = "Adapted $changedFiles Smarty templates";
        }
    }

    /**
     * Crea notas de migración
     */
    private function createMigrationNotes($changes)
    {
        $notes = "THEME MIGRATION TO PRESTASHOP 9\n";
        $notes .= "================================\n\n";
        $notes .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
        $notes .= "AUTOMATIC CHANGES APPLIED:\n";
        $notes .= "--------------------------\n";
        
        foreach ($changes as $change) {
            $notes .= "- " . $change . "\n";
        }

        $notes .= "\n\nMANUAL STEPS REQUIRED:\n";
        $notes .= "----------------------\n";
        $notes .= "1. Review all .backup files and verify changes\n";
        $notes .= "2. Test theme in PrestaShop 9 development environment\n";
        $notes .= "3. Update module dependencies if needed\n";
        $notes .= "4. Regenerate assets (npm install && npm run build)\n";
        $notes .= "5. Test all pages and functionalities\n";
        $notes .= "6. Review console for JavaScript errors\n";
        $notes .= "7. Check responsive design\n\n";

        return $notes;
    }
}

