<?php
/**
 * Exportador CSV según formato OFICIAL de PrestaShop
 * Compatible con el importador nativo de PS9
 * 
 * Formato según: https://docs.prestashop-project.org/v.8-documentation/user-guide/configuring-shop/advanced-parameters/import
 */
class CSVExportService
{
    private $db;
    private $prefix;
    private $shopId;
    private $langId;
    
    public function __construct($shopId = 1, $langId = 1)
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;
        $this->shopId = (int)$shopId;
        $this->langId = (int)$langId;
    }
    
    /**
     * Validar y limpiar valor decimal
     */
    private function cleanDecimal($value, $decimals = 6)
    {
        if (empty($value) || !is_numeric($value)) {
            return '0.00';
        }
        
        // Convertir a float y formatear
        $clean = (float)$value;
        
        // Validar rango razonable
        if ($clean < 0) $clean = 0;
        if ($clean > 999999) $clean = 999999;
        
        return number_format($clean, $decimals, '.', '');
    }
    
    /**
     * Validar y limpiar valor entero
     */
    private function cleanInt($value, $min = 0, $max = 999999)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return $min;
        }
        
        $clean = (int)$value;
        
        if ($clean < $min) $clean = $min;
        if ($clean > $max) $clean = $max;
        
        return $clean;
    }
    
    /**
     * Validar y limpiar texto
     */
    private function cleanString($value, $maxLength = 255)
    {
        if (empty($value)) return '';
        
        // Eliminar caracteres de control y NULL bytes
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        
        // Truncar si es necesario
        if (strlen($clean) > $maxLength) {
            $clean = substr($clean, 0, $maxLength);
        }
        
        return trim($clean);
    }
    
    /**
     * Exportar PRODUCTOS a CSV formato oficial PrestaShop
     */
    public function exportProducts($outputFile)
    {
        // Headers oficiales PrestaShop 9 (SIN ID - auto-generado)
        $headers = array(
            'Active (0/1)',
            'Name *',
            'Categories (x,y,z...)',
            'Price tax excluded',
            'Tax rule ID',
            'Wholesale price',
            'On sale (0/1)',
            'Discount amount',
            'Discount percent',
            'Discount from (yyyy-mm-dd)',
            'Discount to (yyyy-mm-dd)',
            'Reference #',
            'Supplier reference #',
            'Supplier',
            'Brand',
            'EAN13',
            'UPC',
            'MPN',
            'Ecotax',
            'Width',
            'Height',
            'Depth',
            'Weight',
            'Delivery time of in-stock products:',
            'Delivery time of out-of-stock products with allowed orders:',
            'Quantity',
            'Minimal quantity',
            'Low stock level',
            'Send me an email when the quantity is under this level',
            'Visibility',
            'Additional shipping cost',
            'Unit for the price per unit',
            'Price per unit',
            'Summary',
            'Description',
            'Tags (x,y,z...)',
            'Meta title',
            'Meta keywords',
            'Meta description',
            'URL rewritten',
            'Text when in stock',
            'Text when backorder allowed',
            'Available for order (0 = No, 1 = Yes)',
            'Product availability date',
            'Product creation date',
            'Show price (0 = No, 1 = Yes)',
            'Image URLs (x,y,z...)',
            'Image alt texts (x,y,z...)',
            'Delete existing images (0 = No, 1 = Yes)',
            'Feature (Name:Value:Position:Customized)',
            'Available online only (0 = No, 1 = Yes)',
            'Condition',
            'Customizable (0 = No, 1 = Yes)',
            'Uploadable files (0 = No, 1 = Yes)',
            'Text fields (0 = No, 1 = Yes)',
            'Out of stock',
            'ID / Name of shop',
            'Advanced Stock Management',
            'Depends on stock',
            'Warehouse'
        );
        
        // Abrir archivo CSV
        $fp = fopen($outputFile, 'w');
        
        // BOM para UTF-8 (importante para Excel)
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escribir headers
        fputcsv($fp, $headers, ';');
        
        // Obtener productos
        $sql = "SELECT p.*, pl.*, ps.*, sa.quantity
                FROM `{$this->prefix}product` p
                LEFT JOIN `{$this->prefix}product_lang` pl ON (p.id_product = pl.id_product AND pl.id_lang = {$this->langId})
                LEFT JOIN `{$this->prefix}product_shop` ps ON (p.id_product = ps.id_product AND ps.id_shop = {$this->shopId})
                LEFT JOIN `{$this->prefix}stock_available` sa ON (p.id_product = sa.id_product AND sa.id_shop = {$this->shopId})
                WHERE p.id_product IS NOT NULL
                ORDER BY p.id_product ASC";
        
        $products = $this->db->executeS($sql);
        $count = 0;
        
        foreach ($products as $product) {
            $row = array();
            
            // Active (0/1) - SIN ID, PS9 lo auto-genera
            $row[] = $this->cleanInt($product['active'] ?? 1, 0, 1);
            
            // Name * (REQUERIDO)
            $name = $this->cleanString($product['name'] ?? '', 128);
            if (empty($name)) {
                $name = 'Product ' . $product['id_product']; // Nombre por defecto
            }
            $row[] = $name;
            
            // Categories (x,y,z...)
            $categories = $this->getProductCategories($product['id_product']);
            if (empty($categories)) {
                $categories = array(2); // Categoría root por defecto
            }
            $row[] = implode(',', $categories);
            
            // Price tax excluded - VALIDAR que sea decimal válido
            $row[] = $this->cleanDecimal($product['price'] ?? 0);
            
            // Tax rule ID
            $row[] = $this->cleanInt($product['id_tax_rules_group'] ?? 0, 0);
            
            // Wholesale price
            $row[] = $this->cleanDecimal($product['wholesale_price'] ?? 0);
            
            // On sale (0/1)
            $row[] = $this->cleanInt($product['on_sale'] ?? 0, 0, 1);
            
            // Discount amount
            $row[] = '';
            
            // Discount percent
            $row[] = '';
            
            // Discount from (yyyy-mm-dd)
            $row[] = '';
            
            // Discount to (yyyy-mm-dd)
            $row[] = '';
            
            // Reference #
            $row[] = $this->cleanString($product['reference'] ?? '', 64);
            
            // Supplier reference #
            $row[] = $this->cleanString($product['supplier_reference'] ?? '', 64);
            
            // Supplier
            $row[] = $this->getSupplierName($product['id_supplier'] ?? 0);
            
            // Brand
            $row[] = $this->getManufacturerName($product['id_manufacturer'] ?? 0);
            
            // EAN13
            $row[] = $this->cleanString($product['ean13'] ?? '', 13);
            
            // UPC
            $row[] = $this->cleanString($product['upc'] ?? '', 12);
            
            // MPN
            $row[] = $this->cleanString($product['mpn'] ?? '', 40);
            
            // Ecotax
            $row[] = $this->cleanDecimal($product['ecotax'] ?? 0);
            
            // Width
            $row[] = $this->cleanDecimal($product['width'] ?? 0);
            
            // Height
            $row[] = $this->cleanDecimal($product['height'] ?? 0);
            
            // Depth
            $row[] = $this->cleanDecimal($product['depth'] ?? 0);
            
            // Weight
            $row[] = $this->cleanDecimal($product['weight'] ?? 0);
            
            // Delivery time of in-stock products
            $row[] = $this->cleanString($product['delivery_in_stock'] ?? '', 255);
            
            // Delivery time of out-of-stock products with allowed orders
            $row[] = $this->cleanString($product['delivery_out_stock'] ?? '', 255);
            
            // Quantity
            $row[] = $this->cleanInt($product['quantity'] ?? 0, 0);
            
            // Minimal quantity
            $row[] = $this->cleanInt($product['minimal_quantity'] ?? 1, 1);
            
            // Low stock level
            $lowStock = $product['low_stock_threshold'] ?? null;
            $row[] = ($lowStock !== null && $lowStock !== '') ? $this->cleanInt($lowStock, 0) : '';
            
            // Send me an email when the quantity is under this level
            $row[] = $this->cleanInt($product['low_stock_alert'] ?? 0, 0, 1);
            
            // Visibility
            $visibility = $product['visibility'] ?? 'both';
            if (!in_array($visibility, array('both', 'catalog', 'search', 'none'))) {
                $visibility = 'both';
            }
            $row[] = $visibility;
            
            // Additional shipping cost - IMPORTANTE: validar
            $row[] = $this->cleanDecimal($product['additional_shipping_cost'] ?? 0);
            
            // Unit for the price per unit
            $row[] = $this->cleanString($product['unity'] ?? '', 255);
            
            // Price per unit - SIEMPRE VACÍO para evitar errores de validación en PS9
            // PS9 calcula esto automáticamente basado en precio y unidad
            $row[] = '';
            
            // Summary
            $row[] = $this->cleanText($product['description_short'] ?? '');
            
            // Description
            $row[] = $this->cleanText($product['description'] ?? '');
            
            // Tags (x,y,z...)
            $row[] = $this->getProductTags($product['id_product']);
            
            // Meta title
            $row[] = $this->cleanString($product['meta_title'] ?? '', 128);
            
            // Meta keywords (vacío para PS9 - obsoleto)
            $row[] = '';
            
            // Meta description
            $row[] = $this->cleanString($product['meta_description'] ?? '', 255);
            
            // URL rewritten
            $row[] = $this->cleanString($product['link_rewrite'] ?? '', 128);
            
            // Text when in stock
            $row[] = $this->cleanString($product['available_now'] ?? '', 255);
            
            // Text when backorder allowed
            $row[] = $this->cleanString($product['available_later'] ?? '', 255);
            
            // Available for order (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['available_for_order'] ?? 1, 0, 1);
            
            // Product availability date
            $availDate = $product['available_date'] ?? '';
            $row[] = ($availDate != '0000-00-00' && !empty($availDate)) ? $availDate : '';
            
            // Product creation date
            $dateAdd = $product['date_add'] ?? date('Y-m-d H:i:s');
            if ($dateAdd == '0000-00-00 00:00:00') $dateAdd = date('Y-m-d H:i:s');
            $row[] = $dateAdd;
            
            // Show price (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['show_price'] ?? 1, 0, 1);
            
            // Image URLs (x,y,z...)
            $row[] = $this->getProductImages($product['id_product']);
            
            // Image alt texts (x,y,z...)
            $row[] = '';
            
            // Delete existing images (0 = No, 1 = Yes)
            $row[] = 0;
            
            // Feature (Name:Value:Position:Customized)
            $row[] = $this->getProductFeatures($product['id_product']);
            
            // Available online only (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['online_only'] ?? 0, 0, 1);
            
            // Condition
            $condition = $product['condition'] ?? 'new';
            if (!in_array($condition, array('new', 'used', 'refurbished'))) {
                $condition = 'new';
            }
            $row[] = $condition;
            
            // Customizable (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['customizable'] ?? 0, 0, 1);
            
            // Uploadable files (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['uploadable_files'] ?? 0, 0, 255);
            
            // Text fields (0 = No, 1 = Yes)
            $row[] = $this->cleanInt($product['text_fields'] ?? 0, 0, 255);
            
            // Out of stock
            $outOfStock = $product['out_of_stock'] ?? 2;
            if (!in_array($outOfStock, array(0, 1, 2))) {
                $outOfStock = 2;
            }
            $row[] = $outOfStock;
            
            // ID / Name of shop
            $row[] = $this->shopId;
            
            // Advanced Stock Management
            $row[] = $this->cleanInt($product['advanced_stock_management'] ?? 0, 0, 1);
            
            // Depends on stock
            $row[] = $this->cleanInt($product['depends_on_stock'] ?? 0, 0, 1);
            
            // Warehouse
            $row[] = '';
            
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        
        return array(
            'ok' => true,
            'file' => $outputFile,
            'count' => $count,
            'message' => "Exported $count products to CSV (official PrestaShop format)"
        );
    }
    
    /**
     * Exportar CATEGORÍAS a CSV formato oficial PrestaShop
     * EXCLUYE categorías especiales (1=Home, 2=Root) para evitar conflictos
     * USA NOMBRES de categoría padre en lugar de IDs
     */
    public function exportCategories($outputFile)
    {
        // NO incluir ID - dejar que PS9 asigne IDs automáticamente
        $headers = array(
            'Active (0/1)',
            'Name *',
            'Parent category',
            'Root category (0/1)',
            'Description',
            'Meta title',
            'Meta description',
            'Meta keywords',
            'Friendly URL',
            'Image URL'
        );
        
        $fp = fopen($outputFile, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($fp, $headers, ';');
        
        // EXCLUIR categorías 1 (Home) y 2 (Root) - son especiales de PS
        $sql = "SELECT c.*, cl.*
                FROM `{$this->prefix}category` c
                LEFT JOIN `{$this->prefix}category_lang` cl ON (c.id_category = cl.id_category AND cl.id_lang = {$this->langId})
                WHERE c.id_category > 2
                ORDER BY c.level_depth ASC, c.id_category ASC";
        
        $categories = $this->db->executeS($sql);
        $count = 0;
        
        // Crear mapa de ID => Nombre para buscar nombres de padres
        $categoryMap = array(
            1 => 'Home',
            2 => 'Home' // En PS9 importar como Home
        );
        
        foreach ($categories as $cat) {
            if (!empty($cat['name'])) {
                $categoryMap[$cat['id_category']] = $cat['name'];
            }
        }
        
        foreach ($categories as $cat) {
            // Validar nombre - REQUERIDO
            $name = $this->cleanString($cat['name'] ?? '', 128);
            if (empty($name)) {
                $name = 'Category ' . ($cat['id_category'] ?? 'Unknown');
            }
            
            $parentId = $this->cleanInt($cat['id_parent'] ?? 2, 1);
            
            // Obtener NOMBRE de categoría padre en lugar de ID
            $parentName = 'Home'; // Por defecto
            if (isset($categoryMap[$parentId])) {
                $parentName = $categoryMap[$parentId];
            }
            
            $row = array(
                $this->cleanInt($cat['active'] ?? 1, 0, 1),
                $name,
                $parentName, // USAR NOMBRE en lugar de ID
                0, // Nunca marcar como root - PS9 ya tiene su root
                $this->cleanText($cat['description'] ?? ''),
                $this->cleanString($cat['meta_title'] ?? '', 128),
                $this->cleanString($cat['meta_description'] ?? '', 255),
                '', // meta_keywords obsoleto en PS9
                $this->cleanString($cat['link_rewrite'] ?? '', 128),
                '' // Image URL
            );
            
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        
        return array(
            'ok' => true,
            'file' => $outputFile,
            'count' => $count,
            'message' => "Exported $count categories to CSV (excluding Home/Root)"
        );
    }
    
    /**
     * Obtener categorías de un producto
     */
    private function getProductCategories($idProduct)
    {
        $sql = "SELECT id_category FROM `{$this->prefix}category_product` 
                WHERE id_product = " . (int)$idProduct;
        $categories = $this->db->executeS($sql);
        
        $ids = array();
        foreach ($categories as $cat) {
            $ids[] = $cat['id_category'];
        }
        
        return $ids;
    }
    
    /**
     * Obtener imágenes de producto (URLs completas)
     */
    private function getProductImages($idProduct)
    {
        $sql = "SELECT id_image FROM `{$this->prefix}image` 
                WHERE id_product = " . (int)$idProduct . "
                ORDER BY position ASC";
        $images = $this->db->executeS($sql);
        
        $urls = array();
        foreach ($images as $img) {
            // Construir URL de imagen según estructura de PrestaShop
            $id = $img['id_image'];
            $path = implode('/', str_split($id)) . '/' . $id;
            
            // URL completa (cambiar por tu dominio real)
            $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $urls[] = 'http://' . $domain . '/img/p/' . $path . '.jpg';
        }
        
        return implode(',', $urls);
    }
    
    /**
     * Obtener tags de producto
     */
    private function getProductTags($idProduct)
    {
        $sql = "SELECT t.name 
                FROM `{$this->prefix}tag` t
                INNER JOIN `{$this->prefix}product_tag` pt ON t.id_tag = pt.id_tag
                WHERE pt.id_product = " . (int)$idProduct . "
                AND t.id_lang = {$this->langId}";
        $tags = $this->db->executeS($sql);
        
        $names = array();
        foreach ($tags as $tag) {
            $names[] = $tag['name'];
        }
        
        return implode(',', $names);
    }
    
    /**
     * Obtener características de producto (formato PrestaShop)
     */
    private function getProductFeatures($idProduct)
    {
        $sql = "SELECT fl.name as feature_name, fvl.value as feature_value
                FROM `{$this->prefix}feature_product` fp
                INNER JOIN `{$this->prefix}feature_lang` fl ON fp.id_feature = fl.id_feature AND fl.id_lang = {$this->langId}
                INNER JOIN `{$this->prefix}feature_value_lang` fvl ON fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = {$this->langId}
                WHERE fp.id_product = " . (int)$idProduct;
        $features = $this->db->executeS($sql);
        
        $formatted = array();
        $position = 1;
        foreach ($features as $feat) {
            // Formato: featurename:value:position:customizedvalue
            $formatted[] = $feat['feature_name'] . ':' . $feat['feature_value'] . ':' . $position . ':0';
            $position++;
        }
        
        return implode(',', $formatted);
    }
    
    /**
     * Obtener nombre de proveedor
     */
    private function getSupplierName($idSupplier)
    {
        if (!$idSupplier) return '';
        
        $sql = "SELECT name FROM `{$this->prefix}supplier` WHERE id_supplier = " . (int)$idSupplier;
        $result = $this->db->getValue($sql);
        
        return $result ?: '';
    }
    
    /**
     * Obtener nombre de fabricante
     */
    private function getManufacturerName($idManufacturer)
    {
        if (!$idManufacturer) return '';
        
        $sql = "SELECT name FROM `{$this->prefix}manufacturer` WHERE id_manufacturer = " . (int)$idManufacturer;
        $result = $this->db->getValue($sql);
        
        return $result ?: '';
    }
    
    /**
     * Limpiar texto HTML para CSV
     */
    private function cleanText($html)
    {
        // Eliminar tags HTML
        $text = strip_tags($html);
        
        // Eliminar saltos de línea múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Escapar comillas
        $text = str_replace('"', '""', $text);
        
        return trim($text);
    }
    
    /**
     * Exportar MARCAS (Manufacturers/Brands) a CSV
     */
    public function exportBrands($outputFile)
    {
        $headers = array('ID', 'Active (0/1)', 'Name *', 'Description', 'Short description', 'Meta title', 'Meta keywords', 'Meta description', 'Image URL');
        
        $fp = fopen($outputFile, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers, ';');
        
        $sql = "SELECT m.*, ml.description, ml.short_description, ml.meta_title, ml.meta_description, ml.meta_keywords
                FROM `{$this->prefix}manufacturer` m
                LEFT JOIN `{$this->prefix}manufacturer_lang` ml ON (m.id_manufacturer = ml.id_manufacturer AND ml.id_lang = {$this->langId})
                ORDER BY m.id_manufacturer ASC";
        
        $brands = $this->db->executeS($sql);
        $count = 0;
        
        foreach ($brands as $brand) {
            $row = array(
                $this->cleanInt($brand['id_manufacturer'], 1),
                $this->cleanInt($brand['active'] ?? 1, 0, 1),
                $this->cleanString($brand['name'], 64),
                $this->cleanText($brand['description'] ?? ''),
                $this->cleanText($brand['short_description'] ?? ''),
                $this->cleanString($brand['meta_title'] ?? '', 128),
                '', // meta_keywords obsoleto
                $this->cleanString($brand['meta_description'] ?? '', 255),
                '' // Image URL
            );
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        return array('ok' => true, 'file' => $outputFile, 'count' => $count, 'message' => "Exported $count brands");
    }
    
    /**
     * Exportar PROVEEDORES (Suppliers) a CSV
     */
    public function exportSuppliers($outputFile)
    {
        $headers = array('ID', 'Active (0/1)', 'Name *', 'Description', 'Short description', 'Meta title', 'Meta keywords', 'Meta description', 'Image URL');
        
        $fp = fopen($outputFile, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers, ';');
        
        $sql = "SELECT s.*, sl.description, sl.meta_title, sl.meta_description, sl.meta_keywords
                FROM `{$this->prefix}supplier` s
                LEFT JOIN `{$this->prefix}supplier_lang` sl ON (s.id_supplier = sl.id_supplier AND sl.id_lang = {$this->langId})
                ORDER BY s.id_supplier ASC";
        
        $suppliers = $this->db->executeS($sql);
        $count = 0;
        
        foreach ($suppliers as $supplier) {
            $row = array(
                $this->cleanInt($supplier['id_supplier'], 1),
                $this->cleanInt($supplier['active'] ?? 1, 0, 1),
                $this->cleanString($supplier['name'], 64),
                $this->cleanText($supplier['description'] ?? ''),
                '',
                $this->cleanString($supplier['meta_title'] ?? '', 128),
                '',
                $this->cleanString($supplier['meta_description'] ?? '', 255),
                ''
            );
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        return array('ok' => true, 'file' => $outputFile, 'count' => $count, 'message' => "Exported $count suppliers");
    }
    
    /**
     * Exportar CLIENTES (Customers) a CSV
     */
    public function exportCustomers($outputFile)
    {
        $headers = array('ID', 'Active (0/1)', 'Titles ID (Mr = 1, Ms = 2)', 'Email *', 'Password *', 'Birthday (yyyy-mm-dd)', 'Last Name *', 'First Name *', 'Newsletter (0/1)', 'Opt-in (0/1)', 'Registration Date (yyyy-mm-dd)', 'Groups (x,y,z...)');
        
        $fp = fopen($outputFile, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers, ';');
        
        $sql = "SELECT * FROM `{$this->prefix}customer` WHERE deleted = 0 ORDER BY id_customer ASC";
        $customers = $this->db->executeS($sql);
        $count = 0;
        
        foreach ($customers as $customer) {
            $birthday = $customer['birthday'] ?? '';
            if ($birthday == '0000-00-00') $birthday = '';
            
            $row = array(
                $this->cleanInt($customer['id_customer'], 1),
                $this->cleanInt($customer['active'] ?? 1, 0, 1),
                $this->cleanInt($customer['id_gender'] ?? 0, 0, 2),
                $this->cleanString($customer['email'], 128),
                $customer['passwd'], // Ya está hasheado
                $birthday,
                $this->cleanString($customer['lastname'], 32),
                $this->cleanString($customer['firstname'], 32),
                $this->cleanInt($customer['newsletter'] ?? 0, 0, 1),
                $this->cleanInt($customer['optin'] ?? 0, 0, 1),
                $customer['date_add'] ?? date('Y-m-d'),
                $this->cleanInt($customer['id_default_group'] ?? 3, 1) // 3 = Customers
            );
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        return array('ok' => true, 'file' => $outputFile, 'count' => $count, 'message' => "Exported $count customers");
    }
    
    /**
     * Exportar DIRECCIONES (Addresses) a CSV
     */
    public function exportAddresses($outputFile)
    {
        $headers = array('ID', 'Alias *', 'Active (0/1)', 'Customer e-mail *', 'Customer ID', 'Brand', 'Company', 'Last Name *', 'First Name *', 'Address 1 *', 'Address 2', 'Zip/Postal Code *', 'City *', 'Country *', 'State', 'Other', 'Phone', 'Mobile Phone', 'VAT number', 'DNI');
        
        $fp = fopen($outputFile, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fp, $headers, ';');
        
        $sql = "SELECT a.*, c.email, co.iso_code as country_code
                FROM `{$this->prefix}address` a
                LEFT JOIN `{$this->prefix}customer` c ON a.id_customer = c.id_customer
                LEFT JOIN `{$this->prefix}country` co ON a.id_country = co.id_country
                WHERE a.deleted = 0
                ORDER BY a.id_address ASC";
        
        $addresses = $this->db->executeS($sql);
        $count = 0;
        
        foreach ($addresses as $addr) {
            $row = array(
                $this->cleanInt($addr['id_address'], 1),
                $this->cleanString($addr['alias'] ?? 'My address', 32),
                1,
                $this->cleanString($addr['email'] ?? '', 128),
                $this->cleanInt($addr['id_customer'], 1),
                '',
                $this->cleanString($addr['company'] ?? '', 64),
                $this->cleanString($addr['lastname'], 32),
                $this->cleanString($addr['firstname'], 32),
                $this->cleanString($addr['address1'], 128),
                $this->cleanString($addr['address2'] ?? '', 128),
                $this->cleanString($addr['postcode'] ?? '', 12),
                $this->cleanString($addr['city'], 64),
                $this->cleanString($addr['country_code'] ?? 'ES', 2),
                '',
                $this->cleanString($addr['other'] ?? '', 300),
                $this->cleanString($addr['phone'] ?? '', 16),
                $this->cleanString($addr['phone_mobile'] ?? '', 16),
                $this->cleanString($addr['vat_number'] ?? '', 32),
                $this->cleanString($addr['dni'] ?? '', 16)
            );
            fputcsv($fp, $row, ';');
            $count++;
        }
        
        fclose($fp);
        return array('ok' => true, 'file' => $outputFile, 'count' => $count, 'message' => "Exported $count addresses");
    }
}
