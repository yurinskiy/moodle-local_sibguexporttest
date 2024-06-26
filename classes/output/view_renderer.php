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

use context_course;
use html_writer;
use local_sibguexporttest\settings;
use moodle_url;
use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->libdir . '/form/dateselector.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_renderer extends plugin_renderer_base {

    private ?moodle_url $baseurl = null;
    private ?context_course $context = null;
    private ?int $courseid = null;
    private ?\stdClass $course = null;
    private ?int $roleid = null;

    private ?settings $settings = null;
    private array $quizzids = [];
    private array $quizzes = [];

    public function init_manager() {
        global $COURSE, $DB;

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $this->context = context_course::instance($COURSE->id);
        $this->courseid = $COURSE->id;
        $this->course = $DB->get_record('course', array('id' => $COURSE->id), '*', MUST_EXIST);
        $this->roleid = $studentrole->id;

        $this->settings = settings::get_by_course($COURSE->id);

        $contents = $this->settings->get_contents();
        $contents = array_column($contents, 'order','id');

        $this->quizzids = array_keys($contents);

        $this->quizzes = $DB->get_records_list('quiz', 'id', array_keys($contents));
        usort($this->quizzes, fn ($a, $b) => ((int)$contents[$a->id]) <=> ((int)$contents[$b->id]));

    }

    public function init_baseurl(moodle_url $url) {
        $this->baseurl = $url;
    }

    /**
     * @return string HTML to output.
     */
    public function view(array $filter = [], string $sort = 'lastcourseaccess', $direction='ASC', $page = 0, $perpage = 25): string {

        ['group' => $selectedgroup, 'lastattempt_sdt' => $lastattempt_sdt, 'lastattempt_edt' => $lastattempt_edt] = $filter;

        $output = html_writer::start_tag('form', ['method' => 'get']);

        $output .= $this->dateselector('lastattempt_sdt', '&nbsp;&nbsp;Дата попытки с', $lastattempt_sdt);
        $output .= $this->dateselector('lastattempt_edt', '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; по', $lastattempt_edt);

        $output .= $this->select_group($selectedgroup);

        $buttons = [];
        $buttons[] = html_writer::empty_tag('input', ['class' => 'btn btn-primary mr-1', 'type' => 'submit', 'name' => 'download_list', 'value' => 'Скачать список билетов', 'onclick' => 'return $(this).closest(\'div\').find(\'input[name=filter]\').hasClass(\'d-none\') || confirm(\'Нажмите кнопку отфильтровать, чтобы корректно выгрузить список билетов. Продолжить все равно?\');']);
        $buttons[] = html_writer::empty_tag('input', ['class' => 'btn btn-primary d-none', 'type' => 'submit', 'name' => 'filter', 'value' => 'Отфильтровать']);
        $output .= html_writer::div(implode('', $buttons), 'groupselector form-inline');

        $users = $this->get_users($filter, $sort, $direction, $page, $perpage);

        $output .= $this->get_paginator($filter, $page, $perpage);
        $output .= html_writer::div($this->get_table($users, $sort, $direction), 'no-overflow');
        $output .= $this->get_paginator($filter, $page, $perpage);

        $output .= $this->select_perpage($perpage);

        $output .= html_writer::input_hidden_params($this->baseurl, [
            'group', 'perpage',
            'lastattempt_sdt[day]', 'lastattempt_sdt[month]', 'lastattempt_sdt[year]', 'lastattempt_sdt[enabled]',
            'lastattempt_edt[day]', 'lastattempt_edt[month]', 'lastattempt_edt[year]', 'lastattempt_edt[enabled]'
        ]);

        $buttons = [];
        $buttons[] = html_writer::empty_tag('input', ['class' => 'btn btn-primary mr-1', 'type' => 'submit', 'name' => 'download_all', 'value' => 'Скачать все билеты', 'onclick' => 'return $(this).closest(\'div\').find(\'input[name=filter]\').hasClass(\'d-none\') || confirm(\'Нажмите кнопку отфильтровать, чтобы корректно выгрузить билеты. Продолжить все равно?\');']);
        $buttons[] = html_writer::empty_tag('input', ['class' => 'btn btn-primary mr-1 d-none', 'type' => 'submit', 'name' => 'download_selected', 'value' => 'Скачать выбранные билеты']);
        $output .= html_writer::div(implode('', $buttons), 'groupselector form-inline');

        $output .= html_writer::end_tag('form');

        return $output;
    }

    protected function select_group($selected = 0): string
    {
        global $USER;
        if (!$groupmode = $this->course->groupmode) {
            return '';
        }

        $aag = has_capability('moodle/site:accessallgroups', $this->context);
        $usergroups = array();
        if ($groupmode == VISIBLEGROUPS or $aag) {
            $allowedgroups = groups_get_all_groups($this->course->id, 0, $this->course->defaultgroupingid);
            // Get user's own groups and put to the top.
            $usergroups = groups_get_all_groups($this->course->id, $USER->id, $this->course->defaultgroupingid);
        } else {
            $allowedgroups = groups_get_all_groups($this->course->id, $USER->id, $this->course->defaultgroupingid);
        }

        $groupsmenu = array();
        if (!$allowedgroups or $groupmode == VISIBLEGROUPS or $aag) {
            $groupsmenu[0] = get_string('allparticipants');
        }

        $groupsmenu += groups_sort_menu_options($allowedgroups, $usergroups);

        if ($groupmode == VISIBLEGROUPS) {
            $grouplabel = get_string('groupsvisible');
        } else {
            $grouplabel = get_string('groupsseparate');
        }

        if ($aag and $this->course->defaultgroupingid) {
            if ($grouping = groups_get_grouping($this->course->defaultgroupingid)) {
                $grouplabel = $grouplabel . ' (' . format_string($grouping->name) . ')';
            }
        }

        if (count($groupsmenu) == 1) {
            $groupname = reset($groupsmenu);
            $output = $grouplabel.': '.$groupname;
        } else {
            $output = html_writer::label($grouplabel, 'menugroup');

            $output .= html_writer::select($groupsmenu, 'group', $selected, false, ['class' => 'form-control m-r-1', 'onchange' => '$(this.form).find(\'input[name=filter]\').removeClass(\'d-none\')']);
        }

        return html_writer::div($output, 'my-2 form-inline');
    }

    protected function select_perpage($selected = 25): string
    {
        $options = [25, 50, 100, 250];
        $output = html_writer::label(get_string('perpage', 'moodle'), 'menuperpage');

        $output .= html_writer::select(array_combine($options, $options), 'perpage', $selected, false, ['class' => 'form-control m-r-1', 'onchange' => 'this.form.submit()']);

        return html_writer::div($output, 'groupselector form-inline');
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
        $output .= $this->column_sort('lastattempt',  'Дата попытки', $sort, $direction);
        $output .= html_writer::end_tag('th') . "\n";

        $output .= html_writer::start_tag('th', array_merge($attrcell, ['rowspan' => 2]));
        $output .= $this->column_sort('lastcourseaccess',  get_string('lastcourseaccess'), $sort, $direction);
        $output .= html_writer::end_tag('th') . "\n";


        $suboutput = html_writer::start_tag('tr', array()) . "\n";
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
            $output .= html_writer::tag('td', userdate($user->lastattempt)) . "\n";
            $output .= html_writer::tag('td', userdate($user->lastcourseaccess)) . "\n";

            $actions = [];
            $url = new moodle_url('/local/sibguexporttest/generate.php', ['action' => 'one', 'courseid' => $this->baseurl->param('courseid'), 'userid' => $user->id]);
            $actions[] = html_writer::link($url, 'Скачать') . "\n";
            //$url = new moodle_url('/local/sibguexporttest/generate.php', ['action' => 'one', 'courseid' => $this->baseurl->param('courseid'), 'userid' => $user->id, 'debug' => true]);
            //$actions[] = html_writer::link($url, 'Отладочный файл');
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

    public function get_users(array $filter = [], string $sort = 'lastcourseaccess', $direction='ASC', $page = 0, $perpage = 25) {
        global $DB;

        if ($direction !== 'ASC') {
            $direction = 'DESC';
        }

        $params['courseid'] = $this->courseid;
        $params['roleid'] = $this->roleid;
        $params['state1'] = \quiz_attempt::FINISHED;
        $params['state2'] = \quiz_attempt::ABANDONED;

        $contextids = $this->context->get_parent_context_ids();
        $contextids[] = $this->context->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);

        $params += $contextparams;

        list($quizzsql, $quizzparams) = $DB->get_in_or_equal($this->quizzids, SQL_PARAMS_NAMED);
        $params += $quizzparams;

        $sql = <<<SQL
SELECT DISTINCT u.id, u.email, u.picture, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.imagealt, 
                trim(concat(u.lastname, ' ', u.firstname)) AS fio, 
                COALESCE(ul.timeaccess, 0) AS lastcourseaccess,
                (SELECT MAX(case when qa.timefinish > 0 then qa.timefinish else qa.timemodified end) 
                 FROM {quiz_attempts} qa 
                 WHERE qa.state IN (:state1, :state2) 
                   AND qa.quiz $quizzsql AND qa.userid = u.id) AS lastattempt
  FROM {user} u
  JOIN {enrol} e ON (e.courseid = :courseid) 
  JOIN {user_enrolments} ue ON (ue.userid = u.id  AND ue.enrolid = e.id)
  LEFT JOIN {user_lastaccess} ul ON (ul.courseid = e.courseid AND ul.userid = u.id)
WHERE (SELECT COUNT(1) FROM {role_assignments} ra 
                       WHERE ra.userid = u.id
                         AND ra.roleid = :roleid AND ra.contextid $contextsql) > 0
SQL;

        list($quizzsql2, $quizzparams2) = $DB->get_in_or_equal($this->quizzids, SQL_PARAMS_NAMED);
        $params += $quizzparams2;
        $sql .= " AND (SELECT MAX(case when qa.timefinish > 0 then qa.timefinish else qa.timemodified end) FROM {quiz_attempts} qa WHERE qa.state IN (:state1w, :state2w) AND qa.quiz $quizzsql2 AND qa.userid = u.id) > 0";
        $params['state1w'] = \quiz_attempt::FINISHED;
        $params['state2w'] = \quiz_attempt::ABANDONED;

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

        if (!empty($filter['lastattempt_sdt']['enabled'])) {
            list($lastattempt_sdtsql, $lastattempt_sdtparams) = $DB->get_in_or_equal($this->quizzids, SQL_PARAMS_NAMED);
            $params += $lastattempt_sdtparams;

            $sql .= " AND (SELECT MAX(qa.timefinish) FROM {quiz_attempts} qa WHERE qa.state = 'finished' AND qa.quiz $lastattempt_sdtsql AND qa.userid = u.id) >= :lastattempt_sdt";
            $params['lastattempt_sdt'] = \DateTime::createFromFormat('Y-m-d', implode('-', [
                $filter['lastattempt_sdt']['year'] ?? '1970', $filter['lastattempt_sdt']['month'] ?? '01', $filter['lastattempt_sdt']['day'] ?? '01'
            ]))->setTime(0,0)->getTimestamp();
        }

        if (!empty($filter['lastattempt_edt']['enabled'])) {
            list($lastattempt_edtsql, $lastattempt_edtparams) = $DB->get_in_or_equal($this->quizzids, SQL_PARAMS_NAMED);
            $params += $lastattempt_edtparams;

            $sql .= " AND (SELECT MAX(qa.timefinish) FROM {quiz_attempts} qa WHERE qa.state = 'finished' AND qa.quiz $lastattempt_edtsql AND qa.userid = u.id) <= :lastattempt_edt";
            $params['lastattempt_edt'] = \DateTime::createFromFormat('Y-m-d', implode('-', [
                $filter['lastattempt_edt']['year'] ?? '1970', $filter['lastattempt_edt']['month'] ?? '01', $filter['lastattempt_edt']['day'] ?? '01'
            ]))->setTime(0,0)->getTimestamp();
        }

        $sql .= " ORDER BY $sort $direction";

        return $DB->get_records_sql($sql, $params, $page*$perpage, $perpage);
    }

    private function get_total_users(array $filter = []) {
        global $DB;

        $params['courseid'] = $this->courseid;
        $params['roleid'] = $this->roleid;

        $contextids = $this->context->get_parent_context_ids();
        $contextids[] = $this->context->id;
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

        return html_writer::checkbox(
            'userids[]',
            $data->id,
            false,
            '',
            [
                'class' => 'usercheckbox m-1',
                'onchange' => '$(this).closest(\'table\').find(\'input[type=checkbox]:checked\').length > 0 ? $(this.form).find(\'input[name=download_selected]\').removeClass(\'d-none\'): $(this.form).find(\'input[name=download_selected]\').addClass(\'d-none\')'
            ]);
    }

    private function dateselector(string $name, string $title, array $selected = null) {
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $dateformat = $calendartype->get_date_order($calendartype->get_min_year(), $calendartype->get_max_year());

        if (!$selected || !$selected['enabled']) {
            $selected = [
                'enabled' => false,
                'day' => date_create()->format('d'),
                'month' => date_create()->format('m'),
                'year' => date_create()->format('Y'),
            ];
        }

        $output = html_writer::span($title);

        foreach ($dateformat as $key => $value) {

            $output .= html_writer::select($value, $name.'['.$key.']', (int) $selected[$key], false, ['class' => 'ml-2', 'disabled' => !$selected['enabled'], 'onchange' => '$(this.form).find(\'input[name=filter]\').removeClass(\'d-none\')']);
        }

        $output .= html_writer::tag('i', '', ['class' => 'icon fa fa-calendar fa-fw ml-2', 'title' => 'Календарь', 'role' => 'img']);

        $output .= html_writer::checkbox($name.'[enabled]', true, $selected['enabled'], 'Включить', ['onchange'=>'$(this.form).find(\'input[name=filter]\').removeClass(\'d-none\');$(this).closest(\'div\').find(\'select\').prop(\'disabled\', !this.checked)']);

        return html_writer::div($output, 'my-2 form-inline');
    }
}