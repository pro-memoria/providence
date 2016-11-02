<?php
/**
 * Funzioni per il sync per il Polo del 900
 * 
 * 2015/10/30
 * 
 * @version 0.1
 * @author Luca Montanera <luca.montanera@promemoriagroup.com>
 * @copyright Promemoria
 */

define('_CA_ROOT_URL_', 'polo900-dev.promemoriagroup.com/backend');

/**
 * Recupero della tipologia
 * @param Int $type_id Identificativo della tipologia
 * @return array ritornante le informazioni per quella tipologia
 */
 function getTypeName($type_id)	{
 	global $mysql;

     $result['id'] = $type_id;
     $r = $mysql->query("SELECT l.name_singular as 'name' FROM ca_list_item_labels l WHERE l.item_id = " . $type_id);
     while ($r->nextRow())
         $result['name'] = $r->get('name');

     // Recupero la mappatura del filtro
     include_once('exception.php');
	 global $types_map;
     if ($supertype = $types_map[strtolower($result['name'])]) {
         $result['supertype'] = $supertype;
     }

 	return $result;
 }

/**
 * Recupero del nome dell'ente per un determinato elemento
 * @param Int $el_id Identificativo dell'elemnto
 * @param Int $table_num numero della tabella dell'elemnto in CA
 * @return String nome dell'ente per quell'unità
 */
function getEnteName($el_id, $table_num)	{
	global $mysql;

    $q_group_name = <<<QUERY
    SELECT g.name as 'name'
    FROM ca_acl a INNER JOIN ca_user_groups g ON (a.group_id = g.group_id)
    WHERE a.group_id IS NOT NULL
    AND a.row_id = {$el_id}
    AND a.table_num = {$table_num}
QUERY;

    $result = array();
    $r = $mysql->query($q_group_name);
    while ($r->nextRow()) {
        switch ($r->get('name')) {
            case 'Ente Gramsci':
                $result[] = "Fondazione Istituto Piemontese \"Antonio Gramsci\"";
                break;
            case 'Fondazione Gobetti':
                $result[] = "Centro Studi Piero Gobetti";
                break;
            case 'Fondazione Donat-Cattin':
                $result[] = "Fondazione Carlo Donat-Cattin";
                break;
            case 'Ente Nocentini':
                $result[] = "Fondazione Vera Nocentini";
                break;
            case 'Salvemini':
                $result[] = "Istituto di studi storici Gaetano Salvemini";
                break;
            default:
                break;
        }
    }

	return count($result > 1) ? $result : $result[0];
}

function getInfoEnte($obj_id)  {
    global $mysql;

    $parent_id = $obj_id;
    do {
	$obj_id = $parent_id;
        $sql = $mysql->query("SELECT parent_id as 'parent_id' FROM ca_objects WHERE object_id = " . $obj_id);
        while ($pa = $sql->nextRow()) {
            $parent_id = $pa['parent_id'];
        }
    } while ($parent_id != null);

    $result = $mysql->query("SELECT value_longtext1 FROM ca_attribute_values INNER JOIN ca_attributes ON (ca_attribute_values.attribute_id = ca_attributes.attribute_id) WHERE ca_attribute_values.element_id = 1417 AND table_num = 57 AND row_id = " . $obj_id);
    while ($result->nextRow()) {
        $res = $r->get('value_longtext1');
    }
    return $res;
}

/**
 * Recupero della tipologia
 * @param Int $obj_id Identificativo dell'oggetto
 * @return Array elenco di tutti i nomi alternativi per quell'oggetto
 */
function getAlternativeObjNames($obj_id)	{
	global $mysql;

    $q_alternative_name = <<<QUERY
    SELECT l.name as 'name'
    FROM ca_object_labels l
    WHERE l.is_preferred = 0
    AND	l.object_id = {$obj_id}
QUERY;

    $result = array();
    $r = $mysql->query($q_alternative_name);
    while ($r->nextRow())
        $result[] = $r->get('name');

	return $result;
}

function getAlternativeEntNames($obj_id)	{
	global $mysql;

	$q_alternative_name = <<<QUERY
    SELECT l.displayname as 'name'
    FROM ca_entity_labels l
    WHERE l.is_preferred = 0
    AND	l.entity_id = {$obj_id}
QUERY;

	$result = array();
	$r = $mysql->query($q_alternative_name);
	while ($r->nextRow())
		$result[] = $r->get('name');

	return $result;
}

