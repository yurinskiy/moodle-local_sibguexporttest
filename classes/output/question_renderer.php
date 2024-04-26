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
require_once($CFG->libdir . '/questionlib.php');

use html_writer;
use local_sibguexporttest\debug;
use question_attempt;
use question_display_options;

/**
 * Renderer class for 'local_sibguexporttest' component.
 *
 * @package   local_sibguexporttest
 * @copyright 2024, Yuriy Yurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_renderer extends \core_question_renderer {

    public function question_gen(question_attempt $qa, question_display_options $options, $number) {
        global $PAGE;

        $behaviouroutput = $qa->get_behaviour()->get_renderer($PAGE);
        $qtoutput = $qa->get_question()->get_renderer($PAGE);

        // If not already set, record the questionidentifier.
        $options = clone($options);
        $options->marks = question_display_options::MAX_ONLY;

        $output = '';
        $output .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() .' '.$qa->get_behaviour_name());

        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');
        $content .= html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer']);
        if (strpos($content, 'checked="checked"')) {
            $content = str_replace('checked="checked"', '', $content);
        }

        $output .= $content;

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    protected function info(question_attempt $qa, $behaviouroutput, $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->mark_summary($qa, $behaviouroutput, $options);

        return $output;
    }
}