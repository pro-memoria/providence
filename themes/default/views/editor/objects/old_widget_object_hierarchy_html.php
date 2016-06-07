<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 31/08/15
 * Time: 09:43
 */
?>
<link rel="stylesheet" href="http://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
<style type = "text/css" >
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
    #wpca_hierarchy li{
        list-style-type: none;  
        background-image: none;
        padding: 0px;
    }
    .toggler {
        margin-right: 4px;
        padding: 3px 3px 0 3px;
        border-radius: 3px;
        color: #656464;
    }

    .not-child .wpca-parent-container-head {
        padding: 5px;
    }

    ul.no-bullet {
        padding-left: 15px;
    }

    #root   {
        display: inline-block;
        font-size: x-large;
    }

    .child .wpca-parent-container-head {
        background-color: transparent;
        padding: 8px;
	font-size: smaller;
    }

    .fa-caret-right {
        font-size: large!important;
    }
    .unfold>.fa-caret-right:before {
        content: "\f0d7";
        color: #656464;
    }
    .open span a {
        color: #549eb9;
        font-weight: bold;
        text-decoration: none;
    }
    .closed span a {
        color: #656464;
        font-weight: normal;
        text-decoration: none;
    }
    #wpca_hierarchy {
        overflow-x: auto!important;
    }
    .current-item {
        background-color: #faff9b;
        padding: 5px;
    }
    .hierarchy-wrap {
        font-size: larger;
    }
    .wpca-parent-container span {
        cursor: pointer;
    }

    .wpca-parent-container-head {
        padding: 5px 0 5px 7px;
    }
    #action-bar {
        position: fixed;
        top: 25%;
        left: 0;
        background-color: #DDDDDD;
        padding: 15px;
        border-radius: 0px 10px 10px 0px;
    }

    #sync-drawer{
        margin-left: 5px;
        cursor:pointer;
        float: left;
    }
    .dashboardwidgetsScrollLarge    {
        margin:4em 1.5em;
        /*overflow-y: auto;*/
        height: 99vh;
        max-width: 470px;

    }
     
    #show-drawer{
        cursor:pointer;
    }

    .command-drawer {
        display: inline-block;
        margin: 8px 0px;
        width: 95%;
    }
    #hide-drawer    {
        cursor: pointer;
        float: right;
    }
</style >
<div id='wpca_hierarchy_container'>

<div id='action-bar'>
    <div id='show-drawer'><i class="fa fa-pagelines fa-2x"></i></div>
</div>

