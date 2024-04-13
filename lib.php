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

if (!function_exists('dd')) {
    function dd(...$vars)
    {
        if (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true) && !headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        echo '<pre>';

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            echo print_r($vars[0], 1);
        } else {
            foreach ($vars as $k => $v) {
                echo print_r($v, 1);
            }
        }
        echo '</pre>';

        exit(1);
    }
}

function local_sibguexporttest_extend_settings_navigation(settings_navigation $settingsnav, context $context) {

    if ($context->contextlevel !== CONTEXT_COURSE) {
        return;
    }

    $addnode = is_siteadmin() || has_capability('local/sibguexporttest:view', $context);
    if (!$addnode) {
        return;
    }

    $id = $context->instanceid;

    $coursereportsnode = $settingsnav->find('coursereports', navigation_node::TYPE_CONTAINER);

    // Создание билета ВИ
    $urltext = get_string('navigation_main', 'local_sibguexporttest');
    $url = null;
    $mainnode = $coursereportsnode->create(
        $urltext,
        $url,
        navigation_node::TYPE_CONTAINER,
        null,
        'sibguexporttest',
        new pix_icon('i/report', $urltext)
    );
    $coursereportsnode->add_node($mainnode);

    //Скачать билеты ВИ
    $urltext = get_string('navigation_view', 'local_sibguexporttest');
    $url = new moodle_url('/local/sibguexporttest/index.php',['courseid' => $id, 'action' => 'view']);
    $node = $coursereportsnode->create(
        $urltext,
        $url,
        navigation_node::NODETYPE_LEAF,
        null,
        'sibguexporttest_download',
        new pix_icon('i/report', $urltext)
    );
    $mainnode->add_node($node);


    $addnode = is_siteadmin() || has_capability('local/sibguexporttest:manager', $context);
    if (!$addnode) {
        return;
    }

    // Настройка билета ВИ
    $urltext = get_string('navigation_settings', 'local_sibguexporttest');
    $url = new moodle_url('/local/sibguexporttest/index.php',['courseid' => $id, 'action' => 'settings']);
    $node = $coursereportsnode->create(
        $urltext,
        $url,
        navigation_node::NODETYPE_LEAF,
        null,
        'sibguexporttest_settings',
        new pix_icon('i/settings', $urltext)
    );
    $mainnode->add_node($node);

    // Генератор билетов
    $urltext = get_string('navigation_generator', 'local_sibguexporttest');
    $url = new moodle_url('/local/sibguexporttest/index.php',['courseid' => $id, 'action' => 'generate']);
    $node = $coursereportsnode->create(
        $urltext,
        $url,
        navigation_node::NODETYPE_LEAF,
        null,
        'sibguexporttest_generator',
        new pix_icon('i/report', $urltext)
    );
    $mainnode->add_node($node);
}