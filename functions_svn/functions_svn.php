<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: functions_svn.php 3 2010-10-11 04:40:17Z tumba25 $
* @copyright (c) 2008 phpbbmodders
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
 * command-line svn class (for svn 1.5)
 * thanks to highway of life for the original code
 * thanks to this svn documentation: http://svnbook.red-bean.com/nightly/en/index.html
 */
class svn_base
{
	// The name of the svn executable including path if needed.
	protected $svn_bin = 'svn';

	// our paths
	protected $local_copy_path;
	protected $local_bin_path;
	protected $local_config_path;
	protected $svn_repository;

	// svn login details
	protected $username;
	protected $password;

	// global svn options, used for things like ignore-externals
	protected $global_options = array();

	// contains the version of subversion binaries
	public $svn_version;

	// svn properties
	public $properties = array(
		// reserved properties
		'date'			=> 'svn:date',
		'orig_date'		=> 'svn:original-date',
		'author'		=> 'svn:author',
		'log'			=> 'svn:log',

		// other properties
		'executable'	=> 'svn:executable',
		'mime_type'		=> 'svn:mime-type',
		'ignore'		=> 'svn:ignore',
		'keywords'		=> 'svn:keywords',	// Id, Date, Rev, Author, URL
		'eol_style'		=> 'svn:eol-style',	// native, CRLF, LF, CR
		'externals'		=> 'svn:externals',
	);

	/**
	 * constructor
	 * build it all, paths should end with /
	 *
	 * @param string $local_copy_path path to the local svn copy
	 * @param string $local_bin_path path to the svn binaries dir
	 * @param string $local_config_path path to the svn configuration
	 * @param string $svn_repository path to the svn repo
	 * @param string $svn_username username for svn server
	 * @param string $svn_password password for svn server
	 * @param string $svn_bin binary file for SVN
	 */
	public function __construct($local_copy_path, $local_bin_path, $local_config_path, $svn_repository, $svn_username, $svn_password, $svn_bin = false)
	{
		$this->local_copy_path		= $this->append_slash((string) $local_copy_path);
		$this->local_bin_path		= $this->append_slash((string) $local_bin_path);
		$this->local_config_path	= $this->append_slash((string) $local_config_path);
		$this->svn_repository		= $this->append_slash((string) $svn_repository);

		$this->username	= (string) $svn_username;
		$this->password	= (string) $svn_password;

		if ($svn_bin)
		{
			$this->svn_bin = (string) $svn_bin;
		}

		$this->svn_version = implode("\n", $this->exec(false, false, array('version' => null, 'quiet' => null)));
	}

	/**
	 * append a slash to a path, if there isn't one already
	 *
	 * @param string $path
	 * @return string slash appended path
	 */
	protected function append_slash($path)
	{
		return (strlen($path) && substr($path, -1) !== '/') ? $path . '/' : $path;
	}

	/**
	 * escape a shell arguemnt, wrapper for escapeshellarg()
	 *
	 * @param mixed $arg
	 * @return string escaped arg
	 */
	protected function escape_arg($arg)
	{
		return escapeshellarg(addslashes($arg));
	}

	/**
	 * execute an svn command, this is the main function
	 *
	 * @param string $_svn_command
	 * @param mixed $_svn_arg svn argument(s), can either be bool(false), string or array
	 * @param array $_svn_options svn options
	 * @param boolean $inc_user_pass when set to true, user and pass are included as args, only needed for remote actions
	 * @return array result
	 */
	protected function exec($_svn_command = false, $_svn_arg = false, $_svn_options = array(), $inc_user_pass = false)
	{
		// add some svn options
		if ($this->local_config_path)
		{
			$_svn_options['config-dir'] = $this->local_config_path;
		}

		if ($inc_user_pass)
		{
			if ($this->username)
			{
				$_svn_options['username'] = $this->username;
			}

			if ($this->password)
			{
				$_svn_options['password'] = $this->password;
			}
		}

		// add the global options
		$_svn_options = array_merge($_svn_options, $this->global_options);

		// build the svn command
		$svn_command = $this->build_command($_svn_command, $_svn_arg, $_svn_options);

		// exec and return
		$result = array();
		exec($svn_command, $result);
		return $result;
	}

