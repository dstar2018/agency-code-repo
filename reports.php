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

//FIXME:
//require_once 'openoffice.php';

function get_report_from_db( $report_code )
{
	$r_def=get_def('report');
	$key=$r_def['id_field'];
	$rpt=get_generic(array($key=>$report_code),'','',$r_def);
	if (count($rpt) <> 1)
	{
		return false;
	}
	$rec=sql_to_php_generic(array_shift($rpt),$r_def);
	// get sql statements
	$s_def=get_def('report_block');
	$sql_recs=get_generic(array('report_code'=>$rec['report_code']),'','',$s_def);
	$sql=array();
	while ($s=array_shift($sql_recs)) { 
		$s=sql_to_php_generic($s,$s_def);
		/* Split multiple SQL statements into array */
		$s['report_block_sql'] = preg_split( '/\n\s?SQL\s?\n/im',$s['report_block_sql'] );
		$sql[]=$s; 
	}
	$rec['report_block']=$sql;
	if (!be_null($rec['output_template_codes'])) {
		foreach(explode("\n",$rec['output_template_codes']) as $line)
		{
			$out_a[] = explode('|',$line);
		}
		$rec['output_template_codes']=$out_a;
	}
	else {
		$rec['output_template_codes'] = array();
	}
	return $rec;
}

function report_parse_var_text( $text ) {
	$vartypes=array('PICK','DATE','TIME','TIMESTAMP','TEXT','TEXT_AREA','VALUE');
	$lines = preg_split('/\n/m',$text);
	while ($line = array_shift($lines)) {
		if (preg_match('/^\s$/',$line)) {
			continue; //skip blank lines
		}
		$var=array();
		$ex = explot($line);
		if (!in_array(strtoupper($ex[0]),$vartypes)) {	
			//fixme:  make me a pretty warning
			outline('Warning:  Unknown variable type ' .$ex[0]);
			continue;
		}
		$var['type']    = strtoupper($ex[0]);
        $var['name']    = $ex[1];
        $var['prompt']  = $ex[2];
        $var['default'] = $ex[3];
		if ($var['type']=='PICK') {
			while ($tmp_line = array_shift($lines))	{
				$tmp_line = explot($tmp_line);
				if (strtoupper($tmp_line[0])=='ENDPICK') {
					break;
				}
				if (strtoupper($tmp_line[0])=='SQL') {
					$tmp_sql = '';
					while ($tmp = array_shift($lines)) {
						$tmp_ex = explot($tmp);
						if (strtoupper($tmp_ex[0])=='ENDSQL') {
							// SQL query assembled and ready
							$tmp_query = agency_query($tmp_sql); 
							while ($t=sql_fetch_assoc($tmp_query)) {
								$var['options'][]='"'.$t['value']. '" "' .$t['label'].'"';
							}
							break;
						} else {
							$tmp_sql .= $tmp;
						}	
					}	
				} else {
					$var['options'][]=enquote2($tmp_line[0]) . ' ' . enquote2($tmp_line[1]);
				}
			}
		}
		$vars[]=$var;
	}
	return $vars;
}

function report_valid_request($report, &$mesg)
{
	/*
	 * validates a request for a report
     * currently, only checks for valid dates
	 */

	$valid = true;
	$report['variables']=orr($report['variables'],array());
	foreach ($report['variables'] as $var) {
		$type  = $var['type'];
		$name  = $var['name'];
		$prompt = $var['prompt'];
		$value = report_get_user_var($name,$report['report_code']);
		switch ($type) {
		case 'VALUE' :
			if (!is_numeric($value)) {
				$mesg .= oline($prompt.': '.$value.' is an invalid value');
				$valid = false;
			}
			break;
		case 'DATE' :
			if (!dateof($value,'SQL')) {
				$mesg .= oline($prompt.': '.$value.' is an invalid date');
				$valid = false;
			}
			break;
		case 'TIME' :
			if (!timeof($value,'SQL')) {
				$mesg .= oline($prompt.': '.$value.' is an invalid time');
				$valid = false;
			}
			break;
		case 'TIMESTAMP' :
			if (!datetimeof($value,'SQL')) {
				$mesg .= oline($prompt.': '.$value.' is an invalid timestamp');
				$valid = false;
			}
			break;
		default: //no error checking
		}
	}
	return $valid;
}

