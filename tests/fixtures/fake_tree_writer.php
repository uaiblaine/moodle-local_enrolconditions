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

/**
 * The fake enrolment method that also writes condition trees back, for tests.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fake_enforceable.php');

/**
 * The fake method that also lets the engine write a tree back, as \local_enrolconditions\tree_writer asks.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_enrolconditions_fake_tree_writer extends local_enrolconditions_fake_enforceable implements
    \local_enrolconditions\tree_writer {
    #[\Override]
    public function set_enforcement_tree(stdClass $instance, string $json): void {
        global $DB;
        $DB->set_field('enrol', 'customtext1', $json, ['id' => $instance->id]);
        \local_enrolconditions\local\enforcer::purge_event_map();
    }
}
