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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_reportbuilder
 */

M.reportbuilder = M.reportbuilder || {};
NS = M.reportbuilder.graphicalreport = M.reportbuilder.graphicalreport || {};

NS.init = function() {
    $('form').on('change', '#id_type', this.handletypechange);
    $('form').on('change', '#id_orientation', this.handleorientationchange);
    $('#id_type').change();
    $('#id_orientation').change();
    $('.fitem').css('overflow', 'hidden');
};

NS.handletypechange = function(e) {
    var selected = $(e.currentTarget).val();

    if (selected === 'pie') {
        $('#id_series').removeAttr('multiple');
    } else {
        $('#id_series').attr('multiple', 1);
    }

    if (selected === 'pie' || selected === 'scatter') {
        $('#fitem_id_stacked').hide();
        $('#fitem_id_orientation').show();
        $('#id_serieshdr').show();
        $('#id_advancedhdr').show();
    } else if (selected === '') {
        $('#fitem_id_orientation').hide();
        $('#id_serieshdr').hide();
        $('#id_advancedhdr').hide();
    } else {
        $('#fitem_id_stacked').show();
        $('#fitem_id_orientation').show();
        $('#id_serieshdr').show();
        $('#id_advancedhdr').show();
    }
    e.preventDefault();
};

NS.handleorientationchange = function(e) {
    var selected = $(e.currentTarget).val();

    if (selected === 'C') {
        $('#fitem_id_category').show();
        $('#fitem_id_legend').hide();
    } else {
        $('#fitem_id_category').hide();
        $('#fitem_id_legend').show();
    }
};
