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

function xmldb_local_sibguexporttest_upgrade($oldversion): bool
{
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2024041400) {
        // Define table local_sibguexporttest to be created.
        $table = new xmldb_table('local_sibguexporttest');

        // Adding fields to table local_sibguexporttest.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('headerpage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('footerpage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('headerbodypage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('footerbodypage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_sibguexporttest.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_sibguexporttest.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024041400, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024041401) {

        // Define field headerpageformat to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('headerpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'timemodified');

        // Conditionally launch add field headerpageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field footerpageformat to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('footerpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'headerpageformat');

        // Conditionally launch add field footerpageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field headerbodypageformat to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('headerbodypageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'footerpageformat');

        // Conditionally launch add field headerbodypageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field footerbodypageformat to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('footerbodypageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'headerbodypageformat');

        // Conditionally launch add field footerbodypageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024041401, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024051401) {

        // Define field signmasterpage to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('signmasterpage', XMLDB_TYPE_TEXT, null, null, null, null, null, 'footerbodypageformat');

        // Conditionally launch add field signmasterpage.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field signmasterpageformat to be added to local_sibguexporttest.
        $table = new xmldb_table('local_sibguexporttest');
        $field = new xmldb_field('signmasterpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'signmasterpage');

        // Conditionally launch add field signmasterpageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024051401, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024051405) {

        // Define table local_sibguexporttest_export to be created.
        $table = new xmldb_table('local_sibguexporttest_export');

        // Adding fields to table local_sibguexporttest_export.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'new');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userids', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_sibguexporttest_export.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_sibguexporttest_export.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024051405, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024051406) {

        // Define field id to be added to local_sibguexporttest_export.
        $table = new xmldb_table('local_sibguexporttest_export');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timemodified');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        // Launch add key userid.
        $dbman->add_key($table, $key);

        $key = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        // Launch add key courseid.
        $dbman->add_key($table, $key);

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024051406, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024062600) {

        // Define field type to be added to local_sibguexporttest_export.
        $table = new xmldb_table('local_sibguexporttest_export');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'selected', 'userid');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024062600, 'local', 'sibguexporttest');
    }

    if ($oldversion < 2024090100) {

        // Define table local_sibguexporttest_config to be created.
        $table = new xmldb_table('local_sibguexporttest_config');

        // Adding fields to table local_sibguexporttest_config.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'ticket');
        $table->add_field('headerpage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('headerpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('footerpage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('footerpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('headerbodypage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('headerbodypageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('footerbodypage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('footerbodypageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('signmasterpage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('signmasterpageformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_sibguexporttest_config.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Adding indexes to table local_sibguexporttest_config.
        $table->add_index('type_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'type']);

        // Conditionally launch create table for local_sibguexporttest_config.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Sibguexporttest savepoint reached.
        upgrade_plugin_savepoint(true, 2024090100, 'local', 'sibguexporttest');
    }

    // Everything has succeeded to here. Return true.
    return true;
}