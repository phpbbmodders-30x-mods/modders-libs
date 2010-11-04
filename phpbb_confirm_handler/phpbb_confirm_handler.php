<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: phpbb_confirm_handler.php 1 2008-05-06 22:58:49Z evil3 $
* @copyright (c) 2007, 2008 phpbbmodders
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Create confirm images and check confirm codes
*/
class phpbb_confirm_handler
{
	protected $confirm_type = 0;

	public function __construct($confirm_type)
	{
		$this->confirm_type = (int) $confirm_type;
	}

	public function check($confirm_id, $confirm_code)
	{
		global $db, $user;

		if ($confirm_id)
		{
			$sql = 'SELECT code
				FROM ' . CONFIRM_TABLE . "
				WHERE confirm_id = '" . $db->sql_escape($confirm_id) . "'
					AND session_id = '" . $db->sql_escape($user->session_id) . "'
					AND confirm_type = {$this->confirm_type}";
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if ($row)
			{
				if (strcasecmp($row['code'], $confirm_code) === 0)
				{
					$sql = 'DELETE FROM ' . CONFIRM_TABLE . "
						WHERE confirm_id = '" . $db->sql_escape($confirm_id) . "'
							AND session_id = '" . $db->sql_escape($user->session_id) . "'
							AND confirm_type = {$this->confirm_type}";
					$db->sql_query($sql);

					return true;
				}
			}
		}

		return false;
	}

	public function confirm_image($max_attempts, &$confirm_id)
	{
		global $db, $user, $template;
		global $phpbb_root_path, $phpEx;

		$user->confirm_gc($this->confirm_type);

		if ($max_attempts)
		{
			$sql = 'SELECT COUNT(session_id) AS attempts
				FROM ' . CONFIRM_TABLE . "
				WHERE session_id = '" . $db->sql_escape($user->session_id) . "'
					AND confirm_type = {$this->confirm_type}";
			$result = $db->sql_query($sql);
			$attempts = (int) $db->sql_fetchfield('attempts');
			$db->sql_freeresult($result);

			if ($attempts > $max_attempts)
			{
				return false;
			}
		}

		$code = gen_rand_string(mt_rand(5, 8));
		$confirm_id = md5(unique_id($user->ip));
		$seed = hexdec(substr(unique_id(), 4, 10));

		// compute $seed % 0x7fffffff
		$seed -= 0x7fffffff * floor($seed / 0x7fffffff);

		$sql = 'INSERT INTO ' . CONFIRM_TABLE . ' ' . $db->sql_build_array('INSERT', array(
			'confirm_id'	=> (string) $confirm_id,
			'session_id'	=> (string) $user->session_id,
			'confirm_type'	=> (int) $this->confirm_type,
			'code'			=> (string) $code,
			'seed'			=> (int) $seed,
		));
		$db->sql_query($sql);

		$template->assign_var('S_CONFIRM_CODE', true);

		return '<img src="' . append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=confirm&amp;id=' . $confirm_id . '&amp;type=' . $this->confirm_type) . '" alt="" title="" />';
	}
}

?>