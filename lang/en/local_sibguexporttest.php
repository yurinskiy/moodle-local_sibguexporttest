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

$string['pluginname'] = 'Генерация билетов вступительных испытаний';
$string['navigation_main'] = 'Создание билета ВИ';
$string['navigation_view'] = 'Скачать билеты ВИ';
$string['navigation_settings'] = 'Настройка билета ВИ';
$string['navigation_generator'] = 'Генератор билетов';

$string['settingspage'] = 'Настройки страницы';
$string['headerpage'] = 'Верхний колонтитул';
$string['footerpage'] = 'Верхний колонтитул';
$string['headerbodypage'] = 'Титульная страница ДО номера варианта';
$string['footerbodypage'] = 'Титульная страница ПОСЛЕ номера варианта';

$string['settingstest'] = 'Настройки тестов';
$string['test_id'] = 'Выбранный тест';
$string['test_id-error_not_found'] = 'Выбранный тест не существует';
$string['test_id-error_exists'] = 'Тест "{$a}" уже используется';
$string['test_id-error_max'] = 'Выбрано максимальное количество тестов, удалите лишние варианты';
$string['test_order'] = 'Порядок теста';
$string['test_delete'] = 'Удалить тест';

$string['test_not_start'] = 'Абитуриент не совершил запуск тестового задания "{$a->name}", на основе попытки прохождения которого формируются вопросы № {$a->from} - {$a->to}';