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

error_reporting(E_ERROR | E_PARSE);

global $mysql, $mongo;

$die_after = 0;

// Gestione delle opzioni di sincronizzazione
$ignore_media = false;

/**
 * Connessione ai database
 */

//$mysql_DB_user = "lucamontanera";
//$mysql_DB_psw = "lucamontanera";
//$mysql_DB_dbname = "ca_polo900";

$mysql_DB_user = "ca_polo900_dev";
$mysql_DB_psw = "Mbre2T";
$mysql_DB_dbname = "ca_polo900_dev";

$mongodb = "polo900";

// Mysql
$mysql = new mysqli($mysql_DB_host, $mysql_DB_user, $mysql_DB_psw, $mysql_DB_dbname);
if ($mysql->connect_errno) {
    error_log("Errore durante la connessione al database " . $mysql->connect_error);
    exit();
}
$mysql->set_charset("utf8");

// // MongoDB
try {
    $m = new MongoClient();
    $mongo = $m->$mongodb;
} catch (MongoConnectionException $e) {
    error_log("Errore durante la connessione al mongo " . $e->getMessage());
    $mysql->close();
    exit();
}

// * Tutto Ã¨ andato bene e sono pronto a sincronizzare *

/**
 * Importazioni
 */
include_once "function.php";

//IMPOSTAZIONE INVIATA DA MONGOCANE
if ($argv[1] == "oggetti") {
    $oggetti = true;
    $ca_id = $_POST['object_id'];

} else if ($argv[1] == "entita") {
    $entita = true;
    $ca_id = $_POST['entity_id'];
} else if ($argv[1] == "occorrenze") {
    $occorrenze = true;
    $ca_id = $_POST['occurrence_id'];
} else if ($argv[1] == "luoghi") {
    $luoghi = true;
    $ca_id = $_POST['place_id'];
} else if ($argv[1] == "collezioni") {
    $collezioni = true;
    $ca_id = $_POST['collection_id'];
} else if ($argv[1] == "sommari")
    $sommari = true;
else if ($argv[1] == "update_strumenti") {
    $update_strumenti = true;
    $ca_id = $_POST['object_id'];
} else if ($argv[1] == "tutto") {
    $oggetti = true;
    $entita = true;
    $occorrenze = true;
    $luoghi = true;
    $collezioni = true;

    include_once "hierarchy_all.php";
} else if ($argv[1] == "access")    {
    $access = true;
}

if ($argv[2] > 0)
    $ca_id = $argv[2];

/**
 * Recupero tutti gli oggetti
 */
