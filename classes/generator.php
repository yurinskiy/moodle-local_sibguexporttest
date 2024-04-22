<?php

namespace local_sibguexporttest;

use local_sibguexporttest\form\course_settings_form;
use mikehaertl\wkhtmlto\Pdf;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/local/sibguexporttest/vendor/autoload.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

class generator {
    public int $userid;
    public \stdClass $settings;

    /**
     * @param int $courseid
     * @param int $userid
     */
    public function __construct(int $courseid, int $userid) {
        $this->userid = $userid;
        $this->settings = $this->get_settings($courseid);
    }

    private function get_settings($courseid) {
        $settings = settings::get_by_course($courseid);
        $context = \context_course::instance($courseid);

        return $settings->get_pdfdata(new course_settings_form(null, ['context' => $context, 'repeatno' => $settings->get_repeatno()]));
    }

    public function get_header($body, $variant) {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
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
    </script>
</head>
<body onload="substitutePdfVariables()" style="position: relative">
    <div style="position: absolute; top: 0; left: 0; text-align: right; white-space: nowrap; overflow: hidden;">{$variant}</div>
    <div style="position: absolute; top: 0; right: 0; text-align: right; white-space: nowrap; overflow: hidden;"><span class="page"></span> / <span class="topage"></span></div>
    <div>{$body}</div>
</body>
</html>
HTML;

        $temppath = tempnam(sys_get_temp_dir(), 'header').'.html';
        file_put_contents($temppath, $html);

        return $temppath;
    }



    public function get_footer($body) {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
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
    </script>
</head>
<body onload="substitutePdfVariables()" style="position: relative">
    <div>{$body}</div>
</body>
</html>
HTML;

        $temppath = tempnam(sys_get_temp_dir(), 'footer').'.html';
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

    public function generate() {
        global $CFG, $SITE;

        $headerpath = $this->get_header($this->settings->headerpage_editor['text'], $this->get_variant());
        $footerpath = $this->get_footer($this->settings->footerpage_editor['text']);

        $pdf = new Pdf([
            'encoding' => 'UTF-8',
            'header-html' => $headerpath,
            'header-line',
            'footer-html' => $footerpath,
            'footer-line',
            'page-size' => 'A4',
        ]);

        $first_page = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<title></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
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
    </script>
</head>
<body onload="substitutePdfVariables()" style="position: relative">
{$this->settings->headerbodypage_editor['text']}
<h1 align="center">{$this->get_variant()}</h1>
{$this->settings->footerbodypage_editor['text']}
</body>
</html>
HTML;


        $pdf->addPage($first_page);
        $pdf->addPage('test');


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