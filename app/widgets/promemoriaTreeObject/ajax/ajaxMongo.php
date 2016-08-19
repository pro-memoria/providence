<?php

global $mysqli;

switch ($_POST['action']) {
	case 'hierarchy':
		$m = new MongoClient();

		$collection = $m->polo900->hierarchy;

		$elements = $collection->find();
		$data = array();
		foreach ($elements as $key => $element) {
		    $data[] = $element;
		}
		echo json_encode($data);
		break;
	case 'sync':
		$mysqli = new mysqli( 'localhost', 'dbuser', 'dbpass', 'ca_dev' );
		$mysqli->set_charset( "utf8" );
		$mysqli->autocommit( true );

		$m = new MongoClient();
		$objectDB = $m->polo900->hierarchy;

		$query = "SELECT o.object_id as 'id', o.parent_id as 'parent', o.type_id as 'type', ol.name as 'name', o.access as 'access'
		  FROM ca_objects o  INNER JOIN ca_object_labels ol ON (o.object_id = ol.object_id)
		  WHERE o.deleted = 0 AND ol.is_preferred = 1
		  ORDER BY o.posizione";

		$result = $mysqli->query( $query );

		$num = $result->num_rows;
		if ( $num > 0 ) {
			while ( $record = $result->fetch_assoc() ) {
				$num --;

				$identificativo = $record['id'];
				$parentId       = $record['parent'];

				if ( $record['name'] === "[BLANK]" ) {
					continue;
				}

				$kind = getObjectKind( $identificativo, $record['type'] );
				if ( $kind === NULL ) {
					continue;			
				}

				$parentTMP = generateParentIDPath( $parentId );
				if ($parentTMP == null) continue;

				$recordArray['id']        = $identificativo;
				$recordArray['access']    = $record['access'];
				$recordArray['name']      = stripslashes( $record['name'] );
				$recordArray['parent_id'] = ( $parentId == NULL ) ? NULL : stripslashes( ltrim( $parentTMP, '/' ) );
				$recordArray['table']     = "ca_objects";
				$recordArray['type']      = $kind;
				$recordArray['children']  = getChildren( $identificativo );
				$recordArray['isChild']   = ( count( $recordArray['children'] ) > 0 ) ? false : true;
				//$recordArray['data']      = stripslashes( getAttributeValue( 57, METADATO_DATA_RANGE, METADATO_DATA, $identificativo ) );
				//$recordArray['numero']    = stripslashes( getAttributeValue( 57, METADATO_NUMERO, METADATO_NUMERO, $identificativo ) );

				$objectDB->update(array('id' => $identificativo), array('$set' => $recordArray), array("upsert" => true));

			}//While
		}
		break;
	
	default:
		# code...
		break;
}


function generateParentIDPath( $parentId ) {
	global $mysqli;
	if ( $parentId === NULL ) {
		return "";
	}
	$query     = "SELECT parent_id, deleted FROM ca_objects WHERE object_id = " . $parentId;
	$result    = $mysqli->query( $query );
	$tmp       = $result->fetch_assoc();

	if ($tmp['deleted'] == '1')	{
		return null;
	}
	$concat = generateParentIDPath( $tmp['parent_id'] );
	if ($concat === NULL){
		return null;
	} else {
		return  $concat . "/" . $parentId;
	}
}

function getChildren( $id ) {
	global $mysqli;

	$children = array();
	$query    = 'SELECT ca_objects.object_id as "id", ca_object_labels.name as "name", ca_objects.type_id as "type", ca_objects.access as "access"
			FROM ca_objects INNER JOIN ca_object_labels ON (ca_objects.object_id = ca_object_labels.object_id)
			WHERE ca_objects.parent_id = ' . $id . ' AND ca_object_labels.is_preferred = 1 AND ca_objects.deleted = 0 ORDER BY ca_objects.posizione';

	$result = $mysqli->query( $query );

	while ( $row = $result->fetch_assoc() ) {
		$count   = "SELECT COUNT(*) as 'num'
						FROM ca_objects
						WHERE ca_objects.parent_id = {$row['id']} AND ca_objects.deleted = 0";
		$r       = $mysqli->query( $count );
		$num     = $r->fetch_assoc();
		$isChild = ( $num['num'] > 0 ) ? false : true;

		//$data   = stripslashes( getAttributeValue( 57, METADATO_DATA_RANGE, METADATO_DATA, $row['id'] ) );
		//$numero = stripslashes( getAttributeValue( 57, METADATO_NUMERO, METADATO_NUMERO, $row['id'] ) );
		$kind   = getObjectKind( $row['id'], $row['type'] );
		if ( $kind === NULL ) {
			continue;
		}

		$children[] = array(
			"id"      => $row['id'],
			"access"  => $row['access'],
			"name"    => stripslashes( $row['name'] ),
			"type"    => $kind,
			"table"   => "ca_objects",
			"isChild" => $isChild,
			//"data"    => $data,
			//"numero"  => $numero
		);
	}

	return ( count( $children ) > 0 ) ? $children : NULL;
}

function getObjectKind( $object_id, $type_id ) {
	global $mysqli;

	$query = <<<QUERY
		SELECT li.idno as 'r'
		FROM ca_objects o inner join (ca_attributes a inner join (ca_attribute_values av inner join ca_list_items li on (av.item_id = li.item_id)) on (a.attribute_id = av.attribute_id)) ON (a.row_id = o.object_id) 
		WHERE o.object_id = {$object_id} and a.element_id IN (327, 245)
QUERY;

	$result = $mysqli->query( $query );

	if ($result->num_rows > 0)	{
		$row = $result->fetch_assoc();
		return ucfirst($row['r']);
	} else {
		$query = <<<QUERY
		SELECT `name_sort` as 'name'
		FROM (`ca_list_items` INNER JOIN `ca_lists` ON (`ca_lists`.`list_id` = `ca_list_items`.`list_id`))
		INNER JOIN `ca_list_item_labels`
		ON (`ca_list_items`.`item_id` = `ca_list_item_labels`.`item_id`)
		WHERE `ca_list_items`.`parent_id` IS NOT NULL AND `ca_lists`.`list_code` = 'object_types' AND `ca_list_items`.`item_id` = {$type_id}
			AND `ca_list_items`.`deleted` = 0 AND `ca_list_items`.`is_enabled` = 1
QUERY;
		
		$result = $mysqli->query($query);
		if ($result->num_rows > 0)	{
			$row = $result->fetch_assoc();
			return ucfirst($row['name']);
		}

	}

	return 'Errore';
}

function getAttributeValue( $tabNum, $metadato, $container, $objectId ) {
	global $mysqli;

	$query = "SELECT value_longtext1 as 'value' FROM ca_attribute_values WHERE attribute_id = (
		SELECT attribute_id FROM ca_attributes WHERE table_num = {$tabNum} AND row_id = {$objectId} AND element_id = (
		SELECT element_id FROM ca_metadata_elements WHERE TRIM(UPPER(element_code)) = TRIM(UPPER('{$container}'))))
		 AND element_id = (SELECT element_id FROM ca_metadata_elements WHERE TRIM(UPPER(element_code)) = TRIM(UPPER('{$metadato}')))";

	$result = $mysqli->query( $query );
	if ( $result->num_rows > 0 ) {
		$tmp = $result->fetch_assoc();

		return $tmp['value'];
	}

	return "";
}
