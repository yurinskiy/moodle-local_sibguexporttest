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

namespace local_sibguexporttest\task;

global $CFG;

require_once($CFG->libdir . '/filestorage/zip_archive.php');

use local_sibguexporttest\config;
use local_sibguexporttest\debug;
use local_sibguexporttest\export;
use local_sibguexporttest\generator_v2;
use zip_archive;

class local_sibguexporttest_create_zip extends \core\task\adhoc_task {
    public static function instance(
        int $exportid
    ): self {
        $task = new self();
        $task->set_custom_data((object) [
            'exportid' => $exportid,
            'session_id' => session_id(),
        ]);

        return $task;
    }

    /**
     * @inheritDoc
     */
    public function get_name() {
        return 'Генерация zip-архива выгрузки';
    }

    /**
     * @inheritDoc
     */
    public function execute() {
        global $DB;

        mtrace("My task started");
        $data = $this->get_custom_data();
        $exportid = $data->exportid;
        mtrace($exportid);

        $export = export::get_record(['id' => $exportid]);
        $courseid = $export->get('courseid');
        $course = $DB->get_record('course', ['id' => $courseid]);
        $user =\core_user::get_user($this->get_userid());

        try {
            switch ($export->get('type')) {
                case 'all':
                case 'selected':
                    $file = $this->generate_all($export, $user, $course, $data->session_id ?? null);
                    $title = '';
                    break;
                case 'list':
                    $file = $this->generate_list($export, $user, $course, $data->session_id ?? null);
                    $title = '';
                    break;
                case 'ticket':
                    $file = $this->generate_ticket($export, $user, $course, $data->session_id ?? null);
                    $title = '';
                    break;
                default:
                    throw new \moodle_exception('error_unknown_type', 'sibguexporttest');
            }

            $messageid = $this->send_message($file, $user, $course, $title);
            mtrace('Send message #' . $messageid);

            $export->set('status', 'complete');
            $export->set('description', json_encode(['message' => $messageid, 'file' => $file->get_id()], JSON_OBJECT_AS_ARRAY));
            $export->save();
        } catch (\Throwable $exception) {
            mtrace("Error: " . $exception->getMessage());

            $export->set('status', 'error');
            $export->set('description', json_encode(['error' => $exception->getMessage()], JSON_OBJECT_AS_ARRAY));
            $export->save();
        }

        mtrace("My task finished");
    }

    private function generate_all(export $export, \stdClass $user, \stdClass $course, $session_id = null) : \stored_file {
        global $PAGE;

        /** @var \local_sibguexporttest\output\generator_renderer $renderer */
        $renderer = $PAGE->get_renderer('local_sibguexporttest', 'generator');
        /** @var \local_sibguexporttest\output\question_renderer $qrenderer */
        $qrenderer = $PAGE->get_renderer('local_sibguexporttest', 'question');

        $zippath = tempnam(sys_get_temp_dir(), 'local_sibguexporttest');
        $ziparchive = new zip_archive();
        if ($ziparchive->open($zippath, \file_archive::CREATE)) {
            $ziparchive->add_file_from_string('README.txt', 'Сформирована выгрузка по курсу "' . $course->shortname . '" от ' . date('Y.m.d H:i:s').PHP_EOL.$export->get('userids'));

            $userids = json_decode($export->get('userids'));
            foreach ($userids as $userid) {
                mtrace('User ID: ' . $userid);
                $generator = new \local_sibguexporttest\generator($course->id, $userid, $renderer, $qrenderer, false, $session_id);
                mtrace('Filename: ' . $generator->get_filename());

                if (!empty($generator->get_error())) {
                    mtrace('Error(user_id='.$userid.'): ' . print_r($generator->get_error(), true));
                    continue;
                }

                $ziparchive->add_file_from_string($generator->get_filename(), $generator->get_content());
            }
            $res = $ziparchive->close();

            if ($res === true)
                mtrace('success zip.');
            else
                mtrace('failed zip: ' . $this->GetZipErrMessage($res));
        } else {
            throw new \moodle_exception('error open file ' . $zippath);
        }

        // You probably don't need attachments but if you do, here is how to add one
        $usercontext = \context_user::instance($user->id);
        $file = new \stdClass();
        $file->contextid = $usercontext->id;
        $file->component = 'local_sibguexporttest';
        $file->filearea = 'local_sibguexporttest_export';
        $file->itemid = $export->get('id');
        $file->filepath = '/';
        $file->filename = $course->shortname . ' - ' . date('Y.m.d') . '.zip';
        $file->source = 'local_sibguexporttest_export';

        $fs = get_file_storage();
        if ($stored_file = $fs->get_file($file->contextid, $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename)) {
            $stored_file->delete();
        }

        return $fs->create_file_from_pathname($file, $zippath);
    }

