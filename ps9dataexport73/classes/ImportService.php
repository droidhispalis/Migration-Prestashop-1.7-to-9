<?php
/**
 * Import Service con Rollback automático
 */
class ImportService
{
    private $db;
    private $backupFile = null;
    private $importedTables = array();
    
    public function __construct()
    {
        $this->db = Db::getInstance();
    }
    
    /**
     * Importar SQL con backup automático y rollback
     */
    public function importFromFile($sqlFile, $options = array())
    {
        if (!file_exists($sqlFile)) {
            throw new Exception('SQL file not found: ' . $sqlFile);
        }
        
        $prefix = _DB_PREFIX_;
        $results = array('ok' => true, 'tables' => array(), 'backup' => null);
        
        try {
            // 1. CREAR BACKUP ANTES DE IMPORTAR
            $results['backup'] = $this->createBackupBeforeImport($prefix);
            
            // 2. LEER SQL Y EJECUTAR
            $sql = file_get_contents($sqlFile);
            
            // Reemplazar {PREFIX} con el prefijo actual
            $sql = str_replace('{PREFIX}', $prefix, $sql);
            
            // 3. PARSEAR Y EJECUTAR STATEMENT POR STATEMENT
            $statements = $this->parseSqlStatements($sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }
                
                // Detectar tabla afectada
                if (preg_match('/(?:INTO|FROM|UPDATE|TRUNCATE TABLE)\s+`?([a-z0-9_]+)`?/i', $statement, $m)) {
                    $table = $m[1];
                    if (!in_array($table, $this->importedTables)) {
                        $this->importedTables[] = $table;
                    }
                }
                
                // Ejecutar
                if (!$this->db->execute($statement)) {
                    throw new Exception('SQL Error: ' . $this->db->getMsgError() . ' | Statement: ' . substr($statement, 0, 100));
                }
            }
            
            $results['tables'] = $this->importedTables;
            $results['count'] = count($statements);
            
            // 4. VALIDAR Y REPARAR PARA PS9
            require_once __DIR__ . '/PS9ValidationService.php';
            $ps9Validator = new PS9ValidationService();
            $validationResult = $ps9Validator->validateAndRepairImport();
            
            $results['ps9_validation'] = $validationResult;
            $results['message'] = 'Import completed successfully. Backup: ' . basename($results['backup']);
            
            if (!$validationResult['ok']) {
                $results['message'] .= ' | WARNING: Some PS9 validation errors found';
            } elseif (!empty($validationResult['fixes'])) {
                $results['message'] .= ' | PS9 validation: ' . count($validationResult['fixes']) . ' fixes applied';
            }
            
        } catch (Exception $e) {
            // ROLLBACK AUTOMÁTICO
            if ($this->backupFile && file_exists($this->backupFile)) {
                $this->rollback($this->backupFile);
                throw new Exception('Import failed. Rollback completed. Error: ' . $e->getMessage());
            } else {
                throw new Exception('Import failed without backup. Error: ' . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Crear backup de tablas antes de importar
     */
    private function createBackupBeforeImport($prefix)
    {
        $timestamp = date('Ymd_His');
        $backupDir = _PS_DOWNLOAD_DIR_ . 'ps9-export';
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        
        $this->backupFile = $backupDir . '/backup_before_import_' . $timestamp . '.sql';
        
        $tables = $this->getImportantTables($prefix);
        $sql = "-- BACKUP BEFORE IMPORT: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            $fullTable = $prefix . $table;
            
            // Estructura
            $create = $this->db->executeS("SHOW CREATE TABLE `{$fullTable}`");
            if (!empty($create)) {
                $sql .= "DROP TABLE IF EXISTS `{$fullTable}`;\n";
                $sql .= $create[0]['Create Table'] . ";\n\n";
            }
            
            // Datos (limitar a 10000 registros para rapidez)
            $rows = $this->db->executeS("SELECT * FROM `{$fullTable}` LIMIT 10000");
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $val) {
                        $values[] = is_null($val) ? 'NULL' : "'" . pSQL($val, true) . "'";
                    }
                    $sql .= "INSERT INTO `{$fullTable}` VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($this->backupFile, $sql);
        return $this->backupFile;
    }
    
    /**
     * Rollback: restaurar desde backup
     */
    private function rollback($backupFile)
    {
        if (!file_exists($backupFile)) {
            return false;
        }
        
        $sql = file_get_contents($backupFile);
        $statements = $this->parseSqlStatements($sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            $this->db->execute($statement);
        }
        
        return true;
    }
    
    /**
     * Parsear SQL en statements individuales
     */
    private function parseSqlStatements($sql)
    {
        // Dividir por ; pero respetando comillas y paréntesis
        $statements = array();
        $current = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i-1] !== '\\')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            }
            
            if ($char === ';' && !$inString) {
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if (!empty(trim($current))) {
            $statements[] = $current;
        }
        
        return $statements;
    }
    
    /**
     * Obtener tablas importantes para backup
     */
    private function getImportantTables($prefix)
    {
        return array(
            'product', 'product_lang', 'product_shop', 'product_attribute',
            'category', 'category_lang', 'category_shop', 'category_product',
            'image', 'image_lang', 'image_shop',
            'feature', 'feature_lang', 'feature_product', 'feature_value', 'feature_value_lang',
            'attribute', 'attribute_lang', 'attribute_group', 'attribute_group_lang',
            'manufacturer', 'manufacturer_lang', 'supplier', 'supplier_lang',
            'stock_available', 'specific_price'
        );
    }
    
    /**
     * Validar SQL antes de importar
     */
    public function validateSqlFile($sqlFile)
    {
        if (!file_exists($sqlFile)) {
            return array('ok' => false, 'error' => 'File not found');
        }
        
        $sql = file_get_contents($sqlFile);
        $statements = $this->parseSqlStatements($sql);
        
        $tables = array();
        $inserts = 0;
        $truncates = 0;
        
        foreach ($statements as $stmt) {
            if (preg_match('/TRUNCATE TABLE `?([a-z0-9_]+)`?/i', $stmt, $m)) {
                $tables[] = $m[1];
                $truncates++;
            }
            if (preg_match('/INSERT INTO `?([a-z0-9_]+)`?/i', $stmt, $m)) {
                $tables[] = $m[1];
                $inserts++;
            }
        }
        
        return array(
            'ok' => true,
            'tables' => array_unique($tables),
            'statements' => count($statements),
            'inserts' => $inserts,
            'truncates' => $truncates,
            'size' => filesize($sqlFile)
        );
    }
}
