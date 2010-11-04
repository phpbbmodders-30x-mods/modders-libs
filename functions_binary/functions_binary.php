<?php
/**
*
* @package phpbbmodders_lib
* @version $Id: functions_binary.php 4 2008-05-18 17:56:05Z evil3 $
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

/**
 * convert an ascii input string into it's binary equivalent
 * @param $input string Input string
 * @param $byte_length int Byte length
 * @return string Binary output
 */
function ascii_to_binary($input, $byte_length = 8)
{
	$input = (string) $input;

	$output = '';

	for ($i = 0, $size = strlen($input); $i < $size; $i++)
	{
		$char = decbin(ord($input[$i]));
		$char = str_pad($char, $byte_length, '0', STR_PAD_LEFT);
		$output .= $char;
	}

	return $output;
}

/**
 * convert a binary representation of a string back into it's original form
 * @param $input string Input string
 * @param $byte_length int Byte length
 * @return string Ascii output
 */
function binary_to_ascii($input, $byte_length = 8)
{
	$input = (string) $input;

	$size = strlen($input);

	if ($size % $byte_length)
	{
		return false;
	}

	$output = '';

	// jump between bytes.
	for ($i = 0; $i < $size; $i += $byte_length)
	{
		// extract character's binary code
		$char = substr($input, $i, $byte_length);
		$output .= chr(bindec($char)); // conversion to ASCII.
	}

	return $output;
}

?>