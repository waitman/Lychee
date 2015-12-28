<?php

###
# @name			Settings Module
# @copyright	2015 by Tobias Reich
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

class Settings extends Module {


	public function __construct() {

		# Init vars
		return true;

	}

	public function get() {

		# Execute query
		$sql = "SELECT * FROM settings";
		$res = pg_query($db,$sql);
		while ($row = pg_fetch_array($res))
		{
			$return[$row['key']] = $row['value'];
		}
		pg_free_result($res);
		return $return;

	}

	public function setLogin($oldPassword = '', $username, $password) {

		return false;

	}

	private function setUsername($username) {

		return false;

	}

	private function setPassword($password) {
		
		return false;

	}

	public function setDropboxKey($key) {
		return false;
	}

	public function setSortingPhotos($type, $order) {

		# Check dependencies
		self::dependencies(isset($type, $order));

		$sorting = 'ORDER BY ';

		# Set row
		switch ($type) {

			case 'id':			$sorting .= 'id';
								break;

			case 'title':		$sorting .= 'title';
								break;

			case 'description':	$sorting .= 'description';
								break;

			case 'public':		$sorting .= 'public';
								break;

			case 'type':		$sorting .= 'type';
								break;

			case 'star':		$sorting .= 'star';
								break;

			case 'takestamp':	$sorting .= 'takestamp';
								break;

			default:			exit('Error: Unknown type for sorting!');

		}

		$sorting .= ' ';

		# Set order
		switch ($order) {

			case 'ASC':		$sorting .= 'ASC';
							break;

			case 'DESC':	$sorting .= 'DESC';
							break;

			default:		exit('Error: Unknown order for sorting!');

		}

		# Execute query
		# Do not prepare $sorting because it is a true statement
		# Preparing (escaping) the sorting would destroy it
		# $sorting is save and can't contain user-input
		$sql = "UPDATE settings SET \"value\"=".pg_escape_literal($sorting)." WHERE \"key\"='sortingPhotos'";
		pg_query($db,$sql);
		return true;

	}

	public function setSortingAlbums($type, $order) {

		# Check dependencies
		self::dependencies(isset($type, $order));

		$sorting = 'ORDER BY ';

		# Set row
		switch ($type) {

			case 'id':			$sorting .= 'id';
								break;

			case 'title':		$sorting .= 'title';
								break;

			case 'description':	$sorting .= 'description';
								break;

			case 'public':		$sorting .= 'public';
								break;

			default:			exit('Error: Unknown type for sorting!');

		}

		$sorting .= ' ';

		# Set order
		switch ($order) {

			case 'ASC':		$sorting .= 'ASC';
							break;

			case 'DESC':	$sorting .= 'DESC';
							break;

			default:		exit('Error: Unknown order for sorting!');

		}

		# Execute query
		# Do not prepare $sorting because it is a true statement
		# Preparing (escaping) the sorting would destroy it
		# $sorting is save and can't contain user-input

		$sql = "UPDATE settings SET \"value\"=".pg_escape_literal($sorting)." WHERE \"key\"='sortingAlbums'";
		pg_query($db,$sql);
		
		return true;

	}

}
