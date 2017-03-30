<?php
require_once(__CA_MODELS_DIR__ . "/ca_bundle_displays.php");
require_once(__CA_MODELS_DIR__ . "/ca_objects.php");
require_once(__CA_LIB_DIR__ . "/ca/Search/ObjectSearch.php");
require_once(__CA_LIB_DIR__ . "/core/Db.php");

/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 21/04/16
 * Time: 16:27
 */
class StampaInventario {
    private $config;
    private $start_id;
    private $typeXview;
    private $phpWord;
    private $request;
    private $stack;
    private $searcher;
    private $o_db;
	private $style_path;


    public function __construct($po_request, $config) {
        $this->request = $po_request;
        $this->config = $config;

        $this->stack = new Stack();
        $this->searcher = new ObjectSearch();

        $this->o_db = new Db("", null, false);
	    $this->style_path = __CA_APP_DIR__ . '/plugins/strumenti/tools/StampaInventario/styles.php';
    }

    public function run($start_id, $typeXview = array()) {
	    include($this->style_path);

        $this->start_id = $start_id;
        $this->typeXview = $typeXview;

        // Generazione del frontpage e inizializzazione del documeno word
        $file = $this->frontpage();
        $this->phpWord = \PhpOffice\PhpWord\IOFactory::load($file);
        $this->phpWord->setDefaultFontName($this->config->get('fontFamily'));
        $this->phpWord->setDefaultFontSize($this->config->get('fontSize'));

        // Aggiungo il primo elemento dallo stack;
        $object= new ca_objects($start_id);
        $this->stack->push($object, 0, $this->getTypeInfo($object->getTypeInstance()));
        $changeParent = null;
        $currentSection = $this->createSection();
        $currentSection->addPageBreak();
        // $currentSection->addTOC();
        $insert = array();
	    $map_entities = array();

        $livels = $this->config->get('indent_levels');

        $types = array_flip($this->config->get('stampa_inventario_types'));

	    $index = 1;

        // Incomincio ad analizzare gli elementi
        while (!$this->stack->isEmpty()) {
            $obj = $this->stack->pop();
            $object = $obj['obj'];
            $currentLivel = $obj['liv'];
            $type = $obj['type'];

            $currentObjId = $object->get('object_id');

            if ($object->getTypeID() == $types['Fondi'])   {
                $currentSection = $this->createSection(true);
                $header = $currentSection->addHeader();
                $header->addText($this->getPreferredLabel($object));
	            $header->addLine(array('weight' => 1, 'width' => 100, 'height' => 0, 'color' => 635552));
	            $footer = $currentSection->addFooter();
	            $footer->addPreserveText('{PAGE}', null, array('alignment' => 'center', 'align' => 'center'));
                $parag = $this->printTitle($currentSection, $this->getPreferredLabel($object), 'h1', 0, $index);
            } else {
                if ($type['isad'] == 'ISAD3' || $type['isad'] == 'ISAD4')   {
                    if ($type['isad'] == 'ISAD3')   {
	                    $parag = $this->printTitle($currentSection, $this->getPreferredLabel($object), 'h4', $currentLivel, $index, true);
                    } else {
	                    $parag = $this->printTitle($currentSection, $this->getPreferredLabel($object), 'h5', $currentLivel, $index, true);
                    }
                } else {
                    $h = 'h' . (filter_var($type['isad'], FILTER_SANITIZE_NUMBER_INT) + $currentLivel);
	                $parag = $this->printTitle($currentSection, $this->getPreferredLabel($object), $h, $currentLivel, $index);
                }
                // $paragraph = $this->newParagraph($currentSection, 2);
            }

            $obj_type = ($type['isad'] == 'ISAD4') ? 'documentarie' : $object->getTypeID();

            $metadataView = $this->typeXview[$obj_type];

            if ($metadataView != null)  {
                // Stampo le informazioni
	            $this->printMetadata($metadataView, $parag, $currentLivel, $object);

                if ($object->getTypeID() == $types['Fondi'])    {
                    $currentSection->addPageBreak();
                }
            }

            $getChildren = $this->o_db->query("SELECT object_id FROM ca_objects WHERE parent_id = " . $currentObjId . " AND deleted = 0 ORDER BY ordine DESC, object_id DESC");
            while ($getChildren->nextRow()) {
                $obj = new ca_objects($getChildren->get('object_id'));
                if (!$obj->getTypeInstance()) { continue; }
                $type = $this->getTypeInfo($obj->getTypeInstance());
                if ($type['isad'] == 'ISAD4')   {
                    $this->stack->push($obj, $currentLivel + $livels[$types['Unità documentarie']], $type);
                } else {
                    $this->stack->push($obj, $currentLivel + $livels[$obj->getTypeID()], $type);
                }

            }
            // Recupero le entità
	        $entities = $object->getRelatedItems(20);

	        foreach ($entities as $entity) {
		        if (!isset($map_entities[$entity['entity_id']])) {
			        $map_entities[ $entity['entity_id'] ]['name'] = array(
			        	'displayname' => $entity['displayname'],
				        'nome' => $entity['forename'],
				        'cognome' => $entity['surname']
			        );
		        }
		        $map_entities[$entity['entity_id']]['index'][] = $index;
	        }
			$index++;
        }
	    $currentSection->addPageBreak();

        // Genera la struttura per le entità

	    $this->headerPage($this->phpWord->addSection());
	    $entities_section = $this->phpWord->addSection($entity_section);
	    usort( $map_entities, function ( $a, $b ) {
		    $cognomeA = $a['name']['cognome'];
		    $cognomeB = $b['name']['cognome'];
		    if (!isset($cognomeA) || !isset($cognomeB)) {
			    if ($a['name']['displayname'] == $b['name']['displayname']) {
				    return 0;
			    }
			    return ($a['name']['displayname'] < $b['name']['displayname']) ? -1 : 1;
		    } else {
			    if ($cognomeA == $cognomeB) {
				    return 0;
			    }
			    return ($cognomeA < $cognomeB) ? -1 : 1;
		    }
	    });
	    foreach ($map_entities as $entity) {
		    $this->printEntity($entities_section, $entity);
	    }


        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($this->phpWord, 'Word2007');
        $objWriter->save($file);

        return $file;
    }

