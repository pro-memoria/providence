<?php

/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 14/04/16
 * Time: 11:10
 */

require_once(__CA_LIB_DIR__ . "/ca/ApplicationPluginManager.php");
require_once(__CA_LIB_DIR__ . "/core/View.php");
require_once(__CA_LIB_DIR__ . "/core/Db.php");
require_once(__CA_LIB_DIR__ . "/core/Db/Transaction.php");
require_once(__CA_LIB_DIR__ . "/core/Parsers/TimeExpressionParser.php");


class StrumentiController extends ActionController {
    protected $plugin_path;
    protected $plugin_url;

    protected $opo_config;
    protected $ausiliarView;

    protected $transiction;
    protected $transaction_file;
    protected $o_db;

    protected $template;

    public function __construct(&$po_request, &$po_response, $pa_view_paths = NULL) {
        parent::__construct($po_request, $po_response, $pa_view_paths);
        $this->plugin_path = __CA_APP_DIR__ . '/plugins/strumenti/';
        $this->plugin_url = __CA_URL_ROOT__;
        $this->ausiliarView = new View($po_request);

        AssetLoadManager::register('panel');

        //Recupero il file di configurazione del plugin
        $this->opo_config = Configuration::load($this->plugin_path . '/conf/strumenti.conf');

        //Creo una connessione al database
        $this->o_db = new Db("", NULL, false);

        // Generazione della transizione per tutta la durata dell'utilizzo del plugin
        $this->transaction_file = $this->plugin_path . '/conf/transaction.json';
        $this->transiction = null;
        if (file_exists($this->transaction_file)) {
            $this->transiction = json_decode(file_get_contents($this->transaction_file), true);
        }

        if (!$this->transiction) {
            $this->transiction = array('INSERT' => array(), 'UPDATE' => array(), 'DELETE' => array());
            $this->saveTrans();
        }

        // Gestione del template per l'ordinatore
        $this->template = json_decode(file_get_contents($this->plugin_path . "/conf/template.json"), true);
        if ($this->template == null || empty($this->template)) {
            $this->template = $this->opo_config->get('menu');
            file_put_contents($this->plugin_path . "/conf/template.json", json_encode($this->template));
        }
    }

    public function Index($pa_values = NULL, $pa_options = NULL) {
        require_once(__CA_MODELS_DIR__ . "/ca_bundle_displays.php");

        AssetLoadManager::register('panel');
        AssetLoadManager::register('treejs');

        //Setto delle variabili da passare alla view dell'albero
        $this->ausiliarView->setVar('plugin_url', $this->plugin_url);
        $this->ausiliarView->setVar('root', __CA_URL_ROOT__ . "/index.php/");
        $this->ausiliarView->setVar('stampa_inventario_restriction', $this->opo_config->get('enable_stampa_inventario'));
        $this->ausiliarView->setVar('refinisci_restriction', $this->opo_config->get('enable_rifinisci'));
        $albero = $this->ausiliarView->render($this->plugin_path . 'views/albero.php');

        $info = $this->ausiliarView->render($this->plugin_path . 'views/info_html.php');

        // Prendo gli screen disponibili per il summary
        $t_display = new ca_bundle_displays();
        $va_displays = $t_display->getBundleDisplays(array('table' => 57, 'user_id' => $this->request->getUserID(), 'access' => __CA_BUNDLE_DISPLAY_READ_ACCESS__));
        $screen = array();
        foreach ($va_displays as $scr)    {
            $scr = reset($scr);
            $screen[$scr['display_id']] = $scr['name'];
        }

        // Modale stampa inventario
        $this->ausiliarView->setVar('stampa_inventario_types', $this->opo_config->get('stampa_inventario_types'));
        $this->ausiliarView->setVar('screen', $screen);
        $modal_inventario = $this->ausiliarView->render($this->plugin_path . 'views/stampa_inventario_modal_html.php');

        // Modale ordinatore
        $this->ausiliarView->setVar('menu_ordinatore', $this->opo_config->get('menu'));
        $this->ausiliarView->setVar('template_ordinatore', $this->template);
        $modal_ordinatore = $this->ausiliarView->render($this->plugin_path . 'views/ordinatore_modal_html.php');

        // Modale rinumeratore
        $modal_rinumeratore = $this->ausiliarView->render($this->plugin_path . 'views/rinumeratore_modal_html.php');

        $this->view->setVar('albero', $albero);
        $this->view->setVar('info', $info);

        $this->view->setVar('modal_inventario', $modal_inventario);
        $this->view->setVar('modal_ordinatore', $modal_ordinatore);
        $this->view->setVar('modal_rinumeratore', $modal_rinumeratore);

        $this->render('index_html.php');
    }