/**
 * Recupero della tipologia di metadato
 * @param Int $element_id Identificativo dell'element code
 * @return String nome della tipologia dell'element code
 */
function getMetadataType($element_id)	{
	global $mysql;

    $q_metadata_type = <<<QUERY
    SELECT e.datatype as 'type'
    FROM ca_metadata_elements e
    WHERE e.element_id = {$element_id}
QUERY;

    $tmp = '';
    $r = $mysql->query($q_metadata_type);
    while ($r->nextRow()) {
        $tmp = $r->get('type');
    }

	switch ($tmp) {
		case 0: return "container";
		case 1: return "text";
		case 2: return "datarange";
		case 3: return "list";
		case 4: return "geocode";
		case 5: return "url";
		case 6: return "currency";
		case 8: return "length";
		case 9: return "weight";
		case 10: return "timecode";
		case 11: return "integer";
		case 12: return "numeric";
		case 13: return "lcsh";
		case 14: return "geonames";
		case 15: return "file";
		case 16: return "media";
		case 19: return "taxonomy";
		case 20: return "informatioservice";
		case 21: return "objectrepresentations";
		case 22: return "entities";
		case 23: return "places";
		case 24: return "occurrences";
		case 25: return "collections";
		case 26: return "storagelocations";
		case 27: return "loans";
		case 28: return "movements";
		case 29: return "objects";
		case 30: return "objectlots";
	}
}

/**
 * Implementare il recupero della label alternativa associata a un metadato
 * @return String La label della bundle
 */
function getBundleLabel($meta_id, $table_num, $obj_type)	{
	global $mysql;


    $q_bundle_label = <<<QUERY
    SELECT settings
    FROM `ca_editor_ui_bundle_placements`
    WHERE
        bundle_name = CONCAT('ca_attribute_', (
            SELECT element_code
            FROM ca_metadata_elements
            WHERE element_id = {$meta_id})
        ) AND
        screen_id IN (
            SELECT screen_id
            FROM ca_editor_ui_screen_type_restrictions
            WHERE table_num = {$table_num}
            AND type_id = {$obj_type}
            AND screen_id <> 94
        )
QUERY;


    $settings = '';
    $r = $mysql->query($q_bundle_label);
    while ($r->nextRow())
        $settings = $r->get('settings');

	$settings = unserialize(base64_decode($settings));
	$n = ($settings != NULL && isset($settings['label'])) ? $settings['label'][1] : '';

	return $n;
}

/**
 * [getBundleRelationLabel description]
 * @param  [type] $relation_type [description]
 * @param  [type] $table_num     [description]
 * @param  [type] $type_id       [description]
 * @param  [type] $table_name    [description]
 * @return [type]                [description]
 */
function getBundleRelationLabel($relation_type, $table_num, $type_id, $table_name)	{
	global $mysql;


    $q_bundle_relation_label = <<<QUERY
    SELECT settings
    FROM `ca_editor_ui_bundle_placements`
    WHERE
        bundle_name = '{$table_name}' AND
        screen_id IN (
            SELECT screen_id
            FROM ca_editor_ui_screen_type_restrictions
            WHERE table_num = {$table_num}
            AND type_id = {$type_id}
        )
QUERY;

    $settings = '';
    $n = '';
    $r = $mysql->query($q_bundle_relation_label);
    while ($r->nextRow())    {
        $settings = $r->get('settings');
        $settings = unserialize(base64_decode($settings));
        if (isset($settings['restrict_to_relationship_types']) && $settings['restrict_to_relationship_types'][0] == $relation_type)	{
            $n = ($settings != NULL && isset($settings['label'])) ? $settings['label'][1] : '';
            break;
        }
    }

	return $n;
}

/**
 * Ritorna la label associata a quel metadato
 * @param  Int $element_id Identificativo dell'element_code
 * @return String label dell'element_code
 */
function getMetadataName($element_id)	{
	global $mysql;

    $q_element_name = <<<QUERY
    SELECT e.name as 'name'
    FROM ca_metadata_element_labels e
    WHERE e.element_id = {$element_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_element_name);
    while ($r->nextRow())
        $n = $r->get('name');

	return $n;
}

