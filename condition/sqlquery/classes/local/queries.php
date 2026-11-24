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

namespace enrolcond_sqlquery\local;

/**
 * Queries defined by the administrator, and their results.
 *
 * A query is a single SELECT (or WITH ... SELECT) that returns a column "username". It may use the parameter
 * :courseid, the course of the enrolment method. Only administrators (moodle/site:config) define queries; teachers
 * only choose one by its name, and every teacher who can configure an enrolment instance can use every list (and
 * learn from the outcome whether a user is in it).
 *
 * check_text() is a guard against accidents, not a security boundary: a keyword denylist over SQL the administrator
 * wrote cannot prove a statement read-only. The queries run with Moodle's database permissions; review them as code.
 *
 * Results are kept in the MUC application cache 'results' (shared by every node) for the configured time, keyed by
 * the SQL alone when it has no :courseid and by the SQL and the course otherwise. One node refreshes an expired
 * entry under a lock while the others serve the expired one.
 *
 * @package    enrolcond_sqlquery
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queries {
    /** @var string Component. */
    public const COMPONENT = 'enrolcond_sqlquery';

    /** @var int Default number of query slots in the settings. */
    public const DEFAULT_SLOTS = 3;

    /** @var int Maximum number of query slots. */
    public const MAX_SLOTS = 20;

    /** @var string Words that a query cannot contain. */
    public const FORBIDDEN = '/\b(insert|update|delete|drop|alter|create|truncate|grant|revoke|copy|call|exec|execute|merge|'
        . 'replace|lock|unlock|vacuum|analyze|set|reset|load|handler|rename|comment|into|outfile|dumpfile|pg_sleep|sleep|'
        . 'benchmark|dblink|lo_import|lo_export|pg_read_file|pg_read_binary_file|pg_ls_dir)\b/i';

    /** @var array[] Results by query and course, in this request. */
    protected static array $results = [];

    /** @var int Seconds a request waits for another node's refresh when it has no result to serve meanwhile. */
    public const LOCK_WAIT = 10;

    /**
     * Number of query slots.
     *
     * @return int
     */
    public static function get_slots(): int {
        $slots = (int) get_config(self::COMPONENT, 'slots');
        return min(self::MAX_SLOTS, max(1, $slots ?: self::DEFAULT_SLOTS));
    }

    /**
     * Queries with a name and SQL.
     *
     * @return array[] ['name' => ..., 'sql' => ...] by slot key ("1", "2"...).
     */
    public static function get_queries(): array {
        $queries = [];
        for ($i = 1; $i <= self::get_slots(); $i++) {
            $name = trim((string) get_config(self::COMPONENT, 'query' . $i . 'name'));
            $sql = trim((string) get_config(self::COMPONENT, 'query' . $i . 'sql'));
            if ($name !== '' && $sql !== '') {
                $queries[(string) $i] = ['name' => $name, 'sql' => $sql];
            }
        }
        return $queries;
    }

    /**
     * Problem with the text of a query, without running it.
     *
     * @param string $sql
     * @return string|null Error message, or null when the text is acceptable.
     */
    public static function check_text(string $sql): ?string {
        $sql = trim($sql);
        if (!preg_match('/^(select|with)\b/i', $sql)) {
            return get_string('errorselect', self::COMPONENT);
        }
        if (str_contains($sql, ';')) {
            return get_string('errorsemicolon', self::COMPONENT);
        }
        // Comments could hide the rest of the wrapping query.
        if (str_contains($sql, '--') || str_contains($sql, '/*') || str_contains($sql, '#')) {
            return get_string('errorcomment', self::COMPONENT);
        }
        if (preg_match(self::FORBIDDEN, $sql, $matches)) {
            return get_string('errorforbidden', self::COMPONENT, $matches[1]);
        }
        return null;
    }

    /**
     * Parameters of a query.
     *
     * @param string $sql
     * @param int $courseid
     * @return array
     */
    protected static function params(string $sql, int $courseid): array {
        return preg_match('/:courseid\b/', $sql) ? ['courseid' => $courseid] : [];
    }

    /**
     * Runs a query. Refuses while a transaction is open.
     *
     * @param string $sql
     * @param int $courseid
     * @param int $limit 0 for all rows.
     * @return bool[] Usernames in lower case, as keys.
     * @throws \moodle_exception When the text is not acceptable, the query fails or a transaction is open.
     */
    public static function run(string $sql, int $courseid, int $limit = 0): array {
        global $DB;
        // The one place that refuses to run the admin's SQL inside the caller's transaction: every path that runs it
        // ends here, and a failing query would abort that transaction (for example, an event observer's).
        if ($DB->is_transaction_started()) {
            throw new \moodle_exception('errortransaction', self::COMPONENT);
        }
        $error = self::check_text($sql);
        if ($error !== null) {
            throw new \moodle_exception('errorquery', self::COMPONENT, '', $error);
        }
        $rs = $DB->get_recordset_sql(
            'SELECT DISTINCT enrolcondq.username FROM (' . $sql . ') enrolcondq',
            self::params($sql, $courseid),
            0,
            $limit
        );
        $usernames = [];
        foreach ($rs as $row) {
            $usernames[\core_text::strtolower((string) $row->username)] = true;
        }
        $rs->close();
        return $usernames;
    }

    /**
     * Cache key of the results of a query for a course.
     *
     * @param string $sql
     * @param int $courseid
     * @return string The SQL hash alone when the query does not use :courseid, so its one list is run and stored
     *     once for every course.
     */
    public static function cache_key(string $sql, int $courseid): string {
        return self::params($sql, $courseid) ? sha1($sql) . '_' . $courseid : sha1($sql);
    }

    /**
     * Usernames returned by a defined query for a course, cached for the configured time.
     *
     * With a cache time of 0 the query runs on every call. Otherwise an expired or missing entry is refreshed by one
     * request at a time, under a core lock (every node shares it): while one runs the query, the others serve the
     * expired entry, or wait up to LOCK_WAIT seconds when there is none.
     *
     * @param string $key Slot key.
     * @param int $courseid
     * @return bool[] Usernames in lower case, as keys.
     * @throws \moodle_exception When the query no longer exists or fails, inside a transaction with nothing to serve,
     *     or when another refresh holds the lock past the wait.
     */
    public static function get_usernames(string $key, int $courseid): array {
        $queries = self::get_queries();
        if (!isset($queries[$key])) {
            throw new \moodle_exception('errormissing', self::COMPONENT, '', $key);
        }
        $sql = $queries[$key]['sql'];
        $cachekey = self::cache_key($sql, $courseid);
        if (isset(self::$results[$cachekey])) {
            return self::$results[$cachekey];
        }
        $ttl = (int) get_config(self::COMPONENT, 'cachettl');
        $usernames = $ttl > 0 ? self::get_cached($sql, $courseid, $cachekey, $ttl) : self::run($sql, $courseid);
        self::$results = [$cachekey => $usernames] + array_slice(self::$results, 0, 9, true);
        return $usernames;
    }

    /**
     * Results from the shared cache, refreshed by one request at a time.
     *
     * @param string $sql
     * @param int $courseid
     * @param string $cachekey
     * @param int $ttl Configured cache time, in seconds.
     * @return bool[] Usernames as keys.
     * @throws \moodle_exception
     */
    protected static function get_cached(string $sql, int $courseid, string $cachekey, int $ttl): array {
        global $DB;
        $cache = \cache::make(self::COMPONENT, 'results');
        $cached = $cache->get($cachekey);
        if ($cached !== false && $cached['time'] > time() - $ttl) {
            return $cached['usernames'];
        }
        if ($cached !== false && $DB->is_transaction_started()) {
            // Not refreshed inside the caller's transaction (run() refuses it): the expired entry is served.
            return $cached['usernames'];
        }

        $factory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $factory->get_lock($cachekey, $cached !== false ? 0 : self::LOCK_WAIT);
        if (!$lock) {
            // Another request is refreshing it: serve what there is.
            $cached = $cached !== false ? $cached : $cache->get($cachekey);
            if ($cached !== false) {
                return $cached['usernames'];
            }
            throw new \moodle_exception('errorbusy', self::COMPONENT);
        }
        try {
            // The request that held the lock may have refreshed it meanwhile.
            $fresh = $cache->get($cachekey);
            if ($fresh !== false && $fresh['time'] > time() - $ttl) {
                return $fresh['usernames'];
            }
            try {
                $usernames = self::run($sql, $courseid);
            } catch (\Throwable $e) {
                // An expired entry is not served after the query failed: the next request tries again.
                $cache->delete($cachekey);
                throw $e;
            }
            $cache->set($cachekey, ['time' => time(), 'usernames' => $usernames]);
            return $usernames;
        } finally {
            $lock->release();
        }
    }

    /**
     * Forgets the results kept in this request, for tests.
     */
    public static function reset_caches(): void {
        self::$results = [];
        \cache::make(self::COMPONENT, 'results')->purge();
    }
}
