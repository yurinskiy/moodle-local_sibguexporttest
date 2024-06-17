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

namespace local_sibguexporttest\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use context_course;
use course_enrolment_manager;
use html_writer;
use local_sibguexporttest\debug;
use local_sibguexporttest\settings;
use moodle_url;
use plugin_renderer_base;
use quiz_attempt;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_renderer extends plugin_renderer_base {

    private ?moodle_url $baseurl = null;
    private ?int $courseid = null;
    private ?int $roleid = null;

    private ?settings $settings = null;
    private array $quizzes = [];

    public function init_manager() {
        global  $COURSE, $DB;

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $this->courseid = $COURSE->id;
        $this->roleid = $studentrole->id;

        $this->settings = settings::get_by_course($COURSE->id);

        $contents = $this->settings->get_contents();
        $contents = array_column($contents, 'order','id');
        $this->quizzes = $DB->get_records_list('quiz', 'id', array_column($contents, 'id'));
        usort($this->quizzes, fn ($a, $b) => ((int)$contents[$a->id]) <=> ((int)$contents[$b->id]));
    }

    public function init_baseurl(moodle_url $url) {
        $this->baseurl = $url;
    }

    /**
     * @return string HTML to output.
     */
    public function view(array $filter = [], string $sort = 'lastcourseaccess', $direction='ASC', $page = 0, $perpage = 25): string {
        $output = $this->get_download_all();
        $output .= $this->filter();

        $users = $this->get_users($filter, $sort, $direction, $page, $perpage);

        $output .= $this->get_table($users, $sort, $direction);
        $output .= $this->get_paginator($filter, $page, $perpage);

        return $output;
    }

    public function filter():string {
        global $COURSE, $DB;

        $output = html_writer::start_tag('form', ['method' => 'get', 'class' => 'form-inline']);


        $output .= html_writer::start_tag('div', ['class' => 'm-b-1']);
        $output .= html_writer::label('Учебная группа', 'group', ['class' => 'm-r-1']);

        $groups = groups_get_all_groups($COURSE->id);
        foreach ($groups as $group) {
            $usercount = $DB->count_records('groups_members', array('groupid' => $group->id));
            $groupname = format_string($group->name) . ' (' . $usercount . ')';

            $groupoptions[$group->id] = s($groupname);
        }

        $selectedgroup = $this->baseurl->get_param('group') ?? '';
        $output .= html_writer::select($groupoptions ?? [], 'group', $selectedgroup, array('' => 'choosedots'), ['class' => 'form-control m-r-1']);
        $output .= html_writer::empty_tag('input', ['class' => 'btn btn-secondary', 'type' => 'submit', 'value' => 'Поиск']);

        $output .= html_writer::input_hidden_params($this->baseurl, ['group']);

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    public function get_download_all(): string {
        $output = html_writer::start_tag('div', ['class' => 'py-2']);
        $url = new moodle_url('/local/sibguexporttest/generate.php', ['action' => 'all', 'courseid' => $this->baseurl->param('courseid')]);
        $output .= html_writer::link($url, 'Скачать все билеты', ['class' => 'btn btn-primary']);

        return $output;
    }

    public function get_table(array $users = [], string $sort = 'lastcourseaccess', $direction='ASC') {
        $output = html_writer::start_tag('table', ['class' => 'generaltable table-hover table-bordered']) . "\n";
        $output .= $this->get_head($sort, $direction);
        $output .= $this->get_body($users);
        $output .= html_writer::end_tag('table') . "\n";

        return $output;
    }

    public function get_paginator(array $filter = [], $page = 0, $perpage = 25) {
        $total = $this->get_total_users($filter);

        return $this->output->paging_bar($total, $page, $perpage, $this->get_baseurl());
    }

    protected function column_sort($name, $title,  $sort, $dir) {
        if ($name !== $sort) {
            $newDir = 'ASC';
        } elseif ($dir !== 'ASC') {
            $newDir = 'ASC';
        } else {
            $newDir = 'DESC';
        }


        $output = html_writer::link(new moodle_url($this->baseurl, [
            'sort' => $name, 'dir' => $newDir
        ]), $title);
        $output .= $this->get_icon_asc($name, $sort, $dir);

        return $output;
    }

    protected function get_icon_asc($name, $sort, $dir) {
        if ($name !== $sort) return '';

        if ($dir !== 'ASC') {
            return '<i class="icon fa fa-sort-desc fa-fw iconsort" title="По возрастанию" role="img" aria-label="По возрастанию"></i>';
        }

        return '<i class="icon fa fa-sort-asc fa-fw iconsort" title="По убыванию" role="img" aria-label="По убыванию"></i>';
    }

    private function get_head(string $sort = 'lastcourseaccess', $direction='ASC') {
        $output = html_writer::start_tag('thead', array()) . "\n";
        $output .= html_writer::start_tag('tr', array()) . "\n";

        $attrcell = ['scope' => 'col', 'class' => 'cell', 'style' => 'vertical-align: top'];

        $output .= html_writer::tag('th', '', array_merge($attrcell, ['rowspan' => 2])) . "\n";

        $output .= html_writer::start_tag('th', array_merge($attrcell, ['rowspan' => 2]));
        $output .= $this->column_sort('fio', \implode(' ', [get_string('lastname'), get_string('firstname'),]), $sort, $direction);
        $output .= html_writer::end_tag('th') . "\n";

        $output .= html_writer::start_tag('th', array_merge($attrcell, ['rowspan' => 2]));
        $output .= $this->column_sort('email', get_string('email'), $sort, $direction);
        $output .= html_writer::end_tag('th') . "\n";

        $output .= html_writer::start_tag('th', array_merge($attrcell, ['rowspan' => 2]));
        $output .= $this->column_sort('lastcourseaccess',  get_string('lastcourseaccess'), $sort, $direction);
        $output .= html_writer::end_tag('th') . "\n";


        $suboutput = html_writer::start_tag('tr', array()) . "\n";
        foreach ($this->quizzes as $quiz) {
            $output .= html_writer::tag('th', $quiz->name, array_merge($attrcell, ['colspan' => 2])) . "\n";
            $suboutput .= html_writer::tag('td', 'Дата выполнения', $attrcell) . "\n";
            $suboutput .= html_writer::tag('td', 'Состояние', $attrcell) . "\n";
        }
        $suboutput .= html_writer::end_tag('tr') . "\n";

        $output .= html_writer::tag('th', get_string('actions'), array_merge($attrcell, ['rowspan' => 2])) . "\n";

        $output.= $suboutput . "\n";
        $output .= html_writer::end_tag('tr') . "\n";
        $output .= html_writer::end_tag('thead') . "\n";

        return $output;
    }

    private function get_body(array $users = []) {
        $output = html_writer::start_tag('tbody') . "\n";

        foreach ($users as $user) {
            $output .= html_writer::start_tag('tr') . "\n";
            $output .= html_writer::tag('th', $this->col_select($user), ['scope' => 'row']) . "\n";

            $url = new moodle_url('/user/view.php', ['id' => $user->id]);
            $output .= html_writer::tag('td', html_writer::link($url, \implode(' ', [$user->lastname, $user->firstname]))) . "\n";
            $output .= html_writer::tag('td', $user->email) . "\n";
            $output .= html_writer::tag('td', userdate($user->lastcourseaccess)) . "\n";

            foreach ($this->quizzes as $quiz) {
                $attempts = quiz_get_user_attempts($quiz->id, $user->id, 'all', true);
                $lastattempt = end($attempts);

                if (!empty($lastattempt->state)) {
                    $output .= html_writer::tag('td', userdate($lastattempt->timefinish)) . "\n";
                    $output .= html_writer::tag('td', quiz_attempt::state_name($lastattempt->state)) . "\n";
                } else {
                    $output .= html_writer::tag('td', '-', ['colspan' => 2]) . "\n";
                }
            }
            $actions = [];
            $url = new moodle_url('/local/sibguexporttest/generate.php', ['action' => 'one', 'courseid' => $this->baseurl->param('courseid'), 'userid' => $user->id]);
            $actions[] = html_writer::link($url, 'Скачать') . "\n";
            $url = new moodle_url('/local/sibguexporttest/generate.php', ['action' => 'one', 'courseid' => $this->baseurl->param('courseid'), 'userid' => $user->id, 'debug' => true]);
            $actions[] = html_writer::link($url, 'Отладочный файл');
            $output .= html_writer::tag('td', \implode(PHP_EOL, $actions)) . "\n";
            $output .= html_writer::end_tag('tr') . "\n";
        }

        $output .= html_writer::end_tag('tbody') . "\n";

        return $output;
    }

    private function get_baseurl() {
        if (!$this->baseurl) {
            throw new \coding_exception('Не инициализирован компонент базовой ссылки');
        }

        return $this->baseurl;
    }

    private function get_users(array $filter = [], string $sort = 'lastcourseaccess', $direction='ASC', $page = 0, $perpage = 25) {
        global $DB;

        if ($direction !== 'ASC') {
            $direction = 'DESC';
        }

        $params['courseid'] = $this->courseid;
        $params['roleid'] = $this->roleid;

        $context = context_course::instance($params['courseid']);
        $contextids = $context->get_parent_context_ids();
        $contextids[] = $context->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $params += $contextparams;

        $sql = <<<SQL
SELECT DISTINCT u.id, u.email, u.picture, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.imagealt, trim(concat(u.lastname, ' ', u.firstname)) AS fio, COALESCE(ul.timeaccess, 0) AS lastcourseaccess
  FROM {user} u
  JOIN {enrol} e ON (e.courseid = :courseid) 
  JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid = e.id)
  LEFT JOIN {user_lastaccess} ul ON (ul.courseid = e.courseid AND ul.userid = u.id)
WHERE (SELECT COUNT(1) FROM {role_assignments} ra 
                       WHERE ra.userid = u.id
                         AND ra.roleid = :roleid AND ra.contextid $contextsql) > 0
SQL;

        if (!empty($filter['group'])) {
            $sql .= <<<SQL
 AND (SELECT COUNT(1) 
     FROM {groups_members} gm 
         JOIN {groups} g ON (g.id = gm.groupid)
        WHERE (u.id = gm.userid AND g.courseid = e.courseid) 
          AND gm.groupid = :groupid) > 0
SQL;
            $params['groupid'] = $filter['group'];
        }

        $sql .= " ORDER BY $sort $direction";

        return $DB->get_records_sql($sql, $params, $page*$perpage, $perpage);
    }

    private function get_total_users(array $filter = []) {
        global $DB;

        $params['courseid'] = $this->courseid;
        $params['roleid'] = $this->roleid;

        $context = context_course::instance($params['courseid']);
        $contextids = $context->get_parent_context_ids();
        $contextids[] = $context->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $params += $contextparams;

        $sql = <<<SQL
SELECT COUNT(DISTINCT u.id)
  FROM {user} u
  JOIN {enrol} e ON (e.courseid = :courseid) 
  JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid = e.id)
WHERE (SELECT COUNT(1) FROM {role_assignments} ra 
                       WHERE ra.userid = u.id
                         AND ra.roleid = :roleid AND ra.contextid $contextsql) > 0
SQL;

        if (!empty($filter['group'])) {
            $sql .= <<<SQL
 AND (SELECT COUNT(1) 
     FROM {groups_members} gm 
         JOIN {groups} g ON (g.id = gm.groupid)
        WHERE (u.id = gm.userid AND g.courseid = e.courseid) 
          AND gm.groupid = :groupid) > 0
SQL;
            $params['groupid'] = $filter['group'];
        }

        return (int)$DB->count_records_sql($sql, $params);
    }

    private function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('participants-table', false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }
}