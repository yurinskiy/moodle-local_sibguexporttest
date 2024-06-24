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

global $CFG, $PAGE, $DB, $OUTPUT, $USER;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'view',PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

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
        $PAGE->set_url('/local/sibguexporttest/index.php', ['courseid' => $courseid, 'action' => $action]);

        $title = get_string('navigation_settings', 'local_sibguexporttest');
        $render = $PAGE->get_renderer('local_sibguexporttest', 'settings');
        $output = $render->view();
        break;
    case 'view':
        # filters
        $group = optional_param('group', '', PARAM_INT);
        $sort = optional_param('sort', 'lastcourseaccess', PARAM_ALPHAEXT);
        $direction = optional_param('dir', 'ASC', PARAM_ALPHAEXT);
        $lastattempt_sdt = optional_param_array('lastattempt_sdt', [], PARAM_INT);
        $lastattempt_edt = optional_param_array('lastattempt_edt', [], PARAM_INT);

        $lastattempt_sdt += [
            'enabled' => false,
            'day' => date_create()->format('d'),
            'month' => date_create()->format('m'),
            'year' => date_create()->format('Y'),
        ];

        $lastattempt_edt += [
            'enabled' => false,
            'day' => date_create()->format('d'),
            'month' => date_create()->format('m'),
            'year' => date_create()->format('Y'),
        ];

        $url_params = [
            'courseid' => $courseid,
            'action' => $action,
            'group' => $group,
            'sort' => $sort,
            'dir' => $direction,
            'perpage' => $perpage,
        ];

        if ($lastattempt_sdt['enabled']) {
            $url_params += [
                'lastattempt_sdt[enabled]' => $lastattempt_sdt['enabled'],
                'lastattempt_sdt[day]' => $lastattempt_sdt['day'],
                'lastattempt_sdt[month]' => $lastattempt_sdt['month'],
                'lastattempt_sdt[year]' => $lastattempt_sdt['year']
            ];
        }

        if ($lastattempt_edt['enabled']) {
            $url_params += [
                'lastattempt_edt[enabled]' => $lastattempt_edt['enabled'],
                'lastattempt_edt[day]' => $lastattempt_edt['day'],
                'lastattempt_edt[month]' => $lastattempt_edt['month'],
                'lastattempt_edt[year]' => $lastattempt_edt['year'],
            ];
        }

        $PAGE->set_url('/local/sibguexporttest/index.php', $url_params);

        $menu = $PAGE->settingsnav->find('sibguexporttest_download', navigation_node::NODETYPE_LEAF);
        $menu->make_active();

        $title = get_string('navigation_view', 'local_sibguexporttest');
        /** @var \local_sibguexporttest\output\view_renderer $render */
        $render = $PAGE->get_renderer('local_sibguexporttest', 'view');
        $render->init_baseurl($PAGE->url);
        $render->init_manager();

        $downloadAll = optional_param('download_all', false, PARAM_BOOL);
        $downloadSelected = optional_param('download_selected', false, PARAM_BOOL);
        if ($downloadAll || $downloadSelected) {
            if ($downloadAll) {
                $users = $render->get_users([
                    'group' => $group,
                    'lastattempt_sdt' => $lastattempt_sdt,
                    'lastattempt_edt' => $lastattempt_edt,
                ], $sort, $direction, 0, 0);
                $userids = array_column($users, 'id');
            } else {
                $userids = optional_param_array('userids', [], PARAM_INT);
            }

            if (empty($userids)) {
                redirect($PAGE->url, 'Нет данных для выгрузки.', \core\output\notification::NOTIFY_ERROR);
            } else {
                $export = new \local_sibguexporttest\export();
                $export->set('courseid', $courseid);
                $export->set('userids', json_encode($userids));
                $export->save();

                $task = \local_sibguexporttest\task\local_sibguexporttest_create_zip::instance($export->get('id'));
                $task->set_userid($USER->id);
                \core\task\manager::queue_adhoc_task($task);

                redirect($PAGE->url, 'Формирования zip-архива поставлено в очередь.', \core\output\notification::NOTIFY_INFO);
            }
        }

        $output = $render->view([
            'group' => $group,
            'lastattempt_sdt' => $lastattempt_sdt,
            'lastattempt_edt' => $lastattempt_edt,
        ], $sort, $direction, $page, $perpage);
        break;
    case 'task':
        $PAGE->set_url('/local/sibguexporttest/index.php', ['courseid' => $courseid, 'action' => $action]);

        $title = get_string('navigation_task', 'local_sibguexporttest');
        /** @var \local_sibguexporttest\output\view_renderer $render */
        $render = $PAGE->get_renderer('local_sibguexporttest', 'task');
        $render->init_baseurl($PAGE->url);
        $output = $render->view($page, $perpage);
        break;
    default:
        $PAGE->set_url('/local/sibguexporttest/index.php', ['courseid' => $courseid, 'action' => $action]);
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