if ($oggetti) {
    if ($ca_id != null) {
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
AND o.object_id = $ca_id
QUERY;
    } else {

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
QUERY;
    }
    $page = 0;
    $start = 0;
    $limit = 1000;
    $firstStart = $start;

    while (1) {
        $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
        $start = ($limit * ++$page) + $firstStart;
        $firstStart = 0;

        if ($all_obj->num_rows <= 0) {
            break;
        }

        while ($row = $all_obj->fetch_assoc()) {
            $table_num = 57;
            $search_field = '';
            $obj_id = $row['id'];
            $parent_id = $row['parent'];
            $obj_type = $row['type'];
            $idno = array();
            $idno['value'] = $row['idno'];
            // $idno['ordine'] = getOrdine("idno", $table_num, $obj_type);
            // $idno['visibile'] = getVisibilita("idno", $table_num, $obj_type);
            $access = $row['access'];
            $name = $row['preferred_label'];
            $name_type = $row['label_type'];

            $m_ente = getEnteName($obj_id, $table_num);
            $m_info_ente = getInfoEnte($obj_id);

            $i = 0;
            foreach ($m_ente as $ente) {
                if (!isset($id_enti[$ente])) {
                    $id_enti[$ente] = ($obj_id + $i) % 100;
                }
                $i++;
            }
            unset($i);

            // Informazioni principali
            $m_id = (int)$obj_id;
            $m_idno = $idno;

            if ($parent_id != null) {
                $m_parent = array(
                    'id' => (int)$parent_id,
                    'relation' => getMongoRelation($parent_id, "ca_objects")
                );
            }

            $m_type_id = getTypeName($obj_type);
            $m_access = (int)$access;
            $m_name['name'] = ($name);
            if ($name_type)
                $m_name['type'] = getTypeName($name_type);


            // Gestione degli attributi
            $attributes = gestioneAttributi($obj_id, $table_num, $obj_type);
            // Recupero dei media
            if (!$ignore_media) {
                $m_media = array();
                $m_media = getAllMedia($obj_id, $table_num);
            }

            /*
                Recupero delle relazioni
             */


            // Entities
            /** @var String $q_ca_objects_x_entities */
            $m_entities = recuperoEntitaRelazioni($table_num, $obj_id, 'object');

            // Collections
            $m_collections = recuperoCollezioniRelazioni($table_num, $obj_id, 'object');

            // Places
            $m_places = recuperoLuoghiRelazioni($table_num, $obj_id, 'object');

            // Occurrences
            $m_occurrences = recuperoOccorrenzeRelazioni($table_num, $obj_id, 'object');

            // storage_locations
            $storage_locations = recuperoStorage_locationRelazioni($table_num, $obj_id, 'object');

            // Objects
            $m_objects_right = recuperoOggettiRelazioni($table_num, $obj_id, 'object');
            $m_objects_left = recuperoOggettiRelazioni($table_num, $obj_id, 'object_left');

            // vocabulary
            $m_vocabulary = recuperoVocabularyRelazioni($table_num, $obj_id, 'object');

            /*
                Generazione della struttura di Mongo
            */

            // gestisco le due casistiche poichè talvolta accade che nei db le relazioni tra left e right siano scambiate

            if (!empty($m_objects_right)) {
                $m_objects = $m_objects_right;
            } else {
                $m_objects = $m_objects_left;
            }

            // $m_tema_filtri = array();
            //     foreach ($m_occurrences as $key => $value) {
            //        if($key == "tema"){
            //            $m_tema_filtri = CreateTemaFilter($value);
            //            unset($m_occurrences[$key]);
            //        }
            //     }

            $mongo_record = array();

            $mongo_record['id'] = $m_id;
            $mongo_record['table'] = "ca_objects";
            $mongo_record['parent'] = $m_parent;
            // $mongo_record['aggregatore'] = $aggregatore;
            if ($m_ente) $mongo_record['ente'] = $m_ente;
            $mongo_record['access'] = $m_access;
            $mongo_record['preferred_label'] = $m_name;
            if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;

            $mongo_record['idno'] = $m_idno;
            $mongo_record['type_id'] = $m_type_id;

            if (isset($m_info_ente) && $m_info_ente != null) {
                $mongo_record['info_ente'] = $m_info_ente;
            }

            // $mongo_record['filtri'] = $m_tema_filtri;
            $mongo_record['filtri'] = array();

            /** TIPO **/

            // recupero il path per la tipologia del filtro
            $root = getRootParentType($m_type_id);
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . "tipologia"
            ), array("id" => "tipologia", "name" => "tipologia"));
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . "tipologia" . "/" . $m_type_id["supertype"]
            ), array(
                "id" => $m_type_id['supertype'],
                "name" => $m_type_id['supertype']
            ));

            /** ENTE **/

            $mongo_record['filtri'][] = array(
                "path" => "/ente",
                "id" => "144",
                "name" => "ente"
            );

            foreach ($m_ente as $ente) {
                $mongo_record['filtri'][] = array(
                    "path" => "/ente/" . $ente,
                    "id" => $id_enti[$ente] . "",
                    "name" => $ente
                );
                $o++;
            }

            /** MEDIA **/

            $mongo_record['filtri'][] = array(
                "path" => "/media",
                "id" => "537",
                "name" => "media"
            );

            $mongo_record['filtri'][] = array(
                "path" => "/media/" . (empty($m_media) ? 'Non ha media' : "Ha media"),
                "id" => "541",
                "name" => (empty($m_media) ? 'Non ha media' : "Ha media")
            );


            /** SOGGETTO PRODUTTORE **/

            if (isset($m_entities['soggetto produttore'])) {

                $mongo_record['filtri'][] = array(
                    "path" => "/soggetto_produttore",
                    "id" => "2164",
                    "name" => "Soggetto produttore"
                );


                if (is_array($m_entities['soggetto produttore'])) {
                    foreach ($m_entities['soggetto produttore'] as $soggetto_produttore) {
                        $mongo_record['filtri'][] = array(
                            "path" => "/soggetto_produttore/" . $soggetto_produttore['name'],
                            "id" => $soggetto_produttore['id'],
                            "name" => $soggetto_produttore['name']
                        );
                    }
                } else {
                    $mongo_record['filtri'][] = array(
                        "path" => "/soggetto_produttore/" . $m_entities['name'],
                        "id" => $m_entities['id'],
                        "name" => $m_entities['name']
                    );
                }
            }

            /** DATAZIONE */
            if (isset($attributes["cronologia"]["datazione"]["range"])) {
                $mongo_record['filtri'][] = array(
                    "path" => "/datazione",
                    "id" => "datazione",
                    "name" => "datazione"
                );
                foreach ($attributes["cronologia"]["datazione"]["range"] as $range) {
                    $mongo_record['filtri'][] = array(
                        "path" => "/datazione/" . $range,
                        "id" => $range . "",
                        "name" => $range . " - 9"
                    );
                }
            }


            if (!$ignore_media && !empty($m_media))
                $mongo_record['representations'] = $m_media;

            foreach ($attributes as $attr_code => $attribute) {
                $mongo_record[$attr_code] = $attribute;
            }

            $related = array();
            $mongo_record['searchentity'] = "";

            if (!empty($m_entities)) {
                $related['ca_entities'] = $m_entities;
                array_walk_recursive($m_entities, function ($item, $key) use (&$mongo_record) {
                    if ($key == "name") {
                        $mongo_record['searchentity'] .= normalizeChars($item) . " ";
                    }
                });
            }
            if (!empty($m_places))
                $related['ca_places'] = $m_places;
            if (!empty($m_occurrences))
                $related['ca_occurrences'] = $m_occurrences;
            if (!empty($m_collections))
                $related['ca_collections'] = $m_collections;
            if (!empty($m_objects))
                $related['ca_objects'] = $m_objects;
            if (!empty($m_vocabulary))
                $related['ca_vocabulary'] = $m_vocabulary;

            // Scheda breve
            $m_scheda_breve = array();
            $m_scheda_breve['Tipo oggetto'] = $m_type_id['name'];
            $m_scheda_breve['Titolo'] = $m_name['name'];
            if (isset($m_name['type'])) {
                $m_scheda_breve['Titolo'] .= " (" . $m_name['type']['name'] . ")";
            }
            if (isset($m_alternative_names) && !empty($m_alternative_names)) {
                $m_scheda_breve['Altro titolo'] = $m_alternative_names;
            }
            if (isset($mongo_record['num_def_numero'])) {
                $m_scheda_breve['Segnatura'] = $mongo_record['num_def_numero']['value'];
                if (isset($mongo_record['num_def_bis'])) {
                    $m_scheda_breve['Segnatura'] .= " " . $mongo_record['num_def_bis']['value'];
                }
            }
            if (isset($mongo_record['ntc'])) {
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

            if (isset($mongo_record['cronologia'])) {
                if (isset($mongo_record['cronologia']['cron_inv'])) {
                    $m_scheda_breve['Data'] = $mongo_record['cronologia']['cron_inv']['value'];
                } else if (isset($mongo_record['cronologia']['datazione'])) {
                    $m_scheda_breve['Data'] = $mongo_record['cronologia']['datazione']['value'];
                }
            }

            if (isset($mongo_record['mtct'])) {
                $m_scheda_breve['Tecnica'] = $mongo_record['mtct']['value'];
            } else if (isset($mongo_record['mtc'])) {
                $m_scheda_breve['Tecnica'] = $mongo_record['mtc']['value'];
            }

            if (isset($mongo_record['consistenza'])) {
                if (isset($mongo_record['consistenza']['consistenza2'])) {
                    $m_scheda_breve['Consistenza'] = $mongo_record['consistenza']['consistenza2']['value'];
                }
                if (isset($mongo_record['consistenza']['tipo_consistenza'])) {
                    $m_scheda_breve['Consistenza'] .= " " . $mongo_record['consistenza']['tipo_consistenza']['value'];
                }

                if (isset($mongo_record['consistenza']['consistenza_specifica'])) {
                    $m_scheda_breve['Consistenza'] .= " " . $mongo_record['consistenza']['consistenza_specifica']['value'];
                }
            }

            if (isset($mongo_record['description'])) {
                $m_scheda_breve['desc']['Descrizione'] = $mongo_record['description'];
            } else if (isset($mongo_record['dess1'])) {
                $m_scheda_breve['desc']['Descrizione'] = $mongo_record['dess1'];
            } else if (isset($mongo_record['drs'])) {
                $m_scheda_breve['desc']['Descrizione'] = $mongo_record['drs'];
            }

            if (isset($mongo_record['livello'])) {
                $m_scheda_breve['Livello (descrizione)'] = $mongo_record['livello'];
            }

            if (isset($mongo_record['sigla_cit'])) {
                $m_scheda_breve['Sigla per citazione'] = $mongo_record['sigla_cit'];
            }

            $mongo_record['scheda_breve'] = $m_scheda_breve;

            $mongo_record['related'] = $related;

            $mongo_record['language'] = 'italian';


            try {
                $mongo->data->remove(array('id' => $m_id, 'table' => 'ca_objects'));
                $mongo->data->insert($mongo_record);
                //create search field
                $search_field = array("search" => creaSearch($mongo, $mongo_record['id'], "ca_objects"));
                $mongo->data->update(array('id' => $m_id, 'table' => 'ca_objects'), array('$set' => $search_field), array("upsert" => false));

            } catch (MongoException $e) {
                error_log("Errore durante la connessione al mongo " . $e->getMessage());
            }
        }

    }
}
/**
 * Recupero tutte le entitÃ
 */
