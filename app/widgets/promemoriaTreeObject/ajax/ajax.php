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

$o_db          = new Db( NULL, NULL, false );
$opo_config = json_decode(file_get_contents(__CA_APP_DIR__ . '/widgets/promemoriaTreeObject/conf/promemoriaTreeObjectAttr.json' ));

function saveChildren( $children, $parent_id = "null" ) {
    global $o_db;
    foreach ( $children as $posizione => $nodo ) {
        $id = $nodo->id;
        ( $parent_id == 'null' ) ? $hier_object_id = $id : $hier_object_id = $parent_id;
        $o_db->query( "UPDATE ca_objects SET parent_id = $parent_id, hier_object_id = $hier_object_id, posizione = $posizione, ordine = ($posizione + 1)  WHERE object_id = $id" );
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
    case "get_children":
        $order = empty( $_GET["order"] ) ? "t.ordine, posizione" : $_GET["order"];
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

        if ($user_groups == 2 || in_array($user_groups, 2)) {
            //query per recuperare gli oggetti
            $query = "
                SELECT t.object_id as id, t.parent_id,t.type_id as type, t.posizione, l.name as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                                        FROM
                                        ca_objects t
                                        inner join
                                        ca_object_labels l on (t.object_id=l.object_id)
                                        left join
                                        (
                                                SELECT av.value_decimal1 as date, a.row_id
                                                FROM
                                                ca_attributes a
                                                INNER JOIN
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57
                                                and av.element_id = 39
                                        ) d on (t.object_id = d.row_id)
                    where deleted=0 and l.is_preferred = 1 and ";
        } else {
            $query = "SELECT t.object_id as id, t.parent_id,t.type_id as type, t.posizione, l.name as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                      FROM
                      ((ca_objects t inner join ca_object_labels l on (t.object_id=l.object_id) INNER JOIN ca_acl ON t.object_id = ca_acl.row_id AND ca_acl.table_num = 57))
                                        left join
                                        (
                                                SELECT av.value_decimal1 as date, a.row_id
                                                FROM
                                                ca_attributes a
                                                INNER JOIN
                                                ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                                WHERE
                                                a.table_num = 57
                                                and av.element_id = 39
                                        ) d on (t.object_id = d.row_id)
                    where deleted=0 and l.is_preferred = 1 and (ca_acl.user_id = {$user_id} ";
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
        $query .= " GROUP BY id, t.parent_id, type, t.posizione, text, date ";
        $query .= " ORDER BY $order $verso";
        $qr_result = $o_db->query( $query );
        $i         = 0;
        $o_db->beginTransaction();
        while ( $qr_result->nextRow() ) {
            if ( $order != "t.ordine, posizione" ) {
                $o_db->query( "UPDATE ca_objects SET posizione=$i WHERE object_id=" . $qr_result->get( "id" ) );
            }
            $nodo           = new stdClass();
            $nodo->id = $qr_result->get( "id" );
            $nodo->children = $qr_result->get("hasChildren")>0;

            $nome     = $qr_result->get( "text" );
            $type     = $qr_result->get( "type" );
            $objectId = $qr_result->get( "id" );

            // Scelta dell'icona
            switch($type) {
                case 186:
                    $icon = 'fa fa-archive icon-color';
                break;
                case 189:
                case 187:
                case 188:
                case 192:
                    $icon = 'fa fa-archive icon-color';
                break;
                case 286:
                    $icon = 'fa fa-camera';
                break;
                case 287:
                    $icon = 'fa fa-file-image-o';
                break;
                case 288:
                    $icon = 'fa fa-pencil-square-o';
                break;
                case 289:
                    $icon = 'fa fa-cubes';
                break;
                case 368:
                case 1880:
                    $icon = 'fa fa-music';
                break;
                case 370:
                case 1881:
                case 2021:
                    $icon = 'fa fa-video-camera';
                break;
                case 369:
                case 1882:
                    $icon = 'fa fa-picture-o';
                break;
                case 291:
                    $icon = 'fa fa-book';
                break;
                case 1964:
                    $icon = 'fa fa-file-text';
                break;
                case 281:
                    $icon = 'fa fa-camera icon-color';
                break;
                case 282:
                    $icon = 'fa fa-cubes icon-color';
                break;
                case 283:
                    $icon = 'fa fa-camera-retro';
                break;
                case 284:
                    $icon = 'fa fa-file-image-o icon-color';
                break;
                case 285:
                    $icon = 'fa fa-pencil-square-o icon-color';
                break;
                case 2020:
                    $icon = 'fa fa-video-camera icon-color';
                break;
                case 280:
                    $icon = 'fa fa-folder icon-color';
                break;
            }

            $nodo->icon = $icon;

            //Recupero tutte le informazioni in più che l'utente vuole inserire
            $tipologia = '';
            $text = '';
            $dataIniziale = "";
            $data = "";
            $num_provv = "";
            $num_puntamento = "";
            $num_def = "";
            $text = "";
            $metadataAttr = null;
            if (isset($opo_config->users->$user_id))  {
                $metadataAttr = $opo_config->users->$user_id;
            } else if (isset($opo_config->groups)) {
                foreach (explode(",", $user_groups) as $group_id) {
                    if (isset($opo_config->groups->$group_id))
                        $metadataAttr = $opo_config->groups->$group_id;
                    break;
                }
            }

            foreach ((($metadataAttr == null ) ? $opo_config->default : $metadataAttr) as $metadato) {

                switch ($metadato) {
                    case 'genreform':
                        //iquery per recuperare la descrizione del type
                    $type_desc  = "";
                    $qr_result1 = $o_db->query( "SELECT name_singular FROM ca_list_item_labels WHERE item_id = $type" );
                    $qr_result1->nextRow();
                    $type_desc = $qr_result1->get( "name_singular" );
                    $text .= ' ' . $type_desc . ' ';
                break;
                    case 'datazione':
                        $aa = $o_db->query( "SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE (v.element_id = 283 OR v.element_id = 39) AND a.table_num = 57 AND a.row_id = {$objectId}" );
                        if ( $aa->nextRow() ) {
                            $data = $aa->get( "value" );
                            $dataIniziale = ($data == 'undated') ? '' : $data;
                        }

                        if ($dataIniziale != '')    {
                            $text = $text . "<i>" . $dataIniziale . "</i>" . " ";
                        }
                        break;
                    case 'num_provv':
                        $attr_num_prov = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 138 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_prov->nextRow())  {
                            $num_provv = $attr_num_prov->get('value');
                        }
                        if ($num_provv != "")   {
                            $text .= "(" . $num_provv . ") ";
                        }
                        break;
                    case 'num_puntamento':
                        $attr_num_puntamento = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 139 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_puntamento->nextRow())  {
                            $num_puntamento = $attr_num_puntamento->get('value');
                        }
                        if ($num_puntamento != "")   {
                            $text .= "[" . $num_puntamento . "] ";
                        }
                        break;
                    case 'num_def_numero':
                        $attr_num_def = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 1133 AND a.table_num = 57 AND a.row_id = {$objectId}");
                        if ($attr_num_def->nextRow())  {
                            $num_def = $attr_num_def->get('value');
                        }
                        if ($num_def != "")   {
                            $text .= $num_def . " ";
                        }
                        $num_def = '';
                        break;
                    default:
                        $text2 = '';
                        $sep = " | ";
                        if ($metadato != 'preferred_label') {
                            $att_result = $o_db->query( "SELECT ca_attribute_values.value_longtext1 as 'value' FROM (ca_attribute_values INNER JOIN ca_metadata_elements on ca_metadata_elements.element_id = ca_attribute_values.element_id) INNER JOIN ca_attributes on ca_attribute_values.attribute_id = ca_attributes.attribute_id where element_code = '{$metadato}' and ca_attributes.table_num = 57 and ca_attributes.row_id = {$objectId}" );
                            if ( $att_result->nextRow() ) {
                                $text2 = $att_result->get('value');
                            }

                            if (($metadato == 'txSegnatura' || $metadato == 'txSegnOrig' || $metadato == 'segnature_originali') && $text2 != '') {
                                $text2 = "(" . $text2 . ")";
                            }
                        } else {
                            $text2 = $nome;
                        }
                        if ($text2 == "")   $sep = "";
                        $text = $text . $text2 . $sep;
                        break;
                }

            }
            if ($tipologia != '') $text = $tipologia . $text;
            $nodo->text = "<span>".( rtrim(trim($text), '|') )."</span>";

            //if (!isset($_GET['show_eye']) || $_GET['show_eye'] == "true")
            //    $nodo->text = $nodo->text . " <i class='fa fa-angle-right showsummary'></i>";

            $return[]    = $nodo;
            $i ++;
        }
        $o_db->commitTransaction();
        break;
    case "get_children_contestuale":
        $user_id = empty( $_GET['user_id'] ) ? 1 : $_GET["user_id"];
        $user_groups = empty( $_GET['user_groups'] ) ? null : $_GET["user_groups"];

        if ($user_groups == 2 || in_array($user_groups, 2)) {
            //query per recuperare gli oggetti
            $query = "
                SELECT t.object_id as id, t.parent_id,t.type_id as type, t.posizione, l.name as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                    FROM
                    ca_objects t
                    inner join
                    ca_object_labels l on (t.object_id=l.object_id)
                    left join
                    (
                            SELECT av.value_decimal1 as date, a.row_id
                            FROM
                            ca_attributes a
                            INNER JOIN
                            ca_attribute_values  av ON av.attribute_id = a.attribute_id
                            WHERE
                            a.table_num = 57
                            and av.element_id = 39
                    ) d on (t.object_id = d.row_id)
                    where deleted=0 and l.is_preferred = 1 and ";
        } else {
            $query = "SELECT t.object_id as id, t.parent_id,t.type_id as type, t.posizione, l.name as text, d.date as date, (SELECT COUNT(*) FROM ca_objects p WHERE t.object_id = p.parent_id) hasChildren
                      FROM
                      ((ca_objects t inner join ca_object_labels l on (t.object_id=l.object_id) INNER JOIN ca_acl ON t.object_id = ca_acl.row_id AND ca_acl.table_num = 57))
                            left join
                            (
                                    SELECT av.value_decimal1 as date, a.row_id
                                    FROM
                                    ca_attributes a
                                    INNER JOIN
                                    ca_attribute_values  av ON av.attribute_id = a.attribute_id
                                    WHERE
                                    a.table_num = 57
                                    and av.element_id = 39
                            ) d on (t.object_id = d.row_id)
                    where deleted=0 and l.is_preferred = 1 and (ca_acl.user_id = {$user_id} ";
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
        $query .= " ORDER BY t.ordine, posizione ASC";
        $qr_result = $o_db->query( $query );
        $i         = 0;
        $o_db->beginTransaction();
        while ( $qr_result->nextRow() ) {
            $nodo           = new stdClass();
            $nodo->id = $qr_result->get( "id" );
            $nodo->children = $qr_result->get("hasChildren")>0;

            $nome     = $qr_result->get( "text" );
            $type     = $qr_result->get( "type" );
            $objectId = $qr_result->get( "id" );

            // Scelta dell'icona
            switch($type) {
                case 186:
                    $icon = 'fa fa-archive icon-color';
                break;
                case 189:
                case 187:
                case 188:
                case 192:
                    $icon = 'fa fa-archive icon-color';
                break;
                case 286:
                    $icon = 'fa fa-camera';
                break;
                case 287:
                    $icon = 'fa fa-file-image-o';
                break;
                case 288:
                    $icon = 'fa fa-pencil-square-o';
                break;
                case 289:
                    $icon = 'fa fa-cubes';
                break;
                case 368:
                case 1880:
                    $icon = 'fa fa-music';
                break;
                case 370:
                case 1881:
                case 2021:
                    $icon = 'fa fa-video-camera';
                break;
                case 369:
                case 1882:
                    $icon = 'fa fa-picture-o';
                break;
                case 291:
                    $icon = 'fa fa-book';
                break;
                case 1964:
                    $icon = 'fa fa-file-text';
                break;
                case 281:
                    $icon = 'fa fa-camera icon-color';
                break;
                case 282:
                    $icon = 'fa fa-cubes icon-color';
                break;
                case 283:
                    $icon = 'fa fa-camera-retro';
                break;
                case 284:
                    $icon = 'fa fa-file-image-o icon-color';
                break;
                case 285:
                    $icon = 'fa fa-pencil-square-o icon-color';
                break;
                case 2020:
                    $icon = 'fa fa-video-camera icon-color';
                break;
                case 280:
                    $icon = 'fa fa-folder icon-color';
                break;
            }

            $nodo->icon = $icon;

            //Recupero tutte le informazioni in più che l'utente vuole inserire
            $tipologia = '';
            $numerazione = '';
            $attr_num_def = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE v.element_id = 1133 AND a.table_num = 57 AND a.row_id = {$objectId}");
            if ($attr_num_def->nextRow())  {
                $numerazione = $attr_num_def->get('value');
            }
            if ($numerazione == "") {
                $attr_num_puntamento = $o_db->query("SELECT v.value_longtext1 as 'value' FROM ca_attribute_values v INNER JOIN ca_attributes a ON (a.attribute_id = v.attribute_id) WHERE (v.element_id = 197 OR v.element_id = 199) AND a.table_num = 57 AND a.row_id = {$objectId}");
                if ($attr_num_puntamento->nextRow())  {
                    $numerazione = $attr_num_puntamento->get('value');
                }
            }
            $text = $tipologia . " " . $numerazione . " " . $nome;
            $nodo->text = "<span>".( trim($text) )."</span>";

            //if (!isset($_GET['show_eye']) || $_GET['show_eye'] == "true")
            //    $nodo->text = $nodo->text . " <i class='fa fa-angle-right showsummary'></i>";

            $return[]    = $nodo;
            $i ++;
        }
        $o_db->commitTransaction();
        break;
    case "save_node":
        $d = $_POST['data'];
        $data = json_decode($d);
        $o_db->beginTransaction();
        saveChildren( $data );
        $o_db->commitTransaction();
        $return["result"] = "OK";
        break;
    case "autocomplete":
        $query = <<<QUERY
            SELECT `name`, ca_metadata_elements.element_code as 'code', ca_metadata_elements.datatype as 'datatype', ca_metadata_elements.element_id as 'id'
            FROM (`ca_editor_ui_bundle_placements` euibp
              INNER JOIN (`ca_metadata_elements`
                INNER JOIN `ca_metadata_element_labels`
                  ON (`ca_metadata_elements`.`element_id` = `ca_metadata_element_labels`.`element_id`)
                )
                ON (REPLACE(`bundle_name`, 'ca_attribute_', '') = `element_code`))
              LEFT JOIN ca_editor_ui_screen_type_restrictions euistr ON (euibp.screen_id = euistr.screen_id)
            WHERE euibp.screen_id IN
                  (SELECT `screen_id`
                   FROM `ca_editor_ui_screens`
                   WHERE ca_editor_ui_screens.`ui_id` =
                         (SELECT `ui_id`
                          FROM `ca_editor_uis`
                          WHERE `editor_code` = 'editor-schede'))
            AND `bundle_name` LIKE '%ca_attribute%' AND `ca_metadata_elements`.datatype != 22
            GROUP BY name, code, datatype, id 
            ORDER BY name
QUERY;

        $result = $o_db->query($query);
        $result2 = $o_db->query("SELECT element_code as 'code' FROM ca_metadata_elements WHERE element_id IN (SELECT ca_metadata_elements.parent_id FROM ca_metadata_elements WHERE ca_metadata_elements.parent_id IS NOT NULL AND ca_metadata_elements.datatype = 22)");
        $elim = $result2->getAllRows();
        while ( $result->nextRow() ) {
            $code =  $result->get( "code" );
            if ($result->get( "datatype" ) == 0)    {
                $result2 = $o_db->query("SELECT element_code as 'code', name FROM ca_metadata_elements INNER JOIN `ca_metadata_element_labels` ON (`ca_metadata_elements`.`element_id` = `ca_metadata_element_labels`.`element_id`) WHERE parent_id = ".$result->get("id"));
                while ($result2->nextRow()) {
                    $return['data'][$result2->get( "code" )] = $result2->get( "name" );
                }
            } else {
                $return['data'][$code] = $result->get( "name" );
            }
        }
    $return['data']["genreform"] = "Tipologia oggetto";

        $user_id = $_GET['user_id'];
        $user_groups = $_GET['user_groups'];

        $select = array();
        if ($user_id != 1)  {
            if (isset($opo_config->users->$user_id))  {
                $select = $opo_config->users->$user_id;
            } else if (count($opo_config->groups) > 0) {
                foreach (explode(",", $user_groups) as $group_id) {
                    if (isset($opo_config->groups->$group_id)) {
                        $select = $opo_config->groups->$group_id;
                        break;
                    }
                }
            }

            if (empty($select))
                $select = $opo_config->default;
        } else {
            $select = $opo_config->default;
        }
        foreach ($select as $k => $met)   {
            if ($met == 'preferred_label')  $return['select'][] = 'preferred_label';
            else    $return['select'][] = $return['data'][$met];
        }
        break;
    case 'salvaOpzioni':
        $metadata = $_GET['metadata'];
        $user_id = $_GET['user_id'];
        $group_ids = $_GET['user_groups'];
        foreach ($metadata as $data) {
            $metadataAtt[] = $data;
        }

        switch ($user_id)   {
            case 1:
                $opo_config->default = $metadataAtt;
                break;
            case -1:
                $groups = (array)$opo_config->groups;
                foreach (explode(",", $group_ids) as $group_id)
                    $groups[$group_id] = $metadataAtt;
                $opo_config->groups = (object)$groups;
                break;
            default:
                $users = (array)$opo_config->users;
                $users[$user_id] = $metadataAtt;
                $opo_config->users = (object) $users;
        }

        //Modifico il file di configurazione in modo che al caricamento ricarichi le informazioni
        file_put_contents( __CA_APP_DIR__ . '/widgets/promemoriaTreeObject/conf/promemoriaTreeObjectAttr.json', json_encode($opo_config));
        break;
    default:
        break;
}
echo json_encode( $return );
