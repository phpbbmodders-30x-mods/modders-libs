<?php
/**
 * Zephyr -- Bot Posting Handler
 *
 * @package phpbbmodders_lib
 * @version $Id: class_zephyr.php 13 2009-09-17 19:58:43Z Obsidian $
 * @copyright (c) 2009 phpbbmodders
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
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
 * Handles building posts in phpBB for Bots and automated systems.
 *
 * Dependant upon the phpbb_auth_handler class in the phpbbmodders_lib package,
 * 	{@link https://github.com/phpbbmodders/modders-libs/tree/master/phpbb_auth_handler/}
 */
class zephyr
{
	/**
	 * -- Class vars
	 */

	/**
	 * Posting data
	 * @var array
	 */
	protected $post = array();

	/**
	 * Poll data...not really needed.  Just there.
	 * @var array
	 */
	protected $poll = array();

	/**
	 * Posting mode (either 'post' or 'reply')
	 * @var string
	 */
	protected $mode = 'post';

	/**
	 * User ID that we're masking as, if we are masking as another user.
	 * @var integer
	 */
	protected $user_id = 0;

	/**
	 * Name of the user that we're masking as, if we're masking as another user.
	 * @var string
	 */
	protected $username = '';

	/**
	 * What forum ID are we posting into?
	 * @var integer
	 */
	protected $forum_id = 0;

	/**
	 * What topic ID are we posting into?  (If reply mode, of course)
	 * @var integer
	 */
	protected $topic_id = 0;

	/**
	 * What time is it? Store the output of time() here.
	 * @var integer
	 */
	protected $now = 0;

	/**
	 * What is the subject of the topic/post we're making?
	 * @var string
	 */
	protected $subject = '';

	/**
	 * What is the message we're posting?
	 * @var string
	 */
	protected $message = '';

	/**
	 * What type of topic are we posting?
	 * @var integer
	 */
	protected $topic_type = POST_NORMAL;

	/**
	 * How long (in days) should the topic keep its topic type? 0 if it is permanent.
	 * @var integer
	 */
	protected $time_limit = 0;

	/**
	 * Backup object for auths and user data.
	 * @var object
	 */
	protected $auth_handler = NULL;

	/**
	 * The message parser!
	 * @var object
	 */
	protected $parser = NULL;

	/**
	 * -- Main methods used for backend management of Zephyr
	 */

	/**
	 * Zephyr Constructor method -- Loads necessary components
	 */
	public function __construct()
	{
		global $phpbb_root_path, $phpEx;
		$this->now = time();

		$this->auth_handler = new phpbb_auth_handler();

		if(!class_exists('parse_message'))
		{
			include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
		}
		$this->parser = new parse_message();
	}

	/**
	 * Garbage collect method - call this after using Zephyr to create the post.
	 *
	 * @param boolean $reset - Do we want to reset Zephyr for another round?
	 */
	public function garbage_collect($reset = false)
	{
		if($this->user_id)
		{
			$this->auth_handler->restore();
		}
		if($reset === true)
		{
			$this->reload_zephyr();
		}
	}

	/**
	 * Load the default data that submit_post wants.  ;)
	 */
	public function load_default_data()
	{
		global $user, $config;
		/**
		* Fields not set by set_default_postdata:
		*	topic_title, message, message_md5, bbcode_bitfield, bbcode_uid, forum_name, topic_id, post_id, poster_id
		*/

		// Clear out $this->post before starting.
		$this->post = array();

		/**
		 * Sourced from http://phpbbmodders.net/articles/3.0/create_post/
		 */

		$this->post = array_merge($this->post, array(

				//'post_id'				=> 0,  // ...is this supposed to be here?
			'icon_id'				=> 0,
				//'poster_id'				=> 0,
			'enable_sig'			=> ($config['allow_sig'] && $user->optionget('attachsig') && $user->data['is_registered']) ? true: false,
			'enable_bbcode'			=> ($config['allow_smilies'] && $user->optionget('smilies')) ? true : false,
			'enable_smilies'		=> ($config['allow_bbcode'] && $user->optionget('bbcode')) ? true : false,
			'enable_urls'			=> true,
			'post_time'				=> $this->now,
			'post_checksum'			=> '',
			'notify'				=> false,
			'notify_set'			=> 0,
			'poster_id'				=> $user->data['user_id'],
			'poster_ip'				=> $user->ip,
			'post_edit_locked'		=> 0,
			'post_approved'			=> true,
			'topic_status'			=> 0,
				//'bbcode_bitfield'		=> $bitfield,
				//'bbcode_uid'			=> $uid,
				//'message'				=> $post,
			'attachment_data'		=> 0,
			'filename_data'			=> 0,
		));

		// @todo: Check to see what all vars we actually need here. o_O
		if($this->mode == 'post')
		{
			$this->post = array_merge($this->post, array(
				'topic_title'			=> '',
				'topic_first_post_id'	=> 0,
				'topic_last_post_id'	=> 0,
				'topic_time_limit'		=> 0,
				'topic_attachment'		=> 0,
				'topic_approved'		=> true,
			));
		}
		elseif($this->mode == 'reply')
		{
			$this->post = array_merge($this->post, array(
				'topic_id'				=> 0,
			));
		}
	}

