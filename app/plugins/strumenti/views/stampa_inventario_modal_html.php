<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 21/04/16
 * Time: 11:13
 */
$stampa_inventario_types = $this->getVar('stampa_inventario_types');
$screen = $this->getVar('screen');
$select = '<option default value="">Nessuna</option>';
foreach ($screen as $id => $name)   {
    $select .= '<option value="'. $id .'">'.$name.'</option>';
}
$screen = null;

?>
<div id="modal-inventory" class="modal">
    <?php
        print caFormTag($this->request, 'Inventary', 'caInventary', NULL, 'POST', 'multipart/form-data', '_blank', array(
            'disableUnsavedChangeWarning' => true,
            'noTimestamp' => true
        ));
    ?>
    <input class="Formobject_id" name="object" type="hidden" value="">
    <header>
        <span class="title">Stampa Inventario</span>
        <span class="close"><i class="fa fa-times"></i></span>
    </header>
    <article>
        <h4>Seleziona la vista da stampare per ogni tipologia di oggetti</h4>
        <ul>
            <?php foreach ($stampa_inventario_types as $key => $value) { ?>
                <li>
                    <?php echo $value; ?><select name="<?php echo $value. "#" .$key;?>"><?php echo $select; ?></select>
                </li>
            <?php } ?>
        </ul>

    </article>
    <footer>
        <?php
            print caJSButton($this->request, __CA_NAV_BUTTON_SAVE__, _t('Stampa'), 'caInventaryButtom');
        ?>
    </footer>
    </form>
    <script>
        (function($)  {
            $('#caInventaryButtom').click(function(e)   {
                disableExitControl=true;
               $('#caInventary').submit();
            });

            /*$('#caInventary input[type="checkbox"]').change(function(e)  {
                if ($(this).attr("checked")) {
                    $(this).parent().find('select').removeAttr('disabled');
                    return;
                }
                $(this).parent().find('select').attr('disabled');
            });*/
        })(jQuery)
    </script>
</div>
