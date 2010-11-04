<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: functions_cute_url.php 1 2008-05-06 22:58:49Z evil3 $
* @copyright (c) 2007 phpbbmodders
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
 * class used for the nice urls
 */
class cute_url_handler
{
	/**
	 * This is used for writing URLs, for example /homepage/index.php/test/
	 *
	 * @var string
	 */
	public $url_base = '';

	/**
	 * Contains the URL in form of an array
	 *
	 * @var array
	 */
	public $url = array();

	/**
	 * Copy of $url, used for array_shift()
	 *
	 * @var array
	 */
	public $_url = array();

	/**
	 * Separates url chunks
	 *
	 * @var string
	 */
	public $separator = '/';

	/**
	 * Gets appended to the URL
	 *
	 * @var string
	 */
	public $url_append = '/';

	/**
	 * Limit for performances sake
	 */
	const MAX_URL_CHUNKS = 20;

	/**
	 * Constructor
	 *
	 * @param string $url_base
	 * @return cute_url_handler
	 */
	public function __construct($url_base)
	{
		$this->url_base = (string) $url_base;

		$url = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

		// remove the url base from the beginning
		if (strpos($url, $this->url_base) === 0)
		{
			$url = substr($url, strlen($this->url_base) - 1);
		}

		// Get rid of _GET query string
		if (strpos($url, '?') !== false)
		{
			$url = substr($url, 0, strpos($url, '?'));
		}

		// if we have url_append at the end, remove it
		if ($url !== '/' && $this->url_append && strrpos($url, $this->url_append) === (strlen($url) - strlen($this->url_append)))
		{
			$url = substr($url, 0, -strlen($this->url_append));
		}

		// remove / at the beginning
		if (strlen($url) && $url[0] === '/')
		{
			$url = substr($url, 1);
		}

		$url = explode($this->separator, $url, self::MAX_URL_CHUNKS);

		// Now get rid of empty entries
		$url = array_diff($url, array(''));

		$this->url = $this->_url = $url;
	}

	/**
	 * Build an URL
	 *
	 * @param array $url_ary Array of url chunks
	 * @param array $request_ary Array of request data
	 * @param boolean $no_append If true, url_append will not be added
	 * @param mixed $append_string A string that gets appended
	 * @param boolean $no_sid If set true, session id will be removed from the url
	 * @return string url
	 */
	public function build($url_ary = array(), $request_ary = array(), $no_append = false, $append_string = false, $no_sid = false)
	{
		if (empty($request_ary))
		{
			$request_ary = false;
		}

		$url = $this->url_base . implode($this->separator, $url_ary) . (!empty($url_ary) && !$no_append ? $this->url_append : '');
		$url = append_sid($url, $request_ary);

		if ($no_sid)
		{
			$url = preg_replace('#sid=([0-9a-f]{32})(&|&amp;|)#', '', $url);
		}

		// remove trailing ?
		if (substr($url, -1) === '?')
		{
			$url = substr($url, 0, -1);
		}

		// this is for things like #p20
		if ($append_string !== false)
		{
			$url .= $append_string;
		}

		return $url;
	}

	/**
	 * Get the next part of the URL (array_shift())
	 *
	 * @param string default value
	 * @return string URL
	 */
	public function get($default = '')
	{
		if (sizeof($this->_url))
		{
			$return = array_shift($this->_url);

			// this is a phpbb function
			// htmlspecialchars it, like request_var
			set_var($return, $return, gettype($default), true);

			return $return;
		}
		return $default;
	}
}

?>