/**
 * [getRelationTypeName description]
 * @param  [type] $relation_type [description]
 * @return [type]                [description]
 */
function getRelationTypeName($relation_type)	{
	global $mysql;

    $q_relation_name = <<<QUERY
    SELECT typename as 'name'
    FROM ca_relationship_type_labels
    WHERE type_id = {$relation_type}
QUERY;

    $n = '';
    $r = $mysql->query($q_relation_name);
    while ($r->nextRow())
        $n = $r->get('name');

	return $n;
}
/**
 * Trasforma la data di CA in una data normale
 * @param  Double $data La data nel formato di CA
 * @return Array La data spezzettata
 */
function trasformData($data)  {
    $data   = $data . ""; //Trasformo la data in una stringa per comodità
    $exp    = explode(".", $data);
    $rest   = str_split($exp[1]);
      
    return array(
      'year' => $exp[0],
      'month' => $rest[0],
      'day' => $rest[1],
      'hour' => $rest[2],
      'minute' => $rest[3],
      'second' => $rest[4]
    );
  }

/**
 * [getLabel description]
 * @param  [type] $elem_id [description]
 * @return [type]          [description]
 */
function getLabel($elem_id, $table_num, $type_id)	{
	$label = getBundleLabel($elem_id, $table_num, $type_id);
	if ($label == '')	
		$label = getMetadataName($elem_id);
	return $label;
}

/**
 * [getRelationLabel description]
 * @param  [type] $relation_type [description]
 * @param  [type] $table_num     [description]
 * @param  [type] $type_id       [description]
 * @return [type]                [description]
 */
function getRelationLabel($relation_type, $table_num, $type_id, $rel_table)	{
	$label = getBundleRelationLabel($relation_type, $table_num, $type_id, $rel_table);
	if ($label == '')
		$label = getRelationTypeName($relation_type);

	return $label;
}

/**
 * [getListValue description]
 * @param  [type] $item_id [description]
 * @return [type]          [description]
 */
function getListValue($item_id)	{
	global $mysql;

    if (!$item_id) return "";

    $q_list_value = <<<QUERY
    SELECT l.name_singular as 'name'
    FROM ca_list_item_labels l
    WHERE l.item_id = {$item_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_list_value);
    while ($r->nextRow())
        $n = $r->get('name');

	return $n;
}

/**
 * [getMetadataCode description]
 * @param  [type] $elem_id [description]
 * @return [type]          [description]
 */
function getMetadataCode($elem_id)	{
	global $mysql;

    $q_metadata_code = <<<QUERY
    SELECT e.element_code as 'name'
    FROM ca_metadata_elements e
    WHERE e.element_id = {$elem_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_metadata_code);
    while ($r->nextRow())
        $n = $r->get('name');

	return $n;
}

/**
 * [getTableName description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getTableName($table_num)	{
	switch ($table_num) {
		case 56: return "ca_object_representations";
		case 20: return "ca_entities";
		case 72: return "ca_places";
		case 67: return "ca_occurrences";
		case 13: return "ca_collections";
		case 89: return "ca_storage_localtions";
		case 133: return "ca_loans";
		case 137: return "ca_movements";
		case 57: return "ca_objects";
		case 51: return "ca_object_lots";
	}
}

/**
 * [getTableName description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getRelationName($ent_id, $table_num)	{
	global $mysql;
    $n = '';
	switch ($table_num) {
		case 56:
            $q_representation_name = "SELECT l.name as 'name' FROM ca_object_representation_labels l WHERE representation_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 20:
            $q_entity_name = "SELECT l.displayname as 'name' FROM ca_entity_labels l WHERE entity_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_entity_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
            break;
		case 72:
            $q_place_name = "SELECT l.name as 'name' FROM ca_place_labels l WHERE place_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_place_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 67:
            $q_occurrence_name = "SELECT l.name as 'name' FROM ca_occurrence_labels l WHERE occurrence_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_occurrence_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 13:
            $q_collection_name = "SELECT l.name as 'name' FROM ca_collection_labels l WHERE collection_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_collection_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 89:
            $q_location_name = "SELECT l.name as 'name' FROM ca_storage_location_labels l WHERE location_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_location_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
        case 133:
            $q_loan_name = "SELECT l.name as 'name' FROM ca_loan_labels l WHERE loan_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_loan_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
        case 137:
            $q_movement_name = "SELECT l.name as 'name' FROM ca_movement_labels l WHERE movement_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_movement_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 57:
            $q_object_name = "SELECT l.name as 'name' FROM ca_object_labels l WHERE object_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_object_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
		case 51:
            $q_lot_name = "SELECT l.name as 'name' FROM ca_object_lot_labels l WHERE lot_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_lot_name);
            while ($r->nextRow()) {
                $n = $r->get('name');
            }
			break;
	}
	return $n;
}
/**
 * [getMongoRelation description]
 * @param  [type] $ent_id     [description]
 * @param  [type] $table_name [description]
 * @return [type]             [description]
 */
