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

namespace local_enrolconditions\local;

use core_text;

/**
 * Reads lists of usernames, for the conditions that use them.
 *
 * Usernames are compared in lower case, as Moodle stores them.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usernames {
    /**
     * Usernames typed in a text: separated by new lines, spaces, commas or semicolons.
     *
     * @param string $text
     * @return string[] Unique usernames, in the order they appear.
     */
    public static function parse_list(string $text): array {
        return self::unique(preg_split('/[\s,;]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Usernames in the first column of a CSV or text file. A first line "username" is a header.
     *
     * @param string $content
     * @return string[] Unique usernames, in the order they appear.
     */
    public static function parse_csv(string $content): array {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $values = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $i => $line) {
            $first = self::first_value($line);
            if ($first === null || ($i === 0 && $first === 'username')) {
                continue;
            }
            $values[] = $first;
        }
        return self::unique($values);
    }

    /**
     * Username in the first column of a line of a CSV or text file.
     *
     * @param string $line
     * @return string|null In lower case; null for an empty line.
     */
    public static function first_value(string $line): ?string {
        $first = core_text::strtolower(trim((string) preg_split('/[,;\t]/', $line, 2)[0], " \t\"'"));
        return $first === '' ? null : $first;
    }

    /**
     * Values that are not valid usernames.
     *
     * @param string[] $usernames
     * @return string[]
     */
    public static function invalid(array $usernames): array {
        return array_values(array_filter(
            $usernames,
            fn(string $username): bool => clean_param($username, PARAM_USERNAME) !== $username
        ));
    }

    /**
     * How the name of a user is recognised in a list of usernames, for the privacy providers.
     *
     * A deleted account no longer has its username: core's delete_user() replaces it and keeps
     * md5(original username) in the email column, and tool_dataprivacy files deletion requests for users already
     * deleted. Such a user is matched by the hash of each listed name.
     *
     * @param int $userid
     * @return array|null ['username' => lower-case username or null, 'hash' => md5 of the original username or null];
     *     null when the user does not exist.
     */
    public static function matcher(int $userid): ?array {
        $user = \core_user::get_user($userid, 'id, username, email, deleted');
        if (!$user) {
            return null;
        }
        if (!empty($user->deleted)) {
            $hash = core_text::strtolower((string) $user->email);
            return ['username' => null, 'hash' => preg_match('/^[0-9a-f]{32}$/', $hash) ? $hash : null];
        }
        return ['username' => core_text::strtolower($user->username), 'hash' => null];
    }

    /**
     * Name under which a user is listed in a list of usernames.
     *
     * @param array $list Keys are usernames in lower case.
     * @param array $matcher Result of matcher().
     * @return string|null The listed name; null when the user is not listed.
     */
    public static function find(array $list, array $matcher): ?string {
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

    /**
     * Whether a listed name is one of some users.
     *
     * @param string $name Listed name, in lower case.
     * @param array $matchers Results of matcher().
     * @return bool
     */
    public static function names_any(string $name, array $matchers): bool {
        foreach ($matchers as $matcher) {
            if (self::find([$name => true], $matcher) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Adds to a list of users of a context the accounts that some listed names belong to, deleted ones included.
     *
     * @param \core_privacy\local\request\userlist $userlist
     * @param string[] $names Listed names, in lower case.
     */
    public static function add_listed_users(\core_privacy\local\request\userlist $userlist, array $names): void {
        global $DB;
        foreach (array_chunk(array_values(array_unique(array_map('strval', $names))), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $userlist->add_from_sql('id', "SELECT id FROM {user} WHERE deleted = 0 AND username $insql", $params);
            // Deleted accounts keep md5(original username) in email ({@see self::matcher()}).
            [$insql, $params] = $DB->get_in_or_equal(array_map('md5', $chunk), SQL_PARAMS_NAMED);
            $userlist->add_from_sql('id', "SELECT id FROM {user} WHERE deleted = 1 AND email $insql", $params);
        }
    }

    /**
     * Unique values in lower case.
     *
     * @param string[] $values
     * @return string[]
     */
    protected static function unique(array $values): array {
        $unique = [];
        foreach ($values as $value) {
            $value = core_text::strtolower(trim($value));
            if ($value !== '') {
                $unique[$value] = true;
            }
        }
        return array_map('strval', array_keys($unique));
    }
}
