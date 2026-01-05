<?php
require_once _PS_MODULE_DIR_.'ps9dataexport73/classes/SqlWriter.php';
require_once _PS_MODULE_DIR_.'ps9dataexport73/classes/TablePlan.php';

class SqlDumpService
{
    public function exportToFile($dir, $opts)
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new Exception('Cannot create output directory: '.$dir);
            }
        }

        $prefix = _DB_PREFIX_;
        $sid = (int)(isset($opts['shop_id']) ? $opts['shop_id'] : 1);
        $fallbackLang = (int)(isset($opts['lang_id']) ? $opts['lang_id'] : 1);

        $db = Db::getInstance();
        $langs = $db->executeS("SELECT id_lang FROM `{$prefix}lang` WHERE active=1");
        $langIds = array();
        if ($langs) foreach ($langs as $r) $langIds[] = (int)$r['id_lang'];
        if (empty($langIds)) $langIds = array($fallbackLang);

        $tables = TablePlan::build($prefix, $opts);

        $stamp = date('Ymd_His');
        $file = rtrim($dir, '/')."/ps1_7_to_ps9_export_{$stamp}.sql";
        $writer = new SqlWriter($file);

        $writer->w("-- PS 1.7.6 -> PS9 data-only export\n");
        $writer->w("-- Generated at: ".date('c')."\n");
        $writer->w("-- Source prefix: {$prefix}\n");
        $writer->w("-- Target: Use {PREFIX} placeholder for table names\n");
        $writer->w("-- NOTE: Import into a CLEAN PS9 database. Backup first.\n\n");
        $writer->w("SET SESSION sql_mode='';\n");
        $writer->w("SET NAMES 'utf8mb4';\n");
        $writer->w("SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (array_reverse($tables) as $t) {
            $tname = str_replace($prefix, '{PREFIX}', $t);
            $writer->w("TRUNCATE TABLE `{$tname}`;\n");
        }
        $writer->w("\n");

        foreach ($tables as $t) {
            $this->dumpTable($t, $writer);
        }

        $this->writeFixups($writer, $prefix, $sid, $fallbackLang, $langIds);

        $writer->w("\nSET FOREIGN_KEY_CHECKS=1;\n");
        $writer->close();

        return array(
            'ok' => true,
            'file' => $file,
            'tables' => $tables,
            'count_tables' => count($tables),
            'langs' => $langIds,
            'options' => $opts,
        );
    }

    private function dumpTable($table, $writer)
    {
        $db = Db::getInstance();
        $cols = $db->executeS("SHOW COLUMNS FROM `{$table}`");
        if (!$cols) return;

        $colNames = array();
        foreach ($cols as $c) $colNames[] = $c['Field'];

        $escaped = array();
        foreach ($colNames as $c) $escaped[] = pSQL($c);
        $colSql = '`'.implode('`,`', $escaped).'`';
        
        $prefix = _DB_PREFIX_;
        $tname = str_replace($prefix, '{PREFIX}', $table);

        $writer->w("\n-- Dump {$tname}\n");

        $limit = 300;
        $offset = 0;

        while (true) {
            $rows = $db->executeS("SELECT * FROM `{$table}` LIMIT {$offset}, {$limit}");
            if (!$rows) break;

            $values = array();
            foreach ($rows as $row) {
                $vals = array();
                foreach ($colNames as $c) {
                    $vals[] = $this->sqlValue(array_key_exists($c, $row) ? $row[$c] : null);
                }
                $values[] = '('.implode(',', $vals).')';
            }

            $prefix = _DB_PREFIX_;
            $tname = str_replace($prefix, '{PREFIX}', $table);
            $writer->w("INSERT INTO `{$tname}` ({$colSql}) VALUES\n".implode(",\n", $values).";\n");
            $offset += $limit;
        }
    }

    private function sqlValue($v)
    {
        if ($v === null) return "NULL";

        if (is_numeric($v)) {
            $s = (string)$v;
            if (preg_match('/^-?\d+$/', $s) || preg_match('/^-?\d+\.\d+$/', $s)) return $s;
        }

        $s = (string)$v;
        $s = str_replace(array("\\", "'"), array("\\\\", "\\'"), $s);
        $s = str_replace(array("\r\n", "\r"), array("\n", "\n"), $s);
        return "'{$s}'";
    }

    private function writeFixups($writer, $prefix, $shopId, $fallbackLang, $langIds)
    {
        $sid = (int)$shopId;

        $writer->w("\n-- ==========================================================\n");
        $writer->w("-- FIXUPS: rellenos básicos para evitar BO 500 en PS9\n");
        $writer->w("-- ==========================================================\n");

        $writer->w("
INSERT IGNORE INTO `{PREFIX}product_shop` (id_product, id_shop, id_category_default, price, active, available_for_order, show_price, visibility, date_add, date_upd)
SELECT p.id_product, {$sid},
       COALESCE(p.id_category_default, 2),
       p.price, p.active, p.available_for_order, p.show_price, p.visibility, p.date_add, p.date_upd
FROM `{PREFIX}product` p
LEFT JOIN `{PREFIX}product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop={$sid}
WHERE ps.id_product IS NULL;
");

        $writer->w("
INSERT IGNORE INTO `{PREFIX}stock_available` (id_product, id_product_attribute, id_shop, id_shop_group, quantity, depends_on_stock, out_of_stock)
SELECT p.id_product, 0, {$sid}, 1, 0, 0, 2
FROM `{PREFIX}product` p
LEFT JOIN `{PREFIX}stock_available` sa
  ON sa.id_product=p.id_product AND sa.id_product_attribute=0 AND sa.id_shop={$sid}
WHERE sa.id_stock_available IS NULL;
");

        $writer->w("
INSERT IGNORE INTO `{PREFIX}category_product` (id_category, id_product, position)
SELECT p.id_category_default, p.id_product, 0
FROM `{PREFIX}product` p
LEFT JOIN `{PREFIX}category_product` cp
  ON cp.id_category=p.id_category_default AND cp.id_product=p.id_product
WHERE p.id_category_default IS NOT NULL AND cp.id_product IS NULL;
");

        foreach ($langIds as $lid) {
            $lid = (int)$lid;
            $writer->w("
INSERT IGNORE INTO `{PREFIX}product_lang` (id_product, id_shop, id_lang, name, description, description_short, link_rewrite, meta_title, meta_description, meta_keywords, available_now, available_later)
SELECT p.id_product, {$sid}, {$lid},
       COALESCE(src.name, CONCAT('Product ', p.id_product)),
       COALESCE(src.description, ''),
       COALESCE(src.description_short, ''),
       COALESCE(src.link_rewrite, CONCAT('product-', p.id_product)),
       COALESCE(src.meta_title, ''),
       COALESCE(src.meta_description, ''),
       COALESCE(src.meta_keywords, ''),
       COALESCE(src.available_now, ''),
       COALESCE(src.available_later, '')
FROM `{PREFIX}product` p
LEFT JOIN `{PREFIX}product_lang` src
  ON src.id_product=p.id_product AND src.id_shop={$sid} AND src.id_lang={$fallbackLang}
LEFT JOIN `{PREFIX}product_lang` pl
  ON pl.id_product=p.id_product AND pl.id_shop={$sid} AND pl.id_lang={$lid}
WHERE pl.id_product IS NULL;
");
        }
    }
}
