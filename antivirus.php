<?php
/*
Plugin Name: AntiVirus
Plugin URI: http://wpantivirus.com
Description: AntiVirus for WordPress is a smart, effectively solution to protect your blog against exploits and spam injections.
Author: Sergej M&uuml;ller
Version: 0.8
Author URI: http://www.wpSEO.org
*/


if (!function_exists ('is_admin')) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class AntiVirus {
var $base_name;
function AntiVirus() {
$this->base_name = plugin_basename(__FILE__);
if (defined('DOING_CRON')) {
add_action(
'antivirus_daily_cronjob',
array(
$this,
'exe_daily_cronjob'
)
);
} elseif (is_admin()) {
if (defined('DOING_AJAX')) {
add_action(
'wp_ajax_get_ajax_response',
array(
$this,
'get_ajax_response'
)
);
} else {
add_action(
'admin_menu',
array(
$this,
'init_admin_menu'
)
);
if ($this->is_current_page('home')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'admin_init',
array(
$this,
'add_plugin_sources'
)
);
} else if ($this->is_current_page('index')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'admin_notices',
array(
$this,
'show_dashboard_notice'
)
);
} else if ($this->is_current_page('plugins')) {
add_action(
'init',
array(
$this,
'load_plugin_lang'
)
);
add_action(
'activate_' .$this->base_name,
array(
$this,
'init_plugin_options'
)
);
add_action(
'deactivate_' .$this->base_name,
array(
$this,
'clear_scheduled_hook'
)
);
add_action(
'admin_notices',
array(
$this,
'show_version_notice'
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
}
}
}
function load_plugin_lang() {
load_plugin_textdomain(
'antivirus',
false,
'antivirus/lang'
);
}
function init_action_links($links, $file) {
if ($this->base_name == $file) {
return array_merge(
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->base_name,
__('Settings')
)
),
$links
);
}
return $links;
}
function init_row_meta($links, $file) {
if ($this->base_name == $file) {
return array_merge(
$links,
array(
sprintf(
'<a href="options-general.php?page=%s">%s</a>',
$this->base_name,
__('Settings')
)
)
);
}
return $links;
}
function init_plugin_options() {
add_option(
'antivirus',
array(),
'',
'no'
);
if ($this->get_option('cronjob_enable')) {
$this->init_scheduled_hook();
}
}
function get_option($field) {
if (!$options = wp_cache_get('antivirus')) {
$options = get_option('antivirus');
wp_cache_set(
'antivirus',
$options
);
}
return @$options[$field];
}
function update_option($field, $value) {
$this->update_options(
array(
$field => $value
)
);
}
function update_options($data) {
$options = array_merge(
(array)get_option('antivirus'),
$data
);
update_option(
'antivirus',
$options
);
wp_cache_set(
'antivirus',
$options
);
}
function init_scheduled_hook() {
if (!wp_next_scheduled('antivirus_daily_cronjob')) {
wp_schedule_event(
time(),
'daily',
'antivirus_daily_cronjob'
);
}
}
function clear_scheduled_hook() {
if (wp_next_scheduled('antivirus_daily_cronjob')) {
wp_clear_scheduled_hook('antivirus_daily_cronjob');
}
}
function exe_daily_cronjob() {
if (!$this->get_option('cronjob_enable')) {
return;
}
$this->update_option(
'cronjob_timestamp',
time()
);
if ($this->check_theme_files() || $this->check_permalink_structure()) {
$this->load_plugin_lang();
$email = $this->get_option('notify_email');
$email = (empty($email) ? get_bloginfo('admin_email') : $email);
wp_mail(
$email,
sprintf(
'[%s] %s',
get_bloginfo('name'),
__('Suspicion on a virus', 'antivirus')
),
sprintf(
"%s\n%s",
__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
get_bloginfo('url')
)
);
$this->update_option(
'cronjob_alert',
1
);
}
}
function init_admin_menu() {
$page = add_options_page(
'AntiVirus',
'<img src="' .plugins_url('antivirus/img/icon.png'). '" id="av_icon" alt="AntiVirus Icon" />AntiVirus',
($this->is_min_wp('2.8') ? 'manage_options' : 9),
__FILE__,
array(
$this,
'show_admin_menu'
)
);
add_action(
'admin_print_scripts-' . $page,
array(
$this,
'add_enqueue_script'
)
);
add_action(
'admin_print_styles-' . $page,
array(
$this,
'add_enqueue_style'
)
);
}
function add_plugin_sources() {
$data = get_plugin_data(__FILE__);
wp_register_script(
'av_script',
plugins_url('antivirus/js/script.js'),
array('jquery'),
$data['Version']
);
wp_register_style(
'av_style',
plugins_url('antivirus/css/style.css'),
array(),
$data['Version']
);
}
function add_enqueue_script() {
wp_enqueue_script('av_script');
wp_localize_script(
'av_script',
'av_settings',
array(
'nonce' => wp_create_nonce('av_ajax_nonce'),
'ajax'=> admin_url('admin-ajax.php'),
'theme'=> urlencode($this->get_theme_name()),
'msg_1'=> __('There is no virus', 'antivirus'),
'msg_2' => __('View line', 'antivirus'),
'msg_3' => __('Scan finished', 'antivirus')
)
);
}
function add_enqueue_style() {
wp_enqueue_style('av_style');
}
function is_min_wp($version) {
return version_compare(
$GLOBALS['wp_version'],
$version. 'alpha',
'>='
);
}
function get_current_theme() {
if ($themes = get_themes()) {
if ($theme = get_current_theme()) {
if (array_key_exists((string)$theme, $themes)) {
return $themes[$theme];
}
}
}
return false;
}
function get_theme_files() {
if (!$theme = $this->get_current_theme()) {
return false;
}
if (empty($theme['Template Files'])) {
return false;
}
return array_unique(
array_map(
create_function(
'$v',
'return str_replace(array(WP_CONTENT_DIR, "wp-content"), "", $v);'
),
$theme['Template Files']
)
);
}
function get_theme_name() {
if ($theme = $this->get_current_theme()) {
if (!empty($theme['Name'])) {
return $theme['Name'];
}
}
return false;
}
function get_white_list() {
return explode(
':',
$this->get_option('white_list')
);
}
function get_ajax_response() {
$this->check_user_can();
check_ajax_referer('av_ajax_nonce');
if (empty($_POST['_action_request'])) {
exit();
}
$values = array();
$output = '';
switch ($_POST['_action_request']) {
case 'get_theme_files':
$this->update_option(
'cronjob_alert',
0
);
$values = $this->get_theme_files();
break;
case 'check_theme_file':
if (!empty($_POST['_theme_file']) && $lines = $this->check_theme_file($_POST['_theme_file'])) {
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
if (!empty($_POST['_file_md5'])) {
$this->update_option(
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
header('Content-Type: plain/text');
echo sprintf(
'{data:%s, nonce:"%s"}',
$output,
$_POST['_ajax_nonce']
);
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
function get_preg_match() {
return '/(eval|base64_encode|base64_decode|create_function|exec|shell_exec|system|passthru|ob_get_contents|file|curl_init|readfile|fopen|fsockopen|pfsockopen|fclose|fread|include|include_once|require|require_once|file_put_contents)\s*?\(/';
}
function check_file_line($line = '', $num) {
$line = trim($line);
if (!$line || !$num) {
return false;
}
$results = array();
$output = array();
preg_match_all(
$this->get_preg_match(),
$line,
$matches
);
if ($matches[1]) {
$results = $matches[1];
}
preg_match_all(
'/[\'\"\$\\ \/]*?([a-zA-Z0-9]{' .strlen(base64_encode('sergej + swetlana = love.')). ',})/',
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
'/get_option\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
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
function check_theme_files() {
if (!$files = $this->get_theme_files()) {
return false;
}
$results = array();
foreach($files as $file) {
if ($result = $this->check_theme_file($file)) {
$results[$file] = $result;
}
}
if (!empty($results)) {
return $results;
}
return false;
}
function check_theme_file($file) {
if (!$file) {
return false;
}
if (!$content = $this->get_file_content($file)) {
return false;
}
$results = array();
foreach($content as $num => $line) {
if ($result = $this->check_file_line($line, $num)) {
$results[$num] = $result;
}
}
if (!empty($results)) {
return $results;
}
return false;
}
function check_permalink_structure() {
if ($structure = get_option('permalink_structure')) {
preg_match_all(
$this->get_preg_match(),
$structure,
$matches
);
if ($matches[1]) {
return $matches[1];
}
}
return false;
}
function is_current_page($page) {
switch($page) {
case 'home':
return (!empty($_REQUEST['page']) && $_REQUEST['page'] == $this->base_name);
case 'index':
case 'plugins':
return (!empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == sprintf('%s.php', $page));
default:
return false;
}
}
function check_user_can() {
if (current_user_can('manage_options') === false || current_user_can('edit_plugins') === false || !is_user_logged_in()) {
wp_die('You do not have permission to access!');
}
}
function show_version_notice() {
if ($this->is_min_wp('2.7')) {
return;
}
echo sprintf(
'<div class="error"><p><strong>%s</strong> %s</p></div>',
__('AntiVirus for WordPress', 'antivirus'),
__('requires at least WordPress 2.7', 'antivirus')
);
}
function show_dashboard_notice() {
if (!$this->get_option('cronjob_alert')) {
return;
}
echo sprintf(
'<div class="updated fade"><p><strong>%s:</strong> %s <a href="options-general.php?page=%s">%s</a></p></div>',
__('Suspicion on a virus', 'antivirus'),
__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
$this->base_name,
__('Manual scan', 'antivirus')
);
}
function show_plugin_info() {
$data = get_plugin_data(__FILE__);
echo sprintf(
'%s %s %s <a href="http://eBiene.de" target="_blank">Sergej M&uuml;ller</a> | <a href="http://twitter.com/wpSEO" target="_blank">%s</a> | <a href="http://www.wpSEO.%s/?utm_source=antivirus&utm_medium=plugin&utm_campaign=plugins" target="_blank">%s</a>',
__('AntiVirus for WordPress', 'antivirus'),
$data['Version'],
__('by', 'antivirus'),
__('Follow on Twitter', 'antivirus'),
(get_locale() == 'de_DE' ? 'de' : 'org'),
__('Learn about wpSEO', 'antivirus')
);
}
function show_admin_menu() {
if (!$this->is_min_wp('2.8')) {
$this->check_user_can();
}
if (!empty($_POST)) {
check_admin_referer('antivirus');
$options = array(
'cronjob_enable'=> (int)(!empty($_POST['av_cronjob_enable'])),
'notify_email'=> sanitize_email(@$_POST['av_notify_email'])
);
if (empty($options['cronjob_enable'])) {
$options['notify_email'] = '';
}
if ($options['cronjob_enable'] && !$this->get_option('cronjob_enable')) {
$this->init_scheduled_hook();
} else if (!$options['cronjob_enable'] && $this->get_option('cronjob_enable')) {
$this->clear_scheduled_hook();
}
$this->update_options($options); ?>
<div id="message" class="updated fade">
<p>
<strong>
<?php _e('Settings saved.') ?>
</strong>
</p>
</div>
<?php } ?>
<div class="wrap">
<div class="icon32"></div>
<h2>
AntiVirus
</h2>
<form method="post" action="">
<?php wp_nonce_field('antivirus') ?>
<div id="poststuff">
<div class="postbox">
<h3>
<?php _e('Completed scan', 'antivirus') ?>
</h3>
<div class="inside" id="av_completed">
<div class="output">
<div class="<?php echo ($this->check_permalink_structure() ? 'danger' : 'done') ?>"><?php _e('Permalink back door check', 'antivirus') ?> <a href="<?php _e('http://mashable.com/2009/09/05/wordpress-attack/', 'antivirus') ?>" target="_blank">Info</a></div>
</div>
<ul class="agenda">
<li>
<p></p>
<span>
<?php _e('All clear', 'antivirus') ?>
</span>
</li>
<li class="danger">
<p></p>
<span>
<?php _e('Danger', 'antivirus') ?>
</span>
</li>
</ul>
</div>
</div>
<div class="postbox">
<h3>
<?php _e('Manual scan', 'antivirus') ?>
</h3>
<div class="inside" id="av_manual">
<p>
<a href="#" class="button rbutton"><?php _e('Scan the theme templates now', 'antivirus') ?></a>
<span class="alert"></span>
</p>
<div class="output"></div>
</div>
</div>
<div class="postbox">
<h3>
<?php _e('Settings') ?>
</h3>
<div class="inside">
<table class="form-table">
<tr>
<td>
<label for="av_cronjob_enable">
<input type="checkbox" name="av_cronjob_enable" id="av_cronjob_enable" value="1" <?php checked($this->get_option('cronjob_enable'), 1) ?> />
<?php _e('Enable the daily antivirus scan', 'antivirus') ?>
<?php if ($this->get_option('cronjob_enable') && $this->get_option('cronjob_timestamp')) {
echo sprintf(
'&nbsp;(%s @ %s)',
__('Last check', 'antivirus'),
date_i18n('d.m.Y H:i:s', ($this->get_option('cronjob_timestamp') + get_option('gmt_offset') * 60))
);
} ?>
</label>
<span class="shift">
<?php _e('Alternate email address', 'antivirus') ?>:&nbsp;<input type="text" name="av_notify_email" value="<?php echo $this->get_option('notify_email') ?>" class="regular-text" />
</span>
</td>
</tr>
</table>
<p>
<input type="submit" name="av_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
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
new AntiVirus();