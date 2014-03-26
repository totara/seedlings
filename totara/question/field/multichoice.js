/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark Webster <mark.webster@catalyst-eu.net>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

M.totara_question_multichoice = M.totara_question_multichoice || {
  Y : null,

  config: {},
  /**
   * module initialisation method called by php js_init_call()
   *
   * @param object    YUI instance
   * @param string    args supplied in JSON format
   */
  init : function(Y, args) {
    // save a reference to the Y instance (all of its dependencies included)
    this.Y = Y;

    if (args) {
      var jargs = Y.JSON.parse(args);
      for (var a in jargs) {
        if (Y.Object.owns(jargs, a)) {
          this.config[a] = jargs[a];
        }
      }
    }

    // check jQuery dependency is available
    if ( typeof $ === 'undefined') {
      throw new Error('M.totara_question_multichoice.init()-> jQuery dependency required for this module to function.');
    }

    var savedchoices = M.totara_question_multichoice.config.savedchoices;
    var numVisible = 0;
    var $container = $('#id_'+M.totara_question_multichoice.config.jsid);
    var max = M.totara_question_multichoice.config.max;

    var $allOptions = $container.find('div[id^="fgroup_id_choice_"]').slice(0, max);

    $allOptions.each(function(i){
        $(this).find("input[type='text']").each(function(j, e) {
            if ($(e).val() != '') {
                numVisible = i + 1;
                return false;
            }
        });
    });

    if (numVisible < 3) {
        numVisible = 3;
    }

    // Hide from numVisible (default is 3) to last
    $allOptions.slice(numVisible, max).addClass('js-hide');

    $allOptions.each(function(){
        var $this = $(this);
        if ($($this.find('span')).length > 0) {
            $this.find('span').addClass('js-hide');
            var $makeDefault = $('<span class="makedefaultlink">');
            var $makeDefaultLink = $('<a href="#">' + M.util.get_string('defaultmake', 'totara_question') + '</a>');
            $makeDefault.append($makeDefaultLink);
            $makeDefaultLink.on('click', function(){
                if (M.totara_question_multichoice.config.oneAnswer == 1) {
                    $allOptions.find('.unselectlink').each(function(){
                        if (!$(this).hasClass('js-hide')) {
                            $(this).find('a').click();
                        }
                    });
                }

                $this.find('input.makedefault').prop('checked', true);

                $makeDefault.addClass('js-hide');
                $unselect.removeClass('js-hide');
                return false;
            });

            var $unselect = $('<span class="unselectlink js-hide">' + M.util.get_string('defaultselected', 'totara_question') + ' </span>');
            var $unselectLink = $('<a href="#">' + M.util.get_string('defaultunselect', 'totara_question') + '</a>');
            $unselect.append($unselectLink);
            $unselectLink.on('click', function(){
                $this.find('input.makedefault').prop('checked', false);
                $makeDefault.removeClass('js-hide');
                $unselect.addClass('js-hide');
                return false;
            });

            $this.find('fieldset').append($makeDefault);
            $this.find('fieldset').append($unselect);

            if ($this.find('input.makedefault').prop('checked')) {
              $makeDefaultLink.click();
            }
        }

    });

    // Make visible #addoptionlink_$jsid
    $container.find('a.addoptionlink').addClass('js-show');
    $container.find('a.addoptionlink').on('click', function(){
        var $group = $container.find('.fcontainer .fitem_fgroup.js-hide').eq(0);

        if ($group.length) {
            $group.removeClass('js-hide');
            numVisible++;
        } else {
            $(this).removeClass('js-show');
        }

        if (numVisible == max) {
            $(this).removeClass('js-show');
        }

        return false;
    });

    // Saved choices savedchoices$jsid - is complicated one. It has array of option sets.
    // Each of them has number of options
    // Basically, when user choose some selection next actions should be performed
    // - All choices should be cleaned
    // - All choices should be disabled (if choosen option with key=0, all choices should be enabled)
    // - If number of preset choices bigger then shown choices, they should be unhidden
    // - Choices from array should be put to fields
    // - User still need to be able to choose default options
    // Thanks!

    function clearChoices() {
        $allOptions.find("input[type='text']").val('');
        $allOptions.find('.unselectlink').each(function(){
            if (!$(this).hasClass('js-hide')) {
                $(this).find('a').click();
            }
        });
    }

    $('#id_selectchoices').on('change', function(e){
        var theVal = $(this).val();
        if (theVal != 0) {
            if (e.originalEvent) {
                clearChoices();
                numVisible = savedchoices[theVal].values.length;
                for (value in savedchoices[theVal].values) {
                    $allOptions.eq(value).find("input[type='text']").eq(0).val(savedchoices[theVal].values[value].name);
                    $allOptions.eq(value).find("input[type='text']").eq(1).val(savedchoices[theVal].values[value].score);
                }
            }
            $allOptions.find("input[type='text']").prop('disabled', true);
        } else {
            $allOptions.find("input[type='text']").prop('disabled', false);
        }
        $container.find('.fcontainer .fitem_fgroup').removeClass('js-hide');
        $allOptions.slice(numVisible, max).addClass('js-hide');
    });

    // Trigger the change handler to check the select after loading.
    $('#id_selectchoices').trigger('change');

   }
}
