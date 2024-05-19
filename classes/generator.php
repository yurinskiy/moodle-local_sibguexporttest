<?php

namespace local_sibguexporttest;

use local_sibguexporttest\output\generator_renderer;
use local_sibguexporttest\output\question_renderer;
use mikehaertl\tmp\File;
use mikehaertl\wkhtmlto\Pdf;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/sibguexporttest/vendor/autoload.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

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

        $this->quizzes = $settings->get_selected_quizzes();

        return $settings->get_pdfdata();
    }

    public function get_header($body, $variant) {
        $content = <<<HTML
<table class="debug">
<thead>
<tr>
<td>{$variant}</td>
<td><span class="page"></span> / <span class="topage"></span></td>
</tr>
</thead>
<tbody>
<tr>
<td colspan="2">{$body}</td>
</tr>
</tbody></table>
HTML;
        $customcss = <<<CSS
body > table thead td:last-child {
    text-align: right;
}
CSS;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content, '', $customcss);
    }

    protected function get_file($url) {
        global $CFG;

        // Make sure the session is closed properly, this prevents problems in IIS
        // and also some potential PHP shutdown issues.
        \core\session\manager::write_close();

        $cookies = 'MoodleSession'.$CFG->sessioncookie .'='.session_id(); // Add your cookies here
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        $imageData = curl_exec($ch);
        curl_close($ch);

        return $imageData;

    }

    protected function link_to_base64(&$content) {
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/', $content, $matches);

        foreach ($matches[1] as $imageSrc) {
            $imageData = $this->get_file($imageSrc);
            $imageData64 = base64_encode($imageData);

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);

            $imageType = '';
            if ($mimeType === 'image/jpeg') {
                $imageType = 'jpeg';
            } elseif ($mimeType === 'image/png') {
                $imageType = 'png';
            } // Add more image types here if needed

            if (!empty($imageType)) {
                $content = str_replace($imageSrc, 'data:' . $mimeType . ';base64,' . $imageData64, $content);
            }
        }
    }

    public function get_footer($body) {
        $content = <<<HTML
<table class="debug">
<tbody>
<tr>
<td>$body</td>
</tr>
</tbody>
</table>
HTML;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content, '');
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
<table class="debug">
<tbody>
<tr>
    <td>{$this->settings->headerbodypage_editor['text']}</td>
</tr>   
<tr>
    <td style="text-align: center; padding-top: 24px; padding-bottom: 24px"><strong><span class="" style="font-size: xx-large;">{$this->get_variant()}</span></strong></td>
</tr>
<tr>
    <td>{$this->settings->footerbodypage_editor['text']}</td>
</tr>
</tbody>
</table>
HTML;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content);
    }

    public function get_font_size_page() {
        $content = <<<HTML
    <table class="debug">
        <tbody>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: xx-small;">x-small (7,5pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: x-small;">x-small (7,5pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: small;">small (10pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: medium;">medium (12pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: large;">large (13,5pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: x-large;">x-large (16pt)</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: xx-large;">xx-large (24pt)</span></td>
            </tr>
        </tbody>
    </table>
HTML;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content);
    }

    public function get_test_page() {

        $output = \html_writer::start_tag('table', ['class' => 'questions debug']);
        $output .= \html_writer::start_tag('tbody');
        $questionno = 1;

        foreach ($this->quizzes as $quizid) {
            $quiz = \quiz::create($quizid, $this->userid);
            // Look for an existing attempt.
            $attempts = quiz_get_user_attempts($quiz->get_quizid(), $this->userid, 'all', true);

            $lastattempt = end($attempts);
            if (!$lastattempt) {
                $output .= $this->get_not_found_attempts($quiz, $questionno);
                continue;
            }

            $attempt = \quiz_attempt::create($lastattempt->id);
            $slots = $attempt->get_slots();
            foreach ($slots as $slot) {
                $output .= $this->print_question($attempt, $questionno, $slot);

                $questionno++;
            }
        }


        $output .= \html_writer::end_tag('tbody');
        $output .= \html_writer::end_tag('table');

        $this->link_to_base64($output);

        return $this->renderer->get_html($output);
    }

    public function get_not_found_attempts(\quiz $quiz, &$number) {
        $cnt = $quiz->get_structure()->get_question_count() - 1;

        $content = \html_writer::start_tag('tr');
        $content .= \html_writer::tag('th', \html_writer::nonempty_tag('i', $number.'<br>-<br>'.($number + $cnt), ['class' => 'question-number']), ['scope' => 'row']);
        $content .= \html_writer::tag('td', get_string('test_not_start', 'local_sibguexporttest', [
            'name' => $quiz->get_quiz_name(),
            'from' => $number,
            'to' => $number + $cnt,
        ]));
        $content .= \html_writer::end_tag('tr');

        $number += $cnt + 1;

        return $content;
    }

    public function print_question(\quiz_attempt $attempt, $number, $slot) {
        $displayoptions = clone($attempt->get_display_options(true));
        $question_attempt = $attempt->get_question_attempt($slot);

        $content = \html_writer::start_tag('tr');
        $content .= \html_writer::tag('th', \html_writer::nonempty_tag('i', $number, ['class' => 'question-number']), ['scope' => 'row']);
        $content .= \html_writer::tag('td', $this->qrenderer->question_gen(
                $question_attempt,
                $displayoptions,
                $number
            ));
        $content .= \html_writer::end_tag('tr');

        return $content;
    }

    public function generate() {
        $header_content = $this->get_header($this->settings->headerpage_editor['text'], $this->get_variant());
        $footer_content = $this->get_footer($this->settings->footerpage_editor['text']);

        $pdf = new Pdf([
            'encoding' => 'UTF-8',
            'header-html' => new File($header_content, '.html'),
            'header-line',
            'header-spacing' => 5,
            'footer-html' => new File($footer_content, '.html'),
            'footer-line',
            'footer-spacing' => 5,
            'page-size' => 'A4',
        ]);

        //echo $header_content;die;
        //echo $this->get_first_page();die;

        $pdf->addPage($this->get_font_size_page());
        $pdf->addPage($this->get_first_page());
        $pdf->addPage($this->get_test_page());

        $content = $pdf->toString();

        if ($errors = $pdf->getError()) {
            debug::dd($errors);
        }

        header('Content-Type: application/pdf');
        echo $content;
    }
}