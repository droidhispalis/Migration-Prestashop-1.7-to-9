<?php
/**
 * Validación y reparación específica para PrestaShop 9
 * Corrige incompatibilidades de datos entre PS 1.7 y PS 9
 */
class PS9ValidationService
{
    private $db;
    private $prefix;
    private $errors = array();
    private $warnings = array();
    private $fixes = array();
    
    public function __construct()
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;
    }
    
    /**
     * Validar y reparar datos después de importación
     */
    public function validateAndRepairImport()
    {
        $this->errors = array();
        $this->warnings = array();
        $this->fixes = array();
        
        // 1. Verificar shop configuration
        $this->validateShopConfiguration();
        
        // 2. Validar id_shop en todas las tablas *_shop
        $this->validateIdShopFields();
        
        // 3. Verificar integridad referencial
        $this->validateReferentialIntegrity();
        
        return array(
            'ok' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'fixes' => $this->fixes
        );
    }
    
    /**
     * Validar configuración de shop (CRÍTICO)
     */
    private function validateShopConfiguration()
    {
        // Verificar que existe shop con id=1
        $shop = $this->db->getRow("SELECT * FROM `{$this->prefix}shop` WHERE id_shop = 1");
        
        if (!$shop) {
            $this->errors[] = 'CRITICAL: Shop ID 1 does not exist';
            
            // Intentar crear shop 1
            $result = $this->db->insert('shop', array(
                'id_shop' => 1,
                'id_shop_group' => 1,
                'name' => 'Default',
                'active' => 1,
                'deleted' => 0
            ));
            
            if ($result) {
                $this->fixes[] = 'Created shop ID 1';
            } else {
                $this->errors[] = 'FAILED to create shop ID 1: ' . $this->db->getMsgError();
            }
        }
        
        // Verificar shop_group
        $shopGroup = $this->db->getRow("SELECT * FROM `{$this->prefix}shop_group` WHERE id_shop_group = 1");
        
        if (!$shopGroup) {
            $result = $this->db->insert('shop_group', array(
                'id_shop_group' => 1,
                'name' => 'Default',
                'active' => 1,
                'deleted' => 0,
                'share_customer' => 0,
                'share_order' => 0,
                'share_stock' => 0
            ));
            
            if ($result) {
                $this->fixes[] = 'Created shop_group ID 1';
            }
        }
        
        // Verificar shop_url
        $shopUrl = $this->db->getRow("SELECT * FROM `{$this->prefix}shop_url` WHERE id_shop = 1");
        
        if (!$shopUrl) {
            $domain = Tools::getHttpHost();
            
            $result = $this->db->insert('shop_url', array(
                'id_shop' => 1,
                'domain' => pSQL($domain),
                'domain_ssl' => pSQL($domain),
                'physical_uri' => '/',
                'virtual_uri' => '',
                'main' => 1,
                'active' => 1
            ));
            
            if ($result) {
                $this->fixes[] = 'Created shop_url for shop ID 1';
            }
        }
    }
    
    /**
     * Validar campos id_shop en todas las tablas (CRÍTICO PARA PS9)
     */
    private function validateIdShopFields()
    {
        $tablesToCheck = array(
            'product_shop',
            'stock_available',
            'category_shop',
            'image_shop',
            'manufacturer_shop',
            'supplier_shop',
            'feature_shop',
            'attribute_shop',
            'attribute_group_shop',
            'product_attribute_shop'
        );
        
        foreach ($tablesToCheck as $table) {
            $fullTable = $this->prefix . $table;
            
            // Verificar si la tabla existe
            $exists = $this->db->executeS("SHOW TABLES LIKE '$fullTable'");
            if (empty($exists)) {
                continue;
            }
            
            // Buscar registros con id_shop = 0 o NULL
            $invalid = $this->db->getValue("
                SELECT COUNT(*) 
                FROM `$fullTable` 
                WHERE id_shop = 0 OR id_shop IS NULL
            ");
            
            if ($invalid > 0) {
                $this->warnings[] = "Table $table has $invalid records with invalid id_shop";
                
                // REPARAR: Actualizar id_shop = 1
                $result = $this->db->execute("
                    UPDATE `$fullTable` 
                    SET id_shop = 1 
                    WHERE id_shop = 0 OR id_shop IS NULL
                ");
                
                if ($result) {
                    $this->fixes[] = "Fixed $invalid records in $table (set id_shop = 1)";
                } else {
                    $this->errors[] = "FAILED to fix id_shop in $table: " . $this->db->getMsgError();
                }
            }
        }
    }
    
    /**
     * Validar integridad referencial
     */
    private function validateReferentialIntegrity()
    {
        // Verificar que todos los id_shop en tablas *_shop existen en la tabla shop
        $validShops = $this->db->executeS("SELECT id_shop FROM `{$this->prefix}shop`");
        $validShopIds = array();
        foreach ($validShops as $shop) {
            $validShopIds[] = (int)$shop['id_shop'];
        }
        
        if (empty($validShopIds)) {
            $this->errors[] = 'CRITICAL: No shops found in shop table';
            return;
        }
        
        $validShopIdsStr = implode(',', $validShopIds);
        
        // Verificar product_shop
        $orphaned = $this->db->getValue("
            SELECT COUNT(*) 
            FROM `{$this->prefix}product_shop` 
            WHERE id_shop NOT IN ($validShopIdsStr)
        ");
        
        if ($orphaned > 0) {
            $this->warnings[] = "Found $orphaned orphaned products (id_shop not in shop table)";
            
            // Reparar: asignar al shop por defecto
            $this->db->execute("
                UPDATE `{$this->prefix}product_shop` 
                SET id_shop = 1 
                WHERE id_shop NOT IN ($validShopIdsStr)
            ");
            
            $this->fixes[] = "Reassigned $orphaned orphaned products to shop ID 1";
        }
    }
    
    /**
     * Obtener reporte completo
     */
    public function getReport()
    {
        return array(
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'fixes' => $this->fixes,
            'summary' => array(
                'total_errors' => count($this->errors),
                'total_warnings' => count($this->warnings),
                'total_fixes' => count($this->fixes)
            )
        );
    }
}