	/**
	 * build the main svn command
	 *
	 * @param string $command the svn command
	 * @param mixed $argument the svn argument(s)
	 * @param array $options the svn options
	 * @return string svn shell command
	 */
	protected function build_command($command, $argument, $options)
	{
		if ($argument)
		{
			if (!is_array($argument))
			{
				$argument = array($argument);
			}
			$argument = array_diff($argument, array('')); // remove empty values
			$argument = implode(' ', array_map(array($this, 'escape_arg'), $argument));
		}

		$svn_command = $this->local_bin_path . $this->svn_bin . ($command ? ' ' . $this->escape_arg($command) : '') . ($argument ? ' ' . $argument : '');
		foreach ($options as $key => $option)
		{
			// nothing if $option is null
			// implode if $option is an array
			$svn_command .= ' --' . $key . ($option !== null ? ' ' . (!is_array($option) ? $this->escape_arg($option) : implode(' ', array_map(array($this, 'escape_arg'), $option))) : '');
		}
		$svn_command .= ' 2>&1'; // this is needed

		return $svn_command;
	}

	/**
	 * build the revision
	 *
	 * @param mixed $data
	 * @param string $mode one of these: nun, date, head, base, committed, prev
	 * @return mixed build revision
	 */
	public function build_revision($data, $mode = 'num')
	{
		if (is_array($data))
		{
			if (sizeof($data) < 2)
			{
				$data = $data[0];
			}
			else
			{
				// use array values so it doesn't mess up with list()
				list($data, $mode) = array_values($data);
			}
		}

		switch ($mode)
		{
			case 'num':
				// revision id
				return (int) $data;
				break;
			case 'date':
				// unix timestamp, convert it to date
				// reference: http://svnbook.red-bean.com/nightly/en/svn.tour.revs.specifiers.html#svn.tour.revs.dates
				return '{"' . gmdate('Y-m-d H:i', (int) $data) . '"}';
				break;
			case 'head':
			case 'base':
			case 'committed':
			case 'prev':
				/**
				 * special modes
				 *
				 * head:		latest in repository
				 * base:		base rev of item's working copy
				 * committed:	last commit at or before BASE
				 * prev:		revision just before COMMITTED
				 */
				return strtoupper($mode);
				break;
			default:
				if (in_array($data, array('head', 'base', 'committed', 'prev'), true))
				{
					// see if head, base, committed or prev is supplied as data
					return strtoupper($data);
				}
				else
				{
					// no valid mode given -- default to num
					// slight recursion :P
					return $this->build_revision($data, 'num');
				}
				break;
		}
	}

	/**
	 * used to destinguish between local/remote
	 *
	 * @param boolean $on_server do we want to access the svn server
	 * @return string svn root path
	 */
	protected function on_server($on_server)
	{
		return ($on_server ? $this->svn_repository : $this->local_copy_path);
	}

	/**
	 * Set a global option
	 *
	 * @param string $option
	 * @param mixed $value
	 */
	public function set_global_option($option, $value = null)
	{
		$this->global_options[$option] = $value;
	}

	/**
	 * Unset global option
	 *
	 * @param string $option
	 */
	public function unset_global_option($option)
	{
		if (array_key_exists($option, $this->global_options))
		{
			unset($this->global_options[$option]);
		}
	}

