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
use mikehaertl\shellcommand\Command;
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

    public function question_gen(question_attempt $qa, question_display_options $options, &$number, bool $showrightanswer = true) {
        global $PAGE;

        $behaviouroutput = $qa->get_behaviour()->get_renderer($PAGE);
        $qtoutput = $qa->get_question()->get_renderer($PAGE);

        // If not already set, record the questionidentifier.
        $options = clone($options);
        $options->marks = question_display_options::MAX_ONLY;

        return $this->getContent($qa, $qtoutput, $options, $number, $showrightanswer);
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

    protected function getContent(question_attempt $qa, $qtoutput, question_display_options $options, &$number, bool $showrightanswer = true): string {
        $hasNumber = true;

        //if ($number === 8) {
        //    echo get_class($qtoutput); exit;
        //}

        switch (get_class($qtoutput)) {
            case 'qtype_description_renderer':
                $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');
                $hasNumber = false;
                break;
            case 'qtype_shortanswer_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_shortanswer_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_multianswer_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_multianswer_renderer($qa, $qtoutput, $options, $number, $showrightanswer);
                break;
            case 'qtype_essay_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_essay_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_match_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_match_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_calculated_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_calculated_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_calculatedsimple_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_calculatedsimple_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_ddwtos_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_ddwtos_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_gapselect_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_gapselect_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_multichoice_single_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_multichoice_single_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_numerical_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_numerical_renderer($qa, $qtoutput, $options);
                break;
            case 'qtype_truefalse_renderer':
                [$content, $correctAnswer] = $this->prepare_qtype_truefalse_renderer($qa, $qtoutput, $options);
                break;
            default:
                $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

                if ($this->hasCorrectAnswer($qtoutput)) {
                    $correctAnswer = html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer']);
                }
                break;
        }

        if ($showrightanswer) {
            $content .= $correctAnswer ?? '';
        }

        if (strpos($content, 'checked="checked"')) {
            $content = str_replace('checked="checked"', '', $content);
        }

        $output = $this->getHeader($hasNumber ? $number++ : '');
        $output .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() . ' ' . $qa->get_behaviour_name());
        $output .= $this->convertLatexToSvgInHtml($content);
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
        $content = html_writer::div(
            html_writer::tag('div', $qa->get_question()->format_questiontext($qa), array('class' => 'qtext')),
            'formulation clearfix'
        );

        return [
            $content,
            null
        ];
    }

    private function prepare_qtype_multianswer_renderer(question_attempt $qa, \qtype_renderer $qtoutput, question_display_options $options, $number, bool $showrightanswer = true): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $rightanswers = $qa->get_right_answer_summary();

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

            if (isset($correct->innertext)) {
                $correct->innertext = '';
            }

            $index++;

            $questions[] = clone $node;
            $node->innertext = $number . '.' . count($questions);
        }

        foreach ($deleted as $delete) {
            $delete->remove();
        }

        $content = $html->save();

        if (mb_substr_count($rightanswers, 'часть') !== count($questions)) {
            throw new \moodle_exception('error_prepare_qtype_multianswer_renderer', 'sibguexporttest', '', null, 'question_attempt#'.$qa->get_database_id());
        }

        foreach ($questions as $key => $question) {
            $content .= \html_writer::end_tag('tr');
            $content .= \html_writer::end_tag('table');
            $content .=  \html_writer::start_tag('table', ['class' => 'questions ']);
            $content .= \html_writer::start_tag('tr');
            $content .= $this->getFooter();
            $content .= $this->getHeader($number . '.' . ($key+1));
            $content .= html_writer::start_div('que ' . $qa->get_question(false)->get_type_name() . ' ' . $qa->get_behaviour_name());
            $content .= $question->text();
            if ($showrightanswer) {
                $content .= html_writer::nonempty_tag('div', sprintf(
                    'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
                    $this->extractTextByKey($rightanswers, $key+1)
                ), ['class' => 'rightanswer']);
            }
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

    private function prepare_qtype_calculated_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock form-inline">')) . '</div>';

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_calculatedsimple_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock form-inline">')) . '</div>';

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_ddwtos_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        // TODO fix
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $correctAnswer = $qtoutput->correct_response($qa);

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_gapselect_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        // TODO fix
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $correctAnswer = html_writer::nonempty_tag('div', $qtoutput->correct_response($qa), ['class' => 'rightanswer']);

        //echo '<code>';
        //echo htmlspecialchars($content);
        //echo '</code>';
        //echo '<code>';
        //echo htmlspecialchars($correctAnswer);
        //echo '</code>';
        //echo '<div style="border: 1px solid red">';
        //echo $content;
        //echo '</div>';
        //echo '<div style="border: 1px solid red">';
        //echo $correctAnswer;
        //echo '</div>';
        //die;

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_numerical_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock form-inline">')) . '</div>';

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_truefalse_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        $content = mb_substr($content, 0, mb_stripos($content, '<div class="ablock">')) . '</div>';

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }

    private function prepare_qtype_multichoice_single_renderer(question_attempt $qa, $qtoutput, question_display_options $options): array {
        $content = html_writer::div($qtoutput->formulation_and_controls($qa, $options), 'formulation clearfix');

        /** @var \simple_html_dom $html */
        $html = str_get_html($content);

        $index = 0;
        /** @var \simple_html_dom_node|null $node */
        while ($node = $html->find('.answer', $index)) {
            foreach ($node->find('input,.answernumber') as $input) {
                $input->remove();
            }

            $index++;
        }

        $content = $html->save();

        preg_match_all('/Правильный ответ:\s{0,}(.+)/', $qtoutput->correct_response($qa), $matches);
        $correctAnswer = html_writer::nonempty_tag('div', sprintf(
            'Правильный ответ: <p dir="ltr" style="text-align: left;">%s</p>',
            \implode(' ', $matches[1] ?? [])
        ), ['class' => 'rightanswer']);

        return [$content, $correctAnswer];
    }

    private function extractTextByKey($input, $key): string
    {
        // Разбиваем строку на части по слову "часть"
        $parts = explode('часть', $input);

        // Пробегаем по всем частям
        foreach ($parts as $part) {
            // Убираем лишние пробелы
            $part = trim($part);

            // Если часть начинается с ключа, возвращаем её
            if (strpos($part, "$key:") === 0) {
                // Убираем номер части и возвращаем оставшийся текст
                return trim(substr($part, strlen("$key:")));
            }
        }

        // Если ключ не найден, возвращаем null или ошибку
        return '';
    }

    protected function convertLatexToSvgInHtml($html) {
        /** @var \simple_html_dom $html */
        $html = str_get_html($html);

        $index = 0;
        /** @var \simple_html_dom_node|null $node */
        while ($node = $html->find('.filter_mathjaxloader_equation', $index)) {
            /** @var \simple_html_dom_node|null $input */
            foreach ($node->find('.nolink') as $input) {
                $svg = $this->latexToSvg($input->innertext());
                if ($svg) {
                    $input->innertext = sprintf('<img src="data:image/svg+xml;base64,%s" alt="%s" />', base64_encode($svg), $input->innertext());
                }
            }

            $index++;
        }

        return $html->save();
    }

    protected function convertLatexToSvgInHtmlOld($html) {
        $regex = '/\$\$(.+?)\$\$|\\\[(.+?)\\\]|\$(.+?)\$/s';
        $result = preg_replace_callback($regex, function ($matches) {
            print_r($matches);
            echo '#####'. PHP_EOL;

            if (!empty($matches[1])) {
                $svg = $this->latexToSvg($matches[0]);
                if ($svg) {
                    return sprintf('<img src="data:image/svg+xml;base64,%s" alt="%s" />', base64_encode($svg), $matches[0]);
                }
            }

            return ""; // Если ничего не найдено, ничего не заменяем
        }, $html);

        return $result;
    }

    function latexToSvg($latex) {
        try {
            // Шаблон LaTeX документа
            $texTemplate = <<<TEX
\\documentclass[preview]{standalone}
\\usepackage{amsmath}
\\begin{document}
\\fontsize{14.4}{17.3}\\selectfont
%s
\\end{document}
TEX;

            // Создаем временные файлы
            $tmpDir = sys_get_temp_dir();
            $texFile = tempnam($tmpDir, 'latex');
            rename($texFile, $texFile.'.tex');
            $pdfFile = $texFile.'.pdf';
            $svgFile = $texFile.'.svg';

            // Записываем LaTeX код в файл
            file_put_contents($texFile.'.tex', sprintf($texTemplate, $latex));

            // Компиляция LaTeX в PDF
            $command = new Command(sprintf('/usr/bin/pdflatex -output-directory=%s %s', escapeshellarg($tmpDir), escapeshellarg($texFile.'.tex')));
            if (!$command->execute()) {
                return null;
            }

            // Конвертация PDF в SVG
            $command = new Command(sprintf('/usr/bin/pdf2svg %s %s', escapeshellarg($pdfFile), escapeshellarg($svgFile)));
            if (!$command->execute()) {
                return null;
            }

            // Читаем результат SVG
            $svg = file_exists($svgFile) ? file_get_contents($svgFile) : null;

            return $svg;
        } finally {
            $files = [
                $texFile,
                $texFile.'.log',
                $texFile.'.tex',
                $texFile.'.aux',
                $pdfFile,
                $svgFile,
            ];

            // Удаляем временные файлы
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
