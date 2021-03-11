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
 * @package    report_examreports
 * @copyright  2021 Jan Kłudkiewicz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function report_examreports_extend_navigation_course($navigation, $course, $context) {
	if (has_capability('moodle/grade:export', $context)) {
		$url = new moodle_url('/report/examreports/finalreport.php', array('id' => $course->id));
		$name = get_string('finalreport', 'report_examreports');
		$navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
		$url = new moodle_url('/report/examreports/progressreport.php', array('id' => $course->id));
		$name = get_string('progressreport', 'report_examreports');
		$navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
	}
}

/**
 * This function extends the module navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $cm
 */
function report_examreports_extend_navigation_module($navigation, $cm) {
    $context = context_module::instance($cm->id);
    if ($cm->modname == 'assign' && has_capability('moodle/grade:export', $context)) {
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
        switch ($gradingmanager->get_active_method()) {
            case 'rubric':
                $url = new moodle_url('/report/examreports/assessmentreport.php', array('id'=>$cm->course,'modid'=>$cm->id));
                $navigation->add(get_string('assessmentreport', 'report_examreports'), $url, navigation_node::TYPE_SETTING, null, 'assessmentreport');
                break;
            }
    }
}
?>