	/**
	 * convert unix timestamps to the format used by SVN or vice versa
	 * format taken from svn's libsvn_subr/time.c
	 *
	 * @todo find out what happened to those six numbers and the T
	 *
	 * @param int|string $time_input
	 * @param boolean $svn_to_unix
	 * @return int|string converted timestamp
	 */
	public static function convert_time($time_input, $svn_to_unix = true)
	{
		if ($svn_to_unix)
		{
			// the regex is supposed to be: '#(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2}).{\d{6}}Z#'
			if (preg_match('#(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})Z#', $time_input, $matches))
			{
				return mktime($matches[5], $matches[6], $matches[7], $matches[2], $matches[3], $matches[1]);
			}

			return false;
		}
		else
		{
			// can't use getdate() because it uses the timezone settings
			// the format is supposed to be: '%04d-%02d-%02dT%02d:%02d:%02d.%06dZ'
			list($year, $month, $day, $hours, $minutes, $seconds) = array_map('intval', explode(' ', gmdate('Y n j G i s', $time_input)));
			return sprintf('%04d-%02d-%02d %02d:%02d:%02dZ', $year, $month, $day, $hours, $minutes, $seconds);
		}
	}
}

/**
 * the svn commands for svn_base
 *
 * @todo svn_merge
 * @todo svn_mergeinfo
 */
class svn_commands extends svn_base
{
	/**
	 * svn add
	 * add a file to the repository
	 *
	 * @param string $path path of the file to add, relative to svn root
	 * @param mixed $depth set to 0 if you don't want recursion
	 * @return array result
	 */
	public function svn_add($path, $depth = false)
	{
		$options = array();

		if ($depth !== false)
		{
			$options['depth'] = $depth;
		}

		return $this->exec('add', $this->local_copy_path . $path, $options);
	}

	/**
	 * svn blame
	 * get revision number, author and changes from a file
	 *
	 * @param string $path path of file, relative to svn root -- must be a file
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_blame($path, $revision = false, $on_server = false, $verbose = false, $xml = false, $incremental = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($verbose)
		{
			$options['verbose'] = null;
		}

		if ($xml)
		{
			if ($incremental)
			{
				$options['incremental'] = null;
			}

			$options['xml'] = null;
		}

		return $this->exec('blame', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn cat
	 * get contents of a file
	 *
	 * @param string $path path of file, relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_cat($path, $revision = false, $on_server = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		return $this->exec('cat', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn changelist
	 * associate (or deassociate) local paths with a changelist
	 *
	 * @param mixed $path path to file, relative to svn root - can also be an array of paths
	 * @param mixed $changelist name of the changelist
	 * @param boolean $remove remove from the changelist
	 * @return array result
	 */
	public function svn_changelist($path = '', $changelist = false, $remove = false)
	{
		$options = array();

		if ($remove)
		{
			$options['remove'] = null;
		}

		if (!is_array($path))
		{
			$path = array($path);
		}

		foreach ($path as $key => $value)
		{
			$path[$key] = $this->local_copy_path . $value;
		}

		if ($changelist !== false)
		{
			array_unshift($path, $changelist);
		}

		return $this->exec('changelist', $path, $options);
	}

	/**
	 * svn checkout
	 * checkout to local
	 *
	 * @param string $path path to where we want to checkout, relative to svn root
	 * @param mixed $revision
	 * @return array result
	 */
	public function svn_checkout($path = '', $revision = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		return $this->exec('co', array($this->svn_repository . $path, $this->local_copy_path . $path), $options, true);
	}

	/**
	 * svn cleanup
	 * recursively clean up local
	 *
	 * @param string $path path to clean up, relative to svn root
	 * @return array result
	 */
	public function svn_cleanup($path = '')
	{
		return $this->exec('cleanup', $this->local_copy_path . $path);
	}

