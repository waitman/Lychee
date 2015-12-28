<?php

###
# @name			Define
# @copyright	2015 by Tobias Reich
###

# Define root
define('LYCHEE', substr(__DIR__, 0, -3));

# Define status
define('LYCHEE_STATUS_NOCONFIG', 0);
define('LYCHEE_STATUS_LOGGEDOUT', 1);
define('LYCHEE_STATUS_LOGGEDIN', 2);

# Define dirs
define('LYCHEE_DATA', LYCHEE . 'data/');
define('LYCHEE_SRC', LYCHEE . 'src/');
define('LYCHEE_UPLOADS', LYCHEE . 'uploads/');
define('LYCHEE_UPLOADS_BIG', LYCHEE_UPLOADS . 'big/');
define('LYCHEE_UPLOADS_MEDIUM', LYCHEE_UPLOADS . 'medium/');
define('LYCHEE_UPLOADS_THUMB', LYCHEE_UPLOADS . 'thumb/');
define('LYCHEE_UPLOADS_IMPORT', LYCHEE_UPLOADS . 'import/');
define('LYCHEE_PLUGINS', LYCHEE . 'plugins/');

# Define files
define('LYCHEE_CONFIG_FILE', LYCHEE_DATA . 'config.php');

# Define urls
define('LYCHEE_URL_UPLOADS_BIG', 'uploads/big/');
define('LYCHEE_URL_UPLOADS_MEDIUM', 'uploads/medium/');
define('LYCHEE_URL_UPLOADS_THUMB', 'uploads/thumb/');

define('LYCHEE_TABLE_ALBUMS', 'albums');
define('LYCHEE_TABLE_PHOTOS', 'photos');
define('LYCHEE_TABLE_SETTINGS', 'settings');
