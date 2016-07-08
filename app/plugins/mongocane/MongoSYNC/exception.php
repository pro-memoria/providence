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

global $exception_metadata, $exception_screen, $types_map;

$exception_screen = array(
    '35', // Accesso e utilizzo
    '41', // Admin e info
    '154', // Admin e info
    '135', // Accesso ai dati
    '139', // Accesso ai dati
);

$exception_metadata = array(
    'collocaz', // Collocazione
    'coll_sup_orig', // Collocazione del supporto originale
    'internal_notes', // note di servizio
    'archivistnote', // note dell'archivista
    'ldc', // Collocazione specifica (ldc)
);

$types_map = array(
    'subfondo' => 'Documenti',
    'fondo' => 'Documenti',
    'complesso di fondi' => 'Documenti',
    'superfondo' => 'Documenti',
    'unità archivistica' => 'Documenti',
    'unità documentaria' => 'Documenti',
    'livello' => 'Documenti',
    'bdm' => 'Oggetti',
    'bdm (aggregazione)' => 'Oggetti',
    'disegni' => 'Disegni',
    'disegni d' => 'Disegni',
    'unità bibliografica bib' => 'Unità bibliografiche',
    'fototipo f' => 'Foto',
    'fototipo (aggregazione)' => 'Foto',
    'documento fotografico (bdi)' => 'Foto',
    'documento fotografico integrativo (fi)' => 'Foto',
    'stampe s' => 'Stampe',
    'stampe (aggragazione)' => 'Stampe',
    'documeno audio (bdi)' => 'Audio',
    'documento audio integrativo (ai)' => 'Audio',
    'scheda video' => 'Video',
    'video (aggrafazione)' => 'Video',
    'documento video-cinematografico integrativo (vi)' => 'Video',
    'documento video-cinematografico (bdi)' => 'Video',
    'bdi (aggregazione)' =>'Insiemi di beni demoetnoantropologici immateriali'
);