<?php
/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2012 by Ken Tanzer and Downtown Emergency
Service Center (DESC).

All rights reserved.

For more information about AGENCY, see http://agency-software.org/
For more information about DESC, see http://www.desc.org/.

AGENCY is free software: you can redistribute it and/or modify
it under the terms of version 3 of the GNU General Public License
as published by the Free Software Foundation.

AGENCY is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with AGENCY.  If not, see <http://www.gnu.org/licenses/>.

For additional information, see the README.copyright file that
should be included in this distribution.

</LICENSE>
*/

$quiet = true;
include 'includes.php';
$action = $_REQUEST['action'];

if ($report_id = orr($_REQUEST['report_code'],$_REQUEST['id'],$_REQUEST['report_id'])) {
	if (!$report=get_report_from_db($report_id)) // fixme: just get_report()?
	{
		 $out .= alert_mark("Couldn't get report $report_id.");
		 $report_id=NULL;
	} else {
		$report['variables']=report_parse_var_text($report['variables']);
		$action = orr($action,'options');
		$edit_this_report = link_report($report_id,'Edit this Report',array(),'edit');
		$view_this_report = link_report($report_id,'View this Report',array(),'view');
	}
}

$navigation = array();
$main_reports_url = hlink(AG_REPORTS_URL,'List Reports');

switch ($action) {
 case 'post_sql': //this is for generating an openoffice document from posted sql (via list)
	$dummy=agency_query("SET DATESTYLE TO SQL");
	report_generate_from_posted($out);
	break;

 case 'generate':
	$var_save=$report['variables'];
//	$report['variables']=report_parse_var_text($report['variables']);
	 $title  = 'Report - ' . $report['report_title'];
	 if (!report_valid_request($report,$mesg)) {
		 $out .= div($mesg,'','class="error"');
		 $report['variables']=$var_save;
	 } else {
			$nav_link=report_user_options_form($report).toggle_label('Change options for this report');
			 $navigation[]=div($nav_link,'','class="hiddenDetail fancyLink"');
			 $navigation[]=$view_this_report;
			 $navigation[]=$edit_this_report;
			 // valid request, generate report
			$dummy=agency_query('SET DATESTYLE TO SQL');
			 
			 track_report_usage($report);
		
			if (!($out=report_generate($report,$mesg))) {
			  $out .= div($mesg,'','class="error"');
			}
			 break;
	 } 

 case 'options' : // set user options for report
	$title = 'Select Options ('. $report['report_title']. ')';
	// navigate back to list of reports
	$navigation[]=$main_reports_url;
	$navigation[]=$view_this_report;
	$navigation[]=$edit_this_report;
	// check permissions
	$perm_type = $report['permission_type_codes'];
	if (!be_null($perm_type) && (!has_perm($perm_type))) {
		 $out .= alert_mark('You do not have the proper permissions ('.implode(', ',$perm_type).') to run this report');;
		 break;
	 }
	 $out .= report_user_options_form($report);
	 break;

 default: // report list
	$title = 'AGENCY Report Page';
	$perm= $tot_recs = NULL;
	 $out .= call_engine(array('object'=>'report','action'=>'list','format'=>''),'',true,true,$perm,$tot_recs);
}

$out = html_heading_1($title) . $out ;
$commands = array(bottomcell(implode(oline(),$navigation),' class="pick"'));
agency_top_header($commands);
out($out);
page_close();
?>
