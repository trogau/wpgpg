<?php
/*
Plugin Name: wpgpg
Plugin URI: http://trog.qgl.org/wpgpg
Description: GPG/PGP encryption for WordPress output
Author: trogau
Version: 0.1.0
Author URI: http://trog.qgl.org
License: GPLv2 or later
*/

require_once("config.php");

/**
 * Output Buffering
 *
 * Buffers the entire WP process, capturing the final output for manipulation.
 * From http://stackoverflow.com/questions/772510/wordpress-filter-to-modify-final-html-output
 */
ob_start();

/**
 * get_user()
 *
 * Get the current user information. If we're logged in, add an action for the page shutdown and set up the filter
 * to do the encryption via GPG. 
 * 
 */
function get_user()
{
	global $current_user;
	if ($current_user->user_email)
	{
		add_action('shutdown', function($email) {
			$final = '';

		// We'll need to get the number of ob levels we're in, so that we can iterate over each, collecting
		// that buffer's output into the final output.
		$levels = ob_get_level();


		for ($i = 0; $i < $levels; $i++)
    		{
			$final .= ob_get_clean();
		}

		// Apply any filters to the final output
		global $current_user;
		echo apply_filters('final_output', $final);
		}, 0);



		add_filter('final_output', function($output) {
			global $current_user;
			$cmd = GPG_BIN." --homedir ".GPG_HOME_DIR." --armor --batch -e -r '$current_user->user_email'";
			$crypted = encrypt_command($cmd, $output);
			return "<html><div id='decryptbtn'></div><br /><div id='crypted' style='white-space:pre;font-family:monospace;font-size:11px;width:500px;border:dotted 2px black; height:200px;overflow:auto'>$crypted</div></html>";
		});

	}
}

// Call the get_user() function once WordPress hits init - otherwise none of the user functionality has been loaded so we
// can't retreive the email. 
// FIXME: may be a better way to call this or a different trigger point that is more suitable. 
add_action( 'init', 'get_user' );


/**
 * encrypt_command
 *
 * Pass the page contents to GnuPG to be encrypted. 
 *
 * WARNING: may not be a secure method. Literally the first thing I found that looked easy to implement and
 * worked OK. 
 *
 * From: https://github.com/paulyasi/encryption_helper/blob/master/encryption_helper.php
 */
function encrypt_command ($gpg_command, $data)
{
	$descriptors = array(
			0 => array("pipe", "r"), //stdin
			1 => array("pipe", "w"), //stdout
			2 => array("pipe", "w"), //stderr
			);
	$process = proc_open($gpg_command, $descriptors, $pipes);

	if (is_resource($process)) {
		// send data to encrypt to stdin
		fwrite($pipes[0], $data);
		fclose($pipes[0]);
		// read stdout
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		// read stderr
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		// It is important that you close any pipes before calling
		// proc_close in order to avoid a deadlock
		$return_code = proc_close($process);
		$return_value = trim($stdout, "\n");
		//echo "$stdout";
		if (strlen($return_value) < 1) {
			$return_value = "error: $stderr";
		}
	}
	return $return_value;
}
