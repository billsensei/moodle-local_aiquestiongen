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
 * Admin settings for local_aiquestiongen.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiquestiongen', get_string('pluginname', 'local_aiquestiongen'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_aiquestiongen/enabled',
        get_string('enabled', 'local_aiquestiongen'),
        get_string('enabled_desc', 'local_aiquestiongen'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/maxarticlelength',
        get_string('maxarticlelength', 'local_aiquestiongen'),
        get_string('maxarticlelength_desc', 'local_aiquestiongen'),
        20000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/maxquestionsperjob',
        get_string('maxquestionsperjob', 'local_aiquestiongen'),
        get_string('maxquestionsperjob_desc', 'local_aiquestiongen'),
        20,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/dailyjoblimit',
        get_string('dailyjoblimit', 'local_aiquestiongen'),
        get_string('dailyjoblimit_desc', 'local_aiquestiongen'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/dailyregenlimit',
        get_string('dailyregenlimit', 'local_aiquestiongen'),
        get_string('dailyregenlimit_desc', 'local_aiquestiongen'),
        \local_aiquestiongen\local\regen_budget::DEFAULT_LIMIT,
        PARAM_INT
    ));

    // How the plugin talks to an AI model.
    $settings->add(new admin_setting_heading(
        'local_aiquestiongen/aiheading',
        get_string('aiheading', 'local_aiquestiongen'),
        get_string('aiheading_desc', 'local_aiquestiongen')
    ));
    $settings->add(new admin_setting_configselect(
        'local_aiquestiongen/aimode',
        get_string('aimode', 'local_aiquestiongen'),
        get_string('aimode_desc', 'local_aiquestiongen'),
        'core',
        [
            'core' => get_string('aimode_core', 'local_aiquestiongen'),
            'direct' => get_string('aimode_direct', 'local_aiquestiongen'),
        ]
    ));
    $settings->add(new admin_setting_configselect(
        'local_aiquestiongen/directprovider',
        get_string('directprovider', 'local_aiquestiongen'),
        get_string('directprovider_desc', 'local_aiquestiongen'),
        'claude',
        [
            'claude' => get_string('provider_claude', 'local_aiquestiongen'),
            'openai' => get_string('provider_openai', 'local_aiquestiongen'),
        ]
    ));
    $settings->hide_if('local_aiquestiongen/directprovider', 'local_aiquestiongen/aimode', 'neq', 'direct');
    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/directendpoint',
        get_string('directendpoint', 'local_aiquestiongen'),
        get_string('directendpoint_desc', 'local_aiquestiongen'),
        '',
        PARAM_URL
    ));
    $settings->hide_if('local_aiquestiongen/directendpoint', 'local_aiquestiongen/aimode', 'neq', 'direct');
    $settings->add(new admin_setting_encryptedpassword(
        'local_aiquestiongen/directapikey',
        get_string('directapikey', 'local_aiquestiongen'),
        get_string('directapikey_desc', 'local_aiquestiongen')
    ));
    $settings->hide_if('local_aiquestiongen/directapikey', 'local_aiquestiongen/aimode', 'neq', 'direct');
    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/directmodel',
        get_string('directmodel', 'local_aiquestiongen'),
        get_string('directmodel_desc', 'local_aiquestiongen'),
        'claude-sonnet-5-5',
        PARAM_TEXT
    ));
    $settings->hide_if('local_aiquestiongen/directmodel', 'local_aiquestiongen/aimode', 'neq', 'direct');
    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/directmaxtokens',
        get_string('directmaxtokens', 'local_aiquestiongen'),
        get_string('directmaxtokens_desc', 'local_aiquestiongen'),
        8000,
        PARAM_INT
    ));
    $settings->hide_if('local_aiquestiongen/directmaxtokens', 'local_aiquestiongen/aimode', 'neq', 'direct');
    $settings->add(new admin_setting_configtext(
        'local_aiquestiongen/directtimeout',
        get_string('directtimeout', 'local_aiquestiongen'),
        get_string('directtimeout_desc', 'local_aiquestiongen'),
        120,
        PARAM_INT
    ));
    $settings->hide_if('local_aiquestiongen/directtimeout', 'local_aiquestiongen/aimode', 'neq', 'direct');

    $settings->add(new admin_setting_configexecutable(
        'local_aiquestiongen/pdftotextpath',
        get_string('pdftotextpath', 'local_aiquestiongen'),
        get_string('pdftotextpath_desc', 'local_aiquestiongen'),
        ''
    ));
}
