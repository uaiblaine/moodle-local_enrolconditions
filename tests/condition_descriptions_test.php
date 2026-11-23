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

namespace local_enrolconditions;

use local_enrolconditions\condition\manager;

/**
 * Every condition's description is escaped: the failure list prints it raw.
 *
 * templates/failures_list.mustache prints each message with a triple stash, and the tree editor puts summaries in
 * innerHTML, on the contract that base::get_description() returns text already escaped or cleaned. This test holds
 * that contract for every installed enrolcond subplugin, enumerated from the tree: a new subplugin without a fixture
 * here fails until one is written.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(condition\base::class)]
final class condition_descriptions_test extends \advanced_testcase {
    /** @var string Value with markup, put in every name, file name, field value and query name. */
    protected const MARKUP = '<b>x</b><script>alert(1)</script><img src=x onerror=alert(2)>';

    /**
     * Configurations of a type whose descriptions show values a user or teacher wrote.
     *
     * @param string $type Subplugin name.
     * @return array|null Configurations; null when the test has no fixture for the type.
     */
    protected function configs(string $type): ?array {
        $generator = $this->getDataGenerator();
        switch ($type) {
            case 'cohort':
                return [['cohortid' => (int) $generator->create_cohort(['name' => self::MARKUP])->id]];
            case 'coursecompleted':
                return [['courseid' => (int) $generator->create_course(['fullname' => self::MARKUP])->id]];
            case 'courseenrolled':
                $ids = [
                    (int) $generator->create_course(['fullname' => self::MARKUP])->id,
                    (int) $generator->create_course(['fullname' => self::MARKUP])->id,
                ];
                return [
                    ['courseids' => [$ids[0]], 'mode' => 'any'],
                    ['courseids' => $ids, 'mode' => 'all'],
                    ['courseids' => $ids, 'mode' => 'any'],
                ];
            case 'date':
                return [['from' => 1, 'to' => 0], ['from' => 0, 'to' => 2], ['from' => 1, 'to' => 2]];
            case 'profile':
                $generator->create_custom_profile_field([
                    'datatype' => 'text',
                    'shortname' => 'markup',
                    'name' => self::MARKUP,
                ]);
                $configs = [];
                foreach (['isequalto', 'contains', 'doesnotcontain', 'startswith', 'endswith', 'isempty'] as $operator) {
                    $configs[] = ['field' => 'profile_field_markup', 'operator' => $operator, 'value' => self::MARKUP];
                    $configs[] = ['field' => 'city', 'operator' => $operator, 'value' => self::MARKUP];
                }
                return $configs;
            case 'sqlquery':
                set_config('query1name', self::MARKUP, 'enrolcond_sqlquery');
                set_config('query1sql', 'SELECT username FROM {user}', 'enrolcond_sqlquery');
                return [['query' => '1']];
            case 'userfile':
                return [['fileid' => 5, 'filename' => self::MARKUP . '.csv', 'count' => 3]];
            case 'userlist':
                return [['usernames' => ['ana', 'bia']]];
        }
        return null;
    }

    public function test_every_description_is_escaped(): void {
        $this->resetAfterTest();
        manager::reset_caches();
        $types = array_keys(\core_component::get_plugin_list('enrolcond'));
        $this->assertNotEmpty($types);
        foreach ($types as $type) {
            $condition = manager::get_condition($type);
            $this->assertNotNull($condition, "enrolcond_{$type} provides no condition");
            $configs = $this->configs($type);
            $this->assertNotNull($configs, "No fixture with markup for enrolcond_{$type}: add one to this test");
            foreach ($configs as $config) {
                $texts = [
                    $condition->get_description($config, false),
                    $condition->get_description($config, true),
                    $condition->get_summary($config),
                ];
                foreach ($texts as $text) {
                    $this->assertNotSame('', $text);
                    foreach (['<b', '<script', '<img'] as $raw) {
                        $this->assertStringNotContainsString($raw, $text, "enrolcond_{$type}: " . json_encode($config));
                    }
                }
            }
        }
        // Control: the markup reaches the text, escaped, where the description shows a teacher's value.
        $this->assertStringContainsString(
            '&lt;b&gt;x&lt;/b&gt;',
            manager::get_condition('userfile')->get_description(['filename' => self::MARKUP, 'count' => 1], false)
        );
    }
}
