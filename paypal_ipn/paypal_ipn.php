<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: paypal_ipn.php 11 2008-08-19 10:19:17Z evil3 $
* @copyright (c) 2008 phpbbmodders
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
 * Class to handle paypal IPN
 */
class paypal_ipn
{
	/**
	 * Paypal url, can be changed to sandbox for testing
	 */
	public $paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
	//public $paypal_url = 'http://www.sandbox.paypal.com/cgi-bin/webscr';

	/**
	 * post fields, following are possible keys:
	 * amount
	 * currency_code (USD, EUR, CHF)
	 * lc (country; CH, DE, GB, US)
	 * business
	 * item_name
	 * item_number
	 * no_shipping
	 * return
	 * cancel_return
	 * tax
	 * page_style
	 * bn
	 * notify_url
	 */
	protected $post_fields = array();

	/**
	 * Socket properties
	 */
	public $errno, $errstr;
	public $timeout = 30;

	/**
	 * Array to hold the ipn data
	 */
	public $ipn_data = array();

	/**
	 * constructor
	 */
	public function __construct()
	{
		$this->set_field('cmd', '_xclick');
	}

	/**
	 * Set a post field
	 */
	public function set_field($name, $value)
	{
		$this->post_fields[$name] = $value;
	}

	/**
	 * Returns hidden fields for paypal submission
	 */
	public function hidden_fields()
	{
		$hidden_fields = '';
		foreach ($this->post_fields as $name => $value)
		{
			$hidden_fields .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
		}

		return $hidden_fields;
	}

	/**
	 * Check if the ipn notification was processed successfully
	 */
	public function check_ipn()
	{
		$this->ipn_data = array_map('strval', $_POST);
		$this->ipn_data['cmd'] = '_notify-validate';

		if ($result = self::post_request($this->paypal_url, $this->ipn_data, $this->errno, $this->errstr, $this->timeout))
		{
			return (strpos($result, 'VERIFIED') !== false) ? true : false;
		}

		return false;
	}

	/**
	 * Process a post request
	 */
	public static function post_request($url, $data, &$errno = null, &$errstr = null, &$timeout = null)
	{
		$parse_url = parse_url($url);

		$port = 80;
		if ($parse_url['scheme'] === 'https')
		{
			$port = 443;
		}
		else if (isset($parse_url['port']))
		{
			$port = (int) $parse_url['port'];
		}

		$fp = fsockopen($parse_url['host'], $port, $errno, $errstr, $timeout);

		if (!$fp)
		{
			return false;
		}

		$values = array();
		foreach ($data as $key => $value)
		{
			$values[] = $key . '=' . urlencode($value);
		}

		$post_data = implode('&', $values);

		$request = '';
		$request .= "POST {$parse_url['path']} HTTP/1.1\r\n";
		$request .= "Host: {$parse_url['host']}\r\n";
		$request .= "Content-type: application/x-www-form-urlencoded\r\n";
		$request .= "Content-length: " . strlen($post_data) . "\r\n";
		$request .= "Connection: close\r\n\r\n";
		$request .= "$post_data\r\n\r\n";

		fputs($fp, $request);

		$response = '';
		while (!feof($fp))
		{
			$response .= fgets($fp, 1024);
		}

		fclose($fp);

		return $response;
	}
}

?>