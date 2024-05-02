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

use core_question\output\question_version_info;
use course_enrolment_manager;
use html_writer;
use local_sibguexporttest\debug;
use local_sibguexporttest\settings;
use moodle_url;
use plugin_renderer_base;
use qbehaviour_renderer;
use qtype_renderer;
use question_attempt;
use question_display_options;
use quiz_attempt;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_renderer extends plugin_renderer_base {
    public function get_head_js($customjs = '') {
        return <<<HTML
<script>
    function substitutePdfVariables() {

        function getParameterByName(name) {
            var match = RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
            return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
        }

        function substitute(name) {
            var value = getParameterByName(name);
            var elements = document.getElementsByClassName(name);

            for (var i = 0; elements && i < elements.length; i++) {
                elements[i].textContent = value;
            }
        }

        ['frompage', 'topage', 'page', 'webpage', 'section', 'subsection', 'subsubsection']
            .forEach(function(param) {
                substitute(param);
            });
    }
    $customjs
</script>
HTML;

    }

    public function get_head_css($customcss = '') {
        global $CFG;

        $times_new_roman = file_get_contents($CFG->dirroot . '/local/sibguexporttest/vendor/Times New Roman.ttf') ?? '';
        $times_new_roman_base64 = base64_encode($times_new_roman);

        $font = <<<CSS
@font-face {
    font-family: "Times New Roman";
    src: url(data:font/truetype;charset=utf-8;base64,$times_new_roman_base64);
}

body {
    font-family: "Times New Roman",sans-serif;
}

* {
    font-size: 14pt;
}
CSS;

        $normalize = file_get_contents($CFG->dirroot . '/local/sibguexporttest/vendor/normalize.css') ?? '';
        $pageBreak = <<<CSS
table {
    border-collapse: collapse; 
}
table tr, table td, table th {
    page-break-inside: avoid !important;
    break-inside: avoid-page !important; 
}
.page-break {
  height: 20px;
  page-break-before: always; 
  break-before: page;
}
CSS;


        return <<<HTML
<style>$font</style>
<style>$normalize</style>
<style>
    $pageBreak
    $customcss
</style>
HTML;
    }

    public function get_html($content, $title = '', $customcss = '', $customjs = '') {
        $output = '';
        $output .= html_writer::start_tag('!DOCTYPE html');
        $output .= html_writer::start_tag('html', ['lang' => 'ru']);
        $output .= html_writer::start_tag('head');
        $output .= html_writer::tag('title', $title);
        $output .= html_writer::empty_tag('meta', ['http-equiv' => 'Content-Type', 'content' => 'text/html; charset=UTF-8']);
        $output .= $this->get_head_js($customjs);
        $output .= $this->get_head_js($customjs);
        $output .= $this->get_head_css($customcss);
        $output .= html_writer::end_tag('head');
        $output .= html_writer::start_tag('body', ['onload' => 'substitutePdfVariables()']);
        $output .= $content;
        $output .= html_writer::end_tag('body');
        $output .= html_writer::end_tag('html');

        return $output;
    }
}