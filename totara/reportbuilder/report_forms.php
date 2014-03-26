<?php
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Moodle Formslib templates for report builder settings forms
 */

require_once "$CFG->dirroot/lib/formslib.php";
include_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_content.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * Formslib template for the new report form
 */
class report_builder_new_form extends moodleform {

    function definition() {

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('newreport', 'totara_reportbuilder'));
        $sources = reportbuilder::get_source_list();
        if (count($sources) > 0) {

            $mform->addElement('text', 'fullname', get_string('reportname', 'totara_reportbuilder'), 'maxlength="255"');
            $mform->setType('fullname', PARAM_TEXT);
            $mform->addRule('fullname', null, 'required');
            $mform->addHelpButton('fullname', 'reportbuilderfullname', 'totara_reportbuilder');

            $pick = array(0 => get_string('selectsource', 'totara_reportbuilder'));
            $select = array_merge($pick, $sources);
            $mform->addElement('select', 'source', get_string('source', 'totara_reportbuilder'), $select);
            // invalid if not set
            $mform->addRule('source', get_string('error:mustselectsource', 'totara_reportbuilder'), 'regex', '/[^0]+/');
            $mform->addHelpButton('source', 'reportbuildersource', 'totara_reportbuilder');

            $mform->addElement('advcheckbox', 'hidden', get_string('hidden', 'totara_reportbuilder'), '', null, array(0, 1));
            $mform->addHelpButton('hidden', 'reportbuilderhidden', 'totara_reportbuilder');
            $this->add_action_buttons(true, get_string('createreport', 'totara_reportbuilder'));

        } else {
            $mform->addElement('html', get_string('error:nosources', 'totara_reportbuilder'));
        }
    }

}


/**
 * Formslib tempalte for the edit report form
 */
class report_builder_edit_form extends moodleform {
    function definition() {
        global $TEXTAREA_OPTIONS;

        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];

        $mform->addElement('header', 'general', get_string('reportsettings', 'totara_reportbuilder'));

        $mform->addElement('text', 'fullname', get_string('reporttitle', 'totara_reportbuilder'), array('size' => '30'));
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', null, 'required');
        $mform->addHelpButton('fullname', 'reportbuilderfullname', 'totara_reportbuilder');

        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);
        $mform->addHelpButton('description_editor', 'reportbuilderdescription', 'totara_reportbuilder');

        $mform->addElement('static', 'reportsource', get_string('source', 'totara_reportbuilder'), $report->src->sourcetitle);
        $mform->addHelpButton('reportsource', 'reportbuildersource', 'totara_reportbuilder');

        $mform->addElement('advcheckbox', 'hidden', get_string('hidden', 'totara_reportbuilder'), '', null, array(0, 1));
        $mform->setType('hidden', PARAM_INT);
        $mform->addHelpButton('hidden', 'reportbuilderhidden', 'totara_reportbuilder');


        $mform->addElement('text', 'recordsperpage', get_string('recordsperpage', 'totara_reportbuilder'), array('size' => '6', 'maxlength' => 4));
        $mform->setType('recordsperpage', PARAM_INT);
        $mform->addRule('recordsperpage', null, 'numeric');
        $mform->addHelpButton('recordsperpage', 'reportbuilderrecordsperpage', 'totara_reportbuilder');

        $reporttype = ($report->embeddedurl === null) ? get_string('usergenerated', 'totara_reportbuilder') :
            get_string('embedded', 'totara_reportbuilder');

        $mform->addElement('static', 'reporttype', get_string('reporttype', 'totara_reportbuilder'), $reporttype);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source', $report->source);
        $mform->setType('source', PARAM_TEXT);
        $this->add_action_buttons();

        // set the defaults
        $this->set_data($report);
    }
}

/**
 * Formslib template for edit filters form
 */
class report_builder_edit_filters_form extends moodleform {
    function definition() {
        global $OUTPUT;

        // Common.
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];
        $allstandardfilters = $this->_customdata['allstandardfilters'];
        $unusedstandardfilters = $this->_customdata['unusedstandardfilters'];
        $allsidebarfilters = $this->_customdata['allsidebarfilters'];
        $unusedsidebarfilters = $this->_customdata['unusedsidebarfilters'];
        $allsearchcolumns = $this->_customdata['allsearchcolumns'];
        $unusedsearchcolumns = $this->_customdata['unusedsearchcolumns'];
        $filters = array();

