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

namespace enrolcond_userfile\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrolcond_userfile\condition;
use local_enrolconditions\local\usernames;

/**
 * Privacy provider: the files of usernames name the users they list.
 *
 * A user is found by their username in the files of each course, or, once their account is deleted, by the
 * md5 of a listed name that core keeps in their email column. Deleting the data of a user removes their line
 * from the files and drops the cached list of the old content.
 *
 * @package    enrolcond_userfile
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        return $collection;
    }

    /**
     * Files of usernames, optionally of one context.
     *
     * @param int|null $contextid
     * @return \stored_file[]
     */
    protected static function get_files(?int $contextid = null): array {
        global $DB;
        $params = ['component' => condition::COMPONENT, 'filearea' => condition::FILEAREA];
        $where = "component = :component AND filearea = :filearea AND filename <> '.'";
        if ($contextid !== null) {
            $where .= ' AND contextid = :contextid';
            $params['contextid'] = $contextid;
        }
        $fs = get_file_storage();
        $files = [];
        foreach ($DB->get_records_select('files', $where, $params, 'id') as $record) {
            $files[] = $fs->get_file_instance($record);
        }
        return $files;
    }

    /**
     * How the lines of a user are recognised in the files.
     *
     * A deleted account no longer has its username: core's delete_user() replaces it and keeps
     * md5(original username) in the email column, and tool_dataprivacy files deletion requests for users already
     * deleted. Such a user is matched by the hash of each listed name.
     *
     * @param int $userid
     * @return array|null ['username' => lower-case username or null, 'hash' => md5 of the original username or null];
     *     null when the user does not exist.
     */
    protected static function get_matcher(int $userid): ?array {
        $user = \core_user::get_user($userid, 'id, username, email, deleted');
        if (!$user) {
            return null;
        }
        if (!empty($user->deleted)) {
            $hash = \core_text::strtolower((string) $user->email);
            return ['username' => null, 'hash' => preg_match('/^[0-9a-f]{32}$/', $hash) ? $hash : null];
        }
        return ['username' => \core_text::strtolower($user->username), 'hash' => null];
    }

    /**
     * Name under which a user is listed in a list of usernames.
     *
     * @param array $list Keys are usernames in lower case.
     * @param array $matcher Result of get_matcher().
     * @return string|null The listed name; null when the user is not listed.
     */
    protected static function find_in_list(array $list, array $matcher): ?string {
        if ($matcher['username'] !== null) {
            return isset($list[$matcher['username']]) ? $matcher['username'] : null;
        }
        if ($matcher['hash'] === null) {
            return null;
        }
        foreach (array_keys($list) as $name) {
            if (md5((string) $name) === $matcher['hash']) {
                return (string) $name;
            }
        }
        return null;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $matcher = self::get_matcher($userid);
        $contextids = [];
        foreach ($matcher === null ? [] : self::get_files() as $file) {
            if (isset($contextids[$file->get_contextid()])) {
                continue;
            }
            if (self::find_in_list(condition::get_usernames($file), $matcher) !== null) {
                $contextids[$file->get_contextid()] = true;
            }
        }
        if ($contextids) {
            global $DB;
            [$insql, $params] = $DB->get_in_or_equal(array_keys($contextids), SQL_PARAMS_NAMED);
            $contextlist->add_from_sql("SELECT id FROM {context} WHERE id $insql", $params);
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $listed = [];
        foreach (self::get_files($userlist->get_context()->id) as $file) {
            $listed += condition::get_usernames($file);
        }
        foreach (array_chunk(array_map('strval', array_keys($listed)), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $userlist->add_from_sql('id', "SELECT id FROM {user} WHERE deleted = 0 AND username $insql", $params);
            // Deleted accounts keep md5(original username) in email ({@see self::get_matcher()}).
            [$insql, $params] = $DB->get_in_or_equal(array_map('md5', $chunk), SQL_PARAMS_NAMED);
            $userlist->add_from_sql('id', "SELECT id FROM {user} WHERE deleted = 1 AND email $insql", $params);
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        $matcher = self::get_matcher((int) $contextlist->get_user()->id);
        if ($matcher === null) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            $names = [];
            $username = null;
            foreach (self::get_files($context->id) as $file) {
                $found = self::find_in_list(condition::get_usernames($file), $matcher);
                if ($found !== null) {
                    $names[] = $file->get_filename();
                    $username = $found;
                }
            }
            if ($names) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', condition::COMPONENT)],
                    (object) ['username' => $username, 'files' => $names]
                );
            }
        }
    }

    /**
     * Removes the lines of some users from the files of a context.
     *
     * The cached list of each rewritten or deleted content is dropped as well, on every node.
     *
     * @param int $contextid
     * @param array|null $matchers Results of get_matcher(); null removes the files.
     */
    protected static function remove_from_files(int $contextid, ?array $matchers): void {
        $names = [];
        $hashes = [];
        foreach ($matchers ?? [] as $matcher) {
            if ($matcher['username'] !== null) {
                $names[$matcher['username']] = true;
            } else if ($matcher['hash'] !== null) {
                $hashes[$matcher['hash']] = true;
            }
        }
        foreach (self::get_files($contextid) as $file) {
            $oldhash = $file->get_contenthash();
            if ($matchers === null) {
                $file->delete();
                condition::forget_usernames($oldhash);
                continue;
            }
            $lines = preg_split('/\r\n|\r|\n/', $file->get_content());
            $kept = array_filter($lines, function (string $line) use ($names, $hashes): bool {
                $value = usernames::first_value($line);
                if ($value === null) {
                    return true;
                }
                return !isset($names[$value]) && !($hashes && isset($hashes[md5($value)]));
            });
            if (count($kept) === count($lines)) {
                continue;
            }
            $record = [
                'contextid' => $file->get_contextid(),
                'component' => $file->get_component(),
                'filearea' => $file->get_filearea(),
                'itemid' => $file->get_itemid(),
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ];
            $file->delete();
            get_file_storage()->create_file_from_string($record, implode("\n", $kept));
            condition::forget_usernames($oldhash);
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        self::remove_from_files($context->id, null);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $matcher = self::get_matcher((int) $contextlist->get_user()->id);
        if ($matcher === null) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            self::remove_from_files($context->id, [$matcher]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $matchers = array_filter(array_map(fn($userid) => self::get_matcher((int) $userid), $userlist->get_userids()));
        if ($matchers) {
            self::remove_from_files($userlist->get_context()->id, array_values($matchers));
        }
    }
}
