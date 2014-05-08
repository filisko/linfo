<?php

/**
 * This file is part of Linfo (c) 2011 Joseph Gillotti.
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
*/

/**
 * Keep out hackers...
 */
defined('IN_LINFO') or exit;

/**
 * Output in ncurses format for client side CLI functionality
 * @author Joseph Gillotti
 */
class LinfoNcurses {

	private 

    $linfo,

		// Store our windows here
		$_windows = array(),
		$_max_dims = array(),
		
		// ncurses loaded?
		$loaded = true;

	public function __construct(Linfo $linfo) {

    $this->linfo = $linfo;

		// We obviously need this
		if (!extension_loaded('ncurses')) {
			$this->loaded = false;
			throw new LinfoFatalException('ncurses extension not loaded');
		}

		// Start ncurses
		ncurses_init();
		ncurses_timeout(0);
	}

	// Make sure ncurses_end() always gets called no matter what;
	// not doing so will leave the terminal messed up until the user 
	// runs 'reset'
	public function __destruct() {
		if ($this->loaded)
			ncurses_end();	
	}

	public function draw() {
		
		// Gain access to translations
    $lang = $this->linfo->getLang();
    $info = $this->linfo->getInfo();

		// Say we're called more than once. Kill previous remnants
		if (count($this->_windows) > 0)
			$this->_kill_windows();
		
		// Get dimensions and give lovely header text
		$fullscreen = ncurses_newwin(0, 0, 0, 0);
		ncurses_wrefresh($fullscreen);
		ncurses_getmaxyx ($fullscreen, $x, $y);
		ncurses_mvwaddstr($fullscreen, 0, 0, 'Generated by '.$this->linfo->getAppName().' ('.$this->linfo->getVersion().') on '.date('m/d/Y @ h:i:s A (T)'));
		ncurses_wrefresh($fullscreen);
		$this->_max_dims = array($x, $y);

		// Some important windows
		$core_wins = array(
			array(
				'name' => $lang['core'],
				'content' => array(
					array($lang['os'], $info['OS']),
					array_key_exists('Distro', $info) ? array($lang['distro'], $info['Distro']['name'] . ($info['Distro']['version'] ? ' '.$info['Distro']['version'] : '') ) : false,
					array($lang['kernel'], $info['Kernel']),
					array_key_exists('Model', $info) && !empty($info['Model']) ? array($lang['model'], $info['Model']) : false,
					array($lang['uptime'], str_ireplace(array(' ', 'days', 'minutes', 'hours', 'seconds'), array('', 'd', 'm', 'h', 's'), reset(explode(';', $info['UpTime'])))),
					array($lang['hostname'], $info['HostName']),

					array_key_exists('CPUArchitecture', $info) ? array($lang['cpu_arch'], $info['CPUArchitecture']) : false,

					array($lang['load'], implode(' ', (array) $info['Load']))
				)
			),
			array(
				'name' => $lang['memory'],
				'content' => array(
					array($lang['size'], LinfoCommon::byteConvert($info['RAM']['total'])),
					array($lang['used'], LinfoCommon::byteConvert($info['RAM']['total'] - $info['RAM']['free'])),
					array($lang['free'], LinfoCommon::byteConvert($info['RAM']['free'])),
				)
			)
		);

		// Show them
		$h = 1;
		foreach ($core_wins as $win) {
			list($width, $height) = $this->_window_with_lines($win['name'], $win['content'], $h, 0);
			$h += $height + 1;
		}

		// Makeshift event loop
		while (true) {

			// Die on input
			$getch = ncurses_getch();
			if ($getch > 0 && $getch == 113) {
				$this->__destruct();
				echo "\nEnding at your request.\n";
				exit(0);
			}

			// Stop temporariy
			ncurses_napms(1000);

			// Call ourselves
			$this->draw();
		}
	}

	// Create a window with various lines as content
	private function _window_with_lines($name, $lines, $x = 5, $y = 5, $set_width = false) {

		// Need an array of lines. 
		$lines = (array) $lines;

		// Ignore disabled liens
		$lines = array_filter($lines);

		// Do we not have a specific set width? Calculate the longest line
		if (!is_numeric($set_width)) {
			$longest_line = strlen($name) + 10;
			foreach ($lines as $line) {
				$length = strlen(implode('', $line));
				$longest_line = $length > $longest_line ? $length : $longest_line;
			}
			$width = $longest_line + 4;
		}

		// Otherwise we do have a set with
		else
			$width = $set_width;

		// Calculate window hight
		$height = 3 + count($lines);

		// Create window
		$win =  ncurses_newwin($height, $width, $x, $y);

		// This character will be the side borders
		$side = ord('|');

		// Do the borders of the window
		ncurses_wborder($win, $side, $side, ord('-'), ord('-'), ord('/'), ord('\\'), ord('\\'), ord('/'));

		// Add window title string
		ncurses_mvwaddstr($win, 1, 1, $this->_charpad($name, $width, 'c', '='));

		// Keep track of vertical position for each line
		$v = 1;

		// Go through and output each line, while incrementing line position counter
		foreach ($lines as $line) {
			ncurses_mvwaddstr($win, $v + 1, 1, $this->_charpad($line[0] . $this->_charpad($line[1], $width - strlen($line[0]), 'r', '.'), $width, 'n'));
			$v++;
		}

		// Show it
		ncurses_wrefresh($win);

		// Store it so we can kill it later
		$this->_windows[] = &$win;

		// Return window dimensions
		return array($width, $height);
	}

	// Kill all windows
	private function _kill_windows() {
		foreach ($this->_windows as $win) {
			is_resource($win) &&
				ncurses_delwin($win);
		}
	}

	// Because I got tired of sprintf
	private function _charpad($string, $length, $direction, $filler = ' ') {
		
		// Keep length of string handy here
		$strlen = strlen($string);

		// Difference between max length and string length
		$difference = $length - $strlen;

		// If the string length is bigger than the max, just return string truncated to the max length
		if ($difference < 0)
			return substr($string, 0, $length);

		// Deal with direction
		switch ($direction) {

			// Right aligned (padded to the left)
			case 'r':
				return str_repeat($filler, $difference - 2) . $string;
			break;

			// Left aligned (padded to the right)
			case 'l':
				return $string . str_repeat($filler, $difference - 2);
			break;

			// Centered (padded left and right)
			case 'c':
				$cdiff = floor($difference / 2) - ($difference % 2 == 0 ? 1 : 0);
				return str_repeat($filler, $cdiff - 1) . $string . str_repeat($filler, $cdiff);
			break;

			// Not padded; returned as is (provided not longer than max, as tested above)
			case 'n':
				return $string;
			break;

			// Uhh not sure?
			default:
				return '';
			break;
		}
	}
}
