<?php

/**
 * EXTERNAL APP UPLOAD v2.1
 * For use with the Pixelpost Uploader & Lightroom Plugin
 * 
 * @author Jay Williams
 * @author Dennis Mooibroek
 */

/**
 * This is the "master password" that allows external apps to upload photos to Pixelost.
 * Make sure the key is LONG and hard to guess.  You can always copy-paste the key into
 * the application you are wanting to use if it is too long to type.
 * 
 * For a good post key, check out this site: https://www.grc.com/passwords.htm
 **/
define("POSTKEY", "InsertYourSecretPostKeyHere");

/**
 * When you enter categories, you can have the application automatically create 
 * new categories if the one you entered does not exist.  To enable this feature,
 * change the text from false to true.
 **/
define("CREATECAT", false);

/**
 * If this is set to "true", Pixelpost will import the tags from Lightroom. 
 * If you don't want this, simply set it to "false".
 */
define("ENABLETAGS", true);

/**
 * While Lightroom is perfectly happy containing tags with spaces, this is used as a
 * separator in Pixelpost causing the tag "My family" to appear as two tags: "My" and "family"
 * Here you can set the space replacement string in the tags. Default is an underscore "_" 
 * (e.g. "My_family") If you would like to disable this feature, simply enter a space " " as the value.
 * Other values, such as Using a hyphen "-" or setting it to a blank value "" are possible as well.
 * (e.g. My-family or Myfamily)
*/
define("TAGSPACEREPLACEMENT", "_");

/**
 * Pixelpost allows you to upload a post several days after the last post (posting in the future). With this
 * setting you can manipulate this behavior. Default setting is one day after last post.
 */
define("POSTINTERVAL", 1);

/**
 * The title for an image is captured from the caption setting in Lightroom. When that is not available
 * it will default to the image filename (e.g. IMG_xxxx). If you you'd rather have the title show up blank 
 * if no caption is available, set BLANKTITLE to true.
 */
define("BLANKTITLE", false);


////////// DO NOT EDIT BELOW THIS LINE UNLESS YOU KNOW WHAT YOU ARE DOING! //////////


// Based off of the code from:
// SVN file version:
// $Id: index.php 517 2008-01-16 20:01:47Z d3designs $


/**
 * If we are on the Addons page in the admin panel, we don't need to execute the rest of this code, 
 * we only need the config variables, so we will stop here.
 */
if (isset($_GET['view']))
{
	return true;
}

error_reporting(0);
$PHP_SELF = "index.php";
define('ADDON_DIR', '../addons/');

/**
 * Check if the postkey matches, and the mode is set to either validate or upload
 **/
if (!isset($_GET['post_key_hash']) || $_GET['post_key_hash'] != md5(POSTKEY))
{
	die("ERROR: Incorrect Post Key");
}

if (isset($_GET['mode']) and $_GET['mode'] == 'validate')
{
	die('OK');
} elseif (isset($_GET['mode']) and $_GET['mode'] == 'upload')
{
	// Continue on our way...
}
else
{
	die('ERROR: Incorrect Mode');
}

// Start capturing all of the output, to prevent any issues later on.
ob_start();

/**
 * Trim the file extension from the title, if the title is a filename.
 * If the BLANKTITLE option is enabled, filenames will be removed entirely
 */
$title      = pathinfo($_POST['title']);
$extensions = array('jpg','jpeg','png','gif');

if(in_array(@strtolower($title['extension']), $extensions))
{
	$_POST['title'] = $title['filename'];
	
	if (BLANKTITLE)
	{
		$_POST['title'] = '';
	}
}


/**
 * Translate the Lightroom $_POST variables to Pixelpost format
 **/

if(isset($_FILES['title']))
	$_POST['headline'] = $_POST['title'];

if(isset($_FILES['description']))	
	$_POST['body']     = $_POST['description'];

/**
 * If tags are disabled, we can remove them from the post.
 */
if (!ENABLETAGS)
{
	$_POST['tags'] = '';
}

/**
 * Replace any spaces in tags with underscores, to maintain the full LR names:
 */
$_POST['tags'] = str_replace(array(' ', TAGSPACEREPLACEMENT . ',', ',' . TAGSPACEREPLACEMENT), array(TAGSPACEREPLACEMENT, ',', ','), trim($_POST['tags'], ', '));

if(isset($_FILES['photo']))
	$_FILES['userfile'] = $_FILES['photo'];


/**
 * Provide addon support
 */
$_GET['x'] = 'save';

// Hack to get post slug to auto-generate titles
$_POST['postslug'] = "";

