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
 function getTypeName($type_id) {
  include('exception.php');
    global $mysql, $types_map;

     $result['id'] = $type_id;
     $r = $mysql->query("SELECT l.name_singular as 'name' FROM ca_list_item_labels l WHERE l.item_id = " . $type_id);
     while ($row = $r->fetch_assoc())
         $result['name'] = $row['name'];

     // Recupero la mappatura del filtro
     if ($supertype = $types_map[strtolower(trim($result['name']))]) {
        $result['supertype'] = $supertype;
        // error_log(json_encode($result));
     }

    return $result;
 }

/**
 * Recupero del nome dell'ente per un determinato elemento
 * @param Int $el_id Identificativo dell'elemnto
 * @param Int $table_num numero della tabella dell'elemnto in CA
 * @return String nome dell'ente per quell'unità
 */
function getEnteName($el_id, $table_num)    {
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
    while ($row = $r->fetch_assoc())
        $result[] = $row['name'];

    return count($result > 1) ? $result : $result[0];
}

/**
 * Recupero le informazioni dell'ente che ha creato l'oggetto
*/
function getInfoEnte($obj_id)  {
    global $mysql;

    $parent_id = $obj_id;
    do {
       $obj_id = $parent_id;
        $sql = $mysql->query("SELECT parent_id as 'parent_id' FROM ca_objects WHERE object_id = " . $obj_id);
        while ($pa = $sql->fetch_assoc()) {
            $parent_id = $pa['parent_id'];
        }
    } while ($parent_id != null);

    $result = $mysql->query("SELECT value_longtext1 FROM ca_attribute_values INNER JOIN ca_attributes ON (ca_attribute_values.attribute_id = ca_attributes.attribute_id) WHERE ca_attribute_values.element_id = 1417 AND table_num = 57 AND row_id = " . $obj_id);
    while ($row = $result->fetch_assoc()) {
        $res = $row['value_longtext1'];
    }
    return $res;
}

/**
 * Recupero della tipologia
 * @param Int $obj_id Identificativo dell'oggetto
 * @return Array elenco di tutti i nomi alternativi per quell'oggetto
 */
function getAlternativeObjNames($obj_id)    {
    global $mysql;

    $q_alternative_name = <<<QUERY
    SELECT l.name as 'name'
    FROM ca_object_labels l
    WHERE l.is_preferred = 0
    AND l.object_id = {$obj_id}
QUERY;

    $result = array();
    $r = $mysql->query($q_alternative_name);
    while ($row = $r->fetch_assoc())
        $result[] = $row['name'];

    return $result;
}

function getAlternativeEntNames($obj_id)    {
    global $mysql;

    $q_alternative_name = <<<QUERY
    SELECT l.displayname as 'name'
    FROM ca_entity_labels l
    WHERE l.is_preferred = 0
    AND l.entity_id = {$obj_id}
QUERY;

    $result = array();
    $r = $mysql->query($q_alternative_name);
    while ($row = $r->fetch_assoc())
        $result[] = $row['name'];

    return $result;
}

function getAlternativeOccuNames($occu_id)  {
    global $mysql;

    $q_alternative_name = <<<QUERY
    SELECT l.name as 'name'
    FROM ca_occurrence_labels l
    WHERE l.is_preferred = 0
    AND l.occurrence_id = {$occu_id}
QUERY;

    $result = array();
    $r = $mysql->query($q_alternative_name);
    while ($row = $r->fetch_assoc())
        $result[] = $row['name'];

    return $result;
}

/**
 * Recupero della tipologia di metadato
 * @param Int $element_id Identificativo dell'element code
 * @return String nome della tipologia dell'element code
 */
