<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 14/04/16
 * Time: 12:10
 */

$ajax_path = $this->getVar( 'root' ) . 'strumenti/Strumenti';
?>
<script>
(function($)  {

    window.onbeforeunload = function(){
        if (!disableExitControl) {
            return 'You sure you want to leave?';
        }
    };
    window.onunload = function() {
        jQuery.get("<?php echo $ajax_path ?>/Leave");
    }

    $('#leftNavSidebar').html(`
        <div id="infoSelezionati">
        <div class="text">
        <i class="fa fa-angle-down" aria-hidden="true"></i>
        <span class="count">0</span> livelli selezionati
        </div>
        <ol class="select-list"></ol>
        </div>
        <hr />
        <h1>Azioni</h1>
        <div id="btnDeselezione" class="action disable" data-action="deseleziona">
            <i class="fa fa-times-circle-o" aria-hidden="true"></i> <span>Deseleziona tutto</span>
        </div>
        <div id="btnTaglia" class="action disable" data-action="cut">
            <i class="fa fa-scissors" aria-hidden="true"></i> <span>Taglia</span>
        </div>
        <div id="btnIncolla" class="action disable" data-action="paste">
            <i class="fa fa-clipboard" aria-hidden="true"></i> <span>Incolla</span>
        </div>
        <div id="btnElimina" class="action disable" data-action="elim">
            <i class="fa fa-trash" aria-hidden="true"></i> <span>Elimina</span>
        </div>
        <br>
        <div id="btnOrdina" class="action disable" data-action="ord">
            <i class="fa fa-sort" aria-hidden="true"></i> <span>Ordina</span>
        </div>
        <!-- <div id="btnRinumera" class="action disable" data-action="renum">
            <i class="fa fa-sort-numeric-desc" aria-hidden="true"></i> <span>Rinumera</span>
        </div> -->
        <div id="btnInventario" class="action disable" data-action="print">
            <i class="fa fa-print" aria-hidden="true"></i> <span>Stampa inventario</span>
        </div>
        <div id="btnRifinisci" class="action disable" data-action="rifin">
            <i class="fa fa-calculator" aria-hidden="true"></i> <span>Rifinisci fondo</span>
        </div>
        <br>
        <div id="btnAnnulla" class="action" data-action="cancel">
             <i class="fa fa-undo" aria-hidden="true"></i> <span>Annulla</span>
        </div>
        <div id="btnSalva" class="action" data-action="save">
            <i class="fa fa-check-circle-o" aria-hidden="true"></i> <span>Salva</span>
        </div>
    `);

    $('#infoSelezionati .text').click(function(e) {
        $('.select-list').slideToggle();
    });

    $('#btnDeselezione').click(function(e)   {
        archiuitree.jstree().deselect_all();
        // archiuitree.jstree().clear_buffer();
        $('#infoSelezionati .count').text(0);
        $('#infoSelezionati .select-list').html('');
        $('.action:not("#btnSalva, #btnAnnulla")').addClass('disable');
        $(this).addClass('disable');
        $('.cutted').removeClass('cutted');
        e.stopPropagation();
    });

    $('#btnIncolla').click(function (e) {
        if (jQuery(this).hasClass('disable'))
            return;

        if (archiuitree.jstree().can_paste())   {
            var selected = archiuitree.jstree().get_selected();
            archiuitree.jstree().paste(selected[selected.length -1], "last");
        }

        jQuery(this).addClass('disable');
    });

    $('#btnTaglia').click(function (e) {
        if (jQuery(this).hasClass('disable'))
            return;

        var selected = archiuitree.jstree().get_selected();
        archiuitree.jstree().cut(selected)
        archiuitree.jstree().deselect_all();
        jQuery('#btnIncolla').removeClass('disable');
        selected.map(function (id)  {
            jQuery('#archiuitree #' + id + ' .treenode-cont').addClass('cutted');
        })
    });

    $('#btnElimina').click(function (e) {
        if (jQuery(this).hasClass('disable'))
            return;

        var selected = archiuitree.jstree().get_selected();
        jQuery.post("<?php echo $ajax_path; ?>/Elim", {id: selected}, function( data ) {
            selected.map(function(id) {archiuitree.jstree().delete_node(id)});
        });
        jQuery('#infoSelezionati .count').text(0);
        jQuery('#infoSelezionati .select-list').html('');
        jQuery('.action:not("#btnSalva, #btnAnnulla")').addClass('disable');
        jQuery(this).addClass('disable');
    });
    
    $('#btnInventario').click(function (e)  {
        $('#modal-inventory').show();
        $('.modal-backdrop').show();

        $('#modal-inventory .Formobject_id').val(archiuitree.jstree().get_selected());
    });

    $('#btnOrdina').click(function (e)  {
        $('#modal-ordinatore').show();
        $('.modal-backdrop').show();

        $('#modal-ordinatore .Formobject_id').val(archiuitree.jstree().get_selected());
    });

    // $('#btnRinumera').click(function (e)    {
    //     $('#modal-rinumera').show();
    //     $('.modal-backdrop').show();

    //     $('#modal-rinumera .Formobject_id').val(archiuitree.jstree().get_selected());
    // });

    $('#btnRifinisci').click(function (e) {
        var selected = archiuitree.jstree().get_selected();
        $.post("<?php echo $ajax_path; ?>/Rifinisci", {id: selected}, function( message ) {
            jQuery.notify({
                    icon: "fa fa-calculator",
                    message: message
                },
                {
                    type: 'pastel-warning',
                    delay: 1500,
                    allow_dismiss: false,
                    placement: {
                        from: "bottom",
                        align: "left"
                    },
                    offset: 60,
                    template: '<div data-notify="container" class="col-xs-11 col-sm-3 alert alert-{0}" role="alert">' +
                    '<button type="button" aria-hidden="true" class="close" data-notify="dismiss">×</button>' +
                    '<span data-notify="icon"></span> <div class="noty-cont">' +
                    '<span data-notify="title">{1}</span>' +
                    '<span data-notify="message">{2}</span>' +
                    '</div></div>'
            });
        });
    });
    
    $('#btnSalva').click(function (e) {
        if (confirm("Le modifiche saranno permanenti. Continuare?"))  {
            $.get("<?php echo $ajax_path; ?>/Save").done(function () {
                jQuery.notify({
                        icon: "fa fa-floppy-o",
                        message: "Modifiche salvate"
                    },
                    {
                        type: 'pastel-warning',
                        delay: 1500,
                        allow_dismiss: false,
                        placement: {
                            from: "bottom",
                            align: "left"
                        },
                        offset: 60,
                        template: '<div data-notify="container" class="col-xs-11 col-sm-3 alert alert-{0}" role="alert">' +
                        '<button type="button" aria-hidden="true" class="close" data-notify="dismiss">×</button>' +
                        '<span data-notify="icon"></span> <div class="noty-cont">' +
                        '<span data-notify="title">{1}</span>' +
                        '<span data-notify="message">{2}</span>' +
                        '</div></div>'
                });

            });
        }
    });

    $('#btnAnnulla').click(function (e) {
        if (confirm("Le modifiche andranno perse, continuare?"))  {
            $.get("<?php echo $ajax_path; ?>/Undo").done(function () {
                archiuitree.jstree().deselect_all();
                archiuitree.jstree().refresh();
                $('#infoSelezionati .count').text(0);
                $('#infoSelezionati .select-list').html('');
                $('.action:not("#btnSalva, #btnAnnulla")').addClass('disable');
                $(this).addClass('disable');
                $('.cutted').removeClass('cutted');
                jQuery.notify({
                        icon: "fa fa-undo",
                        message: "Modifiche annullate"
                    },
                    {
                        type: 'pastel-warning',
                        delay: 1500,
                        allow_dismiss: false,
                        placement: {
                            from: "bottom",
                            align: "left"
                        },
                        offset: 60,
                        template: '<div data-notify="container" class="col-xs-11 col-sm-3 alert alert-{0}" role="alert">' +
                        '<button type="button" aria-hidden="true" class="close" data-notify="dismiss">×</button>' +
                        '<span data-notify="icon"></span> <div class="noty-cont">' +
                        '<span data-notify="title">{1}</span>' +
                        '<span data-notify="message">{2}</span>' +
                        '</div></div>'
                });

            });
        }
    });


    $('.modal').on('click', '.close', function (e)   {
        $(this).parents('.modal').hide();
        $('.modal-backdrop').hide();
    });



})(jQuery);
</script>