        $strmovedown = get_string('movedown', 'totara_reportbuilder');
        $strmoveup = get_string('moveup', 'totara_reportbuilder');
        $strdelete = get_string('delete', 'totara_reportbuilder');
        $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));

        $renderer =& $mform->defaultRenderer();

        $selectelementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'fselectgroups'), 'fitem');
        $checkelementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'fcheckbox'), 'fitem');
        $textelementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'ftext'), 'fitem');

        // Standard and sidebar filters.
        $mform->addElement('header', 'standardfilter', get_string('standardfilter', 'totara_reportbuilder'));
        $mform->addHelpButton('standardfilter', 'standardfilter', 'totara_reportbuilder');
        $mform->setExpanded('standardfilter');

        if (isset($report->filteroptions) && is_array($report->filteroptions) && count($report->filteroptions) > 0) {
            $filters = $report->filters;

            $standardfiltercount = 0;
            $sidebarfiltercount = 0;
            foreach ($filters as $filter) {
                if ($filter->region == rb_filter_type::RB_FILTER_REGION_STANDARD) {
                    $standardfiltercount++;
                } else if ($filter->region == rb_filter_type::RB_FILTER_REGION_SIDEBAR) {
                    $sidebarfiltercount++;
                }
            }

            // Standard filter options.
            $mform->addElement('html', $OUTPUT->container(get_string('standardfilterdesc', 'totara_reportbuilder')) .
                    html_writer::empty_tag('br'));

            $mform->addElement('html', $OUTPUT->container_start('reportbuilderform') . html_writer::start_tag('table') .
                    html_writer::start_tag('tr') . html_writer::tag('th', get_string('searchfield', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('customisename', 'totara_reportbuilder'), array('colspan' => 2)) .
                    html_writer::tag('th', get_string('advanced', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('options', 'totara_reportbuilder')) . html_writer::end_tag('tr'));

            if (isset($report->filters) && is_array($report->filters) && count($report->filters) > 0) {
                $i = 1;
                foreach ($filters as $index => $filter) {
                    if ($filter->region != rb_filter_type::RB_FILTER_REGION_STANDARD) {
                        continue;
                    }
                    $row = array();
                    $filterid = $filter->filterid;
                    $type = $filter->type;
                    $value = $filter->value;
                    $field = "{$type}-{$value}";
                    $advanced = $filter->advanced;

                    $mform->addElement('html', html_writer::start_tag('tr', array('fid' => $filterid)) .
                            html_writer::start_tag('td'));
                    $mform->addElement('selectgroups', "filter{$filterid}", '', $allstandardfilters,
                            array('class' => 'filter_selector'));
                    $mform->setDefault("filter{$filterid}", $field);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('advcheckbox', "customname{$filterid}", '', '',
                            array('class' => 'filter_custom_name_checkbox', 'group' => 0), array(0, 1));
                    $mform->setDefault("customname{$filterid}", $filter->customname);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('text', "filtername{$filterid}", '', 'class="filter_name_text"');
                    $mform->setType("filtername{$filterid}", PARAM_TEXT);
                    $mform->setDefault("filtername{$filterid}", (empty($filter->filtername) ? $filter->label : $filter->filtername));
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('advcheckbox', "advanced{$filterid}", '', '', array('class' => 'filter_advanced_checkbox'));
                    $mform->setDefault("advanced{$filterid}", $filter->advanced);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $deleteurl = new moodle_url('/totara/reportbuilder/filters.php',
                        array('d' => '1', 'id' => $id, 'fid' => $filterid));
                    $mform->addElement('html', html_writer::link(
                        $deleteurl,
                        $OUTPUT->pix_icon('t/delete', $strdelete, null, array('iconsmall')),
                        array('title' => $strdelete, 'class' => 'deletefilterbtn action-icon')
                    ));
                    if ($i != 1) {
                        $moveupurl = new moodle_url('/totara/reportbuilder/filters.php',
                            array('m' => 'up', 'id' => $id, 'fid' => $filterid));
                        $mform->addElement('html', html_writer::link(
                            $moveupurl,
                            $OUTPUT->pix_icon('t/up', $strmoveup, null, array('class' => 'iconsmall')),
                            array('title' => $strmoveup, 'class' => 'movefilterupbtn action-icon')
                        ));
                    } else {
                        $mform->addElement('html', $spacer);
                    }
                    if ($i != $standardfiltercount) {
                        $movedownurl = new moodle_url('/totara/reportbuilder/filters.php',
                            array('m' => 'down', 'id' => $id, 'fid' => $filterid));
                        $mform->addElement('html', html_writer::link(
                            $movedownurl,
                            $OUTPUT->pix_icon('t/down', $strmovedown, null, array('class' => 'iconsmall')),
                            array('title' => $strmovedown, 'class' => 'movefilterdownbtn action-icon')
                        ));
                    } else {
                        $mform->addElement('html', $spacer);
                    }
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
                    $i++;
                }
            } else {
                $mform->addElement('html', html_writer::tag('p', get_string('nofiltersyet', 'totara_reportbuilder')));
            }

            $mform->addElement('html', html_writer::start_tag('tr') . html_writer::start_tag('td'));
            $mform->addElement('selectgroups', 'newstandardfilter', '', $unusedstandardfilters,
                    array('class' => 'new_standard_filter_selector filter_selector'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('advcheckbox', 'newstandardcustomname', '', '',
                    array('id' => 'id_newstandardcustomname', 'class' => 'filter_custom_name_checkbox', 'group' => 0),
                    array(0, 1));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->setDefault('newstandardcustomname', 0);
            $mform->addElement('text', 'newstandardfiltername', '', 'class="filter_name_text"');
            $mform->setType('newstandardfiltername', PARAM_TEXT);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('advcheckbox', 'newstandardadvanced', '', '', array('class' => 'filter_advanced_checkbox'));
            $mform->disabledIf('newstandardadvanced', 'newstandardfilter', 'eq', 0);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
            $mform->addElement('html', html_writer::end_tag('table') . $OUTPUT->container_end());

            // Sidebar filter options.
            $mform->addElement('header', 'sidebarfilter', get_string('sidebarfilter', 'totara_reportbuilder'));
            $mform->addHelpButton('sidebarfilter', 'sidebarfilter', 'totara_reportbuilder');
            $mform->setExpanded('sidebarfilter');

            $mform->addElement('html', $OUTPUT->container(get_string('sidebarfilterdesc', 'totara_reportbuilder')) .
                    html_writer::empty_tag('br'));
            $mform->addElement('selectgroups', "all_sidebar_filters", '', $allsidebarfilters);

            $mform->addElement('html', $OUTPUT->container_start('reportbuilderform') . html_writer::start_tag('table') .
                    html_writer::start_tag('tr') . html_writer::tag('th', get_string('searchfield', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('customisename', 'totara_reportbuilder'), array('colspan' => 2)) .
                    html_writer::tag('th', get_string('advanced', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('options', 'totara_reportbuilder')) . html_writer::end_tag('tr'));

            if (isset($report->filters) && is_array($report->filters) && count($report->filters) > 0) {
                $i = 1;
                foreach ($filters as $index => $filter) {
                    if ($filter->region != rb_filter_type::RB_FILTER_REGION_SIDEBAR) {
                        continue;
                    }
                    $row = array();
                    $filterid = $filter->filterid;
                    $type = $filter->type;
                    $value = $filter->value;
                    $field = "{$type}-{$value}";
                    $advanced = $filter->advanced;

                    $mform->addElement('html', html_writer::start_tag('tr', array('fid' => $filterid)) .
                            html_writer::start_tag('td'));
                    $mform->addElement('selectgroups', "filter{$filterid}", '', $allsidebarfilters,
                            array('class' => 'filter_selector'));
                    $mform->setDefault("filter{$filterid}", $field);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('advcheckbox', "customname{$filterid}", '', '',
                            array('class' => 'filter_custom_name_checkbox', 'group' => 0), array(0, 1));
                    $mform->setDefault("customname{$filterid}", $filter->customname);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('text', "filtername{$filterid}", '', 'class="filter_name_text"');
                    $mform->setType("filtername{$filterid}", PARAM_TEXT);
                    $mform->setDefault("filtername{$filterid}", (empty($filter->filtername) ?
                            $filter->label : $filter->filtername));
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $mform->addElement('advcheckbox', "advanced{$filterid}", '', '',
                            array('class' => 'filter_advanced_checkbox'));
                    $mform->setDefault("advanced{$filterid}", $filter->advanced);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $deleteurl = new moodle_url('/totara/reportbuilder/filters.php',
                            array('d' => '1', 'id' => $id, 'fid' => $filterid));
                    $mform->addElement('html', html_writer::link($deleteurl, $OUTPUT->pix_icon('/t/delete', $strdelete),
                            array('title' => $strdelete, 'class' => 'deletefilterbtn')));
                    if ($i != 1) {
                        $moveupurl = new moodle_url('/totara/reportbuilder/filters.php',
                                array('m' => 'up', 'id' => $id, 'fid' => $filterid));
                        $mform->addElement('html', html_writer::link($moveupurl, $OUTPUT->pix_icon('/t/up', $strmoveup),
                                array('title' => $strmoveup, 'class' => 'movefilterupbtn')));
                    } else {
                        $mform->addElement('html', $spacer);
                    }
                    if ($i != $sidebarfiltercount) {
                        $movedownurl = new moodle_url('/totara/reportbuilder/filters.php',
                                array('m' => 'down', 'id' => $id, 'fid' => $filterid));
                        $mform->addElement('html', html_writer::link($movedownurl, $OUTPUT->pix_icon('/t/down', $strmovedown),
                                array('title' => $strmovedown, 'class' => 'movefilterdownbtn')));
                    } else {
                        $mform->addElement('html', $spacer);
                    }
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
                    $i++;
                }
            } else {
                $mform->addElement('html', html_writer::tag('p', get_string('nofiltersyet', 'totara_reportbuilder')));
            }

            $mform->addElement('html', html_writer::start_tag('tr') . html_writer::start_tag('td'));
            $mform->addElement('selectgroups', 'newsidebarfilter', '', $unusedsidebarfilters,
                array('class' => 'new_sidebar_filter_selector filter_selector'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('advcheckbox', 'newsidebarcustomname', '', '',
                array('id' => 'id_newsidebarcustomname', 'class' => 'filter_custom_name_checkbox', 'group' => 0), array(0, 1));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->setDefault('newsidebarcustomname', 0);
            $mform->addElement('text', 'newsidebarfiltername', '', 'class="filter_name_text"');
            $mform->setType('newsidebarfiltername', PARAM_TEXT);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('advcheckbox', 'newsidebaradvanced', '', '', array('class' => 'filter_advanced_checkbox'));
            $mform->disabledIf('newsidebaradvanced', 'newsidebarfilter', 'eq', 0);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
            $mform->addElement('html', html_writer::end_tag('table') . $OUTPUT->container_end());

            // Standard and sidebar filters.

            // Remove the labels from the form elements.
            $renderer->setElementTemplate($selectelementtemplate, 'newstandardfilter');
            $renderer->setElementTemplate($selectelementtemplate, 'newsidebarfilter');
            $renderer->setElementTemplate($checkelementtemplate, 'newstandardadvanced');
            $renderer->setElementTemplate($checkelementtemplate, 'newsidebaradvanced');
            $renderer->setElementTemplate($textelementtemplate, 'newstandardfiltername');
            $renderer->setElementTemplate($textelementtemplate, 'newsidebarfiltername');
            $renderer->setElementTemplate($checkelementtemplate, 'newstandardcustomname');
            $renderer->setElementTemplate($checkelementtemplate, 'newsidebarcustomname');

            foreach ($filters as $index => $filter) {
                $filterid = $filter->filterid;
                $renderer->setElementTemplate($selectelementtemplate, 'filter' . $filterid);
                $renderer->setElementTemplate($checkelementtemplate, 'advanced' . $filterid);
                $renderer->setElementTemplate($textelementtemplate, 'filtername' . $filterid);
                $renderer->setElementTemplate($checkelementtemplate, 'customname' . $filterid);
            }

        } else {
            // No filters available.
            $mform->addElement('html', get_string('nofilteraskdeveloper', 'totara_reportbuilder', $report->source));
        }

        // Toolbar search options.
        $mform->addElement('header', 'toolbarsearch', get_string('toolbarsearch', 'totara_reportbuilder'));
        $mform->addHelpButton('toolbarsearch', 'toolbarsearch', 'totara_reportbuilder');
        $mform->setExpanded('toolbarsearch');

        $mform->addElement('advcheckbox', 'toolbarsearchdisabled', get_string('toolbarsearchdisabled', 'totara_reportbuilder'));
        $mform->setDefault('toolbarsearchdisabled', !$report->toolbarsearch);
        $mform->addHelpButton('toolbarsearchdisabled', 'toolbarsearchdisabled', 'totara_reportbuilder');

        if (count($allsearchcolumns) > 0) {
            $searchcolumns = $report->searchcolumns;

            $mform->addElement('html', $OUTPUT->container(get_string('toolbarsearchdesc', 'totara_reportbuilder')) .
                    html_writer::empty_tag('br'));

            $mform->addElement('html', $OUTPUT->container_start('reportbuilderform') . html_writer::start_tag('table') .
                    html_writer::start_tag('tr') . html_writer::tag('th', get_string('searchfield', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('options', 'totara_reportbuilder')) . html_writer::end_tag('tr'));

            if (isset($report->searchcolumns) && is_array($report->searchcolumns) && count($report->searchcolumns) > 0) {
                foreach ($searchcolumns as $index => $searchcolumn) {
                    $row = array();
                    $searchcolumnid = $searchcolumn->id;
                    $type = $searchcolumn->type;
                    $value = $searchcolumn->value;
                    $field = "{$type}-{$value}";

                    $mform->addElement('html', html_writer::start_tag('tr', array('searchcolumnid' => $searchcolumnid)) .
                            html_writer::start_tag('td'));
                    $mform->addElement('selectgroups', "searchcolumn{$searchcolumnid}", '', $allsearchcolumns,
                            array('class' => 'search_column_selector'));
                    $mform->setDefault("searchcolumn{$searchcolumnid}", $field);
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                    $deleteurl = new moodle_url('/totara/reportbuilder/filters.php',
                            array('d' => '1', 'id' => $id, 'searchcolumnid' => $searchcolumnid));
                    $mform->addElement('html', html_writer::link($deleteurl, $OUTPUT->pix_icon('/t/delete', $strdelete),
                            array('title' => $strdelete, 'class' => 'deletesearchcolumnbtn')));
                    $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
                }
            } else {
                $mform->addElement('html', html_writer::tag('p', get_string('nosearchcolumnsyet', 'totara_reportbuilder')));
            }

            $mform->addElement('html', html_writer::start_tag('tr') . html_writer::start_tag('td'));
            $mform->addElement('selectgroups', 'newsearchcolumn', '', $unusedsearchcolumns,
                    array('class' => 'new_search_column_selector search_column_selector'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
            $mform->addElement('html', html_writer::end_tag('table') . $OUTPUT->container_end());

            $renderer->setElementTemplate($selectelementtemplate, 'newsearchcolumn');

            foreach ($searchcolumns as $index => $searchcolumn) {
                $searchcolumnid = $searchcolumn->id;
                $renderer->setElementTemplate($selectelementtemplate, 'searchcolumn' . $searchcolumnid);
            }

        } else {
            // No search columns available.
            $mform->addElement('html', get_string('nosearchcolumnsaskdeveloper', 'totara_reportbuilder', $report->source));
        }

        // Common.
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source', $report->source);
        $mform->setType('source', PARAM_TEXT);
        $this->add_action_buttons();
    }

    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        $err = array();
        $err += validate_unique_filters($data);
        return $err;
    }
}


/**
 * Formslib template for edit columns form
 */
class report_builder_edit_columns_form extends moodleform {
    function definition() {
        global $CFG, $OUTPUT;
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];

        $strmovedown = get_string('movedown', 'totara_reportbuilder');
        $strmoveup = get_string('moveup', 'totara_reportbuilder');
        $strdelete = get_string('delete', 'totara_reportbuilder');
        $strhide = get_string('hide');
        $strshow = get_string('show');
        $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));

        $mform->addElement('header', 'reportcolumns', get_string('reportcolumns', 'totara_reportbuilder'));

        $mform->addHelpButton('reportcolumns', 'reportbuildercolumns', 'totara_reportbuilder');

        if (isset($report->columnoptions) && is_array($report->columnoptions) && count($report->columnoptions) > 0) {


            $mform->addElement('html', $OUTPUT->container(get_string('help:columnsdesc', 'totara_reportbuilder')) .
                html_writer::empty_tag('br'));


            $mform->addElement('html', $OUTPUT->container_start('reportbuilderform') . html_writer::start_tag('table') .
                html_writer::start_tag('tr') . html_writer::tag('th', get_string('column', 'totara_reportbuilder')) .
                html_writer::tag('th', get_string('customiseheading', 'totara_reportbuilder'), array('colspan' => 2)) .
                html_writer::tag('th', get_string('options', 'totara_reportbuilder') . html_writer::end_tag('tr')));

            $columnsselect = $report->get_columns_select();
            $columnoptions = array();

            if (isset($report->columns) && is_array($report->columns) && count($report->columns) > 0) {
                /* Here we get the ORIGINAL columns from the database, rather than the processed (through any
                 * available column generators) columns that are normally returned when a report is built.
                 * Ideally, we wouldn't be calling get_columns for a second time. See bug 10920. */
                $columns = $report->get_columns(array(), false);
                $colcount = count($columns);
                $i = 1;
                foreach ($columns as $index => $column) {
                    $columnoptions["{$column->type}_{$column->value}"] = $column->heading;
                    if (!isset($column->required) || !$column->required) {
                        $row = array();
                        $type = $column->type;
                        $value = $column->value;
                        $field = "{$column->type}-{$column->value}";
                        $cid = $index;
                        $mform->addElement('html', html_writer::start_tag('tr', array('colid' => $cid)) .
                            html_writer::start_tag('td'));
                        $mform->addElement('selectgroups', "column{$cid}", '', $columnsselect, array('class' => 'column_selector'));
                        $mform->setDefault("column{$cid}", $field);
                        $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));

                        $mform->addElement('advcheckbox', "customheading{$cid}", '', '', array('class' => 'column_custom_heading_checkbox', 'group' => 0), array(0, 1));
                        $mform->setDefault("customheading{$cid}", $column->customheading);

                        $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                        $mform->addElement('text', "heading{$cid}", '', 'class="column_heading_text"');
                        $mform->setType("heading{$cid}", PARAM_TEXT);
                        $mform->setDefault("heading{$cid}", $column->heading);
                        $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
                        // show/hide link
                        if ($column->hidden == 0) {
                            $hideurl = new moodle_url('/totara/reportbuilder/columns.php',
                                array('h' => '1', 'id' => $id, 'cid' => $cid));

                            $mform->addElement('html', html_writer::link(
                                $hideurl,
                                $OUTPUT->pix_icon('/t/hide', $strhide, null, array('class' => 'iconsmall')),
                                array('class' => 'hidecolbtn action-icon', 'title' => $strhide)
                            ));
                        } else {
                            $showurl = new moodle_url('/totara/reportbuilder/columns.php',
                                array('h' => '0', 'id' => $id, 'cid' => $cid));

                            $mform->addElement('html', html_writer::link(
                                $showurl,
                                $OUTPUT->pix_icon('/t/show', $strshow, null, array('class' => 'iconsmall')),
                                array('class' => 'showcolbtn action-icon', 'title' => $strshow)
                            ));
                        }
                        // delete link
                        $delurl = new moodle_url('/totara/reportbuilder/columns.php',
                            array('d' => '1', 'id' => $id, 'cid' => $cid));
                        $mform->addElement('html', html_writer::link(
                            $delurl,
                            $OUTPUT->pix_icon('/t/delete', $strdelete, null, array('class' => 'iconsmall')),
                            array('class' => 'deletecolbtn action-icon', 'title' => $strdelete)
                        ));
                        // move up link
                        if ($i != 1) {
                            $moveupurl = new moodle_url('/totara/reportbuilder/columns.php',
                                array('m' => 'up', 'id' => $id, 'cid' => $cid));
                            $mform->addElement('html', html_writer::link(
                                $moveupurl,
                                $OUTPUT->pix_icon('/t/up', $strmoveup, null, array('class' => 'iconsmall')),
                                array('class' => 'movecolupbtn action-icon', 'title' => $strmoveup)
                            ));
                        } else {
                            $mform->addElement('html', $spacer);
                        }

                        // move down link
                        if ($i != $colcount) {
                            $movedownurl = new moodle_url('/totara/reportbuilder/columns.php',
                                array('m' => 'down', 'id' => $id, 'cid' => $cid));
                            $mform->addElement('html', html_writer::link(
                                $movedownurl,
                                $OUTPUT->pix_icon('/t/down', $strmovedown, null, array('class' => 'iconsmall')),
                                array('class' => 'movecoldownbtn action-icon', 'title' => $strmovedown)
                            ));
                        } else {
                            $mform->addElement('html', $spacer);
                        }

                        $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
                        $i++;
                    }
                }
            } else {
                $mform->addElement('html', html_writer::tag('p', get_string('nocolumnsyet', 'totara_reportbuilder')));
                $columns = array();
            }

            $mform->addElement('html', html_writer::start_tag('tr') . html_writer::start_tag('td'));
            $newcolumnsselect = array_merge(
                array(
                    get_string('new') => array(0 => get_string('addanothercolumn', 'totara_reportbuilder'))
                ),
                $columnsselect);
            // Remove already-added cols from the new col selector
            $cleanednewcolselect = $newcolumnsselect;
            foreach ($newcolumnsselect as $okey => $optgroup) {
                foreach ($optgroup as $typeval => $heading) {
                    $typevalarr = explode('-', $typeval);
                    foreach ($report->columns as $curcol) {
                        if ($curcol->type == $typevalarr[0] && $curcol->value == $typevalarr[1]) {
                            unset($cleanednewcolselect[$okey][$typeval]);
                        }
                    }
                }
            }
            $newcolumnsselect = $cleanednewcolselect;
            unset($cleanednewcolselect);
            $mform->addElement('selectgroups', 'newcolumns', '', $newcolumnsselect, array('class' => 'column_selector new_column_selector'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('advcheckbox', "newcustomheading", '', '', array('id' => 'id_newcustomheading', 'class' => 'column_custom_heading_checkbox', 'group' => 0), array(0, 1));
            $mform->setDefault("newcustomheading", 0);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));

            $mform->addElement('text', 'newheading', '', 'class="column_heading_text"');
            $mform->setType('newheading', PARAM_TEXT);
            // do manually as disabledIf doesn't play nicely with using JS to update heading values
            // $mform->disabledIf('newheading', 'newcolumns', 'eq', 0);
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::start_tag('td'));
            $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));
            $mform->addElement('html', html_writer::end_tag('table') . $OUTPUT->container_end());


            // if the report is referencing columns that don't exist in the
            // source, display them here so the user has the option to delete
            // them
            if (count($report->badcolumns)) {
                $mform->addElement('header', 'badcols', get_string('badcolumns', 'totara_reportbuilder'));
                $mform->addElement('html', html_writer::tag('p', get_string('badcolumnsdesc', 'totara_reportbuilder')));

                $mform->addElement('html',
                    $OUTPUT->container_start('reportbuilderform') . html_writer::start_tag('table') . html_writer::start_tag('tr') .
                    html_writer::tag('th', get_string('type', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('value', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('heading', 'totara_reportbuilder')) .
                    html_writer::tag('th', get_string('options', 'totara_reportbuilder')) . html_writer::end_tag('tr'));
                foreach ($report->badcolumns as $bad) {
                    $deleteurl = new moodle_url('/totara/reportbuilder/columns.php',
                        array('d' => '1', 'id' => $id, 'cid' => $bad['id']));

                    $mform->addElement('html', html_writer::start_tag('tr', array('colid' => $bad['id'])) .
                        html_writer::tag('td', $bad['type']) .
                        html_writer::tag('td', $bad['value']) .
                        html_writer::tag('td', $bad['heading']) .
                        html_writer::start_tag('td') .
                        html_writer::link($deleteurl, $OUTPUT->pix_icon('/t/delete', $strdelete),
                            array('title' => $strdelete, 'class' => 'deletecolbtn')) .
                        html_writer::end_tag('td') . html_writer::end_tag('tr'));
                }
                $mform->addElement('html', html_writer::end_tag('table') . $OUTPUT->container_end());
            }


            $mform->addElement('header', 'sorting', get_string('sorting', 'totara_reportbuilder'));
            $mform->addHelpButton('sorting', 'reportbuildersorting', 'totara_reportbuilder');

            $pick = array('' => get_string('noneselected', 'totara_reportbuilder'));
            $select = array_merge($pick, $columnoptions);
            $mform->addElement('select', 'defaultsortcolumn', get_string('defaultsortcolumn', 'totara_reportbuilder'), $select);
            $mform->setDefault('defaultsortcolumn', $report->defaultsortcolumn);


            $radiogroup = array();
            $radiogroup[] =& $mform->createElement('radio', 'defaultsortorder', '', get_string('ascending', 'totara_reportbuilder'), SORT_ASC);
            $radiogroup[] =& $mform->createElement('radio', 'defaultsortorder', '', get_string('descending', 'totara_reportbuilder'), SORT_DESC);
            $mform->addGroup($radiogroup, 'radiogroup', get_string('defaultsortorder', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
            $mform->setDefault('defaultsortorder', $report->defaultsortorder);
        } else {
            $mform->addElement('html', get_string('error:nocolumns', 'totara_reportbuilder', $report->source));
        }

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source', $report->source);
        $mform->setType('source', PARAM_TEXT);
        $this->add_action_buttons();

        // remove the labels from the form elements
        $renderer =& $mform->defaultRenderer();
        $select_elementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'fselectgroups'), 'fitem');
        $check_elementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'fcheckbox'), 'fitem');
        $text_elementtemplate = $OUTPUT->container($OUTPUT->container('{element}', 'ftext'), 'fitem');
        $renderer->setElementTemplate($select_elementtemplate, 'newcolumns');
        $renderer->setElementTemplate($check_elementtemplate, 'newcustomheading');
        $renderer->setElementTemplate($text_elementtemplate, 'newheading');
        foreach ($columns as $index => $unused) {
            $renderer->setElementTemplate($select_elementtemplate, 'column' . $index);
            $renderer->setElementTemplate($check_elementtemplate, 'customheading' . $index);
            $renderer->setElementTemplate($text_elementtemplate, 'heading' . $index);
        }
    }


    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        $err = array();
        $err += validate_unique_columns($data);
        $err += validate_none_empty_heading_columns($data);
        return $err;
    }


}


