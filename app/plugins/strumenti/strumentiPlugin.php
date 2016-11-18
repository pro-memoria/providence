<?php

/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 14/04/16
 * Time: 11:03
 */

require_once(__CA_LIB_DIR__.'/core/Db.php');

class strumentiPlugin extends BaseApplicationPlugin {

    public function __construct( $ps_plugin_path ) {
        $this->description = _t( 'Manage your archive' );
        parent::__construct();

        $this->opo_config = Configuration::load( $ps_plugin_path . '/conf/ordinatore.conf' );
    }
    
    static public function getRoleActionList() {
        return array();
    }

    public function checkStatus() {
        return array(
            'description' => $this->getDescription(),
            'errors'      => array(),
            'warnings'    => array(),
            'available'   => true
        );
    }

    /**
     * Il metodo va a creare una voce in un menu al quale associa un'azione del controller del plugin
     */
    public function hookRenderMenuBar( $pa_menu_bar ) {
        if ( $o_req = $this->getRequest() ) {

            //Controllo se è presente la voce principale al quale aggiungere la mia
            if ( isset( $pa_menu_bar['archiui'] ) ) {
                /*
                 * Se esiste ci vado ad aggiungere la mia voce.
                 * va aggiunta alla voce navigation del menu principale al quale lo si vuole inserire.
                 * il default è composto dai campi:
                 *      module: nome della cartella del plugin,
                 *      controller: il nome del controller (senza il suffisso 'Controller')
                 *      action: il metodo del controller da eseguire
                */
                $pa_menu_bar['archiui']['navigation']['strumenti'] = array(
                    'displayName'     => _t( 'Gestione archivio' ),
                    "default"         => array(
                        'module'     => 'strumenti',
                        'controller' => 'Strumenti',
                        'action'     => 'Index'
                    ),
                    "useActionInPath" => 1,
                    'require'         => array()
                );
            } else {
                //Se non esiste lo creo
                $pa_menu_bar['archiui'] = array(
                    'displayName' => _t( 'Strumenti' ),
                    "default"     => array(
                        'module'     => 'strumenti',
                        'controller' => 'Strumenti',
                        'action'     => 'Index'
                    ),
                    'require'     => array(),
                    'navigation'  => array(
                        'ordinatore_ordinatore' => array(
                            'displayName' => _t( 'Gestione archivio' ),
                            "default"     => array(
                                'module'     => 'strumenti',
                                'controller' => 'Strumenti',
                                'action'     => 'Index'
                            ),
                            'require'     => array()
                        )
                    )
                );
            }
        }

        return $pa_menu_bar;
    }
}