// pretend we are inside admin panel (i.e. to use in addons)
$admin_panel = 1;
$postdatefromexif = false; //asume we don't want to post from exif
session_start();

/**
 * Logs out from the admin panel, exits the script, 
 * and displays the specified message.
 */
function die_logout($message = '')
{
	// Unset all of the session variables.
	$_SESSION = array();

	setcookie("pp_user", "", time() - 36000);
	setcookie("pp_password", "", time() - 36000);

	// Finally, destroy the session.
	session_destroy();
	
	die($message);
}


/**
 * Include supporting functions
 */
require ("../includes/pixelpost.php");
require ("../includes/functions.php");
start_mysql('../includes/pixelpost.php', 'admin');

/**
 * Check Pixelpost version
 */
if (Get_Pixelpost_Version($pixelpost_db_prefix) < 1.71)
{
	die_logout('ERROR: Version Mismatch!');
}

/**
 * Populate the $cfgrow
 */
if ($cfgquery = mysql_query("select * from " . $pixelpost_db_prefix . "config"))
{
	$cfgrow = mysql_fetch_assoc($cfgquery);
	$upload_dir = $cfgrow['imagepath'];
}
else
{
	die_logout('ERROR: Can\'t load config');
}

/**
 * Make the script believe we are actually logged in into the adminpanel
 */
$_POST['user']                        = $cfgrow['admin'];
$_SESSION["pixelpost_admin"]          = $cfgrow['password'];
$_GET["_SESSION"]["pixelpost_admin"]  = '';
$_POST["_SESSION"]["pixelpost_admin"] = '';
$login                                = "true";


/**
 * Start enabling the addons for the adminpanel
 */
refresh_addons_table("../addons/");
$addon_admin_functions = array(0 => array('function_name' => '', 'workspace' => '', 'menu_name' => '', 'submenu_name' => ''));
create_admin_addon_array();

if (!isset($_SESSION["pixelpost_admin"]) || $cfgrow['password'] != $_SESSION["pixelpost_admin"] || $_GET["_SESSION"]["pixelpost_admin"] == $_SESSION["pixelpost_admin"] || $_POST["_SESSION"]["pixelpost_admin"] == $_SESSION["pixelpost_admin"] || $_COOKIE["_SESSION"]["pixelpost_admin"] == $_SESSION["pixelpost_admin"])
{
	die_logout("ERROR: Automatic login has failed");
}

/**
 * Start saving a new post
 */
$headline = clean($_POST['headline']);
$body     = clean($_POST['body']);

//----------------------------------------------------------------------------------------------------------------------------------------
// Lightroom only supports one headline in one language... what to do with the ALT_language? Perhaps an option to fill it with the same data
// as the standard set?
if (isset($_POST['alt_headline']))
{
	//Obviously we would like to use the alternative language
	$alt_headline = clean($_POST['alt_headline']);
	$alt_body = clean($_POST['alt_body']);
	$alt_tags = clean($_POST['alt_tags']);
}
else
{
	$alt_headline = "";
	$alt_body = "";
	$alt_tags = "";
}

$comments_settings = clean($_POST['allow_comments']);

/**
 * Default datetime is current date time
 **/
$datetime = gmdate("Y-m-d H:i:s", time() + (3600 * $cfgrow['timezone']));
switch ($_POST['autodate'])
{
	case 1:
		$query = mysql_query("select datetime + INTERVAL " . POSTINTERVAL . " DAY from " . $pixelpost_db_prefix . "pixelpost order by datetime desc limit 1");
		$row = mysql_fetch_row($query);
		if ($row) $datetime = $row[0]; // If there is none, will default to the other value
		break;
	case 2:
		// use the default date time provided
		break;
	case 3:
		$postdatefromexif = true;
		break;
}

$status = "no"; //assume the upload has failed by default

/**
 * Try to upload the file and copy it to the images folder
 **/
$userfile = strtolower($_FILES['userfile']['name']);
$tz = $cfgrow['timezone'];

if ($cfgrow['timestamp'] == 'yes')
{
	$time_stamp_r = gmdate("YmdHis", time() + (3600 * $tz)) . '_';
}
else
{
	$time_stamp_r = '';
}

$uploadfile = $upload_dir . $time_stamp_r . $userfile;