if ($entita) {

$metadati_to_show = array(
        "data_esistenza" => array("group" => "data_esistenza", "ordine" => 0),
        "spec_den"=> array("group" => "campi", "ordine" => 1),
        "nome_patronim_prov" => array("group" => "campi", "ordine" => 2),
        "aute" => array("group" => "campi", "ordine" => 3),
        "pseudonimo" => array("group" => "campi", "ordine" => 4),
        "denef" => array("group" => "campi", "ordine" => 0),
        "spec_altra_den" => array("group" => "campi", "ordine" => 5),
        "autf" => array("group" => "campi", "ordine" => 6),
        "autv" => array("group" => "campi", "ordine" => 7),
        "luud" => array("group" => "campi", "ordine" => 8),
        "luun" => array("group" => "campi", "ordine" => 9),
        "intest_autor" => array("group" => "campi", "ordine" => 10),
        "intest_auto_altr" => array("group" => "campi", "ordine" => 11),
        "tip_funz" => array("group" => "campi", "ordine" => 12),
        "cond_giuridica" => array("group" => "campi", "ordine" => 13),
        "strutt_amm" => array("group" => "campi", "ordine" => 14),
        "ata" => array("group" => "campi", "ordine" => 15),
        "misv" => array("group" => "campi", "ordine" => 16),
        "sigla_cit" => array("group" => "campi", "ordine" => 17),
        "cont_cult" => array("group" => "campi", "ordine" => 18),
        "genealogia" => array("group" => "campi", "ordine" => 19),
        "autg" => array("group" => "descrizione", "ordine" => 20),
        "storia" => array("group" => "descrizione", "ordine" => 21),
        "biografia" => array("group" => "descrizione", "ordine" => 22),
    );
    if ($ca_id != null) {

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
      AND e.entity_id = $ca_id
QUERY;

    } else {

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
QUERY;
    }

    $page = 0;
    $start = 0;
    $limit = 1000;
    $firstStart = $start;

    while (1) {
        $all_enti = $mysql->query($q_all_ent . " LIMIT $start, $limit");
        $start = ($limit * ++$page) + $firstStart;
        $firstStart = 0;

        if ($all_luoghi->num_rows <= 0) {
            break;
        }

        while ($row = $all_enti->fetch_assoc()) {
            $table_num = 20;
            $ent_id = $row['id'];
            $parent_id = $row['parent'];
            $ent_type = $row['type'];
            $idno = array();
            $idno['value'] = $row['idno'];

            $access = $row['access'];
            $name = $row['preferred_label'];
            $name_type = $row['label_type'];

            $m_id = (int)$ent_id;
            $m_idno = $idno;
            $m_access = (int)$access;
            if ($parent_id != null) {
                $m_parent = array(
                    'id' => (int)$parent_id,
                    'relation' => getMongoRelation($parent_id, "ca_entities")
                );
            }
            $m_type_id = getTypeName($ent_type);
            $m_name = $name;
            $m_alternative_names = getAlternativeEntNames($ent_id);

            // Gestione degli attributi

            $q_all_attribute = <<<QUERY
SELECT
a.attribute_id as 'id',
a.element_id as 'attribute_element',
element_code as 'code'
FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
WHERE a.row_id = {$ent_id}
AND a.table_num = {$table_num}
QUERY;
            $all_attr = $mysql->query($q_all_attribute);
            $attributes = array();
            while ($row = $all_attr->fetch_assoc()) {
                $attr_id = $row['id'];
                $attr_elem = $row['attribute_element'];
                $elem_code = $row['code'];

                if (!in_array($elem_code, array_keys($metadati_to_show))) {
                    continue;
                }

                // Se non esiste ancora lo creo come oggetto.
                $attr = manageAttribute($attr_id, $attr_elem, $table_num, $ent_type, $obj_id);
                if ($attr) {
                    $attr = array_merge($attr, $metadati_to_show[$elem_code]);
                    if (!isset($attributes[$elem_code])) {
                        $attributes[$elem_code] = $attr;
                    } else {
                        // Se invece esiste giÃ  allora lo re-istanzio come array di oggetti
                        $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
                        unset($attributes[$elem_code]); // resetto l'elemento
                        $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
                        // e inserisco il vecchio valore wrappato in una array
                        $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
                    }
                }
            }

            // Recupero dei media
            if (!$ignore_media) {
                $m_media = array();
                $m_media = getAllMedia($ent_id, $table_num);
            }


            // Entities
            $m_entities = recuperoEntitaRelazioni($table_num, $ent_id, 'entity');


            // Collections
            $m_collections = recuperoCollezioniRelazioni($table_num, $ent_id, 'entity');


            // Places
            $m_places = recuperoLuoghiRelazioni($table_num, $ent_id, 'entity');

            // Occurrences
            $m_occurrences = recuperoOccorrenzeRelazioni($table_num, $ent_id, 'entity');


            // storage_locations
            $storage_locations = recuperoStorage_locationRelazioni($table_num, $ent_id, 'entity');

            // Objects
            $m_objects = recuperoOggettiRelazioni($table_num, $ent_id, 'entity');

            // vocabulary
            $m_vocabulary = recuperoVocabularyRelazioni($table_num, $ent_id, 'entity');


//      Generazione della struttura di Mongo

            $mongo_record = array();

            $mongo_record['id'] = $m_id;
            $mongo_record['table'] = "ca_entities";
            $mongo_record['parent'] = $m_parent;
            if ($m_ente) $mongo_record['ente'] = $m_ente;
            $mongo_record['access'] = $m_access;
            $mongo_record['preferred_label'] = $m_name;
            if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;
            $mongo_record['idno'] = $m_idno;
            $mongo_record['type_id'] = $m_type_id;

            // recupero il path per la tipologia del filtro
            $mongo_record['filtri'] = array();
            $root = getRootParentType($m_type_id);
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . "tipologia"
            ), array("id" => "tipologia", "name" => "tipologia"));
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . "tipologia" . "/" . $m_type_id["supertype"]
            ), array(
                "id" => $m_type_id['supertype'],
                "name" => $m_type_id['supertype']
            ));

            /** DATAZIONE */
            if (isset($attributes["data_esistenza"]["data_es"]["range"])) {
                $mongo_record['filtri'][] = array(
                    "path" => "/datazione",
                    "id" => "datazione",
                    "name" => "datazione"
                );
                foreach ($attributes["data_esistenza"]["data_es"]["range"] as $range) {
                    $mongo_record['filtri'][] = array(
                        "path" => "/datazione/" . $range,
                        "id" => $range . "",
                        "name" => $range . " - 9"
                    );
                }
            }


            if (!$ignore_media && !empty($m_media) && !empty($m_media))
                $mongo_record['representations'] = $m_media;

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
            if (!empty($m_vocabulary))
                $related['ca_vocabulary'] = $m_vocabulary;

            $mongo_record['related'] = $related;

            $mongo_record['language'] = 'italian';
            try {
                $mongo->data->remove(array('id' => $m_id, 'table' => 'ca_entities'));
                $mongo->data->insert($mongo_record);
                $search_field = array("search" => creaSearch($mongo, $mongo_record['id'], "ca_entities"));
                $mongo->data->update(array('id' => $m_id, 'table' => 'ca_entities'), array('$set' => $search_field), array("upsert" => false));
                echo $m_id . "\t";
            } catch (MongoException $e) {
                error_log("Errore durante la connessione al mongo " . $e->getMessage());
            }
        }
    }
}
/**
 * Recupero tutti i place
 */
