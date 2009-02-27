<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
	if (function_exists('wp_schedule_event') === true) {
		if (wp_next_scheduled('antivirus_daily_cronjob')) {
			wp_clear_scheduled_hook('antivirus_daily_cronjob');
		}
 	}
 	
	delete_option('antivirus');
}
?>