	/**
	 * svn commit
	 * checkin changes to server
	 *
	 * @param string $path
	 * @param mixed $message
	 * @param mixed $encoding
	 * @param mixed $depth
	 * @return array result
	 */
	public function svn_commit($path = '', $message = false, $encoding = false, $depth = false, $changelist = false)
	{
		$options = array();

		if ($message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		if ($depth !== false)
		{
			$options['depth'] = $depth;
		}

		if ($changelist !== false)
		{
			$options['changelist'] = $changelist;
		}

		return $this->exec('ci', array($this->svn_repository . $path, $this->local_copy_path . $path), $options, true);
	}

	/**
	 * svn copy
	 * copy a file
	 *
	 * @param string $path_old path of source file, relative to svn root
	 * @param string $path_new path of destination file, relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server_old
	 * @param boolean $on_server_new
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_copy($path_old, $path_new, $revision = false, $on_server_old = false, $on_server_new = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($on_server_new && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('copy', array($this->on_server($on_server_old) . $path_old, $this->on_server($on_server_new) . $path_new), $options, $on_server_old || $on_server_new);
	}

	/**
	 * svn delete
	 * delete a file from the repository
	 *
	 * @param string $path path of file to delete, relative to svn root
	 * @param boolean $on_server
	 * @param mixed $log_message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_delete($path, $on_server = false, $log_message = false, $encoding = false)
	{
		$options = array();

		if ($on_server && $log_message)
		{
			$options['message'] = $log_message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('delete', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn diff
	 * find differences between files
	 * compare local to local:		svn_diff($path, $rev_old[, $rev_new])
	 * compare server to server:	svn_diff($path, $rev_old, $rev_new, true, true)
	 * compare local to server:		svn_diff($path, $rev_old, $rev_new, false, true)
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $rev_old
	 * @param mixed $rev_new
	 * @param boolean $on_server_old
	 * @param boolean $on_server_new
	 * @param boolean $xml
	 * @return array result
	 */
	public function svn_diff($path = '', $rev_old = false, $rev_new = false, $on_server_old = false, $on_server_new = false, $xml = false)
	{
		$arguments = $options = array();
		$on_server_custom = false;

		if ($on_server_new !== $on_server_old)
		{
			// comparing local to remote or vice versa
			$arguments = array($this->local_copy_path . $path . ($rev_old ? "@{$this->build_revision($rev_old)}" : ''), $this->svn_repository . $path . ($rev_new ? "@{$this->build_revision($rev_new)}" : ''));
		}
		else
		{
			// comparing local to local or remote to remote
			if ($rev_old && $rev_new)
			{
				// compare $rev_old against $rev_new
				$arguments = array($this->on_server($on_server_old) . $path);
				$options['revision'] = "{$this->build_revision($rev_old)}:{$this->build_revision($rev_new)}";
			}
			else if ($rev_old)
			{
				// compare $rev_old to working copy
				$arguments = array($this->on_server($on_server_old) . $path);
				$options['revision'] = $this->build_revision($rev_old);
			}
			else
			{
				// compare latest to working copy, for that we need username & password
				$arguments = array($this->on_server($on_server_old) . $path);
				$on_server_custom = true;
			}
		}

		// pass it to diff
		//$options['diff-cmd'] = $this->local_bin_path . 'diff';

		// xml support only in svn 1.5.0+
		if ($xml && version_compare($this->svn_version, '1.5.0', '>='))
		{
			$options['xml'] = null;
			$options['summarize'] = null;
		}

		return $this->exec('diff', $arguments, $options, $on_server_old || $on_server_new || $on_server_custom);
	}

	/**
	 * svn export
	 * export the repository to local
	 *
	 * @param string $path path relative to local_site_dir
	 * @param string $export_to path we want to export to, not relative to anything
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_export($path = '', $export_to = '', $revision = false, $on_server = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		return $this->exec('export', array($this->on_server($on_server) . $path, $export_to), $options, $on_server);
	}

	/**
	 * svn help
	 *
	 * @param string $argument
	 * @return array result
	 */
	public function svn_help($argument = '', $version = false, $quiet = false)
	{
		$options = array();

		if ($version)
		{
			$options['version'] = null;
		}

		if ($quiet)
		{
			$options['quiet'] = null;
		}

		return $this->exec('help', $argument, $options);
	}

	/**
	 * svn import
	 * import to a repo from local
	 *
	 * @param string $path path relative to svn root
	 * @param string $import_from path not relative to anything
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_import($path = '', $import_from = '', $message = false, $encoding = false)
	{
		$options = array();

		if ($message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('import', array($import_from, $this->svn_repository . $path), $options, true);
	}

	/**
	 * svn info
	 * get svn info from a path
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param boolean $xml
	 * @return array result
	 */
	public function svn_info($path = '', $revision = false, $on_server = false, $xml = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($xml)
		{
			$options['xml'] = null;
		}

		return $this->exec('info', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn list
	 * the same as ls
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param boolean $verbose returns more details if set to true
	 * @param boolean $xml
	 * @return array result
	 */
	public function svn_list($path = '', $revision = false, $on_server = false, $verbose = false, $xml = false)
	{
		$options = array();

		if ($verbose)
		{
			$options['verbose'] = null;
		}

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($xml)
		{
			$options['xml'] = null;
		}

		return $this->exec('list', $this->on_server($on_server) . $path, $options, true);
	}

	/**
	 * svn lock
	 * lock a path
	 *
	 * @param string $path path relative to svn root
	 * @param boolean $on_server
	 * @param boolean $force force locking - overwrite existing locks
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_lock($path = '', $on_server = false, $force = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		if ($force)
		{
			$options['force'] = null;
		}

		return $this->exec('lock', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn log
	 * get svn log message
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param boolean $verbose returns more info when true
	 * @param boolean $xml
	 * @return array result
	 */
	public function svn_log($path = '', $revision = false, $on_server = false, $verbose = false, $incremental = false, $limit = false, $with_all_revprops = false, $xml = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($verbose)
		{
			$options['verbose'] = null;
		}

		if ($incremental)
		{
			$options['incremental'] = null;
		}

		if ($limit)
		{
			$options['limit'] = (int) $limit;
		}

		if ($with_all_revprops)
		{
			$options['with-all-revprops'] = null;
		}

		if ($xml)
		{
			$options['xml'] = null;
		}

		return $this->exec('log', $this->on_server($on_server) . $path, $options, true);
	}

	public function svn_merge()
	{
		/**
		 * @todo code
		 */
	}

	/**
	 * svn mkdir
	 * create a new dir in svn repo
	 *
	 * @param string $path path relative to svn root
	 * @param boolean $on_server
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_mkdir($path = '', $on_server = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('mkdir', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn move
	 * move path within repo
	 *
	 * @param string $path path relative to svn root
	 * @param string $move_to move to path, relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_move($path = '', $move_to = '', $revision = false, $on_server = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('move', array($this->on_server($on_server) . $path, $this->on_server($on_server) . $move_to), $options, $on_server);
	}

	/**
	 * svn propdel
	 * delete a property
	 *
	 * @param string $prop_name property name
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_propdel($prop_name, $path = '', $revision = false, $on_server = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		return $this->exec('propdel', array($prop_name, $this->on_server($on_server) . $path), $options, $on_server);
	}

	/**
	 * svn propedit
	 * edit a property
	 *
	 * @param string $prop_name property name
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_propedit($prop_name, $path = '', $revision = false, $on_server = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('propedit', array($prop_name, $this->on_server($on_server) . $path), $options, $on_server);
	}

	/**
	 * svn propget
	 * get a property
	 *
	 * @param string $prop_name property name
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_propget($prop_name, $path = '', $revision = false, $on_server = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('propget', array($prop_name, $this->on_server($on_server) . $path), $options, $on_server);
	}

	/**
	 * svn proplist
	 * list properties of a path
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param boolean $verbose when set to true the func returns more info
	 * @return array result
	 */
	public function svn_proplist($path = '', $revision = false, $on_server = false, $verbose = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($verbose)
		{
			$options['verbose'] = null;
		}

		return $this->exec('proplist', $this->on_server($on_server) . $path, $options, $on_server);
	}

	/**
	 * svn propset
	 * set a property
	 *
	 * @param string $prop_name property name
	 * @param string $prop_value property value
	 * @param string $path path relative to local_site_root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param mixed $message
	 * @param mixed $encoding
	 * @return array result
	 */
	public function svn_propset($prop_name, $prop_value, $path = '', $revision = false, $on_server = false, $message = false, $encoding = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($on_server && $message)
		{
			$options['message'] = $message;
			if ($encoding)
			{
				$options['encoding'] = $encoding;
			}
		}

		return $this->exec('propset', array($prop_name, $prop_value, $this->on_server($on_server) . $path), $options, $on_server);
	}

	/**
	 * svn propset log
	 * edit a log message
	 *
	 * @param mixed $revision
	 * @param mixed $message
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_propset_log($revision, $message, $on_server = false)
	{
		return $this->exec('propset', array($this->properties['log'], $message, $this->on_server($on_server)), array('revprop' => null, 'revision' => $this->build_revision($revision)), $on_server);
	}

	/**
	 * svn resolved
	 * remove "conflicted" state on path
	 *
	 * @param string $path path relative to svn root
	 * @return array result
	 */
	public function svn_resolved($path = '')
	{
		return $this->exec('resolved', $this->local_copy_path . $path);
	}

	/**
	 * svn revert
	 * revert local changes to current rev
	 *
	 * @param string $path path relative to svn root
	 * @return array result
	 */
	public function svn_revert($path = '')
	{
		return $this->exec('revert', $this->local_copy_path . $path);
	}

	/**
	 * svn status
	 * info about local files
	 *
	 * @param string $path path relative to svn root
	 * @param boolean $show_updates shows outdated files
	 * @param boolean $no_ignore includes svn:ignore files
	 * @param boolean $verbose when set to true func returns more info
	 * @param boolean $xml
	 * @return array result
	 */
	public function svn_status($path = '', $show_updates = false, $no_ignore = false, $verbose = false, $xml = false)
	{
		$options = array();

		if ($show_updates)
		{
			$options['show-updates'] = null;
		}

		if ($no_ignore)
		{
			$options['no-ignore'] = null;
		}

		if ($verbose)
		{
			$options['verbose'] = null;
		}

		if ($xml)
		{
			$options['xml'] = null;
		}

		return $this->exec('status', $this->local_copy_path . $path, $options);
	}

	/**
	 * svn switch
	 * update working copy to a different url
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @param boolean $on_server
	 * @param mixed $relocate use if you want to change the repo url of your working copy
	 * @return array result
	 */
	public function svn_switch($path = '', $revision = false, $on_server = false, $relocate = false)
	{
		$options = $arg = array();

		if ($revision && !$relocate)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		if ($relocate)
		{
			$options['relocate'] = null;
			$on_server = false;

			$arg = array($this->local_copy_path, $relocate, $path);
		}
		else
		{
			$arg = $this->on_server($on_server) . $path;
		}

		return $this->exec('switch', $arg, $options, $on_server);
	}

	/**
	 * svn unlock
	 * unlock a path
	 *
	 * @param string $path path relative to svn root
	 * @param boolean $on_server
	 * @return array result
	 */
	public function svn_unlock($path = '', $on_server = false)
	{
		return $this->exec('unlock', $this->on_server($on_server) . $path, array(), $on_server);
	}

	/**
	 * svn update
	 * update local copy to $revision
	 *
	 * @param string $path path relative to svn root
	 * @param mixed $revision
	 * @return array result
	 */
	public function svn_update($path = '', $revision = false)
	{
		$options = array();

		if ($revision)
		{
			$options['revision'] = $this->build_revision($revision);
		}

		return $this->exec('update', $this->local_copy_path . $path, $options, true);
	}
}

?>