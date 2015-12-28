<?php

###
# @name			Session Module
# @copyright	2015 by Tobias Reich
# modified by Waitman Gobble <ns@waitman.net>
# replaced crypt with password_verify
# added postgresql support
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

class Session extends Module {

	private $settings = null;

	public function __construct($settings) {

		# Init vars
		$this->settings	= $settings;

		return true;

	}

	public function init($public, $version) {

		# Check dependencies
		self::dependencies(isset($this->settings, $public, $version));

		# Return settings
		$return['config'] = $this->settings;

		# Remove username and password from response
		unset($return['config']['username']);
		unset($return['config']['password']);

		# Remove identifier from response
		unset($return['config']['identifier']);

		# Path to Lychee for the server-import dialog
		$return['config']['location'] = LYCHEE;

		# Check if login credentials exist and login if they don't
		if ($this->noLogin()===true) {
			$public = false;
			$return['config']['login'] = false;
		} else {
			$return['config']['login'] = true;
		}

		if ($public===false) {

			# Logged in
			$return['status'] = LYCHEE_STATUS_LOGGEDIN;

		} else {

			# Logged out
			$return['status'] = LYCHEE_STATUS_LOGGEDOUT;

			# Unset unused vars
			unset($return['config']['thumbQuality']);
			unset($return['config']['sortingAlbums']);
			unset($return['config']['sortingPhotos']);
			unset($return['config']['dropboxKey']);
			unset($return['config']['login']);
			unset($return['config']['location']);
			unset($return['config']['imagick']);
			unset($return['config']['medium']);

		}

		return $return;

	}

	public function login($username, $password) {

		# Check dependencies
		self::dependencies(isset($this->settings, $username, $password));

		# Check login with crypted hash
		if ($this->settings['username']===$username&&
			password_verify($password,$this->settings['password'])) {
				$_SESSION['login']		= true;
				$_SESSION['identifier']	= $this->settings['identifier'];
				return true;
		}

		# No login
		if ($this->noLogin()===true) return true;

		return false;

	}

	private function noLogin() {

		# Check dependencies
		self::dependencies(isset($this->settings));

		# Check if login credentials exist and login if they don't
		if ($this->settings['username']===''&&
			$this->settings['password']==='') {
				$_SESSION['login']		= true;
				$_SESSION['identifier']	= $this->settings['identifier'];
				return true;
		}

		return false;

	}

	public function logout() {

		$_SESSION['login']		= null;
		$_SESSION['identifier']	= null;

		session_destroy();

		return true;

	}

}
