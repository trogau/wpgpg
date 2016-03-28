<?php
/*
Plugin Name: wpgpg
Plugin URI: http://trog.qgl.org/wpgpg
Description: GPG/PGP encryption for WordPress output
Author: trogau
Version: 0.1.1
Author URI: http://trog.qgl.org
License: GPLv2 or later
*/

require_once("config.php");

if (PGP_ENCRYPT_MODE == 'openpgp-php')
{
	require_once("openpgp-php/vendor/autoload.php");
	require_once("openpgp-php/lib/helpers.php");
}

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

			if (PGP_ENCRYPT_MODE == 'gpg')
			{
				$cmd = GPG_BIN." --homedir ".GPG_HOME_DIR." --armor --batch -e -r '$current_user->user_email'";
				$crypted = encrypt_command($cmd, $output);
			}
			else if (PGP_ENCRYPT_MODE == 'openpgp-php')
			{
				// Get the stored user key
				$public_key = get_user_meta($current_user->ID, 'gpgpublickey', true);
				$key = OpenPGP_Message::parse(OpenPGP::unarmor(trim($public_key)));
				$data = new OpenPGP_LiteralDataPacket($output, array('format' => 'u'));
				$encrypted = OpenPGP_Crypt_Symmetric::encrypt($key, new OpenPGP_Message(array($data)));
				$output = OpenPGP::enarmor($encrypted->to_bytes(), "PGP MESSAGE");
				$crypted = wordwrap($output, 64, "\n", 1);
			}
			else
			{
				die("No encryption tool is set (see config.php:PGP_ENCRYPT_MODE)");
			}

			return "<html><body><div id='decryptbtn'></div><br /><div id='crypted' style='white-space:pre;font-family:monospace;font-size:14px;width:100%;border:dotted 1px black;overflow:auto'>$crypted</div></body></html>";
		});
	}
}

// Call the get_user() function once WordPress hits init - otherwise none of the user functionality has been loaded so we
// can't retreive the email. 
// FIXME: may be a better way to call this or a different trigger point that is more suitable. 
$options = get_option('wpgpg_options');
if ($options['wpgpg_onoroff'] === "1")
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


add_action( 'show_user_profile', 'gpgProfileField' );
add_action( 'edit_user_profile', 'gpgProfileField' );

function gpgProfileField($user)
{
?>
	<h3><?php _e("Extra profile information", "blank"); ?></h3>
	<table class="form-table">
    <tr>
      <th><label for="gpgpublickey"><?php _e("GPG Public Key"); ?></label></th>
      <td>
        <textarea name="gpgpublickey" id="gpgpublickey" class="regular-text"><?php echo esc_attr( get_the_author_meta( 'gpgpublickey', $user->ID ) ); ?></textarea><br />
        <span class="description"><?php _e("Please enter your GPG Public Key."); ?></span>
    </td>
    </tr>
  </table>
<?php
}

add_action( 'personal_options_update', 'gpgSaveProfileField' );
add_action( 'edit_user_profile_update', 'gpgSaveProfileField' );

function gpgSaveProfileField( $user_id )
{
	$saved = false;
	if ( current_user_can( 'edit_user', $user_id ) )
	{
		// Verify the key
		if (isValidPublicKey($_POST['gpgpublickey']))
		{
			update_user_meta( $user_id, 'gpgpublickey', $_POST['gpgpublickey'] );
			$saved = true;
		}
		else
		{
			// FIXME: better error handling
			die("ERROR: invalid public key");
		}
	}
	return true;
}


/**
 * WordPress Options
 *
 * Taken from http://ottopress.com/2009/wordpress-settings-api-tutorial/ (referenced in official WordPress 
 * docs here: https://codex.wordpress.org/Creating_Options_Pages
 *
 */

add_action('admin_menu', 'plugin_admin_add_page');
function plugin_admin_add_page()
{
	add_options_page('wpgpg Options', 'wpgpg Options', 'manage_options', 'wpgpg', 'wpgpg_options_page');
}

function wpgpg_options_page()
{
?>
	<div>
	<h2>wpgpg Options</h2>
	WP GPG Options
	<form action="options.php" method="post">
	<?php settings_fields('wpgpg_options'); ?>
	<?php do_settings_sections('wpgpg'); ?>

	<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
	</form></div>

<?php
}

add_action('admin_init', 'plugin_admin_init');

function plugin_admin_init()
{
	register_setting( 'wpgpg_options', 'wpgpg_options', 'wpgpg_options_validate' );
	add_settings_section('wpgpg_plugin_main', 'wpgpg Settings', 'wpgpg_section_text', 'wpgpg');
	add_settings_field('wpgpg_enabled_checkbox', 'GPG Encryption Enabled', 'wpgpg_setting_checkbox', 'wpgpg', 'wpgpg_plugin_main');
}

function wpgpg_section_text()
{
	echo "";
}


function wpgpg_setting_checkbox()
{
	$options = get_option('wpgpg_options');

	$html = '<input type="checkbox" id="wpgpg_onoroff" name="wpgpg_options[wpgpg_onoroff]" 
		value="1"' . checked( 1, $options['wpgpg_onoroff'], false ) . '/>';
	$html .= '<label for="wpgpg_onoroff">Check to enable wpgpg output encryption</label>';

	echo $html;
}

function wpgpg_options_validate($input)
{
	if ($input['wpgpg_onoroff'] === NULL || $input['wpgpg_onoroff'] === "1")
		return $input;
}

/**
 * START Settings Link in Plugin List
 * http://bavotasan.com/2009/a-settings-link-for-your-wordpress-plugins/
 */
function wpgpg_settings_link($links)
{
	$settings_link = "<a href='options-general.php?page=wpgpg'>Settings</a>";
	array_unshift($links, $settings_link);
	return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'wpgpg_settings_link' );

/**
 * END Settings Link in Plugin List
 */
