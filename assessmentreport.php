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
 
define('TIME_CREATED', time());
 
define('FORMATIVE_ASSESSMENT_CATEGORY', 34);
define('SUMMATIVE_ASSESSMENT_CATEGORY', 35);
 
define('MARGIN_LEFT', 24);
define('MARGIN_RIGHT', 15);
define('MARGIN_TOP', 35);
define('MARGIN_BOTTOM', 10);
define('FONT_FAMILY', 'arial');
define('FONT_SIZE', 12);

global $CFG;
global $DB;

require('../../config.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot.'/lib/pdflib.php');

$id          = required_param('id', PARAM_INT); // Course ID
$modid       = required_param('modid', PARAM_INT); // CM ID

$PAGE->set_url('/report/componentgrades/index.php', array('id' => $id, 'modid' => $modid));

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

require_login($course);

$modinfo = get_fast_modinfo($course->id);
$cm = $modinfo->get_cm($modid);

$context = context_module::instance($cm->id);
require_capability('moodle/grade:export', $context);

$grades = $DB->get_records_sql("SELECT student.id AS userid, student.firstname AS studentfirstname, student.lastname AS studentlastname, grades.feedback AS feedback, grades.rawgrade, grades.rawgrademax, grades.rawgrademin, grader.firstname AS graderfirstname, grader.lastname AS graderlastname, grades.timemodified AS timegraded, categories.id AS categoryid 
								FROM {course} AS course
								JOIN {course_modules} modules ON course.id = modules.course
								JOIN {assign} assign ON modules.instance = assign.id
								JOIN {grade_items} items ON assign.id = items.iteminstance 
								JOIN {grade_categories} categories ON items.categoryid = categories.id
								JOIN {grade_grades} grades ON items.id = grades.itemid
								JOIN {user} grader ON grades.usermodified = grader.id
								JOIN {user} student ON grades.userid = student.id
								WHERE items.itemmodule = 'assign' AND modules.id = ?", array($cm->id));

$first = reset($grades);
if ($first === false) {
    $url = $CFG->wwwroot.'/mod/assign/view.php?id='.$cm->id;
    $message = "No grades have been entered into this assignment's rubric.";
    redirect($url, $message, 5);
    exit;
}

if ($first->categoryid==FORMATIVE_ASSESSMENT_CATEGORY) {
	$assessment_type = "Formative_Assessment";
	$title_header = "PROTOKÓŁ OCENY KSZTAŁTUJĄCEJ";
	$assessment_type_text = "ocenie kształtującej";
}
if ($first->categoryid==SUMMATIVE_ASSESSMENT_CATEGORY) {
	$assessment_type = "Summative_Assessment";
	$title_header = "PROTOKÓŁ OCENY PODSUMOWUJĄCEJ";
	$assessment_type_text = "ocenie podsumowującej";
}

class pr_pdf extends pdf {

    //Page header
    public function Header() {
		global $title_header;
		// Date
		$this->SetFont(FONT_FAMILY, 'B', 12);
        $this->Cell(0, 40, $title_header);
		
        // Logo
        $image_file = 'positiverate.png';
        $this->Image($image_file, 140, 10, 0, 15, 'PNG');
    }
	
	public function Footer() {
		// Position at 1.5 cm from bottom
		$this->SetY(-15);
		$this->SetFont(FONT_FAMILY, 'B', 10);
		$this->Cell(0,10, 'Druk: 1/PR/TF',0,0,'R');
	}
}

$doc = new pr_pdf;
$doc->setPrintHeader(true);
$doc->setPrintFooter(true);
$doc->SetMargins(MARGIN_LEFT, MARGIN_TOP, MARGIN_RIGHT);
$doc->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$doc->SetFont(FONT_FAMILY, '', FONT_SIZE);

foreach ($grades as $grade) {
	$doc->AddPage();
	
	$html = '<p style="text-align: justified;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Dnia <b>'.date("d.m.Y",$grade->timegraded).'</b> (data), uczestnik szkolenia <b>'.$grade->studentfirstname.' '.$grade->studentlastname.'</b> (imię i nazwisko) został poddany '.$assessment_type_text.', w ćwiczeniu <b>'.$cm->name.'</b> (nazwa ćwiczenia), podczas szkolenia <b>'.$course->fullname.'</b> (rodzaj szkolenia) nr <b>'.$course->shortname.'</b> (numer szkolenia). Oceny dokonał <b>'.$grade->graderfirstname.' '.$grade->graderlastname.'</b> (imię i nazwisko oceniającego). Uczestnik szkolenia uzyskał następujące wyniki:</p>';

	//Core competencies table
	$html .= '<table border="1" cellpadding="5">';
	$html .= '<tr><th style="width: 71.9%; background-color: #86baf2; text-align: center; font-weight: bold;">Kompetencja kluczowa</th><th style="width: 28.1%; background-color: #86baf2; text-align: center; font-weight: bold;">Wartość wskaźnika</th></tr>';
	
	$corecompetencies = $DB->get_records_sql("SELECT grc.description, grl.definition, stu.id AS userid
                                FROM {course} AS crs
                                JOIN {course_modules} AS cm ON crs.id = cm.course
                                JOIN {assign} AS asg ON asg.id = cm.instance
                                JOIN {context} AS c ON cm.id = c.instanceid
                                JOIN {grading_areas} AS ga ON c.id=ga.contextid
                                JOIN {grading_definitions} AS gd ON ga.id = gd.areaid
                                JOIN {gradingform_rubric_criteria} AS grc ON grc.definitionid = gd.id
                                JOIN {gradingform_rubric_levels} AS grl ON grl.criterionid = grc.id
                                JOIN {grading_instances} AS gin ON gin.definitionid = gd.id
                                JOIN {assign_grades} AS ag ON ag.id = gin.itemid
                                JOIN {user} AS stu ON stu.id = ag.userid
                                JOIN {user} AS rubm ON rubm.id = gin.raterid
                                JOIN {gradingform_rubric_fillings} AS grf ON grf.instanceid = gin.id AND grf.criterionid = grc.id AND grf.levelid = grl.id
                                WHERE cm.id = ? AND gin.status = 1 AND userid = ?
                                ORDER BY grc.sortorder ASC, grc.description ASC", array($cm->id, $grade->userid));
	
	foreach ($corecompetencies as $corecompetency) {
		if ($corecompetency->userid == $grade->userid)	$html .= '<tr><td  style="background-color: #f2f2f2; text-align: center;">'.$corecompetency->description.'</td><td style="text-align: center;">'.$corecompetency->definition.'</td></tr>';
	}
	
	$html .= '</table>';
	
	if ($first->categoryid==SUMMATIVE_ASSESSMENT_CATEGORY) {
		$html .= '<br><br><table border="1" cellpadding="5">';
		$html .= '<tr><td style="width: 50%; background-color: #86baf2; text-align: center; font-weight: bold;">Wynik oceny:</td><td style="width: 50%; text-align: center;">ZALICZONY / NIEZALICZONY*</td></tr>';
		$html .= '</table>';
	}
	
	$html .= '<h3>Komentarz instruktora:</h3>';
	$html .= '<table border="1" cellpadding="5"><tr><td><p style="text-align: justified;">'.$grade->feedback.'</p></td></tr></table>';
	
	if ($first->categoryid==SUMMATIVE_ASSESSMENT_CATEGORY) {
		$html .= '<br><br>* niepotrzebne skreślić';
	}
	
	$doc->writeHTML($html, true, false, false, false, '');
}

$downloadfilename = clean_filename($assessment_type."_Report-".$course->shortname."-".$cm->name."-".date("Y-m-d", TIME_CREATED).".pdf");
$doc->Output($downloadfilename,'D');
exit;
?>