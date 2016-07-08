<?php
$user          = $this->getVar( 'user' );
$user_id = $user->getUserID();
$user_groups_id = array_keys($user->getUserGroups());
$administrator = $user->canDoAction( "is_administrator" );

?>
<link type = "text/css" rel = "stylesheet"
      href = "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/resources/jquery.contextMenu.css" >
<link rel="stylesheet" href="<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/resources/themes/proton/style.min.css" />

<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
<style type = "text/css" >
    .jstree-node li a {white-space: normal; height: auto; vertical-align: middle; width: 95%;}
    .option {float: right;color: rgba(130, 130, 130, 1);cursor: pointer;}
    .option span    {margin: -10px;margin-right: 5px;}
    .remove {color: #ff0000 ;cursor: pointer;margin-right: 5%;}
    .dialog {font-size: 14px;text-align: center;}
    .list   {list-style: none;text-align: start;}
    .list li {padding: 2%;border-bottom: 1px grey solid;display: flex;}
    #ObjectWidget   {min-height: 740px;}
    .search {text-align: center;margin: 2%;font-size: smaller;}
    .jstree-node .jstree-anchor {line-height: 24px!important;white-space: normal!important;height: auto!important;display: initial;}
    .jstree-anchor span {
        display: inline-block;
        width: 80%;
    }
    .jstree-proton .jstree-search {
        font-style: initial;
        background-color: rgb(255, 255, 119);
        font-weight: bold;
        color: darkblue;
        display: inline-block;
        text-transform: uppercase;
    }
    .ui-dialog{z-index: 101;}
    .icon-color {color: #006E2B!important;}
    .jstree-icon.fa {color: #2D9F27;}
    .result h4  {
        text-align: center;
    }

    .jstree-proton .jstree-hovered {
        background: rgba(108, 222, 152, 0.4);
        color: #000;
        border-radius: 3px;
        box-shadow: inset 0 0 1px rgba(108, 222, 152, 0.4);
        padding: 0.7%;
    }

    .jstree-proton .jstree-clicked {
        background: rgba(108, 222, 152, 0.8);
        color: #000;
        border-radius: 3px;
        box-shadow: inset 0 0 1px rgba(108, 222, 152, 0.8);
        padding: 0.8%;
    }
    #navigation-drawer{
        border:2px solid #DDDDDD;
        padding:1em;
        position:fixed;
        top:0;
        left:0;
        background-color: white;
        height:99vh;
		width: 29%;
        display:none;
        z-index:999;
    }
    #action-bar {
        position: fixed;
        top: 25%;
        left: 0;
        background-color: #DDDDDD;
        padding: 15px;
        border-radius: 0px 10px 10px 0px;
    }
    .dashboardwidgetsScrollLarge    {
        margin: 0 1.5em;
        overflow-y: auto;
        height: 99vh;
        max-width: 470px;

    }
     
    #show-drawer{
        cursor:pointer;
    }

    .command-drawer {
        display: inline-block;
        margin: 50px 0 0 0;
        width: 95%;
    }
    #hide-drawer    {
        cursor: pointer;
        float: right;
    }
</style>
<script src = "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/resources/jstree.min.js" type = "text/javascript" ></script >

<div id='wpca_hierarchy_container'>

<div id='action-bar'>
    <div id='show-drawer'><i class="fa fa-pagelines fa-2x"></i></div>
</div>

<div id='navigation-drawer'>
    <div class="command-drawer">
        <i id='hide-drawer' class="fa fa-arrow-circle-o-left fa-3x"></i>
    </div>
	<div class = "dashboardwidgetsContentContainer" >
	    <div class = "dashboardwidgetsScrollLarge" >
	        <div class="search"><span class="fa fa-search"> </span> <input type="text" id="plugins4_q" placeholder="Cerca" /></div>
	        <div id = "promemoria" style = "clear:both;height:85%;overflow-y:auto;overflow-x:hidden" >
	        </div >
	    </div >
	</div>
<script >
    jQuery(function ($) {


    	// show the navigation drawer
    	jQuery('#show-drawer').click(function(){
    	
    	    jQuery('#navigation-drawer').show('slide', { direction: 'left' }, 300);
    	     
    	    jQuery(this).hide();
    	    jQuery('#hide-drawer').show();

    	    // setTimeout(function() {
    	    //     jQuery('.dashboardwidgetsScrollLarge').scrollTop(jQuery('.jstree-clicked').position().top -10);
    	    // }, 500);
    	         
    	});
    	 
    	// hide the navigation drawer
    	jQuery('#hide-drawer').click(function(){
    	
    	    jQuery('#navigation-drawer').hide('slide', { direction: 'left' }, 300);
    	     
    	    jQuery(this).hide();
    	    jQuery('#show-drawer').show();
    	         
    	});

    	var current_id = window.location.pathname.split('/').pop().toString();


        var promemoria = $("#promemoria");
        promemoria.jstree({
            "plugins": ["search", "state", "types"],
            "core" : {
                "animation" : 0,
                "check_callback" : true,
                "themes" : { 'name': 'proton', "stripes" : false, "responsive": true },
                "data": {
                    "url": "<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/ajax/ajax.php",
                    "dataType" : "json",
                    "data": function (n) {
                        return {
                            "operation": "get_children_contestuale",
                            "id": n.id != '#' ? n.id : 0,
                            "order" : (n.attr && n.attr("order")) ? n.attr("order") : '',
                            "verso": (n.attr && n.attr("verso")) ? n.attr("verso") : '',
                            "user_id": <?php print $user_id; ?>,
                            "user_groups": "<?php print implode(",", $user_groups_id) ?>"
                        };
                    }
                }
            }
        })
        
        $("#promemoria").on("click", "a", function () {
            location.href = "<?php print __CA_URL_ROOT__; ?>/index.php/editor/objects/ObjectEditor/Edit/object_id/" + $(this).parent().attr("id");
        });

        var to = false;
        $('#plugins4_q').keyup(function () {
            if(to) { clearTimeout(to); }
                to = setTimeout(function () {
                var v = $('#plugins4_q').val();
                promemoria.jstree(true).search(v);
            }, 250);
      });
    });
</script >
</div>