    private function generate_list(export $export, \stdClass $userto, \stdClass $course, $session_id = null) : \stored_file {
        global $PAGE;

        /** @var \local_sibguexporttest\output\generator_renderer $renderer */
        $renderer = $PAGE->get_renderer('local_sibguexporttest', 'generator');
        /** @var \local_sibguexporttest\output\question_renderer $qrenderer */
        $qrenderer = $PAGE->get_renderer('local_sibguexporttest', 'question');

        $csvpath = tempnam(sys_get_temp_dir(), 'local_sibguexporttest');
        $fp = fopen($csvpath, 'w');
        fputcsv($fp, ['логин абитуриента, получившего билет','ФИО абитуриента','номер билета ВИ']);
        $userids = json_decode($export->get('userids'));
        foreach ($userids as $userid) {
            $user = \core_user::get_user($userid);
            $generator = new \local_sibguexporttest\generator($course->id, $userid, $renderer, $qrenderer, false, $session_id);

            fputcsv($fp, [$user->username, \implode(' ', [$user->lastname, $user->firstname]), str_replace('Вариант ', '', $generator->get_variant())]);
        }
        fclose($fp);

        // You probably don't need attachments but if you do, here is how to add one
        $usercontext = \context_user::instance($userto->id);
        $file = new \stdClass();
        $file->contextid = $usercontext->id;
        $file->component = 'local_sibguexporttest';
        $file->filearea = 'local_sibguexporttest_export';
        $file->itemid = $export->get('id');
        $file->filepath = '/';
        $file->filename = $course->shortname . ' - ' . date('Y.m.d') . '.csv';
        $file->source = 'local_sibguexporttest_export';

        $fs = get_file_storage();
        if ($stored_file = $fs->get_file($file->contextid, $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename)) {
            $stored_file->delete();
        }

        return $fs->create_file_from_pathname($file, $csvpath);
    }

    private function send_message(\stored_file $file, \stdClass $userto, \stdClass $course, string $title)
    {
        $message = new \core\message\message();
        $message->component = 'local_sibguexporttest'; // Your plugin's name
        $message->name = 'sibguexporttest_notification'; // Your notification name from message.php
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = $course->shortname . ' - ' . date('Y.m.d');
        $message->fullmessage = $title;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = '<p>'.$title.'</p>';
        $message->smallmessage = 'Сформировано';
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message
        $message->contexturl = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid()?:null, $file->get_filepath(), $file->get_filename()); // A relevant URL for the notification
        $message->contexturlname = 'Скачать прикрепленный файл'; // Link title explaining where users get to for the contexturl
        $message->attachment = $file;

