<?php

namespace local_sibguexporttest;

use local_sibguexporttest\output\generator_renderer;
use local_sibguexporttest\output\question_renderer;
use mikehaertl\tmp\File;
use mikehaertl\wkhtmlto\Pdf;
use question_engine;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/sibguexporttest/vendor/autoload.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

class generator_v2 {
    /** @var bool  */
    public $debug;
    /** @var string  */
    public  $type;
    /** @var int  */
    public  $userid;
    /** @var \stdClass  */
    public  $user;
    /** @var settings|config */
    public  $settings;
    /** @var \stdClass  */
    public $pdfdata;
    /** @var generator_renderer  */
    public $renderer;
    /** @var question_renderer  */
    public $qrenderer;
    /** @var array  */
    public $quizzes;

    /** @var string  */
    public $session_id;
    /** @var string|null  */
    public $variant;
/** @var string|null  */
    public $path;
    public $content;
    public $rawcontent;
    /** @var string|null  */
    public $error;
    /** @var int|null  */
    public $count;

    public function __construct(generator_renderer $renderer, question_renderer $qrenderer, string $type, int $id, int $userid = null, bool $debug = false, string $session_id = null, array $options = []) {
        $this->debug = $debug;
        $this->type = $type;

        switch ($this->type) {
            case 'default':
                $this->settings = settings::get_by_course($id);
                break;
            case 'ticket':
                $this->settings = config::get_by_id($id);
                $this->count = $options['count'] ?? 1;
                break;
            default:
                throw new \moodle_exception('error_unknown_type', 'sibguexporttest', '', null, 'Unknown type ' . $this->type);
        }

        $this->userid = $userid;
        $this->user = \core_user::get_user($this->userid);

        $this->pdfdata = $this->settings->get_pdfdata();
        $this->quizzes = $this->settings->get_selected_quizzes();
        $this->renderer = $renderer;
        $this->session_id = $session_id ?? session_id();
        $this->qrenderer = $qrenderer;

        $this->variant = null;
        $this->path = null;
        $this->content = null;
        $this->error = null;

        try {
            $this->generate($options);
        } catch (\Throwable $exception) {
            $this->error = $exception->getFile().'#L'.$exception->getLine().': '.$exception->getMessage();
        }
    }

    private function getDebugClass(): string
    {
        return $this->debug ? ' debug':'';
    }

    public function get_header($body) {
        $variant = $this->get_variant();
        $username = $this->settings instanceof settings ? $this->user->username : '';
        $pagination = $this->show_pagination() ? '<span class="page"></span> / <span class="topage"></span>' : '';

        $content = <<<HTML
<table class="{$this->getDebugClass()}">
<thead>
<tr>
<td style="width: 33.33%;">{$variant}</td>
<td style="width: 33.33%; text-align: center">{$username}</td>
<td style="width: 33.33%;">{$pagination}</td>
</tr>
</thead>
<tbody>
<tr>
<td colspan="3">{$body}</td>
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
        if ($this->settings instanceof settings) {
            return 'Вариант ' . $this->variant;
        }

        if (!$this->variant) {
            return '';
        }

        $template = $this->settings::has_property('versionformat') ? $this->settings->get('versionformat') : 'Вариант #';

        // Подсчитываем количество символов # в шаблоне
        $num_hashes = mb_substr_count($template, '#');
        if ($num_hashes === 0) {
            return rtrim($template) . ' ' . $this->variant;
        }

        // Подсчитываем количество цифр в числе
        $num_digits = mb_strlen((string) $this->count);

        // Если количество цифр в числе больше, чем количество # в шаблоне, дополняем шаблон символами #
        if ($num_digits > $num_hashes) {
            $template = str_replace(str_repeat('#', $num_hashes), str_repeat('#', $num_digits), $template);
            $num_hashes = $num_digits; // Обновляем количество #
        }

        // Форматируем число с ведущими нулями
        $formatted_number = sprintf('%0' . $num_hashes . 'd', $this->variant);

        // Заменяем ### на отформатированное число
        return str_replace(str_repeat('#', $num_hashes), $formatted_number, $template);
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

    public function get_ticket_page(array $options = [])
    {
        $output = '';
        $questionno = 1;

        foreach ($this->quizzes as $quizid) {
            $quiz = \quiz::create($quizid, $this->userid);

            $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quiz->get_context());
            $quba->set_preferred_behaviour($quiz->get_quiz()->preferredbehaviour);

            /** @var array $attempts */
            $attempts = quiz_get_user_attempts($quiz->get_quizid(), $this->userid, 'all', true);

            $lastattempt = end($attempts);
            $lastattempt = $lastattempt ? \quiz_attempt::create($lastattempt->id) : null;

            $attempt = quiz_create_attempt($quiz, \count($attempts) +1, $lastattempt, time(), true, $this->userid);
            quiz_start_new_attempt($quiz, $quba, $attempt, \count($attempts) +1, time());
            quiz_attempt_save_started($quiz, $quba, $attempt);

            $attempt = \quiz_attempt::create($attempt->id);

            $slots = $attempt->get_slots();
            foreach ($slots as $slot) {
                $output .=  \html_writer::start_tag('table', ['class' => 'questions ' . $this->getDebugClass()]);
                $output .= $this->print_question($attempt, $questionno, $slot, $options['showrightanswer'] ?? true);
                $output .= \html_writer::end_tag('table');
            }
        }

        $output .= \html_writer::start_tag('table', ['class' => $this->getDebugClass()]);
        $output .= \html_writer::end_tag('tr');
        $output .= \html_writer::tag('td', $this->pdfdata->signmasterpage_editor['text'], ['style' => 'padding-top: 32px']);
        $output .= \html_writer::start_tag('tr');
        $output .= \html_writer::end_tag('table');

        $this->link_to_base64($output);

        return $this->renderer->get_html($output);
    }

