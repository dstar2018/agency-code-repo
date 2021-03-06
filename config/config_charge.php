<?php

/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2017 by Ken Tanzer and Downtown Emergency
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

//the logic involved (short term) makes it easier to use existing add infrastructure

$engine['charge'] = array(
	'allow_edit'=>false,
	'allow_delete'=>false,
	'perm'=>'rent',
	'subtitle_eval_code'=>array( 'balance_by_project($id)'),
	'label_format_list'=>'smaller($x)',
	'list_fields'=>array(
		'effective_date',
		'housing_project_code',
		'charge_type_code',
		'housing_unit_code',
		'amount',
		'comment'),
	'list_order'=>array('is_void'=>false,'effective_date'=>true),
	'fields'=>array(
		'client_id'=>array('row_before'=>'bigger(bold("Basic Information"))'),
		'housing_project_code'=>array( 'default'=>'EVAL: last_residence_own($rec_init["client_id"])'),
		'charge_type_code'=>array('show_lookup_code'=>'CODE'),
		'housing_unit_code'=>array(
		'label'=>'Unit #',
		'default'=>'EVAL: client_housing_unit($rec["client_id"])'),
		'amount'=>array(
			'data_type'=>'currency',
			'value_format_list'=> 'sql_true($rec["is_void"]) ? strike(currency_of($x)) : $x . smaller(" (".link_engine(array("object"=>"charge","id"=>$rec["charge_id"],"action"=>"void")).")",2) ',
			'total_value_list'=>'sql_true($rec["is_void"]) ? 0 : $x'),
		'comment'=>array(
			'is_html'=>true,
			'value_list'=> 'sql_true($rec["is_void"]) ? strike($x) : $x',
            'value_format_list' => 'smaller(sql_true($rec["is_void"]) ? httpimage($GLOBALS["AG_IMAGES"]["RECORD_VOIDED"],30,30,0) .$x : $x)'),
			'effective_date'=>array('default'=>'NOW'),
			'subsidy_type_code'=>array('comment'=>'Leave blank, except for subsidy charges',
			'row_before'=>'bigger(bold("Subsidy Information"))'),
			'period_start'=>array('row_before'=>'bigger(bold("For Rent & Subsidy only:"))')
		)
);
?>
