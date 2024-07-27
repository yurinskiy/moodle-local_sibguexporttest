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
require_once(__DIR__ . '/../../simple_html_dom.php');

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

    public function question_gen(question_attempt $qa, question_display_options $options, &$number) {
        global $PAGE;

        $behaviouroutput = $qa->get_behaviour()->get_renderer($PAGE);
        $qtoutput = $qa->get_question()->get_renderer($PAGE);

        // If not already set, record the questionidentifier.
        $options = clone($options);
        $options->marks = question_display_options::MAX_ONLY;

        if ($this->hasCorrectAnswer($qtoutput)) {
            $output = \html_writer::tag('th', \html_writer::nonempty_tag('i', $number, ['class' => 'question-number']), ['scope' => 'row']);
            $output .= \html_writer::start_tag('td');
            $number++;
        } else {
            $output = \html_writer::start_tag('td', ['scope' => 'row', 'colspan' => 2]);
        }

        $output .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() .' '.$qa->get_behaviour_name());

        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        switch (get_class($qtoutput)) {
            case 'qtype_shortanswer_renderer':
                $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock form-inline">')).'</div>';
                break;
            case 'qtype_multianswer_renderer':
                /** @var \simple_html_dom $html */
                $html = str_get_html($content);

                $deleted = [];

                $index = 0;

                /** @var \simple_html_dom_node|null $node */
                while ($node = $html->find('.subquestion', $index)) {
                    $node->tag = 'div';
                    $node->setAttribute('style', 'border: 1px solid black; display: inline-block');

                    /** @var \simple_html_dom_node $select */
                    $select = $node->find('select', 0);
                    $select->tag = 'div';
                    foreach ($select->find('option') as $option) {
                        $option->tag = 'span';
                        $option->innertext .= '<br>';

                        if (empty($option->attr['value'])) {
                            $deleted[] = $option;
                        }
                    }

                    $node->find('label', 0)->innertext = 'Выберите ответ: ';
                    $correct = $node->find('.feedbackspan', 0);

                    $correct->innertext = mb_substr($correct->innertext, mb_stripos($correct->innertext, 'Правильный ответ:')).'<br>';

                    $index++;
                }

                foreach ($deleted as $delete) {
                    $delete->remove();
                }

                $content = $html->save();
                break;
        }

        if ($this->hasCorrectAnswer($qtoutput)) {
            $content .= html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer']);
        }

        if (strpos($content, 'checked="checked"')) {
            $content = str_replace('checked="checked"', '', $content);
        }

        $output .= $content;

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('td');

        return $output;
    }

    protected function info(question_attempt $qa, $behaviouroutput, $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->mark_summary($qa, $behaviouroutput, $options);

        return $output;
    }

    protected function hasCorrectAnswer(\qtype_renderer $qtoutput): bool
    {
        return method_exists($qtoutput, 'correct_response') && (new \ReflectionMethod($qtoutput, 'correct_response'))->isPublic();
    }
}