function report_get_user_var($name,$report_code)
{
	$varname = AG_REPORTS_VARIABLE_PREFIX.$name;
	if (!isset($_REQUEST[$varname]) and isset($_REQUEST[$varname.'_date_']) and isset($_REQUEST[$varname.'_time_'])) {
		// Reassemble timestamps
		$val=$_REQUEST[$varname.'_date_'].' ' . $_REQUEST[$varname.'_time_'];
	} else {
		$val=$_REQUEST[$varname];
	}	
	$_SESSION['report_options_'.$report_code.'_'.$varname] = $val;
	return $val;
}

function report_generate($report,&$msg)
{
	/*
	 * Generates and returns an engine list,
	 * or merges query results with an open office template and exits
	 *
	 */

	// variables, used repeatedly
	$rb='report_block'; // the element within $report
	$rbs=$rb.'_sql'; // the sql field within that array

	$pattern_replace_header = $pattern_replace = array();
	$report['variables']=orr($report['variables'],array());
	foreach ($report['variables'] as $var) {
		$type  = $var['type'];
		$name  = $var['name'];
		$value = report_get_user_var($name,$report['report_code']);

		switch ($type) {
		case 'DATE' :
			$value = dateof($value,'SQL');
			$value_header = dateof($value);
			break;
		case 'TIME' :
			$value = timeof($value,'SQL');
			$value_header = timeof($value);
			break;
		case 'TIMESTAMP' :
			$value = datetimeof($value,'SQL');
			$value_header = dateof($value) . ' ' . timeof($value);
			break;
		default:
			$value_header = $value;
		}
		$pattern_replace['$'.$name] = $value;
		$pattern_replace_header['$'.$name] = $value_header; //contains human-readable values for date
		$report['pattern_replace']=$pattern_replace;
		$report['pattern_replace_header']=$pattern_replace_header;
		//set labels
		if ($var['options']) { //a pick variable, determine which was picked
			foreach ($var['options'] as $opt) {
				$opte = explot($opt);
				if ($opte[0] === $value) {
					$label = $opte[1];
				}
			}
		} else {
			$label = $var['prompt'];
		}
		$pattern_replace['$'.$name.'_label'] = $label;
		$pattern_replace_header['$'.$name.'_label'] = $label;
	}
	
	foreach( report_system_variables() as $k=>$v) {
		$pattern_replace['$'.$k ]=$v;
		$pattern_replace_header['$'.$k ]=$v;
	}

	// sort longest to shortest keys so, for example, "date_end" is replaced before "date"
	uksort($pattern_replace,'strlen_cmp');
	$pattern = array_keys($pattern_replace);
	$replace = array_values($pattern_replace);
	
	// a separate, formatted, replace array for header and footer
	uksort($pattern_replace_header,'strlen_cmp');
	$pattern_h = array_keys($pattern_replace_header);
	$replace_h = array_values($pattern_replace_header);
	// Loop through report blocks
	foreach ($report[$rb] as $s => $sql) {
		// Strip out disabled blocks
		if (sql_false($sql['is_enabled'])) {
		    unset($report[$rb][$s]);
		    continue;
		}
		$sqls = $block_sql_footer = array();
		foreach ($sql[$rbs] as $sql2) {
			$sql3=str_replace($pattern,$replace,$sql2);
			$sqls[] = $sql3;
			$sq_footer[] = $block_sql_footer[] = html_list_item(webify_sql($sql3)); // all queries, for overall report footer
		}
		$report[$rb][$s][$rbs] = $sqls;
		$report[$rb][$s]['block_sql_footer']=$block_sql_footer;
		foreach( array('footer','header','title','comment') as $f) {
				$report[$rb][$s][$rb.'_'.$f]=str_replace($pattern_h,$replace_h,$sql[$rb.'_'.$f]);
		}
	}
	
	$report['report_title'] = str_replace($pattern_h,$replace_h,$report['report_title']);
	$report['report_header'] = str_replace($pattern_h,$replace_h,$report['report_header']);
	$report['report_footer'] = str_replace($pattern_h,$replace_,$report['report_footer']);
	$report['report_comment'] = str_replace($pattern_h,$replace_,$report['report_comment']);

	$template = orr($_REQUEST[AG_REPORTS_VARIABLE_PREFIX.'template'],'screen');
	// FIXME: drop this line
	$report['output_template_codes']=orr($report['output_template_codes'],array());
	
	 $export_items=report_export_items();
	 $report_items=$report['output_template_codes'];
	$valid_templates=array_merge($export_items,$report_items);
	$valid_templates=array_fetch_column($valid_templates,'0');
	if (!in_array($template,$valid_templates)) {
	    $msg .= oline("Invalid template $template.  Valid templates are " . implode('',$valid_templates));
	    return false;
	}

	// Merge to screen
	if (be_null($template) || $template==='screen') { 
		if (in_array('O_SCREEN',$report['suppress_output_codes'])) {
			$msg.=oline('Screen output not allowed for this report');
			return NULL;
		}
		$footer = $report['report_footer'] ? span(webify($report['report_footer']),'class="reportFooter"') : '';

		/* cfg and sql output */
		$sys_footer = div(span('Configuration for ' . $report['report_title'] . ' (' . $report['report_code'] .')')
			. dump_array($report),'',' class="hiddenDetail configFile"')
			. div(span('Generated SQL:') . implode('',$sq_footer),'','class="sqlCode hiddenDetail"');

		/* Output to screen via engine list */
		// FIXME: suppress_header_row,suppress_row_numbers
		$control = array('object' => 'generic_sql_query',
				     'action' => 'list',
				     'list'   => array('fields'=>array(),'filter'=>array(),'order'=>array(),'show_totals'=>true,
							     'max' => orr($report['rows_per_page'],'-1')),
				     'sql_security_override' => $report['override_sql_security'],
				     'export_header' => $report['report_header']); // ???

		$oo_templates = report_output_select($report);
		if (!be_null($oo_templates)) {
			$control['oo_templates'] = $oo_templates;
		}
		foreach ($report[$rb] as $sql) {
			$out=array();
			if ( !( ($sql['permission_type_codes']==array()) or has_perm($sql['permission_type_codes'])) ) {
				// FIXME:  perm failed.  Log or notice?
				$mesg .= oline('Insufficient permissions to run report_block');
				continue;
			}
			foreach( array('block_sql_footer',$rb.'_footer',$rb.'_header',$rb.'_title',$rb.'_comment',$rbs,$rb.'_id') as $f) {
				$out[$f]=$sql[$f];
			}
			switch ($sql['report_block_type_code']) {
			  case 'CHART' :
			     //FIXME: implement
			    break;
			  case 'TABLE' :
			  default:
			    foreach ($sql[$rbs] as $s ) {
			      $control['sql']= $s;
			      if (!($out['results'][] = call_engine($control,$control_array_variable='control',$NO_TITLE=true,$NO_MESSAGES=true,$TOTAL_RECORDS,$PERM))) {
				$out['results'][]=$report['message_if_error'];
			      } elseif ($TOTAL_RECORDS===0) {
				 $out['results'][]=$report['message_if_empty'];
			      }
			    }
			    break;
			}
			// Supress at end, so any queries are still run
//outline(dump_array($sql));
			if (!in_array('O_SCREEN',$sql['suppress_output_codes'])) {
				$outs[]=$out;
			}
		}
		// Assemble the report
		$out=array();
		$c='reportOutput';
		foreach ($outs as $o) {
			$css_class = ' ' . $report['css_class'];
			$css_id = $report['css_id'];
			$out[]= div(
					($o[$rb.'_title'] ? div($o[$rb.'_title'],'','class="' .$c.'Title"') : '')
					. ($o[$rb.'_header'] ? div($o[$rb.'_header'],'','class="' .$c.'Header"') : '')
					. div(implode(oline(),$o['results'])
					. ($o['report_block_id'] ? smaller(elink('report_block',$o['report_block_id'],'view database record for this section','class="fancyLink" target="_blank"').' (Report block ID# ' . $o['report_block_id'] . '), or just ') :'')
					. div(implode('',$o['block_sql_footer']),'','class="' .$c.'Code hiddenDetail sqlCode"') // not like the others
,'','class="' .$c.'Results"')

					. ($o[$rb.'_footer'] ? div($o[$rb.'_footer'],'','class="' .$c.'Footer"') : '')
					. ($o[$rb.'_comment'] ? div($o[$rb.'_comment'],'','class="' .$c.'Comment"') : ''),$css_id,'class="'.$c . $css_class.'"');
		}
		$css_class = $report['css_class'];
		$css_id = $report['css_id'];
		return div(html_heading_4(webify($report['report_header']),' class="reportHeader"')
			. implode('<HR class="reportOutputSeparator">',$out)
			. (!be_null($footer) ? html_heading_4($footer,' class="reportFooter"') : '')
			. html_heading_4($sys_footer,' class="reportSysFooter"'),$css_id,'class="report $css_class"');
	}

	return report_generate_export($report, $template,$msg);
}


