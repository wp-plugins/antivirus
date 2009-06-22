<?php
/*
Plugin Name: AntiVirus
Plugin URI: http://wpantivirus.com
Description: AntiVirus for WordPress is a smart and effective solution to protect your blog against exploits and spam injections
Author: Sergej M&uuml;ller
Version: 0.3
Author URI: http://wpcoder.de
*/


if (!function_exists ('is_admin')) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class AntiVirus {
function AntiVirus() {
if (!class_exists('WPlize')) {
require_once('inc/wplize.class.php');
}
$this->WPlize = new WPlize('antivirus');
if (defined('DOING_AJAX')) {
add_action(
'wp_ajax_get_ajax_response',
array(
$this,
'get_ajax_response'
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
$this->plugin_basename = plugin_basename(__FILE__);
if (is_admin()) {
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
if ($this->is_current_page('home')) {
add_action(
'admin_head',
array(
$this,
'show_plugin_head'
)
);
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
} else if ($this->is_current_page('index')) {
if ($this->WPlize->get_option('cronjob_alert')) {
add_action(
'admin_notices',
array(
$this,
'show_dashboard_notices'
)
);
}
} else if ($this->is_current_page('plugins')) {
if (!$this->is_min_wp('2.5')) {
add_action(
'admin_notices',
array(
$this,
'show_plugin_notices'
)
);
}
add_action(
'activate_' .$this->plugin_basename,
array(
$this,
'init_plugin_options'
)
);
if ($this->is_min_wp('2.8')) {
add_filter(
'plugin_row_meta',
array(
$this,
'init_row_meta'
),
10,
2
);
} else {
add_filter(
'plugin_action_links',
array(
$this,
'init_action_links'
),
10,
2
);
}
}
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
if ($this->plugin_basename == $file) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->plugin_basename,
__('Settings')
)
),
$links
);
}
return $links;
}
function init_row_meta($links, $file) {
if ($this->plugin_basename == $file) {
return array_merge(
$links,
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->plugin_basename,
__('Settings')
)
)
);
}
return $links;
}
function init_plugin_options() {
$this->WPlize->init_option(
array(
'cronjob_alert'=> 0,
'cronjob_enable'=> 0,
'cronjob_timestamp' => 0,
'white_list'=> '',
'notify_email'=> ''
)
);
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
($this->is_min_wp('2.7') ? '<img src="' .plugins_url('antivirus/img/icon.png'). '" width="11" height="9" alt="AntiVirus Icon" />' : ''). 'AntiVirus',
9,
__FILE__,
array(
$this,
'show_admin_menu'
)
);
}
function exe_daily_cronjob() {
if (!$this->WPlize->get_option('cronjob_enable') || ($this->WPlize->get_option('cronjob_timestamp') + (60 * 60) > time())) {
return;
}
$this->WPlize->update_option(
'cronjob_timestamp',
time()
);
if ($this->check_theme_files()) {
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
wp_mail(
($this->WPlize->get_option('notify_email') ? $this->WPlize->get_option('notify_email') : get_bloginfo('admin_email')),
'[' .get_bloginfo('name'). '] ' .__('Suspicion on a virus', 'antivirus'),
sprintf(
"%s\n%s",
__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
get_bloginfo('url')
)
);
$this->WPlize->update_option(
'cronjob_alert',
1
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
function get_theme_name() {
if ($theme = $this->get_current_theme()) {
return $theme['Name'];
}
return false;
}
function get_white_list() {
return explode(
':',
$this->WPlize->get_option('white_list')
);
}
function get_ajax_response() {
$this->check_user_can();
check_ajax_referer('antivirus_ajax_nonce');
if (!$_POST || !$_POST['_action_request']) {
exit;
}
$values = array();
$output = '';
switch ($_POST['_action_request']) {
case 'get_theme_files':
$this->WPlize->update_option(
'cronjob_alert',
0
);
if ($files = $this->get_theme_files()) {
$values = $files;
}
break;
case 'check_theme_file':
if ($_POST['_theme_file'] && $lines = $this->check_theme_file($_POST['_theme_file'])) {
foreach ($lines as $num => $line) {
foreach ($line as $string) {
$values[] = $num;
$values[] = htmlentities($string, ENT_QUOTES);
$values[] = md5($num . $string);
}
}
}
break;
case 'update_white_list':
if ($_POST['_file_md5']) {
$this->WPlize->update_option(
'white_list',
implode(
':',
array_unique(
array_merge(
$this->get_white_list(),
array($_POST['_file_md5'])
)
)
)
);
$values = array($_POST['_file_md5']);
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
function get_dotted_line($line, $tag, $max = 100) {
if (!$line || !$tag) {
return false;
}
if (strlen($tag) > $max) {
return $tag;
}
$left = round(($max - strlen($tag)) / 2);
$tag = preg_quote($tag);
$output = preg_replace(
'/(' .$tag. ')(.{' .$left. '}).{0,}$/',
'$1$2 ...',
$line
);
$output = preg_replace(
'/^.{0,}(.{' .$left. ',})(' .$tag. ')/',
'... $1$2',
$output
);
return $output;
}
function check_file_line($line = '', $num) {
$line = trim($line);
if (!$line || !$num) {
return false;
}
$results = array();
$output = array();
preg_match_all(
'/(eval|base64_encode|base64_decode|create_function|exec|shell_exec|system|passthru|ob_get_contents|file|curl_init|readfile|fopen|fsockopen|pfsockopen|fclose|fread|file_put_contents)\s*?\(/',
$line,
$matches
);
if ($matches[1]) {
$results = $matches[1];
}
preg_match_all(
'/[\'\"\$\\ \/]*?([a-zA-Z0-9]{' .strlen(base64_encode('sergej+sweta=love')). ',})/',
$line,
$matches
);
if ($matches[1]) {
$results = array_merge($results, $matches[1]);
}
preg_match_all(
'/<\s*?(frame)/',
$line,
$matches
);
if ($matches[1]) {
$results = array_merge($results, $matches[1]);
}
preg_match(
'/get_option.*?\([\'"](.*?)[\'"]\)/',
$line,
$matches
);
if ($matches && $matches[1] && $this->check_file_line(get_option($matches[1]), $num)) {
array_push($results, 'get_option');
}
if ($results) {
$results = array_unique($results);
$md5 = $this->get_white_list();
foreach ($results as $tag) {
$string = str_replace(
$tag,
'@span@' .$tag. '@/span@',
$this->get_dotted_line($line, $tag)
);
if (!in_array(md5($num . $string), $md5)) {
$output[] = $string;
}
}
return $output;
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
if ($result = $this->check_file_line($line, $num)) {
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
function is_min_wp($version) {
return version_compare(
$GLOBALS['wp_version'],
$version. 'alpha',
'>='
);
}
function is_current_page($page) {
switch($page) {
case 'home':
return (isset($_REQUEST['page']) && $_REQUEST['page'] == $this->plugin_basename);
case 'index':
case 'plugins':
return ($GLOBALS['pagenow'] == sprintf('%s.php', $page));
}
return false;
}
function check_user_can() {
if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
}
function show_plugin_notices() {
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
echo sprintf(
'<div class="error"><p><strong>%s</strong> %s</p></div>',
__('AntiVirus for WordPress', 'antivirus'),
__('requires at least WordPress 2.5', 'antivirus')
);
}
function show_dashboard_notices() {
load_plugin_textdomain(
'antivirus',
sprintf(
'%s/antivirus/lang',
PLUGINDIR
)
);
echo sprintf(
'<div class="updated fade"><p><strong>%s:</strong> %s <a href="options-general.php?page=%s">%s</a></p></div>',
__('Suspicion on a virus', 'antivirus'),
__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
$this->plugin_basename,
__('Manual scan', 'antivirus')
);
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
'%1$s: %2$s | %3$s: %4$s | %5$s: %6$s<br />',
__('Plugin'),
__('AntiVirus for WordPress', 'antivirus'),
__('Version'),
$data['Version'],
__('Author'),
$data['Author']
);
}
function show_plugin_head() {
wp_enqueue_script('jquery') ?>
<style type="text/css">
<?php if ($this->is_min_wp('2.7')) { ?>
.icon32 {
background: url(<?php echo plugins_url('antivirus/img/icon32.png') ?>) no-repeat;
}
<?php } ?>
.postbox .output {
height: 1%;
padding: 0 0 1px;
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
xmargin: 0 0 -10px -3px;
xpadding: 10px 0 0;
overflow: hidden;
}
#antivirus_check_output div {
float: left;
color: #FFF;
margin: 12px 6px 0;
padding: 10px 12px;
font-size: 11px !important;
background: orange;
line-height: 1.2em;
border-radius: 10px;
-moz-border-radius: 10px;
-webkit-border-radius: 10px;
}
#antivirus_check_output div p {
padding: 10px;
overflow: hidden;
background: #F9F9F9;
white-space: nowrap;
text-shadow: none;
border-radius: 10px;
-moz-border-radius: 10px;
-webkit-border-radius: 10px;
}
#antivirus_check_output div p a {
float: left;
color: #646464;
margin: 0 6px 12px 0;
display: block;
border: 1px solid #bbbbbb;
padding: 2px 5px;
background: #f2f2f2;
text-decoration: none;
-moz-border-radius: 5px;
-webkit-border-radius: 5px;
}
#antivirus_check_output div p a:hover {
border: 1px solid #646464;
}
#antivirus_check_output div p code {
clear: both;
float: left;
color: #000;
padding: 2px 5px;
-moz-border-radius: 2px;
-webkit-border-radius: 2px;
}
#antivirus_check_output div p code span {
padding: 2px;
background: yellow;
}
table.form-table {
margin: 0;
}
table.form-table span.shift {
display: block;
margin: 2px 0 0 18px;
*margin-left: 26px;
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
'action':'get_ajax_response',
'_ajax_nonce':'<?php echo wp_create_nonce("antivirus_ajax_nonce") ?>',
'_theme_file':file,
'_action_request':'check_theme_file'
},
function(data) {
var output = $('#antivirus_check_' + id);
var content = output.text();
if (lines = eval(data)) {
output.animate(
{
backgroundColor: 'red',
width: '97%'
},
500
);
var i = 0;
var len = lines.length;
for (i; i < len; i = i + 3) {
var num = parseInt(lines[i]) + 1;
var line = lines[i + 1].replace(/@span@/g, '<span>').replace(/@\/span@/g, '</span>');
var md5 = lines[i + 2];
output.append('<p><a href="#" id="' + md5 + '"><?php echo _e("There is no virus", "antivirus") ?></a> <a href="theme-editor.php?file=' + content + '&theme=<?php echo urlencode($this->get_theme_name()) ?>" target="_blank"><?php echo _e("View line", "antivirus") ?> ' + num + '</a><code>' + line + "</code></p>");
$('#' + md5).click(
function() {
$.post(
'<?php echo WP_ADMIN_URL ?>/admin-ajax.php',
{
'action':'get_ajax_response',
'_ajax_nonce':'<?php echo wp_create_nonce("antivirus_ajax_nonce") ?>',
'_file_md5':$(this).attr('id'),
'_action_request':'update_white_list'
},
function(data) {
if (input = eval(data)) {
var parent = $('#' + input[0]).parent();
if (parent.parent().children().length <= 1) {
parent.parent().hide('slow').remove();
}
parent.hide('slow').remove();
}
}
);
return false;
}
);
}
} else {
output.animate(
{
backgroundColor: 'green'
},
500
);
}
antivirus_files_loaded ++;
if (antivirus_files_loaded >= antivirus_files_total) {
$('#antivirus_check_alert').text('<?php _e("Scan finished", "antivirus") ?>').fadeIn().fadeOut().fadeIn().animate({opacity: 1.0}, 500).fadeOut('slow', function() {$(this).empty();});
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
'action':'get_ajax_response',
'_ajax_nonce':'<?php echo wp_create_nonce("antivirus_ajax_nonce") ?>',
'_action_request':'get_theme_files'
},
function(data) {
check_theme_files(eval(data));
}
);
return false;
}
);
function manage_options() {
var id = 'antivirus_cronjob_enable';
$('#' + id).parents('.form-table').find('input[id!="' + id + '"]').attr('disabled', !$('#' + id).attr('checked'));
}
$('#antivirus_cronjob_enable').click(manage_options);
manage_options();
}
);
</script>
<?php }
function show_admin_menu() {
$this->check_user_can();
if (isset($_POST) && !empty($_POST)) {
check_admin_referer('antivirus');
$this->WPlize->update_option(
array(
'cronjob_enable'=> $_POST['antivirus_cronjob_enable'],
'notify_email'=> $_POST['antivirus_notify_email']
)
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
<?php if ($this->is_min_wp('2.7')) { ?>
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
<input type="checkbox" name="antivirus_cronjob_enable" id="antivirus_cronjob_enable" value="1" <?php checked($this->WPlize->get_option('cronjob_enable'), 1) ?> />
<?php _e('Enable the daily antivirus scan and send the administrator an e-mail if suspicion on a virus', 'antivirus') ?>
<?php echo ($this->WPlize->get_option('cronjob_timestamp') ? ('&nbsp;<span class="setting-description">(' .__('Last', 'antivirus'). ': ' .date_i18n('d.m.Y H:i:s', $this->WPlize->get_option('cronjob_timestamp')). ')</span>') : '') ?>
</label>
<span class="shift">
<input type="text" name="antivirus_notify_email" value="<?php echo $this->WPlize->get_option('notify_email') ?>" class="regular-text" /> <?php _e('Alternate e-mail address', 'antivirus') ?>
</span>
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
<?php _e('About', 'antivirus') ?>
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
if (class_exists('AntiVirus')) {
new AntiVirus();
}