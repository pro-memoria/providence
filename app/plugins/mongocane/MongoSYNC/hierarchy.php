<?php
/**
 * Genera l'alberatura del Polo
 * 
 * 2015/10/30
 * 
 * @version 0.1
 * @author Luca Montanera <luca.montanera@promemoriagroup.com>
 * @copyright Promemoria
 */
global $mysql, $mongo;

define('METADATO_DATA', 'date');
define('METADATO_DATA_RANGE', 'date_display');
define('METADATO_NUMERAZIONE', 'numerazione_definitiva');
define('METADATO_NUMERO', 'final_number');

function hierarchy($id, $hierarchy, $db) {
 global $mongo, $mysql;
 $mongo = $hierarchy;
 $mysql = $db;

 $query = "SELECT o.object_id as 'id', o.parent_id as 'parent', o.type_id as 'type', ol.name as 'name', o.access as 'access'
   FROM ca_objects o  INNER JOIN ca_object_labels ol ON (o.object_id = ol.object_id)
   WHERE o.deleted = 0 AND ol.is_preferred = 1 AND o.object_id = {$id}
   ORDER BY o.ordine, o.posizione";

 $result = $mysql->query($query);

 while ($result->nextRow()) {

  $identificativo = (int) $result->get('id');
  $parentId       = $result->get('parent');

  if ( $result->get('name') === "[BLANK]" ) {
   continue;
  }

  // Aggiorno anche il padre
  if ($parentId != NULL) {
   hierarchy($parentId, $hierarchy, $mysql);
  }

  $kind = getObjectKind( $identificativo, $result->get('type') );
  if ( $kind === NULL ) {
   continue;   
  }

  $parentTMP = generateParentIDPath( $parentId );
  if ($parentTMP === NULL){ continue;}

  $recordArray['id']        = $identificativo;
  $recordArray['access']    = $result->get('access');
  $recordArray['name']      = $result->get('name');
  $recordArray['parent_id'] = ( $parentId == NULL ) ? NULL : stripslashes( ltrim( $parentTMP, '/' ) );
  $recordArray['table']     = "ca_objects";
  $recordArray['type']      = $kind;
  $recordArray['children']  = getChildren( $identificativo );
  $recordArray['isChild']   = count( $recordArray['children'] ) <= 0;
  $recordArray['hasMedia']  = hasMedia($identificativo);

  $hierarchy->update(array('id' => $identificativo), array('$set' => $recordArray), array("upsert" => true));
 }
}
function generateParentIDPath( $parentId ) {
 global $mysql;
 if ( $parentId === NULL ) {
  return "";
 }
 $query     = "SELECT parent_id, deleted FROM ca_objects WHERE object_id = " . $parentId;
 $result    = $mysql->query( $query );
 $result->nextRow();

 if ($result->get('deleted') == '1') {
  return null;
 }
 $concat = generateParentIDPath( $result->get('parent_id') );
 if ($concat === NULL){
  return null;
 } else {
  return  $concat . "/" . $parentId;
 }
}

function getChildren( $id ) {
 global $mysql;

 $children = array();
 $query    = 'SELECT ca_objects.object_id as "id", ca_object_labels.name as "name", ca_objects.type_id as "type", ca_objects.access as "access"
   FROM ca_objects INNER JOIN ca_object_labels ON (ca_objects.object_id = ca_object_labels.object_id)
   WHERE ca_objects.parent_id = ' . $id . ' AND ca_object_labels.is_preferred = 1 AND ca_objects.deleted = 0 ORDER BY ca_objects.ordine, ca_objects.posizione';

 $result = $mysql->query( $query );

 while ( $result->nextRow() ) {
  $isChild = !hasChildren( $result->get('id') );

  $data   = stripslashes( getAttributeValue( 57, METADATO_DATA_RANGE, METADATO_DATA, $result->get('id') ) );
  $numero = stripslashes( getAttributeValue( 57, METADATO_NUMERO, METADATO_NUMERO, $result->get('id') ) );
  $kind   = getObjectKind( $result->get('id'), $result->get('type') );
  if ( $kind === NULL ) {
   continue;
  }

  $children[] = array(
   "id"      => $result->get('id'),
   "access"  => $result->get('access'),
   "name"    => $result->get('name'),
   "type"    => $kind,
   "table"   => "ca_objects",
   "isChild" => $isChild,
   "data"    => $data,
   "numero"  => $numero,
   "hasMedia" => hasMedia($result->get('id'))
  );
 }

 return ( count( $children ) > 0 ) ? $children : NULL;
}


