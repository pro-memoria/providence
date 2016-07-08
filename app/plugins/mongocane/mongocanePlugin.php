<?php

/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 29/04/16
 * Time: 17:17
 */

require_once(__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php");
require_once(__CA_LIB_DIR__."/core/Db.php");


class mongocanePlugin extends BaseApplicationPlugin {

    protected $opo_config;
    protected $db;
    protected $currentCollections;

    static function getRoleActionList() {
        return array();
    }

    public function __construct($ps_plugin_path) {
        $this->description = _t('Implement sync of the CA db width mongodb');
        parent::__construct();

        $this->opo_config = Configuration::load($ps_plugin_path . '/conf/mongocane.conf');

        try {
            $mongodb = new MongoClient($this->opo_config->get('mongodb_host'));
            $this->mongo = $mongodb->selectDB($this->opo_config->get('mongodb_dbname'));
            if ($this->mongo == null)   {
                return;
            }
        } catch (MongoConnectionException $e)  {
            return;
        }

        $this->currentCollections = $this->mongo->getCollectionNames();

        $collections = $this->opo_config->get('tables');
        $tableToCreate = array();
        foreach ($collections as $table => $create) {
            if ($create && !in_array($table, $this->currentCollections))    {
                $coll = $this->mongo->createCollection($table);
                $coll->createIndex(array('$**' => "text"), array("name" => "obj_full_text"));
            }
        }

        if ($this->opo_config->get('hierarchy') == '1') {
            if (!in_array("hierarchy", $this->currentCollections))    {
             $coll = $this->mongo->createCollection("hierarchy");
             $coll->createIndex(array('parent_id' => "text"), array("name" => "parent_id_text"));   
            }
        }

        $this->db = new Db("", null, false);
    }

    /**
     * Funzione eseguita dopo la creazione di un elemento
     * @param $pa_params
     */
    public function hookSaveItem($pa_params)   {
        $row_id = $pa_params['id'];
        $table_name = $pa_params['table_name'];

        if (in_array($table_name, $this->currentCollections))   {
            $table_name($row_id, $this->mongo->$table_name, $this->db);
            if ($pa_params['table_num'] == 57 && $this->opo_config->get('hierarchy') == '1')  {
                require_once(__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/hierarchy.php");
                hierarchy($row_id, $this->mongo->hierarchy, $this->db);
            }
        }
    }

    /**
     * Funzione eseguita dopo la cancellazione di un elemento
     * @param $pa_params
     */
    public function hookDeleteItem($pa_params)  {
        $row_id = (int) $pa_params['id'];
        $table_name = $pa_params['table_name'];
        if (in_array($table_name, $this->currentCollections))   {
            $this->mongo->$table_name->remove(array("id" => $row_id));
            if ($pa_params['table_num'] == 57 && $this->opo_config->get('hierarchy') == '1')  {
                require_once(__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/hierarchy.php");
                delete_hierarchy($row_id, $this->mongo->hierarchy, $this->db);
            }   
        }
    }

    # -------------------------------------------------------
    /**
     * Override checkStatus() to return true - the twitterPlugin plugin always initializes ok
     */
    public function checkStatus() {
        return array(
            'description' => $this->getDescription(),
            'errors' => array(),
            'warnings' => array(),
            'available' => ((bool)$this->opo_config->get('enable'))
        );
    }

}