function report_system_variables() {
// Fixme, I wanted this is agency_config.php, but the UID info not available before it is included.
	$sys_vars = array(
		'today'=>dateof('now'),
		'now'=>timeof('now'),
		'UID'=>$GLOBALS['UID'],
		'UID_NAME'=>staff_name($GLOBALS['UID']),
		'org'=>org_name('short'),
		'org_label'=>org_name());
	uksort($sys_vars, 'strlen_cmp');
	return $sys_vars;
}

function report_generate_export_template($report,$template,&$msg)
{
	if (!is_array($report['report_block'])) {
		outline("FIXME: non-array of report_block to report_Geneerate_export_template");
		// FIXME: delete
		page_close();
		exit;
	}
	// security
	if (!$report['override_sql_security'] && !is_safe_sql($report['report_block'],$errors,$non_select = true)) {
		$msg .= div($errors);
		return false;
	}
	// execute query
	$error = '';
	for ($x=0;$x<count($report['report_block']);$x++) {
	$cnt=0;
	foreach( $report['report_block'][$x]['report_block_sql'] as $s) {
		if (!($r=agency_query($s))) {
			$error .= oline("SQL error with $s.");
			$report['report_block'][$x]['values'][$cnt][][0]=orr($report['report_block']['message_if_error'],'This block generated an error');
			
		} elseif (in_array('O_TEMPLATE',$report['report_block'][$x]['suppress_output_codes'])) {
			$msg .= oline('(block output set to suppress)');
		} else {
			
			// fetch_all causing problems with NULL
			//$report['sql'][$x]['values'] = pg_fetch_all($r);

			if (sql_num_rows($r)===0) {
			  $report['report_block'][$x]['values'][$cnt][][0]=$report['report_block'][$x]['message_if_empty'];
			} else {
			  while ($tmp=sql_fetch_assoc($r)) {
			    $report['report_block'][$x]['values'][$cnt][]=$tmp;
			  }
			}				
		}
		$cnt++;
	}
	}
	if ($error) {
		$msg .= div($error,'',' class="error"');
		return false;
		// FIXME: message_if_error suggests continuing rather than returning...
	}
	if (!AG_OPEN_OFFICE_ENABLE_EXPORT) {
		$msg .= div(AG_OPEN_OFFICE_DISABLED_MESSAGE,'',' class="error"');
		return false;
	}

	set_time_limit(120); //fixme: can be set in report record
	if (preg_match(AG_REPORTS_REGEX_TEMPLATES,$template,$m)) {
		$template = $m[1];
		$group_by = $m[4];
	}
	require_once 'openoffice.php';
	$oo_file = template_merge($report,$template);

}

