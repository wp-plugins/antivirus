<?php
/* Remove settings */
delete_option('antivirus');

/* Clean DB */
$GLOBALS['wpdb']->query("OPTIMIZE TABLE `" .$GLOBALS['wpdb']->options. "`");