/**
 * Formslib template for content restrictions form
 */
class report_builder_edit_content_form extends moodleform {
    function definition() {
        global $DB;
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];

        // get array of content options
        $contentoptions = isset($report->contentoptions) ?
            $report->contentoptions : array();

        $mform->addElement('header', 'contentheader', get_string('contentcontrols', 'totara_reportbuilder'));

        if (count($contentoptions)) {
            if ($report->embeddedurl !== null) {
                $mform->addElement('html', html_writer::tag('p', get_string('embeddedcontentnotes', 'totara_reportbuilder')));
            }

            $radiogroup = array();
            $radiogroup[] =& $mform->createElement('radio', 'contentenabled', '', get_string('nocontentrestriction', 'totara_reportbuilder'), 0);
            $radiogroup[] =& $mform->createElement('radio', 'contentenabled', '', get_string('withcontentrestrictionany', 'totara_reportbuilder'), 1);
            $radiogroup[] =& $mform->createElement('radio', 'contentenabled', '', get_string('withcontentrestrictionall', 'totara_reportbuilder'), 2);
            $mform->addGroup($radiogroup, 'radiogroup', get_string('restrictcontent', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
            $mform->addHelpButton('radiogroup', 'reportbuildercontentmode', 'totara_reportbuilder');
            $mform->setDefault('contentenabled', $DB->get_field('report_builder', 'contentmode', array('id' => $id)));

            // display any content restriction form sections that are enabled for
            // this source
            foreach ($contentoptions as $option) {
                $classname = 'rb_' . $option->classname.'_content';
                if (class_exists($classname)) {
                    $obj = new $classname();
                    $obj->form_template($mform, $id, $option->title);
                }
            }

            $mform->addElement('hidden', 'id', $id);
            $mform->setType('id', PARAM_INT);
            $mform->addElement('hidden', 'source', $report->source);
            $mform->setType('source', PARAM_TEXT);
            $this->add_action_buttons();
        } else {
            // there are no content restrictions for this source. Inform the user
            $mform->addElement('html',
                get_string('error:nocontentrestrictions',
                'totara_reportbuilder', $report->source));
        }
    }
}

/**
 * Formslib template for access restrictions form
 */
class report_builder_edit_access_form extends moodleform {
    function definition() {
        global $DB;
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];

