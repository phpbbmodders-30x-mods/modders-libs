<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: functions_user_merge.php 17 2009-10-11 04:46:04Z Obsidian $
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
 * Merge two user accounts into one
 *
 * @author eviL3
 * @param int $old_user User id of the old user
 * @param int $new_user User id of the new user
 *
 * @return void
 */
function user_merge($old_user, $new_user)
{
	global $user, $db;

	if (!function_exists('user_add'))
	{
		global $phpbb_root_path, $phpEx;
		include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
	}

	$old_user = (int) $old_user;
	$new_user = (int) $new_user;
	
	// Update postcount
	$total_posts = 0;
	
	// Add up the total number of posts for both...
	$sql = 'SELECT user_posts
		FROM ' . USERS_TABLE . '
		WHERE ' . $db->sql_in_set('user_id', array($old_user, $new_user));
	$result = $db->sql_query($sql);
	while($return = $db->sql_fetchrow($result))
	{
		$total_posts = $total_posts + (int) $return['user_posts'];
	}
	$db->sql_freeresult($result);
	
	// Now set the new user to have the total amount of posts.  ;)
	$db->sql_query('UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', array(
		'user_posts' => $total_posts,
	)) . ' WHERE user_id = ' . $new_user);

	// Get both users userdata
	$data = array();
	foreach (array($old_user, $new_user) as $key)
	{
		$sql = 'SELECT user_id, username, user_colour
			FROM ' . USERS_TABLE . '
				WHERE user_id = ' . $key;
		$result = $db->sql_query($sql);
		$data[$key] = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
	}

	$update_ary = array(
		ATTACHMENTS_TABLE		=> array('poster_id'),
		FORUMS_TABLE			=> array(array('forum_last_poster_id', 'forum_last_poster_name', 'forum_last_poster_colour')),
		LOG_TABLE				=> array('user_id', 'reportee_id'),
		MODERATOR_CACHE_TABLE	=> array(array('user_id', 'username')),
		POSTS_TABLE				=> array(array('poster_id', 'post_username'), 'post_edit_user'),
		POLL_VOTES_TABLE		=> array('vote_user_id'),
		PRIVMSGS_TABLE			=> array('author_id', 'message_edit_user'),
		PRIVMSGS_TO_TABLE		=> array('user_id', 'author_id'),
		REPORTS_TABLE			=> array('user_id'),
		TOPICS_TABLE			=> array(array('topic_poster', 'topic_first_poster_name', 'topic_first_poster_colour'), array('topic_last_poster_id', 'topic_last_poster_name', 'topic_last_poster_colour')),
	);

	foreach ($update_ary as $table => $field_ary)
	{
		foreach ($field_ary as $field)
		{
			$sql_ary = array();

			if (!is_array($field))
			{
				$field = array($field);
			}

			$sql_ary[$field[0]] = $new_user;

			if (!empty($field[1]))
			{
				$sql_ary[$field[1]] = $data[$new_user]['username'];
			}

			if (!empty($field[2]))
			{
				$sql_ary[$field[2]] = $data[$new_user]['user_colour'];
			}

			$primary_field = $field[0];

			$sql = "UPDATE $table SET " . $db->sql_build_array('UPDATE', $sql_ary) . "
				WHERE $primary_field = $old_user";
			$db->sql_query($sql);
		}
	}

	user_delete('remove', $old_user);
}

?>