function getMongoRelation($ent_id, $table_name)	{
	global $mongo;
    try {
		$ent_id = (int) $ent_id;
	    $collection = $mongo->$table_name;
        $r = $collection->findOne(array('id' => $ent_id), array('_id' => 1));
    } catch (Exception $e)  {
        $r['_id'] = '';
    }
	return $r['_id'];

}

/**
 * [getPrimaryImg description]
 * @param  [type] $obj_id    [description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getPrimaryImg($obj_id, $table_num)	{
    global $mysql, $get_representation_primary_img, $get_entity_primary_img, $get_place_primary_img, $get_occurrence_primary_img, $get_collection_primary_img, $get_location_primary_img, $get_loan_primary_img, $get_movements_primary_img, $get_object_primary_img, $get_lot_primary_img;

    $primary = '';
	switch ($table_num) {
		case 20:
            $q_entity_primary_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_entities WHERE is_primary = 1 AND entity_id = {$obj_id}";
            $r = $mysql->query($q_entity_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 72:
			$q_place_primary_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_places WHERE is_primary = 1 AND place_id = {$obj_id}";
            $r = $mysql->query($q_place_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 67:
            $q_occurrence_primary_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_occurrences WHERE is_primary = 1 AND occurrence_id = {$obj_id}";
            $r = $mysql->query($q_occurrence_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 13:
			$q_collection_primary_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_collections WHERE is_primary = 1 AND collection_id = {$obj_id}";
            $r = $mysql->query($q_collection_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 89:
            $q_location_primary_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_storage_locations WHERE is_primary = 1 AND location_id = {$obj_id}";
            $r = $mysql->query($q_location_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
        case 133:
            $q_loan_primary_img = "SELECT representation_id as 'id' FROM ca_loans_x_object_representations WHERE is_primary = 1 AND loan_id = {$obj_id}";
            $r = $mysql->query($q_loan_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
        case 137:
            $q_movement_primary_img = "SELECT representation_id as 'id' FROM ca_movements_x_object_representations WHERE is_primary = 1 AND movement_id = {$obj_id}";
            $r = $mysql->query($q_movement_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 57:
            $q_object_primary_img = "SELECT representation_id as 'id' FROM ca_objects_x_object_representations WHERE is_primary = 1 AND object_id = {$obj_id}";
            $r = $mysql->query($q_object_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
		case 51:
            $q_lot_primary_img = "SELECT representation_id as 'id' FROM ca_object_lots_x_object_representations WHERE is_primary = 1 AND lot_id = {$obj_id}";
            $r = $mysql->query($q_lot_primary_img);
            while ($r->nextRow()) {
                $primary = $r->get('id');
            }
			break;
	}

    if ($primary != '') {
        $r = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $primary);
        while ($r->nextRow()) {
            $name = $r->get('name');
        }
        return array('links' => getMediaURL($primary), 'label' => $name);
    }
    return null;
}

/**
 * [getPrimaryImg description]
 * @param  [type] $obj_id    [description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getAllMedia($obj_id, $table_num)	{
    global $mysql;

    $n = array();
	$id = -1;
	switch ($table_num) {
		case 20:
            $q_entity_all_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_entities WHERE entity_id = {$obj_id}";
            $r = $mysql->query($q_entity_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 72:
            $q_place_all_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_places WHERE place_id = {$obj_id}";
            $r = $mysql->query($q_place_all_img);
            while ($r->nextRow()) {

                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 67:
            $q_occurrence_all_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_occurrences WHERE occurrence_id = {$obj_id}";
            $r = $mysql->query($q_occurrence_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 13:
            $q_collection_all_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_collections WHERE collection_id = {$obj_id}";
            $r = $mysql->query($q_collection_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 89:
            $q_location_all_img = "SELECT representation_id as 'id' FROM ca_object_representations_x_storage_locations WHERE location_id = {$obj_id}";
            $r = $mysql->query($q_location_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
        case 133:
            $q_lot_all_img = "SELECT representation_id as 'id' FROM ca_object_lots_x_object_representations WHERE lot_id = {$obj_id}";
            $r = $mysql->query($q_lot_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
        case 137:
            $q_movement_all_img = "SELECT representation_id as 'id' FROM ca_movements_x_object_representations WHERE movement_id = {$obj_id}";
            $r = $mysql->query($q_movement_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 57:
            $q_object_all_img = "SELECT representation_id as 'id' FROM ca_objects_x_object_representations WHERE object_id = {$obj_id}";
            $r = $mysql->query($q_object_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
		case 51:
            $q_lot_all_img = "SELECT representation_id as 'id' FROM ca_object_lots_x_object_representations WHERE lot_id = {$obj_id}";
            $r = $mysql->query($q_lot_all_img);
            while ($r->nextRow()) {
                $r2 = $mysql->query("SELECT name as 'name' FROM `ca_object_representation_labels` where representation_id = " . $r->get('id'));
                while ($r2->nextRow()) {
                    $name = $r2->get('name');
                }
                $n[] = array('links' => getMediaURL($r->get('id')), 'label' => $name);
            }
			break;
	}
	return $n;
}

/**
 * [getMediaURL description]
 * @param  [type] $media_id [description]
 * @return [type]           [description]
 */
