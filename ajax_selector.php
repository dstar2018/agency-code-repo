<?php

/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2009 by Ken Tanzer and Downtown Emergency
Service Center (DESC).

All rights reserved.

For more information about AGENCY, see http://agency.sourceforge.net/
For more information about DESC, see http://www.desc.org/.

AGENCY is free software: you can redistribute it and/or modify
it under the terms of version 3 of the GNU General Public License
as published by the Free Software Foundation.

AGENCY is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with CHASERS.  If not, see <http://www.gnu.org/licenses/>.

For additional information, see the README.copyright file that
should be included in this distribution.

</LICENSE>
*/

$quiet='Y';
include 'includes.php';
$qs=$_REQUEST['QuickSearch'];
$my=$_REQUEST['MyClients'];
$obj=orr($_REQUEST['QuickSearchObject'],AG_MAIN_OBJECT_DB);

if ($my && ($my == 'true')) {
	$filt['IN:' . $obj .'_id'] = staff_client_assignments_ids($UID);
} elseif ($qs) {
	$filt = object_qs_filter($qs,$obj);
} else {
	return false;
}
$control=array('action' => 'list', 'list' => array( 'filter' => $filt));
$def = get_def($obj);
$recs = list_generic($control,$def,'',$dummy);
out( $recs );
?>