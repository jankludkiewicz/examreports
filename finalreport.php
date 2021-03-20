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
 
define('FINAL_EXAM_CATEGORY', 28);
 
define('MARGIN_LEFT', 24);
define('MARGIN_RIGHT', 15);
define('MARGIN_TOP', 35);
define('MARGIN_BOTTOM', 10);
define('FONT_FAMILY', 'arial');
define('FONT_SIZE', 12);

require('../../config.php');
require_once($CFG->dirroot.'/lib/pdflib.php');
require_once($CFG->dirroot.'/grade/export/lib.php');

$courseid = required_param('id', PARAM_INT); // Course ID

$PAGE->set_url('/report/examreports/index.php', array('id'=>$courseid));

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/grade:export', $context);

// Cell     ($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=0, $link='', $stretch=0, $ignore_min_height=false, $calign='T', $valign='M')
// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0)
// Image($file, $x='', $y='', $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false)

$timecreated = time();

class pr_pdf extends pdf {

    //Page header
    public function Header() {
		// Date
		$this->SetFont(FONT_FAMILY, 'B', 16);
        $this->Cell(0, 40, 'PROTOKOŁY EGZAMINÓW KOŃCOWYCH');
		
        // Logo
        $image_file = 'positiverate.png';
        $this->Image($image_file, 140, 10, 0, 15, 'PNG');
    }
	
	public function Footer() {
		$this->SetFont(FONT_FAMILY, 'B', 10);
		$this->Cell(140,35, 'Druk: 2/PR/TF');
	}
}

$doc = new pr_pdf;
$doc->setPrintHeader(true);
$doc->setPrintFooter(true);
$doc->SetMargins(MARGIN_LEFT, MARGIN_TOP, MARGIN_RIGHT);
$doc->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$doc->SetFont(FONT_FAMILY, '', FONT_SIZE);

$gui = new graded_users_iterator($course);;
$gui->require_active_enrolment(true);
$gui->init();

while ($userdata = $gui->next_user()) {
	$user = $userdata->user;
	$doc->AddPage();
	$html = '<p style="text-align: justified;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Uczestnik szkolenia <b>'.$user->firstname.' '.$user->lastname.'</b> (imię i nazwisko) uzyskał nastepujące wyniki egzaminów końcowych podczas szkolenia <b>'.$course->fullname.'</b> nr <b>'.$course->shortname.'</b>. Stan na dzień <b>'.date("d.m.Y", TIME_CREATED).'.</b></p>';
			
	// Show grade table
	$grades = $DB->get_records_sql("SELECT items.itemname AS examname, grades.finalgrade AS finalgrade, grades.rawgrademax AS rawgrademax, grades.rawgrademin AS rawgrademin, grades.userid AS userid, grades.timemodified AS date
									FROM {course} AS course
									JOIN {grade_items} items ON course.id = items.courseid
									JOIN {grade_grades} grades ON items.id = grades.itemid
									WHERE items.itemmodule = 'quiz' && course.id = ? && items.categoryid = ? && grades.userid = ?
									ORDER BY examname ASC", array($courseid, FINAL_EXAM_CATEGORY, $user->id));
									
								
	$html .= '<table cellpadding="5" border="1">';
	$html .= '<tr><th style="width: 10%; text-align: center; font-weight: bold; background-color: #86baf2;">Nr</th><th style="width: 55%; text-align: center; font-weight: bold; background-color: #86baf2;">Egzamin</th><th style="width: 15%; text-align: center; font-weight: bold; background-color: #86baf2;">Wynik</th><th style="width: 20%; text-align: center; font-weight: bold; background-color: #86baf2;">Data</th></tr>';

	//Table of grades
	$i=0;
	foreach ($grades as $grade) {
		if (!empty($grade->finalgrade)) $grade_display = number_format($grade->finalgrade/$grade->rawgrademax*100,2)."%";
		else $grade_display = "-";
		if (!empty($grade->date)) $date_display = date("d.m.Y", $grade->date);
		else $date_display = "-";
		$html .= '<tr><td style="background-color: #f2f2f2; text-align: center;">'.($i+1).'.</td><td style="text-align: center;">'.$grade->examname.'</td><td style="text-align: center;">'.$grade_display.'</td><td style="text-align: center;">'.$date_display.'</td></tr>';
		$i++;
	}

	$html .= '</table>';
			
	$doc->writeHTML($html, true, false, false, false, '');
}

$downloadfilename = clean_filename("FinalReport_".$course->shortname."_".date("Y-m-d", TIME_CREATED).".pdf");
		
$doc->Output($downloadfilename,'D');
exit;