function report_generate_from_posted(&$mesg)
{
	$report = array();
	//FIXME:
	$report = get_report_from_db('AD-HOC_QUERY');
	$report['report_block'][0]['report_block_sql'][0] = dewebify($_REQUEST['sql1']);
	$report['report_header'] = dewebify($_REQUEST["report_header"]);

	//fixme: this still relies on sql being acquired from the browser. even though it is checked
	//       it is still a dangerous idea.
	//note: requested sql is tested in report_generate_openoffice() or report_generate_export()

	$template = $_REQUEST[AG_REPORTS_VARIABLE_PREFIX.'template'];
	switch ($template) {
		//fixme: this is currently in too many places. make common function
	case 'sql_data_csv':
	case 'sql_data_tab':
	case 'sql_dump_full':
	case 'sql_dump_copy':
	case 'sql_dump_inserts':
break;
//		return report_generate_export($report['sql'],$template); // if succesful, this will exit the script
	case 'spreadsheet' :
		$template = AG_OPEN_OFFICE_CALC_TEMPLATE;
		break;
	default:
	}
	return report_generate_export($report,$template,$mesg);
}

function report_user_options_form($report)
{
	$out = formto();
	$out .= $report['report_code'] ? hiddenvar('report_code',$report['report_code']) : '';
	$out .= hiddenvar('action','generate');
	foreach( orr($report['variables'],array()) as $p ) {
		$varname    = AG_REPORTS_VARIABLE_PREFIX . $p['name'];	
		$userprompt = $p['prompt'];	
		$comment    = $p['comment']; // fixme in parse_cfg_file

		// store report variables for session
		$default = $_SESSION['report_options_'.$report['report_code'].'_'.$varname] = 
			orr($_REQUEST[$varname],$_SESSION['report_options_'.$report['report_code'].'_'.$varname]);
		
		switch ($p['type']) {
		case 'PICK' :
			$label =($userprompt ? bigger(bold($userprompt)) : '' ).  ($comment ? " $comment" : '');
			$cell = selectto($varname);

			foreach( $p['options'] as $li) {
				$li = explot($li);
				// default is set a) if default is passed, and equals current option
				$defaulti = $default===$li[0] 
					// or, b) no default is passed, but default is configured to current option
					|| (!$default && $li[0]==$p['default']);
				$cell .= selectitem( $li[0],$li[1],$defaulti);
			}
			$opt .= row(cell($userprompt) . cell($cell.selectend()));
			break;
		case 'DATE' :
				 $opt .= row(cell($userprompt) . cell(formdate($varname,orr($default,$p['default'],dateof('now')))));
				 break;
		case 'TIME' :
				 $opt .= row(cell($userprompt) . cell(formtime($varname,orr($default,$p['default'],timeof('now')))));
				 break;
		case 'TIMESTAMP' :
				 $opt .= row(cell($userprompt) . cell(oline(formdate($varname.'_date_',orr(dateof($default),$p['default'],dateof('now'))))
												. formtime($varname.'_time_',orr(timeof($default),timeof($p['default']),timeof('now')))));
				 break;
		case 'VALUE' :
		case 'TEXT' :
			$opt .= row(cell($userprompt) . cell(formvartext($varname,orr($default,$p['default']))));
			break;
		case 'TEXT_AREA':
			$opt .= row(cell($userprompt) . cell(formtextarea($varname,orr($default,$p['default']))));
			break;
		default :
			$opt .= row(cell(alert_mark('Don\'t know how to handle a ' . $p['type']),' colspan="2"'));
		}
	}

	// output options
	$opt .= row(cell('Choose Output Format').cell(report_output_select($report,true)));
	//$opt .= row(cell(oline('',2).formcheck(AG_REPORTS_VARIABLE_PREFIX.'showcfg').smaller(' Show Config File on Results Page'),' colspan="2"'));
	//$opt .= row(cell(formcheck(AG_REPORTS_VARIABLE_PREFIX.'showsql').smaller(' Show SQL on Results Page'),' colspan="2"'));

	$out .= table($opt);
	$out .= button();
	$out .= formend();

	return $out;
}

