<?php

###
# @name			Album Module
# @copyright	2015 by Tobias Reich
# modified by Waitman Gobble <ns@waitman.net> 12/28/2015
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

class Album extends Module {

	private $settings	= null;
	private $albumIDs	= null;

	public function __construct($settings, $albumIDs) {

		# Init vars
		$this->settings	= $settings;
		$this->albumIDs	= $albumIDs;

		return true;

	}

	public function add($title = 'Untitled', $public = 0, $visible = 1) {

		# Parse
		if (strlen($title)>50) $title = substr($title, 0, 50);

		# Database
		$sysstamp	= time();
		$sql = "INSERT INTO albums (id,title,public,visible) VALUES (DEFAULT,".pg_escape_literal($title).",".pg_escape_literal($sysstamp).",".pg_escape_literal($public).",".pg_escape_literal($visible).") RETURNING id";
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		$id = $row['id'];
		pg_free_result($res);
		
		return $id;

	}

	public static function prepareData($data) {

		# This function requires the following album-attributes and turns them
		# into a front-end friendly format: id, title, public, sysstamp, password
		# Note that some attributes remain unchanged

		# Check dependencies
		self::dependencies(isset($data));

		# Init
		$album = null;

		# Set unchanged attributes
		$album['id']		= $data['id'];
		$album['title']		= $data['title'];
		$album['public']	= $data['public'];

		# Parse date
		$album['sysdate'] = date('F Y', $data['sysstamp']);

		# Parse password
		$album['password'] = ($data['password']=='' ? '0' : '1');

		# Set placeholder for thumbs
		$album['thumbs'] = array();

		return $album;

	}

	public function get() {

		# Check dependencies
		self::dependencies(isset($this->settings, $this->albumIDs));

		# Get album information
		switch ($this->albumIDs) {

			case 'f':	$return['public'] = '0';
						$sql = "SELECT id, title, tags, public, star, album, thumbUrl, takestamp, url FROM photos WHERE star = 1 " . $this->settings['sortingPhotos'];
						break;

			case 's':	$return['public'] = '0';
						$sql = "SELECT id, title, tags, public, star, album, thumbUrl, takestamp, url FROM photos WHERE public = 1 " . $this->settings['sortingPhotos'];
						break;

			case 'r':	$return['public'] = '0';
						$check = strtotime('-24 Hours');
						$sql = "SELECT id, title, tags, public, star, album, thumbUrl, takestamp, url FROM photos WHERE takestamp> " . $check ." ".$this->settings['sortingPhotos'];
						break;

			case '0':	$return['public'] = '0';
						$sql = "SELECT id, title, tags, public, star, album, thumbUrl, takestamp, url FROM photos WHERE album = 0 " .$this->settings['sortingPhotos'];
						break;

			default:	$sql = "SELECT * FROM albums id = ".intval($this->albumIDs);
						$res = pg_query($db,$sql);
						$row = pg_fetch_array($res);
						$return = $row;
						$return['sysdate']	= date('d M. Y', $return['sysstamp']);
						$return['password']	= ($return['password']=='' ? '0' : '1');
						pg_free_result($res);
						$sql = "SELECT id, title, tags, public, star, album, thumbUrl, takestamp, url FROM photos WHERE album = ".intval($this->albumIDs) . " " . $this->settings['sortingPhotos'];
						break;

		}

		# Get photos
		$res = pg_query($db,$sql);
		$previousPhotoID	= '';
		while ($row = pg_fetch_array($res)) {

			# Turn data from the database into a front-end friendly format
			$photo = Photo::prepareData($row);

			# Set previous and next photoID for navigation purposes
			$photo['previousPhoto'] = $previousPhotoID;
			$photo['nextPhoto']		= '';

			# Set current photoID as nextPhoto of previous photo
			if ($previousPhotoID!=='') $return['content'][$previousPhotoID]['nextPhoto'] = $photo['id'];
			$previousPhotoID = $photo['id'];

			# Add to return
			$return['content'][$photo['id']] = $photo;

		}

		if (pg_num_rows($res)<1) {

			# Album empty
			$return['content'] = false;

		} else {

			# Enable next and previous for the first and last photo
			$lastElement	= end($return['content']);
			$lastElementId	= $lastElement['id'];
			$firstElement	= reset($return['content']);
			$firstElementId	= $firstElement['id'];

			if ($lastElementId!==$firstElementId) {
				$return['content'][$lastElementId]['nextPhoto']			= $firstElementId;
				$return['content'][$firstElementId]['previousPhoto']	= $lastElementId;
			}

		}

		$return['id']	= $this->albumIDs;
		$return['num']	= pg_num_rows($res);
		pg_free_result($res);

		return $return;

	}