if ($luoghi) {

    if ($ca_id != null) {

        $q_all_place = <<<QUERY
SELECT
  e.place_id as 'id',
  e.parent_id as 'parent',
  e.type_id as 'type',
  e.idno as 'idno',
  e.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_places e
  INNER JOIN ca_place_labels l
    ON (e.place_id = l.place_id)
WHERE l.is_preferred = 1
      AND e.deleted = 0 AND e.parent_id is NOT null
	  AND e.place_id = $ca_id
QUERY;

    } else {

        $q_all_place = <<<QUERY
SELECT
  e.place_id as 'id',
  e.parent_id as 'parent',
  e.type_id as 'type',
  e.idno as 'idno',
  e.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_places e
  INNER JOIN ca_place_labels l
    ON (e.place_id = l.place_id)
WHERE l.is_preferred = 1
      AND e.deleted = 0 AND e.parent_id is NOT null
QUERY;

    }

    $page = 0;
    $start = 0;
    $limit = 1000;
    $firstStart = $start;

    while (1) {
        $all_luoghi = $mysql->query($q_all_place . " LIMIT $start, $limit");
        $start = ($limit * ++$page) + $firstStart;
        $firstStart = 0;

        if ($all_luoghi->num_rows <= 0) {
            break;
        }

        while ($row = $all_luoghi->fetch_assoc()) {
            $table_num = 72;
            $search_field = '';
            $place_id = $row['id'];
            $parent_id = $row['parent'];
            $place_type = $row['type'];
            $idno = array();
            $idno['value'] = $row['idno'];
            $access = $row['access'];
            $name = trim($row['preferred_label']);
            $name_type = $row['label_type'];

            $m_id = (int)$place_id;
            $m_idno = $idno;
            $m_access = (int)$access;
            if ($parent_id != null) {
                $m_parent = array(
                    'id' => (int)$parent_id,
                    'relation' => getMongoRelation($parent_id, "ca_place")
                );
            }

            $m_type_id = array(
                'id' => '26',
                'name' => 'Luogo',
                'supertype' => 'Luogo'
            );
            if ($place_type)
                $m_type_id = getTypeName($place_type);
            $m_name = $name;
            // $m_alternative_names = getAlternativeEntNames($place_id);

            // Gestione degli attributi

            $q_all_attribute = <<<QUERY
    SELECT
        a.attribute_id as 'id',
        a.element_id as 'attribute_element',
        element_code as 'code'
    FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
    WHERE a.row_id = {$place_id}
    AND a.table_num = {$table_num}
QUERY;
            $all_attr = $mysql->query($q_all_attribute);
            $attributes = array();

            while ($row = $all_attr->fetch_assoc()) {
                $attr_id = $row['id'];
                $attr_elem = $row['attribute_element'];
                $elem_code = $row['code'];

                if (!toInsert($elem_code, $place_type, $table_num))
                    continue;
                // Se non esiste ancora lo creo come oggetto.

                $attr = manageAttribute($attr_id, $attr_elem, $table_num, $place_type, $place_id);
                if ($attr) {
                    if (!isset($attributes[$elem_code])) {
                        $attributes[$elem_code] = $attr;
                    } else {
                        // Se invece esiste già allora lo re-istanzio come array di oggetti
                        $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
                        unset($attributes[$elem_code]); // resetto l'elemento
                        $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
                        // e inserisco il vecchio valore wrappato in una array
                        $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
                    }
                }
            }

            // Recupero dei media
            if (!$ignore_media) {
                $m_media = array();
                $m_media = getAllMedia($place_id, $table_num);
            }

            // Entities
            $m_entities = recuperoEntitaRelazioni($table_num, $place_id, 'place');


            // Collections
            $m_collections = recuperoCollezioniRelazioni($table_num, $place_id, 'place');


            // Places
            $m_places = recuperoLuoghiRelazioni($table_num, $place_id, 'place');

            // Occurrences
            $m_occurrences = recuperoOccorrenzeRelazioni($table_num, $place_id, 'place');


            // storage_locations
            $storage_locations = recuperoStorage_locationRelazioni($table_num, $place_id, 'place');

            // Objects
            $m_objects = recuperoOggettiRelazioni($table_num, $place_id, 'place');


            /*
            Generazione della struttura di Mongo
          */
            $mongo_record = array();

            $mongo_record['id'] = $m_id;
            $mongo_record["table"] = "ca_places";
            $mongo_record['parent'] = $m_parent;
            $mongo_record['access'] = $m_access;
            $mongo_record['preferred_label'] = $m_name;
            // if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;
            $mongo_record['idno'] = $m_idno;
            $mongo_record['type_id'] = $m_type_id;

//    $mongo_record['filtri'][] = array_merge(array(
//      "path" => "/" . $root["name"]
//    ), $root);
//    $mongo_record['filtri'][] = array_merge(array(
//      "path" => "/" . $root["name"] . "/" . $m_type_id["name"]
//    ), $m_type_id);


            if (!$ignore_media && !empty($m_media) && !empty($m_media))
                $mongo_record['representations'] = $m_media;

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

            $mongo_record['language'] = 'italian';
            try {
                $mongo->data->remove(array('id' => $m_id, 'table' => 'ca_places'));
                $mongo->data->insert($mongo_record);
                //create search field
                $search_field = array("search" => creaSearch($mongo, $mongo_record['id'], "ca_places"));
                $mongo->data->update(array('id' => $m_id, 'table' => 'ca_places'), array('$set' => $search_field), array("upsert" => true));
            } catch (MongoException $e) {
                error_log("Errore durante la connessione al mongo " . $e->getMessage());
            }

        }
    }

    if ($argv[1] != null)
        echo "ca_place fatto<br>";
}


