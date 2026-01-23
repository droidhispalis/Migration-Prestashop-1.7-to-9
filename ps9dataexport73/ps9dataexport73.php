<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ps9DataExport73 extends Module
{
    public function __construct()
    {
        $this->name = 'ps9dataexport73';
        $this->tab = 'administration';
        $this->version = '2.2.0';
        $this->author = 'Custom';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PS 1.7 → PS 9 Data Export (PHP 7.3)');
        $this->description = $this->l('Complete migration with PS9 validation + auto-repair + shop config.');
        
        // INTERCEPTAR DESCARGAS ANTES DE CUALQUIER OUTPUT
        $this->processDownload();
    }
    
    /**
     * Procesar descarga de archivos ANTES de cualquier output HTML
     */
    private function processDownload()
    {
        if (Tools::isSubmit('downloadPs9Export') && Tools::getValue('file')) {
            $file = Tools::getValue('file');
            $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $filePath = $exportDir . '/' . basename($file);
            
            if (file_exists($filePath) && is_file($filePath)) {
                // LIMPIAR TODO OUTPUT PREVIO
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // HEADERS DE DESCARGA
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                
                // LEER Y ENVIAR ARCHIVO EN CHUNKS PARA ARCHIVOS GRANDES
                $handle = fopen($filePath, 'rb');
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
                exit;
            } else {
                die('File not found: ' . basename($file));
            }
        }
    }

    public function install() { return parent::install(); }
    public function uninstall() { return parent::uninstall(); }

    public function getContent()
    {
        // Ya no necesitamos código de descarga aquí - se procesa en __construct()
        
        if (Tools::getValue('ajax') === '1') {
            $action = Tools::getValue('action');
            if ($action === 'runTests') return $this->ajaxRunTests();
            if ($action === 'validate') return $this->ajaxValidate();
            if ($action === 'repair') return $this->ajaxRepair();
            if ($action === 'exportSql') return $this->ajaxExport();
            if ($action === 'exportCSV') return $this->ajaxExportCSV();
            if ($action === 'exportImages') return $this->ajaxExportImages();
            if ($action === 'listFiles') return $this->ajaxListFiles();
            if ($action === 'deleteFile') return $this->ajaxDeleteFile();
            if ($action === 'uploadFile') return $this->ajaxUploadFile();
            if ($action === 'validateImport') return $this->ajaxValidateImport();
            if ($action === 'import') return $this->ajaxImport();
            if ($action === 'emergencyRestore') return $this->ajaxEmergencyRestore();

            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'Unknown action')));
        }

        $adminModulesLink = $this->context->link->getAdminLink('AdminModules', true, array(), array(
            'configure' => $this->name
        ));

        $this->context->smarty->assign(array(
            'baseUrl' => $adminModulesLink,
            'defaultShopId' => (int)$this->context->shop->id,
            'defaultLangId' => (int)$this->context->language->id,
            'downloadDir' => _PS_DOWNLOAD_DIR_,
        ));

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }
    
    private function ajaxListFiles()
    {
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        $files = array();
        
        if (is_dir($exportDir)) {
            $items = glob($exportDir . '/*');
            foreach ($items as $item) {
                if (is_file($item)) {
                    $name = basename($item);
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $files[] = array(
                        'name' => $name,
                        'size' => filesize($item),
                        'date' => date('Y-m-d H:i:s', filemtime($item)),
                        'type' => $ext === 'sql' ? 'sql' : 'other'
                    );
                }
            }
        }
        
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(array('ok' => true, 'files' => $files)));
    }
    
    private function ajaxDeleteFile()
    {
        $fileName = Tools::getValue('file');
        
        if (empty($fileName)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'Nombre de archivo no especificado')));
        }
        
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        $filePath = $exportDir . '/' . basename($fileName); // basename para seguridad
        
        if (!file_exists($filePath)) {
            header('Content-Type: application/json; charset=utf-8', true, 404);
            die(json_encode(array('ok' => false, 'error' => 'Archivo no encontrado')));
        }
        
        if (!is_file($filePath)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No es un archivo válido')));
        }
        
        if (@unlink($filePath)) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(array(
                'ok' => true,
                'message' => 'Archivo eliminado: ' . $fileName
            )));
        } else {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => 'No se pudo eliminar el archivo. Verifica permisos.')));
        }
    }
    
    private function ajaxUploadFile()
    {
        if (!isset($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No se recibió archivo o hubo error en la subida')));
        }
        
        $file = $_FILES['sqlfile'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'sql') {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'Solo se permiten archivos .sql')));
        }
        
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file['name']);
        $destPath = $exportDir . '/' . $safeName;
        
        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(array(
                'ok' => true,
                'message' => 'Archivo subido correctamente',
                'filename' => $safeName
            )));
        } else {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => 'Error al mover el archivo')));
        }
    }

    private function ajaxRunTests()
    {
        require_once __DIR__.'/classes/PS9ExportTester.php';
        
        try {
            ob_start();
            $tester = new PS9ExportTester();
            $result = $tester->runAllTests();
            $output = ob_get_clean();
            
            $result['output'] = $output;
            
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }

    private function ajaxValidate()
    {
        require_once __DIR__.'/classes/ValidationService.php';
        $shopId = (int)Tools::getValue('shop_id', $this->context->shop->id);
        $langId = (int)Tools::getValue('lang_id', $this->context->language->id);

        try {
            $svc = new ValidationService();
            $result = $svc->runPreflight(_DB_PREFIX_, $shopId, $langId);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }

    private function ajaxRepair()
    {
        require_once __DIR__.'/classes/ValidationService.php';
        $shopId = (int)Tools::getValue('shop_id', $this->context->shop->id);
        $langId = (int)Tools::getValue('lang_id', $this->context->language->id);

        $optCatalog = Tools::getValue('catalog', '1') === '1';
        $optCustomers = Tools::getValue('customers', '0') === '1';
        $optOrders = Tools::getValue('orders', '0') === '1';

        try {
            $svc = new ValidationService();
            $result = $svc->repairOrphans(_DB_PREFIX_, $shopId, $langId, array(
                'catalog' => $optCatalog,
                'customers' => $optCustomers,
                'orders' => $optOrders,
            ));

            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }

    private function ajaxExport()
    {
        require_once __DIR__.'/classes/SqlDumpService.php';

        $shopId = (int)Tools::getValue('shop_id', $this->context->shop->id);
        $langId = (int)Tools::getValue('lang_id', $this->context->language->id);

        $optCatalog = Tools::getValue('catalog', '1') === '1';
        $optCustomers = Tools::getValue('customers', '0') === '1';
        $optOrders = Tools::getValue('orders', '0') === '1';

        try {
            $outDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $svc = new SqlDumpService();
            $result = $svc->exportToFile($outDir, array(
                'shop_id' => $shopId,
                'lang_id' => $langId,
                'catalog' => $optCatalog,
                'customers' => $optCustomers,
                'orders' => $optOrders,
            ));

            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
    
    /**
     * EXPORTAR A CSV formato OFICIAL PrestaShop
     * Compatible con el importador nativo de PS9
     */
    private function ajaxExportCSV()
    {
        require_once __DIR__.'/classes/CSVExportService.php';

        $shopId = (int)Tools::getValue('shop_id', $this->context->shop->id);
        $langId = (int)Tools::getValue('lang_id', $this->context->language->id);
        $exportType = Tools::getValue('export_type', 'products');

        try {
            $outDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            if (!is_dir($outDir)) {
                mkdir($outDir, 0775, true);
            }
            
            $svc = new CSVExportService($shopId, $langId);
            $timestamp = date('Ymd_His');
            
            switch ($exportType) {
                case 'categories':
                    $filename = "categories_csv_{$timestamp}.csv";
                    $result = $svc->exportCategories($outDir . '/' . $filename);
                    break;
                    
                case 'brands':
                    $filename = "brands_csv_{$timestamp}.csv";
                    $result = $svc->exportBrands($outDir . '/' . $filename);
                    break;
                    
                case 'suppliers':
                    $filename = "suppliers_csv_{$timestamp}.csv";
                    $result = $svc->exportSuppliers($outDir . '/' . $filename);
                    break;
                    
                case 'customers':
                    $filename = "customers_csv_{$timestamp}.csv";
                    $result = $svc->exportCustomers($outDir . '/' . $filename);
                    break;
                    
                case 'addresses':
                    $filename = "addresses_csv_{$timestamp}.csv";
                    $result = $svc->exportAddresses($outDir . '/' . $filename);
                    break;
                    
                default: // products
                    $filename = "products_csv_{$timestamp}.csv";
                    $result = $svc->exportProducts($outDir . '/' . $filename);
                    break;
            }
            
            $result['filename'] = $filename;

            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }

    private function ajaxExportImages()
    {
        require_once __DIR__.'/classes/ImageExportService.php';

        $includeProd = Tools::getValue('img_prod', '1') === '1';
        $includeCat = Tools::getValue('img_cat', '0') === '1';
        $includeManu = Tools::getValue('img_manu', '0') === '1';
        $includeSupp = Tools::getValue('img_supp', '0') === '1';

        try {
            $outDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $svc = new ImageExportService();
            $result = $svc->exportZip($outDir, array(
                'prod' => $includeProd,
                'cat' => $includeCat,
                'manu' => $includeManu,
                'supp' => $includeSupp,
            ));

            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
    
    private function ajaxValidateImport()
    {
        require_once __DIR__.'/classes/ImportService.php';
        
        $fileName = Tools::getValue('file');
        if (empty($fileName)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No file specified')));
        }
        
        try {
            $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $sqlFile = $exportDir . '/' . basename($fileName);
            
            $svc = new ImportService();
            $result = $svc->validateSqlFile($sqlFile);
            
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
    
    private function ajaxImport()
    {
        require_once __DIR__.'/classes/ImportService.php';
        
        $fileName = Tools::getValue('file');
        if (empty($fileName)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No file specified')));
        }
        
        try {
            $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $sqlFile = $exportDir . '/' . basename($fileName);
            
            $svc = new ImportService();
            $result = $svc->importFromFile($sqlFile);
            
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
    
    /**
     * RESTAURAR BACKUP DE EMERGENCIA
     * Para cuando el import rompe la BD
     */
    private function ajaxEmergencyRestore()
    {
        require_once __DIR__.'/classes/ImportService.php';
        
        $fileName = Tools::getValue('file');
        if (empty($fileName)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No backup file specified')));
        }
        
        try {
            $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $backupFile = $exportDir . '/' . basename($fileName);
            
            if (!file_exists($backupFile)) {
                throw new Exception('Backup file not found: ' . $fileName);
            }
            
            if (strpos($fileName, 'backup_before_import_') !== 0) {
                throw new Exception('Invalid backup file - must start with backup_before_import_');
            }
            
            $svc = new ImportService();
            $result = $svc->rollback($backupFile);
            
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(array(
                'ok' => true,
                'message' => 'Emergency restore completed successfully',
                'restored' => $result
            )));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
}
