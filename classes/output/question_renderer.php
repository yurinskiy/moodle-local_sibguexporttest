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

        return $this->getContent($qa, $qtoutput, $options, $number);
    }

    protected function info(question_attempt $qa, $behaviouroutput, $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->mark_summary($qa, $behaviouroutput, $options);

        return $output;
    }

    protected function hasCorrectAnswer(\qtype_renderer $qtoutput): bool {
        return method_exists($qtoutput, 'correct_response') && (new \ReflectionMethod($qtoutput, 'correct_response'))->isPublic();
    }

    protected function getHeader(string $number = null): string {
        if ($number) {
            $output = \html_writer::tag('th', \html_writer::nonempty_tag('i', $number, ['class' => 'question-number']),
                ['scope' => 'row']);
            $output .= \html_writer::start_tag('td');
        } else {
            $output = \html_writer::start_tag('td', ['scope' => 'row', 'colspan' => 2]);
        }

        return $output;
    }

    protected function getContent(question_attempt $qa, $qtoutput, question_display_options $options, &$number): string {
        $hasNumber = true;

        switch (get_class($qtoutput)) {
            case 'qtype_description_renderer':
                $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');
                $hasNumber = false;
                break;
            case 'qtype_shortanswer_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_shortanswer_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_multianswer_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_multianswer_renderer($qa, $qtoutput, $options, $number);
                break;
            case 'qtype_essay_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_essay_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_match_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_match_renderer($qa, $qtoutput, $options);
                break;
            default:
                $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

                if ($this->hasCorrectAnswer($qtoutput)) {
                    $correctAnswer = html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer']);
                }
                break;
        }

        $content .= $correctAnswer ?? '';

        if (strpos($content, 'checked="checked"')) {
            $content = str_replace('checked="checked"', '', $content);
        }

        $output = $this->getHeader($hasNumber ? $number++ : '');
        $output .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() . ' ' . $qa->get_behaviour_name());
        $output .= $content;
        $output .= html_writer::end_tag('div');
        $output .= $this->getFooter();

        return $output;
    }

    protected function getFooter(): string {
        return html_writer::end_tag('td');
    }

    private function prepare_qtype_match_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        /** @var \simple_html_dom $html */
        $html = str_get_html($content);

        $questions = [];
        $answers = [];

        $rows = $html->find('.answer tr');

        foreach ($rows as $row) {
            /** @var \simple_html_dom_node $item */
            foreach ($row->find('td.text') as $item) {
                if (in_array($item->innertext(), $questions)) {
                    continue;
                }

                $questions[] = $item->innertext();
            }

            /** @var \simple_html_dom_node $option */
            foreach ($row->find('option') as $option) {
                if (empty($option->attr['value'])) {
                    continue;
                }

                if (in_array($option->innertext(), $answers)) {
                    continue;
                }

                $answers[] = $option->innertext();
            }
        }

        /** @var \simple_html_dom_node|null $table */
        $table = $html->find('.answer', 0);
        if ($table) {
            $table->find('tbody', 0)->remove();

            for ($i = 0; $i < max(count($questions), count($answers)); $i++) {
                /** @var \simple_html_dom_node $td1 */
                $td1 = $html->createElement('td', $questions[$i] ?? '');
                $td1->setAttribute('class', 'text');

                /** @var \simple_html_dom_node $td2 */
                $td2 = $html->createElement('td');
                $td2->setAttribute('style', 'width: 32px');

                /** @var \simple_html_dom_node $td3 */
                $td3 = $html->createElement('td', $answers[$i] ?? '');
                $td3->setAttribute('class', 'control');

                /** @var \simple_html_dom_node $tr */
                $tr = $html->createElement('tr');
                $tr->appendChild($td1);
                $tr->appendChild($td2);
                $tr->appendChild($td3);
                $table->appendChild($tr);
            }
        }

        return [
            $html->save(),
            html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer'])
        ];
    }

    private function prepare_qtype_essay_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        /** @var \simple_html_dom $html */
        $html = str_get_html($content);

        $deleted = [];

        foreach ($html->find('.answer') as $answer) {
            $deleted[] = $answer;
        }

        foreach ($html->find('.attachments') as $attachment) {
            $deleted[] = $attachment;
        }

        foreach ($deleted as $delete) {
            $delete->remove();
        }

        return [
            $html->save(),
            null
        ];
    }

    private function prepare_qtype_multianswer_renderer(question_attempt $qa, $qtoutput, question_display_options $options, $number): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        /** @var \simple_html_dom $html */
        $html = str_get_html($content);

        /** @var \simple_html_dom_node[] $questions */
        $questions = [];

        $deleted = [];

        $index = 0;

        /** @var \simple_html_dom_node|null $node */
        while ($node = $html->find('.subquestion', $index)) {
            $node->tag = 'div';
            $node->setAttribute('style', 'border: 1px solid black; display: inline-block; padding: 2px 10px');

            /** @var \simple_html_dom_node|null $select */
            $select = $node->find('select', 0);
            if ($select) {
                $select->tag = 'div';
                foreach ($select->find('option') as $option) {
                    $option->tag = 'span';
                    $option->innertext .= '<br>';

                    if (empty($option->attr['value'])) {
                        $deleted[] = $option;
                    }
                }
            }

            $node->find('label', 0)->innertext = 'Выберите ответ: <br>';
            $correct = $node->find('.feedbackspan', 0);

            $correct->innertext = mb_substr($correct->innertext, mb_stripos($correct->innertext, 'Правильный ответ:')) . '<br>';

            preg_match_all('/Правильный ответ:\s{0,}(.+)/', $correct->innertext, $matches);
            $correct->innertext = html_writer::nonempty_tag('div', sprintf(
                'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
                \implode(' ', $matches[1] ?? [])
            ), ['class' => 'rightanswer']);

            $index++;

            $questions[] = clone $node;
            $node->innertext = $number . '.' . count($questions);
        }

        foreach ($deleted as $delete) {
            $delete->remove();
        }

        $content = $html->save();
        foreach ($questions as $key => $question) {
            $content .= \html_writer::end_tag('tr');
            $content .= \html_writer::end_tag('table');
            $content .=  \html_writer::start_tag('table', ['class' => 'questions ']);
            $content .= \html_writer::start_tag('tr');
            $content .= $this->getFooter();
            $content .= $this->getHeader($number . '.' . ($key+1));
            $content .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() . ' ' . $qa->get_behaviour_name());
            $content .= $question->text();
            $content .= html_writer::end_tag('div');
        }

        return [
            $content,
            null
        ];
    }

    private function prepare_qtype_shortanswer_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock form-inline">')) . '</div>';

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }
}
