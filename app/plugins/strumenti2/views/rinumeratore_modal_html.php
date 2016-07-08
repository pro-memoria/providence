<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 23/05/16
 * Time: 12:14
 */
?>

<div id="modal-rinumera" class="modal">
    <?php
    print caFormTag($this->request, 'Rinumeratore', 'caRinumera', NULL, 'POST', 'multipart/form-data', '_self', array('disableUnsavedChangeWarning' => true, 'noTimestamp' => true));
    ?>
    <input class="Formobject_id" name="object" type="hidden" value="">
    <header>
        <span class="title">Rinumeratore</span>
        <span class="close"><i class="fa fa-times"></i></span>
    </header>
    <article>
        <section>
            <h3>Opzioni di rinumerazione</h3>
            <div class="input">Complessi Archivistici: <input type="radio" name="FONDI" value="cascata" checked> corda aperta</div>

            <div class="input">Serie: <input type="radio" name="LIVELLI" value="cascata" checked> corda aperta<input type="radio"
                                                                                                        name="LIVELLI"
                                                                                                        value="romano">
                numero romano
            </div>

            <div class="input">Unità archivistiche: <input type="radio" name="UNITA COMPLESSE" value="cascata" checked> corda aperta<input type="radio"
                                                                                                        name="UNITA COMPLESSE"
                                                                                                        value="chiusa">
                corda chiusa
            </div>

            <div class="input">Unità documentarie: <input type="radio" name="UNITA SEMPLICI" value="cascata" checked> corda aperta<input type="radio"
                                                                                                        name="UNITA SEMPLICI"
                                                                                                        value="cascata">
                corda chiusa
            </div>

        </section>
        <section class="prefisso" style="display: none;">
            <h3>Opzioni prefisso</h3>
            <div class="input"><input type="radio" name="prefisso" value="nessuno" checked> Nessun prefisso</div>

            <div class="input"><input type="radio" name="prefisso" value="fisso"> Prefisso generale fisso <input
                    type="text" name="fisso" placeholder="prefisso" disabled></div>

            <div class="input"><input type="radio" name="prefisso" value="gerarchico"> Prefisso dipendente dalla
                gerarchia
            </div>

            <div class="input"><input type="radio" name="prefisso" value="combinazione"> Combinazione prefissi <input
                    type="text" name="fisso" placeholder="prefisso" disabled></div>

        </section>
        <br>
        <div class="input">Inizia da <input type="number" name="start" value="1"></div>

    </article>
    <footer>
        <?php
        print caJSButton($this->request, __CA_NAV_BUTTON_SAVE__, _t('Rinumera'), 'caRinumeraButtom');
        ?>
    </footer>
    </form>
    <script>
        (function ($) {
            $('#modal-rinumera .form-button').click(function (e) {
                disableExitControl = true;
                $('input[name="function"]').val($(this).attr('id'));
                $('#caRinumera').submit();
            });

            $('input[name="prefisso"]').change(function() {
                var type = $(this).attr('value');
                $('.prefisso input[type="text"]').prop('disabled');
                if (type == 'combinazione' || type == 'fisso')  {
                    $('input[name="fisso"]').prop('disabled', true);
                    $(this).next().prop('disabled', false);
                } else {
                    $('input[name="fisso"]').prop('disabled', true);
                }
            })

        })(jQuery)
    </script>
</div>