        $mform->addElement('header', 'access', get_string('accesscontrols', 'totara_reportbuilder'));

        if ($report->embeddedurl !== null) {
            $mform->addElement('html', html_writer::tag('p', get_string('embeddedaccessnotes', 'totara_reportbuilder')));
        }
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'accessenabled', '', get_string('norestriction', 'totara_reportbuilder'), 0);
        $radiogroup[] =& $mform->createElement('radio', 'accessenabled', '', get_string('withrestriction', 'totara_reportbuilder'), 1);
        $mform->addGroup($radiogroup, 'radiogroup', get_string('restrictaccess', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('accessenabled', $DB->get_field('report_builder', 'accessmode', array('id' => $id)));
        $mform->addHelpButton('radiogroup', 'reportbuilderaccessmode', 'totara_reportbuilder');

        // loop round classes, only considering classes that extend rb_base_access
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'rb_base_access')) {
                $obj = new $class();
                // add any form elements for this access option
                $obj->form_template($mform, $id);
            }
        }

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source', $report->source);
        $mform->setType('source', PARAM_TEXT);
        $this->add_action_buttons();
    }

}

/**
 * Formslib tempalte for the edit report form
 */
class report_builder_edit_performance_form extends moodleform {
    function definition() {
        global $output, $CFG;
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];
        $schedule = $this->_customdata['schedule'];

