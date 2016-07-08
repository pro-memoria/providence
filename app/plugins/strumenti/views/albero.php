<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 14/04/16
 * Time: 11:17
 */

$path = $this->getVar( 'plugin_url' );
$object_edit_url = $path . '/index.php/editor/objects/ObjectEditor/Edit/object_id/';
$object_summary_url = $path . '/index.php/editor/objects/ObjectEditor/Summary/object_id/';
$ajax_path = $this->getVar( 'root' ) . 'strumenti/Strumenti';
$path .= '/app/plugins/strumenti/';
$resource_path = $path . "resources/dist";

$stampa_inventario_restriction = $this->getVar( 'stampa_inventario_restriction' );
$refinisci_restriction = $this->getVar( 'refinisci_restriction' );


?>
<link type = "text/css" rel = "stylesheet"
      href = "<?php print $path ?>/resources/custom.css" >
<link type = "text/css" rel = "stylesheet"
      href = "<?php print $path ?>/resources/dist/libs/bootstrap/css/bootstrap-grids.min.css" >
<script src="<?php print $resource_path; ?>/libs/bootstrap-notify.min.js" type="text/javascript"></script>

<div id="archiuitree"></div>


<script>
    var archiuitree = null;
    var disableExitControl = false;


    jQuery(function() {
        archiuitree = jQuery('#archiuitree').jstree({
            "plugins" : [ "types", "dnd", "state" ],
            'core': {
                "check_callback" : true,
                "multiple": true,
                'themes': {
                    'name': 'proton',
                    'responsive': true
                },
                'data': {
                    "url": "<?php print $ajax_path; ?>/AllNode",
                    "dataType": "json",
                    "method": "POST",
                    "data": function (n) {
                        return {"id": n.id !== '#' ? n.id : 0}
                    }
                },
                "types" : {
                    "default" : {
                        "icon" : "fa fa-archive"
                    }
                },
            }
        }).on('loaded.jstree', function(e) {
            archiuitree
                .on('click', '.jstree-container-ul > li > .treenode-cont .dnd', openNode);
            jQuery.get("<?php echo $ajax_path; ?>/EndLoad");
        }).on('hover_node.jstree', function(e, node) {
            if (typeof jQuery('#archiuitree #' + node.node.id + ' .controls .fa.fa-pencil').data('events') === 'undefined' || typeof jQuery('#archiuitree #' + node.node.id + ' .controls .fa.fa-pencil').data('events').click === 'undefined') {
                jQuery('#archiuitree #' + node.node.id + ' .controls .fa.fa-pencil').first().on('click', goToEdit);
                jQuery('#archiuitree #' + node.node.id + ' .controls .fa.fa-eye').first().on('click', goToSummary);
                jQuery('#archiuitree #' + node.node.id + ' .controls .cerchietto').first().on('click', selectNode);
            }
        }).on('select_node.jstree', function (e, data) {
            jQuery('#infoSelezionati .count').text(data.selected.length);
            jQuery('#infoSelezionati .select-list').html('');
            jQuery('#btnDeselezione').removeClass('disable');
            data.selected.map(function (node)   {
                jQuery('#infoSelezionati .select-list').append('<li data-id="' + node + '">' + archiuitree.jstree().get_node(node).text + '</li>');
            });
            manageAction(data);
        }).on('deselect_node.jstree', function (node, selected, e)   {
            jQuery('.select-list li[data-id="' + selected.node.id + '"]').remove();
            jQuery('#infoSelezionati .count').text(selected.selected.length);
            if (selected.selected.length == 0) {
                jQuery('#btnDeselezione').addClass('disable');
            }
            manageAction(selected);
        }).on('paste.jstree', function (e, data)    {
            var node = [];
            jQuery.each(data.node, function(i, item)   {
                node.push(item.id);
            });
            jQuery.ajax({
                url: "<?php echo $ajax_path; ?>/Paste",
                method: "POST",
                data: { 'node': node, 'parent': data.parent },
                dataType: "json"
            });
        }).on('move_node.jstree', function (e, data)    {
            var post = archiuitree.jstree().get_json();
            jQuery.ajax({
                type: 'POST',
                url: "<?php echo $ajax_path; ?>/Move",
                data: {"data": JSON.stringify(post)},
                dataType: "json"
            });
        });

        function openNode(e) {
            var node = jQuery(this).parents('.jstree-node');
            if (node.hasClass('jstree-open')) {
                archiuitree.jstree().close_node(node);
            } else {
                archiuitree.jstree().open_node(node);
            }
        }

        jQuery('#archiuitree ').on('click', '.controls .fa.fa-pencil', goToEdit);
        jQuery('#archiuitree').on('click', '.controls .fa.fa-eye', goToSummary);
        jQuery('#archiuitree').on('click', '.controls .cerchietto', selectNode);

        function goToEdit(e) {
            window.open('<?php print $object_edit_url; ?>' + jQuery(this).parents('.jstree-node').attr('id'), '_blank');
            e.stopPropagation();
        }

        function goToSummary(e) {
            window.open('<?php print $object_summary_url; ?>' + jQuery(this).parents('.jstree-node').attr('id'), '_blank');
            e.stopPropagation();
        }
        
        function selectNode(e) {
            if (jQuery(this).parents('.treenode-cont').hasClass('jstree-clicked'))  {
                archiuitree.jstree().deselect_node(jQuery(this).parents('.jstree-node').attr('id'));
            } else {
                archiuitree.jstree().select_node(jQuery(this).parents('.jstree-node').attr('id'));
            }
            e.stopPropagation();
        }

        function manageAction(node)    {
            if (node.selected.length >= 1)  {
                jQuery('#btnElimina, #btnTaglia').removeClass('disable');
            } else {
                jQuery('#btnElimina, #btnTaglia').addClass('disable');
            }

            if (node.selected.length === 1 && !archiuitree.jstree().is_leaf(node.node))   {
                jQuery('#btnOrdina').removeClass('disable');
                jQuery('#btnRinumera').removeClass('disable');
            } else if (node.selected.length !== 1 || archiuitree.jstree().is_leaf(node.node))   {
                jQuery('#btnOrdina').addClass('disable');
                jQuery('#btnRinumera').addClass('disable');
            }

            // Gestione dello stampa inventario
            if (node.selected.length === 1 && !archiuitree.jstree().is_leaf(node.node))   {
                var type = jQuery('#' + node.node.id).find('[data-type]').data('type');
                if ([<?php echo implode(',', $stampa_inventario_restriction); ?>].indexOf(type) >= 0)   {
                    jQuery('#btnInventario').removeClass('disable');
                } else {
                    jQuery('#btnInventario').addClass('disable');
                }

                if ([<?php echo implode(',', $refinisci_restriction); ?>].indexOf(type) >= 0)   {
                    jQuery('#btnRifinisci').removeClass('disable');
                } else {
                    jQuery('#btnRifinisci').addClass('disable');
                }
            } else {
                jQuery('#btnInventario, #btnRifinisci').addClass('disable');
            }
        }
    });
</script>

