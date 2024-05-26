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
    public bool $debug;
    public int $userid;
    public \stdClass $user;
    public settings $settings;
    public \stdClass $pdfdata;
    public generator_renderer $renderer;
    public question_renderer $qrenderer;
    public array $quizzes;

    public string $session_id;
    public ?string $variant;

    public ?string $path;
    public $content;
    public ?string $error;

    public function __construct(int $courseid, int $userid, generator_renderer $renderer, question_renderer $qrenderer, bool $debug = false, string $session_id = null) {
        $this->debug = $debug;
        $this->userid = $userid;
        $this->user = \core_user::get_user($this->userid);
        $this->settings = settings::get_by_course($courseid);
        $this->pdfdata = $this->settings->get_pdfdata();
        $this->quizzes = $this->settings->get_selected_quizzes();
        $this->renderer = $renderer;
        $this->session_id = $session_id ?? session_id();
        $this->qrenderer = $qrenderer;

        $this->variant = null;
        $this->path = null;
        $this->content = null;
        $this->error = null;

        $this->generate();
    }

    private function getDebugClass(): string
    {
        return $this->debug ? ' debug':'';
    }

    public function get_header($body, $variant) {
        $content = <<<HTML
<table class="{$this->getDebugClass()}">
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

        $cookies = 'MoodleSession'.$CFG->sessioncookie .'='.$this->session_id; // Add your cookies here
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
<table class="{$this->getDebugClass()}">
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
        if (!$this->variant) {
            foreach ($this->quizzes as $quizid) {
                $attempts = quiz_get_user_attempts($quizid, $this->userid, 'all', true);
                $lastattempt = end($attempts);

                if ($lastattempt) {
                    $variants[] = $lastattempt->id;
                }
            }

            $this->variant = \implode('-', $variants ?? []);
        }

        return 'Вариант ' . $this->variant;
    }

    public function get_first_page() {
        $content = <<<HTML
<table class="{$this->getDebugClass()}">
<tbody>
<tr>
    <td>{$this->pdfdata->headerbodypage_editor['text']}</td>
</tr>   
<tr>
    <td style="text-align: center; padding-top: 24px; padding-bottom: 24px"><strong><span class="" style="font-size: xx-large;">{$this->get_variant()}</span></strong></td>
</tr>
<tr>
    <td>{$this->pdfdata->footerbodypage_editor['text']}</td>
</tr>
</tbody>
</table>
HTML;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content);
    }

    public function get_font_size_page() {
        $content = <<<HTML
    <table class="{$this->getDebugClass()}">
        <tbody>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: xx-small;">xx-small (7pt)</span></td>
                <td><span style="font-size: xx-small;">.7rem</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: x-small;">x-small (7,5pt)</span></td>
                <td><span style="font-size: x-small;">.8rem</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: small;">small (10pt)</span></td>
                <td><span style="font-size: small;">.9rem</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: medium;">medium (12pt)</span></td>
                <td><span style="font-size: medium;">16pt</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: large;">large (13,5pt)</span></td>
                <td><span style="font-size: large;">1.25rem</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: x-large;">x-large (16pt)</span></td>
                <td><span style="font-size: x-large;">1.5rem</span></td>
            </tr>
            <tr>
                <td><span>обычный текст</span></td>
                <td><span style="font-size: xx-large;">xx-large (24pt)</span></td>
                <td><span style="font-size: xx-large;">2rem</span></td>
            </tr>
        </tbody>
    </table>
HTML;

        $this->link_to_base64($content);

        return $this->renderer->get_html($content);
    }

    public function get_test_page() {

        $output = \html_writer::start_tag('table', ['class' => 'questions ' . $this->getDebugClass()]);
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

            $variants[] = $lastattempt->id;

            $attempt = \quiz_attempt::create($lastattempt->id);
            $slots = $attempt->get_slots();
            foreach ($slots as $slot) {
                $output .= $this->print_question($attempt, $questionno, $slot);

                $questionno++;
            }
        }

        $this->variant = \implode('-', $variants ?? []);

        $output .= \html_writer::end_tag('tbody');
        $output .= \html_writer::end_tag('table');

        $output .= \html_writer::start_tag('table', ['class' => $this->getDebugClass()]);
        $output .= \html_writer::start_tag('tbody');
        $output .= \html_writer::end_tag('tr');
        $output .= \html_writer::tag('td', $this->pdfdata->signmasterpage_editor['text'], ['style' => 'padding-top: 32px']);
        $output .= \html_writer::start_tag('tr');
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

    private function generate() {
        $header_content = $this->get_header($this->pdfdata->headerpage_editor['text'], $this->get_variant());
        $footer_content = $this->get_footer($this->pdfdata->footerpage_editor['text']);

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

        $test_page = $this->get_test_page();
        $first_page = $this->get_first_page();

        $pdf->addPage($first_page);
        $pdf->addPage($test_page);

        if ($this->debug) {
            $pdf->addPage($this->get_font_size_page());
        }

        $this->path = $pdf->getPdfFilename();
        $this->content = $pdf->toString();
        $this->error = $pdf->getError();
    }

    public function get_pdf_response() {
        if ($this->error) {
            debug::dd($this->error);
        }

        header('Content-Type: application/pdf');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Content-Disposition: inline; filename="' . rawurlencode($this->get_filename()) . '"; ' .
            'filename*=UTF-8\'\'' . rawurlencode($this->get_filename()));
        echo $this->content;
    }

    public function get_filename(): string
    {
        return \sprintf("%s %s - %s - %s.pdf", $this->user->lastname, $this->user->firstname, $this->user->username, $this->get_variant());
    }

    public function get_content(): string
    {
        return $this->content;
    }

    public function get_error(): string
    {
        return $this->error;
    }

    public function get_path_file(): ?string
    {
        $this->generate();

        if ($this->error) {
            return null;
        }

        return $this->path;
    }
}