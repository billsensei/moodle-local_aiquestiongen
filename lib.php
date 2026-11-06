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
 * Library functions for local_aiquestiongen.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a link to the upload page in the course "More" menu.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 */
function local_aiquestiongen_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!get_config('local_aiquestiongen', 'enabled')) {
        return;
    }
    if (!has_capability('local/aiquestiongen:use', $context)) {
        return;
    }

    $url = new moodle_url('/local/aiquestiongen/index.php', ['id' => $course->id]);
    $navigation->add(
        get_string('generatequestions', 'local_aiquestiongen'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_aiquestiongen',
        new pix_icon('i/settings', '')
    );
}

/**
 * Serve files from the source article file area.
 *
 * @param stdClass $course The course.
 * @param stdClass|null $cm The course module (unused).
 * @param context $context The file context.
 * @param string $filearea The file area.
 * @param array $args Remaining path arguments (itemid, filepath, filename).
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if the file cannot be served.
 */
function local_aiquestiongen_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_COURSE || $filearea !== 'source') {
        return false;
    }
    require_login($course);
    require_capability('local/aiquestiongen:use', $context);

    // The item id is the source article's id. Only the teacher who added the article may fetch its file, as with
    // every other page of this plugin; the capability alone would let any teacher in the course read it.
    global $DB, $USER;
    $itemid = (int) array_shift($args);
    $conditions = ['id' => $itemid, 'courseid' => $context->instanceid, 'userid' => $USER->id];
    if (!$DB->record_exists('local_aiquestiongen_source', $conditions)) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file($context->id, 'local_aiquestiongen', 'source', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true, $options);
}
