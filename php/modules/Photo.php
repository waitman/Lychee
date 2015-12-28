<?php

###
# @name			Photo Module
# @copyright	2015 by Tobias Reich
# modified by Waitman Gobble <ns@waitman.net> 12/28/15
###

if (!defined('LYCHEE')) exit('Error: Direct access is not allowed!');

class Photo extends Module {

	private $settings	= null;
	private $photoIDs	= null;

	public static $validTypes = array(
		IMAGETYPE_JPEG,
		IMAGETYPE_GIF,
		IMAGETYPE_PNG
	);
	public static $validExtensions = array(
		'.jpg',
		'.jpeg',
		'.png',
		'.gif'
	);

	public function __construct($settings, $photoIDs) {

		# Init vars
		$this->settings	= $settings;
		$this->photoIDs	= $photoIDs;

		return true;

	}

	public function add($files, $albumID = 0, $description = '', $tags = '', $returnOnError = false) {

		# Use $returnOnError if you want to handle errors by your own
		# e.g. when calling this functions inside an if-condition

		# Check dependencies
		self::dependencies(isset($this->settings, $files));

		# Check permissions
		if (hasPermissions(LYCHEE_UPLOADS)===false||
			hasPermissions(LYCHEE_UPLOADS_BIG)===false||
			hasPermissions(LYCHEE_UPLOADS_THUMB)===false) {
				exit('Error: An upload-folder is missing or not readable and writable!');
		}

		switch($albumID) {

			case 's':
				# s for public (share)
				$public		= 1;
				$star		= 0;
				$albumID	= 0;
				break;

			case 'f':
				# f for starred (fav)
				$star		= 1;
				$public		= 0;
				$albumID	= 0;
				break;

			case 'r':
				# r for recent
				$public		= 0;
				$star		= 0;
				$albumID	= 0;
				break;

			default:
				$star		= 0;
				$public		= 0;
				break;

		}

		foreach ($files as $file) {

			# Check if file exceeds the upload_max_filesize directive
			if ($file['error']===UPLOAD_ERR_INI_SIZE) {
				if ($returnOnError===true) return false;
				exit('Error: The uploaded file exceeds the upload_max_filesize directive in php.ini!');
			}

			# Check if file was only partially uploaded
			if ($file['error']===UPLOAD_ERR_PARTIAL) {
				if ($returnOnError===true) return false;
				exit('Error: The uploaded file was only partially uploaded!');
			}

			# Check if writing file to disk failed
			if ($file['error']===UPLOAD_ERR_CANT_WRITE) {
				if ($returnOnError===true) return false;
				exit('Error: Failed to write photo to disk!');
			}

			# Check if a extension stopped the file upload
			if ($file['error']===UPLOAD_ERR_EXTENSION) {
				if ($returnOnError===true) return false;
				exit('Error: A PHP extension stopped the file upload!');
			}

			# Check if the upload was successful
			if ($file['error']!==UPLOAD_ERR_OK) {
				if ($returnOnError===true) return false;
				exit('Error: Upload failed!');
			}

			# Verify extension
			$extension = getExtension($file['name']);
			if (!in_array(strtolower($extension), Photo::$validExtensions, true)) {
				if ($returnOnError===true) return false;
				exit('Error: Photo format not supported!');
			}

			# Verify image
			$type = @exif_imagetype($file['tmp_name']);
			if (!in_array($type, Photo::$validTypes, true)) {
				if ($returnOnError===true) return false;
				exit('Error: Photo type not supported!');
			}

			# Generate id
			$sql = "INSERT INTO photos (id) VALUES (DEFAULT) RETURNING id";
			$res = pg_query($db,$sql);
			$row = pg_fetch_array($res);
			$id = $row['id'];
			pg_free_result($res);

			# Set paths
			$tmp_name	= $file['tmp_name'];
			$photo_name	= md5($id) . $extension;
			$path		= LYCHEE_UPLOADS_BIG . $photo_name;

			# Calculate checksum
			$checksum = sha1_file($tmp_name);
			if ($checksum===false) {
				if ($returnOnError===true) return false;
				exit('Error: Could not calculate checksum for photo!');
			}

			# Check if image exists based on checksum
			if ($checksum===false) {

				$checksum	= '';
				$exists		= false;

			} else {

				$exists = $this->exists($checksum);

				if ($exists!==false) {
					$photo_name	= $exists['photo_name'];
					$path		= $exists['path'];
					$path_thumb	= $exists['path_thumb'];
					$medium		= ($exists['medium']==='1' ? 1 : 0);
					$exists		= true;
				}

			}

			if ($exists===false) {

				# Import if not uploaded via web
				if (!is_uploaded_file($tmp_name)) {
					if (!@copy($tmp_name, $path)) {
						if ($returnOnError===true) return false;
						exit('Error: Could not copy photo to uploads!');
					} else @unlink($tmp_name);
				} else {
					if (!@move_uploaded_file($tmp_name, $path)) {
						if ($returnOnError===true) return false;
						exit('Error: Could not move photo to uploads!');
					}
				}

			} else {

				# Photo already exists
				# Check if the user wants to skip duplicates
				if ($this->settings['skipDuplicates']==='1') {
					if ($returnOnError===true) return false;
					exit('Warning: This photo has been skipped because it\'s already in your library.');
				}

			}

			# Read infos
			$info = $this->getInfo($path);

			# Use title of file if IPTC title missing
			if ($info['title']==='') $info['title'] = substr(basename($file['name'], $extension), 0, 30);

			# Use description parameter if set
			if ($description==='') $description = $info['description'];

			if ($exists===false) {

				# Set orientation based on EXIF data
				if ($file['type']==='image/jpeg'&&isset($info['orientation'])&&$info['orientation']!=='') {
					$adjustFile = $this->adjustFile($path, $info);
					if ($adjustFile!==false) $info = $adjustFile;
				}

				$info['takestamp']=time();

				# Create Thumb
				if (!$this->createThumb($path, $photo_name, $info['type'], $info['width'], $info['height'])) {
					if ($returnOnError===true) return false;
					exit('Error: Could not create thumbnail for photo!');
				}

				# Create Medium
				if ($this->createMedium($path, $photo_name, $info['width'], $info['height'])) $medium = 1;
				else $medium = 0;

				# Set thumb url
				$path_thumb = md5($id) . '.jpg';

			}

			# Save to DB
			$sql = "UPDATE photos SET \"title\"=".pg_escape_literal($info['title']).",".
					"\"description\"=".pg_escape_literal($description).",".
					"\"tags\"=".pg_escape_literal($tags).",".
					"\"type\"=".pg_escape_literal($info['type']).",".
					"\"width\"=".pg_escape_literal($info['width']).",".
					"\"height\"=".pg_escape_literal($info['height']).",".
					"\"size\"=".pg_escape_literal($info['size']).",".
					"\"iso\"=".pg_escape_literal($info['iso']).",".
					"\"aperature\"=".pg_escape_literal($info['aperature']).",".
					"\"make\"=".pg_escape_literal($info['make']).",".
					"\"model\"=".pg_escape_literal($info['model']).",".
					"\"shutter\"=".pg_escape_literal($info['shutter']).",".
					"\"focal\"=".pg_escape_literal($info['focal']).",".
					"\"takestamp\=".time().",".
					"\"thumbUrl\"=".pg_escape_literal($path_thumb).",".
					"\"album\"=".pg_escape_literal($albumID).",".
					"\"public\"=".pg_escape_literal($public).",".
					"\"star\"=".pg_escape_literal($star).",".
					"\"checksum\"=".pg_escape_literal($checksum).",".
					"\"medium\"=".pg_escape_literal($medium).
					"WHERE id=".intval($id);
					
			pg_query($db,$sql) or die($sql);
				

		}

		return true;

	}