        $mform->addElement('header', 'general', get_string('initialdisplay_heading', 'totara_reportbuilder'));
        $initial_display_attributes = sizeof($report->filters) < 1 ? array('disabled' => 'disabled', 'group' => null) : null;
        $initial_display_sidenote = is_null($initial_display_attributes) ? '' : get_string('initialdisplay_disabled', 'totara_reportbuilder');
        $mform->addElement('advcheckbox', 'initialdisplay', get_string('initialdisplay', 'totara_reportbuilder'),
            $initial_display_sidenote, $initial_display_attributes, array(RB_INITIAL_DISPLAY_SHOW, RB_INITIAL_DISPLAY_HIDE));
        $mform->setType('initialdisplay', PARAM_INT);
        $mform->setDefault('initialdisplay', RB_INITIAL_DISPLAY_SHOW);
        $mform->addHelpButton('initialdisplay', 'initialdisplay', 'totara_reportbuilder');

        $mform->addElement('header', 'general', get_string('reportbuildercache_heading', 'totara_reportbuilder'));
        if (!empty($CFG->enablereportcaching)) {
            //only show report cache settings if it is enabled
            $caching_attributes = $report->src->cacheable ? null : array('disabled' => 'disabled', 'group' => null);
            $caching_sidenote = is_null($caching_attributes) ? '' :
                    get_string('reportbuildercache_disabled', 'totara_reportbuilder');
            $mform->addElement('advcheckbox', 'cache', get_string('cache', 'totara_reportbuilder'),
                    $caching_sidenote, $caching_attributes, array(0, 1));
            $mform->setType('cache', PARAM_INT);
            $mform->addHelpButton('cache', 'reportbuildercache', 'totara_reportbuilder');

            $mform->addElement('scheduler', 'schedulegroup', get_string('reportbuildercachescheduler', 'totara_reportbuilder'));
            $mform->disabledIf('schedulegroup', 'cache');
            $mform->addHelpButton('schedulegroup', 'reportbuildercachescheduler', 'totara_reportbuilder');

            $mform->addElement('static', 'servertime', get_string('reportbuildercacheservertime', 'totara_reportbuilder'),
                    date_format_string(time(), get_string('strftimedaydatetime', 'langconfig')));
            $mform->addHelpButton('servertime', 'reportbuildercacheservertime', 'totara_reportbuilder');

            $usertz = totara_get_clean_timezone();
            $cachetime = isset($report->cacheschedule->lastreport) ? $report->cacheschedule->lastreport : 0;
            $cachedstr = get_string('lastcached','totara_reportbuilder', userdate($cachetime, '', $usertz));
            $notcachedstr = get_string('notcached','totara_reportbuilder');
            $lastcached = ($cachetime > 0) ? $cachedstr : $notcachedstr;

            if ($report->cache) {
                $mform->addElement('static', 'cachenowselector', get_string('reportbuilderinitcache', 'totara_reportbuilder'),
                    html_writer::tag('span', $lastcached. ' ') .
                    $output->cachenow_button($id)
                );
            } else {
                $mform->addElement('advcheckbox', 'generatenow', get_string('cachenow', 'totara_reportbuilder'), '', null, array(0, 1));
                $mform->setType('generatenow', PARAM_INT);
                $mform->addHelpButton('generatenow', 'cachenow', 'totara_reportbuilder');
                $mform->disabledIf('generatenow', 'cache');
            }

        } else {
            //report caching is not enabled, inform user and link to settings page.
            $mform->addElement('hidden', 'cache', 0);
            $mform->setType('cache', PARAM_INT);
            $enablelink = new moodle_url("/".$CFG->admin."/settings.php", array('section' => 'optionalsubsystems'));
            $mform->addElement('static', 'reportcachingdisabled', '', get_string('reportcachingdisabled', 'totara_reportbuilder', $enablelink->out()));
        }

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'source', $report->source);
        $mform->setType('source', PARAM_TEXT);

        // set the defaults
        $this->set_data($report);
        $this->set_data($schedule);

        $this->add_action_buttons();
    }
}