	public function getAll($public) {

		# Check dependencies
		self::dependencies(isset($this->settings, $public));

		# Initialize return var
		$return = array(
			'smartalbums'	=> null,
			'albums'		=> null,
			'num'			=> 0
		);

		# Get SmartAlbums
		if ($public===false) $return['smartalbums'] = $this->getSmartInfo();

		# Albums query
		if ($public===false)
		{
				$sql = "SELECT id, title, public, sysstamp, password FROM albums " . $this->settings['sortingAlbums'];
		} else {
				$sql = "SELECT id, title, public, sysstamp, password FROM albums WHERE public = 1 AND visible != 0 ". $this->settings['sortingAlbums'];
		}

		# Execute query
		$res = pg_query($db,$sql);

		# For each album
		while ($row = pg_fetch_array($res)) {

			# Turn data from the database into a front-end friendly format
			$album = Album::prepareData($row);

			# Thumbs
			if (($public===true&&$album['password']==='0')||
				($public===false)) {

					# Execute query
					$sql = "SELECT thumbUrl FROM photos WHERE album = ".intval($album['id'])." ORDER BY star DESC, " . substr($this->settings['sortingPhotos'], 9);
					$nres = pg_query($db,$sql);

					# For each thumb
					$k = 0;
					while ($nrow = pg_fetch_array($nres)) {
						$album['thumbs'][$k] = LYCHEE_URL_UPLOADS_THUMB . $nrow['thumbUrl'];
						$k++;
					}
					pg_free_result($nres);

			}

			# Add to return
			$return['albums'][] = $album;

		}

		# Num of albums
		$return['num'] = pg_num_rows($res);
		pg_free_result($res);

		return $return;

	}

	private function getSmartInfo() {

		# Check dependencies
		self::dependencies(isset($this->settings));

		# Initialize return var
		$return = array(
			'unsorted'	=> null,
			'public'	=> null,
			'starred'	=> null,
			'recent'	=> null
		);

		###
		# Unsorted
		###
		$sql = "SELECT thumbUrl FROM photos WHERE album = 0 " . $this->settings['sortingPhotos'];
		$res = pg_query($db,$sql);
		$i			= 0;

		$return['unsorted'] = array(
			'thumbs'	=> array(),
			'num'		=> pg_num_rows($res)
		);

		while($row = pg_fetch_array($res)) {
			if ($i<3) {
				$return['unsorted']['thumbs'][$i] = LYCHEE_URL_UPLOADS_THUMB . $row['thumbUrl'];
				$i++;
			} else break;
		}
		pg_free_result($res);

		###
		# Starred
		###

		$query		= "SELECT thumbUrl FROM photos WHERE star = 1 " . $this->settings['sortingPhotos'];
		$res = pg_query($db,$sql);
		$i			= 0;

		$return['starred'] = array(
			'thumbs'	=> array(),
			'num'		=> pg_num_rows($res)
		);

		while($row = pg_fetch_array($res)) {
			if ($i<3) {
				$return['starred']['thumbs'][$i] = LYCHEE_URL_UPLOADS_THUMB . $row['thumbUrl'];
				$i++;
			} else break;
		}
		pg_free_result($res);
		
		###
		# Public
		###

		$sql = "SELECT thumbUrl FROM photos WHERE public = 1 " . $this->settings['sortingPhotos'];
		$row = pg_query($db,$sql);
		$i			= 0;

		$return['public'] = array(
			'thumbs'	=> array(),
			'num'		=> pg_num_rows($res)
		);

		while($row = pg_fetch_array($res)) {
			if ($i<3) {
				$return['public']['thumbs'][$i] = LYCHEE_URL_UPLOADS_THUMB . $row['thumbUrl'];
				$i++;
			} else break;
		}

		pg_free_result($res);
		###
		# Recent
		###

		$check = strtotime('-24 HOURS');
		$sql = "SELECT thumbUrl FROM photos WHERE takestamp > ".$check ." ".$this->settings['sortingPhotos'];
		$res = pg_query($db,$sql);
		$i			= 0;

		$return['recent'] = array(
			'thumbs'	=> array(),
			'num'		=> pg_num_rows($res)
		);

		while($row = pg_fetch_array($res)) {
			if ($i<3) {
				$return['recent']['thumbs'][$i] = LYCHEE_URL_UPLOADS_THUMB . $row['thumbUrl'];
				$i++;
			} else break;
		}
		pg_free_result($res);
		
		# Return SmartAlbums
		return $return;

	}

