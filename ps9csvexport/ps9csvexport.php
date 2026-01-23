<?php
if (!defined('_PS_VERSION_')) { exit; }

class Ps9CsvExport extends Module
{
    public function __construct()
    {
        $this->name = 'ps9csvexport';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'Custom';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PS9 CSV Export & Import');
        $this->description = $this->l('Export and import data using official PrestaShop CSV format. Compatible with any database prefix.');
        
        $this->processDownload();
    }
    
    private function processDownload()
    {
        if (Tools::isSubmit('downloadPs9Export') && Tools::getValue('file')) {
            $file = Tools::getValue('file');
            $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
            $filePath = $exportDir . '/' . basename($file);
            
            if (file_exists($filePath) && is_file($filePath)) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Pragma: no-cache');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                
                $handle = fopen($filePath, 'rb');
                while (!feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
                exit;
            }
        }
    }

    public function install() { return parent::install(); }
    public function uninstall() { return parent::uninstall(); }

    public function getContent()
    {
        if (Tools::getValue('ajax') === '1') {
            $action = Tools::getValue('action');
            if ($action === 'exportCSV') return $this->ajaxExportCSV();
            if ($action === 'importCSV') return $this->ajaxImportCSV();
            if ($action === 'uploadFile') return $this->ajaxUploadFile();
            if ($action === 'listFiles') return $this->ajaxListFiles();
            if ($action === 'deleteFile') return $this->ajaxDeleteFile();

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
                        'type' => in_array($ext, array('csv', 'sql')) ? $ext : 'other'
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
            die(json_encode(array('ok' => false, 'error' => 'No file specified')));
        }
        
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        $filePath = $exportDir . '/' . basename($fileName);
        
        if (file_exists($filePath) && is_file($filePath)) {
            if (@unlink($filePath)) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(array('ok' => true, 'message' => 'File deleted successfully')));
            }
        }
        
        header('Content-Type: application/json; charset=utf-8', true, 500);
        die(json_encode(array('ok' => false, 'error' => 'Could not delete file')));
    }
    
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
                    
                default:
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
    
    private function ajaxUploadFile()
    {
        if (!isset($_FILES['csv_file'])) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No file uploaded')));
        }
        
        $file = $_FILES['csv_file'];
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0775, true);
        }
        
        $filename = basename($file['name']);
        $targetPath = $exportDir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(array('ok' => true, 'filename' => $filename)));
        }
        
        header('Content-Type: application/json; charset=utf-8', true, 500);
        die(json_encode(array('ok' => false, 'error' => 'Failed to upload file')));
    }
    
    private function ajaxImportCSV()
    {
        require_once __DIR__.'/classes/CSVImportService.php';
        
        $filename = Tools::getValue('filename');
        $entityType = Tools::getValue('entity_type');
        $truncate = Tools::getValue('truncate', '0') === '1';
        
        if (empty($filename)) {
            header('Content-Type: application/json; charset=utf-8', true, 400);
            die(json_encode(array('ok' => false, 'error' => 'No file specified')));
        }
        
        $exportDir = _PS_DOWNLOAD_DIR_.'ps9-export';
        $filePath = $exportDir . '/' . basename($filename);
        
        if (!file_exists($filePath)) {
            header('Content-Type: application/json; charset=utf-8', true, 404);
            die(json_encode(array('ok' => false, 'error' => 'File not found')));
        }
        
        try {
            $importer = new CSVImportService();
            $result = $importer->import($filePath, $entityType, $truncate);
            
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode($result));
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            die(json_encode(array('ok' => false, 'error' => $e->getMessage())));
        }
    }
}
