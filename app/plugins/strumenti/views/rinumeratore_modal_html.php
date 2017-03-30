<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 23/05/16
 * Time: 12:14
 */

$menu = $this->getVar('menu_rinumeratore');
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
            <select name="type">
                <?php foreach ($menu as $key => $value) {?>
                    <option value="<?php echo $key; ?>"><?php echo $value['label']; ?></option>
                <?php } ?>
            </select>
            <?php foreach ($menu as $key => $value) {?>
                <div id="<?php echo $key; ?>" class="rinum-options" style="display: none">
                    <div class="input">
                        <?php if (isset($value['options']['aperta'])) { ?>
                            <input type="radio" name="<?php echo $value['label']; ?>" value="cascata" checked disabled> corda aperta
                        <?php }?>
                        <?php if (isset($value['options']['chiusa'])) { ?>
                            <input type="radio" name="<?php echo $value['label']; ?>" value="chiusa" disabled> corda chiusa
                        <?php }?>
                    </div>
                    <?php if (isset($value['romano'])) { ?>
                        <div class="input"><input type="checkbox" name="romano" value="romano" disabled> numero romano</div>
                    <?php }?>
                </div>
            <?php } ?>

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
            $('#<?php echo reset(array_keys($menu)); ?>').show();
            $("#<?php echo reset(array_keys($menu)); ?> .input input").prop('disabled', false);
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
            });

            $('select[name="type"]').change(function () {
                $(".rinum-options").hide();
                $(".rinum-options .input input").prop('disabled', true);
                $('#' + $(this).attr('value')).show();
                $('#' + $(this).attr('value') + " .input input").prop('disabled', false);
            });

        })(jQuery)
    </script>
</div>