function hasChildren( $id ) {
 global $mysql;

 $count   = "SELECT COUNT(*) as 'num'
     FROM ca_objects
     WHERE ca_objects.parent_id = {$id} AND ca_objects.deleted = 0";
     
 $r       = $mysql->query( $count );
 $r->nextRow();
 
 return ( $r->get('num') > 0 ) ? true : false;
}

function getObjectKind( $object_id, $type_id ) {
 global $mysql;

 $query = <<<QUERY
  SELECT li.idno as 'r'
  FROM ca_objects o inner join (ca_attributes a inner join (ca_attribute_values av inner join ca_list_items li on (av.item_id = li.item_id)) on (a.attribute_id = av.attribute_id)) ON (a.row_id = o.object_id) 
  WHERE o.object_id = {$object_id} and a.element_id IN (327, 245)
QUERY;

 $result = $mysql->query( $query );

 if ($result->numRows() > 0) {
  $result->nextRow();
  return ucfirst($result->get('r'));
 } else {
  $query = <<<QUERY
  SELECT `name_sort` as 'name'
  FROM (`ca_list_items` INNER JOIN `ca_lists` ON (`ca_lists`.`list_id` = `ca_list_items`.`list_id`))
  INNER JOIN `ca_list_item_labels`
  ON (`ca_list_items`.`item_id` = `ca_list_item_labels`.`item_id`)
  WHERE `ca_list_items`.`parent_id` IS NOT NULL AND `ca_lists`.`list_code` = 'object_types' AND `ca_list_items`.`item_id` = {$type_id}
   AND `ca_list_items`.`deleted` = 0 AND `ca_list_items`.`is_enabled` = 1
QUERY;
  
  $result = $mysql->query($query);
  if ($result->numRows() > 0) {
   $result->nextRow();
   return ucfirst($result->get('name'));
  }
 }

 return 'Errore';
}

function getAttributeValue( $tabNum, $metadato, $container, $objectId ) {
 global $mysql;

 $query = "SELECT value_longtext1 as 'value' FROM ca_attribute_values WHERE attribute_id = (
  SELECT attribute_id FROM ca_attributes WHERE table_num = {$tabNum} AND row_id = {$objectId} AND element_id = (
  SELECT element_id FROM ca_metadata_elements WHERE TRIM(UPPER(element_code)) = TRIM(UPPER('{$container}'))) LIMIT 1)
   AND element_id = (SELECT element_id FROM ca_metadata_elements WHERE TRIM(UPPER(element_code)) = TRIM(UPPER('{$metadato}')))";

 $result = $mysql->query( $query );
 if ( $result->numRows() > 0 ) {
  $result->nextRow();

  return $result->get('value');
 }

 return "";
}

function hasMedia($id){
 global $mysql;
 $query = 'SELECT * FROM ca_objects_x_object_representations INNER JOIN ca_object_representations
ON ca_object_representations.representation_id = ca_objects_x_object_representations.representation_id
WHERE access != 0 AND object_id = ' . $id;
 $result = $mysql->query($query);
 if ($result->numRows() > 0) {
  return true;
 }
 return false;
}

function delete_hierarchy($id, $hierarchy, $db) {
 global $mongo, $mysql;
 $mongo = $hierarchy;
 $mysql = $db;

 // Recupero il padre
 $parent = $mysql->query("SELECT parent_id FROM ca_objects WHERE object_id = " . $id);
 $parent->nextRow();
 $parent = $parent->get('parent_id');

 if ($parent != NULL) {
  hierarchy($parent, $mongo, $mysql);
 }

 $mongo->remove(array("id" => $id));

}
