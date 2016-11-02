<?php
/**
 * Created by PhpStorm.
 * User: lucamontanera
 * Date: 09/05/16
 * Time: 08:37
 */

$menu = $this->getVar('template_ordinatore');
// $complete = $this->getVar('menu_ordinatore');
?>
<div id="modal-ordinatore" class="modal">
    <?php
    print caFormTag($this->request, 'Ordinatore', 'caOrdinatore', NULL, 'POST', 'multipart/form-data', '_self', array('disableUnsavedChangeWarning' => true, 'noTimestamp' => true));
    ?>
    <input class="Formobject_id" name="object" type="hidden" value="">
    <header>
        <span class="title">Ordinatore</span>
        <span class="twotype">
            Generico:
            <span class="option">
                <input type="checkbox" name="TwoType" data-criterio="data" value="data"> Data
                <input type="checkbox" name="TwoType" data-criterio="num" value="numero_definitivo"> Numero Definitivo
            </span>
        </span>
        <span class="close"><i class="fa fa-times"></i></span>
    </header>
    <article>
        <?php foreach ($menu as $key => $livels):
            if (empty($livels)) { continue; } ?>
            <div class="tabContent" id="<?php echo $key; ?>">

                <div class="tipologia3">
                    <span>Vuoi ordinare tutti gli elementi della categoria corrente indipendentemente dalla tipologia? <input
                            class="tipologia3Select" type="checkbox"></span>
                    <div class="option">
                        <input type="radio" name="<?php echo $key; ?>3Type" data-criterio="data" value="data"> Data
                        <input type="radio" name="<?php echo $key; ?>3Type" data-criterio="num" value="numero_definitivo"> Numero
                    </div>
                </div>
                <?php foreach ($livels as $livel_id => $livel): ?>
                    <div class="metadati" data-metadata-container= "<?php echo $livel['name']; ?>">
                        <div class="type_id">
                            <span class="title"><?php echo $livel['name']; ?></span>
                            <input type="checkbox" name="<?php echo $livel['name']; ?>" checked>
                        </div>
                        <div class="clear"></div>
                        <ul class="metadata-list">
                            <?php // foreach ($livel['metadati'] as $metadato):
                            $metadato = $livel['metadati']['preferred_labels'];
                            ?>
                            <li class="metadato">
							<span class="dnd">
								<i class="fa fa-bars"></i>
							</span>
							<span class="label">
								<?php echo $metadato['label']; ?>
							</span>
							<span class="controls">
								<input type="checkbox"
                                       name="<?php echo $livel['name']; ?>[<?php echo $metadato['element_code']; ?>]">
							</span>
                            </li>
                            <?php // endforeach; ?>
                        </ul>
                        <div class="addMetadati">
                            <select class="more-elem">
                                <option value="Aggiungi elemento" default>Aggiungi elemento</option>
                                <?php
                                $options = array();
                                foreach ($livel['metadati'] as $metadato) {
                                    if (!in_array($metadato['element_code'], array())) {
                                        if (isset($metadato['container'])) {
                                            if (isset($options[$metadato['container']])) {
                                                $options[$metadato['container']][] = array('element_code' => $metadato['element_code'], 'label' => $metadato['label']);
                                            } else {
                                                $options[$metadato['container']] = array(array('element_code' => $metadato['element_code'], 'label' => $metadato['label']));
                                            }
                                        } else {
                                            $options[$metadato['element_code']] = $metadato;
                                        }
                                    }
                                }
                                foreach ($options as $key => $option) {
                                    if (!isset($option['element_code'])) {
                                        ?>
                                        <optgroup label="<?php echo $key; ?>">
                                            <?php
                                            foreach ($option as $opt) {
                                                ?>
                                                <option
                                                    value="<?php echo $opt['element_code']; ?>"><?php echo $opt['label']; ?></option>
                                                <?php
                                            }
                                            ?>
                                        </optgroup>
                                    <?php } else { ?>
                                        <option
                                            value="<?php echo $option['element_code']; ?>"><?php echo $option['label']; ?></option>
                                    <?php }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
                <!--<div class="addType">
                    Aggiungi un tipo di oggetto
                    <div class="addTypeInput">
                        <select>
                            <option value="Aggiungi elemento" default>Aggiungi elemento</option>
                            <option value="preferred_labels">Titolo</option>
                            <option value="data_range">Data</option>
                            <optgroup label="Numerazione provvisoria">
                                <option value="temp_number">Numero provvisorio</option>
                                <option value="temp_number_bis">Numero provvisorio bis</option>
                            </optgroup>
                            <optgroup label="Numerezione definitiva">
                                <option value="final_number">Numero definitivo</option>
                                <option value="final_number_bis">Numero definitivo bis</option>
                                <option value="numero_romano">Numero romano</option>
                                <option value="value_prefix">Prefisso</option>
                            </optgroup>
                            <option value="segnatura_ordinamento">Segnatura</option>
                        </select>
                    </div>
                </div>-->

            </div>
        <?php endforeach; ?>
        <ul class="tabs">
            <?php foreach ($menu as $m => $v):
                if (empty($v))  { continue; }
                ?>
                <li data-content="<?php echo $m; ?>"><?php echo $m; ?></li>
            <?php endforeach; ?>
        </ul>

    </article>
    <footer>
        <?php
        print caJSButton($this->request, __CA_NAV_BUTTON_UPDATE__, _t('RIPRISTINA TEMPLATE'), 'caRipristina');
        print caJSButton($this->request, __CA_NAV_BUTTON_SAVE__, _t('SALVA CONFIGURAZIONE'), 'caSave');
        print caJSButton($this->request, __CA_NAV_BUTTON_GO__, _t('ORDINA'), 'caOrd');
        ?>
        <input type="hidden" name="function" value="">
    </footer>
    </form>
    <script>
        (function ($) {
            $('input[name="TwoType"]').attr('checked', false);

            $('#modal-ordinatore .form-button').click(function (e) {
                disableExitControl = true;
                $('input[name="function"]').val($(this).attr('id'));
                $('#caOrdinatore').submit();
            });

            var tabLinks = [];
            var contentDivs = [];

            // Grab the tab links and content divs from the page
            var tabListItems = document.getElementsByClassName('tabs')[0].childNodes;
            for (var i = 0; i < tabListItems.length; i++) {
                if (tabListItems[i].nodeName == "LI") {
                    var tabLink = tabListItems[i];
                    var id = tabLink.getAttribute('data-content');
                    tabLinks[id] = tabLink;
                    contentDivs[id] = document.getElementById(id);
                }
            }

            // Assign onclick events to the tab links, and
            // highlight the first tab
            var i = 0;

            for (var id in tabLinks) {
                tabLinks[id].onclick = showTab;
                tabLinks[id].onfocus = function () {
                    this.blur()
                };
                if (i == 0) tabLinks[id].className = 'selected';
                i++;
            }

            // Hide all content divs except the first
            var i = 0;

            for (var id in contentDivs) {
                if (i != 0) contentDivs[id].className = 'tabContent hide';
                i++;
            }

            function showTab() {
                var selectedId = this.getAttribute('data-content');

                // Highlight the selected tab, and dim all others.
                // Also show the selected content div, and hide all others.
                for (var id in contentDivs) {
                    if (id == selectedId) {
                        tabLinks[id].className = 'selected';
                        contentDivs[id].className = 'tabContent';
                    } else {
                        tabLinks[id].className = '';
                        contentDivs[id].className = 'tabContent hide';
                    }
                }

                // Stop the browser following the link
                return false;
            }

            $('.metadati .type_id input').change(function (e) {
                var name = $(this).attr('name');
                if (!$(this).attr("checked"))    {
                    $('[data-metadata-container="' + name + '"] .metadata-list input, [data-metadata-container="' + name + '"] .addMetadati select').attr('disabled', 'true');
                    $('[data-metadata-container="' + name + '"] .metadata-list, [data-metadata-container="' + name + '"] .addMetadati').css({
                        'pointer-events': 'none',
                        'opacity': '0.4'
                    });
                } else {
                    $('[data-metadata-container="' + name + '"] .metadata-list input, [data-metadata-container="' + name + '"] .addMetadati select').removeAttr('disabled');
                    $('[data-metadata-container="' + name + '"] .metadata-list, [data-metadata-container="' + name + '"] .addMetadati').css({
                        'pointer-events': 'auto',
                        'opacity': '1'
                    });
                }
            });

            $('.tipologia3Select').change(function (e) {
                var container = $(this).parents('.tipologia3');
                if (this.checked)    {
                    container.parent().find('.metadati').css({
                        'pointer-events': 'none',
                        'opacity': '0.4'
                    });
                    container.parent().find('.metadati:input').prop('disabled', true);
                    container.children('.option').show();
                } else {
                    container.parent().find('.metadati').css({
                        'pointer-events': 'auto',
                        'opacity': '1'
                    });
                    container.parent().find('.metadati:input').prop('disabled', false);
                    container.children('.option').hide();
                    $('.option input').prop('disabled', true);
                }
            });

            $('.twotype input[name="TwoType"]')
                .change(function (e) {
                    if (this.checked) {
                        $('#modal-ordinatore article').css({
                            'opacity': 0.4,
                            'pointer-events': 'none'
                        });
                    } else {
                        $('#modal-ordinatore article').css({
                            'opacity': 1,
                            'pointer-events': 'auto'
                        });
                    }
                })
                .click(function (e) {
                   var checked = $(this).data('criterio');
                    if (checked === 'data') {
                        $('.twotype input[data-criterio="num"]').attr('checked', false);
                    } else {
                        $('.twotype input[data-criterio="data"]').attr('checked', false);
                    }
                });

            $('.metadata-list').sortable({handle: ".dnd"});
            $('.metadata-list').disableSelection();

            function moreElement() {
                $('.more-elem').change(function () {
                    var val = $(this).val();
                    if (val === 'null') { return; }
                    var text = $(this).find("option[value='" + val + "']").text();
                    var string = '<li class="metadato"><span class="dnd"><i class="fa fa-bars"></i></span><span class="label"> ' + text + '</span><span class="controls"><input type="checkbox" checked name="' + $(this).parents('.metadati').attr('data-metadata-container') + '[' + val + ']"></span></li>';
                    $(this).parent().prev().append(string);
                    $(this).children('option[value="' + val + '"]').remove();
                });
            }

            moreElement();
        })(jQuery);
    </script>
</div>

