<?php

namespace local_sibguexporttest;

use local_sibguexporttest\form\course_settings_form;
use local_sibguexporttest\output\generator_renderer;
use local_sibguexporttest\output\question_renderer;
use mikehaertl\wkhtmlto\Pdf;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/sibguexporttest/vendor/autoload.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

class generator {
    public int $userid;
    public \stdClass $settings;
    public generator_renderer $renderer;
    public question_renderer $qrenderer;

    public array $quizzes;

    /**
     * @param int $courseid
     * @param int $userid
     */
    public function __construct(int $courseid, int $userid, generator_renderer $renderer, question_renderer $qrenderer) {
        $this->userid = $userid;
        $this->settings = $this->get_settings($courseid);
        $this->renderer = $renderer;
        $this->qrenderer = $qrenderer;
    }

    private function get_settings($courseid) {
        $settings = settings::get_by_course($courseid);
        $context = \context_course::instance($courseid);

        $this->quizzes = $settings->get_selected_quizzes();

        return $settings->get_pdfdata(new course_settings_form(null, ['context' => $context, 'repeatno' => $settings->get_repeatno()]));
    }

    public function get_header($body, $variant) {
        $content = <<<HTML
    <div style="position: absolute; top: 10px; left: 0; text-align: right; white-space: nowrap; overflow: hidden;">{$variant}</div>
    <div style="position: absolute; top: 10px; right: 0; text-align: right; white-space: nowrap; overflow: hidden;"><span class="page"></span> / <span class="topage"></span></div>
    <div>{$body}</div>
HTML;
        $customcss = <<<CSS
body {
    padding-top: 1px; padding-bottom: 6px;
}
CSS;

        $html = $this->renderer->get_html($content, '', $customcss);

        $temppath = tempnam(sys_get_temp_dir(), 'header');
        file_put_contents($temppath, $html);

        return $temppath;
    }



    public function get_footer($body) {
        $content = <<<HTML
    <div>$body</div>
HTML;
        $customcss = <<<CSS
body {
    padding-top: 6px; padding-bottom: 1px;
}
CSS;

        $html = $this->renderer->get_html($content, '', $customcss);

        $temppath = tempnam(sys_get_temp_dir(), 'footer');
        file_put_contents($temppath, $html);

        return $temppath;
    }

    public function get_variant() {
        foreach ($this->settings->test_id as $quizid) {
            $attempts = quiz_get_user_attempts($quizid, $this->userid, 'all', true);
            $lastattempt = end($attempts);

            if ($lastattempt) {
                $variants[] = $lastattempt->id;
            }
        }

        return 'Вариант ' . \implode('-', $variants ?? []);
    }

    public function get_first_page() {
        $content = <<<HTML
    <div>{$this->settings->headerbodypage_editor['text']}</div>
    <h1 align="center">{$this->get_variant()}</h1>
    <div>{$this->settings->footerbodypage_editor['text']}</div>
HTML;

        debug::dd($content);

        return $this->renderer->get_html($content);
    }

    public function get_test_page() {

        $output = \html_writer::start_tag('table');
        $output .= \html_writer::start_tag('tbody');
        $questionno = 1;
        foreach ($this->quizzes as $quizid) {
            $quiz = \quiz::create($quizid, $this->userid);
            // Look for an existing attempt.
            $attempts = quiz_get_user_attempts($quiz->get_quizid(), $this->userid, 'all', true);

            $lastattempt = end($attempts);
            if (!$lastattempt) {
                $output .= \html_writer::start_tag('tr');
                $output .= \html_writer::tag('td', $this->get_not_found_attempts($quiz, $questionno), ['colspan' => 2]);
                $output .= \html_writer::end_tag('tr');
                continue;
            }

            $attempt = \quiz_attempt::create($lastattempt->id);
            $slots = $attempt->get_slots();

            foreach ($slots as $slot) {
                $displayoptions = $attempt->get_display_options_with_edit_link(true, $slot, null);
                $question_attempt = $attempt->get_question_attempt($slot);

                $output .= \html_writer::start_tag('tr');
                $output .= \html_writer::tag('td', $this->qrenderer->question_gen(
                    $question_attempt,
                    $displayoptions,
                    $questionno
                ));
                $output .= \html_writer::tag('td', $questionno, ['style' => 'vertical-align: top']);
                $output .= \html_writer::end_tag('tr');

                $questionno++;
            }
        }


        $output .= \html_writer::end_tag('tbody');
        $output .= \html_writer::end_tag('table');

        return $this->renderer->get_html($output);
    }

    public function get_not_found_attempts(\quiz $quiz, &$number) {
        $cnt = 5; // TODO получить количество вопросов пропущенного теста

        $content = <<<HTML
<div><p>Абитуриент не совершил запуск тестового задания "{$quiz->get_quiz_name()}", на основе попытки прохождения которого формируются вопросы № {$number} - {($number + $cnt)}</p></div>
HTML;
        $number += $cnt;

        return $content;
    }

    public function generate() {
        global $CFG;

        $headerpath = $this->get_header($this->settings->headerpage_editor['text'], $this->get_variant());
        $footerpath = $this->get_footer($this->settings->footerpage_editor['text']);

        $pdf = new Pdf([
            'encoding' => 'UTF-8',
            'header-html' => $headerpath,
            'header-line',
            'header-spacing' => 5,
            'footer-html' => $footerpath,
            'footer-line',
            'footer-spacing' => 5,
            'page-size' => 'A4',
            'cookie' => ['MoodleSession'.$CFG->sessioncookie => session_id()]
        ]);

        $pdf->addPage($this->get_first_page());
        $pdf->addPage($this->get_test_page());

        $content = $pdf->toString();

        unlink($headerpath);
        unlink($footerpath);

        if ($errors = $pdf->getError()) {
            debug::dd($errors);
        }

        header('Content-Type: application/pdf');
        echo $content;
    }
}