function getMetadataType($element_id)   {
    global $mysql;

    $q_metadata_type = <<<QUERY
    SELECT e.datatype as 'type'
    FROM ca_metadata_elements e
    WHERE e.element_id = {$element_id}
QUERY;

    $tmp = '';
    $r = $mysql->query($q_metadata_type);
    while ($row = $r->fetch_assoc()) {
        $tmp = $row['type'];
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
function getBundleLabel($meta_id, $table_num, $obj_type)    {
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

    // var_dump($q_bundle_label);


    $settings = '';

    $r = $mysql->query($q_bundle_label);
    while ($row = $r->fetch_assoc())
        $settings = $row['settings'];

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
function getBundleRelationLabel($relation_type, $table_num, $type_id, $table_name)  {
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
    while ($row = $r->fetch_assoc())    {
        $settings = $row['settings'];
        $settings = unserialize(base64_decode($settings));
        if (isset($settings['restrict_to_relationship_types']) && $settings['restrict_to_relationship_types'][0] == $relation_type) {
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
function getMetadataName($element_id)   {
    global $mysql;

    $q_element_name = <<<QUERY
    SELECT e.name as 'name'
    FROM ca_metadata_element_labels e
    WHERE e.element_id = {$element_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_element_name);
    while ($row = $r->fetch_assoc())
        $n = $row['name'];

    return $n;
}

/**
 * [getRelationTypeName description]
 * @param  [type] $relation_type [description]
 * @return [type]                [description]
 */
function getRelationTypeName($relation_type)    {
    global $mysql;

    $q_relation_name = <<<QUERY
    SELECT typename as 'name'
    FROM ca_relationship_type_labels
    WHERE type_id = {$relation_type}
QUERY;

    $n = '';
    $r = $mysql->query($q_relation_name);
    while ($row = $r->fetch_assoc())
        $n = $row['name'];

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
      $rest   = str_split($exp[1], 2);

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
function getLabel($elem_id, $table_num, $type_id)   {

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
function getRelationLabel($relation_type, $table_num, $type_id, $rel_table) {
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
function getListValue($item_id) {
    global $mysql;

    if($item_id == null) return null;

    $q_list_value = <<<QUERY
    SELECT l.name_singular as 'name'
    FROM ca_list_item_labels l
    WHERE l.item_id = {$item_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_list_value);
    while ($row = $r->fetch_assoc())
        $n = $row['name'];

    return $n;
}

/**
 * [getMetadataCode description]
 * @param  [type] $elem_id [description]
 * @return [type]          [description]
 */
function getMetadataCode($elem_id)  {
    global $mysql;

    $q_metadata_code = <<<QUERY
    SELECT e.element_code as 'name'
    FROM ca_metadata_elements e
    WHERE e.element_id = {$elem_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_metadata_code);
    while ($row = $r->fetch_assoc())
        $n = $row['name'];

    return $n;
}

/**
 * [getTableName description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getTableName($table_num)   {
    switch ($table_num) {
        case 56: return "ca_object_representations";
        case 20: return "ca_entities";
        case 72: return "ca_places";
        case 67: return "ca_occurrences";
        case 13: return "ca_collections";
        case 89: return "ca_storage_locations";
        case 133: return "ca_loans";
        case 137: return "ca_movements";
        case 57: return "ca_objects";
        case 51: return "ca_object_lots";
        case 33: return "ca_list_items";
    }
}

/**
 * [getTableName description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getRelationName($ent_id, $table_num)   {
    global $mysql;
    $n = '';
    switch ($table_num) {
        case 56:
            $q_representation_name = "SELECT l.name as 'name' FROM ca_object_representation_labels l WHERE representation_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 20:
            $q_entity_name = "SELECT l.displayname as 'name' FROM ca_entity_labels l WHERE entity_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_entity_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 72:
            $q_place_name = "SELECT l.name as 'name' FROM ca_place_labels l WHERE place_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_place_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 67:
            $q_occurrence_name = "SELECT l.name as 'name' FROM ca_occurrence_labels l WHERE occurrence_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_occurrence_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 13:
            $q_collection_name = "SELECT l.name as 'name' FROM ca_collection_labels l WHERE collection_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_collection_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 89:
            $q_location_name = "SELECT l.name as 'name' FROM ca_storage_location_labels l WHERE location_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_location_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 133:
            $q_loan_name = "SELECT l.name as 'name' FROM ca_loan_labels l WHERE loan_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_loan_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 137:
            $q_movement_name = "SELECT l.name as 'name' FROM ca_movement_labels l WHERE movement_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_movement_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 57:
            $q_object_name = "SELECT l.name as 'name' FROM ca_object_labels l WHERE object_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_object_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;
        case 51:
            $q_lot_name = "SELECT l.name as 'name' FROM ca_object_lot_labels l WHERE lot_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_lot_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
            }
            break;

        case 33:
            $q_lot_name = "SELECT l.name_singular as 'name' FROM ca_list_item_labels l WHERE item_id = {$ent_id}";
            $n = '';
            $r = $mysql->query($q_lot_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['name'];
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
function getMongoRelation($ent_id, $table_name) {
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
function getPrimaryImg($obj_id, $table_num) {
    global $mysql, $get_representation_primary_img, $get_entity_primary_img, $get_place_primary_img, $get_occurrence_primary_img, $get_collection_primary_img, $get_location_primary_img, $get_loan_primary_img, $get_movements_primary_img, $get_object_primary_img, $get_lot_primary_img;

    $primary = '';
    switch ($table_num) {
        case 20:
            $q_entity_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_representations_x_entities x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND entity_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_entity_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 72:
            $q_place_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_representations_x_places x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND place_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_place_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 67:
            $q_occurrence_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_representations_x_occurrences x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND occurrence_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_occurrence_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 13:
            $q_collection_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_representations_x_collections x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND collection_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_collection_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 89:
            $q_location_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_representations_x_storage_locations x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND location_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_location_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 133:
            $q_loan_primary_img = "SELECT r.representation_id as 'id' FROM ca_loans_x_object_representations x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND loan_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_loan_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 137:
            $q_movement_primary_img = "SELECT r.representation_id as 'id' FROM ca_movements_x_object_representations x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND movement_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_movement_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 57:
            $q_object_primary_img = "SELECT r.representation_id as 'id' FROM ca_objects_x_object_representations x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND object_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_object_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
        case 51:
            $q_lot_primary_img = "SELECT r.representation_id as 'id' FROM ca_object_lots_x_object_representations x INNER JOIN ca_object_representations r ON (r.representation_id = x.representation_id) WHERE is_primary = 1 AND lot_id = {$obj_id} AND r.access != 0";
            $r = $mysql->query($q_lot_primary_img);
            while ($row = $r->fetch_assoc()) {
                $primary = $row['id'];
            }
            break;
    }

    return ($primary != '') ? getMediaURL($primary) : null;
}

/**
 * [getPrimaryImg description]
 * @param  [type] $obj_id    [description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function getAllMedia($obj_id, $table_num)   {
    global $mysql;

    $n = array();
    $id = -1;
    switch ($table_num) {
        case 20:
            $q_entity_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_representations_x_entities x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE entity_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_entity_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 72:
            $q_place_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_representations_x_places x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE place_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_place_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 67:
            $q_occurrence_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_representations_x_occurrences x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE occurrence_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_occurrence_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 13:
            $q_collection_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_representations_x_collections x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE collection_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_collection_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 89:
            $q_location_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_representations_x_storage_locations x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE location_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_location_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 133:
            $q_lot_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_object_lots_x_object_representations x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE lot_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_lot_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 137:
            $q_movement_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_movements_x_object_representations x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE movement_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_movement_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 57:
            $q_object_all_img = "SELECT r.representation_id as 'id', is_primary as 'primary' FROM ca_objects_x_object_representations x INNER JOIN ca_object_representations r ON (x.representation_id = r.representation_id) WHERE object_id = {$obj_id} AND r.access != 0 ORDER BY is_primary DESC";
            $r = $mysql->query($q_object_all_img);
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
            }
            break;
        case 51:
            while ($row = $r->fetch_assoc()) {
                array_push($n, array(
                    'url' =>getMediaURL($row['id']),
                    'title' => getMediaDidascalia($row['id']),
                    'isPrimary'=> $row['primary']== 1 ));
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
function getMediaURL($media_id) {

    $ca_user         = "administrator";
    $ca_psw          = "po!Pr0m3m0r1a";

    $result = array();

    exec("curl -XGET 'http://$ca_user:$ca_psw@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.thumbnail\": {\"returnURL\": true}}}'", $vs_exec);
    $vs_exec = (array) json_decode($vs_exec[0]);

    if (isset($vs_exec['ca_object_representations.media.thumbnail']))   {
        $result['thumbnail'] = $vs_exec['ca_object_representations.media.thumbnail'];
    }
    $vs_exec = null;
    exec("curl -XGET 'http://$ca_user:$ca_psw@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.mediumlarge\": {\"returnURL\": true}}}'", $vs_exec);
    $vs_exec = (array) json_decode($vs_exec[0]);
    if (isset($vs_exec['ca_object_representations.media.mediumlarge']))   {
        $result['mediumlarge'] = $vs_exec['ca_object_representations.media.mediumlarge'];
    }

    $vs_exec = null;
    exec("curl -XGET 'http://$ca_user:$ca_psw@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.original\": {\"returnURL\": true}}}'", $vs_exec);
    $vs_exec = (array) json_decode($vs_exec[0]);
    if (isset($vs_exec['ca_object_representations.media.original']))   {
        $result['original'] = $vs_exec['ca_object_representations.media.original'];
    }

    $vs_exec = null;
    exec("curl -XGET 'http://$ca_user:$ca_psw@". _CA_ROOT_URL_ ."/service.php/item/ca_object_representations/?id={$media_id}' -d '{\"bundles\": {\"ca_object_representations.media.tiny\": {\"returnURL\": true}}}'", $vs_exec);
    $vs_exec = (array) json_decode($vs_exec[0]);
    if (isset($vs_exec['ca_object_representations.media.tiny']))   {
        $result['tiny'] = $vs_exec['ca_object_representations.media.tiny'];
    }

    return $result;
}


function getMediaDidascalia($media_id)  {
    global $mysql;
    $title = "";
    $title_res = $mysql->query("SELECT name FROM ca_object_representation_labels WHERE is_preferred = 1 AND representation_id = " . $media_id);
    while ($row = $title_res->fetch_assoc()) {
        $title = $row['name'];
    }


    return $title;
}



/**
 * [getOrdine description]
 * @param  [type] $media_id, $table_num, $obj_type [description]
 * @return [type]           [description]
 */
 function getOrdine($meta_id, $table_num, $obj_type)    {
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
     while ($row = $r->fetch_assoc())   {
         if ($row['screen_id'] == '94')
             continue;
         $screen_id = $row['screen_id'];
         $rank = $row['rank'];
     }

     return ($screen_id != null && $rank != null) ? array('screen' => $screen_id, 'ord' => $rank) : null;
 }

//TODO I PARAMETRI PER IL TIPO DI RECORD SONO HARDCODED...
function getVisibilita($meta_id, $table_num, $obj_type) {

    //todo
    //gestire casi speciali (tranne preferred e non preferred label e relazioni): idno (identificativo dell'oggetto), extent, extent_units, metadati su accession/deaccession/current loc
    //tralasciare locale id (lingua), access (accesso), status, life_sdatetime, life_edatetime
    //gestione entità/sommari differenti

    global $mysql;

    $display_id = 0;
    $element_code = '';
    $bundle_name = '';

    if ($meta_id == "idno")
        $element_code = $meta_id;
    else if ($meta_id == "extent")
        $element_code = $meta_id;
    else if ($meta_id == "extent_units")
        $element_code = $meta_id;
    else if ($meta_id == "is_deaccessioned")
        $element_code = $meta_id;
    else if ($meta_id == "deaccession_date")
        $element_code = $meta_id;
    else if ($meta_id == "deaccession_notes")
        $element_code = $meta_id;
    else if ($meta_id == "deaccession_type_id")
        $element_code = $meta_id;
    else
        $element_code = getMetadataCode($meta_id);

    if ($table_num = 57) {
        $display_id = 29;
        $bundle_name = "ca_objects.".$element_code;
    }

    $q_bundle_label = <<<QUERY
    SELECT placement_id
    FROM ca_bundle_display_placements
    WHERE display_id = $display_id AND bundle_name LIKE '$bundle_name'
QUERY;

    $placement_id = null;
    $r = $mysql->query($q_bundle_label);
    while ($row = $r->fetch_assoc()) {
        $placement_id = $row['placement_id'];
    }

    return ($placement_id != null) ?  1 : 0;
}


function toInsert($element_code, $obj_type, $table_num) {
    global $mysql;

  include("exception.php");

    if (in_array($element_code, $exception_metadata))
        return false;

    $r = $mysql->query("SELECT screen_id FROM ca_editor_ui_bundle_placements WHERE bundle_name = 'ca_attribute_".$element_code."' AND screen_id IN (SELECT screen_id FROM ca_editor_ui_screen_type_restrictions WHERE table_num = {$table_num} AND type_id = {$obj_type})");
    while ($row = $r->fetch_assoc())
        $screen = $row['screen_id'];
    return !in_array($screen, $exception_screen);
}

// function generateParentIDPath( $parentId ) {
//     global $mysql;
//     if ( $parentId === NULL ) {
//         return "";
//     }
//     $query     = "SELECT parent_id, deleted FROM ca_objects WHERE object_id = " . $parentId;
//     $result    = $mysql->query( $query );
//     $tmp       = $result->fetch_assoc();

//     if ($tmp['deleted'] == '1') {
//         return null;
//     }
//     $concat = generateParentIDPath( $tmp['parent_id'] );
//     if ($concat === NULL){
//         return null;
//     } else {
//         return  $concat . "/" . $parentId;
//     }
// }


function getParentArea($obj_id, $level){
    global $mysql;

    $all_parents = explode("/", generateParentIDPath( $obj_id ));

    for ($i=1; $i <= $level && $i < count($all_parents); $i++) {

        if (hasChildren($all_parents[$i]))  {

            $r = $mysql->query("SELECT obl.name AS 'name' FROM  ca_object_labels obl WHERE obl.object_id = ".$all_parents[$i]);
            while ($row = $r->fetch_assoc()){
                $name = $row['name'];
            }

            $parentFil = ($i > 1) ? $all_parents[$i - 1] : 0;
            $ar_filtro_area[] = array("id" => $all_parents[$i], "name" => $name, "parent" => $parentFil);

        }
    }

    return $ar_filtro_area;
}

function getRootParentType($type, $stop = "Root"){
    global $mysql;

    $type_id = $type["id"];

    $query = <<<QUERY
      SELECT ca_list_items.parent_id as id, ca_list_item_labels.name_singular as name
      FROM ca_list_item_labels INNER JOIN ca_list_items ON ca_list_item_labels.item_id = ca_list_items.parent_id
      WHERE ca_list_items.item_id = $type_id
QUERY;
    $results = $mysql->query($query);
    while ($row = $results->fetch_assoc()) {
      if(!startsWith($row["name"], $stop)){
        $type = getRootParentType($row, $stop);
      }
    }
    return $type;
}

function calculatePath($type_id, $precName, $stop = "Root", $path = array()){
    global $mysql;
    $suffix = empty($path) ? "" : end($path);
    $path[] = "/" . $precName  . $suffix;
    $query = <<<QUERY
      SELECT ca_list_items.parent_id as parent_id, ca_list_item_labels.name_singular as name
      FROM ca_list_item_labels INNER JOIN ca_list_items ON ca_list_item_labels.item_id = ca_list_items.parent_id
      WHERE ca_list_items.item_id = $type_id
QUERY;
    $results = $mysql->query($query);
    while ($row = $results->fetch_assoc()) {
      if(!startsWith($row["name"], $stop)){
        $path = calculatePath($row["parent_id"], $row["name"], $stop, $path);
      }
    }
    return $path;
}

function getItemId($item_name)  {
  global $mysql;

  $query = "SELECT item_id as 'id' FROM ca_list_item_labels WHERE ca_list_item_labels.name_singular = \"{$item_name}\"";
  $results = $mysql->query($query);
  $id = -1;
  while ($row = $results->fetch_assoc()) {
    $id = $row['id'];
  }

  return $id;
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function isAggregatore($id){
    global $mysql;
    $code='';
    $query = <<<QUERY
      SELECT ca_lists.list_code as 'code' FROM `ca_list_items`, ca_lists WHERE `item_id` = $id AND ca_list_items.list_id = ca_lists.list_id
QUERY;
    $results = $mysql->query($query);
    while ($row = $results->fetch_assoc()) {
        $code = $row['code'];
    }

    return ($code == "aggregatore") ? true : false;

}


/**
 * [getListCode description]
 * @param  [type] $item_id [description]
 * @return [type]          [description]
 */
function getListCode($item_id) {
    global $mysql;

    $q_list_value = <<<QUERY
    SELECT ca_lists.list_code as 'name'
    FROM ca_list_items l INNER JOIN ca_lists ON (l.list_id = ca_lists.list_id)
    WHERE l.item_id = {$item_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_list_value);
    while ($row = $r->fetch_assoc())
        $n = $row['name'];

    return $n;
}


/**
 * funzioni generiche sync
**/
function gestioneAttributi($obj_id, $table_num,$obj_type){
 global $mysql;
    $q_all_attribute = <<<QUERY
    SELECT
        a.attribute_id as 'id',
        a.element_id as 'attribute_element',
        element_code as 'code'
    FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
    WHERE a.row_id = {$obj_id}
    AND a.table_num = {$table_num}
QUERY;

    $all_attr = $mysql->query($q_all_attribute);
    $attributes = array();
    $is_attribute_array = false;

    while ($row = $all_attr->fetch_assoc()) {
        $attr_id = $row['id'];
        $attr_elem = $row['attribute_element'];
        $elem_code = $row['code'];

        if (!toInsert($elem_code, $obj_type, $table_num))
            continue;

        // Se non esiste ancora lo creo come oggetto.
        $attr = manageAttribute($attr_id, $attr_elem, $table_num, $obj_type, $obj_id);


        if ($attr) {
            if (!isset($attributes[$elem_code])) {
              $attributes[$elem_code] = $attr;
            } else {
              $is_attribute_array = true;
              // Se invece esiste giÃ  allora lo re-istanzio come array di oggetti
              $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
              unset($attributes[$elem_code]); // resetto l'elemento
              $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
              // e inserisco il vecchio valore wrappato in una array
              $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
            }

            //$attributes[$elem_code][$attr_elem][] = $attr;

        }
    }
    return $attributes;
}


function recuperoEntitaRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

    switch ($typeRelation) {
        case 'object':
            $q_relation = "SELECT x.entity_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_entities.type_id as 'typ' FROM ca_objects_x_entities x INNER JOIN ca_entities ON (x.entity_id = ca_entities.entity_id) WHERE x.object_id = {$obj_id} AND ca_entities.deleted = 0";
            break;
        case 'entity':
            $q_relation = "SELECT x.entity_right_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_entities.type_id as 'typ' FROM ca_entities_x_entities x INNER JOIN ca_entities ON (x.entity_right_id= ca_entities.entity_id) WHERE x.entity_left_id = {$obj_id} AND ca_entities.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.entity_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_entities.type_id as 'typ' FROM ca_entities_x_occurrences x INNER JOIN ca_entities ON (x.entity_id = ca_entities.entity_id) WHERE x.occurrence_id = {$obj_id} AND ca_entities.deleted = 0";
            break;
        case 'place':
            $q_relation = "SELECT x.entity_id as 'rel_id', x.type_id as 'type', ca_entities.type_id as 'typ' FROM ca_entities_x_places x INNER JOIN ca_entities ON (ca_entities.entity_id = x.entity_id)  WHERE ca_entities.deleted = 0 AND x.place_id = {$obj_id}";
            break;
        default:
            # code...
            break;
    }
    $m_entities = array();
    $all_ent = $mysql->query($q_relation);

    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_entities");
        // if (!isset($m_entities[$label])) {
            $m_entities[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 20),
                'access' => getAccess($row['rel_id'], 20),
                'relation' => getMongoRelation($row['rel_id'], "ca_entities"),
                'type_id' => getTypeName($row['typ'])
            );
        // }
    }

    return $m_entities;

}

function recuperoCollezioniRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

    switch ($typeRelation) {
        case 'object':
            $q_relation    = "SELECT x.collection_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_collections.type_id as 'typ' FROM ca_objects_x_collections x INNER JOIN ca_collections ON (x.collection_id = ca_collections.collection_id) WHERE x.object_id = {$obj_id} AND ca_collections.deleted = 0";
            break;
        case 'entity':
            $q_relation    = "SELECT x.collection_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_collections.type_id as 'typ' FROM ca_entities_x_collections x INNER JOIN ca_collections ON (x.collection_id= ca_collections.collection_id) WHERE x.entity_id = {$obj_id} AND ca_collections.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.collection_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_collections.type_id as 'typ' FROM ca_occurrences_x_collections x INNER JOIN ca_collections ON (x.collection_id = ca_collections.collection_id) WHERE x.occurrence_id = {$obj_id} AND ca_collections.deleted = 0";
        case 'place':
            $q_relation = "SELECT x.collection_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_collections.type_id as 'typ' FROM ca_places_x_collections x INNER JOIN ca_collections ON (x.collection_id = ca_collections.collection_id) WHERE x.place_id = {$obj_id} AND ca_collections.deleted = 0";
            break;
        default:
            # code...
            break;
    }

    $m_collections = array();
    $all_ent = $mysql->query($q_relation);
    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_collections");
        // if (!isset($m_entities[$label])) {
            $m_collections[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 13),
                'access' => getAccess($row['rel_id'], 13),
                'relation' => getMongoRelation($row['rel_id'], "ca_collections"),
                'type_id' => getTypeName($row['typ'])
            );
        // }
    }


    return $m_collections;
}


function recuperoLuoghiRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

        switch ($typeRelation) {
        case 'object':
            $q_relation = "SELECT x.place_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_places.type_id as 'typ' FROM ca_objects_x_places x INNER JOIN ca_places ON (x.place_id = ca_places.place_id) WHERE x.object_id = {$obj_id} AND ca_places.deleted = 0";
            break;
        case 'entity':
            $q_relation = "SELECT x.place_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_places.type_id as 'typ' FROM ca_entities_x_places x INNER JOIN ca_places ON (x.place_id = ca_places.place_id) WHERE x.entity_id = {$obj_id} AND ca_places.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.place_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_places.type_id as 'typ' FROM ca_places_x_occurrences x INNER JOIN ca_places ON (x.place_id = ca_places.place_id) WHERE x.occurrence_id = {$obj_id} AND ca_places.deleted = 0";
            break;
        case 'place':
            $q_relation = "SELECT x.place_left_id as 'rel_id', x.type_id as 'type', ca_places.type_id as 'typ' FROM ca_places_x_places x INNER JOIN ca_places ON (x.place_left_id = ca_places.place_id) WHERE x.place_right_id = {$obj_id} AND ca_places.deleted = 0";
            break;
        default:
            # code...
            break;
    }


    $m_places = array();
    $all_ent = $mysql->query($q_relation);
    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_places");
        // if (!isset($m_entities[$label])) {
            $m_places[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 72),
                'access' => getAccess($row['rel_id'], 72),
                'relation' => getMongoRelation($row['rel_id'], "ca_places"),
            );
        // }
    }


    return $m_places;
}

function recuperoOccorrenzeRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

    switch ($typeRelation) {
        case 'object':
            $q_relation    = "SELECT x.occurrence_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_occurrences.type_id as 'typ' FROM ca_objects_x_occurrences x INNER JOIN ca_occurrences ON (ca_occurrences.occurrence_id = x.occurrence_id) WHERE x.object_id = {$obj_id} AND ca_occurrences.deleted = 0";
            break;
        case 'entity':
            $q_relation    = "SELECT x.occurrence_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_occurrences.type_id as 'typ' FROM ca_entities_x_occurrences x INNER JOIN ca_occurrences ON (x.occurrence_id= ca_occurrences.occurrence_id) WHERE x.entity_id = {$obj_id} AND ca_occurrences.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.occurrence_right_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_occurrences.type_id as 'typ' FROM ca_occurrences_x_occurrences x INNER JOIN ca_occurrences ON (x.occurrence_right_id= ca_occurrences.occurrence_id) WHERE x.occurrence_left_id = {$obj_id} AND ca_occurrences.deleted = 0";
            break;
        case 'place':
            $q_relation = "SELECT x.occurrence_id as 'rel_id', x.type_id as 'type', ca_occurrences.type_id as 'typ' FROM ca_places_x_occurrences x INNER JOIN ca_occurrences ON (x.occurrence_id = ca_occurrences.occurrence_id) WHERE place_id = {$obj_id} AND ca_occurrences.deleted = 0";
            break;
        default:
            # code...
            break;
    }



    $m_occurrences = array();
    $all_ent = $mysql->query($q_relation);

    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_occurrences");
        // if (!isset($m_entities[$label])) {
            $m_occurrences[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 67),
                'access' => getAccess($row['rel_id'], 67),
                'relation' => getMongoRelation($row['rel_id'], "ca_occurrences"),
                'type_id' => getTypeName($row['typ'])
            );
        // }
    }

    return $m_occurrences;

}


