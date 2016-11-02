<?php
if ( ! file_exists( '../../../../setup.php' ) ) {
	print "No setup.php file found!";
	exit;
}
require( '../../../../setup.php' );
define( ID_DATA, $_GET["field"] ); //created

// connect to database
$o_db = new Db( NULL, NULL, false );


function saveChildren( $children, $parent_id = "null" ) {
	global $o_db;
	foreach ( $children as $posizione => $nodo ) {
		$id = str_replace( "node_", "", $nodo->attr->id );
		( $parent_id == 'null' ) ? $hier_object_id = $id : $hier_object_id = $parent_id;
		$o_db->query( "UPDATE ca_objects SET parent_id = $parent_id, hier_object_id = $hier_object_id, posizione = $posizione  WHERE object_id = $id" );
		if ( isset( $nodo->children ) ) {
			saveChildren( $nodo->children, $id );
		}
	}
}

//$app = AppController::getInstance();
//$req = $app->getRequest();
$operation = $_GET["operation"];
$return    = array();
switch ( $operation ) {
	case "get_fo":
		$order = empty( $_GET["order"] ) ? "posizione" : $_GET["order"];
		$verso = empty( $_GET["verso"] ) ? "ASC" : $_GET["verso"];

		//cerco il valore dell'itemid della data inserita nel file di configurazione per l'ordinamento
		$item_id   = "";
		$qr_result = $o_db->query( "select item_id FROM
                        ca_list_items WHERE     idno = '" . ID_DATA . "'" );
		while ( $qr_result->nextRow() ) {
			$item_id = $qr_result->get( "item_id" );
		}
		$query = "
SELECT t.object_id as id, t.parent_id, t.posizione, IF(l.name = '[BLANK]',l2.name,l.name) as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                                        FROM 
                                        ca_objects t 
                                        inner join 
                                        ca_object_labels l on (t.object_id=l.object_id and l.type_id is null) 
                                        left join
                                        (
                                                SELECT av.value_longtext1 as name, a.row_id
                                                FROM
                                                ca_attributes a 
                                                INNER JOIN 
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57 
                                                and av.element_id = 63
                                        ) l2 on (t.object_id = l2.row_id)
                                        left join
                                        (
                                                SELECT av.value_decimal1 as date, a.row_id
                                                FROM
                                                ca_attributes a 
                                                INNER JOIN 
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57 
                                                and av.element_id = 34
                                        ) d on (t.object_id = d.row_id)
                                        where deleted=0 and access=1 and  ";
		if ( $_GET['id'] == "0" ) {
			$query .= "t.parent_id is null";
		} else {
			$query .= "t.parent_id = " . $_GET['id'];
		}
		$query .= " ORDER BY $order $verso";
		$qr_result = $o_db->query( $query );
		$i         = 0;
		while ( $qr_result->nextRow() ) {
			if ( $order != "posizione" ) {
				$o_db->query( "UPDATE ca_objects SET posizione=$i WHERE object_id=" . $qr_result->get( "id" ) );
			}
			$nodo           = new stdClass();
			$nodo->attr     = new stdClass();
			$nodo->attr->id = "node_" . $qr_result->get( "id" );
			/*if($qr_result->get("hasChildren")>0){
					$nodo->attr->order = $order;
					$nodo->attr->verso = $verso;
			}*/
			$nodo->data  = $qr_result->get( "text" );
			$nodo->state = $qr_result->get( "hasChildren" ) > 0 ? "closed" : "";
			$return[]    = $nodo;
			$i ++;
		}
		break;
	case "get_children":
		$order = empty( $_GET["order"] ) ? "posizione" : $_GET["order"];
		$verso = empty( $_GET["verso"] ) ? "ASC" : $_GET["verso"];

		//cerco il valore dell'itemid della data inserita nel file di configurazione per l'ordinamento
		$item_id   = "";
		$qr_result = $o_db->query( "select item_id FROM
			ca_list_items WHERE	idno = '" . ID_DATA . "'" );
		while ( $qr_result->nextRow() ) {
			$item_id = $qr_result->get( "item_id" );
		}

		//query per recuperare gli oggetti
		$query = "
SELECT t.object_id as id, t.parent_id, t.posizione, IF(l.name = '[BLANK]',l2.name,l.name) as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                                        FROM 
                                        ca_objects t 
                                        inner join 
                                        ca_object_labels l on (t.object_id=l.object_id and l.type_id is null) 
                                        left join
                                        (
                                                SELECT av.value_longtext1 as name, a.row_id
                                                FROM
                                                ca_attributes a 
                                                INNER JOIN 
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57 
                                                and av.element_id = 63
                                        ) l2 on (t.object_id = l2.row_id)
                                        left join
                                        (
                                                SELECT av.value_decimal1 as date, a.row_id
                                                FROM
                                                ca_attributes a 
                                                INNER JOIN 
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57 
                                                and av.element_id = 34
                                        ) d on (t.object_id = d.row_id)
					where deleted=0 and ";
		if ( $_GET['id'] == "0" ) {
			$query .= "t.parent_id is null";
		} else {
			$query .= "t.parent_id = " . $_GET['id'];
		}
		$query .= " ORDER BY $order $verso";
		$qr_result = $o_db->query( $query );
		$i         = 0;
		$o_db->beginTransaction();
		while ( $qr_result->nextRow() ) {
			if ( $order != "posizione" ) {
				$o_db->query( "UPDATE ca_objects SET posizione=$i WHERE object_id=" . $qr_result->get( "id" ) );
			}
			$nodo           = new stdClass();
			$nodo->attr     = new stdClass();
			$nodo->attr->id = "node_" . $qr_result->get( "id" );
			/*if($qr_result->get("hasChildren")>0){
				$nodo->attr->order = $order;
				$nodo->attr->verso = $verso;
			}*/
			$nodo->data  = $qr_result->get( "text" );
			$nodo->state = $qr_result->get( "hasChildren" ) > 0 ? "closed" : "";
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
		saveChildren( $data );
		$o_db->commitTransaction();
		$return["result"] = "OK";
		break;
	default:
		break;
}
echo json_encode( $return );
?>