function report_output_select($report,$form=false)
{
	// Return an array of output options
	$labels['list']='Templates';
	$labels['x_items']='Export Formats';
	if (AG_OPEN_OFFICE_ENABLE_EXPORT) {
		foreach( $report['output_template_codes'] as $o) {
			$label=orr($o[1],$o[0]);
			$list[] = array($o[0],$label);
		}
	}
	//get csv, tab, sql dump options, if permissions correct
	$x_items=report_export_items();
	if (sql_false($report['allow_output_screen'])) {
		unset($x_items['screen']);
	}
	if ( ($report['allow_output_spreadsheet'] == sql_false()) or (!AG_OPEN_OFFICE_ENABLE_EXPORT)) {
		unset($x_items['spreadsheet']);
	}

	$result = array_merge($list,$other,$x_items);
	if (!$form) {
		return $result;
	}
	$out= selectto(AG_REPORTS_VARIABLE_PREFIX . 'template');
	foreach(array('list','other','x_items') as $grp) {
		foreach($$grp as $r) {
			$g[]= selectitem($r[0],$r[1],$r[0]==report_get_user_var('template',$report['report_code']));
		}
		if (count($g)>0) {
			$out .=html_optgroup(implode($g),$labels[$grp]);
		}
		$g=NULL;
	}
	$out .= selectend();
	return $out;
}