        // Actually send the message
        return message_send($message);
    }

    private function GetZipErrMessage($errno)
    {
        if ($errno===true) return '';
        switch($errno)
        {
            case 0: return 'No error'; 								//ZIP_ER_OK
            case 1: return 'Multi-disk zip archives not supported'; //ZIP_ER_MULTIDISK
            case 2: return 'Renaming temporary file failed'; 		//ZIP_ER_RENAME
            case 3: return 'Closing zip archive failed'; 			//ZIP_ER_CLOSE
            case 4: return 'Seek error'; 							//ZIP_ER_SEEK
            case 5: return 'Read error'; 							//ZIP_ER_READ
            case 6: return 'Write error'; 							//ZIP_ER_WRITE
            case 7: return 'CRC error'; 							//ZIP_ER_CRC
            case 8: return 'Containing zip archive was closed'; 	//ZIP_ER_ZIPCLOSED
            case 9: return 'No such file'; 							//ZIP_ER_NOENT
            case 10: return 'File already exists'; 					//ZIP_ER_EXISTS
            case 11: return 'Can\'t open file'; 					//ZIP_ER_OPEN
            case 12: return 'Failure to create temporary file'; 	//ZIP_ER_TMPOPEN
            case 13: return 'Zlib error'; 							//ZIP_ER_ZLIB
            case 14: return 'Malloc failure'; 						//ZIP_ER_MEMORY
            case 15: return 'Entry has been changed'; 				//ZIP_ER_CHANGED
            case 16: return 'Compression method not supported'; 	//ZIP_ER_COMPNOTSUPP
            case 17: return 'Premature EOF'; 						//ZIP_ER_EOF
            case 18: return 'Invalid argument'; 					//ZIP_ER_INVAL
            case 19: return 'Not a zip archive'; 					//ZIP_ER_NOZIP
            case 20: return 'Internal error'; 						//ZIP_ER_INTERNAL
            case 21: return 'Zip archive inconsistent'; 			//ZIP_ER_INCONS
            case 22: return 'Can\'t remove file'; 					//ZIP_ER_REMOVE
            case 23: return 'Entry has been deleted'; 				//ZIP_ER_DELETED
        }
        return 'Unknow zip error';
    }

    private function generate_ticket(export $export, \stdClass $user, \stdClass $course, $session_id = null) : \stored_file {
        global $PAGE;

        /** @var \local_sibguexporttest\output\generator_renderer $renderer */
        $renderer = $PAGE->get_renderer('local_sibguexporttest', 'generator');
        /** @var \local_sibguexporttest\output\question_renderer $qrenderer */
        $qrenderer = $PAGE->get_renderer('local_sibguexporttest', 'question');

        [$config_id, $count] = \explode('|', $export->get('description'));
        $config = config::get_by_id($config_id);

        $zippath = tempnam(sys_get_temp_dir(), 'local_sibguexporttest');
        $ziparchive = new zip_archive();
        if ($ziparchive->open($zippath, \file_archive::CREATE)) {
            $ziparchive->add_file_from_string('README.txt', 'Сформирована билеты по курсу "' . $course->shortname . '" от ' . date('Y.m.d H:i:s').PHP_EOL.$export->get('userids'));

            for ($i = 1; $i <= $count; $i++) {
                mtrace('Iterable: ' . $i);

                $generator = new generator_v2($renderer, $qrenderer, 'ticket',  $config_id, $user->id, false, $session_id, [
                    'variant' => $i + ((int) $config->get('versionfrom')) - 1,
                    'count' => $count,
                ]);

                mtrace('Filename: ' . $course->shortname.'-'.$i.'.pdf');

                if (!empty($generator->get_error())) {
                    mtrace('Error(iterable='.$i.'): ' . print_r($generator->get_error(), true));
                    continue;
                }

                $ziparchive->add_file_from_string($course->shortname.'-'.$i.'.pdf', $generator->get_content());
            }
            $res = $ziparchive->close();

            if ($res === true)
                mtrace('success zip.');
            else
                mtrace('failed zip: ' . $this->GetZipErrMessage($res));
        } else {
            throw new \moodle_exception('error open file ' . $zippath);
        }

        // You probably don't need attachments but if you do, here is how to add one
        $usercontext = \context_user::instance($user->id);
        $file = new \stdClass();
        $file->contextid = $usercontext->id;
        $file->component = 'local_sibguexporttest';
        $file->filearea = 'local_sibguexporttest_export';
        $file->itemid = $export->get('id');
        $file->filepath = '/';
        $file->filename = $course->shortname . '.zip';
        $file->source = 'local_sibguexporttest_export';

        $fs = get_file_storage();
        if ($stored_file = $fs->get_file($file->contextid, $file->component, $file->filearea, $file->itemid, $file->filepath, $file->filename)) {
            $stored_file->delete();
        }

        return $fs->create_file_from_pathname($file, $zippath);
    }
}