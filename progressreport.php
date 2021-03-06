<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
/*// (at your option) any later version.
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
 
define('PROGRESS_EXAM_CATEGORY', 27);
 
define('MARGIN_LEFT', 24);
define('MARGIN_RIGHT', 15);
define('MARGIN_TOP', 35);
define('MARGIN_BOTTOM', 10);
define('FONT_FAMILY', 'arial');
define('FONT_SIZE', 12);

require('../../config.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->dirroot.'/grade/export/lib.php');

$courseid = required_param('id', PARAM_INT); // Course ID

$PAGE->set_url('/report/examreports/index.php', array('id'=>$courseid));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Progress Report');
$PAGE->set_heading(get_string('pluginname', 'report_examreports'));

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/grade:export', $context);

// Cell     ($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=0, $link='', $stretch=0, $ignore_min_height=false, $calign='T', $valign='M')
// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)
// Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)

class pr_pdf extends pdf {

    //Page header
    public function Header() {
		// Date
		$this->SetFont(FONT_FAMILY, 'B', 12);
        $this->Cell(0, 40, 'PROTOKOŁY EGZAMINÓW ETAPOWYCH');
		
        // Logo
        $image_file = 'logo.png';
        $this->Image($image_file, 140, 10, 0, 15, 'PNG');
    }
	
	public function Footer() {
		// Position at 1.5 cm from bottom
		$this->SetY(-15);
		$this->SetFont(FONT_FAMILY, 'B', 10);
		$this->Cell(0,10, 'Druk: 1/PR/TF',0,0,'R');
	}
}

class category_select_form extends moodleform {
    //Add elements to form
	
    public function definition() {
		global $DB;
		global $CFG;
		global $course;
		
        $mform = $this->_form; // Don't forget the underscore! 
		
		// Create form inputs
		foreach ($this->_customdata['categories'] as $category) $mform->addElement('radio', 'categoryid', '', $category->name, $category->categoryid);
		
		$first = reset($this->_customdata['categories']);
		$mform->setDefault('categoryid', $first->categoryid);
		
		$this->add_action_buttons(true, 'Generate Report');
    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

function generate_report($categoryid) {
	global $DB;
	global $course;
	
	$categoryname = $DB->get_field_sql("SELECT fullname FROM {grade_categories} WHERE id = ?", array($categoryid));
	
	$doc = new pr_pdf;
	$doc->setPrintHeader(true);
	$doc->setPrintFooter(true);
	$doc->SetMargins(MARGIN_LEFT, MARGIN_TOP, MARGIN_RIGHT);
	$doc->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
	$doc->SetFont(FONT_FAMILY, '', FONT_SIZE);
	
	$doc->AddPage();
	$html = '<p style="text-align: justified;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Szkolenie: <b>'.$course->fullname.'</b> (rodzaj szkolenia) nr <b>'.$course->shortname.'</b> (numer szkolenia), przedmiot: <b>'.$categoryname.'</b> (nazwa przedmiotu), stan na dzień <b>'.date("d.m.Y", TIME_CREATED).'</b> (data).<br></p>';
	$doc->writeHTML($html, true, false, false, false, '');

	$gui = new graded_users_iterator($course);;
	$gui->require_active_enrolment(true);
	$gui->init();

	while ($userdata = $gui->next_user()) {
		$user = $userdata->user;
		
		$grades = $DB->get_records_sql("SELECT items.itemname AS examname, grades.finalgrade AS finalgrade, grades.rawgrademax AS rawgrademax, grades.rawgrademin AS rawgrademin, grades.userid AS userid, grades.timemodified AS date, categories.fullname AS categoryname
										FROM {course} AS course
										JOIN {grade_items} items ON course.id = items.courseid
										JOIN {grade_grades} grades ON items.id = grades.itemid
                                        JOIN {grade_categories} categories ON items.categoryid = categories.id
										WHERE items.itemmodule = 'quiz' AND course.id = ? AND items.categoryid = ? AND grades.userid = ?
										ORDER BY examname ASC", array($course->id, $categoryid, $user->id));
		
		$html = '<p style="text-align: justified;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uczestnik szkolenia <b>'.$user->firstname.' '.$user->lastname.'</b> (imię i nazwisko) uzyskał nastepujące wyniki egzaminów etapowych:</p>';
								
		$html .= '<table cellpadding="5" border="1">';
		$html .= '<tr><th style="width: 9.4%; text-align: center; font-weight: bold; background-color: #86baf2;">L. p.</th><th style="width: 50.2%; text-align: center; font-weight: bold; background-color: #86baf2;">Egzamin</th><th style="width: 15.3%; text-align: center; font-weight: bold; background-color: #86baf2;">Wynik</th><th style="width: 25.1%; text-align: center; font-weight: bold; background-color: #86baf2;">Data</th></tr>';

		//Table of grades
		$i=1;
		foreach ($grades as $grade) {
			if (!empty($grade->finalgrade)) $grade_display = number_format($grade->finalgrade/$grade->rawgrademax*100,2)."%";
			else $grade_display = "-";
			if (!empty($grade->date)) $date_display = date("d.m.Y", $grade->date);
			else $date_display = "-";
			$html .= '<tr><td style="background-color: #f2f2f2; text-align: center;">'.$i.'.</td><td style="text-align: center;">'.$grade->examname.'</td><td style="text-align: center;">'.$grade_display.'</td><td style="text-align: center;">'.$date_display.'</td></tr>';
			$i++;
		}

		$html .= '</table>';
			
		$doc->writeHTML($html, true, false, false, false, '');
	}

	$downloadfilename = clean_filename("ProgressReport_".$course->shortname."_".date("Y-m-d", TIME_CREATED).".pdf");
		
	$doc->Output($downloadfilename,'D');
	exit;
}

$data = array();
$data['categories'] = $DB->get_records_sql("SELECT categories.id AS categoryid, categories.fullname AS name
									FROM {grade_categories} AS categories
									WHERE categories.courseid = ? AND categories.parent = ?
									ORDER BY name ASC", array($course->id, PROGRESS_EXAM_CATEGORY));

$category_select = new category_select_form("?id=".$courseid, $data);

//Form processing and displaying is done here
if ($category_select->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
} else if ($data = $category_select->get_data()) {
	generate_report($data->categoryid);
  
} else {
	echo $OUTPUT->header();
	$category_select->display();
	echo $OUTPUT->footer();
}