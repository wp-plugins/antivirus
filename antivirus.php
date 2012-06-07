<?php
/*
Plugin Name: AntiVirus
Text Domain: antivirus
Domain Path: /lang
Description: Security solution as a smart, effectively plugin to protect your blog against exploits and spam injections.
Author: Sergej M&uuml;ller
Author URI: http://www.wpSEO.org
Plugin URI: http://wpantivirus.com
Version: 1.1
*/


if ( !class_exists('WP') ) {
header('Status: 403 Forbidden');
header('HTTP/1.1 403 Forbidden');
exit();
}
class AntiVirus {
private static $base;
public static function init()
{
if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
return;
}
self::$base = plugin_basename(__FILE__);
if ( defined('DOING_CRON') ) {
add_action(
'antivirus_daily_cronjob',
array(
__CLASS__,
'exe_daily_cronjob'
)
);
} elseif ( is_admin() ) {
if (defined('DOING_AJAX')) {
add_action(
'wp_ajax_get_ajax_response',
array(
__CLASS__,
'get_ajax_response'
)
);
} else {
add_action(
'admin_menu',
array(
__CLASS__,
'init_admin_menu'
)
);
if ( self::is_current_page('home') ) {
add_action(
'admin_print_styles',
array(
__CLASS__,
'add_enqueue_style'
)
);
add_action(
'admin_print_scripts',
array(
__CLASS__,
'add_enqueue_script'
)
);
add_action(
'init',
array(
__CLASS__,
'load_plugin_lang'
)
);
} else if ( self::is_current_page('index') ) {
add_action(
'init',
array(
__CLASS__,
'load_plugin_lang'
)
);
add_action(
'admin_notices',
array(
__CLASS__,
'show_dashboard_notice'
)
);
} else if ( self::is_current_page('plugins') ) {
add_action(
'init',
array(
__CLASS__,
'load_plugin_lang'
)
);
add_action(
'deactivate_' .self::$base,
array(
__CLASS__,
'clear_scheduled_hook'
)
);
add_action(
'admin_notices',
array(
__CLASS__,
'show_version_notice'
)
);
add_filter(
'plugin_row_meta',
array(
__CLASS__,
'init_row_meta'
),
10,
2
);
add_filter(
'plugin_action_links_' .self::$base,
array(
__CLASS__,
'init_action_links'
)
);
}
}
}
}
public static function load_plugin_lang()
{
load_plugin_textdomain(
'antivirus',
false,
'antivirus/lang'
);
}
public static function init_action_links($data)
{
if ( !current_user_can('manage_options') ) {
return $data;
}
return array_merge(
$data,
array(
sprintf(
'<a href="%s">%s</a>',
add_query_arg(
array(
'page' => 'antivirus'
),
admin_url('options-general.php')
),
__('Settings')
)
)
);
}
public static function init_row_meta($data, $page)
{
if ( $page == self::$base ) {
$data = array_merge(
$data,
array(
sprintf(
'<a href="https://flattr.com/thing/58179/Sicherheit-in-WordPress-Das-erste-AntiVirus-Plugin-fur-WordPress" target="_blank">%s</a>',
esc_html__('Flattr plugin', 'antivirus')
),
sprintf(
'<a href="https://plus.google.com/110569673423509816572" target="_blank">%s</a>',
esc_html__('Follow on Google+', 'antivirus')
)
)
);
}
return $data;
}
public static function install()
{
add_option(
'antivirus',
array(),
'',
'no'
);
if ( self::get_option('cronjob_enable') ) {
self::init_scheduled_hook();
}
}
public static function uninstall()
{
global $wpdb;
delete_option('antivirus');
$wpdb->query("OPTIMIZE TABLE `" .$wpdb->options. "`");
}
private static function get_option($field)
{
if ( !$options = wp_cache_get('antivirus') ) {
$options = get_option('antivirus');
wp_cache_set(
'antivirus',
$options
);
}
return @$options[$field];
}
private static function update_option($field, $value)
{
self::update_options(
array(
$field => $value
)
);
}
private static function update_options($data)
{
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
private static function init_scheduled_hook()
{
if ( !wp_next_scheduled('antivirus_daily_cronjob') ) {
wp_schedule_event(
time(),
'daily',
'antivirus_daily_cronjob'
);
}
}
public static function clear_scheduled_hook()
{
if ( wp_next_scheduled('antivirus_daily_cronjob') ) {
wp_clear_scheduled_hook('antivirus_daily_cronjob');
}
}
public static function exe_daily_cronjob()
{
if ( !self::get_option('cronjob_enable') ) {
return;
}
self::update_option(
'cronjob_timestamp',
time()
);
if ( self::check_theme_files() or self::check_permalink_structure() ) {
self::load_plugin_lang();
$email = sanitize_email(self::get_option('notify_email'));
$email = ( (!empty($email) && is_email($email)) ? $email : get_bloginfo('admin_email') );
wp_mail(
$email,
sprintf(
'[%s] %s',
get_bloginfo('name'),
esc_html__('Suspicion on a virus', 'antivirus')
),
sprintf(
"%s\r\n%s\r\n\r\n\r\n%s\r\n%s\r\n",
esc_html__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
get_bloginfo('url'),
esc_html__('Notify message by AntiVirus for WordPress', 'antivirus'),
esc_html__('http://wpantivirus.com', 'antivirus')
)
);
self::update_option(
'cronjob_alert',
1
);
}
}
public static function init_admin_menu()
{
add_options_page(
'AntiVirus',
'<img src="' .plugins_url('antivirus/img/icon.png'). '" id="av_icon" alt="AntiVirus Icon" />AntiVirus',
'manage_options',
'antivirus',
array(
__CLASS__,
'show_admin_menu'
)
);
}
public static function add_enqueue_script()
{
$data = get_plugin_data(__FILE__);
wp_register_script(
'av_script',
plugins_url('js/script.js', __FILE__),
array('jquery'),
$data['Version']
);
wp_enqueue_script('av_script');
wp_localize_script(
'av_script',
'av_settings',
array(
'nonce' => wp_create_nonce('av_ajax_nonce'),
'ajax'=> admin_url('admin-ajax.php'),
'theme'=> urlencode(self::get_theme_name()),
'msg_1'=> esc_html__('There is no virus', 'antivirus'),
'msg_2' => esc_html__('View line', 'antivirus'),
'msg_3' => esc_html__('Scan finished', 'antivirus')
)
);
}
public static function add_enqueue_style()
{
$data = get_plugin_data(__FILE__);
wp_register_style(
'av_css',
plugins_url('css/style.css', __FILE__),
array(),
$data['Version']
);
wp_enqueue_style('av_css');
}
private static function is_min_wp($version)
{
return version_compare(
$GLOBALS['wp_version'],
$version. 'alpha',
'>='
);
}
private static function get_current_theme()
{
if ( $themes = get_themes() ) {
if ($theme = get_current_theme()) {
if (array_key_exists((string)$theme, $themes)) {
return $themes[$theme];
}
}
}
return false;
}
private static function get_theme_files()
{
if ( !$theme = self::get_current_theme() ) {
return false;
}
if ( empty($theme['Template Files']) ) {
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
private static function get_theme_name()
{
if ( $theme = self::get_current_theme() ) {
if (!empty($theme['Name'])) {
return $theme['Name'];
}
}
return false;
}
private static function get_white_list()
{
return explode(
':',
self::get_option('white_list')
);
}
public static function get_ajax_response()
{
check_ajax_referer('av_ajax_nonce');
if ( empty($_POST['_action_request']) ) {
exit();
}
$values = array();
$output = '';
switch ($_POST['_action_request']) {
case 'get_theme_files':
self::update_option(
'cronjob_alert',
0
);
$values = self::get_theme_files();
break;
case 'check_theme_file':
if ( !empty($_POST['_theme_file']) && $lines = self::check_theme_file($_POST['_theme_file']) ) {
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
if ( !empty($_POST['_file_md5']) ) {
self::update_option(
'white_list',
implode(
':',
array_unique(
array_merge(
self::get_white_list(),
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
private static function get_file_content($file)
{
return file(WP_CONTENT_DIR . $file);
}
public static function get_dotted_line($line, $tag, $max = 100)
{
if ( !$line or !$tag ) {
return false;
}
if ( strlen($tag) > $max ) {
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
private static function get_preg_match()
{
return '/(assert|file_get_contents|curl_exec|popen|proc_open|unserialize|eval|base64_encode|base64_decode|create_function|exec|shell_exec|system|passthru|ob_get_contents|file|curl_init|readfile|fopen|fsockopen|pfsockopen|fclose|fread|include|include_once|require|require_once|file_put_contents)\s*?\(/';
}
private static function check_file_line($line = '', $num)
{
$line = trim((string)$line);
if ( !$line or !isset($num) ) {
return false;
}
$results = array();
$output = array();
preg_match_all(
self::get_preg_match(),
$line,
$matches
);
if ( $matches[1] ) {
$results = $matches[1];
}
preg_match_all(
'/[\'\"\$\\ \/]*?([a-zA-Z0-9]{' .strlen(base64_encode('sergej + swetlana = love.')). ',})/',
$line,
$matches
);
if ( $matches[1] ) {
$results = array_merge($results, $matches[1]);
}
preg_match_all(
'/<\s*?(frame)/',
$line,
$matches
);
if ( $matches[1] ) {
$results = array_merge($results, $matches[1]);
}
preg_match(
'/get_option\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
$line,
$matches
);
if ( $matches && $matches[1] && self::check_file_line(get_option($matches[1]), $num) ) {
array_push($results, 'get_option');
}
if ( $results ) {
$results = array_unique($results);
$md5 = self::get_white_list();
foreach ($results as $tag) {
$string = str_replace(
$tag,
'@span@' .$tag. '@/span@',
self::get_dotted_line($line, $tag)
);
if (!in_array(md5($num . $string), $md5)) {
$output[] = $string;
}
}
return $output;
}
return false;
}
private static function check_theme_files()
{
if ( !$files = self::get_theme_files() ) {
return false;
}
$results = array();
foreach($files as $file) {
if ($result = self::check_theme_file($file)) {
$results[$file] = $result;
}
}
if ( !empty($results) ) {
return $results;
}
return false;
}
private static function check_theme_file($file)
{
if ( !$file ) {
return false;
}
if ( !$content = self::get_file_content($file) ) {
return false;
}
$results = array();
foreach($content as $num => $line) {
if ($result = self::check_file_line($line, $num)) {
$results[$num] = $result;
}
}
if ( !empty($results) ) {
return $results;
}
return false;
}
private static function check_permalink_structure()
{
if ( $structure = get_option('permalink_structure') ) {
preg_match_all(
self::get_preg_match(),
$structure,
$matches
);
if ( $matches[1] ) {
return $matches[1];
}
}
return false;
}
private static function is_current_page($page)
{
switch($page) {
case 'home':
return ( !empty($_REQUEST['page']) && $_REQUEST['page'] == 'antivirus' );
case 'index':
case 'plugins':
return (!empty($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == sprintf('%s.php', $page));
default:
return false;
}
}
public static function show_version_notice()
{
if ( self::is_min_wp('2.8') ) {
return;
}
echo sprintf(
'<div class="error"><p><strong>%s</strong> %s</p></div>',
esc_html__('AntiVirus for WordPress', 'antivirus'),
esc_html__('requires at least WordPress 2.8', 'antivirus')
);
}
public static function show_dashboard_notice() {
if ( !self::get_option('cronjob_alert') ) {
return;
}
echo sprintf(
'<div class="updated fade"><p><strong>%s:</strong> %s <a href="options-general.php?page=%s">%s</a></p></div>',
esc_html__('Suspicion on a virus', 'antivirus'),
esc_html__('The daily antivirus scan of your blog suggests alarm.', 'antivirus'),
self::$base,
esc_html__('Manual scan', 'antivirus')
);
}
public static function show_admin_menu() {
if ( !empty($_POST) ) {
check_admin_referer('antivirus');
$options = array(
'cronjob_enable' => (int)(!empty($_POST['av_cronjob_enable'])),
'notify_email'=> sanitize_email(@$_POST['av_notify_email'])
);
if (empty($options['cronjob_enable'])) {
$options['notify_email'] = '';
}
if ($options['cronjob_enable'] && !self::get_option('cronjob_enable')) {
self::init_scheduled_hook();
} else if (!$options['cronjob_enable'] && self::get_option('cronjob_enable')) {
self::clear_scheduled_hook();
}
self::update_options($options); ?>
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
<?php esc_html_e('Completed scan', 'antivirus') ?>
</h3>
<div class="inside" id="av_completed">
<div class="output">
<div class="<?php echo (self::check_permalink_structure() ? 'danger' : 'done') ?>"><?php esc_html_e('Permalink back door check', 'antivirus') ?> <a href="<?php esc_html_e('http://mashable.com/2009/09/05/wordpress-attack/', 'antivirus') ?>" target="_blank">Info</a></div>
</div>
<ul class="agenda">
<li>
<p></p>
<span>
<?php esc_html_e('All clear', 'antivirus') ?>
</span>
</li>
<li class="danger">
<p></p>
<span>
<?php esc_html_e('Danger', 'antivirus') ?>
</span>
</li>
</ul>
</div>
</div>
<div class="postbox">
<h3>
<?php esc_html_e('Manual scan', 'antivirus') ?>
</h3>
<div class="inside" id="av_manual">
<p>
<a href="#" class="button rbutton"><?php esc_html_e('Scan the theme templates now', 'antivirus') ?></a>
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
<input type="checkbox" name="av_cronjob_enable" id="av_cronjob_enable" value="1" <?php checked(self::get_option('cronjob_enable'), 1) ?> />
<?php esc_html_e('Enable the daily antivirus scan', 'antivirus') ?>
<?php if (self::get_option('cronjob_enable') && self::get_option('cronjob_timestamp')) {
echo sprintf(
'&nbsp;(%s @ %s)',
esc_html__('Last check', 'antivirus'),
date_i18n('d.m.Y H:i:s', (self::get_option('cronjob_timestamp') + get_option('gmt_offset') * 60))
);
} ?>
</label>
<span class="shift">
<?php esc_html_e('Alternate email address', 'antivirus') ?>:&nbsp;<input type="text" name="av_notify_email" value="<?php esc_attr_e(self::get_option('notify_email')) ?>" class="regular-text" />
</span>
</td>
</tr>
</table>
<p>
<input type="submit" name="av_submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>
</div>
</div>
</div>
</form>
</div>
<?php }
}
add_action(
'plugins_loaded',
array(
'AntiVirus',
'init'
),
99
);
register_activation_hook(
__FILE__,
array(
'AntiVirus',
'install'
)
);
register_uninstall_hook(
__FILE__,
array(
'AntiVirus',
'uninstall'
)
);