function recuperoStorage_locationRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

        switch ($typeRelation) {
        case 'object':
            $q_relation = "SELECT x.location_id as 'rel_id', x.type_id as 'type', ca_storage_locations.type_id as 'typ' FROM ca_objects_x_storage_locations x INNER JOIN ca_storage_locations ON (x.location_id = ca_storage_locations.location_id) WHERE x.object_id = {$obj_id} AND ca_storage_locations.deleted = 0";
            break;
        case 'entity':
            $q_relation = "SELECT x.location_id as 'rel_id', x.type_id as 'type', ca_storage_locations.type_id as 'typ' FROM ca_entities_x_storage_locations x INNER JOIN ca_storage_locations ON (x.location_id = ca_storage_locations.location_id) WHERE x.entity_id = {$obj_id} AND ca_storage_locations.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.location_id as 'rel_id', x.type_id as 'type', ca_storage_locations.type_id as 'typ' FROM ca_occurrences_x_storage_locations x INNER JOIN ca_storage_locations ON (x.location_id = ca_storage_locations.location_id) WHERE x.occurrence_id = {$obj_id} AND ca_storage_locations.deleted = 0";
            break;
        case 'place':
            $q_relation = "SELECT x.location_id as 'rel_id', x.type_id as 'type', ca_storage_locations.type_id as 'typ' FROM ca_places_x_storage_locations x INNER JOIN ca_storage_locations ON (x.location_id = ca_storage_locations.location_id) WHERE x.place_id = {$obj_id} AND ca_storage_locations.deleted = 0";
            break;
        default:
            # code...
            break;
    }


    $m_storage_locations = array();
    $all_ent = $mysql->query($q_relation);
    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_storage_locations");
        // if (!isset($m_entities[$label])) {
            $m_storage_locations[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 89),
                'access' => getAccess($row['rel_id'], 89),
                'relation' => getMongoRelation($row['rel_id'], "ca_storage_locations"),
                'type_id' => getTypeName($row['typ'])
            );
        // }
    }

    return $m_storage_locations;

}

function recuperoOggettiRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

        switch ($typeRelation) {
        case 'object':
            $q_relation = "SELECT x.object_right_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_objects.type_id as 'typ' FROM ca_objects_x_objects x INNER JOIN ca_objects ON (ca_objects.object_id = x.object_right_id) WHERE x.object_left_id = {$obj_id} AND ca_objects.deleted = 0";
            break;
        case 'entity':
            $q_relation = "SELECT x.object_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_objects.type_id as 'typ' FROM ca_objects_x_entities x INNER JOIN ca_objects ON (x.object_id = ca_objects.object_id) WHERE x.entity_id = {$obj_id} AND ca_objects.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.object_id as 'rel_id', x.type_id as 'type', ca_objects.type_id as 'typ' FROM ca_objects_x_occurrences x INNER JOIN ca_objects ON (x.object_id = ca_objects.object_id) WHERE x.occurrence_id = {$obj_id} AND ca_objects.deleted = 0";
            break;
        case 'place':
            $q_relation = "SELECT x.object_id as 'rel_id', x.type_id as 'type', ca_objects.type_id as 'typ' FROM ca_objects_x_places x INNER JOIN ca_objects ON (ca_objects.object_id = x.object_id) WHERE ca_objects.deleted = 0 AND  x.place_id = {$obj_id}";
            break;
        case 'object_left':
            $q_relation = "SELECT x.object_left_id as 'rel_id', x.rank as 'rank', x.type_id as 'type', ca_objects.type_id as 'typ' FROM ca_objects_x_objects x INNER JOIN ca_objects ON (ca_objects.object_id = x.object_left_id) WHERE x.object_right_id = {$obj_id} AND ca_objects.deleted = 0";
            break;
        default:
            # code...
            break;
    }


    $m_objects = array();
    $all_ent = $mysql->query($q_relation);
    while ($row = $all_ent->fetch_assoc()) {
        $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_objects");
        // if (!isset($m_entities[$label])) {
            $m_objects[$label][] = array(
                'id' => $row['rel_id'],
                'name' => getRelationName($row['rel_id'], 57),
                'access' => getAccess($row['rel_id'], 57),
                'relation' => getMongoRelation($row['rel_id'], "ca_objects"),
                'type_id' => getTypeName($row['typ'])
            );
        // }
    }

    return $m_objects;

}

