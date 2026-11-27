<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrolcond_userfile\task;

use enrolcond_userfile\condition;
use local_enrolconditions\condition\manager;

/**
 * Deletes files of usernames that no condition refers to any more.
 *
 * They are left behind when a condition is removed, or when its form is saved but the enrolment method is not.
 * Files younger than a day are kept, so forms still open are not affected. A file is deleted only when every
 * stored value of its course was read and none names it ({@see get_spared_fileids()}): a value that names a file
 * but is not valid JSON, a method that cannot be asked for its tree, and the columns of a method that is not
 * installed all keep the files of their course, since a deletion cannot be undone. A course skipped that way is
 * counted in the output of the run and tried again on the next one. The references of a course are read once for
 * the run and again right before each deletion, since a teacher may save a tree that uses the file meanwhile; the
 * cached list of usernames of a deleted file is dropped on every node.
 *
 * @package    enrolcond_userfile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_files extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('cleanupfilestask', condition::COMPONENT);
    }

    /**
     * File ids referred to by the valid stored condition trees of the enrolment instances of a course.
     *
     * This is the "a tree in force uses this file" rule of {@see condition::check_access()}: every method that
     * stores condition trees counts, enabled or not, read through its own API
     * (\local_enrolconditions\enforceable::get_enforcement_tree()), and a value that is not a valid tree is not in
     * force. Deletions are decided by {@see get_spared_fileids()} instead, which also counts the values nothing
     * can evaluate, since a deletion cannot be undone.
     *
     * @param int $courseid
     * @return bool[] Keys are the file ids.
     */
    public static function get_referenced_fileids(int $courseid): array {
        $ids = [];
        foreach (manager::get_course_trees($courseid) as $tree) {
            self::collect_fileids($tree->to_array(), $ids);
        }
        return $ids;
    }

    /**
     * Adds the file ids of the userfile conditions of a node and its children.
     *
     * @param array $node
     * @param array $ids Receives the file ids as keys.
     */
    protected static function collect_fileids(array $node, array &$ids): void {
        if (isset($node['type'])) {
            if ($node['type'] === 'userfile' && (int) ($node['config']['fileid'] ?? 0) > 0) {
                $ids[(int) $node['config']['fileid']] = true;
            }
            return;
        }
        foreach ($node['children'] ?? [] as $child) {
            self::collect_fileids((array) $child, $ids);
        }
    }

    /**
     * File ids a deletion must spare in a course: those named by any stored value, valid tree or not.
     *
     * Every text of {@see manager::get_course_stored_values()} that decodes as JSON is walked at any depth, so a
     * tree that does not validate, or one stored by a method that is not installed, still keeps its files.
     *
     * @param int $courseid
     * @return bool[]|null Keys are the file ids; null when a value names a file but cannot be read, or a method
     *     could not be asked for its value: nothing is known about the course, which is never read as "no file".
     */
    public static function get_spared_fileids(int $courseid): ?array {
        try {
            $texts = manager::get_course_stored_values($courseid);
        } catch (\Throwable $e) {
            return null;
        }
        $ids = [];
        foreach ($texts as $text) {
            $data = json_decode($text, true);
            if (!is_array($data)) {
                // A text that never names a file (a welcome message of a missing method) is no reference. The key is
                // looked for without its quotes: a value encoded twice spells it \"fileid\".
                if (str_contains($text, 'fileid')) {
                    return null;
                }
                continue;
            }
            self::collect_spared_fileids($data, $ids);
        }
        return $ids;
    }

    /**
     * Adds the file ids named by a decoded value, at any depth.
     *
     * A key "fileid" names the file its value casts to with (int), whatever the type of the value, as
     * {@see condition::get_file()} reads it: the cast is silent for every type JSON decodes to, and an array
     * casts to 1, so a node with an array there is evaluated on the file of item 1. The walk also goes into every
     * array value, so a "fileid" under a "fileid" counts too.
     *
     * @param array $data Decoded JSON.
     * @param array $ids Receives the file ids as keys.
     */
    protected static function collect_spared_fileids(array $data, array &$ids): void {
        foreach ($data as $key => $value) {
            if ($key === 'fileid' && (int) $value > 0) {
                $ids[(int) $value] = true;
            }
            if (is_array($value)) {
                self::collect_spared_fileids($value, $ids);
            }
        }
    }

    /**
     * File ids a deletion must spare in a course, read now.
     *
     * @param int $courseid
     * @return bool[]|null Keys are the file ids; null when the course cannot be read, see {@see get_spared_fileids()}.
     */
    protected function read_references(int $courseid): ?array {
        return self::get_spared_fileids($courseid);
    }

    #[\Override]
    public function execute() {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT f.id, f.itemid, ctx.instanceid AS courseid
               FROM {files} f
               JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = :level
              WHERE f.component = :component AND f.filearea = :filearea AND f.filename <> '.'
                AND f.timemodified < :before
           ORDER BY f.id",
            ['level' => CONTEXT_COURSE, 'component' => condition::COMPONENT, 'filearea' => condition::FILEAREA,
                'before' => time() - DAYSECS]
        );
        $referenced = [];
        $fs = get_file_storage();
        $deleted = 0;
        $skipped = [];
        foreach ($records as $record) {
            $courseid = (int) $record->courseid;
            if (!array_key_exists($courseid, $referenced)) {
                $referenced[$courseid] = $this->read_references($courseid);
            }
            if ($referenced[$courseid] === null) {
                $skipped[$courseid] = true;
                continue;
            }
            if (isset($referenced[$courseid][(int) $record->itemid])) {
                continue;
            }
            // The list was read before this file's turn: a tree saved meanwhile may use it now.
            $referenced[$courseid] = $this->read_references($courseid);
            if ($referenced[$courseid] === null) {
                $skipped[$courseid] = true;
                continue;
            }
            if (isset($referenced[$courseid][(int) $record->itemid])) {
                continue;
            }
            $file = $fs->get_file_by_id($record->id);
            if ($file) {
                $hash = $file->get_contenthash();
                $file->delete();
                condition::forget_usernames($hash);
                $deleted++;
            }
        }
        mtrace("$deleted files of usernames deleted.");
        if ($skipped) {
            mtrace('Files of ' . count($skipped) . ' courses kept: a stored condition tree could not be read.');
        }
    }
}