function getMediaURL($media_id)	{
	
	$result = array();

	exec("curl -XGET 'http://administrator:po!Pr0m3m0r1a@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.small\": {\"returnURL\": true}}}'", $vs_exec);
	$vs_exec = (array) json_decode($vs_exec[0]);
	if (isset($vs_exec['ca_object_representations.media.small']))	{
		$result['small'] = $vs_exec['ca_object_representations.media.small'];
	}
	$vs_exec = null;
	exec("curl -XGET 'http://administrator:po!Pr0m3m0r1a@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.large\": {\"returnURL\": true}}}'", $vs_exec);
	$vs_exec = (array) json_decode($vs_exec[0]);
	if (isset($vs_exec['ca_object_representations.media.large']))	{
		$result['large'] = $vs_exec['ca_object_representations.media.large'];
	}

	return $result;
}

function getOrdine($meta_id, $table_num, $obj_type)	{
	global $mysql;

    $q_bundle_label = <<<QUERY
    SELECT screen_id, rank
    FROM `ca_editor_ui_bundle_placements`
    WHERE
        bundle_name = CONCAT('ca_attribute_', (
            SELECT element_code
            FROM ca_metadata_elements
            WHERE element_id = {$meta_id})
        ) AND
        screen_id IN (
            SELECT screen_id
            FROM ca_editor_ui_screen_type_restrictions
            WHERE table_num = {$table_num}
            AND type_id = {$obj_type}
        )
QUERY;


    $screen_id = '';
    $rank = '';
    $r = $mysql->query($q_bundle_label);
    while ($r->nextRow())	{
        if ($r->get('screen_id') == '94')
            continue;
        $screen_id = $r->get('screen_id');
        $rank = $r->get('rank');
    }
 
    return ($screen_id != null && $rank != null) ? array('screen' => $screen_id, 'ord' => $rank) : null;
}

function toInsert($element_code, $obj_type, $table_num) {
	global $mysql;
	include_once __CA_APP_DIR__."/plugins/mongocane/MongoSYNC/exception.php";

	if (in_array($element_code, $exception_metadata))
		return false;

	$r = $mysql->query("SELECT screen_id FROM ca_editor_ui_bundle_placements WHERE bundle_name = 'ca_attribute_".$element_code."' AND screen_id IN (SELECT screen_id FROM ca_editor_ui_screen_type_restrictions WHERE table_num = {$table_num} AND type_id = {$obj_type})");
	while ($r->nextRow())
		$screen = $r->get('screen_id');
	return !in_array($screen, $exception_screen);
}