function recuperoVocabularyRelazioni($table_num,$obj_id,$typeRelation){
    global $mysql;

        switch ($typeRelation) {
        case 'object':
            $q_relation = "SELECT x.item_id as 'rel_id', x.type_id as 'type' FROM ca_objects_x_vocabulary_terms x INNER JOIN ca_list_items ON (ca_list_items.item_id = x.item_id) WHERE x.object_id = {$obj_id} AND ca_list_items.deleted = 0";
            break;
        case 'entity':
            $q_relation = "SELECT x.item_id as 'rel_id', x.type_id as 'type' FROM ca_entities_x_vocabulary_terms x INNER JOIN ca_list_items ON (ca_list_items.item_id = x.item_id) WHERE x.entity_id = {$obj_id} AND ca_list_items.deleted = 0";
            break;
        case 'occurrence':
            $q_relation = "SELECT x.item_id as 'rel_id', x.type_id as 'type' FROM ca_occurrences_x_vocabulary_terms x INNER JOIN ca_list_items ON (ca_list_items.item_id = x.item_id) WHERE x.occurrence_id = {$obj_id} AND ca_list_items.deleted = 0";
            break;
        default:
            # code...
            break;
    }


    $m_vocabulary = array();
    $aggregatore = array();
    $all_ent = $mysql->query($q_relation);




    while ($row = $all_ent->fetch_assoc()) {
      $label = getRelationLabel($row['type'], $table_num, $obj_id, "ca_list_items");
      $aggr  = isAggregatore($row['rel_id']);

      if($aggr){

          $aggregatore = array(
              'name' => getRelationName($row['rel_id'], 33),
              'id' => $row['rel_id']
              );

      }else{
            $name = getRelationName($row['rel_id'], 33);
            $m_vocabulary[$label][] = array(
                'id' => $row['rel_id'],
                'name' => $name,
                'listItem' => getListCode($row['rel_id']),
                'relation' => getMongoRelation($row['rel_id'], "ca_list_items"),
                'type_id' => $row['type'],
                'parent_path' => end(calculatePath($row['rel_id'], $name))
            );

        }
    }

    return $m_vocabulary;

}



/**
 * Gestione di un attributo
 * @param $attr_id
 * @param $meta_id
 * @param $table_num
 * @param $obj_type
 * @return array|null
 */
