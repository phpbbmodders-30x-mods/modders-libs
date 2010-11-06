<?php
/**
*
* @package phpBB3
* @version $Id: site_update.php 3 2010-10-11 04:40:17Z tumba25 $
* @copyright (c) 2010 phpbbmodders.net
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$root_path = (defined('ROOT_PATH')) ? ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

if (!$user->data['is_registered'] || !in_array($user->data['user_id'], array(2, 53, 54, 55)))
{
	trigger_error('NOT_AUTHORISED', E_USER_ERROR);
}

// The page title
$page_title = 'Website SVN Update';

include($root_path . 'includes/lib/functions_svn.' . $phpEx);
include($root_path . 'includes/config/svn_config.' . $phpEx);

if (isset($_POST['cancel']))
{
	redirect(append_sid($phpbb_root_path . 'index.php'));
}

$submit = isset($_POST['submit']) ? true : false;

if ($submit)
{
	$svn = new svn_commands($svn_settings['local_copy_path'], $svn_settings['local_bin_path'], $svn_settings['local_config_path'], $svn_settings['svn_repository'], $svn_settings['svn_username'], $svn_settings['svn_password']);

	$svn->set_global_option('non-interactive');
	$svn->set_global_option('ignore-externals');

	$result = $svn->svn_update();

	if (is_string($result))
	{
		$output = $result;
	}
	else if (is_array($result))
	{
		// use \n here because we're using <pre>
		$output = implode("\n", array_map('htmlspecialchars', $result));
	}

	if (preg_match('#Updated to revision (\d+).#', $output, $matches))
	{
		set_config('svn_revision', (int) $matches[1]);
	}

	$cache->purge();
	$auth->acl_clear_prefetch();
	cache_moderators();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-gb" xml:lang="en-gb">
<head>

<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta http-equiv="content-style-type" content="text/css" />
<meta http-equiv="content-language" content="en-gb" />
<meta http-equiv="imagetoolbar" content="no" />
<meta name="resource-type" content="document" />
<meta name="distribution" content="global" />
<meta name="keywords" content="" />
<meta name="description" content="" />

<title>phpBBModders.net &bull; Dev site update</title>
</head>
<body>
<h1>Update dev site</h1>
<p>Current revision <?php echo $config['svn_revision']; ?></p>
<?php
	if (empty($result))
	{
?>
<form action="" method="post">
	<input type="submit" name="submit" value="Update to latest" />
	<input type="submit" name="cancel" value="Cancel" />
</form>
<?php
	}
	else
	{
?>
	<fieldset>
		<legend>Output</legend>
		<div><pre><?php echo $output; ?></pre></div>
	</fieldset>
<?php
	}
?>
</body>
</html>
