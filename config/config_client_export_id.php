<?php

/*
<LICENSE>

This file is part of AGENCY.

AGENCY is Copyright (c) 2003-2012 by Ken Tanzer and Downtown Emergency
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
along with AGENCY.  If not, see <http://www.gnu.org/licenses/>.

For additional information, see the README.copyright file that
should be included in this distribution.

</LICENSE>
*/


$engine['client_export_id'] = 
    array(
	    // 'singular' => 'Client SHA ID Record',
	    'perm' => 'rent',
	    'list_fields' => array('export_organization_code', 'export_id'),
	    'fields'=>
	    array('export_organization_code' => 
		    array('valid'=>
			    array(
				    '!($x == "SAFE_HARB" and $action=="add") '=>'Cannot add new Safe Harbors record',
				    '!($x == "PAYROLL")' => 'Clients do not have Payroll identifiers')
			    )
		    )
	    
	    /*	  'fields' => array(
	     'export_organization_code' => array('default' => 'SHA',
	     'display' => 'display')
	     ) */
	    );

?>