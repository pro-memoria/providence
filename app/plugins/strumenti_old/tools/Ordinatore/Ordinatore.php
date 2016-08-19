<?php

require_once(__CA_LIB_DIR__ . "/core/Db.php");
require_once(__CA_MODELS_DIR__ ."/ca_objects.php");
require_once(__CA_LIB_DIR__ ."/ca/Search/ObjectSearch.php");
require_once(__CA_LIB_DIR__ ."/ca/Search/ObjectSearchResult.php");
include_once(__DIR__."/criteri.php");

global $db;

class Ordinatore {
    protected $toSort;
    protected $toChange;
    protected $options;
    protected $criteri;

    public function __construct($toSort, $connection = null) {
        global $db;

        $this->toSort = $toSort;
        $this->options = Configuration::load(__CA_APP_DIR__.'/plugins/strumenti/tools/Ordinatore/ordinatore_opt.conf');
        $this->criteri = $this->options->get("map_criteri");
        if ($connection == null)    {
            $db = new Db("", null, false);
        } else {
            $db = $connection;
        }
    }

    public function run($parent_id, $toSort)   {
        $parent = new ca_objects($parent_id);
        $children = $parent->getHierarchyChildren(null, array('idsOnly' => true, 'sort' => 'ca_objects.ordine'));
        $items = array();
        foreach ($children as $child)   {
            $item = $this->generaItem($child, $toSort);
            if ($item != null)  {
                $items[$child] = $item[$child];
            }
        }
        $item_sort = $this->sortItems($items, $toSort);
        $this->setChange($item_sort);


        foreach ($children as $child) {
            $this->run($child, $toSort);
        }

        return $this->toChange;
    }

    private function generaItem($obj_id, $toSort) {
        $object = new ca_objects($obj_id);
        $object_type = $this->typeInfo($object);
        $items = array();
        if (isset($toSort['all'])) {
            $items[$obj_id] = array('type' => 'all');
            $metadata = $this->options->get($toSort['all']);
            foreach ($metadata as $code) {
                $items[$obj_id][$code] = $object->get($code);
            }
            $items[$obj_id]['id'] = $obj_id;
        } else {
            if (isset($toSort[$object_type['name']])) { // Se l'elemento che ho cercato è tra i valori da ordinare
                $items[$obj_id] = array('type' => $object_type);
                $metadata = $toSort[$object_type['name']];
                if (!is_array($metadata))    {
                    $metadata = $this->options->get($metadata);
                }
                foreach ($metadata as $code => $fuffa) {
                    $items[$obj_id][$code] = $object->get($code);
                }
                $items[$obj_id]['id'] = $obj_id;
            } else if (isset($toSort[$object_type['parent']])) {
                $items[$obj_id] = array('type' => $object_type);
                $metadata = $this->options->get($toSort[$object_type['parent']]);
                foreach ($metadata as $code) {
                    $items[$obj_id][$code] = $object->get($code);
                }
                $items[$obj_id]['type3'] = true;
                $items[$obj_id]['id'] = $obj_id;
            }
        }
        return $items;
    }

    private function typeInfo($obj = NULL)  {
        $plural = $this->options->get('plural');
        $istance = $obj->getTypeName(reset($obj->getTypeInstance()->getHierarchyAncestors(null, array('idsOnly' => true))));
        return array(
            'id' => $obj->getTypeID(),
            'code' => $obj->getTypeCode(),
            'name' => str_replace(" ", "_", $obj->getTypeName()),
            'parent' => $istance
        );
    }

    private function sortItems($items, $toSort) {
        if (!isset($toSort['all'])) { // Se ho scelto il caso 2 o 1
            $items = $this->useType($items, $toSort);
        } else {
            $items = $this->mysort(array_values($items), array_values($this->options->get($toSort['all'])));
        }
        return $items;

    }

    private function useType($items, $toSort) {
        $groups = array();
        foreach ($items as $id => $item)   {
            if (isset($item['type3']))  {
                $groups[$item['type']['parent']][] = $item;
            } else {
                $groups[$item['type']['name']][] = $item;
            }
        }

        foreach ($groups as $type => &$group) {
            if (is_array($toSort[$type])) {
                $this->mysort($group, array_keys($toSort[$type]));
            } else {
                $this->mysort($group, array_values($this->options->get($toSort[$type])));
            }
        }
        $this->sortByPriority($groups);
        $newItems = array();
        foreach ($groups as $group) {
            $newItems = array_merge($newItems, $group);
        }

        return $newItems;
    }

    private function sortByPriority(&$items)    {
        global $priority;
        $priority = $this->options->get('priority');
        uksort($items, function ($a, $b) {
            global $priority;
            return ((int) $priority[$a]) - ((int) $priority[$b]);
        });
    }

    private function mysort(&$items, $criteria)   {
        $range_to_sort = array(
            array(0, count($items) -1)
        );
        foreach ($criteria as $key) {
            foreach ($range_to_sort as $range) {
                $this->mergesort($items, $key, $range);
            }
            $range_to_sort = array();
            $from = 0;
            $to = 0;
            for ($i = 0; $i < count($items) - 2; $i++) {
                if ($items[$i][$key] == $items[$i + 1][$key]) {
                    $to = $i + 1;
                } else {
                    if ($to != 0) {$range_to_sort[] = array($from, $to);}
                    $from = $i;
                    $to = 0;
                }
            }
        }
	   return $items;
    }

    private function mergesort(&$items, $key, $range)    {
        $this->mergesortRic($items, $range[0], $range[1], array(), $key);
    }

    private function mergesortRic(&$a, $fst, $lst, $aux, $key) {
        if ($fst >= $lst)    {return;}
        $mid = intval(($fst + $lst) / 2);
        $this->mergesortRic($a, $fst, $mid, $aux, $key);
        $this->mergesortRic($a, $mid +1, $lst, $aux, $key);
        $this->merge($a, $fst, $mid, $lst, $aux, $key);
    }

    private function merge(&$a, $fst, $mid, $lst, $aux, $key) {
        global $db;

        $i = $fst; $j = $mid +1; $k = $fst;
        while ($i <= $mid && $j <= $lst)    {
            $aux[$k++] = (call_user_func($this->criteri[$key], $a[$i][$key], $a[$j][$key]) <= 0) ? $a[$i++] : $a[$j++];
        }

        $h = $mid; $l = $lst;
        while ($h >= $i)    {
            $a[$l--] = $a[$h--];
        }

        for ($r = $fst; $r < $k; $r++) {
            $a[$r] = $aux[$r];
        }
    }

    private function setChange($items_sort)    {
        $ordine = 1;
        foreach ($items_sort as $item)  {
            $this->toChange[$item['id']] = array('ordine' => $ordine);
            $ordine++;
        }
    }
}