<div id='navigation-drawer'>
    <div class = "dashboardwidgetsScrollLarge" >
        <div class="command-drawer">
            <i id='hide-drawer' class="fa fa-arrow-circle-o-left fa-3x"></i>
            <i id='sync-drawer' class="fa fa-refresh fa-3x"></i>
        </div>
        <div id='wpca_hierarchy' style = "clear:both;height:100%;overflow-x:hidden">
            <h3 id="root"></h3>
            <div id='wpca_hierachy_items' class="hierarchy-wrap"></div>
            <script type="text/javascript">
                jQuery(document).ready(function(event) {


                    // show the navigation drawer
                    jQuery('#show-drawer').click(function(){
                    
                        jQuery('#navigation-drawer').show('slide', { direction: 'left' }, 300);
                         
                        jQuery(this).hide();
                        jQuery('#hide-drawer').show();

                        setTimeout(function() {
                            jQuery('#wpca_hierarchy').scrollTop(jQuery('.current-item').position().top -180);
                        }, 500);
                             
                    });
                     
                    // hide the navigation drawer
                    jQuery('#hide-drawer').click(function(){
                    
                        jQuery('#navigation-drawer').hide('slide', { direction: 'left' }, 300);
                         
                        jQuery(this).hide();
                        jQuery('#show-drawer').show();
                             
                    });

                    jQuery('#sync-drawer').click(function ()    {
                        var $this = jQuery(this);
                        jQuery.ajax({
                            url: '<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/ajax/ajaxMongo.php',
                            type: 'POST',
                            dataType: 'text',
                            data: {action: 'sync'},
                        })
                        .done(function ()   {
                            $this.removeClass("fa-spin");
                        });
                        $this.addClass("fa-spin");
                        
                    });



                    var current_id = window.location.pathname.split('/').pop().toString();

                    jQuery.ajax({
                        url: '<?php print __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/ajax/ajaxMongo.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {action: 'hierarchy'},
                    })
                    .done(function(json) {
                        albero = json;
                        albero.sort(function (a, b) {
                            if (parseInt(a.id) < parseInt(b.id))
                                return -1;
                            if (parseInt(a.id) > parseInt(b.id))
                                return 1;
                            return 0;
                        });

                        //jQuery('#wpca_hierarchy_container').remove();

                        // we are on root   (CSP excpetion)
                        if (current_id === null) {                
                            var root = null;
                            for ( var i = 0; i < albero.length; i++ ) {
                                if (albero[i].parent_id === null) {
                                    root = albero[i];
                                }
                            }

                            jQuery("#root").html(getname(root));
                            var html = "<ul class='no-bullet'>";
                            jQuery.each(root.children, function (index, val) {
                                html += "<li class='closed'>" + createNode(val, false) + "</div></li>";
                            });
                            html += "</ul>";
                            jQuery("#wpca_hierachy_items").append(html);
                            clickHierarchyItem();
                        } else {
                            genera(current_id);
                        }

                        jQuery('.inner-wrap').css('min-height', jQuery('#wpca_hierarchy_container').height() + 'px');
                    })
                    .fail(function(jqxhr, textStatus, error) {
                        var err = textStatus + ", " + error;
                        console.log( "Request Failed: " + err );
                    })
                    .always(function() {
                        jQuery('#show-drawer i').removeClass('fa-spin').addClass('fa-pagelines');
                    });
                    jQuery('#show-drawer i').removeClass('fa-pagelines').addClass('fa-refresh').addClass('fa-spin');
                });//Ready

                function clickHierarchyItem()   {

                    jQuery('.wpca-parent-container').on('click','.toggler.fold',function() {
                        var container_id = jQuery(this).parents('.wpca-parent-container').first().data("id");
                        var container_table = jQuery(this).parents('.wpca-parent-container').first().data("table");

                        apri(container_id);
                        jQuery('#wpca-parent-container_'+container_table+'_'+container_id).closest("li").removeClass("closed").addClass("open");
                        jQuery(this).removeClass("fold").addClass('unfold');

                        return false;
                    });

                    jQuery('.wpca-parent-container').on('click','.toggler.unfold',function() {
                        console.log(jQuery(this));
                        jQuery(this).parents('.wpca-parent-container').first().children('.no-bullet').remove();
                        jQuery(this).removeClass('unfold').addClass('fold');
                        jQuery(this).closest("li").removeClass("open").addClass("closed");

                        return false;
                    });
                    return false;
                }




                function apri(element)  {
                    var node = find(albero, element);
                    var html = "<ul class='no-bullet'>";
                    node.children.forEach(function(val) {

                        var nodeGenerated = createNode(val, false);
                        if(nodeGenerated !== null){
                            var clas =  (!val.isChild) ? "not-child" : "child";
                            html += "<li class='closed "+ clas +"'>" + nodeGenerated + "</div></li>";
                        }               

                    });
                    html += "</ul>";

                    jQuery("#wpca-parent-container_"+ node.table +"_" + element).append(html);
                }

                function genera(valore) {
                    // find the node related to the id
                    var node = find(albero, valore);
                    // we are generating from current id
                    if (node.parent_id === null)    {
                        jQuery("#root").html(getname(node));
                                        
                        var html = "<ul class='no-bullet'>";
                        jQuery.each(node.children, function (index, val) {
                            var nodeGenerated = createNode(val, false);
                            if(nodeGenerated !== null){
                                var clas =  (!val.isChild) ? "not-child" : "child";
                                html += "<li class='closed "+ clas +"'>" + nodeGenerated + "</div></li>";
                            }                       
                        });
                        html += "</ul>";
                        // we have the tree, let's paste it
                        jQuery("#wpca_hierachy_items").append(html);
                    } else  {
                        // we start form the anchestor root

                        var parents = node.parent_id.split("/");                
                        
                        // get the first element (root)
                        var root = find(albero, parents.splice(0,1));
                        if (root === null)  {
                            jQuery("#root").html("Impossibile caricare l'albero per questo elemento");
                            return;
                        } else  {
                            jQuery("#root").html(getname(root));
                        }                       
                    
                        var html = "<ul class='no-bullet'>";
                        html += stampa(root, parents) + "</ul>";

                        jQuery("#wpca_hierachy_items").html(html);
                        //jQuery("#wpca-parent-container_"+ root.table +"_" + valore).addClass('current');
                        jQuery("#wpca-parent-container_"+ root.table +"_" + valore + " .wpca-parent-title").addClass("current-item");
                    }
                    clickHierarchyItem();
                }

                function find(array, element_id)    {
                    var inf = 0;
                    var sup = albero.length -1;

                    if(sup == -1 || element_id < parseInt(array[0].id) || element_id > parseInt(array[sup].id)) return null;

                    while(inf <= sup) {
                        var i = Math.floor((inf + sup)/2);
                        if(element_id < parseInt(array[i].id)) sup = i-1;
                        else if(element_id > parseInt(array[i].id)) inf = i+1;
                        else return array[i];
                    }
                    return null;

                }

                function stampa(element, child) {
                    if (!element.isChild)   {
                        
                        var e = child.splice(0,1)[0];
                        var html = "";
                        jQuery.each(element.children, function(index, val)  {

                            //console.log(element);

                            if(("access" in val)){
                                //if (val.access == 1){
                                    var clas =  (!val.isChild) ? "not-child" : "child";
                                    if (val.id === e )  {
                                        html += "<li class='open "+ clas +"'>" + createNode(val, true) + "<ul class='no-bullet'>" + stampa(find(albero, e), child) + "</ul></div></li>";
                                    } else  {
                                        html += "<li class='closed "+ clas +"'>" + createNode(val, false) + "</div></li>";
                                    }
                                //}
                            } /*else {
                                 we don't do anything 
                                console.log("SKIP");
                            }   */
                        });
                        return html;
                    }
                    return "<li>" + createNode(element, false) + "</div></li>";
                }
                        

                function createNode(elem, open) {
                
                    var icon = "fa-caret-right";

                    if(("access" in elem))  {
                        //if (elem.access == 1){ 

                            if (elem.isChild)   {
                                icon = "fa-file";
                            }
                            //$table_singular = substr(rtrim($item["item"]["table"],'s'), 3 );
                            var a = "fold";
                            if (open)   {
                                a = "unfold";
                            }

                            var htmlicon = "<span class='toggler " + a +"'><span class='fa " + icon + "' ></span></span>";

                            var table_singular = elem.table.substring(3, elem.table.length -1);

                            var link = "<?php print __CA_URL_ROOT__; ?>/index.php/editor/objects/ObjectEditor/Edit/object_id/" + elem.id;
                            var item = "<div class='wpca-parent-container'  id='wpca-parent-container_"+ elem.table + "_" + elem.id + "'";
                            item += "data-id='" + elem.id + "'";
                            item += "data-table='" + elem.table + "' ><div class='wpca-parent-container-head'>";
                            item += htmlicon;
                            if (elem.isChild)   {
                                item += "<span class='wpca-parent-title'><a href='" + link + "'>" + getname(elem) + "</a></span>";
                            } else {
                                item += "<span class='wpca-parent-title'><a href='" + link + "'>" + getname(elem) + "</a></span>";
                                
                            }


                            return item;
                        //}
                    } else {
                        return null;
                    }
                }

                function getname(elem) {
                    var name = elem.name;
                    /*if (elem.data !== 'undefined') {
                        name += ", <i>" + elem.data + "</i>";
                    }*/
                    name += " (" + elem.type + ")";

                    return name;
                }               
            </script>
        </div>
    </div >
</div >
</div>