/**
 * Method to check a shortname is unique in database
 *
 * @param array $data Array of data from the form
 *
 * @return array Array of errors to display on failure
 */
function validate_shortname($data) {
    global $DB;
    $errors = array();

    $foundreports = $DB->get_records('report_builder', array('shortname' => $data['shortname']));
    if (count($foundreports)) {
        if (!empty($data['id'])) {
            unset($foundreports[$data['id']]);
        }
        if (!empty($foundreports)) {
            $errors['shortname'] = get_string('shortnametaken', 'totara_reportbuilder');
        }
    }
    return $errors;

}

/**
 * Method to check each column is only included once
 *
 * Flexible table breaks if not used as headers must be distinct
 *
 * @param array $data Array of data from the form
 *
 * @return array Array of errors to display on failure
 */
function validate_unique_columns($data) {
    global $DB;
    $errors = array();

    $id = $data['id'];
    $used_cols = array();
    $currentcols = $DB->get_records('report_builder_columns', array('reportid' => $id));
    foreach ($currentcols as $col) {
        $field = "column{$col->id}";
        if (isset($data[$field])) {
            if (array_key_exists($data[$field], $used_cols)) {
                $errors[$field] = get_string('norepeatcols', 'totara_reportbuilder');
            } else {
                $used_cols[$data[$field]] = 1;
            }
        }
    }

    // also check new column if set
    if (isset($data['newcolumns'])) {
        if (array_key_exists($data['newcolumns'], $used_cols)) {
            $errors['newcolumns'] = get_string('norepeatcols', 'totara_reportbuilder');
        }
    }
    return $errors;
}


