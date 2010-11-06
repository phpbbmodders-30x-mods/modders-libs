<?php
/**
*
* @package phpbbmodders_site
* @version $Id: svn_config.php 3 2010-10-11 04:40:17Z tumba25 $
* @copyright (c) 2007, 2008 phpbbmodders
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// svn_settings
$svn_settings = array(
	'local_copy_path'	=> '', // path to the directory that is being updated
	'local_bin_path'	=> '', // Usually /usr/local/bin/, mostly a empty string works fine.
	'local_config_path'	=> '', // /home/user/.subversion on linux, who knows where they might be on Win systems.
	'svn_repository'	=> '', // Address and path to your repository.

	'svn_username'		=> '', // Not needed for public repos.
	'svn_password'		=> '', // Not needed for public repos.
);