	private function exists($checksum, $photoID = null) {

		# Check dependencies
		self::dependencies(isset($checksum));

		# Exclude $photoID from select when $photoID is set
		if (isset($photoID)) 
		{
			$sql = "SELECT id, url, thumbUrl, medium FROM photos WHERE checksum = ".pg_escape_literal($checksum)." AND id != ".pg_escape_literal($photoID);
		} else {
			$sql = "SELECT id, url, thumbUrl, medium FROM photos WHERE checksum = ".pg_escape_literal($checksum);
		}

		$res	= pg_query($db,$sql);

		if (pg_num_rows($res)>0) {

			$row = pg_fetch_array($res);
			

			$return = array(
				'photo_name'	=> $row['url'],
				'path'			=> LYCHEE_UPLOADS_BIG . $row['url'],
				'path_thumb'	=> $row['thumbUrl'],
				'medium'		=> $row['medium']
			);
			pg_free_result($res);
			return $return;

		}
		pg_free_result($res);
		return false;

	}

	private function createThumb($url, $filename, $type, $width, $height) {

		# Check dependencies
		self::dependencies(isset($this->settings, $url, $filename, $type, $width, $height));

		# Size of the thumbnail
		$newWidth	= 200;
		$newHeight	= 200;

		$photoName	= explode('.', $filename);
		$newUrl		= LYCHEE_UPLOADS_THUMB . $photoName[0] . '.jpeg';
		$newUrl2x	= LYCHEE_UPLOADS_THUMB . $photoName[0] . '@2x.jpeg';

		# Create thumbnails with Imagick
		if(extension_loaded('imagick')&&$this->settings['imagick']==='1') {

			# Read image
			$thumb = new Imagick();
			$thumb->readImage($url);
			$thumb->setImageCompressionQuality($this->settings['thumbQuality']);
			$thumb->setImageFormat('jpeg');

			# Copy image for 2nd thumb version
			$thumb2x = clone $thumb;

			# Create 1st version
			$thumb->cropThumbnailImage($newWidth, $newHeight);
			$thumb->writeImage($newUrl);
			$thumb->clear();
			$thumb->destroy();

			# Create 2nd version
			$thumb2x->cropThumbnailImage($newWidth*2, $newHeight*2);
			$thumb2x->writeImage($newUrl2x);
			$thumb2x->clear();
			$thumb2x->destroy();

		} else {

			# Create image
			$thumb		= imagecreatetruecolor($newWidth, $newHeight);
			$thumb2x	= imagecreatetruecolor($newWidth*2, $newHeight*2);

			# Set position
			if ($width<$height) {
				$newSize		= $width;
				$startWidth		= 0;
				$startHeight	= $height/2 - $width/2;
			} else {
				$newSize		= $height;
				$startWidth		= $width/2 - $height/2;
				$startHeight	= 0;
			}

			# Create new image
			switch($type) {
				case 'image/jpeg':	$sourceImg = imagecreatefromjpeg($url); break;
				case 'image/png':	$sourceImg = imagecreatefrompng($url); break;
				case 'image/gif':	$sourceImg = imagecreatefromgif($url); break;
				default:			return false;
									break;
			}

			# Create thumb
			fastimagecopyresampled($thumb, $sourceImg, 0, 0, $startWidth, $startHeight, $newWidth, $newHeight, $newSize, $newSize);
			imagejpeg($thumb, $newUrl, $this->settings['thumbQuality']);
			imagedestroy($thumb);

			# Create retina thumb
			fastimagecopyresampled($thumb2x, $sourceImg, 0, 0, $startWidth, $startHeight, $newWidth*2, $newHeight*2, $newSize, $newSize);
			imagejpeg($thumb2x, $newUrl2x, $this->settings['thumbQuality']);
			imagedestroy($thumb2x);

			# Free memory
			imagedestroy($sourceImg);

		}

		return true;

	}

