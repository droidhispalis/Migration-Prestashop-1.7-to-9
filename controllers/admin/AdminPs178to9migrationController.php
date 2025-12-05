<?php
/**
 * Admin Controller for PrestaShop Migration
 */

require_once dirname(__FILE__) . '/../../classes/MigrationService.php';

class AdminPs178to9migrationController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'configuration';

        // Aumentar límites PHP para importaciones grandes
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '600');
        @ini_set('post_max_size', '128M');
        @ini_set('upload_max_filesize', '128M');
        @set_time_limit(600);

        parent::__construct();
    }
    
    public function setMedia($isNewTheme = false)
    {
        // Interceptar ANTES de cargar media (muy temprano en el ciclo)
        if (Tools::isSubmit('submitExportImages')) {
            // Limpiar buffers
            while (@ob_end_clean());
            
            require_once dirname(__FILE__) . '/../../classes/MigrationService.php';
            
            try {
                $migrationService = new MigrationService();
                $result = $migrationService->exportImages();

                if (isset($result['success']) && $result['success']) {
                    $zipPath = _PS_MODULE_DIR_ . 'ps178to9migration/exports/' . $result['file'];
                    
                    if (file_exists($zipPath)) {
                        header('Content-Type: application/zip');
                        header('Content-Disposition: attachment; filename="' . $result['file'] . '"');
                        header('Content-Length: ' . filesize($zipPath));
                        readfile($zipPath);
                        die();
                    }
                }
                die('Export failed');
                
            } catch (Exception $e) {
                die('Error: ' . $e->getMessage());
            }
        }
        
        parent::setMedia($isNewTheme);
    }

    public function initContent()
    {
        parent::initContent();

        $this->content = $this->renderInfo();
        $this->content .= $this->renderExportForm();
        $this->content .= $this->renderImportForm();
        
        $this->context->smarty->assign(array(
            'content' => $this->content,
        ));
    }

    private function renderInfo()
    {
        $html = '<div class="alert alert-info">';
        $html .= '<h4><i class="icon-info-circle"></i> ' . $this->l('PrestaShop Migration Module') . '</h4>';
        $html .= '<p>' . $this->l('This module allows you to export and import database tables between PrestaShop versions.') . '</p>';
        $html .= '<p><strong>' . $this->l('Compatible versions:') . '</strong> PrestaShop 1.7.0 to 9.x</p>';
        $html .= '<p><strong>' . $this->l('Current database prefix:') . '</strong> <code>' . _DB_PREFIX_ . '</code></p>';
        $html .= '<p class="text-muted"><small>' . $this->l('The system will automatically adapt table prefixes during import.') . '</small></p>';
        $html .= '</div>';
        return $html;
    }

    private function renderExportForm()
    {
        $migrationService = new MigrationService();
        $tables = $migrationService->getCompatibleTables();
        
        // Contar tablas por categoría
        $productTables = 0;
        $categoryTables = 0;
        $customerTables = 0;
        $existingTables = 0;
        
        foreach ($tables as $table) {
            if ($table['exists']) {
                $existingTables++;
                
                // Detectar tablas de productos
                if (strpos($table['name'], 'product') !== false || 
                    strpos($table['name'], 'stock') !== false ||
                    strpos($table['name'], 'image') !== false) {
                    $productTables++;
                }
                
                // Detectar tablas de categorías
                if (strpos($table['name'], 'category') !== false) {
                    $categoryTables++;
                }
                
                // Detectar tablas de clientes
                if (strpos($table['name'], 'customer') !== false || 
                    strpos($table['name'], 'address') !== false ||
                    strpos($table['name'], 'order') !== false) {
                    $customerTables++;
                }
            }
        }
        
        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-download"></i> ' . $this->l('Export Database') . '</div>';
        $html .= '<div class="panel-body">';
        
        // Información de tablas detectadas
        $html .= '<div class="alert alert-info">';
        $html .= '<h4>' . $this->l('Tables detected:') . '</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>' . $existingTables . '</strong> ' . $this->l('tables found in total') . '</li>';
        $html .= '<li><strong>' . $productTables . '</strong> ' . $this->l('product-related tables') . '</li>';
        $html .= '<li><strong>' . $categoryTables . '</strong> ' . $this->l('category-related tables') . '</li>';
        $html .= '<li><strong>' . $customerTables . '</strong> ' . $this->l('customer/order-related tables') . '</li>';
        $html .= '</ul>';
        
        if ($productTables == 0) {
            $html .= '<div class="alert alert-danger">';
            $html .= '<strong>' . $this->l('WARNING:') . '</strong> ';
            $html .= $this->l('No product tables detected! Products will NOT be exported.');
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        $html .= '<form method="post" action="' . $this->context->link->getAdminLink('AdminPs178to9migration') . '">';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Export Format') . '</label>';
        $html .= '<select name="export_format" class="form-control">';
        $html .= '<option value="json">JSON</option>';
        $html .= '<option value="sql">SQL</option>';
        $html .= '<option value="csv">CSV</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Export Mode') . '</label>';
        $html .= '<select name="export_mode" class="form-control">';
        $html .= '<option value="single">' . $this->l('Single File') . '</option>';
        $html .= '<option value="multiple">' . $this->l('Multiple Files (ZIP)') . '</option>';
        $html .= '</select>';
        $html .= '</div>';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Tables to Export') . '</label>';
        $html .= '<p class="help-block">' . $existingTables . ' ' . $this->l('compatible tables will be exported') . '</p>';
        $html .= '</div>';
        
        $html .= '<button type="submit" name="submitExport" class="btn btn-primary">';
        $html .= '<i class="icon-download"></i> ' . $this->l('Export All Tables');
        $html .= '</button>';
        
        $html .= '</form>';
        
        $html .= '<hr>';
        
        $html .= '<h4><i class="icon-picture"></i> ' . $this->l('Export Images') . '</h4>';
        $html .= '<p>' . $this->l('Export all product, category, manufacturer and supplier images to a ZIP file') . '</p>';
        
        $html .= '<div class="alert alert-danger">';
        $html .= '<i class="icon-warning-sign"></i> <strong>' . $this->l('IMPORTANT - PLEASE READ:') . '</strong><br>';
        $html .= '<ul style="margin: 10px 0 0 20px;">';
        $html .= '<li><strong>' . $this->l('This process can take 5-15 minutes') . '</strong></li>';
        $html .= '<li>' . $this->l('The page will appear FROZEN - this is NORMAL') . '</li>';
        $html .= '<li>' . $this->l('DO NOT close the browser or tab') . '</li>';
        $html .= '<li>' . $this->l('DO NOT click the button again') . '</li>';
        $html .= '<li>' . $this->l('The download will start automatically when finished') . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '<form method="POST" action="' . $this->context->link->getAdminLink('AdminPs178to9migration') . '">';
        $html .= '<button type="submit" name="submitExportImages" class="btn btn-success btn-lg">';
        $html .= '<i class="icon-download"></i> ' . $this->l('Export Images Now');
        $html .= '</button>';
        $html .= '</form>';
        
        $html .= '<hr>';
        
        // Exportar tema
        $html .= '<h4><i class="icon-paint-brush"></i> ' . $this->l('Export Theme') . '</h4>';
        $html .= '<p>' . $this->l('Export the active theme from PrestaShop 1.7.6') . '</p>';
        $html .= '<div class="alert alert-warning">';
        $html .= '<i class="icon-warning"></i> <strong>' . $this->l('Important:') . '</strong> ';
        $html .= $this->l('Themes from PrestaShop 1.7.6 require manual adaptation for PrestaShop 9. The export will include instructions.');
        $html .= '</div>';
        
        $html .= '<form method="POST" action="' . $this->context->link->getAdminLink('AdminPs178to9migration') . '">';
        $html .= '<button type="submit" name="submitExportTheme" class="btn btn-warning">';
        $html .= '<i class="icon-download"></i> ' . $this->l('Export Active Theme');
        $html .= '</button>';
        $html .= '</form>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    private function renderImportForm()
    {
        $adminLink = $this->context->link->getAdminLink('AdminPs178to9migration');
        
        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-upload"></i> ' . $this->l('Import Database') . '</div>';
        $html .= '<div class="panel-body">';
        
        $html .= '<div class="alert alert-warning">';
        $html .= '<h4><i class="icon-warning"></i> ' . $this->l('Important') . '</h4>';
        $html .= '<ul>';
        $html .= '<li>' . $this->l('Make sure you have a backup before importing data') . '</li>';
        $html .= '<li>' . $this->l('The system will automatically adapt table prefixes (current: ') . '<code>' . _DB_PREFIX_ . '</code>)</li>';
        $html .= '<li>' . $this->l('The system will automatically validate and adapt data to match the target version schema') . '</li>';
        $html .= '<li>' . $this->l('Incompatible fields will be skipped automatically') . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Formulario POST simple (SIN AJAX - más compatible)
        $html .= '<form method="POST" enctype="multipart/form-data" action="' . $adminLink . '">';
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Import File') . '</label>';
        $html .= '<input type="file" name="import_file" class="form-control" accept=".json,.sql,.csv,.zip" required />';
        $html .= '<p class="help-block">' . $this->l('Supported formats: JSON, SQL, CSV, ZIP (multiple files)') . '</p>';
        $maxUpload = ini_get('upload_max_filesize');
        $html .= '<p class="help-block"><strong>' . $this->l('Maximum file size:') . ' ' . $maxUpload . '</strong></p>';
        $html .= '</div>';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Import Mode') . '</label>';
        $html .= '<select name="import_mode" class="form-control" required>';
        $html .= '<option value="skip-duplicates">' . $this->l('Skip Duplicates - Ignore existing data (RECOMMENDED)') . '</option>';
        $html .= '<option value="append">' . $this->l('Append - Try to add all records (may show duplicate warnings)') . '</option>';
        $html .= '<option value="update">' . $this->l('Update - Update existing records, add new ones') . '</option>';
        $html .= '<option value="replace" selected>' . $this->l('Replace - Delete all existing data first (RECOMMENDED for fresh install)') . '</option>';
        $html .= '</select>';
        $html .= '<p class="help-block text-danger">' . $this->l('Replace mode: Best for clean migration - truncates tables before importing') . '</p>';
        $html .= '</div>';
        
        $html .= '<div class="form-group">';
        $html .= '<label class="control-label">';
        $html .= '<input type="checkbox" name="import_validate" checked /> ';
        $html .= $this->l('Validate data and adapt to target schema');
        $html .= '</label>';
        $html .= '<p class="help-block">' . $this->l('Recommended: Validates and filters incompatible fields automatically') . '</p>';
        $html .= '</div>';
        
        $html .= '<div class="form-group">';
        $html .= '<label class="control-label">';
        $html .= '<input type="checkbox" name="import_backup" checked /> ';
        $html .= $this->l('Create backup before import');
        $html .= '</label>';
        $html .= '<p class="help-block">' . $this->l('Creates a backup copy of each table before importing') . '</p>';
        $html .= '</div>';
        
        $html .= '<button type="submit" name="submitImport" class="btn btn-primary btn-lg">';
        $html .= '<i class="icon-upload"></i> ' . $this->l('Import Database');
        $html .= '</button>';
        $html .= '</form>';
        
        $html .= '<hr style="margin: 30px 0;">';
        
        // Formulario de importación de imágenes (POST simple)
        $html .= '<h4><i class="icon-picture"></i> ' . $this->l('Import Images') . '</h4>';
        $html .= '<p>' . $this->l('Upload the images ZIP file exported from PrestaShop 1.7.6') . '</p>';
        
        $html .= '<form method="POST" enctype="multipart/form-data" action="' . $adminLink . '">';
        $html .= '<div class="form-group">';
        $html .= '<label>' . $this->l('Images ZIP File') . '</label>';
        $html .= '<input type="file" name="images_file" class="form-control" accept=".zip" required />';
        $html .= '<p class="help-block">' . $this->l('Select the images ZIP file created by the export function') . '</p>';
        $html .= '</div>';
        
        $html .= '<button type="submit" name="submitImportImages" class="btn btn-success btn-lg">';
        $html .= '<i class="icon-upload"></i> ' . $this->l('Import Images');
        $html .= '</button>';
        $html .= '</form>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    public function postProcess()
    {
        // Descargar archivo exportado (para compatibilidad con enlaces antiguos)
        if (Tools::getValue('download')) {
            $this->downloadFile(Tools::getValue('download'));
            exit;
        }
        
        if (Tools::isSubmit('submitExport')) {
            $this->processExport();
        }
        
        if (Tools::isSubmit('submitExportTheme')) {
            $this->processExportTheme();
        }
        
        if (Tools::isSubmit('submitExportImages')) {
            $this->processExportImages();
        }
        
        if (Tools::isSubmit('submitImport')) {
            $isAjax = Tools::getValue('ajax');
            $this->processImport($isAjax);
        }
        
        if (Tools::isSubmit('submitImportImages')) {
            $isAjax = Tools::getValue('ajax');
            $this->processImportImages($isAjax);
        }
    }

    // DESPUÉS (firma compatible con el core)
	public function processExport($text_delimiter = '')
	{
        $format = Tools::getValue('export_format');
        $mode = Tools::getValue('export_mode');

        if (empty($format)) {
            $this->errors[] = $this->l('Please select export format');
            return;
        }

        try {
            $migrationService = new MigrationService();
            $tableNames = $migrationService->getAllCompatibleTableNames();

            if ($mode === 'single') {
                $content = $migrationService->exportToSingleFile($tableNames, $format);
                $filename = 'prestashop_export_' . date('Y-m-d_H-i-s') . '.' . $format;
                
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;
            } else {
                $files = $migrationService->exportToMultipleFiles($tableNames, $format);
                $zipFilename = 'prestashop_export_' . date('Y-m-d_H-i-s') . '.zip';
                $zipPath = _PS_CACHE_DIR_ . $zipFilename;

                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                    foreach ($files as $filename => $content) {
                        $zip->addFromString($filename, $content);
                    }
                    $zip->close();

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
                    header('Content-Length: ' . filesize($zipPath));
                    readfile($zipPath);
                    unlink($zipPath);
                    exit;
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Export error: ') . $e->getMessage();
        }
    }

    private function processExportTheme()
    {
        try {
            $migrationService = new MigrationService();
            $result = $migrationService->exportTheme();
            
            if ($result['success']) {
                $zipPath = $result['file_path'];
                $themeName = $result['theme_name'];
                $size = round($result['size'] / 1024 / 1024, 2);
                
                // Mostrar confirmación
                $this->confirmations[] = $this->l('Theme exported successfully!');
                $this->confirmations[] = $this->l('Theme: ') . $themeName;
                $this->confirmations[] = $this->l('Size: ') . $size . ' MB';
                
                // Mostrar advertencias
                foreach ($result['warnings'] as $warning) {
                    $this->warnings[] = $warning;
                }
                
                $this->warnings[] = $this->l('IMPORTANT: Theme requires manual adaptation for PrestaShop 9');
                $this->warnings[] = $this->l('Check README_MIGRATION.txt inside the ZIP');
                
                // Descargar archivo
                if (file_exists($zipPath)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
                    header('Content-Length: ' . filesize($zipPath));
                    readfile($zipPath);
                    unlink($zipPath);
                    exit;
                }
            } else {
                foreach ($result['errors'] as $error) {
                    $this->errors[] = $error;
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = $this->l('Theme export error: ') . $e->getMessage();
        }
    }

    private function processImport($isAjax = false)
    {
        // Verificar errores de subida
        if (!isset($_FILES['import_file'])) {
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => ['No file was uploaded'],
                    'warnings' => [],
                    'tables_processed' => 0,
                    'rows_inserted' => 0
                ]));
            }
            $this->errors[] = $this->l('No file was uploaded. Please select a file.');
            return;
        }

        $file = $_FILES['import_file'];
        
        // Diagnosticar errores específicos de PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = '';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $maxSize = ini_get('upload_max_filesize');
                    $errorMsg = sprintf('File is too large. Maximum allowed: %s', $maxSize);
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'File upload was interrupted. Please try again.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No file was selected.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg = 'Server configuration error: temporary folder missing.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = 'Server error: cannot write file to disk.';
                    break;
                default:
                    $errorMsg = sprintf('Upload error code: %d', $file['error']);
            }
            
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => [$errorMsg],
                    'warnings' => [],
                    'tables_processed' => 0,
                    'rows_inserted' => 0
                ]));
            }
            $this->errors[] = $this->l($errorMsg);
            return;
        }

        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mode = Tools::getValue('import_mode', 'skip-duplicates');
        $validate = (bool)Tools::getValue('import_validate', true);
        $backup = (bool)Tools::getValue('import_backup', true);

        try {
            $migrationService = new MigrationService();
            $content = file_get_contents($tmpPath);
            
            if ($content === false) {
                if ($isAjax) {
                    die(json_encode([
                        'success' => false,
                        'errors' => ['Could not read uploaded file'],
                        'warnings' => [],
                        'tables_processed' => 0,
                        'rows_inserted' => 0
                    ]));
                }
                $this->errors[] = $this->l('Could not read uploaded file. Please try again.');
                return;
            }
            
            $result = null;

            switch ($extension) {
                case 'json':
                    $result = $migrationService->importFromJSON($content, $mode, $validate, $backup);
                    break;
                
                case 'sql':
                    $result = $migrationService->importFromSQL($content, $mode, $validate, $backup);
                    break;
                
                case 'csv':
                    $result = $migrationService->importFromCSV($content, $mode, $validate, $backup);
                    break;
                
                case 'zip':
                    $result = $migrationService->importFromZip($tmpPath, $mode, $validate, $backup);
                    break;
                
                default:
                    if ($isAjax) {
                        die(json_encode([
                            'success' => false,
                            'errors' => ['Unsupported file format'],
                            'warnings' => [],
                            'tables_processed' => 0,
                            'rows_inserted' => 0
                        ]));
                    }
                    $this->errors[] = $this->l('Unsupported file format. Use JSON, SQL, CSV or ZIP.');
                    return;
            }

            if ($result) {
                // Para AJAX, devolver JSON
                if ($isAjax) {
                    header('Content-Type: application/json');
                    die(json_encode([
                        'success' => isset($result['success']) ? $result['success'] : false,
                        'errors' => isset($result['errors']) ? $result['errors'] : [],
                        'warnings' => isset($result['warnings']) ? $result['warnings'] : [],
                        'tables_processed' => isset($result['tables_processed']) ? $result['tables_processed'] : 0,
                        'rows_inserted' => isset($result['rows_inserted']) ? $result['rows_inserted'] : 0
                    ]));
                }
                
                // Para form normal, mostrar mensajes
                $tables = isset($result['tables_processed']) ? $result['tables_processed'] : 0;
                $rows = isset($result['rows_inserted']) ? $result['rows_inserted'] : 0;
                
                $message = sprintf(
                    $this->l('Import completed! Tables: %d, Rows: %d'),
                    $tables,
                    $rows
                );
                $this->confirmations[] = $message;
                
                if (isset($result['warnings']) && !empty($result['warnings'])) {
                    foreach (array_slice($result['warnings'], 0, 10) as $warning) {
                        $this->warnings[] = $warning;
                    }
                }
                
                if (isset($result['errors']) && !empty($result['errors'])) {
                    foreach (array_slice($result['errors'], 0, 10) as $error) {
                        $this->errors[] = $error;
                    }
                }
            } else {
                $this->errors[] = $this->l('Import failed: No result returned');
            }

        } catch (Exception $e) {
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => [$e->getMessage()],
                    'warnings' => [],
                    'tables_processed' => 0,
                    'rows_inserted' => 0
                ]));
            }
            $this->errors[] = $this->l('Import error: ') . $e->getMessage();
        }
    }

    private function processExportImages()
    {
        // FORZAR detención TOTAL de PrestaShop
        while (@ob_end_clean());
        
        // Log para debugging
        error_log('=== EXPORT IMAGES CALLED ===');
        
        try {
            $migrationService = new MigrationService();
            $result = $migrationService->exportImages();
            
            error_log('Export result: ' . print_r($result, true));

            if (isset($result['success']) && $result['success']) {
                $zipPath = _PS_MODULE_DIR_ . 'ps178to9migration/exports/' . $result['file'];
                
                error_log('ZIP path: ' . $zipPath);
                error_log('File exists: ' . (file_exists($zipPath) ? 'YES' : 'NO'));
                
                if (!file_exists($zipPath)) {
                    error_log('ERROR: ZIP not found');
                    die('Error: ZIP file not created');
                }
                
                // Headers para forzar descarga
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $result['file'] . '"');
                header('Content-Length: ' . filesize($zipPath));
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Limpiar cualquier output
                flush();
                
                // Enviar archivo
                readfile($zipPath);
                
                error_log('File sent successfully');
                die();
                
            } else {
                $errors = isset($result['errors']) ? implode(', ', $result['errors']) : 'Unknown error';
                error_log('Export failed: ' . $errors);
                die('Export failed: ' . $errors);
            }

        } catch (Exception $e) {
            error_log('Exception: ' . $e->getMessage());
            die('Error: ' . $e->getMessage());
        }
    }

    private function processImportImages($isAjax = false)
    {
        if (!isset($_FILES['images_file'])) {
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => ['No file was uploaded'],
                    'warnings' => [],
                    'images_imported' => 0
                ]));
            }
            $this->errors[] = $this->l('No file was uploaded. Please select a ZIP file.');
            return;
        }

        $file = $_FILES['images_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = 'File upload error: ' . $file['error'];
            
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => [$errorMsg],
                    'warnings' => [],
                    'images_imported' => 0
                ]));
            }
            $this->errors[] = $this->l($errorMsg);
            return;
        }

        try {
            $migrationService = new MigrationService();
            $result = $migrationService->importImages($file['tmp_name']);

            if ($isAjax) {
                header('Content-Type: application/json');
                die(json_encode($result));
            }

            if ($result['success']) {
                $this->confirmations[] = sprintf(
                    $this->l('%d images imported successfully!'),
                    $result['images_imported']
                );
            } else {
                $this->errors[] = $this->l('Image import failed: ') . implode(', ', $result['errors']);
            }

            if (!empty($result['warnings'])) {
                foreach (array_slice($result['warnings'], 0, 10) as $warning) {
                    $this->warnings[] = $warning;
                }
            }

        } catch (Exception $e) {
            if ($isAjax) {
                die(json_encode([
                    'success' => false,
                    'errors' => [$e->getMessage()],
                    'warnings' => [],
                    'images_imported' => 0
                ]));
            }
            $this->errors[] = $this->l('Image import error: ') . $e->getMessage();
        }
    }

    private function ajaxStartImageExport()
    {
        header('Content-Type: application/json');
        
        // Crear archivo de estado
        $statusFile = _PS_MODULE_DIR_ . 'ps178to9migration/exports/export_status.json';
        $statusDir = dirname($statusFile);
        
        if (!file_exists($statusDir)) {
            mkdir($statusDir, 0777, true);
        }
        
        $status = [
            'status' => 'processing',
            'current' => 0,
            'total' => 0,
            'message' => 'Counting images...',
            'started' => time()
        ];
        
        file_put_contents($statusFile, json_encode($status));
        
        // Iniciar exportación en segundo plano (simulado con script que se ejecuta rápido por lotes)
        die(json_encode(['status' => 'started']));
    }

    private function ajaxCheckImageExport()
    {
        header('Content-Type: application/json');
        
        $statusFile = _PS_MODULE_DIR_ . 'ps178to9migration/exports/export_status.json';
        
        if (!file_exists($statusFile)) {
            // Si no existe el estado, iniciar la exportación real
            $this->executeImageExport();
        }
        
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true);
            die(json_encode($status));
        }
        
        die(json_encode(['status' => 'error', 'message' => 'Export not started']));
    }

    private function executeImageExport()
    {
        $statusFile = _PS_MODULE_DIR_ . 'ps178to9migration/exports/export_status.json';
        
        try {
            $migrationService = new MigrationService();
            $result = $migrationService->exportImagesWithProgress($statusFile);
            
            if ($result['success']) {
                $status = [
                    'status' => 'completed',
                    'file' => $result['file'],
                    'size' => $result['size'],
                    'images_count' => $result['images_count']
                ];
            } else {
                $status = [
                    'status' => 'error',
                    'message' => implode(', ', $result['errors'])
                ];
            }
            
            file_put_contents($statusFile, json_encode($status));
            
        } catch (Exception $e) {
            $status = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            file_put_contents($statusFile, json_encode($status));
        }
    }

    private function downloadFile($filename)
    {
        $filePath = _PS_MODULE_DIR_ . 'ps178to9migration/exports/' . basename($filename);
        
        if (!file_exists($filePath)) {
            die('File not found');
        }
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }
}

