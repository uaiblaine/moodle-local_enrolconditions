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

namespace local_enrolconditions\condition;

use enrolcond_sqlquery\condition;
use enrolcond_sqlquery\local\queries;

/**
 * Tests of the list from a query condition.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(queries::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\enrolcond_sqlquery\admin\setting_query::class)]
final class sqlquery_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once(__DIR__ . '/../fixtures/counting_refusing_lock_factory.php');
        parent::setUpBeforeClass();
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Queries do not run inside transactions, and a failing query would abort the test transaction.
        $this->preventResetByRollback();
        queries::reset_caches();
    }

    public function test_not_run_inside_transaction(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->define(1, 'All', 'SELECT username FROM {user}');
        // A cache time of 0 runs the query on every call, through run().
        set_config('cachettl', 0, queries::COMPONENT);
        $condition = new condition();
        $instance = (object) ['courseid' => $course->id];
        $transaction = $DB->start_delegated_transaction();
        try {
            $condition->is_met((int) $user->id, $instance, ['query' => '1']);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errortransaction', $e->errorcode);
        }
        $transaction->allow_commit();

        // Control: outside a transaction the same call runs the query.
        $this->assertTrue($condition->is_met((int) $user->id, $instance, ['query' => '1']));
    }

    public function test_cold_cache_is_not_filled_inside_a_transaction(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $sql = 'SELECT username FROM {user}';
        $this->define(1, 'All', $sql);
        set_config('cachettl', MINSECS, queries::COMPONENT);
        $cache = \cache::make(queries::COMPONENT, 'results');
        $key = queries::cache_key($sql, (int) $course->id);

        $transaction = $DB->start_delegated_transaction();
        try {
            queries::get_usernames('1', (int) $course->id);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errortransaction', $e->errorcode);
        }
        $transaction->allow_commit();
        $this->assertFalse($cache->get($key));

        // Control: outside a transaction the cold cache is filled.
        $this->assertArrayHasKey('admin', queries::get_usernames('1', (int) $course->id));
        $this->assertNotFalse($cache->get($key));
    }

    public function test_run_refuses_inside_a_transaction(): void {
        global $DB;
        // The settings page's trial run calls run() directly.
        $transaction = $DB->start_delegated_transaction();
        try {
            queries::run('SELECT username FROM {user}', SITEID, 1);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errortransaction', $e->errorcode);
        }
        $transaction->allow_commit();

        // Control: outside a transaction it runs.
        $this->assertCount(1, queries::run('SELECT username FROM {user}', SITEID, 1));
    }

    /**
     * Forgets the results kept in this request only, as a new request would; the shared cache stays.
     */
    protected function forget_request(): void {
        (new \ReflectionProperty(queries::class, 'results'))->setValue(null, []);
    }

    /**
     * Defines a query in a slot.
     *
     * @param int $slot
     * @param string $name
     * @param string $sql
     */
    protected function define(int $slot, string $name, string $sql): void {
        set_config('query' . $slot . 'name', $name, queries::COMPONENT);
        set_config('query' . $slot . 'sql', $sql, queries::COMPONENT);
    }

    /**
     * Texts that check_text() accepts or refuses.
     *
     * @return array
     */
    public static function check_text_provider(): array {
        return [
            'select' => ["SELECT username FROM {user}", true],
            'with' => ["WITH u AS (SELECT username FROM {user}) SELECT username FROM u", true],
            'lower case and params' => ["select u.username from {user} u where u.id > :courseid", true],
            'not a select' => ["DELETE FROM {user}", false],
            'two statements' => ["SELECT username FROM {user}; DELETE FROM {user}", false],
            'comment' => ["SELECT username FROM {user} -- x", false],
            'block comment' => ["SELECT username /* x */ FROM {user}", false],
            'select into' => ["SELECT username INTO x FROM {user}", false],
            'sleep' => ["SELECT username FROM {user} WHERE pg_sleep(10) IS NULL", false],
            'update in a cte' => ["WITH x AS (UPDATE {user} SET deleted = 1 RETURNING username) SELECT username FROM x", false],
        ];
    }

    /**
     * Test check_text.
     *
     * @param string $sql
     * @param bool $accepted
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('check_text_provider')]
    public function test_check_text(string $sql, bool $accepted): void {
        $this->assertSame($accepted, queries::check_text($sql) === null);
    }

    public function test_is_met(): void {
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user(['username' => 'student']);
        $outsider = $this->getDataGenerator()->create_user(['username' => 'outsider']);
        $this->getDataGenerator()->enrol_user($student->id, $other->id);
        // Users enrolled in any course other than this one.
        $this->define(2, 'Enrolled elsewhere', "SELECT u.username FROM {user} u
            JOIN {user_enrolments} ue ON ue.userid = u.id JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.courseid <> :courseid");
        $condition = new condition();
        $instance = (object) ['courseid' => $course->id];

        $this->assertTrue($condition->is_met((int) $student->id, $instance, ['query' => '2']));
        $this->assertFalse($condition->is_met((int) $outsider->id, $instance, ['query' => '2']));
        $this->assertFalse($condition->is_met((int) $student->id, (object) ['courseid' => $other->id], ['query' => '2']));
        $this->assertSame('You are in the list "Enrolled elsewhere"', $condition->get_summary(['query' => '2']));
        $this->assertSame([], $condition->validate_config(['query' => '2']));
    }

    public function test_cache(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['username' => 'late']);
        $this->define(1, 'Late', "SELECT username FROM {user} WHERE city = 'Late'");
        $condition = new condition();
        $instance = (object) ['courseid' => $course->id];

        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $DB->set_field('user', 'city', 'Late', ['id' => $user->id]);
        // Cached across requests while the time to keep results lasts.
        queries::reset_caches();
        set_config('cachettl', HOURSECS, queries::COMPONENT);
        $this->assertTrue($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $DB->set_field('user', 'city', '', ['id' => $user->id]);
        $this->assertTrue($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        set_config('cachettl', 0, queries::COMPONENT);
        queries::reset_caches();
        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
    }

    public function test_cache_key_is_per_course_only_with_courseid(): void {
        global $DB;
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        set_config('cachettl', HOURSECS, queries::COMPONENT);
        $this->define(1, 'Late', "SELECT username FROM {user} WHERE city = 'Late'");
        $this->define(2, 'Course', "SELECT username FROM {user} WHERE city = 'Late' AND :courseid > 0");
        $condition = new condition();

        $this->assertFalse($condition->is_met((int) $user->id, (object) ['courseid' => $first->id], ['query' => '1']));
        $this->assertFalse($condition->is_met((int) $user->id, (object) ['courseid' => $first->id], ['query' => '2']));
        $DB->set_field('user', 'city', 'Late', ['id' => $user->id]);
        $this->forget_request();

        // Without :courseid the list of the first course is the list of every course: stored once.
        $this->assertFalse($condition->is_met((int) $user->id, (object) ['courseid' => $second->id], ['query' => '1']));
        // Control: with :courseid each course has its own entry, so the second course runs the query.
        $this->assertTrue($condition->is_met((int) $user->id, (object) ['courseid' => $second->id], ['query' => '2']));
        $this->assertSame(
            sha1("SELECT username FROM {user} WHERE city = 'Late'"),
            queries::cache_key("SELECT username FROM {user} WHERE city = 'Late'", (int) $second->id)
        );
    }

    public function test_expired_entry_is_refreshed_by_one_request(): void {
        global $CFG, $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $sql = "SELECT username FROM {user} WHERE city = 'Late'";
        set_config('cachettl', MINSECS, queries::COMPONENT);
        $this->define(1, 'Late', $sql);
        $condition = new condition();
        $instance = (object) ['courseid' => $course->id];
        $cache = \cache::make(queries::COMPONENT, 'results');
        $key = queries::cache_key($sql, (int) $course->id);

        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $DB->set_field('user', 'city', 'Late', ['id' => $user->id]);
        $expire = function () use ($cache, $key): void {
            $entry = $cache->get($key);
            $entry['time'] -= 2 * MINSECS;
            $cache->set($key, $entry);
            $this->forget_request();
        };

        // Another node holds the refresh lock: the expired entry is served and the query does not run.
        $expire();
        $CFG->lock_factory = '\\local_enrolconditions\\counting_refusing_lock_factory';
        \local_enrolconditions\counting_refusing_lock_factory::$asked = 0;
        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $this->assertSame(1, \local_enrolconditions\counting_refusing_lock_factory::$asked);

        // Nothing to serve and the lock still held: unknown, not a stampede of queries.
        $cache->delete($key);
        $this->forget_request();
        try {
            $condition->is_met((int) $user->id, $instance, ['query' => '1']);
            $this->fail('errorbusy expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorbusy', $e->errorcode);
        }

        // Control: with the lock free, this request refreshes the entry.
        $CFG->lock_factory = 'auto';
        $this->assertTrue($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $this->assertArrayHasKey(\core_text::strtolower($user->username), $cache->get($key)['usernames']);
    }

    public function test_missing_or_failing_query_is_unknown(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = (object) ['id' => 1, 'courseid' => $course->id];
        $this->define(1, 'Broken', "SELECT nothing FROM {user}");

        $tree = \local_enrolconditions\tree::from_array(['op' => 'and', 'children' => [
            ['type' => 'sqlquery', 'config' => ['query' => '1']],
        ]]);
        $this->assertTrue($tree->evaluate((int) $user->id, $instance)->is_unknown());
        $this->assertDebuggingCalled();

        $tree = \local_enrolconditions\tree::from_array(['op' => 'and', 'children' => [
            ['type' => 'sqlquery', 'config' => ['query' => '9']],
        ]]);
        $this->assertTrue($tree->evaluate((int) $user->id, $instance)->is_unknown());
        $this->assertDebuggingCalled();
        $this->assertSame('You are in a list that no longer exists', (new condition())->get_summary(['query' => '9']));
        $this->assertArrayHasKey('query', (new condition())->validate_config(['query' => '9']));
    }

    public function test_setting_validation(): void {
        $setting = new \enrolcond_sqlquery\admin\setting_query('enrolcond_sqlquery/query1sql', 'Query', '');
        $this->assertTrue($setting->validate(''));
        $this->assertTrue($setting->validate('SELECT username FROM {user} WHERE id = :courseid'));
        $this->assertSame(get_string('errorselect', queries::COMPONENT), $setting->validate('DELETE FROM {user}'));
        $this->assertStringStartsWith('The query failed', $setting->validate('SELECT nothing FROM {user}'));
    }

    public function test_slots_and_form(): void {
        $this->assertSame(queries::DEFAULT_SLOTS, queries::get_slots());
        set_config('slots', 100, queries::COMPONENT);
        $this->assertSame(queries::MAX_SLOTS, queries::get_slots());
        $this->define(1, 'Without SQL', '');
        $this->define(3, 'Teachers', 'SELECT username FROM {user}');
        $this->assertSame(['3'], array_map('strval', array_keys(queries::get_queries())));

        $form = new \MoodleQuickForm('test', 'post', '');
        (new condition())->config_form_definition($form);
        $this->assertTrue($form->elementExists('query'));
        $this->assertFalse($form->elementExists('noqueries'));
        $this->assertSame(['query' => '3'], (new condition())->config_from_form_data((object) ['query' => 3]));
    }

    public function test_privacy_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('enrolcond_sqlquery'));
    }

    public function test_cached_path_inside_a_transaction_serves_the_expired_entry(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $sql = "SELECT username FROM {user} WHERE city = 'Late'";
        set_config('cachettl', MINSECS, queries::COMPONENT);
        $this->define(1, 'Late', $sql);
        $condition = new condition();
        $instance = (object) ['courseid' => $course->id];
        $cache = \cache::make(queries::COMPONENT, 'results');
        $key = queries::cache_key($sql, (int) $course->id);

        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $DB->set_field('user', 'city', 'Late', ['id' => $user->id]);
        $entry = $cache->get($key);
        $entry['time'] -= 2 * MINSECS;
        $cache->set($key, $entry);
        $this->forget_request();

        // Inside the caller's transaction the expired list is served and the query does not run.
        $transaction = $DB->start_delegated_transaction();
        $this->assertFalse($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $transaction->allow_commit();
        $this->assertSame($entry['time'], $cache->get($key)['time']);

        // Control: outside it, the same call refreshes the entry.
        $this->forget_request();
        $this->assertTrue($condition->is_met((int) $user->id, $instance, ['query' => '1']));
        $this->assertGreaterThan($entry['time'], $cache->get($key)['time']);
    }

    public function test_expired_entry_is_deleted_after_a_failed_refresh(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_user(['username' => 'ana']);
        $broken = "SELECT nothing FROM {user}";
        $working = "SELECT username FROM {user} WHERE username = 'ana'";
        set_config('cachettl', MINSECS, queries::COMPONENT);
        $this->define(1, 'Broken', $broken);
        $this->define(2, 'Working', $working);
        $cache = \cache::make(queries::COMPONENT, 'results');
        $expired = ['time' => time() - 2 * MINSECS, 'usernames' => ['ana' => true]];
        $brokenkey = queries::cache_key($broken, (int) $course->id);
        $cache->set($brokenkey, $expired);

        try {
            queries::get_usernames('1', (int) $course->id);
            $this->fail('The broken query must fail');
        } catch (\Throwable $e) {
            $this->assertFalse($cache->get($brokenkey));
        }

        // Control: a refresh that works replaces the expired entry.
        $workingkey = queries::cache_key($working, (int) $course->id);
        $cache->set($workingkey, ['time' => time() - 2 * MINSECS, 'usernames' => []]);
        $this->assertSame(['ana' => true], queries::get_usernames('2', (int) $course->id));
        $this->assertSame(['ana' => true], $cache->get($workingkey)['usernames']);
    }
}