    public function get_default_page(array $options) {
        $output = '';
        $questionno = 1;

        foreach ($this->quizzes as $quizid) {
            $quiz = \quiz::create($quizid, $this->userid);
            // Look for an existing attempt.
            $attempts = quiz_get_user_attempts($quiz->get_quizid(), $this->userid, 'finished', true);

            $lastattempt = end($attempts);
            if (!$lastattempt) {
                $output .=  \html_writer::start_tag('table', ['class' => 'questions ' . $this->getDebugClass()]);
                $output .= $this->get_not_found_attempts($quiz, $questionno);
                $output .= \html_writer::end_tag('table');
                continue;
            }

            $variants[] = $lastattempt->id;

            $attempt = \quiz_attempt::create($lastattempt->id);
            $slots = $attempt->get_slots();
            foreach ($slots as $slot) {
                $output .=  \html_writer::start_tag('table', ['class' => 'questions ' . $this->getDebugClass()]);
                $output .= $this->print_question($attempt, $questionno, $slot);
                $output .= \html_writer::end_tag('table');
            }
        }

        $this->variant = \implode('-', $variants ?? []);

        $output .= \html_writer::start_tag('table', ['class' => $this->getDebugClass()]);
        $output .= \html_writer::end_tag('tr');
        $output .= \html_writer::tag('td', $this->pdfdata->signmasterpage_editor['text'], ['style' => 'padding-top: 32px']);
        $output .= \html_writer::start_tag('tr');
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

    public function print_question(\quiz_attempt $attempt, &$number, $slot) {
        $displayoptions = clone($attempt->get_display_options(true));
        $question_attempt = $attempt->get_question_attempt($slot);

        $content = \html_writer::start_tag('tr');
        $content .= $this->qrenderer->question_gen(
            $question_attempt,
            $displayoptions,
            $number,
            !$this->settings::has_property('showrightanswer') || $this->settings->get('showrightanswer')
        );
        $content .= \html_writer::end_tag('tr');

        return $content;
    }

    private function generate(array $options = []) {
        $this->variant = $options['variant'] ?? '-';

        $contents = [];

        $params = [
            'encoding' => 'UTF-8',
            'page-size' => 'A4',
            'print-media-type',
        ];

        switch ($this->type) {
            case 'default':
                $test_page = $this->get_default_page($options);
                $first_page = $this->get_first_page();

                $contents[] = $first_page;
                $contents[] = $test_page;

                $header_content = $this->get_header($this->pdfdata->headerpage_editor['text']);
                $params['header-html'] = new File($header_content, '.html');

                $footer_content = $this->get_footer($this->pdfdata->footerpage_editor['text']);
                $params['footer-html'] = new File($footer_content, '.html');
                break;
            case 'ticket':
                if (!$this->settings instanceof config) {
                    throw new  \moodle_exception('error_unknown_type', 'sibguexporttest', '', null, sprintf('Expected "%s", given "%s".', config::class, get_class($this->settings)));
                }

                $test_page = $this->get_ticket_page($options);

                if ($this->settings->get('hasfirstpage')) {
                    $first_page = $this->get_first_page();
                    if ($this->settings->get('hasbreakfirstpage')) {
                        $contents[] = $first_page;
                        $contents[] = $test_page;
                    } else {
                        $contents[] = $first_page.$test_page;
                    }
                } else {
                    $contents[] = $test_page;
                }

                if ($this->pdfdata->headerpage_editor['text'] || $this->show_pagination() || $this->variant) {
                    $header_content = $this->get_header($this->pdfdata->headerpage_editor['text']);
                    $params['header-html'] = new File($header_content, '.html');
                    $params[] = 'header-line';
                }

                if ($this->pdfdata->footerpage_editor['text']) {
                    $footer_content = $this->get_footer($this->pdfdata->footerpage_editor['text']);
                    $params['footer-html'] = new File($footer_content, '.html');
                    $params[] = 'footer-line';
                }

                break;
            default:
                throw new \moodle_exception('error_unknown_type', 'sibguexporttest', '', null, 'Unknown type ' . $this->type);
        }

        $pdf = new Pdf($params);

        foreach ($contents as $content) {
            $pdf->addPage($content);
        }

        $this->rawcontent = implode('', $contents);

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

    public function show_pagination(): bool
    {
        return !$this->settings::has_property('showpagination') || $this->settings->get('showpagination');
    }
}