/**
 * Recupero tutte le occurrence
 */

if ($occorrenze) {

    if ($ca_id != null) {

        $q_all_occurrence = <<<QUERY
SELECT
  o.occurrence_id as 'id',
  o.parent_id as 'parent',
  o.type_id as 'type',
  o.idno as 'idno',
  o.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_occurrences o
  INNER JOIN ca_occurrence_labels l
    ON (o.occurrence_id = l.occurrence_id)
WHERE l.is_preferred = 1
      AND o.deleted = 0 AND o.type_id != 265
      AND o.occurrence_id = $ca_id
QUERY;

    } else {

        $q_all_occurrence = <<<QUERY
SELECT
  o.occurrence_id as 'id',
  o.parent_id as 'parent',
  o.type_id as 'type',
  o.idno as 'idno',
  o.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_occurrences o
  INNER JOIN ca_occurrence_labels l
    ON (o.occurrence_id = l.occurrence_id)
WHERE l.is_preferred = 1
      AND o.deleted = 0 AND o.type_id != 265
QUERY;
    }

    $page = 0;
    $start = 0;
    $limit = 1000;
    $firstStart = $start;


    while (1) {
        $all_occurrences = $mysql->query($q_all_occurrence . " LIMIT $start, $limit");
        $start = ($limit * ++$page) + $firstStart;
        $firstStart = 0;

        if ($all_occurrences->num_rows <= 0) {
            break;
        }


        while ($row = $all_occurrences->fetch_assoc()) {
            $table_num = 67;
            $search_field = '';
            $occu_id = $row['id'];
            $parent_id = $row['parent'];
            $occu_type = $row['type'];
            $idno = array();
            $idno['value'] = $row['idno'];
            $access = $row['access'];
            $name = trim($row['preferred_label']);
            $name_type = $row['label_type'];


            // Informazioni principali
            $m_id = (int)$occu_id;


            if ($parent_id != null) {
                $m_parent = array(
                    'id' => (int)$parent_id,
                    'relation' => getMongoRelation($parent_id, "ca_occurrences")
                );
            }

            $m_access = (int)$access;
            $m_idno = $idno;

            $m_type_id = getTypeName($occu_type);
            $m_name = $name;
            $m_alternative_names = getAlternativeOccuNames($occu_id);

            // Gestione degli attributi

            $q_all_attribute = <<<QUERY
    SELECT
        a.attribute_id as 'id',
        b.element_id as 'attribute_element',
        element_code as 'code'
    FROM ca_attributes a INNER JOIN ca_attribute_values b ON (a.attribute_id = b.attribute_id) INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
    WHERE a.row_id = {$occu_id}
    AND a.table_num = {$table_num}
QUERY;

            $all_attr = $mysql->query($q_all_attribute);
            $attributes = array();


            while ($row = $all_attr->fetch_assoc()) {
                $attr_id = $row['id'];
                $attr_elem = $row['attribute_element'];
                $elem_code = $row['code'];

                if (!toInsert($elem_code, $occu_type, $table_num))
                    continue;

                // Se non esiste ancora lo creo come oggetto.
                $attr = manageAttribute($attr_id, $attr_elem, $table_num, $occu_type, $obj_id);

                if ($attr) {
                    if (!isset($attributes[$elem_code])) {
                        $attributes[$elem_code] = $attr;
                    } else {
                        // Se invece esiste giÃ  allora lo re-istanzio come array di oggetti
                        $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
                        unset($attributes[$elem_code]); // resetto l'elemento
                        $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
                        // e inserisco il vecchio valore wrappato in una array
                        $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
                    }
                }
            }

            // Recupero dei media
            if (!$ignore_media) {
                $m_media = array();
                $m_media = getAllMedia($occu_id, $table_num);
            }


            //    Recupero delle relazioni

            // Entities
            $m_entities = recuperoEntitaRelazioni($table_num, $occu_id, 'occurrence');

            // Collections
            $m_collections = recuperoCollezioniRelazioni($table_num, $occu_id, 'occurrence');


            // Places
            $m_places = recuperoLuoghiRelazioni($table_num, $occu_id, 'occurrence');

            // Occurrences
            $m_occurrences = recuperoOccorrenzeRelazioni($table_num, $occu_id, 'occurrence');


            // storage_locations
            $storage_locations = recuperoStorage_locationRelazioni($table_num, $occu_id, 'occurrence');

            // Objects
            $m_objects = recuperoOggettiRelazioni($table_num, $occu_id, 'occurrence');

            // vocabulary
            $m_vocabulary = recuperoVocabularyRelazioni($table_num, $occu_id, 'occurrence');


            //  Generazione della struttura di Mongo

            $mongo_record = array();

            $mongo_record['id'] = $m_id;
            $mongo_record["table"] = "ca_occurrences";
            $mongo_record['parent'] = $m_parent;
            $mongo_record['access'] = $m_access;
            $mongo_record['preferred_label'] = $m_name;
            if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;

            $mongo_record['idno'] = $m_idno;
            $mongo_record['type_id'] = $m_type_id;

            $mongo_record['filtri'] = array();
            $root = getRootParentType($m_type_id, "Evento/Tema");
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . $root["name"]
            ), $root);
            $mongo_record['filtri'][] = array_merge(array(
                "path" => "/" . $root["name"] . "/" . $m_type_id["name"]
            ), $m_type_id);

            if (!$ignore_media && !empty($m_media))
                $mongo_record['representations'] = $m_media;

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
            $mongo_record['language'] = 'italian';
            try {
                $mongo->data->remove(array('id' => $m_id, 'table' => 'ca_occurrences'));
                $mongo->data->insert($mongo_record);

                //create search field
                $search_field = array("search" => creaSearch($mongo, $mongo_record['id'], "ca_occurrences"));
                $mongo->data->update(array('id' => $m_id, 'table' => 'ca_occurrences'), array('$set' => $search_field), array("upsert" => true));
            } catch (MongoException $e) {
                error_log("Errore durante la connessione al mongo " . $e->getMessage());
            }
        }

        if ($argv[1] != null)
            echo "ca_occurrences fatto<br>";
    }
}


