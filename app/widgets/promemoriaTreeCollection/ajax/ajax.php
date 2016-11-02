<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 02/02/15
 * Time: 15:46
 */

//Rinomino la variabile $_SERVER['SCRIPT_FILENAME'] perché punti alla corretta cartella per l'esecuzione di setup.php
$array                      = explode( "/", $_SERVER['SCRIPT_FILENAME'] );
$_SERVER['SCRIPT_FILENAME'] = implode( "/", array_slice( $array, 0, count( $array ) - 4 ) );

require( '../../../../setup.php' );
require_once( '../../../lib/core/Parsers/TimeExpressionParser.php' );

$o_db          = new Db( NULL, NULL, false );

$o_tep = new TimeExpressionParser();
$o_tep->setLanguage('it_IT');

function saveChildren( $id, $parent_id = "null", $pos ) {
    global $o_db;
    //foreach ( $children as $posizione => $id ) {
        $hier_collection_id = ( $parent_id == 'null' ) ?  $id : $parent_id;
        $o_db->query( "UPDATE ca_collections SET parent_id = $parent_id, hier_collection_id = $hier_object_id  WHERE collection_id = $id" );
    //}
}

//$app = AppController::getInstance();
//$req = $app->getRequest();
$operation = $_GET["operation"];
$return    = array();
switch ( $operation ) {
    case "get_children":
        $order = empty( $_GET["order"] ) ? "l.name" : $_GET["order"];
        $verso = empty( $_GET["verso"] ) ? "ASC" : $_GET["verso"];
        $user_id = empty( $_GET['user_id'] ) ? 1 : $_GET["user_id"];
        $user_groups = empty( $_GET['user_groups'] ) ? null : $_GET["user_groups"];

        //cerco il valore dell'itemid della data inserita nel file di configurazione per l'ordinamento
        $item_id   = "";
        $qr_result = $o_db->query( "select item_id FROM
			ca_list_items WHERE idno = '" . ID_DATA . "'" );
        while ( $qr_result->nextRow() ) {
            $item_id = $qr_result->get( "item_id" );
        }

        if ($user_id == 1) {
            //query per recuperare gli oggetti
            $query = "
                SELECT t.collection_id as id, t.parent_id,t.type_id as type, l.name as text
                FROM
                ca_collections t
                inner join
                ca_collection_labels l on (t.collection_id=l.collection_id)
				WHERE deleted=0 and ";
        } else {
            $query = "SELECT t.collection_id as id, t.parent_id,t.type_id as type, l.name as text
                      FROM
                      ((ca_collections t inner join ca_collection_labels l on (t.collection_id=l.collection_id) INNER JOIN ca_acl ON t.collection_id = ca_acl.row_id AND ca_acl.table_num = 67))
					where deleted=0 and (ca_acl.user_id = {$user_id} ";
            if ($user_groups)   {
                $query .= " OR ca_acl.group_id IN (". $user_groups .") )";
            } else {
                $query .= ") ";
            }
            $query .= "AND ca_acl.access IN (1, 2, 3) AND ";
        }
        if ( $_GET['id'] == "0" ) {
            $query .= "t.parent_id is null";
        } else {
            $query .= "t.parent_id = " . $_GET['id'];
        }
        $query .= " GROUP BY id ";
        $query .= " ORDER BY $order $verso";
        $qr_result = $o_db->query( $query );
        $i         = 0;
        $o_db->beginTransaction();
        while ( $qr_result->nextRow() ) {
            $nodo           = new stdClass();
            $nodo->id = $qr_result->get( "id" );
            $nodo->children = true;
            /*	if($qr_result->get("hasChildren")>0){
                $nodo->attr->order = $order;
                $nodo->attr->verso = $verso;
            }
            			$nodo->data=$qr_result->get("text");   <--originale */
            $nome     = $qr_result->get( "text" );
            $type     = $qr_result->get( "type" );
            //iquery per recuperare la descrizione del type
            //$type_desc  = "";
            //$qr_result1 = $o_db->query( "SELECT name_singular FROM ca_list_item_labels WHERE item_id = $type" );
            //$qr_result1->nextRow();
            //$type_desc = $qr_result1->get( "name_singular" );

            //#promemoria#davide
            // Inserita visualizzazione del numero provvisorio nella label del widget

            $nodo->text = trim($nome);
            //#promemoria#davide

            if (!isset($_GET['show_eye']) || $_GET['show_eye'] == "true")
			    $nodo->text = $nodo->text . " <i class='fa fa-angle-right showsummary'></i>";

            $return[]    = $nodo;
            $i ++;
        }
        $o_db->commitTransaction();
        break;
    case "save_node":
        $d = $_POST['data'];
        if ( get_magic_quotes_gpc() ) {
            $d = stripslashes( $d );
        }
        $data = json_decode( $d );
        $o_db->beginTransaction();
        saveChildren( $data->node, ($data->parent == '#' ? 'null' : $data->parent), $data->position );
        $o_db->commitTransaction();
        $return["result"] = $data;
        break;
    default:
        break;
}
echo json_encode( $return );