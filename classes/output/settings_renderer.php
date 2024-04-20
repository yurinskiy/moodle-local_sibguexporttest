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

use local_sibguexporttest\form\course_settings_form;
use local_sibguexporttest\settings;
use plugin_renderer_base;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_renderer extends plugin_renderer_base {

    /**
     * @return string HTML to output.
     */
    public function view(): string
    {
        global $PAGE, $COURSE;

        $settings = settings::get_by_course($COURSE->id);
        $context = $PAGE->context;
        $data = $settings->to_form();

        $output = '';

        $mform = new course_settings_form($PAGE->url->out_as_local_url(false), ['context' => $context, 'id' => $data->id, 'repeatno' => $data->test_repeats]);

        foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage'] as $field) {
            $data = file_prepare_standard_editor($data, $field, $mform->get_editor_options($field), $context, 'local_sibguexporttest', $field, $data->id);
        }

        $mform->set_data($data);

        if ($mform->is_cancelled()) {

        } else if ($data = $mform->get_data()) {
            if (!$data->id) {
                $settings->set('content', '');
                $settings->save();
            }

            foreach (['headerpage', 'footerpage', 'headerbodypage', 'footerbodypage'] as $field) {
                $data = file_postupdate_standard_editor($data, $field, $mform->get_editor_options($field), $context, 'local_sibguexporttest', $field, $data->id);
                unset($data->{$field.'_editor'});
                unset($data->{$field.'trust'});
            }

            $settings->from_form($data);

            $settings->save();

            redirect($PAGE->url);
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
}

function dump(...$vars)
{
    if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }

    echo '<pre>';

    if (array_key_exists(0, $vars) && 1 === count($vars)) {
        echo print_r($vars[0], 1);
    } else {
        foreach ($vars as $k => $v) {
            echo print_r($v, 1);
        }
    }
    echo '</pre>';
}

function dd(...$vars)
{
    if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }

    echo '<pre>';

    if (array_key_exists(0, $vars) && 1 === count($vars)) {
        echo print_r($vars[0], 1);
    } else {
        foreach ($vars as $k => $v) {
            echo print_r($v, 1);
        }
    }
    echo '</pre>';

    exit(1);
}