function manageAttribute($attr_id, $meta_id, $table_num, $obj_type, $obj_id)
{
    // echo getMetadataType($meta_id).' '.$meta_id.'     ';
    switch (getMetadataType($meta_id)) {
        case 'container':
            return manageContainer($attr_id, $meta_id, $table_num, $obj_type);
        case 'datarange':
            return manageDate($attr_id, $table_num, $obj_type);
        case 'list':
            return manageList($attr_id, $table_num, $obj_type);
        case 'geocode':
            return manageGeoCode($attr_id, $table_num, $obj_type);
        case "objectrepresentations":
            return manageRelations($attr_id, 56, $obj_type);
        case "entities":
            return manageRelations($attr_id, 20, $obj_type);
        case "places":
            return manageRelations($attr_id, 72, $obj_type);
        case "occurrences":
            return manageRelations($attr_id, 67, $obj_type);
        case "collections":
            return manageRelations($attr_id, 13, $obj_type);
        case "storagelocations":
            return manageRelations($attr_id, 89, $obj_type);
        case "loans":
            return manageRelations($attr_id, 133, $obj_type);
        case "movements":
            return manageRelations($attr_id, 137, $obj_type);
        case "objects":
            return manageRelations($attr_id, 57, $obj_type);
        case "objectlots":
            return manageRelations($attr_id, 51, $obj_type);
        default:
            return manageDefault($attr_id, $table_num, $obj_type, $obj_type);
    }
}

/**
 * [manageDefault description]
 * @param $attr_id
 * @param $table_num
 * @param $obj_type
 * @return array [type]          [description]
 * @internal param $ [type] $attr_id [description]
 */
function manageDefault($attr_id, $table_num, $obj_type)
{
    global $mysql;

    $q_attribute_value = <<<QUERY
    SELECT
        v.element_id as 'element_id',
        v.value_longtext1 as 'value'
    FROM ca_attribute_values v
    WHERE v.attribute_id = {$attr_id}
QUERY;


    $result=null;
    $n = '';
    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {
        if ($row['value'] == null) return null;

        $result = array(
            'label' => getLabel($row['element_id'], $table_num, $obj_type),
            'value' => $row['value'],
            'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            // 'visibile' => getVisibilita($row['element_id'], $table_num, $obj_type)
        );


    }
    return $result;
}

/**
 * [manageDate description]
 * @param $attr_id
 * @param $table_num
 * @param $obj_type
 * @return array|null [type]          [description]
 * @internal param $ [type] $attr_id [description]
 */
function manageDate($attr_id, $table_num, $obj_type)
{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
    v.element_id as 'element_id',
    v.value_longtext1 as 'value',
    v.value_decimal1 as 'decimal1',
    v.value_decimal2 as 'decimal2'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {
        if ($row['value'] == null) return null;

        $start = trasformData($row['decimal1']);
        $end = trasformData($row['decimal2']);

        if ($start["year"] == "" || $start["year"] == null) {
            $data_container = array(
                'label' => getLabel($row['element_id'], $table_num, $obj_type),
                'value' => $row['value'],
                'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            );
            break;
        }

        $start = strtotime($start['month'] . "/" . $start['day'] . "/" . $start['year'] . " " . $start['hour'] . ":" . $start['minute'] . ":" . $start['second']) + 3600;
        $end = strtotime($end['month'] . "/" . $end['day'] . "/" . $end['year'] . " " . $end['hour'] . ":" . $end['minute'] . ":" . $end['second']);


        $range = array();
        $sY = date('Y', $start);
        $eY = date('Y', $end);


        $startOfDecade  = intval(substr($sY, 0, 3)."0");
        $endOfDecade    = $eY - ( $eY % 10 ) + ($eY % 10 ? 10 : 0);

        for ($i = 0; $i < (($endOfDecade - $startOfDecade) / 10) ; $i++ )  {
            array_push($range, $startOfDecade  + ($i * 10));
        }

        $data_container = array(
            'label' => getLabel($row['element_id'], $table_num, $obj_type),
            'value' => $row['value'],
            'start' => new MongoDate($start),
            'end' => new MongoDate($end),
            'range' => $range,
            'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            // 'visibile' => getVisibilita($row['element_id'], $table_num, $obj_type)
        );
        break;
    }
    return $data_container;
}

/**
 * [manageList description]
 * @param  [type] $attr_id [description]
 * @return [type]          [description]
 */
function manageList($attr_id, $table_num, $obj_type)
{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
    v.element_id as 'element_id',
    v.item_id as 'item_id'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
AND v.item_id IS NOT NULL
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {

        $result = array(
            'label' => getLabel($row['element_id'], $table_num, $obj_type),
            'value' => getListValue($row['item_id']),
            'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            // 'visibile' => getVisibilita($row['element_id'], $table_num, $obj_type)
        );
    }
    return $result;
}

/**
 * [manageRelations description]
 * @param  [type] $attr_id   [description]
 * @param  [type] $table_num [description]
 * @return [type]            [description]
 */
function manageRelations($attr_id, $table_num, $obj_type)
{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
    v.element_id as 'element_id',
    v.value_longtext1 as 'value'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {
        if ($row['value'] == null) return null;

        $result = array(
            'label' => getLabel($row['element_id'], $table_num, $obj_type),
            'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            // 'visibile' => getVisibilita($row['element_id'], $table_num, $obj_type),
            'id' => $row['value'],
            'name' => getRelationName($row['value'], $table_num),
            'relation' => getMongoRelation($row['value'], getTableName($table_num))
        );
    }

    return $result;
}

/**
 * [manafeGeoCode description]
 * @param  [type] $attr_id [description]
 * @return [type]          [description]
 */
function manageGeoCode($attr_id, $table_num, $obj_type)
{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
    v.element_id as 'element_id',
    v.value_longtext1 as 'value',
    v.value_decimal1 as 'decimal1',
    v.value_decimal2 as 'decimal2'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {
        $result = array(
            'label' => getLabel($row['element_id'], $table_num, $obj_type),
            'ordine' => getOrdine($row['element_id'], $table_num, $obj_type),
            // 'visibile' => getVisibilita($row['element_id'], $table_num, $obj_type),
            'mongocord' => array(
                'type' => "Point",
                'coordinates' => array($row['decimal1'], $row['decimal2']),
            ),
            'name' => $row['value'],
            'cord' => array(
                'lat' => $row['decimal1'],
                'lon' => $row['decimal2']
            )
        );
    }

    return $result;
}

/**
 * [manageContainer description]
 * @param  [type] $attr_id [description]
 * @return [type]          [description]
 */
function manageContainer($attr_id, $meta_id, $table_num, $obj_type)
{
    global $mysql;

    $container = array();

    $q_attribute_value = <<<QUERY
SELECT
    v.element_id as 'element_id',
    v.item_id as 'item_id',
    v.value_longtext1 as 'value',
    v.value_decimal1 as 'decimal1',
    v.value_decimal2 as 'decimal2'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($row = $r->fetch_assoc()) {
        $elem_id = $row['element_id'];
        $decimal1 = $row['decimal1'];
        $decimal2 = $row['decimal2'];
        $value = $row['value'];
        $item_id = $row['item_id'];

        if ($value == null) {
            continue;
        }


        switch (getMetadataType($elem_id)) {
            case 'datarange':
                $start = trasformData($decimal1);
                $end = trasformData($decimal2);

                if ($start["year"] == "" || $start["year"] == null) {
                    $result = array(
                        'label' => getLabel($elem_id, $table_num, $obj_type),
                        'value' => $value
                    );
                    break;
                }

                $start = strtotime($start['month'] . "/" . $start['day'] . "/" . $start['year'] . " " . $start['hour'] . ":" . $start['minute'] . ":" . $start['second']) + 3600;
                $end = strtotime($end['month'] . "/" . $end['day'] . "/" . $end['year'] . " " . $end['hour'] . ":" . $end['minute'] . ":" . $end['second']);


                $range = array();
                $sY = date('Y', $start);
                $eY = date('Y', $end);


                $startOfDecade  = intval(substr($sY, 0, 3)."0");
                $endOfDecade    = $eY - ( $eY % 10 ) + ($eY % 10 ? 10 : 0);

                if ($startOfDecade == $endOfDecade) {
                    array_push($range, $startOfDecade);
                } else {

                    for ($i = 0; $i < (($endOfDecade - $startOfDecade) / 10); $i++) {
                        array_push($range, $startOfDecade + ($i * 10));
                    }
                }         

                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'value' => $value,
                    'start' => new MongoDate($start),
                    'range' => $range,
                    'end' => new MongoDate($end)
                );
                break;
            case 'list':
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'value' => getListValue($item_id)
                );
                break;
            case 'geocode':
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'mongocord' => array(
                        'type' => "Point",
                        'coordinates' => array($decimal1, $decimal2),
                    ),
                    'name' => $value,
                    'cord' => array(
                        'lat' => $decimal1,
                        'lon' => $decimal2
                    )
                );
                break;
            case "objectrepresentations":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 56),
                    'relation' => getMongoRelation($value, getTableName(56))
                );
                break;
            case "entities":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 20),
                    'relation' => getMongoRelation($value, getTableName(20))
                );
                break;
            case "places":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 72),
                    'relation' => getMongoRelation($value, getTableName(72))
                );
                break;
            case "occurrences":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'ordine' => getOrdine($elem_id, $table_num, $obj_type),
                    // 'visibile' => getVisibilita($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 67),
                    'relation' => getMongoRelation($value, getTableName(67))
                );
                break;
            case "collections":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 13),
                    'relation' => getMongoRelation($value, getTableName(13))
                );
                break;
            case "storagelocations":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 89),
                    'relation' => getMongoRelation($value, getTableName(89))
                );
                break;
            case "loans":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 133),
                    'relation' => getMongoRelation($value, getTableName(133))
                );
                break;
            case "movements":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 137),
                    'relation' => getMongoRelation($value, getTableName(137))
                );
                break;
            case "objects":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 57),
                    'relation' => getMongoRelation($value, getTableName(57))
                );
                break;
            case "objectlots":
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'id' => $value,
                    'name' => getRelationName($value, 51),
                    'relation' => getMongoRelation($value, getTableName(51))
                );
                break;
            default:
                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'value' => $value
                );
        }
        $container[getMetadataCode($elem_id)] = $result;
    }

    if (!empty($container)) {
        $container['label'] = getLabel($meta_id, $table_num, $obj_type);
        $container['ordine'] = getOrdine($meta_id, $table_num, $obj_type);
        // $container['visibile'] = getVisibilita($meta_id, $table_num, $obj_type);
        return $container;
    }

    return null;
}


