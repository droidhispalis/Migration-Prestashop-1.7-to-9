<?php
class ValidationService
{
    public function runPreflight($prefix, $shopId, $langId)
    {
        $db = Db::getInstance();

        $shops = $db->executeS("SELECT id_shop FROM `{$prefix}shop` WHERE active=1 AND deleted=0");
        $langs = $db->executeS("SELECT id_lang FROM `{$prefix}lang` WHERE active=1");

        $shopIds = array();
        if ($shops) foreach ($shops as $r) $shopIds[] = (int)$r['id_shop'];

        $langIds = array();
        if ($langs) foreach ($langs as $r) $langIds[] = (int)$r['id_lang'];

        if (empty($shopIds)) $shopIds = array((int)$shopId);
        if (empty($langIds)) $langIds = array((int)$langId);

        $checks = array();

        $add = function($name, $sql, $idField) use (&$checks, $db) {
            $count = (int)$db->getValue("SELECT COUNT(*) FROM ({$sql}) t");
            $sample = array();
            if ($count > 0) {
                $rows = $db->executeS($sql . " LIMIT 20");
                if ($rows) foreach ($rows as $r) if (isset($r[$idField])) $sample[] = (string)$r[$idField];
            }
            $checks[] = array('name' => $name, 'count' => $count, 'sample' => $sample);
        };

        $add('product_default_category_missing',
            "SELECT p.id_product
             FROM `{$prefix}product` p
             LEFT JOIN `{$prefix}category` c ON c.id_category = p.id_category_default
             WHERE p.id_category_default IS NULL OR c.id_category IS NULL",
            'id_product'
        );

        $add('category_product_orphans',
            "SELECT cp.id_product
             FROM `{$prefix}category_product` cp
             LEFT JOIN `{$prefix}product` p ON p.id_product = cp.id_product
             LEFT JOIN `{$prefix}category` c ON c.id_category = cp.id_category
             WHERE p.id_product IS NULL OR c.id_category IS NULL",
            'id_product'
        );

        foreach ($shopIds as $sid) {
            $add('product_shop_missing_shop_'.$sid,
                "SELECT p.id_product
                 FROM `{$prefix}product` p
                 LEFT JOIN `{$prefix}product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=".(int)$sid."
                 WHERE ps.id_product IS NULL",
                'id_product'
            );
        }

        foreach ($shopIds as $sid) {
            foreach ($langIds as $lid) {
                $add('product_lang_missing_shop_'.$sid.'_lang_'.$lid,
                    "SELECT p.id_product
                     FROM `{$prefix}product` p
                     LEFT JOIN `{$prefix}product_lang` pl
                       ON pl.id_product=p.id_product AND pl.id_shop=".(int)$sid." AND pl.id_lang=".(int)$lid."
                     WHERE pl.id_product IS NULL",
                    'id_product'
                );
            }
        }

        foreach ($shopIds as $sid) {
            $add('stock_available_missing_shop_'.$sid,
                "SELECT p.id_product
                 FROM `{$prefix}product` p
                 LEFT JOIN `{$prefix}stock_available` sa
                   ON sa.id_product=p.id_product AND sa.id_product_attribute=0 AND sa.id_shop=".(int)$sid."
                 WHERE sa.id_stock_available IS NULL",
                'id_product'
            );
        }

        $ok = true;
        foreach ($checks as $c) { if ((int)$c['count'] > 0) { $ok = false; break; } }

        return array(
            'ok' => $ok,
            'shops' => $shopIds,
            'langs' => $langIds,
            'checks' => $checks,
            'hint' => $ok ? 'OK: no se detectan huérfanos típicos del catálogo.' : 'KO: hay huérfanos (esto suele romper PS9).'
        );
    }

    public function repairOrphans($prefix, $shopId, $langId, $opts)
    {
        $db = Db::getInstance();
        $sid = (int)$shopId;
        $fallbackLang = (int)$langId;

        $langs = $db->executeS("SELECT id_lang FROM `{$prefix}lang` WHERE active=1");
        $langIds = array();
        if ($langs) foreach ($langs as $r) $langIds[] = (int)$r['id_lang'];
        if (empty($langIds)) $langIds = array($fallbackLang);

        $result = array('ok' => true, 'langs' => $langIds, 'actions' => array());

        if (!empty($opts['catalog'])) {
            $sql = "DELETE cp FROM `{$prefix}category_product` cp
                    LEFT JOIN `{$prefix}product` p ON p.id_product = cp.id_product
                    LEFT JOIN `{$prefix}category` c ON c.id_category = cp.id_category
                    WHERE p.id_product IS NULL OR c.id_category IS NULL";
            $db->execute($sql);
            $result['actions'][] = array('action' => 'delete_category_product_orphans', 'affected' => (int)$db->Affected_Rows());

            $sql = "UPDATE `{$prefix}product` p
                    LEFT JOIN `{$prefix}category` cdef ON cdef.id_category = p.id_category_default
                    SET p.id_category_default = COALESCE(
                        (SELECT MIN(cp.id_category)
                         FROM `{$prefix}category_product` cp
                         INNER JOIN `{$prefix}category` c ON c.id_category = cp.id_category
                         WHERE cp.id_product = p.id_product),
                        2
                    )
                    WHERE p.id_category_default IS NULL OR cdef.id_category IS NULL";
            $db->execute($sql);
            $result['actions'][] = array('action' => 'fix_product_default_category', 'affected' => (int)$db->Affected_Rows());

            $sql = "INSERT IGNORE INTO `{$prefix}product_shop`
                    (id_product, id_shop, id_category_default, price, active, available_for_order, show_price, visibility, date_add, date_upd)
                    SELECT p.id_product, {$sid}, p.id_category_default, p.price, p.active, p.available_for_order, p.show_price, p.visibility, p.date_add, p.date_upd
                    FROM `{$prefix}product` p";
            $db->execute($sql);
            $result['actions'][] = array('action' => 'ensure_product_shop', 'affected' => (int)$db->Affected_Rows());

            $sql = "INSERT IGNORE INTO `{$prefix}stock_available`
                    (id_product, id_product_attribute, id_shop, id_shop_group, quantity, depends_on_stock, out_of_stock)
                    SELECT p.id_product, 0, {$sid}, 1, 0, 0, 2
                    FROM `{$prefix}product` p";
            $db->execute($sql);
            $result['actions'][] = array('action' => 'ensure_stock_available', 'affected' => (int)$db->Affected_Rows());

            foreach ($langIds as $lid) {
                $lid = (int)$lid;
                $sql = "INSERT IGNORE INTO `{$prefix}product_lang`
                        (id_product, id_shop, id_lang, name, description, description_short, link_rewrite, meta_title, meta_description, meta_keywords, available_now, available_later)
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
                        FROM `{$prefix}product` p
                        LEFT JOIN `{$prefix}product_lang` src
                          ON src.id_product=p.id_product AND src.id_shop={$sid} AND src.id_lang={$fallbackLang}
                        LEFT JOIN `{$prefix}product_lang` pl
                          ON pl.id_product=p.id_product AND pl.id_shop={$sid} AND pl.id_lang={$lid}
                        WHERE pl.id_product IS NULL";
                $db->execute($sql);
                $result['actions'][] = array('action' => 'ensure_product_lang_lang_'.$lid, 'affected' => (int)$db->Affected_Rows());
            }

            $sql = "INSERT IGNORE INTO `{$prefix}category_product` (id_category, id_product, position)
                    SELECT p.id_category_default, p.id_product, 0
                    FROM `{$prefix}product` p
                    WHERE p.id_category_default IS NOT NULL";
            $db->execute($sql);
            $result['actions'][] = array('action' => 'ensure_category_product_default', 'affected' => (int)$db->Affected_Rows());
        }

        $result['note'] = 'Reparación aplicada. Vuelve a ejecutar Validar (preflight).';
        return $result;
    }
}
