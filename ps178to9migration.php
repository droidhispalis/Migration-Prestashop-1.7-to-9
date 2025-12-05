<?php
/**
 * PrestaShop Migration Module 1.7.8 to 9
 *
 * @author    Migration Tools
 * @copyright 2025
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps178to9migration extends Module
{
    public function __construct()
    {
        $this->name = 'ps178to9migration';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Migration Tools';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '9.99.99');
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PS Migration 1.7 to 9');
        $this->description = $this->l('Export and import database tables for PrestaShop migration');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    public function install()
    {
        return parent::install() && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab() && parent::uninstall();
    }

    private function installTab()
    {
        // Para PrestaShop 9, insertar directamente en la BD
        if (version_compare(_PS_VERSION_, '9.0.0', '>=')) {
            return $this->installTabPS9();
        }
        
        // PrestaShop 1.7.x - mÃ©todo tradicional
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminPs178to9migration';
        $tab->name = array();
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'PS Migration 1.7.8 â†’ 9';
        }
        
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminTools');
        $tab->module = $this->name;
        return $tab->add();
    }
    
    private function installTabPS9()
    {
        try {
            // InserciÃ³n directa en BD para evitar problemas con Doctrine en PS9
            $idParent = (int)Tab::getIdFromClassName('AdminTools');
            
            // Obtener la siguiente posiciÃ³n
            $sql = 'SELECT IFNULL(MAX(position), 0) + 1 as next_position 
                    FROM `' . _DB_PREFIX_ . 'tab` 
                    WHERE id_parent = ' . (int)$idParent;
            $result = Db::getInstance()->getRow($sql);
            $position = isset($result['next_position']) ? (int)$result['next_position'] : 1;
            
            // Insertar tab
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'tab` 
                    (`id_parent`, `class_name`, `module`, `active`, `position`)
                    VALUES (' . (int)$idParent . ', "AdminPs178to9migration", "ps178to9migration", 1, ' . (int)$position . ')';
            
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
            
            $idTab = (int)Db::getInstance()->Insert_ID();
            
            if (!$idTab) {
                return false;
            }
            
            // Insertar nombres en todos los idiomas
            $langs = Db::getInstance()->executeS('SELECT id_lang FROM `' . _DB_PREFIX_ . 'lang`');
            if ($langs) {
                foreach ($langs as $lang) {
                    Db::getInstance()->insert('tab_lang', array(
                        'id_tab' => (int)$idTab,
                        'id_lang' => (int)$lang['id_lang'],
                        'name' => pSQL('PS Migration 1.7.8 â†’ 9')
                    ));
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminPs178to9migration');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPs178to9migration'));
    }
}
