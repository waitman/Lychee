<?php

###
# @name			Upload Module
# @copyright	2015 by Tobias Reich
# modified by Waitman Gobble <ns@waitman.net> 12/28/15
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

class Import extends Module {

	private $settings	= null;

	public function __construct($settings) {

		# Init vars
		$this->settings	= $settings;

		return true;

	}

	private function photo($path, $albumID = 0, $description = '', $tags = '') {

		# Check dependencies
		self::dependencies(isset($this->settings, $path));

		# No need to validate photo type and extension in this function.
		# $photo->add will take care of it.

		$info	= getimagesize($path);
		$size	= filesize($path);
		$photo	= new Photo($this->settings, null);

		$nameFile					= array(array());
		$nameFile[0]['name']		= $path;
		$nameFile[0]['type']		= $info['mime'];
		$nameFile[0]['tmp_name']	= $path;
		$nameFile[0]['error']		= 0;
		$nameFile[0]['size']		= $size;
		$nameFile[0]['error']		= UPLOAD_ERR_OK;

		if (!$photo->add($nameFile, $albumID, $description, $tags, true)) return false;
		return true;

	}

	public function url($urls, $albumID = 0) {

		# Check dependencies
		self::dependencies(isset($urls));

		$error = false;

		# Parse URLs
		$urls = str_replace(' ', '%20', $urls);
		$urls = explode(',', $urls);

		foreach ($urls as &$url) {

			# Validate photo type and extension even when $this->photo (=> $photo->add) will do the same.
			# This prevents us from downloading invalid photos.

			# Verify extension
			$extension = getExtension($url);
			if (!in_array(strtolower($extension), Photo::$validExtensions, true)) {
				$error = true;
				continue;
			}

			# Verify image
			$type = @exif_imagetype($url);
			if (!in_array($type, Photo::$validTypes, true)) {
				$error = true;
				continue;
			}

			$pathinfo	= pathinfo($url);
			$filename	= $pathinfo['filename'] . '.' . $pathinfo['extension'];
			$tmp_name	= LYCHEE_DATA . $filename;

			if (@copy($url, $tmp_name)===false) {
				$error = true;
				continue;
			}

			# Import photo
			if (!$this->photo($tmp_name, $albumID)) {
				$error = true;
				continue;
			}

		}

		if ($error===false) return true;
		return false;

	}

	public function server($path, $albumID = 0) {

		# Check dependencies
		self::dependencies(isset($this->settings));

		# Parse path
		if (!isset($path))				$path = LYCHEE_UPLOADS_IMPORT;
		if (substr($path, -1)==='/')	$path = substr($path, 0, -1);

		if (is_dir($path)===false) {
			return 'Error: Given path is not a directory!';
		}

		# Skip folders of Lychee
		if ($path===LYCHEE_UPLOADS_BIG||($path . '/')===LYCHEE_UPLOADS_BIG||
			$path===LYCHEE_UPLOADS_MEDIUM||($path . '/')===LYCHEE_UPLOADS_MEDIUM||
			$path===LYCHEE_UPLOADS_THUMB||($path . '/')===LYCHEE_UPLOADS_THUMB) {
				return 'Error: Given path is a reserved path of Lychee!';
		}

		$error				= false;
		$contains['photos']	= false;
		$contains['albums']	= false;


		# Get all files
		$files = glob($path . '/*');

		foreach ($files as $file) {

			# It is possible to move a file because of directory permissions but
			# the file may still be unreadable by the user
			if (!is_readable($file)) {
				$error = true;
				continue;
			}

			if (@exif_imagetype($file)!==false) {

				# Photo

				$contains['photos'] = true;

				if (!$this->photo($file, $albumID)) {
					$error = true;
					continue;
				}

			} else if (is_dir($file)) {

				# Folder

				$album				= new Album($this->settings, null);
				$newAlbumID			= $album->add('[Import] ' . basename($file));
				$contains['albums']	= true;

				if ($newAlbumID===false) {
					$error = true;
					continue;
				}

				$import = $this->server($file . '/', $newAlbumID);

				if ($import!==true&&$import!=='Notice: Import only contains albums!') {
					$error = true;
					continue;
				}

			}

		}

		# The following returns will be caught in the front-end
		if ($contains['photos']===false&&$contains['albums']===false)	return 'Warning: Folder empty or no readable files to process!';
		if ($contains['photos']===false&&$contains['albums']===true)	return 'Notice: Import only contained albums!';

		if ($error===true) return false;
		return true;

	}

}