function explot( $line )
{
	while ( preg_match('/^([^\s\"]+)\s?(.*)$/',$line,$matches) ||
		 preg_match('/^\s?\"(.*?)\"(.*)$/',$line,$matches)) {
			$split[]=$matches[1];
			$line=trim($matches[2]);
		} 
	return $split;
}

function report_generate_export( $report,$template,&$mesg) {

	/* template handling */
	switch ($template) {

	case 'sql_data_csv':
	case 'sql_data_tab':
	case 'sql_dump_full':
	case 'sql_dump_copy':
	case 'sql_dump_inserts':

		return report_generate_export_sql($report['report_block'][0]['report_block_sql'][0],$template,$mesg); // if succesful, this will exit the script

	case 'spreadsheet' :

		if (in_array('O_SPREAD',$report['suppress_output_codes'])) {
			$mesg .= 'Generic spreadsheet option not allowed for this report.';
			return false;
		}
		if (!AG_OPEN_OFFICE_ENABLE_EXPORT) {
			$mesg .= AG_OPEN_OFFICE_DISABLED_MESSAGE;
			return false;
		}
		$template = AG_OPEN_OFFICE_CALC_TEMPLATE;
		break;
	default:
	}
	return report_generate_export_template($report,$template, $mesg);
}

function report_generate_export_sql($sql,$format,&$mesg)
{
	/*
	 *    Expects format to be one of:
	 *
	 *	case 'sql_data_csv':
	 *	case 'sql_data_tab':
	 *	case 'sql_dump_full':
	 *	case 'sql_dump_copy':
	 *	case 'sql_dump_inserts':
	 */
	if (!is_safe_sql($sql,$errors,$non_select = true)) {
		$message .= $errors;
		return false;
	}

	if (has_perm('sql_dump')) {
		preg_match('/^sql_(dump|data)_([a-z]*)$/',$format,$m);
		//header("Content-Type: text; charset=ISO-8859-1");
		//header("Content-Type: application/octet-stream");
		if ($m[1]=='data') {
			switch ($m[2]) {
				case 'csv' :
					$delimiter=',';
					$quotes=true;
					$c_type='text/csv';
					break;
				case 'tab' :
					$delimiter="\t";
					$quotes=false;
					$c_type='text/tab-delimited';
					break;
				default :
					// unknown format;
					$c_type='text/plain';
					break;
			}
			header('Content-Type: ' . $c_type);
			//header('Accept-Ranges: bytes');
			//header('Content-Transfer-Encoding: binary');
			//header('Pragma: public');
			header('Content-Disposition: attachment; filename="agency_data.csv"');
			$out=sql_data_export($sql,$delimiter,'',$quotes);
			//$len=strlen($out);
			//header('Content-Length: ' . $len);
			//header('Content-Range: bytes 0-' . ($len-1) . '/' . $len);
			out($out);
		} elseif ($m[1]=='dump') {
			header('Content-Disposition: attachment; filename="agency_sql_dump.sql"');
			out(sql_commentify($GLOBALS['AG_TEXT']['CONFIDENTIAL_STATEMENT']));
			out(sql_dump($sql,strtoupper($m[2])));
		}
		page_close($silent=true);
		exit;
	}

	$mesg .= oline(alert_mark('You aren\'t allowed to perform an SQL Dump'),4);
	return false;
}

