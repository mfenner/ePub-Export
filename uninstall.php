<?php

// For old versions --
if ( !defined('WP_UNINSTALL_PLUGIN')) {
	exit();
}

// Delete the option data --
delete_option('cio_aim');
delete_option('cio_yim');
delete_option('cio_jabber');
delete_option('cio_twitter');
delete_option('cio_facebook');
delete_option('cio_delicious');
delete_option('cio_citeulike');
delete_option('cio_mendeley');
delete_option('cio_orcid');
delete_option('cio_affiliation');

?>