/**
 * Recupero tutte le collezioni
 */

if ($collezioni) {

    if ($ca_id != null) {

        $q_all_collection = <<<QUERY
SELECT
  c.collection_id as 'id',
  c.parent_id as 'parent',
  c.type_id as 'type',
  c.idno as 'idno',
  c.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_collections c
  INNER JOIN ca_collection_labels l
    ON (c.collection_id = l.collection_id)
WHERE l.is_preferred = 1
      AND c.deleted = 0
      AND c.collection_id = $ca_id
QUERY;

    } else {

        $q_all_collection = <<<QUERY
SELECT
  c.collection_id as 'id',
  c.parent_id as 'parent',
  c.type_id as 'type',
  c.idno as 'idno',
  c.access as 'access',
  l.name as 'preferred_label',
  l.type_id as 'label_type'
FROM ca_collections c
  INNER JOIN ca_collection_labels l
    ON (c.collection_id = l.collection_id)
WHERE l.is_preferred = 1
      AND c.deleted = 0
QUERY;
    }

    $page = 0;
    $start = 0;
    $limit = 1000;
    $firstStart = $start;


    while (1) {
        $all_collections = $mysql->query($q_all_collection . " LIMIT $start, $limit");
        $start = ($limit * ++$page) + $firstStart;
        $firstStart = 0;

        if ($all_collections->num_rows <= 0) {
            break;
        }


        while ($row = $all_collections->fetch_assoc()) {
            $table_num = 13;
            $search_field = '';
            $coll_id = $row['id'];
            $parent_id = $row['parent'];
            $coll_type = $row['type'];
            $idno = array();
            $idno['value'] = $row['idno'];
            $access = $row['access'];
            $name = trim($row['preferred_label']);
            $name_type = $row['label_type'];


            // Informazioni principali
            $m_id = (int)$coll_id;


            if ($parent_id != null) {
                $m_parent = array(
                    'id' => (int)$parent_id,
                    'relation' => getMongoRelation($parent_id, "ca_collections")
                );
            }

            $m_access = (int)$access;
            $m_idno = $idno;

            $m_type_id = getTypeName($coll_type);
            $m_name = $name;
            $m_alternative_names = getAlternativeCollNames($coll_id);

            // Gestione degli attributi

            $q_all_attribute = <<<QUERY
    SELECT
        a.attribute_id as 'id',
        b.element_id as 'attribute_element',
        element_code as 'code'
    FROM ca_attributes a INNER JOIN ca_attribute_values b ON (a.attribute_id = b.attribute_id) INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
    WHERE a.row_id = {$coll_id}
    AND a.table_num = {$table_num}
QUERY;

            $all_attr = $mysql->query($q_all_attribute);
            $attributes = array();


            while ($row = $all_attr->fetch_assoc()) {
                $attr_id = $row['id'];
                $attr_elem = $row['attribute_element'];
                $elem_code = $row['code'];

                if (!toInsert($elem_code, $coll_type, $table_num))
                    continue;

                // Se non esiste ancora lo creo come oggetto.
                $attr = manageAttribute($attr_id, $attr_elem, $table_num, $coll_type, $obj_id);

                if ($attr) {
                    if (!isset($attributes[$elem_code])) {
                        $attributes[$elem_code] = $attr;
                    } else {
                        // Se invece esiste giÃ  allora lo re-istanzio come array di oggetti
                        $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
                        unset($attributes[$elem_code]); // resetto l'elemento
                        $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
                        // e inserisco il vecchio valore wrappato in una array
                        $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
                    }
                }
            }

            // Recupero dei media
            if (!$ignore_media) {
                $m_media = array();
                $m_media = getAllMedia($coll_id, $table_num);
            }


            //    Recupero delle relazioni

            // Entities
            $m_entities = recuperoEntitaRelazioni($table_num, $coll_id, 'collection');

            // Collections
            $m_collections = recuperoCollezioniRelazioni($table_num, $coll_id, 'collection');


            // Places
            $m_places = recuperoLuoghiRelazioni($table_num, $coll_id, 'collection');

            // Occurrences
            $m_occurrences = recuperoOccorrenzeRelazioni($table_num, $coll_id, 'collection');


            // storage_locations
            $storage_locations = recuperoStorage_locationRelazioni($table_num, $coll_id, 'collection');

            // Objects
            $m_objects = recuperoOggettiRelazioni($table_num, $coll_id, 'collection');

            // vocabulary
            $m_vocabulary = recuperoVocabularyRelazioni($table_num, $coll_id, 'collection');


            //  Generazione della struttura di Mongo

            $mongo_record = array();

            $mongo_record['id'] = $m_id;
            $mongo_record["table"] = "ca_collections";
            $mongo_record['parent'] = $m_parent;
            $mongo_record['access'] = $m_access;
            $mongo_record['preferred_label'] = $m_name;
            if (!empty($m_alternative_names)) $mongo_record['alternative_names'] = $m_alternative_names;

            $mongo_record['idno'] = $m_idno;
            $mongo_record['type_id'] = $m_type_id;


            if (!$ignore_media && !empty($m_media))
                $mongo_record['representations'] = $m_media;

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
            $mongo_record['language'] = 'italian';
            try {
                $mongo->data->remove(array('id' => $m_id, 'table' => 'ca_collections'));
                $mongo->data->insert($mongo_record);

                //create search field
                $search_field = array("search" => creaSearch($mongo, $mongo_record['id'], "ca_collections"));
                $mongo->data->update(array('id' => $m_id, 'table' => 'ca_collections'), array('$set' => $search_field), array("upsert" => true));
            } catch (MongoException $e) {
                error_log("Errore durante la connessione al mongo " . $e->getMessage());
            }
        }

        if ($argv[1] != null)
            echo "ca_collections fatto<br>";
    }
}

