<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 20/05/16
 * Time: 11:29
 */
$user = $this->getVar('user');
$current_id = $this->getVar('current_id');
$user_id = $user->getUserID();
$user_groups_id = array_keys($user->getUserGroups());

?>
<style>
    .contestual-tree {
        position: fixed;
        top: 0;
        left: -200%;
        display: block;
        height: 100vh;
        min-width: 447px;
        background-color: white;
        border-right: 1.5px solid #EDEDED;
        padding: 1% 3% 0 1%;
        transition: left 1s;
        z-index: 1;

        will-change: left;
        transition: left .25s ease-out;
    }
    .tree-container {
        margin-top: 75px;
        height: 100%;
        overflow-y: auto;
        display: none;
    }

    #archiuitree {
        height: 85%;
        overflow-y: auto;
        margin-bottom: 110px;
    }

    .tree-icons {
        position: fixed;
        background-color: white;
        width: 35px;
        height: 35px;
        border: 1.5px solid #EDEDED;
        border-radius: 50%;
        top: 50%;
        left: 15px;
        cursor: pointer;
    }

    .tree-icons .fa {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 1.5em;
        color: #06B86F;
    }

    .contestual-tree.open {
        left: 0!important;
        display: block;
    }

    .contestual-tree.open .tree-icons {
        position: absolute;
        left: 96%;
    }

    .contestual-tree.open .tree-container {
        display: block;
    }

    .jstree-proton .treenode-cont.jstree-clicked {
        background: #1EB771;
        color: #FFF;
    }

    .treenode-cont:hover .controls, .jstree-clicked .controls {
        display: none!important;
    }

    .treenode-cont .dnd, .jstree-clicked .dnd {
        display: none!important;
    }

    .col-md-9 {
        width: 83.33333333%;
        float: left;
        position: relative;
        min-height: 1px;
        padding-left: 15px;
        padding-right: 15px;
    }

    .tree-container span.label {
        display: inline-block;
        width: 95%;
    }

    .active .tree-icons {
        background: rgba(255, 255, 255, .75);
    }

    .contestual-tree.open-lg {
        left: 0!important;
        width: 93%;
    }

    .contestual-tree.open-lg .tree-icons {
        left: 98.5%!important;
    }

    .spinner.active {
        display: block;
        height: 35px;
        width: 35px;
        position: absolute;
        animation: rotate .5s infinite linear;
        border: 1px solid #06B86F;
        border-right-color: transparent;
        border-left-color: transparent;
        border-bottom-color: transparent;
        border-radius: 50%;
        top: auto;
    }

    @keyframes rotate {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }

</style>
<div class="contestual-tree">
    <div class="tree-container">
        <div id="archiuitree"></div>
    </div>
    <div class="tree-icons">
        <i class="fa fa-2x fa-pagelines"></i>
        <!--<div class="spinner"></div>-->
    </div>
</div>
<script>
    jQuery(document).ready(function () {
        var timeoutId = 0;

        // jQuery('.contestual-tree').css('left', '-' + (jQuery('.contestual-tree').outerWidth() - (jQuery('.contestual-tree').outerWidth() * 3 /100) - 10) + 'px');

        jQuery('.contestual-tree .tree-icons').mousedown(function() {
            jQuery( '.spinner' ).addClass('active');
            timeoutId = setTimeout(function () {
                jQuery( '.contestual-tree' ).toggleClass( "open open-lg" );
            }, 500);
        }).bind('mouseup mouseleave', function() {
            clearTimeout(timeoutId);
            jQuery( '.spinner' ).removeClass('active');
        });

        jQuery('.contestual-tree').on('click', '.tree-icons', function (e)   {
            jQuery( '.contestual-tree' ).toggleClass( "open" );
            if (jQuery( '.contestual-tree' ).hasClass('open-lg'))  {
                jQuery( '.contestual-tree' ).removeClass('open-lg');
            }
        });

        var archiuitree = jQuery('#archiuitree').jstree({
            "plugins" : [ "types", "dnd", "state" ],
            'core': {
                "check_callback" : true,
                "multiple": false,
                'themes': {
                    'name': 'proton',
                    'responsive': true
                },
                'data': {
                    "url": "<?php echo __CA_URL_ROOT__; ?>/app/widgets/promemoriaTreeObject/ajax/ajax.php?operation=get_children_contestuale",
                    "dataType": "json",
                    "method": "POST",
                    "data": function (n) {
                        return {
                            "id": n.id !== '#' ? n.id : 0,
                            "user_id": <?php print $user_id; ?>,
                            "user_groups": "<?php print implode(',', $user_groups_id); ?>",
                            "current_id": <?php print $current_id; ?>
                        }
                    }
                },
                "types" : {
                    "default" : {
                        "icon" : "fa fa-archive"
                    }
                },
            }
        });

        // var lastHeight = jQuery('.contestual-tree').outerWidth();
        // function checkForChanges() {
        //     if (jQuery('.contestual-tree').outerWidth() != lastHeight) {
        //         jQuery('.contestual-tree').css('left', '-' + (jQuery('.contestual-tree').outerWidth() - (jQuery('.contestual-tree').outerWidth() * 3 /100) - 10) + 'px');
        //         lastHeight = jQuery('.contestual-tree').outerWidth();
        //     }

        //     setTimeout(checkForChanges, 500);
        // }
        // checkForChanges();


        var draggerWidth = 10, // width of your dragger
            down = false,
            rangeWidth, rangeLeft;

        jQuery('#archiuitree').on('click', '.jstree-anchor', function ()    {
            location.href = "<?php echo __CA_URL_ROOT__; ?>/index.php/editor/objects/ObjectEditor/Edit/object_id/" + jQuery(this).parents('.jstree-node').attr('id');
        });
    });
</script>
