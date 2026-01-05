<?php
class TablePlan
{
    public static function catalog($p)
    {
        return array(
            // CONFIGURACIÓN CRÍTICA PARA PS9
            "{$p}shop",
            "{$p}shop_group",
            "{$p}shop_url",
            
            "{$p}category",
            "{$p}category_lang",
            "{$p}category_shop",
            "{$p}category_group",
            "{$p}category_product",

            "{$p}manufacturer",
            "{$p}manufacturer_lang",
            "{$p}supplier",
            "{$p}supplier_lang",

            "{$p}product",
            "{$p}product_lang",
            "{$p}product_shop",

            "{$p}product_attribute",
            "{$p}product_attribute_shop",
            "{$p}product_attribute_combination",
            "{$p}product_attribute_image",

            "{$p}specific_price",

            "{$p}feature",
            "{$p}feature_lang",
            "{$p}feature_value",
            "{$p}feature_value_lang",
            "{$p}feature_product",

            "{$p}attribute_group",
            "{$p}attribute_group_lang",
            "{$p}attribute_group_shop",
            "{$p}attribute",
            "{$p}attribute_lang",
            "{$p}attribute_shop",

            "{$p}product_supplier",

            "{$p}image",
            "{$p}image_lang",
            "{$p}image_shop",

            "{$p}stock_available",

            "{$p}tag",
            "{$p}product_tag"
        );
    }

    public static function customers($p)
    {
        return array(
            "{$p}customer",
            "{$p}customer_group",
            "{$p}group",
            "{$p}group_lang",
            "{$p}address"
        );
    }

    public static function orders($p)
    {
        return array(
            "{$p}cart",
            "{$p}cart_product",
            "{$p}orders",
            "{$p}order_detail",
            "{$p}order_invoice",
            "{$p}order_invoice_payment",
            "{$p}order_payment",
            "{$p}order_slip",
            "{$p}order_slip_detail"
        );
    }

    public static function build($prefix, $opts)
    {
        $tables = array();
        if (!empty($opts['catalog'])) $tables = array_merge($tables, self::catalog($prefix));
        if (!empty($opts['customers'])) $tables = array_merge($tables, self::customers($prefix));
        if (!empty($opts['orders'])) $tables = array_merge($tables, self::orders($prefix));

        $existing = array();
        $db = Db::getInstance();
        $rows = $db->executeS("SHOW TABLES LIKE '".pSQL($prefix)."%'");
        if ($rows) foreach ($rows as $r) $existing[reset($r)] = true;

        $unique = array();
        foreach ($tables as $t) $unique[$t] = true;

        $out = array();
        foreach ($unique as $t => $_) if (isset($existing[$t])) $out[] = $t;

        return $out;
    }
}
