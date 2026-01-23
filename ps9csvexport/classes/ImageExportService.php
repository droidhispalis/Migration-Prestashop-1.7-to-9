<?php
class ImageExportService
{
    public function exportZip($outDir, $opts)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive no está disponible en este servidor (ext-zip).');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '-1');

        if (!is_dir($outDir)) {
            if (!@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
                throw new Exception('Cannot create output directory: '.$outDir);
            }
        }

        $zipFilename = 'images_export_' . date('Ymd_His') . '.zip';
        $zipPath = rtrim($outDir, '/').'/'.$zipFilename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el ZIP: '.$zipPath);
        }

        $totalFiles = 0;

        $folders = array();
        if (!empty($opts['prod'])) $folders['p'] = _PS_PROD_IMG_DIR_;
        if (!empty($opts['cat'])) $folders['c'] = _PS_CAT_IMG_DIR_;
        if (!empty($opts['manu'])) $folders['m'] = _PS_MANU_IMG_DIR_;
        if (!empty($opts['supp'])) $folders['su'] = _PS_SUPP_IMG_DIR_;

        foreach ($folders as $code => $path) {
            if (file_exists($path) && is_dir($path)) {
                $totalFiles += $this->addFolderToZip($zip, $path, 'img/'.$code);
            }
        }

        if (!empty($opts['prod'])) {
            $manifest = $this->buildProductManifest(_DB_PREFIX_);
            if ($manifest !== '') $zip->addFromString('manifest/product_images_manifest.csv', $manifest);
        }

        $zip->close();

        if ($totalFiles <= 0) {
            @unlink($zipPath);
            return array('ok' => false, 'error' => 'No se encontraron imágenes para exportar.');
        }

        return array(
            'ok' => true,
            'file' => $zipPath,
            'filename' => $zipFilename,
            'size' => @filesize($zipPath),
            'files' => $totalFiles,
        );
    }

    private function addFolderToZip($zip, $folderPath, $zipPath)
    {
        $count = 0;
        $items = @scandir($folderPath);
        if ($items === false) return 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $itemPath = rtrim($folderPath, '/').'/'.$item;
            $itemZipPath = rtrim($zipPath, '/').'/'.$item;

            if (is_dir($itemPath)) {
                $count += $this->addFolderToZip($zip, $itemPath, $itemZipPath);
            } else {
                if (@$zip->addFile($itemPath, $itemZipPath)) $count++;
            }
        }
        return $count;
    }

    private function buildProductManifest($prefix)
    {
        $db = Db::getInstance();
        $rows = $db->executeS("SELECT id_product, id_image, position, cover FROM `{$prefix}image` ORDER BY id_product, position");
        if (!$rows) return '';

        $out = "id_product,id_image,position,cover\n";
        foreach ($rows as $r) {
            $out .= (int)$r['id_product'].",".(int)$r['id_image'].",".(int)$r['position'].",".(int)$r['cover']."\n";
        }
        return $out;
    }
}
