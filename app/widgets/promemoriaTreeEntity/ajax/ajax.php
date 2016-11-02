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

//$app = AppController::getInstance();
//$req = $app->getRequest();
$operation = $_GET["operation"];
$return    = array();
switch ( $operation ) {
    case "get_children":
        $order = empty( $_GET["order"] ) ? "l.displayname" : $_GET["order"];
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
                SELECT t.entity_id as id, t.parent_id, t.type_id as type, l.displayname as text
                FROM
                ca_entities t
                inner join
                ca_entity_labels l on (t.entity_id=l.entity_id)
				where deleted=0 and ";
        } else {
            $query = "SELECT t.entity_id as id, t.parent_id, t.type_id as type, l.displayname as text
                      FROM
                      ((ca_entities t inner join ca_entity_labels l on (t.entity_id=l.entity_id) INNER JOIN ca_acl ON t.entity_id = ca_acl.row_id AND ca_acl.table_num = 20))
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
            $nodo->text     = $qr_result->get( "text" );
            //iquery per recuperare la descrizione del type
            //$type_desc  = "";
            //$qr_result1 = $o_db->query( "SELECT name_singular FROM ca_list_item_labels WHERE item_id = $type" );
            //$qr_result1->nextRow();
            //$type_desc = $qr_result1->get( "name_singular" );

            //#promemoria#davide
            // Inserita visualizzazione del numero provvisorio nella label del widget

            //#promemoria#davide

            if (!isset($_GET['show_eye']) || $_GET['show_eye'] == "true")
			    $nodo->text = $nodo->text . " <i class='fa fa-angle-right showsummary'></i>";
            $return[]    = $nodo;
            $i ++;
        }
        $o_db->commitTransaction();
        break;
    default:
        break;
}
echo json_encode( $return );