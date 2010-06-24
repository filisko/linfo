<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/


defined('IN_INFO') or exit;


// Exception for above
class GetInfoException extends Exception{}

/*
 * Try determining OS
 * So far Linux is the only one supported
 */
function determineOS($os = null) {

	// List of known/supported Os's
	$known = array('linux', 'freebsd', 'darwin', 'windows');

	// Maybe we hardcoded OS type in
	if ($os != null && in_array(strtolower($os), $known)) {
		return $os;
	}

	// Or not:

	// Get uname
	$uname = strtolower(trim(@`/bin/uname`));

	// Do we have it?
	if (in_array($uname, $known)) {
		return $uname;
	}

	// Otherwise no. Winfux support coming later'ish
	else {
		return false;
	}

}

/*
 * Start up class based on result of above
 */
function parseSystem($type, $settings) {
	$type = ucfirst($type) . 'Info';
	if (!class_exists($type))
		exit('Info class for this does not exist');

	try {
		$info =  new $type($settings);
	}
	catch (GetInfoException $e) {
		exit($e->getMessage());
	}

	return $info;
}