	/**
	 * -- Private class methods used for internal management
	 */

	/**
	 * Reload method - resets Zephyr for another run
	 */
	private function reload_zephyr()
	{
		// Clear out previously set data.

		$this->post = array();
		$this->poll = array();
		$this->post_mode = 'post';
		$this->user_id = 0;
		$this->username = '';
		$this->fora_id = 0;
		$this->now = 0;
		$this->subject = '';
		$this->message = '';
		$this->topic_type = POST_NORMAL;
		$this->time_limit = 0;
		$this->parser = NULL;

		// Now, reload the parser.
		$this->parser = new parse_message();
	}

	/**
	 * -- Publicly accessible methods for Zephyr
	 */

	/**
	 * Allows us to mask as another user with Zephyr
	 *
	 * @param integer $user_id - ID of user to mask as.
	 *
	 * @return boolean - False on masking failure, true on success.
	 */
	public function mask_as_user($user_id)
	{
		global $user;

		if($user_id === ANONYMOUS)
		{
			return false;
		}

		$this->user_id = (int) $user_id;

		if($this->auth_handler->load($this->user_id) === false)
		{
			return false;
		}

		$user->data['is_registered'] = true;
		$user->ip = '0.0.0.0';
		$this->username = $user->data['username'];
		$this->post['poster_id'] = $this->user_id;
		$this->post['poster_ip'] = $user->ip;

		return true;
	}

	/**
	 * Sets the forum that we want to be posting to.
	 *
	 * @param integer $forum_id - The forum ID we want to post to.
	 *
	 * @param boolean - False on failure, true on success.
	 */
	public function set_target_forum($forum_id)
	{
		global $db;

		$this->forum_id = (int) $forum_id;

		$sql = 'SELECT forum_name, enable_indexing, forum_parents
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . (int) $db->sql_escape($this->forum_id);
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if(!$row)
		{
			return false;
		}

		$this->post['forum_name'] = $row['forum_name'];
		$this->post['enable_indexing'] = $row['enable_indexing'];
		$this->post['forum_id'] = $this->forum_id;

		return true;
	}

	/**
	 * Sets the topic that we want to be posting to.
	 *
	 * @param integer $topic_id - The topic ID we want to post to.
	 *
	 * @return boolean - False on failure, true on success.
	 */
	public function set_target_topic($topic_id)
	{
		if($this->mode == 'post')
		{
			return false;  // FAIL!
		}
		$this->topic_id = (int) $topic_id;

		$this->post['topic_id'] = $this->topic_id;
		return true;
	}

