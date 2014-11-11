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
 * @package totara
 * @subpackage totara_question
 */

M.totara_question_ratingnumeric = M.totara_question_ratingnumeric || {
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
      throw new Error('M.totara_question_ratingnumeric.init()-> jQuery dependency required for this module to function.');
    }

    var sliderName = this.config.slider_field_name;
    var $container = $('#fitem_id_' + sliderName);
    var $inputs = $container.find('option');
    var inputsName = $inputs.parent().attr('name');
    var minVal = $inputs.eq(0).attr('value');
    var maxVal = $inputs.eq($inputs.length - 1).attr('value');
    var defaultVal = $container.find('option:selected').attr('value');
    var $sliderContainer = ('<div id="' + sliderName + '_container" class="question-rating-slider-container" />');
    $container.append($sliderContainer);

    Y.use('slider', function(Y){

      if (right_to_left()) {
        var slider = new Y.Slider({
          min : parseInt(maxVal),
          max : parseInt(minVal),
          length : '150px',
          value: parseInt(defaultVal)
        });
      } else {
        var slider = new Y.Slider({
          min : parseInt(minVal),
          max : parseInt(maxVal),
          length : '150px',
          value: parseInt(defaultVal)
        });
      }

      slider.on('thumbMove', function(e){
        $inputs.eq(slider.getValue() - parseInt(minVal)).prop('selected', true);
        $('#' + sliderName + '_currentVal').html(slider.getValue());
      });

      slider.render('#' + sliderName + '_container');
      $container.find('.felement').css('display', 'none');

      var sliderID = slider.get('id');
      var labelWidth = parseInt(slider.get('length')) + 40 + 'px';
      var $slider = $('#'+sliderID);

      if (right_to_left()) {
        $slider.css('float', 'right');
        $slider.before('<span id="' + sliderName + '_minVal" style="display:inline-block; margin-left:5px; width:20px; text-align:left; float:right;">' + minVal + '</span>');
        $slider.after('<span id="' + sliderName + '_currentVal" style="display:inline-block; width:' + labelWidth + '; text-align:center; float:right; clear:both;">' + (defaultVal || minVal) + '</span>');
        $slider.after('<span id="' + sliderName + '_maxVal" style="display:inline-block; margin-right:5px; width:20px; text-align:left; float:right;">' + maxVal + '</span>');
      } else {
        $slider.css('float', 'left');
        $slider.before('<span id="' + sliderName + '_minVal" style="display:inline-block; margin-right:5px; width:20px; text-align:right; float:left;">' + minVal + '</span>');
        $slider.after('<span id="' + sliderName + '_currentVal" style="display:inline-block; width:' + labelWidth + '; text-align:center; float:left; clear:both;">' + (defaultVal || minVal) + '</span>');
        $slider.after('<span id="' + sliderName + '_maxVal" style="display:inline-block; margin-left:5px; width:20px; text-align:left; float:left;">' + maxVal + '</span>');
      }
    });

  }
}