/**
 * Update delle modifiche fatte dagli strumenti
 */
if ($update_strumenti) {
    include("exception.php");

    $q_all_obj = <<<QUERY
SELECT
    o.object_id as 'id',
    o.type_id as 'type'
FROM ca_objects o
WHERE o.deleted = 0
AND o.object_id = $ca_id
QUERY;
    $element_code_to_update = "\"" . implode("\", \"", $element_code_to_update) . "\"";
    $all_obj = $mysql->query($q_all_obj);
    while ($row = $all_obj->fetch_assoc()) {

        $obj_type = $row["type"];

        $q_all_attribute = <<<QUERY
    SELECT
        a.attribute_id as 'id',
        a.element_id as 'attribute_element',
        element_code as 'code'
    FROM ca_attributes a INNER JOIN ca_metadata_elements ON (a.element_id = ca_metadata_elements.element_id)
    WHERE a.row_id = {$ca_id}
    AND a.table_num = 57
    AND element_code IN ({$element_code_to_update})
QUERY;
        $all_attr = $mysql->query($q_all_attribute);
        $attributes = array();
        while ($row = $all_attr->fetch_assoc()) {
            $attr_id = $row['id'];
            $attr_elem = $row['attribute_element'];
            $elem_code = $row['code'];

            // Se non esiste ancora lo creo come oggetto.
            $attr = manageAttribute($attr_id, $attr_elem, 57, $obj_type, $ca_id);
            if ($attr) {
                if (!isset($attributes[$elem_code])) {
                    $attributes[$elem_code] = $attr;
                } else {
                    // Se invece esiste giÃ  allora lo re-istanzio come array di oggetti
                    $old_attr = $attributes[$elem_code]; // salvo il vecchio valore
                    unset($attributes[$elem_code]); // resetto l'elemento
                    $attributes[$elem_code][] = $old_attr; // ricreo l'elemento
                    // e inserisco il vecchio valore wrappato in una array
                    $attributes[$elem_code][] = $attr; // aggiungo il nuovo valore
                }
            }
        }

        $mongo_record = array();
        foreach ($attributes as $attr_code => $attribute)
            $mongo_record[$attr_code] = $attribute;

        try {
            $mongo->data->update(array('id' => $ca_id, 'table' => 'ca_objects'), array('$set' => $mongo_record), array("upsert" => false));
        } catch (MongoException $e) {
            error_log("Errore durante la connessione al mongo " . $e->getMessage());
        }
    }

}

