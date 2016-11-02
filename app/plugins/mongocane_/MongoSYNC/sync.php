<?php
/**
 * Sincronizzatore di MongoDB per il Polo del 900
 * 
 * 2015/10/30
 * 
 * @version 0.1
 * @author Luca Montanera <luca.montanera@promemoriagroup.com>
 * @copyright Promemoria
 */

global $mysql, $mongo;

function ca_objects($id, $collection, $mysqli)   {
    global $mongo;
    global $mysql;
    $mongo = $collection;
    $mysql = $mysqli;
    include_once __CA_APP_DIR__."/plugins/mongocane/MongoSYNC/function.php";
    include_once __CA_APP_DIR__."/plugins/mongocane/MongoSYNC/exception.php";
    $q_all_obj = <<<QUERY
    SELECT
    	o.object_id as 'id',
    	o.parent_id as 'parent',
    	o.type_id as 'type',
    	o.idno as 'idno',
    	o.access as 'access',
    	l.name as 'preferred_label',
    	l.type_id as 'label_type'
    FROM ca_objects o
    INNER JOIN ca_object_labels l
    ON (o.object_id = l.object_id)
    WHERE l.is_preferred = 1
    AND o.deleted = 0
    AND o.object_id = {$id}
QUERY;

  $all_obj = $mysql->query($q_all_obj);

    while ($all_obj->nextRow()) {
        $obj_id = $all_obj->get('id');
        $parent_id = $all_obj->get('parent');
        $obj_type = $all_obj->get('type');
        $idno = $all_obj->get('idno');
        $access = $all_obj->get('access');
        $name = $all_obj->get('preferred_label');
        $name_type = $all_obj->get('label_type');
        $table_num  = 57;
    	// Informazioni principali
    	$m_id = (int) $obj_id;
    	if ($parent_id != null) {
            $m_parent = array(
                'id' => (int)$parent_id,
                'relation' => getMongoRelation($parent_id, "ca_objects")
            );
        }
    	$m_type_id = getTypeName($obj_type);
    	$m_access = (int) $access;
    	$m_ente = getEnteName($obj_id, $table_num);
        $m_info_ente = getInfoEnte($obj_id);
        $m_name['name'] = ($name);
        if ($name_type)
            $m_name['type'] = getTypeName($name_type);

    	$m_idno = $idno;
    	$m_alternative_names = getAlternativeObjNames($obj_id);

    	// Gestione degli attributi

        $q_all_attribute = <<<QUERY
        SELECT
            a.attribute_id as 'id',
            a.element_id as 'attribute_element',
            element_code as 'code'
        FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
        WHERE a.row_id = {$obj_id}
        AND	a.table_num = {$table_num}
QUERY;
        $all_attr = $mysql->query($q_all_attribute);
    	$attributes = array();
    	while ($all_attr->nextRow()) {
            $attr_id = $all_attr->get('id');
            $attr_elem = $all_attr->get('attribute_element');
            $elem_code = $all_attr->get('code');

            if (!toInsert($elem_code, $obj_type, $table_num))
                continue;

    		// Se non esiste ancora lo creo come oggetto.
    		if (!isset($attributes[$elem_code]))	{
                $attr = manageAttribute($attr_id, $attr_elem, $table_num, $obj_type);
                if ($attr)
    			    $attributes[$elem_code] = $attr;
    		} else {
    			// Se invece esiste già allora lo re-istanzio come array di oggetti
    			$old_attr = $attributes[$elem_code]; // salvo il vecchio valore
    			unset($attributes[$elem_code]); // resetto l'elemento
    			$attributes[$elem_code][] = $old_attr; // ricreo l'elemento
    												  // e inserisco il vecchio valore wrappato in una array
    			$attributes[$elem_code][] = manageAttribute($attr_id, $attr_elem, $table_num, $obj_type); // aggiungo il nuovo valore
    		}
    	}

    	// Recupero dei media
        $m_media = array();
        $m_media['primary'] = getPrimaryImg($obj_id, $table_num);
        $m_media['all'] = getAllMedia($obj_id, $table_num);

        $m_hasMedia = (isset($m_media['all']) && count($m_media['all']) > 0) ? 'Ha media' : 'Non ha media';

    	/*
    		Recupero delle relazioni
    	 */
    	
    	// Entities
        /** @var String $q_ca_objects_x_entities */
        $q_relation = "SELECT entity_id as 'rel_id', type_id as 'type' FROM ca_objects_x_entities WHERE object_id = {$obj_id}";
        $m_entities = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_entities");
            if (!isset($m_entities[$label]))	{
                $m_entities[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 20),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_entities")
                );
            }
        }

    	// Collections
        $q_relation = "SELECT collection_id as 'rel_id', type_id as 'type' FROM ca_objects_x_collections WHERE object_id = {$obj_id}";
        $m_collections = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_collections");
            if (!isset($m_collections[$label]))	{
                $m_collections[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 13),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_collections")
                );
            }
        }

    	// Places
        $q_relation = "SELECT place_id as 'rel_id', type_id as 'type' FROM ca_objects_x_places WHERE object_id = {$obj_id}";
        $m_places = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_places");
            if (!isset($m_places[$label]))	{
                $m_places[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 72),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_places")
                );
            }
        }


    	// Occurrences
        $q_relation = "SELECT occurrence_id as 'rel_id', type_id as 'type' FROM ca_objects_x_occurrences WHERE object_id = {$obj_id}";
        $m_occurrences = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_occurrences");
            if (!isset($m_occurrences[$label]))	{
                $m_occurrences[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 67),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_occurrences")
                );
            }
        }

    	// storage_locations
        $q_relation = "SELECT location_id as 'rel_id', type_id as 'type' FROM ca_objects_x_storage_locations WHERE object_id = {$obj_id}";
        $m_storage_locations = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_storage_locations");
            if (!isset($m_storage_locations[$label]))	{
                $m_storage_locations[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 89),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_storage_locations")
                );
            }
        }

    	// Objects
        $q_relation = "SELECT object_right_id as 'rel_id', type_id as 'type' FROM ca_objects_x_objects WHERE object_left_id = {$obj_id}";
        $m_objects = array();
        $all_ent = $mysql->query($q_relation);
        while ($all_ent->nextRow()) {
            $label = getRelationLabel($all_ent->get('type'), $table_num, $obj_id, "ca_objects");
            if (!isset($m_objects[$label]))	{
                $m_objects[$label] = array(
                    'id' => $all_ent->get('rel_id'),
                    'name' => getRelationName($all_ent->get('rel_id'), 57),
                    'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_objects")
                );
            }
        }

    	/*
    		Generazione della struttura di Mongo
    	*/
    	$mongo_record = array();

    	$mongo_record['id'] = $m_id;
    	$mongo_record['parent'] = $m_parent;
        if ($m_ente) $mongo_record['ente'] = $m_ente;
    	$mongo_record['access'] = $m_access;
        if ($m_info_ente) $mongo_record['info_ente'] = $m_info_ente;
    	$mongo_record['preferred_label'] = $m_name;
        if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;
    	$mongo_record['idno'] = $m_idno;
    	$mongo_record['type_id'] = $m_type_id;

        if (!$ignore_media && $m_media['primary'] != null)  {
    	    $mongo_record['representations'] = $m_media;
        }
        $mongo_record['hasMedia'] = $m_hasMedia;

    	foreach ($attributes as $attr_code => $attribute)
    		$mongo_record[$attr_code] = $attribute;

        $related = array();

        if (!empty($m_entities))
            $related['ca_entities'] = $m_entities;
        if (!empty($m_places))
            $related['ca_places'] = $m_places;
        if (!empty($m_occurrences))
            $related['ca_occurrences'] = $m_occurrences;
        if (!empty($m_collections))
            $related['ca_collections'] = $m_collections;
        if (!empty($m_objects))
            $related['ca_objects'] = $m_objects;

        $mongo_record['related'] = $related;

    	
    	//Scheda breve
    	$m_scheda_breve = array();
    	$m_scheda_breve['Tipo oggetto'] = $m_type_id['name'];
    	$m_scheda_breve['Titolo'] = $m_name['name'];
        if (isset($m_name['type'])) {
            $m_scheda_breve['Titolo'] .= " (" . $m_name['type']['name'] . ")";
        }
    	if (isset($m_alternative_names) && !empty($m_alternative_names)) {
            $m_scheda_breve['Altro titolo'] = $m_alternative_names;
        }
    	if (isset($mongo_record['num_def_numero']))	{
    		$m_scheda_breve['Segnatura'] = $mongo_record['num_def_numero']['value'];
    		if (isset($mongo_record['num_def_bis'])) {
                $m_scheda_breve['Segnatura'] .= " " . $mongo_record['num_def_bis']['value'];
            }
    	}
        if (isset($mongo_record['ntc']) )	{
    		if (isset($mongo_record['ntc']['nctr'])) {
                $m_scheda_breve['Codice'] = $mongo_record['ntc']['nctr']['value'];
            }
    		if (isset($mongo_record['ntc']['nctn'])) {
                $m_scheda_breve['Codice'] .= " " . $mongo_record['ntc']['nctn']['value'];
            }
    		if (isset($mongo_record['ntc']['ncts'])) {
                $m_scheda_breve['Codice'] .= " " . $mongo_record['ntc']['ncts']['value'];
            }
    	}

    	if (isset($mongo_record['cronologia']))	{
    		if (isset($mongo_record['cronologia']['cron_inv']))	{
    			$m_scheda_breve['Data'] = $mongo_record['cronologia']['cron_inv']['value'];
    		} else if (isset($mongo_record['cronologia']['datazione'])) {
    			$m_scheda_breve['Data'] = $mongo_record['cronologia']['datazione']['value'];
    		}
    	}

    	if (isset($mongo_record['mtct']))	{
    		$m_scheda_breve['Tecnica'] = $mongo_record['mtct']['value'];
    	} else if (isset($mongo_record['mtc']))	{
    		$m_scheda_breve['Tecnica'] = $mongo_record['mtc']['value'];
    	}

    	if (isset($mongo_record['consistenza']))	{
    		if (isset($mongo_record['consistenza']['consistenza2']))	{
    			$m_scheda_breve['Consistenza'] = $mongo_record['consistenza']['consistenza2']['value'];
    		}
    		if (isset($mongo_record['consistenza']['tipo_consistenza']))	{
    			$m_scheda_breve['Consistenza'] .= " " . $mongo_record['consistenza']['tipo_consistenza']['value'];
    		}

    		if (isset($mongo_record['consistenza']['consistenza_specifica']))	{
    			$m_scheda_breve['Consistenza'] .= " " . $mongo_record['consistenza']['consistenza_specifica']['value'];
    		}
    	}

    	if (isset($mongo_record['description']))	{
    		$m_scheda_breve['desc']['Descrizione'] = $mongo_record['description'];
    	} else if(isset($mongo_record['dess1']))	{
    		$m_scheda_breve['desc']['Descrizione'] = $mongo_record['dess1'];
    	} else if (isset($mongo_record['drs']))	{
    		$m_scheda_breve['desc']['Descrizione'] = $mongo_record['drs'];
    	}

        if (isset($mongo_record['livello']))    {
            $m_scheda_breve['Livello (descrizione)'] = $mongo_record['livello'];
        }

        if (isset($mongo_record['sigla_cit']))  {
            $m_scheda_breve['Sigla per citazione'] = $mongo_record['sigla_cit'];
        }

    	$mongo_record['scheda_breve'] = $m_scheda_breve;
    	$mongo_record['language'] = 'italian';
        try {
            $mongo->remove(array('id' => $m_id));
    	    $mongo->insert($mongo_record);
        } catch (MongoException $e)  {
            error_log("Errore durante la connessione al mongo " . $e->getMessage());
        }
    }
} //Function