eval_addon_admin_workspace_menu('image_upload_start');

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile))
{
	chmod($uploadfile, 0644);
	$result     = check_upload($_FILES['userfile']['error']);
	$filnamn    = strtolower($_FILES['userfile']['name']);
	$filnamn    = $time_stamp_r . $filnamn;
	$filtyp     = $_FILES['userfile']['type'];
	$filstorlek = $_FILES['userfile']['size'];
	$status     = "ok";

	//Get the exif data so we can store it.
	// what about files that don't have exif data??
	include_once ('../includes/functions_exif.php');

	$exif_info_db = serialize_exif($uploadfile);

	if ($postdatefromexif == true)
	{
		// since we all ready escaped everything for database commit we have
		// strip the slashes before we can use the exif again.
		$exif_info        = stripslashes($exif_info_db);
		$exif_result      = unserialize_exif($exif_info);
		$exposuredatetime = $exif_result['DateTimeOriginalSubIFD'];

		if ($exposuredatetime != '')
		{
			list($exifyear, $exifmonth, $exifday, $exifhour, $exifmin, $exifsec) = split('[: ]', $exposuredatetime);
			$datetime = date("Y-m-d H:i:s", mktime($exifhour, $exifmin, $exifsec, $exifmonth, $exifday, $exifyear));
		}
		else  $datetime = gmdate("Y-m-d H:i:s", time() + (3600 * $tz));
	}
	
	eval_addon_admin_workspace_menu('image_upload_succesful');
}
else
{
	/**
	 * The upload has failed, describe what went wrong
	 **/
	if ($_FILES['userfile']['error'] != '0') $result = check_upload($_FILES['userfile']['error']);
	else  die_logout("ERROR: Image uploading has failed");
	if (!is__writable($upload_dir)) die_logout("ERROR: The folders are not open for writing");
	eval_addon_admin_workspace_menu('image_upload_failed');
} // end move


$image = $filnamn;

/**
 * If the image was uploaded ok we can populate the database with values
 **/
if ($status != "ok")
{
	/**
	 * There was an error while uploading
	 * Let the program know
	 **/
	$output = ob_get_contents();
	ob_end_clean();
	echo "ERROR: \n";
	die_logout($output);
}


$query = "INSERT INTO " . $pixelpost_db_prefix . "pixelpost 
		(datetime,headline,body,image,alt_headline,alt_body,comments,exif_info)
		VALUES('$datetime','$headline','$body','$image','$alt_headline',
		'$alt_body','$comments_settings','$exif_info_db')";
$result = mysql_query($query) || die_logout("Error: " . mysql_error() . $admin_lang_ni_db_error);
$theid  = mysql_insert_id(); //Gets the id of the last added image to use in the next "insert"

/**
 * Support for the GooglemapAddon
 * 
 * since we all ready escaped everything for database commit we have
 * strip the slashes before we can use the exif again.
 **/
$exif_info = stripslashes($exif_info_db);
$exif_info = unserialize_exif($exif_info);

// try to get the GPS exif data
if (array_key_exists('LatitudeGPS', $exif_info))
{
	$_POST['imagePointLat'] = ($exif_info['Latitude ReferenceGPS'] == "S")? '-'. $exif_info['LatitudeGPS'] : $exif_info['LatitudeGPS'];
	$_POST['imagePointLng'] = ($exif_info['Longitude ReferenceGPS'] == "W")? '-'. $exif_info['LongitudeGPS'] : $exif_info['LongitudeGPS'];
	
	// add backwards compatibility with GooglemapAddon v2
	$_POST['imagePoint'] = "({$_POST['imagePointLat']},{$_POST['imagePointLng']})";
}
	
/**
 * Support for categories
 **/
if (isset($_POST['category']))
{
	$query_val = array();
	foreach ($_POST['category'] as $val)
	{
		$val = clean($val);
		$query_val[] = "(NULL,'$val','$theid')";
	}
	$query_st = "INSERT INTO " . $pixelpost_db_prefix . "catassoc (id,cat_id,image_id) VALUES " . implode(",", $query_val) . ";";
	$result = mysql_query($query_st) || die_logout("Error: " . mysql_error());
}

eval_addon_admin_workspace_menu('image_uploaded');
save_tags_new(clean($_POST['tags']), $theid);

//----------------------------------------------------------------------------------------------------------------------------------------
if ($cfgrow['altlangfile'] != 'Off') save_tags_new(clean($_POST['alt_tags']), $theid, "alt_");

/**
 * Create a thumbnail
 **/
if (function_exists('gd_info'))
{
	$gd_info = gd_info();

	if ($gd_info != "")
	{
		$thumbnail = $filnamn;
		$thumbnail = createthumbnail($thumbnail);
		eval_addon_admin_workspace_menu('thumb_created');
	} // end if
} // function_exists


eval_addon_admin_workspace_menu('upload_finished');

/**
 * Image has been uploaded with success
 * Our job is done...
 * Let the program know
 **/
ob_end_clean();
die_logout("OK");

?>