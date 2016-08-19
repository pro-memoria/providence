<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 16/06/16
 * Time: 12:12
 */

require_once(__CA_MODELS_DIR__ ."/ca_objects.php");


class Rinumeratore  {
    protected $options;
    protected $config;
    protected $category;
    protected $updateItem;

    public function __construct($params)   {
        $this->options = $params;
        $this->config = Configuration::load(__CA_APP_DIR__ . "/plugins/strumenti/tools/Rinumeratore/rinumeratore_opt.conf");
        $this->category = $this->config->get('categorie');
        $this->updateItem = array();
    }

    public function run($idPartenza, $numeroPartenza = 1, $tipo_prefisso = 'nessuno') {
        if ($idPartenza)    {
            $parent = new ca_objects($idPartenza);
            $category = $parent->getTypeName(reset($parent->getTypeInstance()->getHierarchyAncestors(null, array('idsOnly' => true))));
            $num_dev_parent = $parent->get($this->config->get('numero_def'));

            if ($category == 'Root node for object_types')  {
                $category = $parent->getTypeName();
            }

            $prefisso = "";
            if ($num_dev_parent && $num_dev_parent != '') {
                $prefisso = $num_dev_parent . ".";
            }

            // Eccezione Fascicolo -> Unità documentale

            switch ($tipo_prefisso) {
                case 'gerarchico':
                    $this->ricorsivo($idPartenza, $numeroPartenza, $category, $prefisso, $tipo_prefisso);
                    break;
                case 'combinazione':
                    $this->ricorsivo($idPartenza, $numeroPartenza, $category, array($prefisso, $this->options['fisso']), $tipo_prefisso);
                    break;
                case 'nessuno':
                    $this->ricorsivo($idPartenza, $numeroPartenza, $category);
                    break;
                case 'fisso':
                    $this->ricorsivo($idPartenza, $numeroPartenza, $category, $this->options['fisso'], $tipo_prefisso);
                    break;
                default:
                    // Errore
            }

            return $this->updateItem;
        }
    }

    private function ricorsivo($parent, $contatore, $parent_category, $prefisso = NULL, $tipo_prefisso = NULL) {
        $criterio = $this->options[$parent_category];
        $p_object = new ca_objects($parent);
        $children = $p_object->getHierarchyChildren(null, array('idsOnly' => true, 'sort' => 'ca_objects.ordine'));
        unset($p_object);
        if (empty($children))   {
            return;
        }
        $containers = array();
        foreach ($children as $child)   {
            $bimbo = new ca_objects($child);
            $id = $child;
            $type_id = $bimbo->getTypeID();
            $category = $bimbo->getTypeName(reset($bimbo->getTypeInstance()->getHierarchyAncestors(null, array('idsOnly' => true))));

            if ($category == 'Root node for object_types')  {
                $category = $bimbo->getTypeName();
            }

            if (in_array($type_id, $this->config->get('isad4_exce')))   {
                $category = $this->config->get('isad4');
            }

            if ($criterio == 'cascata' && isset($this->category[$category]['unità']))    {
                $containers['isad4'][] = array(
                    "id" => $id,
                    "parent_id" => $parent,
                    "type_id" => $type_id,
                    "category" => $category
                );
            } else {
                $containers[$type_id][] = array(
                    "id" => $id,
                    "parent_id" => $parent,
                    "type_id" => $type_id,
                    "category" => $category
                );
            }
        }

        unset($children, $child, $bimbo);

        $contatore_isad4 = $contatore;
        foreach ($containers as $type_id => $elements)  {
            foreach ($elements as $element) {

                $parent_category = $element['category'];
                if (isset($this->category[$element["category"]]['unita']) && $this->options[$element['category']] != 'cascata')  {
                    $cont = $contatore_isad4;
                    $contatore_isad4++;
                } else {
                    $cont = $contatore;
                }
		
                switch ($tipo_prefisso) {
                    case 'gerarchico':
                        if ($prefisso == "")    {
                            $use_prefisso = "";
                            $new_prefisso = $cont . ".";
                        } else {
                            $use_prefisso = $prefisso;
                            $new_prefisso = $prefisso . $cont . ".";
                        }
                        break;
                    case 'combinazione':
                        $use_prefisso = $prefisso[0] . " " . $prefisso[1];
                        $new_prefisso = array($prefisso[0] . $prefisso[1] . $cont . ".", $prefisso[1]);
                        break;
                    case 'fisso':
                        $use_prefisso = $prefisso;
                        $new_prefisso = $prefisso;
                        break;
                    default:
                        $use_prefisso = "";
                        $new_prefisso = "";
                }

                $this->formatItem($element, $cont, $use_prefisso);

                if ($this->options[$element['category']] == 'cascata')  {
                    $cont = 1;
                }
                $contatore++;
		
                $this->ricorsivo($element['id'], $cont, $parent_category, $new_prefisso, $tipo_prefisso);
            }
        }
    }

    private function formatItem($element, $count, $prefisso) {

        $this->updateItem[$element['id']] = array(
            'numero_def' => $count
        );

        // if ($this->options[$element['category']] == 'romano')   {
        //     $this->updateItem[$element['id']]['romano'] = $this->toRoman($count);
        // }
    }

    private function toRoman($numero)   {
        $n = intval($numero);
        $res = '';
        $roman_numerals = array(
            'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
            'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
            'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
        );

        foreach ($roman_numerals as $roman => $number) {
            $matches = intval($n / $number);
            $res .= str_repeat($roman, $matches);
            $n = $n % $number;
        }
        return $res;
    }
}
