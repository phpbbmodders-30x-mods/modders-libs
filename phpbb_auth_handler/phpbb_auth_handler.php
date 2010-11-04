<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: phpbb_auth_handler.php 8 2008-07-29 22:17:40Z evil3 $
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
* Handle user auth (and userdata) changes (for bots)
* Backs up userdata and restores it later
*/
class phpbb_auth_handler
{
	protected $user_backup, $auth_backup;
	protected $user_id = 0;

	/**
	 * load a user
	 *
	 * @param int $user_id
	 * @return boolean false on fail
	 */
	public function load($user_id)
	{
		global $user, $db, $auth;

		if ($this->user_id)
		{
			$this->restore();
		}

		$this->user_id = (int) $user_id;

		$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $this->user_id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if (!$row)
		{
			//trigger_error('NO_USER', E_USER_ERROR);
			return false;
		}

		$this->user_backup = clone $user;
		$this->auth_backup = clone $auth;

		$user->data = array_merge($user->data, $row);
		$auth->acl($user->data);

		unset($row);
	}

	/**
	 * restore the user
	 */
	public function restore()
	{
		global $user, $auth;

		$user = clone $this->user_backup;
		$auth = clone $this->auth_backup;

		$this->user_backup = $this->auth_backup = NULL;

		$this->user_id = 0;
	}

	/**
	 * get the user id
	 *
	 * @return int user_id
	 */
	public function get_user_id()
	{
		return $this->user_id;
	}
}

?>