// if ($access) {
//     $q_all_obj = "SELECT o.object_id as 'id', o.access as 'access' FROM ca_objects o WHERE o.object_id = 9156 AND o.deleted = 0 ORDER BY id DESC";
//     $page = 0;
//     $start = 0;
//     $limit = 100;
//     $firstStart = $start;

//     while (1) {
//         $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
//         $start = ($limit * ++$page) + $firstStart;
//         $firstStart = 0;

//         if ($all_obj->num_rows <= 0) {
//             break;
//         }

//         while ($row = $all_obj->fetch_assoc()) {
//             $access = (int) $row['access'];
//             $ca_id = (int) $row['id'];
//             $mongo->data->update(
//                 array('id' => $ca_id, 'table' => 'ca_objects'),
//                 array('$set' => array("access" => $access)),
//                 array("upsert" => false));
//         }
//     }

//     echo "Fine oggetti \n";

//     $q_all_obj = "SELECT o.entity_id as 'id', o.access as 'access' FROM ca_entities o WHERE o.deleted = 0";
//     $page = 0;
//     $start = 0;
//     $limit = 1000;
//     $firstStart = $start;

//     while (1) {
//         $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
//         $start = ($limit * ++$page) + $firstStart;
//         $firstStart = 0;

//         if ($all_obj->num_rows <= 0) {
//             break;
//         }

//         while ($row = $all_obj->fetch_assoc()) {
//             $access = (int) $row['access'];
//             $ca_id = (int) $row['id'];

//             $mongo->data->update(
//                 array('id' => $ca_id, 'table' => 'ca_entities'),
//                 array('$set' => array("access" => $access)),
//                 array("upsert" => false));
//         }
//     }

//     echo "Fine entita \n";

//     $q_all_obj = "SELECT o.occurrence_id as 'id', o.access as 'access' FROM ca_occurrences o WHERE o.deleted = 0";
//     $page = 0;
//     $start = 0;
//     $limit = 1000;
//     $firstStart = $start;

//     while (1) {
//         $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
//         $start = ($limit * ++$page) + $firstStart;
//         $firstStart = 0;

//         if ($all_obj->num_rows <= 0) {
//             break;
//         }

//         while ($row = $all_obj->fetch_assoc()) {
//             $access = (int) $row['access'];
//             $ca_id = (int) $row['id'];

//             $mongo->data->update(
//                 array('id' => $ca_id, 'table' => 'ca_occurrences'),
//                 array('$set' => array("access" => $access)),
//                 array("upsert" => false));
//         }
//     }

//     echo "Fine occorrenze \n";

//     $q_all_obj = "SELECT o.collection_id as 'id', o.access as 'access' FROM ca_collections o WHERE o.deleted = 0";
//     $page = 0;
//     $start = 0;
//     $limit = 1000;
//     $firstStart = $start;

//     while (1) {
//         $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
//         $start = ($limit * ++$page) + $firstStart;
//         $firstStart = 0;

//         if ($all_obj->num_rows <= 0) {
//             break;
//         }

//         while ($row = $all_obj->fetch_assoc()) {
//             $access = (int) $row['access'];
//             $ca_id = (int) $row['id'];

//             $mongo->data->update(
//                 array('id' => $ca_id, 'table' => 'ca_collections'),
//                 array('$set' => array("access" => $access)),
//                 array("upsert" => false));
//         }
//     }

//     echo "Fine collezioni \n";

//     $q_all_obj = "SELECT o.place_id as 'id', o.access as 'access' FROM ca_place o WHERE o.deleted = 0";
//     $page = 0;
//     $start = 0;
//     $limit = 1000;
//     $firstStart = $start;

//     while (1) {
//         $all_obj = $mysql->query($q_all_obj . " LIMIT $start, $limit");
//         $start = ($limit * ++$page) + $firstStart;
//         $firstStart = 0;

//         if ($all_obj->num_rows <= 0) {
//             break;
//         }

//         while ($row = $all_obj->fetch_assoc()) {
//             $access = (int) $row['access'];
//             $ca_id = (int) $row['id'];

//            $mongo->data->update(
//                 array('id' => $ca_id, 'table' => 'ca_place'),
//                 array('$set' => array("access" => $access)),
//                 array("upsert" => false));
//         }
//     }

//     echo "Fine luoghi \n";
// }

$mysql->close();