	public function getArchive() {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Illicit chars
		$badChars =	array_merge(
						array_map('chr', range(0,31)),
						array("<", ">", ":", '"', "/", "\\", "|", "?", "*")
					);

		# Photos query
		switch($this->albumIDs) {
			case 's':
				$photo_sql		= "SELECT title, url FROM photos WHERE public = 1";
				$zipTitle	= 'Public';
				break;
			case 'f':
				$photo_sql		= "SELECT title, url FROM photos WHERE star = 1";
				$zipTitle	= 'Starred';
				break;
			case 'r':
				$check = strtotime('-24 HOURS');
				$photo_sql		= "SELECT title, url FROM photos WHERE takestamp > ".$check;
				$zipTitle	= 'Recent';
				break;
			default:
				$photo_sql		= "SELECT title, url FROM photos WHERE album = ".intval($this->albumIDs);
				$zipTitle	= 'Unsorted';
		}

		# Get title from database when album is not a SmartAlbum
		if ($this->albumIDs!=0&&is_numeric($this->albumIDs)) {

			$sql = "SELECT title FROM albums WHERE id = ".intval($this->albumIDs);
			$res = pg_query($db,$sql);

			$album = pg_fetch_array($res);

			$zipTitle = $album['title'];
			pg_free_result($res);

		}

		# Escape title
		$zipTitle = str_replace($badChars, '', $zipTitle);

		$filename = LYCHEE_DATA . $zipTitle . '.zip';

		# Create zip
		$zip = new ZipArchive();

		# Execute query
		$res = pg_query($db,$photo_sql);

		# Parse each path
		$files = array();
		while ($row = pg_fetch_array($res)) {

			# Parse url
			$row['url'] = LYCHEE_UPLOADS_BIG . $row['url'];

			# Parse title
			$row['title'] = str_replace($badChars, '', $row['title']);
			if (!isset($row['title'])||$row['title']==='') $row['title'] = 'Untitled';

			# Check if readable
			if (!@is_readable($row['url'])) continue;

			# Get extension of image
			$extension = getExtension($row['url']);

			# Set title for photo
			$zipFileName = $zipTitle . '/' . $row['title'] . $extension;

			# Check for duplicates
			if (!empty($files)) {
				$i = 1;
				while (in_array($zipFileName, $files)) {
					# Set new title for photo
					$zipFileName = $zipTitle . '/' . $row['title'] . '-' . $i . $extension;
					$i++;
				}
			}

			# Add to array
			$files[] = $zipFileName;

			# Add photo to zip
			$zip->addFile($row['url'], $zipFileName);

		}

		pg_free_result($res);
		# Finish zip
		$zip->close();

		# Send zip
		header("Content-Type: application/zip");
		header("Content-Disposition: attachment; filename=\"$zipTitle.zip\"");
		header("Content-Length: " . filesize($filename));
		readfile($filename);

		# Delete zip
		unlink($filename);

		return true;

	}

	public function setTitle($title = 'Untitled') {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Execute query
		$sql = "UPDATE albums SET title = ".pg_escape_literal($title)." WHERE id = " . intval($this->albumIDs);
		pg_query($db,$sql);

		return true;

	}

