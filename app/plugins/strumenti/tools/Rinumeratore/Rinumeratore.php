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
        $this->storico = array();
        $this->updateItem = array();
    }

    public function run($idPartenza, $numeroPartenza = 1, $tipo_prefisso = 'nessuno', $type) {
        if ($idPartenza)    {
            $parent = new ca_objects($idPartenza);
            $num_dev_parent = $parent->get($this->config->get('numero_def'));

            $prefisso = "";
            if ($num_dev_parent && $num_dev_parent != '') {
                $prefisso = $num_dev_parent . ".";
                $this->storico[$idPartenza] = $num_dev_parent;
            }

            // Eccezione Fascicolo -> Unità documentale

            switch ($tipo_prefisso) {
                case 'gerarchico':
                    if ($num_dev_parent && $num_dev_parent != '') {
                        $prefisso = $num_dev_parent . ".";
                    } else {
                        $prefisso = "";
                    }
                    break;
                case 'combinazione':
                    if ($num_dev_parent && $num_dev_parent != '') {
                        $prefisso = array($num_dev_parent . ".", $this->options['fisso']);
                    } else {
                        $prefisso = array("", $this->options['fisso']);
                    }
                    break;
                case 'nessuno':
                    $prefisso = NULL;
                    break;
                case 'fisso':
                    $prefisso = $this->options['fisso'];
                    break;
                default:
                    $prefisso = "";
            }



            if (reset($this->options) == "cascata") {
                $this->ricorsivoCascata($idPartenza, $numeroPartenza, $type, $prefisso, $tipo_prefisso);
            } else {
                $this->ricorsivoChiusa($idPartenza, $numeroPartenza, $type, $prefisso, $tipo_prefisso);
            }

            return $this->updateItem;
        }
    }

    private function ricorsivoChiusa($parent, &$contatore, $type, $prefisso = NULL, $tipo_prefisso = NULL) {
        $p_object = new ca_objects($parent);
        $children = $p_object->getHierarchyChildren(null, array('idsOnly' => true, 'sort' => 'ca_objects.ordine'));
        unset($p_object);
        if (empty($children))   {
            return;
        }

        foreach ($children as $child)   {
            $bimbo = new ca_objects($child);
            $listp = $bimbo->getTypeInstance()->getHierarchyAncestors(null, array('idsOnly' => true));
            $bimbo_type = $bimbo->getTypeCode($listp[count($listp) -2]);
            if ($bimbo_type == 'Root node for object_types')  {
                $bimbo_type = $bimbo->getTypeCode();
            }
            if ($bimbo_type == $type)  {
                switch ($tipo_prefisso) {
                    case 'gerarchico':
                        $use_prefisso = $prefisso;
                        break;
                    case 'combinazione':
                        $use_prefisso = $prefisso[0] . " " . $prefisso[1];
                        break;
                    case 'fisso':
                        $use_prefisso = $prefisso;
                        break;
                    default:
                        $use_prefisso = "";
                }

                $this->storico[$child] = $contatore;

                $this->formatItem($child, $contatore, $use_prefisso);
                $contatore++;
            }
        }
        foreach ($children as $child) {
            switch ($tipo_prefisso) {
                case 'gerarchico':
                    if ($prefisso == "") {
                        $new_prefisso = $this->storico[$child] . ".";
                    } else {
                        $new_prefisso = $prefisso . $this->storico[$child] . ".";
                    }
                    break;
                case 'combinazione':
                    $new_prefisso = array($prefisso[0] .  $this->storico[$child] . ".", $prefisso[1]);
                    break;
                case 'fisso':
                    $new_prefisso = $prefisso;
                    break;
                default:
                    $new_prefisso = "";
            }
            $this->ricorsivoChiusa($child, $contatore, $type, $new_prefisso, $tipo_prefisso);
        }
    }

    private function ricorsivoCascata($parent, $contatore, $type, $prefisso = NULL, $tipo_prefisso = NULL) {
        $p_object = new ca_objects($parent);
        $children = $p_object->getHierarchyChildren(null, array('idsOnly' => true, 'sort' => 'ca_objects.ordine'));
        unset($p_object);
        if (empty($children))   {
            return;
        }

        foreach ($children as $child) {
            $bimbo = new ca_objects($child);
            $listp = $bimbo->getTypeInstance()->getHierarchyAncestors(null, array('idsOnly' => true));
            $bimbo_type = $bimbo->getTypeCode($listp[count($listp) -2]);
            if ($bimbo_type == 'Root node for object_types')  {
                $bimbo_type = $bimbo->getTypeCode();
            }
            if ($bimbo_type == $type) {
                switch ($tipo_prefisso) {
                    case 'gerarchico':
                        $use_prefisso = $prefisso;
                        break;
                    case 'combinazione':
                        $use_prefisso = $prefisso[0] . " " . $prefisso[1];
                        break;
                    case 'fisso':
                        $use_prefisso = $prefisso;
                        break;
                    default:
                        $use_prefisso = "";
                }

                $this->storico[$child] = $contatore;

                $this->formatItem($child, $contatore, $use_prefisso);
                $contatore++;
            }
        }

        foreach ($children as $child) {
            switch ($tipo_prefisso) {
                case 'gerarchico':
                    if ($prefisso == "") {
                        $new_prefisso = $this->storico[$child] . ".";
                    } else {
                        $new_prefisso = $prefisso . $this->storico[$child] . ".";
                    }
                    break;
                case 'combinazione':
                    $new_prefisso = array($prefisso[0] . $this->storico[$child] . ".", $prefisso[1]);
                    break;
                case 'fisso':
                    $new_prefisso = $prefisso;
                    break;
                default:
                    $new_prefisso = "";
            }
            $this->ricorsivoCascata($child, 1, $type, $new_prefisso, $tipo_prefisso);
        }

    }

    private function formatItem($element, $count, $prefisso) {

        $this->updateItem[$element] = array(
            'numero_def' => $count,
            'prefix' => $prefisso,
        );

        if (isset($this->options['romano']))   {
            $this->updateItem[$element]['romano'] = $this->toRoman($count);
        }
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
