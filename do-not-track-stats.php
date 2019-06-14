<?php

/**
 * @author Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 * @since 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Do Not Track Stats
 * Plugin URI:        https://wordpress.org/plugins/do-not-track-stats/
 * Description:       Easily obtain reliable statistics on the use of the "Do Not Track" policy by your visitors.
 * Version:           1.1.5
 * Author:            Pierre Lannoy
 * Author URI:        https://pierre.lannoy.fr
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       do-not-track-stats
 */

// If this file is called directly, abort.
if (!defined( 'WPINC'))  {
    die;
}


/**
 * Main class of the plugin.
 *
 * @since 1.0.0
 */
class DoNotTrackStats {

    private static $full_name = 'Do Not Track Stats';
    private static $menu_name = 'DNT Stats';
    private static $table_name = 'dnts_stats';
    private static $transient_expiry = 7200;
    private static $plugin_dir;
    private static $plugin_url;

    private static $dnts_activated_default = 1;
    private static $dnts_mode_default = 0;
    private static $dnts_exclude_default = '/wp-admin/,/feed/,wp-cron.php';
    private static $dnts_agent_default = 'addthis,adscanner,alwaysonline,analyze,anyevent,apache-httpclient,appengine,archiver,aspseek,axios,baidu,biglotron,binlar,blackboard,bot,brandverify,bubing,buck,capsulink,check_http,cloudfront,coccoc,crawler,curl,dataprovider,dareboost,daum,digg,disqus,drupact,embedly,ezid,facebook,feedvalidator,feedwatch,fetch,femtosearch,fever,flamingo,flipboard,findlinks,findthatfile,go-http-client,google-structured,google-physicalweb,grab,grub,google,heritrix,http_get,httpurl,httrack,jetty,ichiro,infosearch,iskanie,libwww-perl,linkfinder,ltx71,mastodon,meltwater,miniflux,muckrack,netvibes,newspaper,newshare,ning,nutch,nuzzel,omgili,outbrain,page2rss,parser,pcore,pingdom,proximic,python-requests,scoutjet,scrap,search,sentry,seokick,seoscanner,simpy,siteexplorer,siteimprove,slurp,snacktory,socialrank,sonic,summify,surveyagent,spider,theoldreader,thinklab,traackr,tracemyfile,twingly,twurly,trove,upflow,uripreview,utorrent,w3c,webdata,webfilter,wesee,wget,whatsapp,xenu,xrawler,yak,zabbix';

    public static function init() {
        static $instance = null;
        if (!$instance) {
            self::$plugin_dir = plugin_dir_path(__FILE__);
            self::$plugin_url = plugin_dir_url(__FILE__);
            $instance = new DoNotTrackStats;
        }
        return $instance;
    }

    private $rq_excluded;
    private $rq_included;
    private $dnt_unset;
    private $dnt_0;
    private $dnt_1;