function report_export_items()
{
	$options=array();
	$options[] = array('screen' , 'Show on screen');
	$options[] = array('spreadsheet', 'Generate spreadsheet');

	if (has_perm('sql_dump')) {
	  $options[] = array('sql_dump_inserts' , 'SQL Dump (insert commmands)');
	  $options[] = array('sql_dump_full'    , 'SQL Dump (column insert commands)');
	  $options[] = array('sql_dump_copy'    , 'SQL Dump (copy commands)');
	  $options[] = array('sql_data_csv'     , 'CSV file');
	  $options[] = array('sql_data_tab'     , 'Tab-delimited file');
	}
	return $options;
}

function link_report($report_code,$label,$init=array(),$action='',$template=null)
{
	/*
	 * Generate link to a report, by default to options page
	 * Use action="generate" to directly run report
	 * $init optionally pre-fills variables
	 * Specify template, esp. for generate
	 */

	$redirect=in_array($action,array('view','edit','delete','clone'));

	if (!($rep=get_report_from_db($report_code))) {
		out(div("warning: link_report couldn't find report $report_code",'','class="warning"'));
		return false; 
	}
	$r_def=get_def('report');
	$key=$r_def['id_field'];
	$url = $GLOBALS['off']
			. ($redirect
				? 'display.php?control[object]=report&control[id]='.$report_code
				: AG_REPORTS_URL . '?' .$key.'='.$report_code);
	$url .= $action ? (($redirect ? '&control[action]=' : '&action=') . $action ): '';
	$url .= $template ?  '&'.AG_REPORTS_VARIABLE_PREFIX.'template='. $template : '';
	
	if (!be_null($init) && is_assoc_array($init)) {
		foreach ($init as $var => $val) {
			$url .= '&'.AG_REPORTS_VARIABLE_PREFIX.$var.'='.$val;
		}
	}
	$perm = $rep['permission_type_codes'];
	return hlink_if($url,$label,(be_null($perm) || ($perm==array()) || has_perm($perm)));
}

function track_report_usage($report)
{
	global $UID;
	$IP = $_SERVER['REMOTE_ADDR'];
	$output = $_REQUEST[AG_REPORTS_VARIABLE_PREFIX.'template'];
	$record = array('generated_by' => $UID,
			    'report_id' => $report['report_id'],
			    'report_code' => $report['report_code'],
			    'report_name' => $report['report_title'],
			    'output_format' => $output,
			    'generated_from' => $IP,
			    'added_by' => $GLOBALS["sys_user"],
			    'generated_at' => datetimeof("now","SQL"),
			    'changed_by' => $GLOBALS["sys_user"] );
	return agency_query(sql_insert('tbl_report_usage', $record));  
}

function list_report($control,$def,$control_array_variable='',&$REC_NUM)
{
		/* Custom formatting for report list */
		$order="COALESCE(report_category_code,'GENERAL'),report_title";
		$result = list_query($def,array(),$order,$control);
		if (($REC_NUM=sql_num_rows($result)) == 0 ) {
			$out = oline('No reports found');
		} else {
			for ($count=1;$count<=$REC_NUM;$count++) {
				$rep=sql_fetch_assoc($result);
				$sortkey = ucfirst(strtolower(orr($rep['report_category_code'],'General')));
				$comment=(($com=$rep['report_comment'])) ? span($com . toggle_label('comment...'),'class="hiddenDetail"') : '';
				$lists[$sortkey][]= html_list_item(link_report($rep['report_code'],$rep['report_title']) . ' ' . $comment);
			}
			$out .= oline();
			foreach($lists as $sec => $list) {
				$item = html_list(implode('',$list));
				if ($sec=='Hidden') {
					$hidden .= $item;
				} else {
					$out .= oline(bigger(bold($sec)),2) . $item;
				}
			}
		}
		$out .= $hidden ? oline() . span($hidden . toggle_label('Show hidden reports...'),'class="hiddenDetail"').oline() : '';
		$out .= oline() . smaller(italic(add_link('report','Add a new report')));
		return $out;
}

?>
