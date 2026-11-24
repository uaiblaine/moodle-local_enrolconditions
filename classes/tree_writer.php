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

use stdClass;

/**
 * An enrolment method that lets the engine write back the condition tree of an instance. Optional.
 *
 * Implemented, besides \local_enrolconditions\enforceable, by the plugin class of a method that stores condition
 * trees. The engine writes a tree only to erase personal data from it (the usernames of a list of usernames
 * condition, on a privacy request); a method that does not implement it keeps its trees as they are, and the
 * privacy provider of that condition says so.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tree_writer {
    /**
     * Stores this already validated tree for the instance, without re-running the reach check, and purges what
     * depends on it.
     *
     * The tree was read through enforceable::get_enforcement_tree() and changed by the engine (for example, a
     * username removed from a list): do not validate it again for the current user, who may be the privacy
     * system's own user. Write the column the method reads the tree from, then purge whatever depends on it (the
     * instance caches of the method, and \local_enrolconditions\local\enforcer::purge_event_map()).
     *
     * @param stdClass $instance Record of the enrol table, as read before the change.
     * @param string $json Tree as \local_enrolconditions\tree::to_json() returns it.
     */
    public function set_enforcement_tree(stdClass $instance, string $json): void;
}
