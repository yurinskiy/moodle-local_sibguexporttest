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

use local_sibguexporttest\config;
use local_sibguexporttest\debug;
use local_sibguexporttest\form\course_config_form;
use local_sibguexporttest\form\course_settings_form;
use local_sibguexporttest\generator_v2;
use local_sibguexporttest\settings;
use plugin_renderer_base;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket_renderer extends plugin_renderer_base {

    /**
     * @return string HTML to output.
     */
    public function view(): string
    {
        global $PAGE, $COURSE, $USER;

        $config = config::get_by_course($COURSE->id);

        $context = $PAGE->context;

        $output = <<<HTML
<style> 
.editor_atto_content {
    font-family: times new roman,times,serif;;
}
.editor_atto_content p {
    margin-top: 0!important;
    margin-bottom: 0!important;
}
</style>
HTML;

        $mform = new course_config_form($PAGE->url->out_as_local_url(false), ['id' => $config->get('id'), 'context' => $context, 'repeatno' => $config->get_repeatno(), 'submitlabel' => 'Сгенерировать ЭБ']);
        $config->set_form($mform);

        if ($mform->is_cancelled()) {
            redirect($PAGE->url);
        } else if ($data = $mform->get_data()) {
            $config->handle_form($mform);
            $config->save();

            $this->createtask($config, $data);
        } else {
            $output .= $this->moodleform($mform);
        }

        return $output;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param \moodleform $mform
     * @return string HTML
     */
    protected function moodleform(\moodleform $mform) {
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    private function createtask(config $config, $data)
    {
        global $PAGE, $USER;

        $export = new \local_sibguexporttest\export();
        $export->set('type', 'ticket');
        $export->set('courseid', $config->get('courseid'));
        $export->set('userids', 0);
        $export->set('description', $config->get('id').'|'.$data->count_ticket);
        $export->save();

        $task = \local_sibguexporttest\task\local_sibguexporttest_create_zip::instance($export->get('id'));
        $task->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($task);

        $message = 'Генерация ЭБ поставлена в очередь.';

        redirect($PAGE->url, $message, \core\output\notification::NOTIFY_INFO);
    }
}
