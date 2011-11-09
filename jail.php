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

function jail_status_f($id)
{
	$def = get_def('jail');
	$res = get_generic(client_filter($id),'jail_date DESC','1',$def);
	if (count($res) < 1) {
		return false;
	}
	$rec = array_shift($res);
	if (be_null($rec['jail_date_end'])) {
		$days = $rec['days_in_jail'] . ($rec['days_in_jail'] > 1 ? ' days' : ' day');
		$text = 'Incarcerated since '.dateof($rec['jail_date']).' ('.$days.')';
	} elseif (days_interval($rec['jail_date_end'],'now',true) < 31 ) { //show up to 30 days after release
		$text = 'Released from jail on '.dateof($rec['jail_date_end']);
	} else {
		return false;
	}
	return oline(link_engine(array('object'=>'jail','id'=>$rec['jail_id']),
					 red($text)));

}

?>
