# WPGPG

This plugin GPG-encrypts all content for any users that are logged in to the WordPress instance. 

Note: if you're logged in into the site (including into /wp-admin) and enable this plugin, every page will be GPG encrypted, and thus totally unusable without a means to decrypt. Don't lock yourself out. 

### WARNINGS
- this is a proof-of-concept; has not been thoroughly tested. May not be secure. 
- does NOT encrypt anything you send to the server - only encrypts the output from WordPress.

# Requirements

- Needs GPG installed
- GPG keys for users that want encrypted pages need to be added to the GPG keyring (probably of the user that the web server runs as). 

## Installation

1. Install plugin as usual
2. Edit config.php and put your GPG home dir (i.e., the directory which contains the GPG keyring you want to use - see below) in GPG_HOME_DIR, and your GPG binary location (GPG_BIN).
3. Set up the browser plugin so you can decrypt the content.
4. Activate the plugin, if you dare. 

## GPG home dir

The GPG home directory will depend on what user your webserver is running as. Default Debian/Ubuntu user seems to be www-data. You may need to manually create the directory and set permissions correctly so GPG can write to it.  

## TODO

- Make it possible for users to add their GPG keys via some sort of config page
