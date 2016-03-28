# WPGPG

This plugin GPG-encrypts all content for any users that are logged in to the WordPress instance. 

Note: if you're logged in into the site (including into /wp-admin) and enable this plugin, every page will be GPG encrypted, and thus totally unusable without a means to decrypt. Don't lock yourself out. 

### WARNINGS
- this is a proof-of-concept; has not been thoroughly tested. May not be secure. 
- it ONLY encrypts the output that comes directly from WordPress PHP components (e.g., the HTML returned from index.php). Other assets (e.g., JavaScript, CSS, images) are not encrypted. 
- does NOT encrypt anything you send to the server - only encrypts the output from WordPress.

# Requirements

- Needs either GPG installed, or OpenPGP-PHP (which has other dependencies, like phpseclib)
- If using GPG, the GPG keys for the WordPress users that want encrypted pages need to be added to the GPG keyring (probably of the user that the web server runs as). 
- The wpgpg Chrome extension (https://github.com/trogau/wpgpg-extension) to do browser-side decryption of content.

## Installation with GPG

1. Install plugin as usual
2. Edit config.php and put your GPG home dir (i.e., the directory which contains the GPG keyring you want to use - see below) in GPG_HOME_DIR, and your GPG binary location (GPG_BIN). Set the PGP_ENCRYPT_MODE to 'gpg'.
3. Set up the browser plugin so you can decrypt the content.
4. Activate the plugin, if you dare. 
5. Go to the wpgpg Options and check the 'GPG Encryption Enabled' checkbox. On 'save changes', the page should reload as a PGP-encrypted ASCII-armored chunk.

## Installation with OpenPGP-PHP

1. Install plugin as usual
2. Edit config.php and set the PGP_ENCRYPT_MODE to 'openpgp-php'.
3. Set up the browser plugin so you can decrypt the content.
4. Activate the plugin, if you dare. 
5. Log into WordPress and visit the profile page (Users->Your Profile, if you're logged in as usual). Paste your public key into the new field, 'GPG Public Key'.
6. Go to the wpgpg Options and check the 'GPG Encryption Enabled' checkbox. On 'save changes', the page should reload as a PGP-encrypted ASCII-armored chunk.

## GPG home dir

The GPG home directory will depend on what user your webserver is running as. Default Debian/Ubuntu user seems to be www-data. You may need to manually create the directory and set permissions correctly so GPG can write to it.  

## TODO

- Figure out how to encrypt other content aside from the WordPress content (e.g., stylesheets, images, etc)
- Look for other PHP/server side solutions for GPG.
- [DONE] Make it possible for users to add their GPG keys via some sort of config page