function getAccess($id,$table_num){


    global $mysql;

    $n = '';

    switch ($table_num) {
        case 56:
            $q_representation_name = "SELECT access as 'access' FROM ca_object_representations WHERE representation = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 20:
            $q_representation_name = "SELECT access as 'access' FROM ca_entities WHERE entity_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 72:
            $q_representation_name = "SELECT access as 'access' FROM ca_places WHERE place_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 67:
            $q_representation_name = "SELECT access as 'access' FROM ca_occurrences WHERE occurrence_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 13:
            $q_representation_name = "SELECT access as 'access' FROM ca_collections WHERE collection_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 89:
            $q_representation_name = "SELECT access as 'access' FROM ca_storage_locations WHERE location_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
         case 133:
             $q_representation_name = "SELECT access as 'access' FROM ca_loans WHERE loan_id = {$id}";
             $n = '';
             $r = $mysql->query($q_representation_name);
             while ($row = $r->fetch_assoc()) {
                 $n = $row['access'];
             }
             break;
         case 137:
             $q_representation_name = "SELECT access as 'access' FROM ca_movements WHERE movement_id = {$id}";
             $n = '';
             $r = $mysql->query($q_representation_name);
             while ($row = $r->fetch_assoc()) {
                 $n = $row['access'];
             }
             break;
        case 57:
            $q_representation_name = "SELECT access as 'access' FROM ca_objects WHERE object_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
        case 51:
            $q_representation_name = "SELECT access as 'access' FROM ca_object_lots WHERE lot_id = {$id}";
            $n = '';
            $r = $mysql->query($q_representation_name);
            while ($row = $r->fetch_assoc()) {
                $n = $row['access'];
            }
            break;
    }

    return $n;


}


function creaSearch($mongo, $id, $table){

//TODO, define the fields to query OR the key we have to avoid

    //define the var to return
    $search_string = "";

    //do the mongo query

    $item = $mongo->data->find(array(
        'id' => $id,
        'table' => $table
    ));

    //set search string
    foreach ($item as $key => $value) {

        array_walk_recursive($value, function($item, $key) use(&$search_string) {
            if($key == "value" || $key == "name"){

                $search_string .= normalizeChars($item)." ";

            }
        });

    }

    return $search_string;
}

function CreateTemaFilter($occurrences){

    $filtri = [array('path' => '/Tema',
                         'name' => 'Tema',
                         'id' => '0'  )];

    foreach ($occurrences as $key => $value) {
        $filtri[] = array('path' => "/Tema/{$value['name']}",
                          'name' => $value['name'],
                          'id' => $value['id']
                            );
    }
    return $filtri;

}