function ca_entities($id, $collection, $mysqli)   {
    global $mongo;
    global $mysql;
    $mongo = $collection;
    $mysql = $mysqli;
    include_once __CA_APP_DIR__."/plugins/mongocane/MongoSYNC/function.php";

    /**
    * Recupero tutte le entità
    */
    $q_all_ent = <<<QUERY
    SELECT
     e.entity_id as 'id',
     e.parent_id as 'parent',
     e.type_id as 'type',
     e.idno as 'idno',
     e.access as 'access',
     l.displayname as 'preferred_label',
     l.type_id as 'label_type'
    FROM ca_entities e
     INNER JOIN ca_entity_labels l
       ON (e.entity_id = l.entity_id)
    WHERE l.is_preferred = 1
         AND e.deleted = 0 
         AND e.entity_id = {$id}
QUERY;

  $all_enti = $mysql->query($q_all_ent);

    while ($all_enti->nextRow()) {
       $ent_id         = $all_enti->get('id');
       $parent_id      = $all_enti->get('parent');
       $ent_type       = $all_enti->get('type');
       $idno           = $all_enti->get('idno');
       $access         = $all_enti->get('access');
       $name           = $all_enti->get('preferred_label');
       $name_type      = $all_enti->get('label_type');
       $table_num      = 20;
       $m_id           = (int) $ent_id;
       $m_idno         = $idno;
       $m_access       = (int) $access;
       if ($parent_id != null) {
           $m_parent = array(
               'id' => (int) $parent_id,
               'relation' => getMongoRelation($parent_id, "ca_entities")
           );
       }
       $m_type_id           = getTypeName($ent_type);
       $m_ente              = getEnteName($ent_id, $table_num);
       $m_name              = $name;
       $m_alternative_names = getAlternativeEntNames($ent_id);

       // Gestione degli attributi

       $q_all_attribute = <<<QUERY
       SELECT
           a.attribute_id as 'id',
           a.element_id as 'attribute_element',
           element_code as 'code'
       FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
       WHERE a.row_id = {$ent_id}
       AND  a.table_num = {$table_num}
QUERY;
       $all_attr = $mysql->query($q_all_attribute);
       $attributes = array();
       while ($all_attr->nextRow()) {
           $attr_id   = $all_attr->get('id');
           $attr_elem = $all_attr->get('attribute_element');
           $elem_code = $all_attr->get('code');

           // Se non esiste ancora lo creo come oggetto.
           if (!isset($attributes[$elem_code]))  {
               $attr = manageAttribute($attr_id, $attr_elem, $table_num, $ent_type);
               if ($attr) {
                   $attributes[$elem_code] = $attr;
               }
           } else {
               // Se invece esiste già allora lo re-istanzio come array di oggetti
               $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
    	   $attributes[$elem_code] = array();
               $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
               // e inserisco il vecchio valore wrappato in una array
               $attributes[$elem_code][] = manageAttribute($attr_id, $attr_elem, $table_num, $ent_id); // aggiungo il nuovo valore
           }
       }

       // Recupero dei media
       if (!$ignore_media) {
           $m_media = array();
           $m_media['primary'] = getPrimaryImg($ent_id, $table_num);
           $m_media['all'] = getAllMedia($ent_id, $table_num);
       }

       $m_hasMedia = (isset($m_media['all']) && count($m_media['all']) > 0) ? 'Ha media' : 'Non ha media';


       $q_relation = "SELECT collection_id as 'rel_id', type_id as 'type' FROM ca_entities_x_collections WHERE entity_id = {$ent_id}";
       $m_collections = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_collections");
           if (!isset($m_collections[$label]))  {
               $m_collections[$label] = array(
                   'id' => $all_ent->get('rel_id'),
                   'name' => getRelationName($all_ent->get('rel_id'), 13),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_collections")
               );
           }
       }

       $q_relation = "SELECT entity_right_id as 'rel_id', type_id as 'type' FROM ca_entities_x_entities WHERE entity_left_id = {$ent_id}";
       $m_entities = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_entities");
           if (!isset($m_entities[$label])) {
               $m_entities[$label] = array(
                   'id'       => $all_ent->get('rel_id'),
                   'name'     => getRelationName($all_ent->get('rel_id'), 20),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_entities")
               );
           }
       }

       $q_relation = "SELECT occurrence_id as 'rel_id', type_id as 'type' FROM ca_entities_x_occurrences WHERE entity_id = {$ent_id}";
       $m_occurrences = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_occurrences");
           if (!isset($m_occurrences[$label]))  {
               $m_occurrences[$label] = array(
                   'id'       => $all_ent->get('rel_id'),
                   'name'     => getRelationName($all_ent->get('rel_id'), 67),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_occurrences")
               );
           }
       }

       $q_relation = "SELECT place_id as 'rel_id', type_id as 'type' FROM ca_entities_x_places WHERE entity_id = {$ent_id}";
       $m_places = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_places");
           if (!isset($m_places[$label]))   {
               $m_places[$label] = array(
                   'id'       => $all_ent->get('rel_id'),
                   'name'     => getRelationName($all_ent->get('rel_id'), 72),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_places")
               );
           }
       }

       $q_relation = "SELECT location_id as 'rel_id', type_id as 'type' FROM ca_entities_x_storage_locations WHERE entity_id = {$ent_id}";
       $m_storage_locations = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_storage_locations");
           if (!isset($m_storage_locations[$label]))    {
               $m_storage_locations[$label] = array(
                   'id'       => $all_ent->get('rel_id'),
                   'name'     => getRelationName($all_ent->get('rel_id'), 89),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_storage_locations")
               );
           }
       }

       $q_relation = "SELECT object_id as 'rel_id', type_id as 'type' FROM ca_objects_x_entities WHERE entity_id = {$ent_id}";
       $m_objects = array();
       $all_ent = $mysql->query($q_relation);
       while ($all_ent->nextRow()) {
           $label = getRelationLabel($all_ent->get('type'), $table_num, $ent_id, "ca_objects");
           if (!isset($m_objects[$label]))    {
               $m_objects[$label] = array(
                   'id'       => $all_ent->get('rel_id'),
                   'name'     => getRelationName($all_ent->get('rel_id'), 57),
                   'relation' => getMongoRelation($all_ent->get('rel_id'), "ca_objects")
               );
           }
       }


       /*
            Generazione della struttura di Mongo
        */
       $mongo_record = array();

       $mongo_record['id'] = $m_id;
       $mongo_record['parent'] = $m_parent;
       if ($m_ente) $mongo_record['ente'] = $m_ente;
       $mongo_record['access'] = $m_access;
       $mongo_record['preferred_label'] = $m_name;
       if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;
       $mongo_record['idno'] = $m_idno;
       $mongo_record['type_id'] = $m_type_id;

       if (!$ignore_media && $m_media['primary'] != null) {
           $mongo_record['representations'] = $m_media;
       }

       $mongo_record['hasMedia'] = $m_hasMedia;

       foreach ($attributes as $attr_code => $attr) {
           $mongo_record[$attr_code] = $attr;
       }

       $related = array();

       if (!empty($m_entities))
           $related['ca_entities'] = $m_entities;
       if (!empty($m_places))
           $related['ca_places'] = $m_places;
       if (!empty($m_occurrences))
           $related['ca_occurrences'] = $m_occurrences;
       if (!empty($m_collections))
           $related['ca_collections'] = $m_collections;
       if (!empty($m_objects))
           $related['ca_objects'] = $m_objects;

       $mongo_record['related'] = $related;

       // Scheda breve


        $mongo_record['language'] = 'italian';
        try {
            $mongo->remove(array('id' => $m_id));
            $mongo->insert($mongo_record);    
        } catch (MongoException $e)  {
            error_log("Errore durante la connessione al mongo " . $e->getMessage());
        }
    }
}