    private function frontpage() {
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor(__CA_APP_DIR__ . '/plugins/strumenti/tools/StampaInventario/TemplateInventario.docx');

        $templateProcessor->setValue('nomesito', htmlspecialchars(__CA_APP_DISPLAY_NAME__));

        $user = $this->request->getUser();
        $templateProcessor->setValue('username', htmlspecialchars($user->getName()));
        $groupList = $user->getUserGroups();
        $groupS = '';
        foreach ($groupList as $group) $groupS .= $group['name'] . ",";

        $templateProcessor->setValue('groupname', htmlspecialchars($groupS));

        $object = new ca_objects($this->start_id);

        $id = $object->get('object_id');
        $preferred_label = $object->getPreferredLabels();
        $preferred_label = reset($preferred_label[$id]);
        $preferred_label = $preferred_label[0]['name'];
        $templateProcessor->setValue('nomefondo', htmlspecialchars($preferred_label));

	    $templateProcessor->setValue('dataodierna', date('d/m/Y'));

        $templateProcessor->saveAs(__CA_BASE_DIR__ . '/import/' . $preferred_label . 'Inventario.docx');

        return __CA_BASE_DIR__ . '/import/' . $preferred_label . 'Inventario.docx';
    }

    private function printTitle(&$section, $title, $h = 'h3', $livel = 0, $index, $bold = true) {
        $hn = $this->config->get('heading');
        $fontStyle = array('bold' => $bold, 'size' => $hn[$h]);
        $paragraph = array('spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(-10), 'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.4));
        $pa = $this->newParagraph($section, $livel);
        $pa->addText(self::encodeText(trim($title)), $fontStyle, $paragraph);
	    $pa->addText(self::encodeText("\t ({$index}) "), array('bold' => false, 'size' => 8, 'italic' => true), array('align' => 'end'));
	    $pa->addText("\n");
	    $pa->addTextBreak();
	    return $pa;
    }

    private function createSection($brakePage = false) {
        $sectionStyle = array(
            'breakType' => ($brakePage ? 'nextPage' : 'continuous'),
            'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
            'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5),
	        'pageNumberingStart' => 1,
        );

        return $this->phpWord->addSection($sectionStyle);
    }

    private function newParagraph(&$section, $indentation = 0, $brakePage = false, $space = 0) {
        $style = array(
            'indent' => $indentation,
            'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip($space),
            'spaceBefore' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip($space),
	        'keepLines' => true,
            'pageBreakBefore' => $brakePage,
	        'lineHeigth' => 1.2
        );
        return $section->addTextRun($style);
    }

    private function getTypeInfo($list) {
        $idno = $list->get('idno');
        $father = $list;
        while (($parent_id = $list->get('parent_id')) != null)  {
            $father = $list;
            $list = new ca_list_items($parent_id);
        }
        $isad = $father->get('idno');
        $livelliIsad = $this->config->get('livelliIsad');
        return array('type' => $idno, 'isad' => $livelliIsad[$isad]);
    }

    private function getPreferredLabel($object) {
        $id = $object->get('object_id');
        $preferred_label = $object->getPreferredLabels();
        $preferred_label = reset($preferred_label[$id]);
        $preferred_label = $preferred_label[0]['name'];
        $numerazione_definitiva = $this->config->get('numero_definitivo');
        $template = <<<TEMPLATE
<ifdef code="{$numerazione_definitiva['num_rom']}"><ifdef code="{$numerazione_definitiva['prefix']}">^{$numerazione_definitiva['prefix']} </ifdef>^{$numerazione_definitiva['num_rom']}<ifdef code="{$numerazione_definitiva['bis']}">^{$numerazione_definitiva['bis']}</ifdef></ifdef><ifnotdef code="{$numerazione_definitiva['num_rom']}"><ifdef code="{$numerazione_definitiva['prefix']}">^{$numerazione_definitiva['prefix']}</ifdef><ifdef code="{$numerazione_definitiva['num_def']}">^{$numerazione_definitiva['num_def']}.</ifdef><ifdef code="{$numerazione_definitiva['bis']}">^{$numerazione_definitiva['bis']}</ifdef></ifnotdef>
TEMPLATE;

        $options = array(
            'convertCodesToDisplayText' => true,
            "template" => $template
        );
        $numerazione_definitiva = $object->get($numerazione_definitiva['container'], $options);

        $data = $this->config->get('data');
        $template = <<<TEMPLATE
<ifdef code="{$data['display']}">^{$data['display']}</ifdef>
TEMPLATE;

        $options = array(
            'convertCodesToDisplayText' => true,
            "template" => $template
        );
        $data = $object->get($data['container'], $options);
        $data = ($data != "") ? ', ' . $data : '';

        return $numerazione_definitiva . " " . $preferred_label . $data;
    }

    private function printMetadata($metadataView, $currentSection, $currentLivel, $object) {
	    include($this->style_path);

	    $t_display = new ca_bundle_displays();
	    $t_display->load($metadataView);
	    $content = "";
	    $va_placements = $t_display->getPlacements(array('returnAllAvailableIfEmpty' => true, 'table' => 'ca_objects', 'access' => __CA_BUNDLE_DISPLAY_READ_ACCESS__, 'no_tooltips' => true, 'format' => 'simple', 'settingsOnly' => true));
	    // $paragraph = $this->newParagraph($currentSection, $currentLivel, false, 1);
	    $paragraph = $currentSection;
	    $paragraph->addTextBreak();
	    foreach($va_placements as $id => $placement)   {
		    $value = $t_display->getDisplayValue($object, $id, array('convertCodesToDisplayText' => true, 'forReport' => true));
		    if ($value) {
			    $dom = new domDocument('1.0', 'utf-8');
			    $content = strip_tags(self::br2nl($value), '<b><i>');
			    $dom->loadHTML($content);
			    //discard white space
			    $dom->preserveWhiteSpace = false;
			    $boldContain= $dom->getElementsByTagName('b'); // here u use your desired tag
			    // $content = self::br2nl($value) . " -- ";
			    if ($boldContain->length > 0) {
				    foreach ($boldContain as $bld) {
					    $paragraph->addText(self::encodeText(trim(strip_tags($bld->nodeValue))), array("bold" => true));
					    $paragraph->addText(' '.self::encodeText(trim(strip_tags($bld->nextSibling->nodeValue))) . " \n", array("bold" => false));
				    }
			    } else {
				    // Recupero la label dell'oggetto
				    $paragraph->addText(self::encodeText(trim($placement['display'])).": ", array("bold" => true));
				    $content = self::br2nl($value);
				    $paragraph->addText(self::encodeText(trim(strip_tags($content))) . "\n");
			    }
			    $paragraph->addTextBreak(1);
		    }
	    }
    }

    private function headerPage($section) {
	    include($this->style_path);

	    $section->addText(self::encodeText("Indice dei nomi"), $entity_header_font);
	    $section->addText(self::encodeText("I numeri in grassetto accanto a ciascun lemma costituiscono il rimando al puntatore associato a ciascuna unità e riportato a fianco di ogni descrizione archivistica"));
	    $section->addTextBreak();
    }

    private function printEntity($section, $entity) {
	    include($this->style_path);

		$textRun = $section->createTextRun();
	    $textRun->addText(self::encodeText($entity['name']['displayname']), $entity_row_name_font);
	    foreach ($entity['index'] as $obj) {
		    $textRun->addText(", ".self::encodeText($obj), $entity_row_index_font);
	    }
	    $section->addTextBreak();
    }

    static function encodeText($text) {
        return utf8_decode(htmlspecialchars($text));
    }

    static function br2nl( $input ) {
        return preg_replace('/<br\s?\/?>/ius', "\n", str_replace("\n","",str_replace("\r","", htmlspecialchars_decode($input))));
    }
}

class Stack {
    public $stack = array();

    public function push($elem, $level, $type) {
        array_unshift($this->stack, array("obj" => $elem, "liv" => $level, "type" => $type));
    }

    public function pop()   {
        return array_shift($this->stack);
    }

    public function isEmpty()   {
        return count($this->stack) == 0;
    }
}
