<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 14/06/16
 * Time: 10:00
 */

function ord_string($a, $b) {
    return strcmp($a, $b);
}

function ord_number($a, $b) {
    $inta = floatval($a);
    $intb = floatval($b);
    if ($inta > $intb)  return 1;
    if ($inta < $intb) return -1;
    return 0;
}

function ord_romano($a, $b) {

    $romans = array(
        'M' => 1000,
        'CM' => 900,
        'D' => 500,
        'CD' => 400,
        'C' => 100,
        'XC' => 90,
        'L' => 50,
        'XL' => 40,
        'X' => 10,
        'IX' => 9,
        'V' => 5,
        'IV' => 4,
        'I' => 1,
    );

    $rA = 0;
    $rB = 0;
    foreach ($romans as $key => $value) {
        while (strpos($a, $key) === 0) {
            $rA += $value;
            $a = substr($a, strlen($key));
        }
    }

    foreach ($romans as $key => $value) {
        while (strpos($b, $key) === 0) {
            $rB += $value;
            $b = substr($b, strlen($key));
        }
    }

    if ($rA == $rB) return 0;
    else if ($rA == 0) return -1;
    else if ($rB == 0) return 1;

    return ord_number($rA, $rB);
}

function ord_list($a, $b) {
    require_once(__CA_MODELS_DIR__."/ca_list_items.php");

    $itemA = new ca_list_items($a);
    $itemB = new ca_list_items($b);

    return ord_number($itemA->get('rank'), $itemB->get('rank'));
}

function ord_data($a, $b) {
    require_once(__CA_LIB_DIR__."/core/Parsers/TimeExpressionParser.php");

    if ($a == null && $b == null) {
        return 0;
    }

    if ($a == null) {
        return -1;
    } else if ($b == null) {
        return 1;
    }

    $parser = new TimeExpressionParser();
    $dateA = $parser->parseDateTime($a);
    $dateB = $parser->parseDateTime($b);

    $dateA = $dateA['start'];
    $dateB = $dateB['start'];

    return ord_number($dateA, $dateB);
}
