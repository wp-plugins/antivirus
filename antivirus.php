<?php
/*
Plugin Name: AntiVirus
Plugin URI: http://playground.ebiene.de/1577/antivirus-wordpress-plugin/
Description: AntiVirus for WordPress is a smart and effective solution to protect your blog against exploits and spam injections
Author: Sergej M&uuml;ller
Version: 0.1
Author URI: http://wp-coder.de
*/


class AntiVirus {
function AntiVirus() {
if (defined('DOING_AJAX')) {
add_action(
'wp_ajax_get_check_response',
array(
$this,
'get_check_response'
)
);
} else {
if (!defined('PLUGINDIR')) {
define('PLUGINDIR', 'wp-content/plugins');
}
if (!defined('WP_CONTENT_DIR')) {
define('WP_CONTENT_DIR', ABSPATH. 'wp-content');
}
if (function_exists('admin_url')) {
define('WP_ADMIN_URL', rtrim(admin_url(), '/'));
} else {
define('WP_ADMIN_URL', get_option('siteurl'). '/wp-admin');
}
if (is_admin()) {
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
add_action(
'activate_' .plugin_basename(__FILE__),
array(
$this,
'init_plugin_options'
)
);
if (basename($_SERVER['REQUEST_URI']) == 'antivirus.php') {
add_action(
'admin_head',
array(
$this,
'show_plugin_head'
)
);
}
add_filter(
'plugin_action_links',
array(
$this,
'init_action_links'
),
10,
2
);
} else {
add_action(
'antivirus_daily_cronjob',
array(
$this,
'exe_daily_cronjob'
)
);
}
}
}
function init_action_links($links, $file) {
$plugin = plugin_basename(__FILE__);
if ($file == $plugin) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$plugin,
__('Settings')
)
),
$links
);
}
return $links;
}
function init_plugin_options() {
add_option('antivirus_cronjob_enable');
add_option('antivirus_cronjob_timestamp');
if (function_exists('wp_schedule_event') === true) {
if (wp_next_scheduled('antivirus_daily_cronjob_hook')) {
wp_clear_scheduled_hook('antivirus_daily_cronjob_hook');
}
if (!wp_next_scheduled('antivirus_daily_cronjob_hook')) {
wp_schedule_event(time(), 'daily', 'antivirus_daily_cronjob');
}
}
}
function init_admin_menu() {
add_options_page(
'AntiVirus',
($this->check_plugins_url() ? '<img src="' .plugins_url('antivirus/img/icon.png'). '" width="11" height="9" alt="AntiVirus Icon" />' : ''). 'AntiVirus',
9,
__FILE__,
array(
$this,
'show_admin_menu'
)
);
}
function exe_daily_cronjob() {
if (!get_option('antivirus_cronjob_enable') || (get_option('antivirus_cronjob_enable') + (60 * 60) > time())) {
return;
}
update_option('antivirus_cronjob_timestamp', time());
if ($this->check_theme_files()) {
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
wp_mail(
get_bloginfo('admin_email'),
'[' .get_bloginfo('name'). '] ' .__('Suspicion on a virus', 'antivirus'),
__('The daily antivirus scan of your blog suggests alarm.', 'antivirus')
);
}
}
function get_current_theme() {
if ($themes = get_themes()) {
if ($theme = get_current_theme()) {
return $themes[$theme];
}
}
return false;
}
function get_theme_files() {
if ($theme = $this->get_current_theme()) {
return array_unique(
array_map(
create_function(
'$v',
'return str_replace("wp-content", "", $v);'
),
$theme['Template Files']
)
);
}
return false;
}
function get_check_response() {
$this->check_user_can();
check_ajax_referer('antivirus_ajax_nonce');
if (!$_POST || !$_POST['_action_request']) {
exit;
}
$values = array();
$output = '';
switch ($_POST['_action_request']) {
case 'get_theme_files':
if ($files = $this->get_theme_files()) {
$values = $files;
}
break;
case 'check_theme_file':
if ($_POST['_theme_file'] && $lines = $this->check_theme_file($_POST['_theme_file'])) {
foreach ($lines as $num => $line) {
$values[] = $num;
$values[] = htmlentities($line, ENT_QUOTES);
}
}
break;
default:
break;
}
if ($values) {
$output = sprintf(
"['%s']",
implode("', '", $values)
);
header('Content-Type: text/plain');
header('Content-Length: ' .strlen($output));
echo $output;
}
exit();
}
function get_file_content($file) {
return file(WP_CONTENT_DIR . $file);
}
function check_file_line($line = '') {
$line = trim($line);
if (!$line) {
return false;
}
preg_match_all(
'/(eval|base64_encode|base64_decode|create_function|exec|shell_exec|system|passthru|ob_get_contents|file|curl_init|readfile|fopen|fsockopen|pfsockopen|fclose|fread|file_put_contents|get_option)\s*?\(/',
$line,
$matches
);
if ($matches) {
if (@$matches[1][0]) {
$results = $matches[1][0];
} else if (@$matches[1]) {
$results = $matches[1];
} else {
$results = array();
}
}
if ($results) {
if ($results == 'get_option' && preg_match('/get_option\s*\([\'"](.*?)[\'"]\)/', $line, $matches) && !$this->check_file_line(get_option($matches[1]))) {
$results = array();
}
}
if (!$results) {
preg_match('/([a-zA-Z0-9]{' .strlen(base64_encode('sergej+sweta=love')). ',})/', $line, $matches);
$results = @$matches[1];
}
if ($results) {
return str_replace(
$results,
sprintf(
'@span@%s@/span@',
$results
),
$line
);
}
return false;
}
function check_theme_file($file) {
if (!$file) {
return false;
}
if ($content = $this->get_file_content($file)) {
$results = array();
foreach($content as $num => $line) {
if ($result = $this->check_file_line($line)) {
$results[$num] = $result;
}
}
return $results;
}
return false;
}
function check_theme_files() {
if ($files = $this->get_theme_files()) {
$results = array();
foreach($files as $file) {
if ($result = $this->check_theme_file($file)) {
$results[$file] = $result;
}
}
return $results;
}
return false;
}
function check_plugins_url() {
return version_compare($GLOBALS['wp_version'], '2.6.999', '>') && function_exists('plugins_url');
}
function check_user_can() {
if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
__('Plugin'). ': %1$s | ' . __('Version'). ': %2$s | ' .__('Author'). ': %3$s<br />',
__('AntiVirus for WordPress', 'antivirus'),
$data['Version'],
$data['Author']
);
}
function show_plugin_head() {
wp_enqueue_script('jquery') ?>
<style type="text/css">
<?php if ($this->check_plugins_url()) { ?>
.icon32 {
background: url(<?php echo plugins_url('antivirus/img/icon32.png') ?>) no-repeat;
}
<?php } ?>
.postbox .output {
height: 1%;
padding: 0 0 5px;
overflow: hidden;
}
#antivirus_check_start {
float: left;
}
#antivirus_check_alert {
float: left;
color: green;
margin: 1px 10px 0;
border: 1px solid green;
display: none;
padding: 2px 5px;
-moz-border-radius: 5px;
-webkit-border-radius: 5px;
}
#antivirus_check_output {
clear: both;
height: 1%;
margin: 0 0 -10px -3px;
padding: 10px 0 0;
overflow: hidden;
}
#antivirus_check_output div {
float: left;
color: white;
margin: 10px;
padding: 10px;
font-size: 11px !important;
background: orange;
text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.3);
line-height: 1.2em;
-moz-border-radius: 11px;
-webkit-border-radius: 11px;
}
#antivirus_check_output div p {
padding: 10px;
overflow: hidden;
background: #F9F9F9;
white-space: nowrap;
text-shadow: none;
-moz-border-radius: 11px;
-webkit-border-radius: 11px;
}
#antivirus_check_output div p a {
display: block;
margin: 0 0 10px;
font-weight: bold;
}
#antivirus_check_output div p code {
color: black;
padding: 2px 5px;
-moz-border-radius: 2px;
-webkit-border-radius: 2px;
}
#antivirus_check_output div p code span {
padding: 2px;
background: yellow;
}
</style>
<script type="text/javascript">
jQuery(document).ready(
function($) {
function check_theme_files(files) {
if (!files) {
return;
}
var i = 0;
var len = files.length;
var alert = $('#antivirus_check_alert');
var output = $('#antivirus_check_output');
antivirus_files_total = len;
antivirus_files_loaded = 0;
alert.empty();
output.empty();
for (i; i < len; i++) {
output.append('<div id="antivirus_check_' + i + '">' + files[i] + '</div>');
check_theme_file(files[i], i);
}
}
function check_theme_file(file, id) {
if (!file) {
return;
}
$.post(
'<?php echo WP_ADMIN_URL ?>/admin-ajax.php',
{
'action':'get_check_response',
'_theme_file':file,
'_ajax_nonce':'<?php echo wp_create_nonce("antivirus_ajax_nonce") ?>',
'_action_request':'check_theme_file'
},
function(data) {
var output = $('#antivirus_check_' + id);
var content = output.text();
if (lines = eval(data)) {
output.animate(
{
backgroundColor: 'red',
clear: 'left',
width: '97%'
},
1000
);
var i = 0;
var len = lines.length;
for (i; i < len; i = i + 2) {
var num = lines[i];
var line = lines[i + 1].replace('@span@', '<span>').replace('@/span@', '</span>');
output.append('<p><a href="theme-editor.php?file=' + content + '" target="_blank"><?php echo _e('Line', 'antivirus') ?> ' + num + "</a><code>" + line + "</code></p>");
}
} else {
output.animate(
{
backgroundColor: 'green'
},
1000
);
}
antivirus_files_loaded ++;
if (antivirus_files_loaded >= antivirus_files_total) {
$('#antivirus_check_alert').text('<?php _e("Scan finished", "antivirus") ?>').fadeIn().animate({opacity: 1.0}, 3000).fadeOut('slow', function() {$(this).empty();});
}
}
);
}
$('#antivirus_check_start').click(
function() {
$('.postbox .output').append('<div id="antivirus_check_output"></div>');
$('.postbox .output p').append('<span id="antivirus_check_alert"></span>');
$.post(
'<?php echo WP_ADMIN_URL ?>/admin-ajax.php',
{
'action':'get_check_response',
'_ajax_nonce':'<?php echo wp_create_nonce("antivirus_ajax_nonce") ?>',
'_action_request': 'get_theme_files'
},
function(data) {
check_theme_files(eval(data));
}
);
return false;
}
);
}
);
</script>
<?php }
function show_admin_menu() {
$this->check_user_can();
if (isset($_POST) && !empty($_POST)) {
check_admin_referer('antivirus');
update_option(
'antivirus_cronjob_enable',
$_POST['antivirus_cronjob_enable']
); ?>
<div id="message" class="updated fade">
<p>
<strong>
<?php _e('Settings saved.') ?>
</strong>
</p>
</div>
<?php } ?>
<div class="wrap">
<?php if ($this->check_plugins_url()) { ?>
<div class="icon32"><br /></div>
<?php } ?>
<h2>
AntiVirus
</h2>
<form method="post" action="">
<?php wp_nonce_field('antivirus') ?>
<div id="poststuff" class="ui-sortable">
<div class="postbox">
<h3>
<?php _e('Settings') ?>
</h3>
<div class="inside">
<table class="form-table">
<tr>
<td>
<label for="antivirus_cronjob_enable">
<input type="checkbox" name="antivirus_cronjob_enable" id="antivirus_cronjob_enable" value="1" <?php checked(get_option('antivirus_cronjob_enable'), 1) ?> />
<?php _e('Enable the daily antivirus scan and send me an email if suspicion on a virus', 'antivirus') ?>
<span class="setting-description" style="xline-height:24px">(<?php if (get_option('antivirus_cronjob_timestamp')) {
echo __('Last', 'antivirus'). ': '. date_i18n('d.m.Y H:i:s', get_option('antivirus_cronjob_timestamp'));
} else {
_e('Never executed', 'antivirus');
} ?>)</span>
</label>
</td>
</tr>
</table>
<p>
<input type="submit" name="antivirus_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</div>
</div>
<div class="postbox">
<h3>
<?php _e('Manual scan', 'antivirus') ?>
</h3>
<div class="inside output">
<p>
<a href="#" id="antivirus_check_start" class="button rbutton"><?php _e('Scan the templates now', 'antivirus') ?></a>
</p>
</div>
</div>
<div class="postbox">
<h3>
<?php _e('About AntiVirus', 'antivirus') ?>
</h3>
<div class="inside">
<p>
<?php $this->show_plugin_info() ?>
</p>
</div>
</div>
</div>
</form>
</div>
<?php }
}
if (class_exists('AntiVirus') && function_exists('is_admin')) {
$GLOBALS['AntiVirus'] = new AntiVirus();
}