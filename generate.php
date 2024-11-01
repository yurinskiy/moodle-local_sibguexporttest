<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG, $PAGE, $COURSE, $DB, $OUTPUT, $USER;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

$action = required_param('action', PARAM_ALPHA);
$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', null, PARAM_INT);
$debug = optional_param('debug', false, PARAM_BOOL);
//
if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    print_error('invalidcourseid');
}

/** Проверяем авторизован ли пользователь */
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url('/local/sibguexporttest/generate.php', ['action' => $action, 'courseid' => $courseid, 'userid' => $userid]);

switch ($action) {
    case 'one':
        if (!$userid) {
            throw new \moodle_exception('missingparam', '', '', 'userid');
        }

        /** @var \local_sibguexporttest\output\generator_renderer $renderer */
        $renderer = $PAGE->get_renderer('local_sibguexporttest', 'generator');
        /** @var \local_sibguexporttest\output\question_renderer $qrenderer */
        $qrenderer = $PAGE->get_renderer('local_sibguexporttest', 'question');
        (new \local_sibguexporttest\generator($courseid, $userid, $renderer, $qrenderer, $debug))->get_pdf_response();
        //(new \local_sibguexporttest\generator_v2($renderer, $qrenderer, 'default', $courseid, $userid, $debug))->get_pdf_response();
        exit;
    default:
        redirect(
            new moodle_url('/local/sibguexporttest/index.php', ['courseid' => $courseid, 'action' => 'view']),
            'Неизвестное действие.'
        );
        exit;
}