    /**
     * Initializes the instance.
     *
     * @since 1.0.0
     */
    protected function __construct() {
        load_plugin_textdomain('do-not-track-stats');
        register_activation_hook(__FILE__, array($this,'plugin_activate'));
        register_deactivation_hook(__FILE__, array($this,'plugin_deactivate'));
        add_action('init', array($this, 'get_dnt'));
        add_action('shutdown', array($this, 'store_dnt'));
        add_action('rotate_stats', array($this, 'rotate_stats'));
        if (!wp_next_scheduled('rotate_stats')) {
            wp_schedule_event(time(), 'daily', 'rotate_stats');
        }
        add_action('admin_menu', array($this,'init_admin_menus'));
        add_action('admin_init', array($this,'init_settings_sections'));

        add_filter('plugin_action_links_' . plugin_basename( __FILE__), array($this, 'add_plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
        add_shortcode( 'dnts-breakdown', array($this, 'cs_breakdown'));
        add_shortcode( 'dnts-timeseries', array($this, 'cs_timeseries'));
        add_shortcode( 'dnts-table', array($this, 'cs_table'));
    }

    /**
     * Set up the plugin environment upon activation.
     *
     * @since 1.0.0
     */
    public function plugin_activate() {
        // Create main table
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . self::$table_name;
        $sql .= " (`timestamp` date NOT NULL DEFAULT '0000-00-00',";
        $sql .= " `rq_excluded` BIGINT(12) UNSIGNED NOT NULL DEFAULT 0,";
        $sql .= " `rq_included` BIGINT(12) UNSIGNED NOT NULL DEFAULT 0,";
        $sql .= " `dnt_unset` BIGINT(12) UNSIGNED NOT NULL DEFAULT 0,";
        $sql .= " `dnt_0` BIGINT(12) UNSIGNED NOT NULL DEFAULT 0,";
        $sql .= " `dnt_1` BIGINT(12) UNSIGNED NOT NULL DEFAULT 0,";
        $sql .= " PRIMARY KEY (`timestamp`)";
        $sql .= ") $charset_collate;";
        $wpdb->query($sql);
        // Initialize options
        update_option('dnts_activated', self::$dnts_activated_default);
        update_option('dnts_mode', self::$dnts_mode_default);
        update_option('dnts_exclude', self::$dnts_exclude_default);
        update_option('dnts_agent', self::$dnts_agent_default);
    }

    /**
     * Cleans the plugin environment upon deactivation.
     *
     * @since 1.0.0
     */
    public function plugin_deactivate() {
        // Remove options
        delete_option('dnts_activated');
        delete_option('dnts_mode');
        delete_option('dnts_exclude');
        delete_option('dnts_agent');
        // Remove main table
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS " . $wpdb->prefix . self::$table_name;
        $wpdb->query($sql);
        // Finalizing
        remove_action('shutdown', array($this, 'store_dnt'));
        $this->flush_transient(false);
    }

    /**
     * Flush all plugin transients.
     *
     * @param bool $expired Optional. Delete only expired transients.
     * @return integer Count of deleted transients.
     * @since 1.0.0
     *
     */
    private function flush_transient($expired=true) {
        global $wpdb;
        $result = 0;
        if ($expired) {
            $delete = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dnts%' AND option_value < ".time().";");
        }
        else {
            $delete = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dnts%';");
        }
        foreach($delete as $transient) {
            $key = str_replace('_transient_timeout_', '', $transient);
            if (delete_transient($key)) {
                $result += 1;
            }
        }
        return $result;
    }

    /**
     * Add links in the "Plugin" column on the Plugins page.
     *
     * @param array $links List of links to print in the "Plugin" column on the Plugins page.
     * @return array Extended list of links to print in the "Plugin" column on the Plugins page.
     * @since 1.0.0
     */
    public function add_plugin_action_links(array $links) {
        $links[] = '<a href="' . dnts_get_settings_page_url('dnts-settings') . '">' . __('Settings', 'do-not-track-stats') . '</a>';
        $links[] = '<a href="' . dnts_get_statistics_page_url('dnts-statistics') . '">' . __('Statistics', 'do-not-track-stats') . '</a>';
        return $links;
    }
    /**
     * Add links in the "Description" column on the Plugins page.
     *
     * @param array  $links List of links to print in the "Description" column on the Plugins page.
     * @param string $file  Name of the plugin.
     * @return array Extended list of links to print in the "Description" column on the Plugins page.
     * @since 1.0.0
     */
    public function add_plugin_row_meta( array $links, $file ) {
        if ( plugin_basename( __FILE__) === $file ) {
            $links[] = '<a href="https://wordpress.org/support/plugin/do-not-track-stats">' . __('Support', 'do-not-track-stats') . '</a>';
            $links[] = '<a href="https://support.laquadrature.net/" title="' . esc_attr__( 'With your donation, support an advocacy group defending the rights and freedoms of citizens on the Internet.', 'do-not-track-stats') . '"><strong>' . __('Donate', 'do-not-track-stats') . '</strong></a>';
        }
        return $links;
    }

    /**
     * Set the items in the settings & tools menus.
     *
     * @since 1.0.0
     */
    public function init_admin_menus() {
        add_submenu_page('options-general.php', self::$full_name, self::$menu_name, apply_filters('dnts_manage_options_capability', 'manage_options'), 'dnts-settings', array($this, 'get_settings_page'));
        add_submenu_page('tools.php', self::$full_name, self::$menu_name, apply_filters('dnts_manage_options_capability', 'manage_options'), 'dnts-statistics', array($this, 'get_statistics_page'));
    }

    /**
     * Initializes settings sections.
     *
     * @since 1.0.0
     */
    public function init_settings_sections() {
        add_settings_section('dnts_misc_section', null, array($this, 'misc_section_callback'), 'dnts_misc');
    }

    /**
     * Set misc settings fields.
     *
     * @since 1.0.0
     */
    public function misc_section_callback() {
        add_settings_field('dnts_misc_activated', __('Analyze', 'do-not-track-stats'),
            array($this, 'dnts_misc_activated_callback'), 'dnts_misc', 'dnts_misc_section', array());
        register_setting('dnts_misc', 'dnts_misc_activated');
        add_settings_field('dnts_misc_mode', __('Sampling', 'do-not-track-stats'),
            array($this, 'dnts_misc_mode_callback'), 'dnts_misc', 'dnts_misc_section', array());
        register_setting('dnts_misc', 'dnts_misc_mode');
        add_settings_field('dnts_misc_exclude', __('Excluded URIs', 'do-not-track-stats'),
            array($this, 'dnts_misc_exclude_callback'), 'dnts_misc', 'dnts_misc_section', array());
        register_setting('dnts_misc', 'dnts_misc_exclude');
        add_settings_field('dnts_misc_agent', __('Excluded user agents', 'do-not-track-stats'),
            array($this, 'dnts_misc_agent_callback'), 'dnts_misc', 'dnts_misc_section', array());
        register_setting('dnts_misc', 'dnts_misc_agent');
    }

    /**
     * Echoes a check box to activate the analyze.
     *
     * @since 1.0.0
     */
    public function dnts_misc_activated_callback($args) {
        echo $this->field_checkbox(__('Activated', 'do-not-track-stats'), 'dnts_misc_activated', (bool)get_option('dnts_activated', self::$dnts_activated_default), __('Enables the analyze of "Do Not Track" fields in HTTP headers. If unchecked, the plugin will do nothing.', 'do-not-track-stats'));
    }

    /**
     * Echoes a select box with collection modes.
     *
     * @since 1.0.0
     */
    public function dnts_misc_mode_callback($args) {
        $select = array();
        $select[] = array(0, __('All requests', 'do-not-track-stats'));
        foreach (array(40, 20, 10, 5) as $i) {
            $select[] = array($i, sprintf(__('%s%% weighted sampling', 'do-not-track-stats'), $i));
        }
        echo $this->field_select($select, get_option('dnts_mode', self::$dnts_mode_default),'dnts_misc_mode', __('How many requests are to be analyzed. If your site has high traffic, 5% or 10% is sufficient.', 'do-not-track-stats'));
    }

    /**
     * Echoes a text input to set excluded URIs.
     *
     * @since 1.0.0
     */
    public function dnts_misc_exclude_callback($args) {
        echo $this->field_input_text('dnts_misc_exclude', get_option('dnts_exclude', self::$dnts_exclude_default), __('Excludes (from the analyzed requests) the URIs containing these strings. Comma separated list.', 'do-not-track-stats'));
    }

    /**
     * Echoes a text input to set excluded user agents.
     *
     * @since 1.0.0
     */
    public function dnts_misc_agent_callback($args) {
        echo $this->field_input_text('dnts_misc_agent', get_option('dnts_agent', self::$dnts_agent_default), __('Excludes (from the analyzed requests) the user agents containing these strings. Comma separated list.', 'do-not-track-stats'));
    }

    /**
     * Get a checkbox form field.
     *
     * @param string $text The text of the checkbox.
     * @param string $id The id (and the name) of the control.
     * @param boolean $checked Is the checkbox on?
     * @param string $description Optional. A description to display.
     * @return string The HTML string ready to print.
     * @since 1.0.0
     */
    protected function field_checkbox($text, $id, $checked=false, $description=null) {
        $html = '<fieldset><label><input name="' . $id . '" type="checkbox" value="1"' . ($checked ? ' checked="checked"' : '') . '/>' . $text . '</label></fieldset>';
        if (isset($description)) {
            $html .= '<p class="description">' . $description . '</p>';
        }
        return $html;
    }

    /**
     * Get a select form field.
     *
     * @param array $list The list of options.
     * @param int|string $value The selected value.
     * @param string $id The id (and the name) of the control.
     * @param string $description Optional. A description to display.
     * @return string The HTML string ready to print.
     * @since 1.0.0
     */
    protected function field_select($list, $value, $id, $description=null) {
        $html = '';
        foreach ($list as $val) {
            $html .= '<option value="' . $val[0] . '"' . ( $val[0] == $value ? ' selected="selected"' : '') . '>' . $val[1] . '</option>';
        }
        $html = '<select name="' . $id . '" id="' . $id . '">' . $html . '</select>';
        if (isset($description)) {
            $html .= '<p class="description">' . $description . '</p>';
        }
        return $html;
    }

    /**
     * Get a text form field.
     *
     * @param string $id The id (and the name) of the control.
     * @param string $value The string to put in the text field.
     * @param string $description Optional. A description to display.
     * @return string The HTML string ready to print.
     * @since 1.0.0
     */
    protected function field_input_text($id, $value='', $description=null) {
        $html = '<input name="' . $id . '" type="text" id="' . $id . '" value="' . $value . '" style="width:100%;"/>';
        if (isset($description)) {
            $html .= '<p class="description">' . $description . '</p>';
        }
        return $html;
    }

    /**
     * Get the content of the settings page.
     *
     * @since 1.0.0
     */
    public function get_settings_page() {
        if (!empty($_POST)) {
            if (array_key_exists('_wpnonce', $_POST)) {
                if (wp_verify_nonce($_POST['_wpnonce'], 'dnts-settings')) {
                    if (array_key_exists('submit', $_POST)) {
                        update_option('dnts_activated', (array_key_exists('dnts_misc_activated', $_POST) ? 1 : 0));
                        update_option('dnts_mode', (array_key_exists('dnts_misc_mode', $_POST) ? $_POST['dnts_misc_mode'] : self::$dnts_mode_default));
                        update_option('dnts_exclude', (array_key_exists('dnts_misc_exclude', $_POST) ? trim(str_replace(' ', '', $_POST['dnts_misc_exclude'])) : self::$dnts_exclude_default));
                        update_option('dnts_agent', (array_key_exists('dnts_misc_agent', $_POST) ? trim(str_replace(' ', '', $_POST['dnts_misc_agent'])) : self::$dnts_agent_default));
                        $message = __('Settings have been updated.', 'do-not-track-stats');
                        add_settings_error('dnts_no_error', 0, $message, 'updated');
                    }
                    elseif (array_key_exists('reset', $_POST)) {
                        update_option('dnts_activated', self::$dnts_activated_default);
                        update_option('dnts_mode', self::$dnts_mode_default);
                        update_option('dnts_exclude', self::$dnts_exclude_default);
                        update_option('dnts_agent', self::$dnts_agent_default);
                        $message = __('Settings have been reset to defaults.', 'do-not-track-stats');
                        add_settings_error('dnts_no_error', 0, $message, 'updated');
                    }
                }
                else {
                    $message = __('Settings have not been updated. Please try again.', 'do-not-track-stats');
                    add_settings_error('dnts_nonce_error', 2, $message, 'error');
                }
            }
            else {
                $message = __('Settings have not been updated. Please try again.', 'do-not-track-stats');
                add_settings_error('dnts_nonce_error', 3, $message, 'error');
            }
        }
        include(self::$plugin_dir.'partials/settings.php');
    }

    /**
     * Get the content of the statistics page.
     *
     * @since 1.0.0
     */
    public function get_statistics_page() {
        if (!($action = filter_input(INPUT_GET, 'action'))) {
            $action = filter_input(INPUT_POST, 'action');
        }
        if ($action === 'reset-cache') {
            $this->flush_transient(false);
            $message = __('Data has been refreshed.', 'do-not-track-stats');
            add_settings_error('dnts_no_error', 0, $message, 'updated');
        }
        include(self::$plugin_dir.'partials/statistics.php');
    }

    /**
     * Remove old stats.
     *
     * @since 1.0.0
     */
    public function rotate_stats() {
        $this->flush_transient();
        global $wpdb;
        $sql = "DELETE FROM " . $wpdb->prefix . self::$table_name . " WHERE (`timestamp` < NOW() - INTERVAL 1 YEAR);";
        return $wpdb->query($sql);
    }

    /**
     * Should we check header?
     *
     * @return boolean True if it's ok to verify dnt header, false otherwise.
     * @since 1.0.0
     */
    private function is_it_ok_to_get() {
        $result = false;
        if ((bool)get_option('dnts_activated', self::$dnts_activated_default)) {
            $mode = (int)get_option('dnts_mode', self::$dnts_mode_default);
            $seconds = (int)date('s');
            $result = ($mode === 0 || $seconds < $mode * 0.6);
        }
        return $result;
    }

    /**
     * Get all request headers, regardless of the server type.
     *
     * @return array An array containing "normalized" headers.
     * @since 1.0.0
     */
    private function get_all_headers() {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
        else {
            return getallheaders();
        }
    }

    /**
     * Computes DNT values based on the request headers.
     *
     * @since 1.0.0
     */
    public function get_dnt() {
        $this->rq_excluded = 0;
        $this->rq_included = 0;
        $this->dnt_unset = 0;
        $this->dnt_0 = 0;
        $this->dnt_1 = 0;
        if ($this->is_it_ok_to_get()) {
            $headers = $this->get_all_headers();
            $mode = (int)get_option('dnts_mode', self::$dnts_mode_default);
            $value = 1;
            if ($mode !== 0) {
                $value = 100 / $mode;
            }
            foreach (array('X-Do-Not-Track', 'Dnt') as $header) {
                foreach (array($header, strtolower($header), strtoupper($header)) as $field) {
                    if (array_key_exists($field, $headers)) {
                        if ((int) $headers[$field] === 0) {
                            $this->dnt_0 = $value;
                        }
                        if ((int) $headers[$field] === 1) {
                            $this->dnt_1 = $value;
                        }
                    }
                }
            }
            if ($this->dnt_0 + $this->dnt_1 === 0) {
                $this->dnt_unset = $value ;
            }
            // Filters ajax requests
            if ($this->dnt_unset + $this->dnt_0 + $this->dnt_1 !== 0) {
                if (array_key_exists('X-Requested-With', $headers)) {
                    if ($headers['X-Requested-With'] === 'XMLHttpRequest') {
                        $this->dnt_unset = 0;
                        $this->dnt_0 = 0;
                        $this->dnt_1 = 0;
                    }
                }
            }
            // Filters excluded URIs
            if ($this->dnt_unset + $this->dnt_0 + $this->dnt_1 !== 0) {
                if (get_option('dnts_exclude', self::$dnts_exclude_default) !== '') {
                    if (array_key_exists('REQUEST_URI', $_SERVER)) {
                        $uri = strtolower($_SERVER['REQUEST_URI']);
                    } else {
                        $uri = '/';
                    }
                    foreach (explode(',', get_option('dnts_exclude', self::$dnts_exclude_default)) as $exclusion) {
                        if (strlen($exclusion) > 0) {
                            if (strpos($uri, strtolower($exclusion)) !== false) {
                                $this->dnt_unset = 0;
                                $this->dnt_0 = 0;
                                $this->dnt_1 = 0;
                                break;
                            }
                        }
                    }
                }
            }
            // Filters excluded user agents
            if ($this->dnt_unset + $this->dnt_0 + $this->dnt_1 !== 0) {
                if (get_option('dnts_agent', self::$dnts_agent_default) !== '') {
                    if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
                        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
                    } else {
                        $user_agent = 'none';
                    }
                    foreach (explode(',', get_option('dnts_agent', self::$dnts_agent_default)) as $exclusion) {
                        if (strlen($exclusion) > 0) {
                            if (strpos($user_agent, strtolower($exclusion)) !== false) {
                                $this->dnt_unset = 0;
                                $this->dnt_0 = 0;
                                $this->dnt_1 = 0;
                                break;
                            }
                        }
                    }
                }
            }
            // Set included and excluded counters
            if ($this->dnt_unset + $this->dnt_0 + $this->dnt_1 !== 0) {
                $this->rq_included = $this->dnt_unset + $this->dnt_0 + $this->dnt_1;
                $this->rq_excluded = 0;
                if (!defined('DO_NOT_TRACK_STATUS')) {
                    if ($this->dnt_unset !== 0) {
                        define('DO_NOT_TRACK_STATUS', 'included-unset');
                    }
                    elseif ($this->dnt_0 !== 0) {
                        define('DO_NOT_TRACK_STATUS', 'included-consent');
                    }
                    elseif ($this->dnt_1 !== 0) {
                        define('DO_NOT_TRACK_STATUS', 'included-opposition');
                    }
                }
            }
            else {
                $this->rq_included = 0;
                $this->rq_excluded = $value;
                if (!defined('DO_NOT_TRACK_STATUS')) {
                    define('DO_NOT_TRACK_STATUS', 'excluded');
                }
            }
        }
        else {
            if (!defined('DO_NOT_TRACK_STATUS')) {
                define('DO_NOT_TRACK_STATUS', 'unchecked');
            }
        }
    }

    /**
     * Stores DNT values if needed.
     *
     * @since 1.0.0
     */
    public function store_dnt() {
        if (isset($this->rq_excluded, $this->rq_included, $this->dnt_unset, $this->dnt_0, $this->dnt_1)) {
            global $wpdb;
            if ($this->rq_included !== 0) {
                $sql = "INSERT INTO " . $wpdb->prefix . self::$table_name . " ";
                $sql .= "(`timestamp`, `rq_included`, `dnt_unset`, `dnt_0`, `dnt_1`) ";
                $sql .= "VALUES ('" . current_time('Y-m-d') . "', " . $this->rq_included . ", " . $this->dnt_unset . ", " . $this->dnt_0 . ", " . $this->dnt_1 . ") ";
                $sql .= "ON DUPLICATE KEY UPDATE rq_included=rq_included+" . $this->rq_included . ", dnt_unset=dnt_unset+" . $this->dnt_unset . ", dnt_0=dnt_0+" . $this->dnt_0 . ", dnt_1=dnt_1+" . $this->dnt_1 . ";";
            }
            else {
                $sql = "INSERT INTO " . $wpdb->prefix . self::$table_name . " ";
                $sql .= "(`timestamp`, `rq_excluded`) ";
                $sql .= "VALUES ('" . current_time('Y-m-d') . "', " . $this->rq_excluded . ") ";
                $sql .= "ON DUPLICATE KEY UPDATE rq_excluded=rq_excluded+" . $this->rq_excluded . ";";
            }
            $wpdb->query($sql);
        }
    }

    /**
     * Get the date range based on attributes.
     *
     * @param array $attributes The parameters of the shortcode.
     * @return boolean|array The range type, start and end dates. False if it's a malformed shortcode.
     * @since 1.0.0
     */
    private function get_date_range($attributes) {
        $result = false;
        $_attributes = shortcode_atts( array('range' => 'all'), $attributes );
        $range = trim(strtolower($_attributes['range']));
        if ($range === 'all') {
            $result = array();
            $result['range'] = 'all';
            $result['should_be_cached'] = true;
            $result['start'] = date ('Y-m-d', current_time('timestamp') - (60*60*24*364));
            $result['end'] = current_time('Y-m-d');
        }
        if (strpos($range, '-') !== false) {
            $items = explode('-', $range);
            if (count($items) === 2) {
                $slide = (integer)$items[1];
                if (($items[0] === 'day' && $slide >=0 && $slide < 365) ||
                    ($items[0] === 'month' && $slide >=0 && $slide < 12)) {
                    $result = array();
                    $result['range'] = $items[0];
                    if ($items[0] === 'day') {
                        $result['start'] = date ('Y-m-d', current_time('timestamp') - ($slide * 86400));
                        $result['end'] = $result['start'];
                        $result['should_be_cached'] = ($slide !== 0);
                    }
                    if ($items[0] === 'month') {
                        $year = (integer)current_time('Y');
                        $month = (integer)current_time('m') -$slide;
                        while ($month > 12) {
                            $month -= 12;
                            $year -= 1;
                        }
                        while ($month < 0) {
                            $month += 12;
                            $year += 1;
                        }
                        $start = new \DateTime('now');
                        $start->setDate($year, $month, 1);
                        $end = new \DateTime('now');
                        $end->setDate($year, $month, $start->format('t'));
                        $result['start'] = $start->format('Y-m-d');
                        $result['end'] = $end->format('Y-m-d');
                        $result['should_be_cached'] = true;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get a breakdown in json.
     *
     * @param string $start The start date.
     * @param string $end The end date.
     * @return string The json encoded breakdown, ready to use.
     * @since 1.0.0
     */
    private function get_breakdown($start, $end) {
        global $wpdb;
        $sql = "SELECT CASE WHEN s.total=0 THEN 0 ELSE s.sum_unset * 100 / s.total END AS percent_unset, " .
                      "CASE WHEN s.total=0 THEN 0 ELSE s.sum_0 * 100 / s.total END AS percent_0, " .
                      "CASE WHEN s.total=0 THEN 0 ELSE s.sum_1 * 100 / s.total END AS percent_1 " .
               "FROM (SELECT SUM(dnt_unset) + SUM(dnt_0) + SUM(dnt_1) AS total, " .
                            "SUM(dnt_unset) AS sum_unset, ".
                            "SUM(dnt_0) AS sum_0, " .
                            "SUM(dnt_1) AS sum_1 " .
                     "FROM " . $wpdb->prefix . self::$table_name . " " .
                     "WHERE (`timestamp` <= '" . $end . "' AND `timestamp` >= '" . $start . "')) s;";
        $percentages = $wpdb->get_results($sql, ARRAY_A);
        $labels = array();
        $series = array();
        if (count($percentages) === 1) {
            $values = $percentages[0];
            if (array_key_exists('percent_unset', $values) && isset($values['percent_unset']) && $values['percent_unset'] > 0) {
                $txt = __('unset', 'do-not-track-stats');
                $labels[] = $txt;
                $series[] = array("meta" => ucwords($txt), 'value' => round($values['percent_unset'], 2));
            }
            if (array_key_exists('percent_1', $values) && isset($values['percent_1']) && $values['percent_1'] > 0) {
                $txt = __('opposition', 'do-not-track-stats');
                $labels[] = $txt;
                $series[] = array("meta" => ucwords($txt), 'value' => round($values['percent_1'], 2));
            }
            if (array_key_exists('percent_0', $values) && isset($values['percent_0']) && $values['percent_0'] > 0) {
                $txt = __('consent', 'do-not-track-stats');
                $labels[] = $txt;
                $series[] = array("meta" => ucwords($txt), 'value' => round($values['percent_0'], 2));
            }
        }
        return json_encode(array('labels' => $labels, 'series' => $series));
    }

    /**
     * Get time series in json.
     *
     * @param string $start The start date.
     * @param string $end The end date.
     * @param string $exclude Optional. Excludes a serie.
     * @return string The json encoded time series, ready to use.
     * @since 1.0.0
     */
    private function get_timeseries($start, $end, $exclude='none') {
        global $wpdb;
        $sql = "SELECT `timestamp`, `dnt_unset`, `dnt_0`, `dnt_1` " .
               "FROM " . $wpdb->prefix . self::$table_name . " " .
               "WHERE (`timestamp` <= '" . $end . "' AND `timestamp` >= '" . $start . "') " .
               "ORDER BY `timestamp` ASC;";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $series = array();
        $serie_unset = array();
        $serie_0 = array();
        $serie_1 = array();
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $meta_unset = ucwords(__('unset', 'do-not-track-stats')) . ' (' . $row['timestamp'] .')';
                $meta_1 = ucwords(__('opposition', 'do-not-track-stats')) . ' (' . $row['timestamp'] .')';
                $meta_0 = ucwords(__('consent', 'do-not-track-stats')) . ' (' . $row['timestamp'] .')';
                $ts = 'new Date(' . (string)strtotime($row['timestamp']) . '000)';
                $total = $row['dnt_unset'] + $row['dnt_0'] + $row['dnt_1'];
                if ($total > 0) {
                    $serie_unset[] = array('meta' => $meta_unset, 'x' => $ts, 'y' => round($row['dnt_unset'] * 100 / $total, 2));
                    $serie_1[] = array('meta' => $meta_1, 'x' => $ts, 'y' => round($row['dnt_1'] * 100 / $total, 2));
                    $serie_0[] = array('meta' => $meta_0, 'x' => $ts, 'y' => round($row['dnt_0'] * 100 / $total, 2));
                }
                else {
                    $serie_unset[] = array('meta' => $meta_unset, 'x' => $ts, 'y' => 0);
                    $serie_1[] = array('meta' => $meta_1, 'x' => $ts, 'y' => 0);
                    $serie_0[] = array('meta' => $meta_0, 'x' => $ts, 'y' => 0);
                }

            }
            if ($exclude === 'unset') {
                $serie_unset = array();
            }
            if ($exclude === 'opposition') {
                $serie_1 = array();
            }
            if ($exclude === 'consent') {
                $serie_0 = array();
            }
            $series[] = array('name' => $meta_unset, 'data' => $serie_unset);
            $series[] = array('name' => $meta_1, 'data' => $serie_1);
            $series[] = array('name' => $meta_0, 'data' => $serie_0);
        }
        $result = json_encode(array('series' => $series));
        $result = str_replace('"x":"new', '"x":new', $result);
        $result = str_replace(')","y"', '),"y"', $result);
        return $result;
    }

    /**
     * Get raw data in an array.
     *
     * @param string $start The start date.
     * @param string $end The end date.
     * @return array The array containing teh raw data.
     * @since 1.0.0
     */
    private function get_rawdata($start, $end) {
        global $wpdb;
        $sql = "SELECT * " .
               "FROM " . $wpdb->prefix . self::$table_name . " " .
               "WHERE (`timestamp` <= '" . $end . "' AND `timestamp` >= '" . $start . "') " .
               "ORDER BY `timestamp` DESC;";
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get a donut chart for breakdown.
     *
     * @param array $attributes The parameters of the shortcode.
     * @return string The HTML output ready to print.
     * @since 1.0.0
     */
    public function cs_breakdown($attributes) {
        $result = __('Malformed shortcode!', 'do-not-track-stats');;
        if ($range = $this->get_date_range($attributes)) {
            $_attributes = shortcode_atts(array('size' => 'large', 'range' => 'all'), $attributes);
            $size = trim(strtolower($_attributes['size']));
            wp_enqueue_script('dnts-chartist-js', self::$plugin_url.'js/chartist.min.js', array('jquery'));
            wp_enqueue_script('dnts-chartist-plugin-tooltip-js', self::$plugin_url.'js/chartist-plugin-tooltip.min.js', array('dnts-chartist-js'));
            wp_enqueue_style('dnts-chartist-css', self::$plugin_url.'css/chartist.min.css');
            wp_enqueue_style('dnts-chartist-plugin-tooltip-css', self::$plugin_url.'css/chartist-plugin-tooltip.min.css');
            $fingerprint = md5('cs_breakdown' . json_encode($_attributes));
            $uniq = substr ($fingerprint, strlen($fingerprint)-6, 80);
            if ($range['should_be_cached']) {
                if (false !== ($cache = get_transient('dnts_' . $fingerprint))) {
                    return $cache;
                }
            }
            $data = $this->get_breakdown($range['start'], $range['end']);
            if ($data === '{"labels":[],"series":[]}') {
                $data = '{"labels":["", "", "", "' . __('no data', 'do-not-track-stats') . '"],"series":[{"meta":"","value":0}, {"meta":"","value":0}, {"meta":"","value":0}, {"meta":"' . ucwords(__('no data', 'do-not-track-stats')) . '","value":200}]}';
            }
            $result = '';
            $result .= '<div class="dnts-container-' . $size . '" id="dnts-container-' . $uniq . '" style="width:100%;">';
            $result .= '<div class="dnts-chart dnts-donut" id="dnts-chart-' . $uniq . '" style="width:100%;">';
            $result .= '</div>';
            $result .= '<script language="javascript" type="text/javascript">';
            $result .= '  jQuery(document).ready(function($) {';
            $result .= '  var data' . $uniq . ' = ' . $data . ';';
            $result .= '  var tooltip' . $uniq . ' = Chartist.plugins.tooltip({percentage: true, appendToBody: true});';
            if ($size === 'large') {
                $result .= '  var option' . $uniq . ' = {width:300, height:300,donut: true,donutWidth:"40%",startAngle: 270, plugins: [tooltip' . $uniq . ']};';
            }
            else {
                $result .= '  var option' . $uniq . ' = {width:120, height:120,showLabel: false, donut: true,donutWidth:"40%",startAngle: 270, plugins: [tooltip' . $uniq . ']};';
            }
            $result .= '  new Chartist.Pie("#dnts-chart-' . $uniq . '", data' . $uniq . ', option' . $uniq . ');';
            $result .= '  });';
            $result .= '</script>';
            $result .= '</div>';
            if ($range['should_be_cached']) {
                set_transient('dnts_' . $fingerprint, $result, self::$transient_expiry);
            }
        }
        return $result;
    }

    /**
     * Get a line+areas chart for time series.
     *
     * @param array $attributes The parameters of the shortcode.
     * @return string The HTML output ready to print.
     * @since 1.0.0
     */
    public function cs_timeseries($attributes) {
        $result = __('Malformed shortcode!', 'do-not-track-stats');;
        if ($range = $this->get_date_range($attributes)) {
            $_attributes = shortcode_atts(array('range' => 'all', 'exclude' => 'none'), $attributes);
            $exclude = trim(strtolower($_attributes['exclude']));
            wp_enqueue_script('dnts-moment-js', self::$plugin_url.'js/moment.min.js', array('jquery'));
            wp_enqueue_script('dnts-chartist-js', self::$plugin_url.'js/chartist.min.js', array('dnts-moment-js'));
            wp_enqueue_script('dnts-chartist-plugin-tooltip-js', self::$plugin_url.'js/chartist-plugin-tooltip.min.js', array('dnts-chartist-js'));
            wp_enqueue_style('dnts-chartist-css', self::$plugin_url.'css/chartist.min.css');
            wp_enqueue_style('dnts-chartist-plugin-tooltip-css', self::$plugin_url.'css/chartist-plugin-tooltip.min.css');
            $fingerprint = md5('cs_timeseries' . json_encode($_attributes));
            $uniq = substr ($fingerprint, strlen($fingerprint)-6, 80);
            if ($range['should_be_cached']) {
                if (false !== ($cache = get_transient('dnts_' . $fingerprint))) {
                    return $cache;
                }
            }
            $data = $this->get_timeseries($range['start'], $range['end'], $exclude);
            $x_axis = 'axisX: {type: Chartist.AutoScaleAxis, labelInterpolationFnc: function(value) {return moment(value).format("MMM");}}';
            if ($exclude === 'unset') {
                $y_axis = 'axisY: {type: Chartist.AutoScaleAxis, labelInterpolationFnc: function(value) {return value.toString() + "%";}}';
            }
            else {
                $y_axis = 'axisY: {type: Chartist.FixedScaleAxis, ticks: [0, 20, 40, 60, 80, 100], labelInterpolationFnc: function(value) {return value.toString() + "%";}}';
            }
            $result = '';
            $result .= '<div class="dnts-container-standard" id="dnts-container-' . $uniq . '" style="width:100%;">';
            $result .= '<div class="dnts-chart dnts-timeseries" id="dnts-chart-' . $uniq . '" style="width:100%;">';
            $result .= '</div>';
            $result .= '<script language="javascript" type="text/javascript">';
            $result .= '  jQuery(document).ready(function($) {';
            $result .= '  var data' . $uniq . ' = ' . $data . ';';
            $result .= '  var tooltip' . $uniq . ' = Chartist.plugins.tooltip({percentage: true, appendToBody: true});';
            $result .= '  var option' . $uniq . ' = {height:340, low: 0,high: 100,showArea: true, showLine: true, showPoint: true, plugins: [tooltip' . $uniq . '], ' . $x_axis . ', ' . $y_axis . '};';
            $result .= '  new Chartist.Line("#dnts-chart-' . $uniq . '", data' . $uniq . ', option' . $uniq . ');';
            $result .= '  });';
            $result .= '</script>';
            $result .= '</div>';
            if ($range['should_be_cached']) {
                set_transient('dnts_' . $fingerprint, $result, self::$transient_expiry);
            }
        }
        return $result;
    }

    /**
     * Get a table with raw data (number of requests) for the specified range.
     *
     * @param array $attributes The parameters of the shortcode.
     * @return string The HTML table ready to print.
     * @since 1.0.0
     */
    public function cs_table($attributes) {
        $result = __('Malformed shortcode!', 'do-not-track-stats');;
        if ($range = $this->get_date_range($attributes)) {
            $_attributes = shortcode_atts(array('range' => 'all'), $attributes);
            $fingerprint = md5('cs_table' . json_encode($_attributes));
            $uniq = substr ($fingerprint, strlen($fingerprint)-6, 80);
            if ($range['should_be_cached']) {
                if (false !== ($cache = get_transient('dnts_' . $fingerprint))) {
                    return $cache;
                }
            }
            $data = $this->get_rawdata($range['start'], $range['end']);
            $result = '<table class="dnts-container-table" id="dnts-table-' . $uniq . '" style="width:100%;">';
            $result .= '<thead><tr>';
            $result .= '<th scope="col">' . __('Date', 'do-not-track-stats') . '</th>';
            $result .= '<th scope="col">' . __('Excluded Requests', 'do-not-track-stats') . '</th>';
            $result .= '<th scope="col">' . __('Included Requests', 'do-not-track-stats') . '</th>';
            $result .= '<th scope="col">' . ucwords(__('unset', 'do-not-track-stats')) .'</th>';
            $result .= '<th scope="col">' . ucwords(__('opposition', 'do-not-track-stats')) . ' (' . __('opt-out', 'do-not-track-stats') . ')</th>';
            $result .= '<th scope="col">' . ucwords(__('consent', 'do-not-track-stats')) . ' (' . __('opt-in', 'do-not-track-stats') . ')</th>';
            $result .= '</tr></thead>';
            $result .= '<tbody>';
            foreach ($data as $row) {
                $unset = '0.0';
                if ($row['rq_included'] != 0) {
                    $unset = round(100 * $row['dnt_unset'] / $row['rq_included'], 1);
                }
                $unset = '<span style="display: inline-block;min-width:36px;padding-left:10px; font-size: 0.7em;color: #999999">' . sprintf('%.1F', $unset) . '%</span>';
                $dnt1 = '0.0';
                if ($row['rq_included'] != 0) {
                    $dnt1 = round(100 * $row['dnt_1'] / $row['rq_included'], 1);
                }
                $dnt1 = '<span style="display: inline-block;min-width:36px;padding-left:10px; font-size: 0.7em;color: #999999">' . sprintf('%.1F', $dnt1) . '%</span>';
                $dnt0 = '0.0';
                if ($row['rq_included'] != 0) {
                    $dnt0 = round(100 * $row['dnt_0'] / $row['rq_included'], 1);
                }
                $dnt0 = '<span style="display: inline-block;min-width:36px;padding-left:10px; font-size: 0.7em;color: #999999">' . sprintf('%.1F', $dnt0) . '%</span>';

                $result .= '<tr>';
                $result .= '<td class="td-date">' . $row['timestamp'] . '</td>';
                $result .= '<td>' . $row['rq_excluded'] . '</td>';
                $result .= '<td>' . $row['rq_included'] . '</td>';
                $result .= '<td>' . $row['dnt_unset'] . $unset . '</td>';
                $result .= '<td>' . $row['dnt_1'] . $dnt1 . '</td>';
                $result .= '<td>' . $row['dnt_0'] . $dnt0 . '</td>';
                $result .= '</tr>';
            }
            $result .= '</tbody>';
            $result .= '</table>';
            if ($range['should_be_cached']) {
                set_transient('dnts_' . $fingerprint, $result, self::$transient_expiry);
            }
        }
        return $result;
    }

}

// Utilities

function dnts_get_settings_page_url($page='dnts-settings', $tab=null, $action=null) {
    $args = array('page' => $page);
    if (isset($tab)) {
        $args['tab'] = $tab;
    }
    if (isset($action)) {
        $args['action'] = $action;
    }
    $url = add_query_arg($args, admin_url('options-general.php'));
    return $url;
}

function dnts_get_statistics_page_url($page='dnts-statistics', $tab=null, $action=null) {
    $args = array('page' => $page);
    if (isset($tab)) {
        $args['tab'] = $tab;
    }
    if (isset($action)) {
        $args['action'] = $action;
    }
    $url = add_query_arg($args, admin_url('tools.php'));
    return $url;
}

// Init the plugin

DoNotTrackStats::init();