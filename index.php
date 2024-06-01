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

global $CFG, $PAGE, $DB, $OUTPUT;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'view',PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

$PAGE->set_url('/local/sibguexporttest/index.php', ['courseid' => $courseid, 'action' => $action]);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

/** Проверяем авторизован ли пользователь */
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

/** Проверяем права пользователя */
if (!is_siteadmin() && !has_capability('local/sibguexporttest:view', $context)) {
    header('Location: ' . $CFG->wwwroot);
    die();
}

switch ($action) {
    case 'settings':
        $title = get_string('navigation_settings', 'local_sibguexporttest');
        $render = $PAGE->get_renderer('local_sibguexporttest', 'settings');
        $output = $render->view();
        break;
    case 'view':
        $title = get_string('navigation_view', 'local_sibguexporttest');
        /** @var \local_sibguexporttest\output\view_renderer $render */
        $render = $PAGE->get_renderer('local_sibguexporttest', 'view');
        $render->init_baseurl($PAGE->url);
        $render->init_manager();
        $output = $render->view($page, $perpage);
        break;
    case 'task':
        $title = get_string('navigation_task', 'local_sibguexporttest');
        /** @var \local_sibguexporttest\output\view_renderer $render */
        $render = $PAGE->get_renderer('local_sibguexporttest', 'task');
        $render->init_baseurl($PAGE->url);
        $output = $render->view($page, $perpage);
        break;
    default:
        $title = 'В разработке...';
        $output = '';
        break;
}

$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

echo $output;

echo $OUTPUT->footer();