function normalizeChars($s) {
    $replace = array(
        'ъ'=>'-', 'Ь'=>'-', 'Ъ'=>'-', 'ь'=>'-',
        'Ă'=>'A', 'Ą'=>'A', 'À'=>'A ', 'Ã'=>'A', 'Á'=>'A', 'Æ'=>'A', 'Â'=>'A', 'Å'=>'A', 'Ä'=>'Ae',
        'Þ'=>'B',
        'Ć'=>'C', 'ץ'=>'C', 'Ç'=>'C',
        'È'=>'E', 'Ę'=>'E', 'É'=>'E', 'Ë'=>'E', 'Ê'=>'E',
        'Ğ'=>'G',
        'İ'=>'I', 'Ï'=>'I', 'Î'=>'I', 'Í'=>'I', 'Ì'=>'I',
        'Ł'=>'L',
        'Ñ'=>'N', 'Ń'=>'N',
        'Ø'=>'O', 'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe',
        'Ş'=>'S', 'Ś'=>'S', 'Ș'=>'S', 'Š'=>'S',
        'Ț'=>'T',
        'Ù'=>'U', 'Û'=>'U', 'Ú'=>'U', 'Ü'=>'Ue',
        'Ý'=>'Y',
        'Ź'=>'Z', 'Ž'=>'Z', 'Ż'=>'Z',
        'â'=>'a', 'ǎ'=>'a', 'ą'=>'a', 'á'=>'a', 'ă'=>'a', 'ã'=>'a', 'Ǎ'=>'a', 'а'=>'a', 'А'=>'a', 'å'=>'a', 'à'=>'a', 'א'=>'a', 'Ǻ'=>'a', 'Ā'=>'a', 'ǻ'=>'a', 'ā'=>'a', 'ä'=>'ae', 'æ'=>'ae', 'Ǽ'=>'ae', 'ǽ'=>'ae',
        'б'=>'b', 'ב'=>'b', 'Б'=>'b', 'þ'=>'b',
        'ĉ'=>'c', 'Ĉ'=>'c', 'Ċ'=>'c', 'ć'=>'c', 'ç'=>'c', 'ц'=>'c', 'צ'=>'c', 'ċ'=>'c', 'Ц'=>'c', 'Č'=>'c', 'č'=>'c', 'Ч'=>'ch', 'ч'=>'ch',
        'ד'=>'d', 'ď'=>'d', 'Đ'=>'d', 'Ď'=>'d', 'đ'=>'d', 'д'=>'d', 'Д'=>'d', 'ð'=>'d',
        'є'=>'e', 'ע'=>'e', 'е'=>'e', 'Е'=>'e', 'Ə'=>'e', 'ę'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'Ē'=>'e', 'Ė'=>'e', 'ė'=>'e', 'ě'=>'e', 'Ě'=>'e', 'Є'=>'e', 'Ĕ'=>'e', 'ê'=>'e', 'ə'=>'e', 'è'=>'e', 'ë'=>'e', 'é'=>'e',
        'ф'=>'f', 'ƒ'=>'f', 'Ф'=>'f',
        'ġ'=>'g', 'Ģ'=>'g', 'Ġ'=>'g', 'Ĝ'=>'g', 'Г'=>'g', 'г'=>'g', 'ĝ'=>'g', 'ğ'=>'g', 'ג'=>'g', 'Ґ'=>'g', 'ґ'=>'g', 'ģ'=>'g',
        'ח'=>'h', 'ħ'=>'h', 'Х'=>'h', 'Ħ'=>'h', 'Ĥ'=>'h', 'ĥ'=>'h', 'х'=>'h', 'ה'=>'h',
        'î'=>'i', 'ï'=>'i', 'í'=>'i', 'ì'=>'i', 'į'=>'i', 'ĭ'=>'i', 'ı'=>'i', 'Ĭ'=>'i', 'И'=>'i', 'ĩ'=>'i', 'ǐ'=>'i', 'Ĩ'=>'i', 'Ǐ'=>'i', 'и'=>'i', 'Į'=>'i', 'י'=>'i', 'Ї'=>'i', 'Ī'=>'i', 'І'=>'i', 'ї'=>'i', 'і'=>'i', 'ī'=>'i', 'ĳ'=>'ij', 'Ĳ'=>'ij',
        'й'=>'j', 'Й'=>'j', 'Ĵ'=>'j', 'ĵ'=>'j', 'я'=>'ja', 'Я'=>'ja', 'Э'=>'je', 'э'=>'je', 'ё'=>'jo', 'Ё'=>'jo', 'ю'=>'ju', 'Ю'=>'ju',
        'ĸ'=>'k', 'כ'=>'k', 'Ķ'=>'k', 'К'=>'k', 'к'=>'k', 'ķ'=>'k', 'ך'=>'k',
        'Ŀ'=>'l', 'ŀ'=>'l', 'Л'=>'l', 'ł'=>'l', 'ļ'=>'l', 'ĺ'=>'l', 'Ĺ'=>'l', 'Ļ'=>'l', 'л'=>'l', 'Ľ'=>'l', 'ľ'=>'l', 'ל'=>'l',
        'מ'=>'m', 'М'=>'m', 'ם'=>'m', 'м'=>'m',
        'ñ'=>'n', 'н'=>'n', 'Ņ'=>'n', 'ן'=>'n', 'ŋ'=>'n', 'נ'=>'n', 'Н'=>'n', 'ń'=>'n', 'Ŋ'=>'n', 'ņ'=>'n', 'ŉ'=>'n', 'Ň'=>'n', 'ň'=>'n',
        'о'=>'o', 'О'=>'o', 'ő'=>'o', 'õ'=>'o', 'ô'=>'o', 'Ő'=>'o', 'ŏ'=>'o', 'Ŏ'=>'o', 'Ō'=>'o', 'ō'=>'o', 'ø'=>'o', 'ǿ'=>'o', 'ǒ'=>'o', 'ò'=>'o', 'Ǿ'=>'o', 'Ǒ'=>'o', 'ơ'=>'o', 'ó'=>'o', 'Ơ'=>'o', 'œ'=>'oe', 'Œ'=>'oe', 'ö'=>'oe',
        'פ'=>'p', 'ף'=>'p', 'п'=>'p', 'П'=>'p',
        'ק'=>'q',
        'ŕ'=>'r', 'ř'=>'r', 'Ř'=>'r', 'ŗ'=>'r', 'Ŗ'=>'r', 'ר'=>'r', 'Ŕ'=>'r', 'Р'=>'r', 'р'=>'r',
        'ș'=>'s', 'с'=>'s', 'Ŝ'=>'s', 'š'=>'s', 'ś'=>'s', 'ס'=>'s', 'ş'=>'s', 'С'=>'s', 'ŝ'=>'s', 'Щ'=>'sch', 'щ'=>'sch', 'ш'=>'sh', 'Ш'=>'sh', 'ß'=>'ss',
        'т'=>'t', 'ט'=>'t', 'ŧ'=>'t', 'ת'=>'t', 'ť'=>'t', 'ţ'=>'t', 'Ţ'=>'t', 'Т'=>'t', 'ț'=>'t', 'Ŧ'=>'t', 'Ť'=>'t', '™'=>'tm',
        'ū'=>'u', 'у'=>'u', 'Ũ'=>'u', 'ũ'=>'u', 'Ư'=>'u', 'ư'=>'u', 'Ū'=>'u', 'Ǔ'=>'u', 'ų'=>'u', 'Ų'=>'u', 'ŭ'=>'u', 'Ŭ'=>'u', 'Ů'=>'u', 'ů'=>'u', 'ű'=>'u', 'Ű'=>'u', 'Ǖ'=>'u', 'ǔ'=>'u', 'Ǜ'=>'u', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'У'=>'u', 'ǚ'=>'u', 'ǜ'=>'u', 'Ǚ'=>'u', 'Ǘ'=>'u', 'ǖ'=>'u', 'ǘ'=>'u', 'ü'=>'ue',
        'в'=>'v', 'ו'=>'v', 'В'=>'v',
        'ש'=>'w', 'ŵ'=>'w', 'Ŵ'=>'w',
        'ы'=>'y', 'ŷ'=>'y', 'ý'=>'y', 'ÿ'=>'y', 'Ÿ'=>'y', 'Ŷ'=>'y',
        'Ы'=>'y', 'ž'=>'z', 'З'=>'z', 'з'=>'z', 'ź'=>'z', 'ז'=>'z', 'ż'=>'z', 'ſ'=>'z', 'Ж'=>'zh', 'ж'=>'zh'
    );

    return strtr($s, $replace);
}