/**
 * Method to check column headings aren't empty (or just whitespace)
 *
 * @param array $data Array of data from the form
 *
 * @return array Array of errors to display on failure
 */
function validate_none_empty_heading_columns($data) {
    $errors = array();

    foreach ($data as $key => $value) {
        // only look at the heading fields
        if (preg_match('/^heading\d+/', $key)) {
            if (trim($value) == '') {
                $errors[$key] = get_string('noemptycols', 'totara_reportbuilder');
            }
        }
    }

    return $errors;
}


/**
 * Method to check each filter is only included once
 *
 * @param array $data Array of data from the form
 *
 * @return array Array of errors to display on failure
 */
function validate_unique_filters($data) {
    global $DB;
    $errors = array();

    $id = $data['id'];
    $used_filters = array();
    $currentfilters = $DB->get_records('report_builder_filters', array('reportid' => $id));
    foreach ($currentfilters as $filt) {
        $field = "filter{$filt->id}";
        if (isset($data[$field])) {
            if (array_key_exists($data[$field], $used_filters)) {
                $errors[$field] = get_string('norepeatfilters', 'totara_reportbuilder');
            } else {
                $used_filters[$data[$field]] = 1;
            }
        }
    }

    // also check new filter if set
    if (isset($data['newfilter'])) {
        if (array_key_exists($data['newfilter'], $used_filters)) {
            $errors['newfilter'] = get_string('norepeatfilters', 'totara_reportbuilder');
        }
    }
    return $errors;
}


/**
 * Formslib template for saved searches form
 */
