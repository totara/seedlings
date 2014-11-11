<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/**
 * Fix incorrectly upgraded text columns.
 */
function totara_core_fix_old_upgraded_mssql() {
    global $CFG, $DB, $OUTPUT;

    if ($DB->get_dbfamily() !== 'mssql') {
        return;
    }

    $dbman = $DB->get_manager();

    // Changing the default of field laststatus on table backup_courses to 5.
    $table = new xmldb_table('backup_courses');
    $field = new xmldb_field('laststatus', XMLDB_TYPE_CHAR, '1', null, XMLDB_NOTNULL, null, '5', 'lastendtime');
    $dbman->change_field_default($table, $field);

    // All these text columns should be NOT NULL.
    $candidates = array(
        'appraisal_event_message' => array('content'),
        'assign' => array('intro'),
        'badge' => array('message', 'messagesubject'),
        'badge_issued' => array('message', 'uniquehash'),
        'facetoface_notification' => array('body'),
        'facetoface_notification_tpl' => array('body'),
        'feedback_value_history' => array('value'),
        'goal_scale_values' => array('name'),
        'qtype_randomsamatch_options' => array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'),
        'config' => array('value'),
        'config_plugins' => array('value'),
        'course_request' => array('summary', 'reason'),
        'event' => array('description', 'name'),
        'cache_filters' => array('rawtext'),
        'cache_text' => array('formattedtext'),
        'log_queries' => array('sqltext'),
        'scale' => array('scale', 'description'),
        'scale_history' => array('scale', 'description'),
        'role' => array('description'),
        'user_info_field' => array('name'),
        'user_info_data' => array('data'),
        'question_categories' => array('info'),
        'question' => array('questiontext', 'generalfeedback'),
        'question_answers' => array('answer', 'feedback'),
        'question_hints' => array('hint'),
        'question_states' => array('answer'),
        'question_sessions' => array('manualcomment'),
        'mnet_host' => array('public_key'),
        'mnet_rpc' => array('help', 'profile'),
        'events_queue' => array('eventdata'),
        'grade_outcomes' => array('fullname'),
        'grade_outcomes_history' => array('fullname'),
        'tag_correlation' => array('correlatedtags'),
        'cache_flags' => array('value'),
        'comments' => array('content'),
        'blog_external' => array('url'),
        'backup_controllers' => array('controller'),
        'profiling' => array('data'),
        'qtype_match_options' => array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'),
        'qtype_match_subquestions' => array('questiontext'),
        'question_multianswer' => array('sequence'),
        'qtype_multichoice_options' => array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'),
        'assignment' => array('intro'),
        'assignment_submissions' => array('submissioncomment'),
        'book_chapters' => array('content'),
        'chat' => array('intro'),
        'chat_messages' => array('message'),
        'chat_messages_current' => array('message'),
        'choice' => array('intro'),
        'data' => array('intro'),
        'data_fields' => array('description'),
        'feedback' => array('intro', 'page_after_submit'),
        'feedback_item' => array('presentation'),
        'feedback_value' => array('value'),
        'feedback_valuetmp' => array('value'),
        'forum' => array('intro'),
        'forum_posts' => array('message'),
        'glossary' => array('intro'),
        'glossary_entries' => array('definition'),
        'label' => array('intro'),
        'lesson' => array('conditions'),
        'lesson_pages' => array('contents'),
        'lti' => array('toolurl'),
        'lti_types' => array('baseurl'),
        'quiz' => array('intro', 'questions'),
        'quiz_attempts' => array('layout'),
        'quiz_feedback' => array('feedbacktext'),
        'resource_old' => array('alltext', 'popup'),
        'scorm' => array('intro'),
        'scorm_scoes' => array('launch'),
        'scorm_scoes_data' => array('value'),
        'scorm_scoes_track' => array('value'),
        'survey' => array('intro'),
        'survey_answers' => array('answer1', 'answer2'),
        'survey_analysis' => array('notes'),
        'url' => array('externalurl'),
        'wiki_pages' => array('cachedcontent'),
        'wiki_versions' => array('content'),
        'workshop_old' => array('description'),
        'workshop_elements_old' => array('description'),
        'workshop_rubrics_old' => array('description'),
        'workshop_submissions_old' => array('description'),
        'workshop_grades_old' => array('feedback'),
        'workshop_stockcomments_old' => array('comments'),
        'workshop_comments_old' => array('comments'),
        'block_rss_client' => array('title', 'description'),
        'block_quicklinks' => array('title'),
        'block_totara_stats' => array('data'),
        'mnetservice_enrol_courses' => array('summary'),
        'course_info_field' => array('fullname'),
        'errorlog' => array('details'),
        'comp_scale_values' => array('name'),
        'comp_template' => array('fullname'),
        'pos_assignment' => array('fullname'),
        'pos_assignment_history' => array('fullname'),
        'dp_priority_scale' => array('description'),
        'prog_message' => array('mainmessage'),
        'tool_customlang' => array('original'),
    );

    $totalcount = 0;
    foreach ($candidates as $table => $columns) {
        if (!$dbman->table_exists($table)) {
            unset($candidates[$table]);
            continue;
        }
        foreach ($columns as $column) {
            $totalcount++;
        }
    }

    $pbar = new progress_bar('mssqlfixextnulls', 500, true);

    $i = 0;
    foreach ($candidates as $table => $columns) {
        $existingcolumns = $DB->get_columns($table);
        foreach ($columns as $column) {
            if (isset($existingcolumns[$column])) {
                /** @var database_column_info $existing */
                $existing = $existingcolumns[$column];
                if ($existing->meta_type === 'X' and !$existing->not_null) {
                    $DB->execute("UPDATE {{$table}} SET $column = '' WHERE $column IS NULL");
                    $xmldbtable = new xmldb_table($table);
                    $xmldbcolumn = new xmldb_field($column, XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL);
                    $dbman->change_field_notnull($xmldbtable, $xmldbcolumn);
                }
            }
            $i++;
            $pbar->update($i, $totalcount, "Fixed text columns in MS SQL database - $i/$totalcount.");
        }
    }
}