	public function setDescription($description = '') {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Execute query
		$sql = "UPDATE albums SET description = ".pg_escape_literal($description)." WHERE id = ".intval($this->albumIDs);
		pg_query($db,$sql);

		return true;

	}

	public function getPublic() {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		if ($this->albumIDs==='0'||$this->albumIDs==='s'||$this->albumIDs==='f') return false;

		# Execute query
		$sql = "SELECT public FROM albums WHERE id = ".intval($this->albumIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		pg_free_result($res);

		if ($row['public'])
		{
			pg_free_result($res);
			return true;
		} else {
			pg_free_result($res);
			return false;
		}

	}

	public function getDownloadable() {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		if ($this->albumIDs==='0'||$this->albumIDs==='s'||$this->albumIDs==='f'||$this->albumIDs==='r') return false;

		# Execute query
		$sql = "SELECT downloadable FROM albums WHERE id = ".intval($this->albumIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		
		if ($row['downloadable']==1)
		{
			pg_free_result($res);
			return true;
		} else {
			pg_free_result($res);
			return false;
		}

	}

	public function setPublic($public, $password, $visible, $downloadable) {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Convert values
		$public			= ($public==='1' ? 1 : 0);
		$visible		= ($visible==='1' ? 1 : 0);
		$downloadable	= ($downloadable==='1' ? 1 : 0);

		# Set public
		$sql	= "UPDATE albums SET public = ".pg_escape_literal($public).", visible = ".pg_escape_literal($visibl).", downloadable = ".pg_escape_literal($downloadable).", password = NULL WHERE id=" . intval($this->albumIDs);
		pg_query($db,$sql);

		# Reset permissions for photos
		if ($public===1) {
			$sql = "UPDATE photos SET public = 0 WHERE album = " .intval($this->albumIDs);
			pg_query($db,$sql);
		}

		# Set password
		if (isset($password)&&strlen($password)>0) return $this->setPassword($password);

		return true;

	}

	private function setPassword($password) {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		if (strlen($password)>0) {

			# Get hashed password
			$password = getHashedString($password);

			# Set hashed password
			# Do not prepare $password because it is hashed and save
			# Preparing (escaping) the password would destroy the hash
			$sql = "UPDATE albums SET password = ".pg_escape_literal($password)." WHERE id = " . intval($this->albumIDs);
			pg_query($db,$sql);
			
		} else {

			$sql = "UPDATE albums SET password = NULL WHERE id = " . intval($this->albumIDs);
			pg_query($db,$sql);

		}

		return true;

	}

	public function checkPassword($password) {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		$sql = "SELECT \"password\" FROM albums WHERE id=".intval($$this->albumIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		
		if ($row['password']=='') 
		{
				pg_free_result($res);
				return true;
		} else {
			if (password_verify($password,$row['password']))
			{
				pg_free_result($res);
				return true;
			} else {
				pg_free_result($res);
				return false;
			}
		}
		
		return false;

	}

	public function merge() {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Convert to array
		$albumIDs = explode(',', $this->albumIDs);

		# Get first albumID
		$albumID = array_shift($albumIDs);

		$sql = "UPDATE photos SET album = ".$albumID." WHERE album IN (".$this->albumIDs.")";
		pg_query($db,$sql);

		# $albumIDs contains all IDs without the first albumID
		# Convert to string
		$filteredIDs = implode(',', $albumIDs);

		$sql = "DELETE FROM albums WHERE id IN (".$filteredIDs.")";
		pg_query($db,$sql);

		return true;

	}

	public function delete() {

		# Check dependencies
		self::dependencies(isset($this->albumIDs));

		# Init vars
		$error = false;

		# Execute query
		$sql  = "SELECT id FROM photos WHERE album IN (".$this->albumIDs.")";
		$res = pg_query($db,$sql);
		
		# For each album delete photo
		while ($row = pg_fetch_array($res)) {
			$sql = "DELETE FROM photos WHERE id=".$row['id'];
			pg_query($db,$sql);
		}

		# Delete albums
		$sql = "DELETE FROM albums WHERE id IN (".$this->albumIDs.")";
		pg_query($db,$sql);

		return true;

	}

}

