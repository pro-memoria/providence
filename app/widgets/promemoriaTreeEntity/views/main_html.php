<?php
/* ----------------------------------------------------------------------
 * app/widgets/promemoriaTreeEntity/views/main_html.php :
 * created by Promemoria snc (Turin - Italy) www.pro-memoria.it
 * version 2.0 - 16/02/2015
 * info@pro-memoria.it
 *This widget allow to view objects in a hierarchical structure
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2010 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */

$po_request     = $this->getVar( 'request' );
$vs_widgets_id  = $this->getVar( 'widgets_id' );
$field          = $this->getVar( 'field' );
$user           = $this->getVar( 'user' );
$user_id        = $user->getUserID();
$user_groups_id = array_keys($user->getUserGroups());
$administrator  = $po_request->user->canDoAction( "is_administrator" );

?>
<link type = "text/css" rel = "stylesheet"
      href = "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeEntity/resources/jquery.contextMenu.css" >
<link rel="stylesheet" href="<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeEntity/resources/themes/default/style.min.css" />

<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
<style type = "text/css" >
    #EntityWidget .jstree li a {white-space: normal; height: auto; vertical-align: middle; width: 95%;}
    #EntityWidget .search {text-align: center;margin: 2%;font-size: smaller;}
    #EntityWidget .showsummary 	{margin-left: 20px;}

    .result h4  {
        text-align: center;
    }

    .result table {
        margin: 5% auto;
        font-size: 1.4em;
    }

    .result table td {
        background-color: rgba(238, 238, 238, 0.6);
        padding-right: 0px!important;
    }

    .unit {
        text-align: start;
        padding: 10px;
        border-bottom: 1px solid rgba(204, 204, 204, 0.8);
    }

    .unit span.heading {
        text-align: left;
        font-weight: 200;
        text-decoration: underline;
    }

    .unit span.summaryData {
        margin-left: 15px;
    }

    .unit.notDefined {
        display: none;
    }

</style >
<div id="EntityWidget">
<script src = "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeEntity/resources/jstree.min.js" type = "text/javascript" ></script >
<div class = "dashboardwidgetsContentContainer" >
    <div class = "dashboardwidgetsScrollLarge" >
        <div class="search"><span class="fa fa-search"> </span> <input type="text" id="plugins4_entity" placeholder="Cerca" /></div>
        <div id = "promemoriaEntity" style = "clear:both;height:100%;overflow-y:auto;overflow-x:hidden" >
        </div >
    </div >
    <div id="vistaEntity" class="dialog vista" title="Vista rapida">
        <div id="idNode" style="display:none"></div>
        <div class="result"></div>
    </div>
</div >
<script >
    jQuery(function ($) {

        jQuery(document).tooltip();
        var promemoriaEntity = $("#promemoriaEntity");
        promemoriaEntity.jstree({
            "plugins": ["search", "state", "types", "wholerow"],
            "core" : {
                "animation" : 0,
                "check_callback" : true,
                "themes" : { "stripes" : false, "responsive": true },
                "data": {
                    "url": "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeEntity/ajax/ajax.php",
                    "dataType" : "json",
                    "data": function (n) {
                        return {
                            "operation": "get_children",
                            "id": n.id != '#' ? n.id : 0,
                            "order" : (n.attr && n.attr("order")) ? n.attr("order") : '',
                            "verso": (n.attr && n.attr("verso")) ? n.attr("verso") : '',
                            "user_id": <?php print $user_id; ?>,
                            "user_groups": "<?php print implode(",", $user_groups_id) ?>"
                        };
                    }
                }
            },
            "types": {
                "#" : {
                  "icon" : "fa fa-user",
                  "valid_children": ["default"]
                },
                "default" : {
                  "icon" : "fa fa-user",
                  "valid_children" : ["default","file"]
                },
                "file": {
                    "max_children": 0,
                    "icon": "fa fa-user",
                    "valid_children": []
                }
          }
        });
        promemoriaEntity.jstree(true).bind('ready.jstree load_node.jstree load_all.jstree redraw.jstree', function(event) {
        	$('#promemoriaEntity .showsummary').unbind().click(function (event)  {
                var id = $(this).parent().parent().attr('id');
                var title = $(this).parent().text();
                init(id, title);
            });
        });;

        $("#promemoriaEntity").on("dblclick", "a", function () {
            location.href = "<?php print __CA_URL_ROOT__; ?>/index.php/editor/entities/EntityEditor/Edit/entity_id/" + $(this).parent().attr("id");
        });

        var to = false;
        $('#plugins4_entity').keyup(function () {
            if(to) { clearTimeout(to); }
            to = setTimeout(function () {
                var v = $('#plugins4_entity').val();
                promemoriaEntity.jstree(true).search(v);
            }, 250);
      });

        function init(id, text) {
            $result = $('#vistaEntity').find('.result');

            $.get('<?php print __CA_URL_ROOT__; ?>/index.php/editor/entities/EntityEditor/Summary/entity_id/' + id, "html")
                .done(function(data) {
                    $result.html('<h3>' + text + '</h3>');
                    $result.append($(data).find('table')); 
                })
                .fail(function() {
                    $result.html('<span class="error">Errore caricamento Summary</span>');
                });

            $result.html('<i class="fa fa-spinner fa-3x fa-spin"></i>');
            dialogVista.dialog('open');
            keymove(id);
        }

      var dialogVista = $('#vistaEntity').dialog({
            autoOpen: false,
            minHeight: 570,
            width: "90%",
            modal: true,
            buttons: {
                "Apri": function ()    {
                    location.href = "<?php print __CA_URL_ROOT__; ?>/index.php/editor/entities/EntityEditor/Edit/entity_id/" + $(this).find('#idNode').text();
                }
            }
    	});

        function keymove(id)  {
            if (dialogVista.is(':visible'))  {
                $('#vistaEntity').unbind().keyup(function(e) {
                    var instance = $("#promemoriaEntity").jstree(true);
                    var node = instance.get_node(id.toString());

                    if (e.keyCode == 39 || e.keyCode == 40) {
                        node = instance.get_next_dom(node);
                        node = {id: node.attr('id'), text: node.text()};
                    } else if(e.keyCode == 37 || e.keyCode == 38)  {
                        node = instance.get_prev_dom(node);
                        node = {id: node.attr('id'), text: node.text()};
                    }

                    init(node.id, node.text);
                });
            }
        }
    });
</script >
</div>