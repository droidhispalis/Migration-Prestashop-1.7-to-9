<?php
/**
 * SCRIPT DE EMERGENCIA - REPARAR PRESTASHOP ROTO
 * 
 * Este script NO requiere que PrestaShop funcione.
 * Ejecutar directamente: php emergency_repair.php
 * O desde navegador: http://tumanitasia.es/emergency_repair.php
 */

// === CONFIGURACIÓN - EDITA ESTOS VALORES ===
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'TU_USUARIO_BD');  // ← CAMBIAR
define('DB_PASSWORD', 'TU_PASSWORD_BD'); // ← CAMBIAR
define('DB_NAME', 'TU_NOMBRE_BD');       // ← CAMBIAR
define('DB_PREFIX', 'ps_');              // Prefijo de tablas (normalmente ps_)
define('DOMAIN', 'tumanitasia.es');      // ← Tu dominio

// === NO EDITAR DEBAJO DE ESTA LÍNEA ===

echo "<pre>";
echo "==============================================\n";
echo "  REPARACIÓN DE EMERGENCIA PRESTASHOP\n";
echo "==============================================\n\n";

try {
    // Conectar a MySQL
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        die("❌ ERROR de conexión: " . $conn->connect_error . "\n\n" .
            "VERIFICA:\n" .
            "1. DB_USERNAME es correcto\n" .
            "2. DB_PASSWORD es correcto\n" .
            "3. DB_NAME es correcto\n");
    }
    
    echo "✅ Conexión a BD exitosa\n\n";
    
    // PASO 1: Verificar estado actual
    echo "PASO 1: Verificando estado de tablas críticas...\n";
    echo "-------------------------------------------\n";
    
    $tables = ['shop', 'shop_group', 'shop_url'];
    $empty = [];
    
    foreach ($tables as $table) {
        $fullTable = DB_PREFIX . $table;
        $result = $conn->query("SELECT COUNT(*) as total FROM `$fullTable`");
        
        if ($result) {
            $row = $result->fetch_assoc();
            $count = $row['total'];
            echo "- $table: $count registro(s)";
            
            if ($count == 0) {
                echo " ⚠️ VACÍA\n";
                $empty[] = $table;
            } else {
                echo " ✅\n";
            }
        } else {
            echo "- $table: ❌ NO EXISTE\n";
            $empty[] = $table;
        }
    }
    
    echo "\n";
    
    if (empty($empty)) {
        echo "✅ Todas las tablas críticas tienen datos.\n";
        echo "Si PrestaShop sigue roto, el problema es OTRO.\n\n";
        echo "Verifica shop_url con:\n";
        echo "SELECT * FROM " . DB_PREFIX . "shop_url WHERE id_shop = 1;\n";
        exit;
    }
    
    // PASO 2: Reparar tablas vacías
    echo "PASO 2: Reparando tablas vacías...\n";
    echo "-------------------------------------------\n";
    
    $fixed = 0;
    
    // Reparar shop_group
    if (in_array('shop_group', $empty)) {
        $sql = "INSERT INTO `" . DB_PREFIX . "shop_group` 
                (id_shop_group, name, color, share_customer, share_order, share_stock, active, deleted) 
                VALUES (1, 'Default', '', 0, 0, 0, 1, 0)";
        
        if ($conn->query($sql)) {
            echo "✅ shop_group creada\n";
            $fixed++;
        } else {
            echo "❌ Error creando shop_group: " . $conn->error . "\n";
        }
    }
    
    // Reparar shop
    if (in_array('shop', $empty)) {
        $sql = "INSERT INTO `" . DB_PREFIX . "shop` 
                (id_shop, id_shop_group, name, color, active, deleted) 
                VALUES (1, 1, 'Default Shop', '', 1, 0)";
        
        if ($conn->query($sql)) {
            echo "✅ shop creada\n";
            $fixed++;
        } else {
            echo "❌ Error creando shop: " . $conn->error . "\n";
        }
    }
    
    // Reparar shop_url (CRÍTICO)
    if (in_array('shop_url', $empty)) {
        $sql = "INSERT INTO `" . DB_PREFIX . "shop_url` 
                (id_shop, domain, domain_ssl, physical_uri, virtual_uri, main, active) 
                VALUES (1, '" . $conn->real_escape_string(DOMAIN) . "', 
                        '" . $conn->real_escape_string(DOMAIN) . "', '/', '', 1, 1)";
        
        if ($conn->query($sql)) {
            echo "✅ shop_url creada para dominio: " . DOMAIN . "\n";
            $fixed++;
        } else {
            echo "❌ Error creando shop_url: " . $conn->error . "\n";
        }
    }
    
    echo "\n";
    
    // PASO 3: Verificación final
    echo "PASO 3: Verificación final...\n";
    echo "-------------------------------------------\n";
    
    foreach ($tables as $table) {
        $fullTable = DB_PREFIX . $table;
        $result = $conn->query("SELECT COUNT(*) as total FROM `$fullTable`");
        $row = $result->fetch_assoc();
        $count = $row['total'];
        echo "- $table: $count registro(s) " . ($count > 0 ? "✅" : "❌") . "\n";
    }
    
    echo "\n";
    echo "==============================================\n";
    
    if ($fixed > 0) {
        echo "✅ REPARACIÓN COMPLETADA\n";
        echo "Se repararon $fixed tabla(s)\n\n";
        echo "IMPORTANTE:\n";
        echo "1. BORRA ESTE ARCHIVO después de usarlo (emergency_repair.php)\n";
        echo "2. Refresca tu PrestaShop: https://" . DOMAIN . "\n";
        echo "3. Si funciona, el problema está resuelto\n";
    } else {
        echo "ℹ️ No se hicieron cambios\n";
        echo "Las tablas ya tenían datos.\n";
    }
    
    echo "==============================================\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