    /**
     * Metodo che ritorna i nodi dell'albero
     * @param null $pa_values
     * @param null $pa_options
     */
    public function AllNode($pa_values = NULL, $pa_options = NULL) {
        $user = $this->getRequest()->getUser();

        $transazione = new Transaction($this->o_db, MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
        $this->runTransaction($transazione, false, true, true);

        //query per recuperare gli oggetti
        if ($user->canDoAction('is_administrator')) {
            $query = "
              SELECT t.object_id as id, t.parent_id,t.type_id as type, l.name as text, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
              FROM ca_objects t INNER JOIN ca_object_labels l ON (t.object_id=l.object_id)
    		  WHERE deleted = 0 AND l.is_preferred = 1 AND ";
        } else {
            $query = "
              SELECT t.object_id as id, t.parent_id,t.type_id as type, l.name as text, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
              FROM (ca_objects t INNER JOIN ca_object_labels l ON (t.object_id=l.object_id)) INNER JOIN ca_acl ON (ca_acl.row_id = t.object_id AND ca_acl.table_num = 57)
              WHERE deleted = 0 AND (ca_acl.user_id = {$user->getUserID()} ";
              $user_groups = $user->getUserGroups();
                if (!empty($user_groups)) {
                  $query .= "OR ca_acl.group_id IN (". implode(",", array_keys($user_groups)) .")";
                }
              $query .= ") AND l.is_preferred = 1 AND ";
        }
        if ($_POST['id'] == "0") {
            $query .= "t.parent_id is null";
        } else {
            $query .= "t.parent_id = " . $_POST['id'];
        }
        $query .= " ORDER BY t.posizione, t.ordine";
        file_put_contents("/var/www/polo900-dev.promemoriagroup.com/backend_csi/app/widgets/promemoriaTreeObject/ajax/debug.txt", $query);
        $qr_result = $this->o_db->query($query);
        $i = 0;
        $icons = $this->opo_config->get('icons');
        while ($qr_result->nextRow()) {

            $nodo = new stdClass();
            $nodo->id = $qr_result->get("id");
            $nodo->children = $qr_result->get("hasChildren") > 0;

            $nome = $qr_result->get("text");
            $type = $qr_result->get("type");
            $objectId = $qr_result->get("id");

            $nodo->icon = $icons[$type];

            //Recupero tutte le informazioni in più che l'utente vuole inserire
            $tipologia = '';
            $text = $nome;

            $attr_num_def = $this->o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 1133 AND a.table_num = 57 AND a.row_id = {$objectId}");
            if ($attr_num_def->nextRow())  {
                $num_def = $attr_num_def->get('value');
            }
            if ($num_def != "")   {
                $text = $num_def . " " . $text;
            }
            $num_def = '';

            $aa = $this->o_db->query( "SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE (v.element_id = 283 OR v.element_id = 39) AND a.table_num = 57 AND a.row_id = {$objectId}" );
            if ( $aa->nextRow() ) {
                $data = $aa->get( "value" );
                $dataIniziale = ($data == 'undated') ? '' : $data;
            }

            if ($dataIniziale != '')    {
                $text = $text . ",<i>" . $dataIniziale . "</i>" . " ";
            }

            $nodo->text = "<span data-type='{$type}'>" . (rtrim(trim($text), '|')) . "</span>";

            $return[] = $nodo;
            $i++;
        }

        $transazione->rollbackTransaction();

        echo json_encode($return);
    }

    public function Move($pa_values = NULL, $pa_options = NULL) {
        $this->saveChildren(json_decode($_POST['data']));
        $this->saveTrans();
    }

    public function Elim($pa_values = NULL, $pa_options = NULL) {
        if (isset($_POST['id'])) {
            $this->transiction['DELETE'] = array_unique(array_merge($this->transiction['DELETE'], $_POST['id']));
        }

        $this->saveTrans();
    }

    public function Paste($pa_values = NULL, $pa_options = NULL) {
        $ok = false;
        if (isset($_POST['parent']) && $_POST['parent'] != "") {
            $parent = $_POST['parent'];
            $ok = true;
        }
        if (isset($_POST['node']) && !empty($_POST['node'])) {
            $node = $_POST['node'];
        } else {
            $ok = false;
        }

        if ($ok) {
            // Recupero la posizione dell'ultimo figlio di $parent
            $maxChild = 1;
            $maxChild = "SELECT MAX(ordine) as 'max' FROM ca_objects WHERE parent_id = " + $parent;
            $qr_result = $this->o_db->query($maxChild);
            if ($qr_result && $qr_result->numRows() > 0) {
                while ($qr_result->nextRow()) {
                    $maxChild = $qr_result->get('max');
                }
            }

            foreach ($node as $id) {
                $this->transiction['UPDATE'][$id]['intr'] = array('parent_id' => $parent, 'ordine' => $maxChild);
                $maxChild++;
            }

            $this->saveTrans();
            return "ok";
        }
        return "-1";
    }

    public function Inventary($pa_values = NULL, $pa_options = NULL)    {
        $error = null;
        if (!isset($_POST) || $_POST['_formName'] != 'caInventary') {
            $error = "Informazioni del form sbagliato";
        } else {
            if (!isset($_POST['object']))    {
                $error = "Non è stato selezionato nussun elemento";
            } else {
                require_once(__CA_APP_DIR__ . '/plugins/strumenti/tools/StampaInventario/StampaInventario.php');
                $object_id = $_POST['object'];

                unset($_POST['_formName'], $_POST['object']);

                $computate = array();
                foreach ($_POST as $key => $screen_id)  {
                    if ($screen_id != "") {
                        $obj_type = explode("#", $key);
                        $computate[str_replace("_", " ", $obj_type[1])] = $screen_id;
                    }
                }


                $transazione = new Transaction($this->o_db, MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
                $this->runTransaction($transazione, false, true, true);

                // Lancia l'esecuizione
                $stampa_inventario = new StampaInventario($this->request, $this->opo_config);
                $file = $stampa_inventario->run($object_id, $computate);
                $transazione->rollbackTransaction();

                header('Pragma: no-cache');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Disposition: attachment; filename='. basename($file) .';');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: '.filesize($file));

                readfile($file);
                unlink($file);

            }
        }
    }

    public function Ordinatore($pa_values = NULL, $pa_options = NULL) {
        $options = $_POST;
        if ($options['_formName'] == 'caOrdinatore' && isset($options['object']))    {

            if ($options['function'] == 'caSave')   {
                $menu = $this->opo_config->get('menu');
                unset($options['_formName'], $options['object'], $options['function']);
                $keys = array_keys($options);
                foreach ($menu as &$types)   {
                    for ($i = 0; $i < count($types); $i++) {
                       // $type_key = str_replace(" ", "_", $types[array_keys($types)[$i]]['name']);
			$type_key = $types[array_keys($types)[$i]]['name'];
                        if (!in_array($type_key, $keys)) {
                            array_splice($types, $i, 1);
                            $i--;
                        } else {
                            if (is_array($options[$type_key]))  {
                                for ($j = 0; $j < count($types[array_keys($types)[$i]]['metadati']); $j++) {
                                    if (!in_array(array_keys($types[array_keys($types)[$i]]['metadati'])[$j], array_keys($options[$type_key]))) {
                                        array_splice($types[array_keys($types)[$i]]['metadati'], $j, 1);
                                        $j--;
                                    }
                                }
                            }
                        }
                    }
                }

                $this->template = $menu;
                file_put_contents($this->plugin_path . "/conf/template.json", json_encode($this->template));
            } else if ($options['function'] == 'caRipristina') {
                $this->template = $this->opo_config->get('menu');
                file_put_contents($this->plugin_path . "/conf/template.json", json_encode($this->template));
            } else {


                require_once(__CA_APP_DIR__ . '/plugins/strumenti/tools/Ordinatore/Ordinatore.php');
                $object_id = $options['object'];
                unset($options['_formName'], $options['object'], $options['function']);
                $toSort = array();
                if (isset($options['TwoType'])) {
                    $toSort['all'] = $options['TwoType'];
                } else {
                    foreach ($options as $type => $opt) {
                        if (substr( $type, -5 ) == "3Type") {
    //                        $toSort[str_replace("_", " ", substr( $type, 0, -5 ))] = $opt;
			      $toSort[substr( $type, 0, -5 )] = $opt;
                        } else {
                            if (is_array($opt)) {
                                $toSort[$type] = $opt;
                            }
                        }
                    }
                }
                $ordinatore = new Ordinatore($toSort);
                $changeItem = $ordinatore->run($object_id, $toSort);
                foreach ($changeItem as $id => $item)   {
                    $this->transiction['UPDATE'][$id]['intr'] = $item;
                }

                $this->saveTrans();
            }

        }
        $this->redirect('Index');
    }

    public function Rinumeratore($pa_values = NULL, $pa_options = NULL) {
        $options = $_POST;
        if ($options['_formName'] == 'caRinumera' && isset($options['object'])) {
            require_once(__CA_APP_DIR__ . '/plugins/strumenti/tools/Rinumeratore/Rinumeratore.php');

            $idPartenza = $options['object'];
            $numeroPartenza = intval($options['start']);
            $prefisso = $options['prefisso'];

            unset($options['_formName'], $options['object'], $options['start'], $options['prefisso']);

            $params = array();
            foreach ($options as $key => $value)    {
                $params[str_replace("_", " ", $key)] = $value;
            }

            $rinumeratore = new Rinumeratore($params);

            $changeItem = $rinumeratore->run($idPartenza, $numeroPartenza, $prefisso);
            foreach ($changeItem as $id => $item)   {
                $this->transiction['UPDATE'][$id]['attr'] = $item;
            }
            $this->saveTrans();
        }
        $this->redirect('Index');
    }

    public function Rifinisci($pa_values = NULL, $pa_options = NULL) {
        if (!isset($_POST) || !isset($_POST['id']))  {
            return "Errore";
        } else {
            $id = reset($_POST['id']);
            $accumulatore = array(
                'inizio' => "99999999999999",
                'fine' => "-999999999999999",
                'tipologia' => array()
            );
            $accumulatore = $this->rifinisciRicorsiva($id, $accumulatore, $id);

            echo $this->formatAccumulatore($id, $accumulatore);
        }
    }

    public function Save($pa_values = NULL, $pa_options = NULL) {

        $transazione = new Transaction($this->o_db, MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
        $this->runTransaction($transazione, false, true, true);
        $transazione->commitTransaction();

        // Resetto la transazione
        $this->transiction = null;
        $this->saveTrans();
    }

    public function Undo($pa_values = NULL, $pa_options = NULL) {
        // Resetto la transazione
        $this->transiction = null;
        $this->saveTrans();
        // $this->redirect('Index');
    }

    public function Leave($pa_values = NULL, $pa_options = NULL) {
        $this->transiction = null;
        $this->saveTrans();
    }
    /**
     * Sets up view variables for upper-left-hand info panel (aka. "inspector"). Actual rendering is performed by
     * calling sub-class.
     *
     * @param array $pa_parameters Array of parameters as specified in navigation.conf, including primary key value and
     *                             type_id
     */
    public function info($pa_parameters) {
        $o_dm = Datamodel::load();

        //$this->view->setVar( 'screen', $this->request->getActionExtra() );                        // name of screen
        //$this->view->setVar( 'result_context', $this->getResultContext() );

        return $this->render('info_html.php', true);
    }

    # ------------------------------------------------------------------
    # Sidebar info handler
    # ------------------------------------------------------------------

    /**
     * Initializes editor view with core set of values, loads model with record to be edited and selects user interface
     * to use.
     *
     * @param $pa_options Array of options. Supported options are:
     *                    ui = The ui_id or editor_code value for the user interface to use. If omitted the default
     *                    user interface is used.
     */
    protected function _initView($pa_options = NULL) {

    }

    private function saveChildren($children, $parent_id = null) {
        $o_db = $this->o_db;
        foreach ($children as $posizione => $nodo) {
            $id = $nodo->id;
            $this->transiction['UPDATE'][$id]['intr'] = array('parent_id' => $parent_id, 'ordine' => $posizione + 1);
            if (isset($nodo->children)) {
                $this->saveChildren($nodo->children, $id);
            }
        }
    }

    protected function saveTrans() {
        file_put_contents($this->transaction_file, json_encode($this->transiction));
    }

    protected function runTransaction(&$transaction, $insert=false, $update=false, $delete=false) {
        require_once(__CA_MODELS_DIR__ . "/ca_objects.php");
        $map = $this->opo_config->get('mappatura_metadati');
        // Aggiorno per l'inserimento
        if ($insert) {
            // $inserimenti = $this->transiction['INSERT'];
        }

        // Aggiorno oggetti
        if ($update) {
            $aggiornamenti = $this->transiction['UPDATE'];
            foreach ($aggiornamenti as $id => $info) {
                $object = new ca_objects($id);
                $object->setTransaction($transaction);

                // Aggiorno dati intrinseci
                if (isset($info['intr'])) {
                    $object->set($info['intr']);
                }

                // Inserisco nuovi attributi
                if (isset($info['attr'])) {
                    // Consistenza
                    if (isset($info['attr']['consistenza'])) {
                        $object->replaceAttribute(array($map['consistenza'] => $info['attr']['consistenza']), $map['consistenza']);
                    }
                    if (isset($info['attr']['numero_def'])) {
                        $object->replaceAttribute(array($map['numero_dev'] => $info['attr']['numero_def']), $map['num_def']);
                    }
                    if (isset($info['attr']['data']))  {
                        $object->replaceAttribute(array($map['datadisplay'] => $info['attr']['data']['date_display'], $map['datarange'] => $info['attr']['data']['data_range'], $map['notedata'] => "Datazione calcolata"), $map['data']);
                    }
                }

                $object->setMode(ACCESS_WRITE);
                $object->update();
            }
        }

        // Elimino oggetti
        if ($delete) {
            $cancellati = $this->transiction['DELETE'];
            foreach ($cancellati as $id) {
                $object = new ca_objects($id);
                $object->setTransaction($transaction);
                $object->setMode(ACCESS_WRITE);
                $object->delete();
            }
        }
    }

    private function rifinisciRicorsiva($id, $accumulatore, $root) {
        $object = new ca_objects($id);
        if ($object == null) {
            return $accumulatore;
        }

        $children = $object->getHierarchyChildren(null, array('idsOnly' => true));

        // Qui dovrei aver accumulato tutte le informazioni dei figli
        foreach ($children as $child) {
            $accumulatore = $this->rifinisciRicorsiva($child, $accumulatore, $root);
        }

        if ($id != $root)   {
            // Recupero le informazioni dell'oggetto
            $map = $this->opo_config->get('mappatura_metadati');
            $tipologia = $object->getTypeName();
            if ($tipologia != null && $tipologia != "" && $tipologia != " = ") {
                if (isset($accumulatore['tipologia'][$tipologia])) {
                    $accumulatore['tipologia'][$tipologia]++;
                } else {
                    $accumulatore['tipologia'][$tipologia] = 1;
                }
            }

            // if ($dataRange != null && $dataRange != "") {
            //     $timepars = new TimeExpressionParser();
            //     $tmp = explode("#", $dataRange);
            //     $parse = $timepars->parseDate($tmp[0]);

            //     $inizio = $parse['start'];
            //     $fine = $parse['end'];

            //     if ((int)$accumulatore['inizio'] > (int)$inizio) {
            //         $accumulatore['inizio'] = $inizio;
            //     }
            //     if ((int)$accumulatore['fine'] < (int)$fine) {
            //         $accumulatore['fine'] = $fine;
            //     }
            // }
        }
        
        if (count($children) > 0)   {
            $this->formatAccumulatore($id, $accumulatore);
        }

        return $accumulatore;
    }

    private function formatAccumulatore($id, $accumulatore)  {
        $consistenza = "";
        foreach ($accumulatore['tipologia'] as $tipo => $count) {
            $consistenza .= $tipo . ": " . $count . ", ";
        }
        $this->transiction['UPDATE'][$id]['attr']['consistenza'] = $consistenza;
	$this->saveTrans();
        // $data = new TimeExpressionParser($accumulatore['inizio'] . " - " . $accumulatore['fine']);
        // $testo = $data->getText();
        // if ($testo == "")   {
        //     $this->saveTrans();
        //     return "<p><b>Consistenza:</b> " . $consistenza . "</p>";
        // } else {
        //     $this->transiction['UPDATE'][$id]['attr']['data']['date_display'] = $testo;
        //     $this->transiction['UPDATE'][$id]['attr']['data']['data_range'] = $testo;
        //     $this->saveTrans();
        // }

        // return "<p><b>Consistenza:</b> " . $consistenza . "</p><p><b>Estremi cronologici:</b> " . $testo ."</p>";
        return "<p><b>Consistenza:</b> " . $consistenza . "</p>";
    }
}