	private function createMedium($url, $filename, $width, $height) {

		# Function creates a smaller version of a photo when its size is bigger than a preset size
		# Excepts the following:
		# (string) $url = Path to the photo-file
		# (string) $filename = Name of the photo-file
		# (int) $width = Width of the photo
		# (int) $height = Height of the photo
		# Returns the following
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->settings, $url, $filename, $width, $height));


		# Set to true when creation of medium-photo failed
		$error = false;

		# Size of the medium-photo
		# When changing these values,
		# also change the size detection in the front-end
		$newWidth	= 1920;
		$newHeight	= 1080;

		# Check permissions
		if (hasPermissions(LYCHEE_UPLOADS_MEDIUM)===false) {

			# Permissions are missing
			$error = true;

		}

		# Is photo big enough?
		# Is medium activated?
		# Is Imagick installed and activated?
		if (($error===false)&&
			($width>$newWidth||$height>$newHeight)&&
			($this->settings['medium']==='1')&&
			(extension_loaded('imagick')&&$this->settings['imagick']==='1')) {

			$newUrl = LYCHEE_UPLOADS_MEDIUM . $filename;

			# Read image
			$medium = new Imagick();
			$medium->readImage($url);

			# Adjust image
			$medium->scaleImage($newWidth, $newHeight, true);

			# Save image
			try { $medium->writeImage($newUrl); }
			catch (ImagickException $err) {
				$error = true;
			}

			$medium->clear();
			$medium->destroy();

		} else {

			# Photo too small or
			# Medium is deactivated or
			# Imagick not installed
			$error = true;

		}

		if ($error===true) return false;
		return true;

	}

	public function adjustFile($path, $info) {

		# Function rotates and flips a photo based on its EXIF orientation
		# Excepts the following:
		# (string) $path = Path to the photo-file
		# (array) $info = ['orientation', 'width', 'height']
		# Returns the following
		# (array) $info = ['orientation', 'width', 'height'] = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($path, $info));

		$swapSize = false;

		if (extension_loaded('imagick')&&$this->settings['imagick']==='1') {

			switch ($info['orientation']) {

				case 3:
					$rotateImage = 180;
					break;

				case 6:
					$rotateImage	= 90;
					$swapSize		= true;
					break;

				case 8:
					$rotateImage	= 270;
					$swapSize		= true;
					break;

				default:
					return false;
					break;

			}

			if ($rotateImage!==0) {
				$image = new Imagick();
				$image->readImage($path);
				$image->rotateImage(new ImagickPixel(), $rotateImage);
				$image->setImageOrientation(1);
				$image->writeImage($path);
				$image->clear();
				$image->destroy();
			}

		} else {

			$newWidth	= $info['width'];
			$newHeight	= $info['height'];
			$sourceImg	= imagecreatefromjpeg($path);

			switch ($info['orientation']) {

				case 2:
					# mirror
					# not yet implemented
					return false;
					break;

				case 3:
					$sourceImg	= imagerotate($sourceImg, -180, 0);
					break;

				case 4:
					# rotate 180 and mirror
					# not yet implemented
					return false;
					break;

				case 5:
					# rotate 90 and mirror
					# not yet implemented
					return false;
					break;

				case 6:
					$sourceImg	= imagerotate($sourceImg, -90, 0);
					$newWidth	= $info['height'];
					$newHeight	= $info['width'];
					$swapSize	= true;
					break;

				case 7:
					# rotate -90 and mirror
					# not yet implemented
					return false;
					break;

				case 8:
					$sourceImg	= imagerotate($sourceImg, 90, 0);
					$newWidth	= $info['height'];
					$newHeight	= $info['width'];
					$swapSize	= true;
					break;

				default:
					return false;
					break;

			}

			# Recreate photo
			$newSourceImg = imagecreatetruecolor($newWidth, $newHeight);
			imagecopyresampled($newSourceImg, $sourceImg, 0, 0, 0, 0, $newWidth, $newHeight, $newWidth, $newHeight);
			imagejpeg($newSourceImg, $path, 100);

			# Free memory
			imagedestroy($sourceImg);
			imagedestroy($newSourceImg);

		}

		# SwapSize should be true when the image has been rotated
		# Return new dimensions in this case
		if ($swapSize===true) {
			$swapSize		= $info['width'];
			$info['width']	= $info['height'];
			$info['height']	= $swapSize;
		}

		return $info;

	}

	public static function prepareData($data) {

		# Function turns photo-attributes into a front-end friendly format. Note that some attributes remain unchanged.
		# Excepts the following:
		# (array) $data = ['id', 'title', 'tags', 'public', 'star', 'album', 'thumbUrl', 'takestamp', 'url']
		# Returns the following:
		# (array) $photo

		# Check dependencies
		self::dependencies(isset($data));

		# Init
		$photo = null;

		# Set unchanged attributes
		$photo['id']		= $data['id'];
		$photo['title']		= $data['title'];
		$photo['tags']		= $data['tags'];
		$photo['public']	= $data['public'];
		$photo['star']		= $data['star'];
		$photo['album']		= $data['album'];

		# Parse urls
		$photo['thumbUrl']	= LYCHEE_URL_UPLOADS_THUMB . $data['thumbUrl'];
		$photo['url']		= LYCHEE_URL_UPLOADS_BIG . $data['url'];

		# Use takestamp as sysdate when possible
		if (isset($data['takestamp'])&&$data['takestamp']!=='0') {

			# Use takestamp
			$photo['cameraDate']	= '1';
			$photo['sysdate']		= date('d F Y', $data['takestamp']);

		} else {

			# Use sysstamp from the id
			$photo['cameraDate']	= '0';
			$photo['sysdate']		= date('d F Y', substr($data['id'], 0, -4));

		}

		return $photo;

	}

	public function get($albumID) {

		# Functions returns data of a photo
		# Excepts the following:
		# (string) $albumID = Album which is currently visible to the user
		# Returns the following:
		# (array) $photo

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Get photo
		$sql = "SELECT * FROM photos WHERE id=".pg_escape_literal($this->photoIDs);
		
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		$photo = $row;
		
		# Parse photo
		$photo['sysdate'] = date('d M. Y', substr($photo['id'], 0, -4));
		if (strlen($photo['takestamp'])>1) $photo['takedate'] = date('d M. Y', $photo['takestamp']);

		# Parse medium
		if ($photo['medium']==='1')	$photo['medium'] = LYCHEE_URL_UPLOADS_MEDIUM . $photo['url'];
		else						$photo['medium'] = '';

		# Parse paths
		$photo['url']		= LYCHEE_URL_UPLOADS_BIG . $photo['url'];
		$photo['thumbUrl']	= LYCHEE_URL_UPLOADS_THUMB . $photo['thumbUrl'];

		if ($albumID!='false') {

			# Only show photo as public when parent album is public
			# Check if parent album is not 'Unsorted'
			if ($photo['album']!=='0') {

				# Get album
				$sql = "SELECT public FROM albums WHERE id=".intval($photo['album']);
				$nres = pg_query($db,$sql);
				$nrow = pg_fetch_array($res);
				

				# Parse album
				$photo['public'] = ($nrow['public']==='1' ? '2' : $photo['public']);
				pg_free_result($nres);
			}

			$photo['original_album']	= $photo['album'];
			$photo['album']				= $albumID;

		}
		pg_free_result($res);
		
		return $photo;

	}

	public function getInfo($url) {

		# Functions returns information and metadata of a photo
		# Excepts the following:
		# (string) $url = Path to photo-file
		# Returns the following:
		# (array) $return

		# Check dependencies
		self::dependencies(isset($url));

		$iptcArray	= array();
		$info		= getimagesize($url, $iptcArray);

		# General information
		$return['type']		= $info['mime'];
		$return['width']	= $info[0];
		$return['height']	= $info[1];

		# Size
		$size = filesize($url)/1024;
		if ($size>=1024) $return['size'] = round($size/1024, 1) . ' MB';
		else $return['size'] = round($size, 1) . ' KB';

		# IPTC Metadata Fallback
		$return['title']		= '';
		$return['description']	= '';

		# IPTC Metadata
		if(isset($iptcArray['APP13'])) {

			$iptcInfo = iptcparse($iptcArray['APP13']);
			if (is_array($iptcInfo)) {

				$temp = @$iptcInfo['2#105'][0];
				if (isset($temp)&&strlen($temp)>0) $return['title'] = $temp;

				$temp = @$iptcInfo['2#120'][0];
				if (isset($temp)&&strlen($temp)>0) $return['description'] = $temp;

				$temp = @$iptcInfo['2#005'][0];
				if (isset($temp)&&strlen($temp)>0&&$return['title']==='') $return['title'] = $temp;

			}

		}

		# EXIF Metadata Fallback
		$return['orientation']	= '';
		$return['iso']			= '';
		$return['aperture']		= '';
		$return['make']			= '';
		$return['model']		= '';
		$return['shutter']		= '';
		$return['focal']		= '';
		$return['takestamp']	= 0;

		# Read EXIF
		if ($info['mime']=='image/jpeg') $exif = @exif_read_data($url, 'EXIF', 0);
		else $exif = false;

		# EXIF Metadata
		if ($exif!==false) {

			if (isset($exif['Orientation'])) $return['orientation'] = $exif['Orientation'];
			else if (isset($exif['IFD0']['Orientation'])) $return['orientation'] = $exif['IFD0']['Orientation'];

			$temp = @$exif['ISOSpeedRatings'];
			if (isset($temp)) $return['iso'] = $temp;

			$temp = @$exif['COMPUTED']['ApertureFNumber'];
			if (isset($temp)) $return['aperture'] = $temp;

			$temp = @$exif['Make'];
			if (isset($temp)) $return['make'] = trim($temp);

			$temp = @$exif['Model'];
			if (isset($temp)) $return['model'] = trim($temp);

			$temp = @$exif['ExposureTime'];
			if (isset($temp)) $return['shutter'] = $exif['ExposureTime'] . ' s';

			$temp = @$exif['FocalLength'];
			if (isset($temp)) {
				if (strpos($temp, '/')!==FALSE) {
					$temp = explode('/', $temp, 2);
					$temp = $temp[0] / $temp[1];
					$temp = round($temp, 1);
					$return['focal'] = $temp . ' mm';
				}
				$return['focal'] = $temp . ' mm';
			}

			$temp = @$exif['DateTimeOriginal'];
			if (isset($temp)) $return['takestamp'] = strtotime($temp);

		}

		return $return;

	}

	public function getArchive() {

		# Functions starts a download of a photo
		# Returns the following:
		# (boolean + output) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Get photo
		$sql = "SELECT title, url FROM photos WHERE id = ".pg_escape_literal($this->photoIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		$photo = $row;

		# Photo not found
		if (pg_num_rows($res)<1) {
			pg_free_result($res);
			return false;
		}

		# Get extension
		$extension = getExtension($photo['url']);
		if ($extension===false) {
			return false;
		}

		# Illicit chars
		$badChars =	array_merge(
						array_map('chr', range(0,31)),
						array("<", ">", ":", '"', "/", "\\", "|", "?", "*")
					);

		# Parse title
		if ($photo['title']=='') $photo['title'] = 'Untitled';

		# Escape title
		$photo['title'] = str_replace($badChars, '', $photo['title']);

		# Set headers
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"" . $photo['title'] . $extension . "\"");
		header("Content-Length: " . filesize(LYCHEE_UPLOADS_BIG . $photo['url']));

		# Send file
		readfile(LYCHEE_UPLOADS_BIG . $photo['url']);

		return true;

	}

	public function setTitle($title) {

		# Functions sets the title of a photo
		# Excepts the following:
		# (string) $title = Title with a maximum length of 50 chars
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Set title
		$sql = "UPDATE photos SET title = ".pg_escape_literal($title)." WHERE id IN (".$this->photoIDs.")";
		pg_query($db,$sql);

		return true;

	}

	public function setDescription($description) {

		# Functions sets the description of a photo
		# Excepts the following:
		# (string) $description = Description with a maximum length of 1000 chars
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Set description
		$sql = "UPDATE photos SET description = ".pg_escape_literal($description)." WHERE id IN (".$this->photoIDs.")";
		pg_query($db,$sql);

		return true;

	}

	public function setStar() {

		# Functions stars a photo
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Init vars
		$error	= false;

		# Get photos
		$sql ="SELECT id, star FROM photos WHERE id IN (".pg_escape_string($this->photoIDs).")";
		$res = pg_query($db,$sql);
		

		# For each photo
		while ($photo = pg_fetch_array($res)) {

			# Invert star
			$star = ($photo['star']==0 ? 1 : 0);

			# Set star
			$sql = "UPDATE photos SET star = ".pg_escape_literal($star)." WHERE id=".pg_escape_literal($row['id']);
			pg_query($db,$sql);
		}
		pg_free_result($res);

		return true;

	}

	public function getPublic($password) {

		# Functions checks if photo or parent album is public
		# Returns the following:
		# (int) 0 = Photo private and parent album private
		# (int) 1 = Album public, but password incorrect
		# (int) 2 = Photo public or album public and password correct

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Get photo
		$sql = "SELECT public, album FROM photos WHERE id = ".pg_escape_literal($this->photoIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		
		# Check if public
		if ($row['public']==='1') {

			# Photo public
			pg_free_result($res);
			return 2;

		} else {

			# Check if album public
			$album	= new Album(null, null, $row['album']);
			$agP	= $album->getPublic();
			$acP	= $album->checkPassword($password);

			# Album public and password correct
			if ($agP===true&&$acP===true) 
			{
				pg_free_result($res);
				return 2;
			}

			# Album public, but password incorrect
			if ($agP===true&&$acP===false) 
			{
				pg_free_result($res);
				return 1;
			}

		}

		pg_free_result($res);
		# Photo private
		return 0;

	}

	public function setPublic() {

		# Functions toggles the public property of a photo
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Get public
		$sql = "SELECT public FROM photos WHERE id = ".pg_escape_literal($this->photoIDs);
		$res = pg_query($db,$sql);
		$row = pg_fetch_array($res);
		
		# Invert public
		$public = ($row['public']==0 ? 1 : 0);

		# Set public
		$sql = "UPDATE photos SET public = ".pg_escape_literal($public)." WHERE id = ".pg_escape_literal($this->photoIDs);
		pg_query($db,$sql);

		return true;

	}

	function setAlbum($albumID) {

		# Functions sets the parent album of a photo
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));


		# Set album
		$sql = "UPDATE photos SET album = ".pg_escape_literal($albumID)." WHERE id IN (".$this->photoIDS.")";
		pg_query($db,$sql);

		return true;

	}

	public function setTags($tags) {

		# Functions sets the tags of a photo
		# Excepts the following:
		# (string) $tags = Comma separated list of tags with a maximum length of 1000 chars
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Parse tags
		$tags = preg_replace('/(\ ,\ )|(\ ,)|(,\ )|(,{1,}\ {0,})|(,$|^,)/', ',', $tags);
		$tags = preg_replace('/,$|^,|(\ ){0,}$/', '', $tags);

		# Set tags
		$sql = "UPDATE photos SET tags = ".pg_escape_literal($tags)." WHERE id IN (".$this->photoIDs.")";
		pg_query($db,$sql);

		return true;

	}

	public function duplicate() {
/* why dup???? */
		return true;

	}

	public function delete() {

		# Functions deletes a photo with all its data and files
		# Returns the following:
		# (boolean) true = Success
		# (boolean) false = Failure

		# Check dependencies
		self::dependencies(isset($this->photoIDs));

		# Get photos
		$sql = "SELECT id, url, thumbUrl, checksum FROM photos WHERE id IN (".$this->photoIDs.")";
		$res = pg_query($db,$sql);
		
		while ($photo = pg_fetch_array($res)) {

			
			if ($this->exists($photo['checksum'], $photo['id'])===false) {

				# Get retina thumb url
				$thumbUrl2x = explode(".", $photo['thumbUrl']);
				$thumbUrl2x = $thumbUrl2x[0] . '@2x.' . $thumbUrl2x[1];

				# Delete big
				if (file_exists(LYCHEE_UPLOADS_BIG . $photo['url'])&&!unlink(LYCHEE_UPLOADS_BIG . $photo['url'])) {
					return false;
				}

				# Delete medium
				if (file_exists(LYCHEE_UPLOADS_MEDIUM . $photo['url'])&&!unlink(LYCHEE_UPLOADS_MEDIUM . $photo['url'])) {
					return false;
				}

				# Delete thumb
				if (file_exists(LYCHEE_UPLOADS_THUMB . $photo['thumbUrl'])&&!unlink(LYCHEE_UPLOADS_THUMB . $photo['thumbUrl'])) {
					return false;
				}

				# Delete thumb@2x
				if (file_exists(LYCHEE_UPLOADS_THUMB . $thumbUrl2x)&&!unlink(LYCHEE_UPLOADS_THUMB . $thumbUrl2x))	 {
					return false;
				}

			}

			# Delete db entry
			$sql = "DELETE FROM photos WHERE id = ".pg_escape_literal($photo->id);
			pg_query($db,$sql);

		}

		pg_free_result($res);
		return true;

	}

}
