<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ps9DataExport73 extends Module
{
    public function __construct()
    {
        $this->name = 'ps9dataexport73';
        $this->tab = 'administration';
        $this->version = '1.3.4';
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
            if ($action === 'validate') return $this->ajaxValidate();
            if ($action === 'repair') return $this->ajaxRepair();
            if ($action === 'exportSql') return $this->ajaxExport();
            if ($action === 'exportImages') return $this->ajaxExportImages();
            if ($action === 'listFiles') return $this->ajaxListFiles();
            if ($action === 'uploadFile') return $this->ajaxUploadFile();
            if ($action === 'validateImport') return $this->ajaxValidateImport();
            if ($action === 'import') return $this->ajaxImport();

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
}