	/**
	 * Set the topic time and timeout for the topic type (if desired)
	 *
	 * @param integer $topic_type - Topic type that is desired.
	 * @param integer $topic_time_limit - How long (in days) the topic will keep its topic type. (0 if it is permanent.)
	 *
	 * @return boolean - False is returned on failure to set topic type (unallowed topic type), true on success
	 */
	public function set_topic_type($topic_type = POST_NORMAL, $topic_time_limit = 0)
	{
		if($topic_type === POST_NORMAL || $topic_type === POST_STICKY || $topic_type === POST_ANNOUNCE)
		{
			$this->time_limit = (int) $topic_time_limit;
			$this->topic_type = $topic_type;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets whether the topic being created/posted to should be locked or not.
	 *
	 * @param integer $topic_status - What topic status do we want set?  Locked/unlocked?
	 *
	 * @return boolean - True on success, false on failure.
	 */
	public function set_topic_lock($topic_lock)
	{
		if($topic_lock === ITEM_UNLOCKED || $topic_lock === ITEM_LOCKED)
		{
			global $db;
			$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET topic_status = ' . (int) $db->sql_escape($topic_lock) . '
					WHERE topic_id = ' . (int) $db->sql_escape($this->topic_id) . '
						AND topic_moved_id = 0';
			$db->sql_query($sql);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Sets the posting mode, for use in customised runs of Zephyr.  ;)
	 *
	 * @param string $mode - The mode to use.
	 *
	 * @return boolean - True on success, false on failure.
	 */
	public function set_mode($mode)
	{
		if($mode != 'reply' && $mode != 'post')
		{
			return false;
		}
		else
		{
			$this->mode = $mode;
			return true;
		}
	}

	/**
	 * Sets the subject to post with.  Expects that the subject has been run through utf8_normalize_nfc() however.
	 *
	 * @param string $subject - What the subject is that we want to set.
	 *
	 * @param boolean - True if success, false if failure.
	 */
	public function set_subject($subject)
	{
		if($this->mode == 'post' && utf8_clean_string($subject) === '')
		{
			return false;
		}
		elseif($this->mode == 'reply' && utf8_clean_string($subject) !== '')
		{
			$subject = ((strpos($subject, 'Re: ') !== 0) ? 'Re: ' : '') . censor_text($subject);
		}

		$this->subject = $subject;
		$this->post['topic_title'] = $subject;

		return true;
	}

	/**
	 * Parses a message and sets it to be used for posting.
	 *
	 * @param string $message - The message to post.
	 *
	 * @return mixed - Errors, if any are encountered by the parser.
	 */
	public function set_message($message)
	{
		global $config;
		$this->parser->message = &$message;
		unset($message);

		if (sizeof($this->parser->warn_msg))
		{
			$error[] = implode('<br />', $this->parser->warn_msg);
			$this->parser->warn_msg = array();
		}

		$md5 = md5($this->parser->message);
		$this->parser->parse($this->post['enable_bbcode'], ($config['allow_post_links']) ? $this->post['enable_urls'] : false, $this->post['enable_smilies'], $this->post['enable_bbcode'], $this->post['enable_bbcode'], $this->post['enable_bbcode'], $config['allow_post_links']);
		$this->poll = array();

		$this->post['bbcode_bitfield'] = $this->parser->bbcode_bitfield;
		$this->post['bbcode_uid'] = $this->parser->bbcode_uid;
		$this->post['message_md5'] = $md5;
		$this->post['message'] = $this->parser->message;

		$this->message = $this->parser->message;

		if(@sizeof($error))
		{
			return $error;
		}
	}

	/**
	 * The actual method that submits the post.  :D
	 *
	 * @return integer - The topic ID returned.
	 */
	public function post()
	{
		if(!function_exists('submit_post'))
		{
			global $phpbb_root_path, $phpEx;
			include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
			//include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
		}

		submit_post($this->mode, $this->subject, $this->username, $this->time_limit, $this->poll, $this->post);

		return $this->post['topic_id'];
	}

	/**
	 * Quick topic generation method
	 *
	 * This method quickly generates a topic with the provided params, for ease of use.
	 *
	 * @param string $message - The message, in raw unparsed form, not even through utf8_normalize_nfc()
	 * @param string $subject - The subject, in raw unparsed form, not even through utf8_normalize_nfc()
	 * @param integer $forum_id - The forum ID we want to post to.
	 * @param integer $user_id - The ID of the user that we want to post as.  False if we don't want to mask as another user.
	 *
	 * @return mixed - False returned on failure, topic's ID on success.  O_o
	 */
	public function quick_topic($message, $subject, $forum_id, $user_id = false)
	{
		$this->load_default_data();
		if($user_id)
		{
			if(!$this->mask_as_user($user_id))
			{
				return false;
			}
		}
		if(!$this->set_target_forum($forum_id))
		{
			return false;
		}
		$this->set_subject(utf8_normalize_nfc($subject));
		$this->set_message(utf8_normalize_nfc($message));
		$return = $this->post();
		$this->garbage_collect(false);

		return $return;
	}

	/**
	 * Quick post generation method
	 *
	 * This method quickly generates a reply to an existing topic with the provided params, for ease of use.
	 *
	 * @param string $message - The message, in raw unparsed form, not even through utf8_normalize_nfc()
	 * @param string $subject - The subject, in raw unparsed form, not even through utf8_normalize_nfc()
	 * @param integer $forum_id - The forum ID we want to post to.
	 * @param integer $topic_id - The topic ID we want to post to.
	 * @param integer $user_id - The ID of the user that we want to post as.  False if we don't want to mask as another user.
	 *
	 * @return boolean - False returned on failure, topic's ID on success.  O_o
	 */
	public function quick_reply($message, $subject, $forum_id, $topic_id, $user_id = false)
	{
		$this->mode = 'reply';
		$this->load_default_data();
		if($user_id)
		{
			if(!$this->mask_as_user($user_id))
			{
				return false;
			}
		}
		if(!$this->set_target_forum($forum_id))
		{
			return false;
		}
		$this->set_target_topic($topic_id);
		$this->set_subject(utf8_normalize_nfc($subject));
		$this->set_message(utf8_normalize_nfc($message));
		$return = $this->post();
		$this->garbage_collect(false);

		return $return;
	}
}
?>