class report_builder_save_form extends moodleform {
    function definition() {
        global $USER, $SESSION;
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $this->_customdata['id'];
        $sid = isset($this->_customdata['sid']) ? $this->_customdata['sid'] : '';
        $filterparams = $report->get_restriction_descriptions('filter');
        $shortname = $report->shortname;
        $filtername = 'filtering_'.$shortname;
        $searchsettings = (isset($SESSION->reportbuilder[$id])) ? serialize($SESSION->reportbuilder[$id]) : null;
        $params = implode(html_writer::empty_tag('br'), $filterparams);

        if (isset($this->_customdata['sid'])) {
            $mform->addElement('header', 'savesearch', get_string('editingsavedsearch', 'totara_reportbuilder'));
        } else {
            $mform->addElement('header', 'savesearch', get_string('createasavedsearch', 'totara_reportbuilder'));
        }
        $mform->addElement('static', 'description', '', get_string('savedsearchdesc', 'totara_reportbuilder'));
        $mform->addElement('static', 'params', get_string('currentsearchparams', 'totara_reportbuilder'), $params);
        $mform->addElement('text', 'name', get_string('searchname', 'totara_reportbuilder'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('missingsearchname', 'totara_reportbuilder'), 'required', null, 'server');
        $mform->addElement('advcheckbox', 'ispublic', get_string('publicallyavailable', 'totara_reportbuilder'), '', null, array(0, 1));
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sid', $sid);
        $mform->setType('sid', PARAM_INT);
        $mform->addElement('hidden', 'search', $searchsettings);
        $mform->setType('search', PARAM_TEXT);
        $mform->addElement('hidden', 'creatoruserid', $USER->id);
        $mform->setType('creatoruserid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHANUMEXT);

        $this->add_action_buttons();
    }
}

class report_builder_standard_search_form extends moodleform {
    function definition() {
        $mform =& $this->_form;
        $fields = $this->_customdata['fields'];

        $mform->disable_form_change_checker();

        if ($fields && is_array($fields) && count($fields) > 0) {
            $mform->addElement('header', 'newfilterstandard', get_string('searchby', 'totara_reportbuilder'));

            foreach ($fields as $ft) {
                $ft->setupForm($mform);
            }

            $submitgroup = array();
            $submitgroup[] =& $mform->createElement('html', '&nbsp;', html_writer::empty_tag('br'));
            $submitgroup[] =& $mform->createElement('submit', 'addfilter',
                    get_string('search', 'totara_reportbuilder'));
            $submitgroup[] =& $mform->createElement('submit', 'clearstandardfilters',
                    get_string('clearform', 'totara_reportbuilder'));
            $mform->addGroup($submitgroup, 'submitgroupstandard', '&nbsp;', ' &nbsp; ');
        }
    }

    function definition_after_data() {
        $mform =& $this->_form;
        $fields = $this->_customdata['fields'];

        if ($fields && is_array($fields) && count($fields) > 0) {
            foreach ($fields as $ft) {
                if (method_exists($ft, 'definition_after_data')) {
                    $ft->definition_after_data($mform);
                }
            }
        }
    }
}

class report_builder_sidebar_search_form extends moodleform {
    public function definition() {
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $id = $report->_id;
        $fields = $this->_customdata['fields'];

        $mform->updateAttributes(array('id' => 'sidebarfilter'.$id));
        $mform->disable_form_change_checker();

        if ($fields && is_array($fields) && count($fields) > 0) {
            $mform->addElement('header', 'newfiltersidebar', get_string('filterby', 'totara_reportbuilder'));

            foreach ($fields as $ft) {
                $ft->setupForm($mform);
            }

            $submitgroup = array();
            $submitgroup[] =& $mform->createElement('html', '&nbsp;', html_writer::empty_tag('br'));
            $submitgroup[] =& $mform->createElement('submit', 'addfilter',
                    get_string('search', 'totara_reportbuilder'));
            $submitgroup[] =& $mform->createElement('submit', 'clearsidebarfilters',
                    get_string('clearform', 'totara_reportbuilder'));
            $mform->addGroup($submitgroup, 'submitgroupsidebar', '&nbsp;', ' &nbsp; ');

            $mform->addElement('hidden', 'id', $id);
            $mform->setType('id', PARAM_INT);
        }
    }

    public function definition_after_data() {
        $mform =& $this->_form;
        $report = $this->_customdata['report'];
        $fields = $this->_customdata['fields'];

        $report->add_filter_counts($mform);

        if ($fields && is_array($fields) && count($fields) > 0) {
            foreach ($fields as $ft) {
                if (method_exists($ft, 'definition_after_data')) {
                    $ft->definition_after_data($mform);
                }
            }
        }
    }
}

class report_builder_toolbar_search_form extends moodleform {
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('text', 'toolbarsearchtext',
                get_string('searchby', 'totara_reportbuilder'));
        $mform->setType('toolbarsearchtext', PARAM_TEXT);
        $mform->addElement('submit', 'toolbarsearchbutton',
                get_string('search', 'totara_reportbuilder'));
        $mform->addElement('submit', 'cleartoolbarsearchtext',
                get_string('clearform', 'totara_reportbuilder'));
    }

    public function definition_after_data() {
        $mform =& $this->_form;

        $toolbarsearchtext = $this->_customdata['toolbarsearchtext'];

        $mform->setDefault('toolbarsearchtext', $toolbarsearchtext);
    }
}

class report_builder_course_expand_form extends moodleform {
    public function definition() {
        $mform =& $this->_form;

        $summary = $this->_customdata['summary'];
        $status = $this->_customdata['status'];
        $enroltype = isset($this->_customdata['enroltype']) ? $this->_customdata['enroltype'] : '';
        $progress = isset($this->_customdata['progress']) ? $this->_customdata['progress'] : '';
        $enddate = isset($this->_customdata['enddate']) ? $this->_customdata['enddate'] : '';
        $grade = isset($this->_customdata['grade']) ? $this->_customdata['grade'] : '';
        $action = $this->_customdata['action'];
        $url = $this->_customdata['url'];

        if ($summary != '') {
            $mform->addElement('static', 'summary', get_string('coursesummary'), $summary);
        }
        if ($status != '') {
            $mform->addElement('static', 'status', get_string('status'), $status);
        }
        if ($enroltype != '') {
            $mform->addElement('static', 'enroltype', get_string('courseenroltype', 'totara_reportbuilder'), $enroltype);
        }
        if ($progress != '') {
            $mform->addElement('static', 'progress', get_string('courseprogress', 'totara_reportbuilder'), $progress);
        }
        if ($enddate != '') {
            $mform->addElement('static', 'enddate', get_string('courseenddate', 'totara_reportbuilder'), $enddate);
        }
        if ($grade != '') {
            $mform->addElement('static', 'grade', get_string('grade'), $grade);
        }
        if ($url != '') {
            $mform->addElement('static', 'enrol', '', html_writer::link($url, $action,
                    array('class' => 'link-as-button')));
        } else {
            $mform->addElement('static', 'enrol', '', $action);
        }
    }
}

class report_builder_program_expand_form extends moodleform {
    public function definition() {
        $mform =& $this->_form;

        $prog = $this->_customdata;

        if ($prog['summary']) {
            $mform->addElement('static', 'summary', get_string('summary', 'totara_program'), $prog['summary']);
        }

        if ($prog['assigned']) {
            if ($prog['certifid']) {
                $mform->addElement('static', 'status', get_string('status'), get_string('youareassigned', 'totara_certification'));
            } else {
                $mform->addElement('static', 'status', get_string('status'), get_string('youareassigned', 'totara_program'));
            }
        }

        $url = new moodle_url('/totara/program/view.php', array('id' => $prog['id']));
        $mform->addElement('static', 'view', '', html_writer::link($url, get_string('viewprogram', 'totara_program'),
                array('class' => 'link-as-button')));
    }
}
