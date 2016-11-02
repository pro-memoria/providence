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

        $this->db = new Db("", NULL, false);
    }

    /**
     * Funzione eseguita dopo la creazione di un elemento
     * @param $pa_params
     */
    public function hookSaveItem($pa_params)   {
        $row_id = $pa_params['id'];
        $table_name = $pa_params['table_name'];

       // $object = new $table_name($row_id);

       // $object->setMode(ACCESS_WRITE);
       // $object->update();

       // $this->db->commitTransaction();
        if ($table_name == "ca_objects")
            $table_name = (isset($pa_params['strumenti']) && $pa_params['strumenti']) ? "update_strumenti" : "oggetti";
        else if ($table_name == "ca_entities")
            $table_name = "entita";
        else if ($table_name == "ca_occurrences")
            $table_name = "occorrenze";
        else if ($table_name == "ca_places")
            $table_name = "luoghi";
	    else if ($table_name == "ca_collections")
            $table_name = "collezioni";
	    else if ($table_name == "ca_bundle_displays")
	       $table_name = "sommari";

        if (($table_name == "oggetti" || $table_name == "update_strumenti") && $this->opo_config->get('hierarchy') == '1')  {

            require_once(__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/hierarchy.php");
            hierarchy($row_id, $this->mongo->hierarchy, $this->db);
        }
        $comando = "php ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id";
        exec($comando, $risultato);
//        $relazioni = unserialize($risultato[0]);

//         foreach ($_POST as $key => $value) {

//             if (strpos($key, "Form_idnew") !== false && $value != "") {
//                 if (strpos($key, "P144") !== false || strpos($key, "P170") !== false || strpos($key, "P194") !== false || strpos($key, "P182") !== false || strpos($key, "P703") !== false) {

//                     $table_name = "entita";
//                     $row_id = $value;
//                     $comando = "nice -n 10 ionice -c 3 php -f ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id &";
//                     exec($comando,$risultato_ent);
// //                    $relazioni_ent = unserialize($risultato_ent[0]);
//                 }

//                 if (strpos($key, "P143") !== false || strpos($key, "P169") !== false || strpos($key, "P193") !== false || strpos($key, "P181") !== false || strpos($key, "P702") !== false) {

//                     $table_name = "oggetti";
//                     $row_id = $value;
//                     $comando = "nice -n 10 ionice -c 3 php -f ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id &";
//                     exec($comando);
//                 }

//                 if (strpos($key, "P146") !== false || strpos($key, "P172") !== false || strpos($key, "P196") !== false) {

//                     $table_name = "luoghi";
//                     $row_id = $value;
//                     $comando = "nice -n 10 ionice -c 3 php -f ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id &";
//                     exec($comando);
//                 }

//                 if (strpos($key, "P464") !== false || strpos($key, "P171") !== false || strpos($key, "P195") !== false || strpos($key, "765") !== false) {

//                     $table_name = "occorrenze";
//                     $row_id = $value;
//                     $comando = "nice -n 10 ionice -c 3 php -f ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id &";
//                     exec($comando);
//                 }

//                 if (strpos($key, "P609") !== false || strpos($key, "P173") !== false || strpos($key, "706") !== false) {

//                     $table_name = "collezioni";
//                     $row_id = $value;
//                     $comando = "nice -n 10 ionice -c 3 php -f ".__CA_APP_DIR__."/plugins/mongocane/MongoSYNC/sync.php $table_name $row_id &";
//                     exec($comando);
//                 }

// //                $cancellate = array_diff($relazioni_ent, $relazioni);
// //
// //                echo "ok";
//             }
//         }
   }

    /**
     * Funzione eseguita dopo la cancellazione di un elemento
     * @param $pa_params
     */
    public function hookDeleteItem($pa_params)  {
        $row_id = (int) $pa_params['id'];
        $table_name = $pa_params['table_name'];

        $object = new $table_name($row_id);

        $object->setMode(ACCESS_WRITE);
        $object->update();

        $this->db->commitTransaction();

        if ($table_name == "ca_objects" || $table_name == "ca_entities" || $table_name == "ca_occurrences" || $table_name == "ca_places")   {
            $this->mongo->data->remove(array("id" => $row_id, "table" => $table_name));
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
            'available' => (true)
        );
    }

}
