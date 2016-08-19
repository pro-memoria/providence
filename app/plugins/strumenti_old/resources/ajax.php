<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 02/02/15
 * Time: 15:46
 */

//Rinomino la variabile $_SERVER['SCRIPT_FILENAME'] perché punti alla corretta cartella per l'esecuzione di setup.php
$array = explode("/", $_SERVER['SCRIPT_FILENAME']);
$_SERVER['SCRIPT_FILENAME'] = implode("/", array_slice($array, 0, count($array) - 4));

require('../../../../setup.php');

if (ExternalCache::contains("db", "strumenti"))  {
    $o_db = ExternalCache::fetch("db", "strumenti");
    $o_db->connect();
} else {
    $o_db = new Db("", array("host" => "p:localhost"), false);
}

function saveChildren($children, $parent_id = "null")
{
    global $o_db;
    foreach ($children as $posizione => $nodo) {
        $id = $nodo->id;
        ($parent_id == 'null') ? $hier_object_id = $id : $hier_object_id = $parent_id;
        $o_db->query("UPDATE ca_objects SET parent_id = $parent_id, hier_object_id = $hier_object_id, posizione = $posizione, ordine = ($posizione + 1)  WHERE object_id = $id");
        if (isset($nodo->children)) {
            saveChildren($nodo->children, $id);
        }
    }
}

$operation = $_GET["operation"];
$return = array();
switch ($operation) {
    case "get_children":
        $user_id = empty($_GET['user_id']) ? 1 : $_GET["user_id"];
        $user_groups = empty($_GET['user_groups']) ? null : $_GET["user_groups"];
        $o_db->query("UPDATE ca_object_labels SET name = \"Complesso archivissimo\" WHERE object_id = 1");
        if ($user_id == 1) {
            //query per recuperare gli oggetti
            $query = "
                SELECT t.object_id as id, t.parent_id,t.type_id as type, l.name as text, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                FROM ca_objects t INNER JOIN ca_object_labels l ON (t.object_id=l.object_id)
				WHERE deleted = 0 AND l.is_preferred = 1 AND ";
        } else {
            $query = "SELECT t.object_id as id, t.parent_id,t.type_id as type, t.posizione, l.name as text, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                      FROM ((ca_objects t INNER JOIN ca_object_labels l ON (t.object_id=l.object_id) INNER JOIN ca_acl ON t.object_id = ca_acl.row_id AND ca_acl.table_num = 57))
					where deleted=0 and l.is_preferred = 1 and (ca_acl.user_id = {$user_id} ";
            if ($user_groups) {
                $query .= " OR ca_acl.group_id IN (" . $user_groups . ") )";
            } else {
                $query .= ") ";
            }
            $query .= "AND ca_acl.access IN (1, 2, 3) AND ";
        }
        if ($_GET['id'] == "0") {
            $query .= "t.parent_id is null";
        } else {
            $query .= "t.parent_id = " . $_GET['id'];
        }
        $query .= " GROUP BY id ORDER BY t.ordine";
        $qr_result = $o_db->query($query);
        $i = 0;
        while ($qr_result->nextRow()) {

            $nodo = new stdClass();
            $nodo->id = $qr_result->get("id");
            $nodo->children = $qr_result->get("hasChildren") > 0;

            $nome = $qr_result->get("text");
            $type = $qr_result->get("type");
            $objectId = $qr_result->get("id");

            //Recupero tutte le informazioni in più che l'utente vuole inserire
            $tipologia = '';
            $text = '';

            /*foreach ((($metadataAttr == null) ? $opo_config->default : $metadataAttr) as $metadato) {

                switch ($metadato) {
                    case 'genreform':
                        //iquery per recuperare la descrizione del type
                        $type_desc = "";
                        $qr_result1 = $o_db->query("SELECT name_singular FROM ca_list_item_labels WHERE item_id = $type");
                        $qr_result1->nextRow();
                        $type_desc = $qr_result1->get("name_singular");
                        $text .= ' ' . $type_desc . ' ';
                        break;
                    case 'datazione':
                        $aa = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 283 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        while ($aa->nextRow()) {
                            $data = $aa->get("value");
                            $dataIniziale = ($data == 'undated') ? '' : $data;
                        }

                        if ($dataIniziale != '') {
                            $text = rtrim(trim($text), '| ') . "<i>" . $dataIniziale . "</i>" . " ";
                        }
                        break;
                    case 'num_provv':
                        $attr_num_prov = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 138 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_prov->nextRow()) {
                            $num_provv = $attr_num_prov->get('value');
                        }
                        if ($num_provv != "") {
                            $text .= "(" . $num_provv . ") ";
                        }
                        break;
                    case 'num_puntamento':
                        $attr_num_puntamento = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 139 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_puntamento->nextRow()) {
                            $num_puntamento = $attr_num_puntamento->get('value');
                        }
                        if ($num_puntamento != "") {
                            $text .= "[" . $num_puntamento . "] ";
                        }
                        break;
                    case 'num_def_numero':
                        $attr_num_def = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 1133 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_def->nextRow()) {
                            $num_def = $attr_num_def->get('value');
                        }
                        if ($num_def != "") {
                            $text .= $num_def . " ";
                        }
                        $num_def = '';
                        break;
                    default:
                        $text2 = '';
                        $sep = " | ";
                        if ($metadato == 'preferred_label') {
                            $text2 = $nome;
                            $att_result = $o_db->query("SELECT ca_attribute_values.value_longtext1 as 'value' FROM (ca_attributes INNER JOIN ca_metadata_elements on ca_metadata_elements.element_id = ca_attributes.element_id) INNER JOIN ca_attribute_values on ca_attribute_values.attribute_id = ca_attributes.attribute_id where element_code = '{$metadato}' and ca_attributes.table_num = 57 and ca_attributes.row_id = {$objectId}");
                            if ($att_result->nextRow()) {
                                $text2 = $att_result->get('value');
                            }

                            if (($metadato == 'txSegnatura' || $metadato == 'txSegnOrig' || $metadato == 'segnature_originali') && $text2 != '') {
                                $text2 = "(" . $text2 . ")";
                            }
                        }
                        if ($text2 == "") $sep = "";
                        $text .= $text2 . $sep;
                        break;
                }

            }
            if ($tipologia != '') $text = $tipologia . $text;*/
            $nodo->text = "<span>" . (rtrim(trim($nome), '|')) . "</span>";

            $return[] = $nodo;
            $i++;
        }

        $o_db->commitTransaction();
        break;
    case "save_node":
        $d = $_POST['data'];
        $data = json_decode($d);
        $o_db->beginTransaction();
        saveChildren($data);
        $o_db->commitTransaction();
        $return["result"] = "OK";
        break;
    default:
        break;
}
echo json_encode($return);