/*
############################################* FUNCTIONS *###############################################################
 */

/**
 * Gestione di un attributo
 * @param $attr_id
 * @param $meta_id
 * @param $table_num
 * @param $obj_type
 * @return array|null
 */
function manageAttribute($attr_id, $meta_id, $table_num, $obj_type)	{
	switch (getMetadataType($meta_id)) {
		case 'container':	return manageContainer($attr_id, $meta_id, $table_num, $obj_type);
		case 'datarange':	return manageDate($attr_id, $table_num, $obj_type);
		case 'list':	return manageList($attr_id, $table_num, $obj_type);
		case 'geocode':	return manageGeoCode($attr_id, $table_num, $obj_type);
		case "objectrepresentations":	return manageRelations($attr_id, 56, $obj_type);
		case "entities":	return manageRelations($attr_id, 20, $obj_type);
		case "places":	return manageRelations($attr_id, 72, $obj_type);
		case "occurrences":	return manageRelations($attr_id, 67, $obj_type);
		case "collections":	return manageRelations($attr_id, 13, $obj_type);
		case "storagelocations":	return manageRelations($attr_id, 89, $obj_type);
		case "loans":	return manageRelations($attr_id, 133, $obj_type);
		case "movements":	return manageRelations($attr_id, 137, $obj_type);
		case "objects":	return manageRelations($attr_id, 57, $obj_type);
		case "objectlots":	return manageRelations($attr_id, 51, $obj_type);
		default:	return manageDefault($attr_id, $table_num, $obj_type, $obj_type);
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
function manageDefault($attr_id, $table_num, $obj_type)	{
    global $mysql;

    $q_attribute_value = <<<QUERY
    SELECT
        v.element_id as 'element_id',
        v.value_longtext1 as 'value'
    FROM ca_attribute_values v
    WHERE v.attribute_id = {$attr_id}
QUERY;

    $n = '';
    $r = $mysql->query($q_attribute_value);
    while ($r->nextRow())    {
        if ($r->get('value') == null)  return null;
        $result = array(
            'label'  => getLabel($r->get('element_id'), $table_num, $obj_type),
            'value'  => $r->get('value'),
            'ordine' => getOrdine($r->get('element_id'), $table_num, $obj_type)
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
function manageDate($attr_id, $table_num, $obj_type)	{
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
    while ($r->nextRow())    {
        if ($r->get('value') == null)  return null;

        $start = trasformData($r->get('decimal1'));
        $end = trasformData($r->get('decimal2'));

        $start = strtotime($start['year'] . "-" . $start['month'] . "-" . $start['day'] . " " . $start['hour'] . ":" . $start['minute'] . ":" . $start['second']);
        $end = strtotime($end['year'] . "-" . $end['month'] . "-" . $end['day'] . " " . $end['hour'] . ":" . $end['minute'] . ":" . $end['second']);

        $range = array();
        $sY = date('Y', $start);
        $eY = date('Y', $end);
        for ($i = 0; $i <= ($eY - $sY); $i++ )  {
            array_push($range, ($sY + $i));
        }

        $data_container = array(
            'label'  => getLabel($r->get('element_id'), $table_num, $obj_type),
            'value'  => $r->get('value'),
            'start'  => new MongoDate($start),
            'end'    => new MongoDate($end),
            'range'  => $range,
            'ordine' => getOrdine($r->get('element_id'), $table_num, $obj_type)
        );
    }

	return $data_container;
}

/**
 * [manageList description]
 * @param  [type] $attr_id [description]
 * @return [type]          [description]
 */
function manageList($attr_id, $table_num, $obj_type)	{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
	v.element_id as 'element_id',
	v.item_id as 'item_id'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($r->nextRow())    {
        if ($r->get('item_id') == null)  return null;

        $result = array(
            'label' => getLabel($r->get('element_id'), $table_num, $obj_type),
            'value' => getListValue($r->get('item_id')),
            'ordine' => getOrdine($r->get('element_id'), $table_num, $obj_type)
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
function manageRelations($attr_id, $table_num, $obj_type)	{
    global $mysql;

    $q_attribute_value = <<<QUERY
SELECT
	v.element_id as 'element_id',
	v.value_longtext1 as 'value'
FROM ca_attribute_values v
WHERE v.attribute_id = {$attr_id}
QUERY;

    $r = $mysql->query($q_attribute_value);
    while ($r->nextRow())    {
        if ($r->get('value') == null)  return null;

        $result = array(
            'label'    => getLabel($r->get('element_id')),
            'ordine'   => getOrdine($r->get('element_id'), $table_num, $obj_type),
            'id'       => $r->get('value'),
            'name'     => getRelationName($r->get('value'), $table_num),
            'relation' => getMongoRelation($r->get('value'), getTableName($table_num))
        );
    }

	return $result;
}

/**
 * [manafeGeoCode description]
 * @param  [type] $attr_id [description]
 * @return [type]          [description]
 */
function manafeGeoCode($attr_id, $table_num, $obj_type)	{
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
    while ($r->nextRow())    {
        if ($r->get('value') == null)  return null;

        $result = array(
            'label'     => getLabel($r->get('element_id'), $table_num, $obj_type),
            'ordine'    => getOrdine($r->get('element_id'), $table_num, $obj_type),
            'ordine'    => getOrdine($r->get('element_id'), $table_num, $obj_type),
            'mongocord' => array(
                'type'        => "Point",
                'coordinates' => array($r->get('decimal1'), $r->get('decimal2')),
            ),
            'name' => $r->get('value'),
            'cord' => array(
                'lat' => $r->get('decimal1'),
                'lon' => $r->get('decimal2')
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
function manageContainer($attr_id, $meta_id, $table_num, $obj_type)	{
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
    while ($r->nextRow()) {
        $elem_id  = $r->get('element_id');
        $decimal1 = $r->get('decimal1');
        $decimal2 = $r->get('decimal2');
        $value    = $r->get('value');
        $item_id  = $r->get('item_id');

        if ($value == null)  {continue;}


        switch (getMetadataType($elem_id)) {
            case 'datarange':
                $start = trasformData($decimal1);
                $end = trasformData($decimal2);

                $start = strtotime($start['year'] . "-" . $start['month'] . "-" . $start['day'] . " " . $start['hour'] . ":" . $start['minute'] . ":" . $start['second']);
                $end = strtotime($end['year'] . "-" . $end['month'] . "-" . $end['day'] . " " . $end['hour'] . ":" . $end['minute'] . ":" . $end['second']);

                $range = array();
                $sY = date('Y', $start);
                $eY = date('Y', $end);
                for ($i = 0; $i <= ($eY - $sY); $i++ )  {
                    array_push($range, ($sY + $i));
                }

                $result = array(
                    'label' => getLabel($elem_id, $table_num, $obj_type),
                    'value' => $value,
                    'start' => new MongoDate($start),
                    'range' => $range,
                    'end'   => new MongoDate($end)
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
                    'label'     => getLabel($elem_id, $table_num, $obj_type),
                    'mongocord' => array(
                        'type'        => "Point",
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
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 56),
                    'relation' => getMongoRelation($value, getTableName(56))
                );
                break;
            case "entities":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 20),
                    'relation' => getMongoRelation($value, getTableName(20))
                );
                break;
            case "places":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 72),
                    'relation' => getMongoRelation($value, getTableName(72))
                );
                break;
            case "occurrences":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'ordine'   => getOrdine($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 67),
                    'relation' => getMongoRelation($value, getTableName(67))
                );
                break;
            case "collections":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 13),
                    'relation' => getMongoRelation($value, getTableName(13))
                );
                break;
            case "storagelocations":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 89),
                    'relation' => getMongoRelation($value, getTableName(89))
                );
                break;
            case "loans":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 133),
                    'relation' => getMongoRelation($value, getTableName(133))
                );
                break;
            case "movements":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 137),
                    'relation' => getMongoRelation($value, getTableName(137))
                );
                break;
            case "objects":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 57),
                    'relation' => getMongoRelation($value, getTableName(57))
                );
                break;
            case "objectlots":
                $result = array(
                    'label'    => getLabel($elem_id, $table_num, $obj_type),
                    'id'       => $value,
                    'name'     => getRelationName($value, 51),
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

    if (!empty($container))   {
        $container['label']  = getLabel($meta_id, $table_num, $obj_type);
        $container['ordine'] = getOrdine($meta_id, $table_num, $obj_type);
        return $container;
    }

	return null;
}
