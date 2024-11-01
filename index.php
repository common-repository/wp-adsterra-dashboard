<?php

/**
 * Plugin Name: WP Adsterra Dashboard
 * Plugin URI: https://wordpress-plugins.luongovincenzo.it/#wp-adsterra-dashboard
 * Description: WP AdsTerra Dashboard for view statistics via API
 * Version: 1.3.0
 * Author: Vincenzo Luongo
 * Author URI: https://www.luongovincenzo.it/
 * License: GPLv2 or later
 * Text Domain: wp-adsterra-dashboard
 */
if (!defined('ABSPATH')) {
    exit;
}

define("ADSTERRA_DASHBOARD_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX", 'wp_adsterra_dashboard_option');
define("ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP", 'wp-adsterra-dashboard-settings-group');

class WPAdsterraDashboard {

    protected $pluginDetails;
    protected $pluginOptions = [];

    function __construct() {

        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $this->pluginDetails = get_plugin_data(__FILE__);

        //$this->pluginDetails['Version'] = time();

        $this->pluginOptions = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => trim(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token')),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'placements' => trim(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_placements')) ?: 'all',
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);

        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_actions']);

        add_action('admin_enqueue_scripts', [$this, 'widget_dashboard_ajax_script']);
        add_action('wp_ajax_adsterra_update_month_filter', [$this, 'wp_adsterra_update_month_filter_action']);
    }

    public function wp_adsterra_update_month_filter_action() {

        $filter = $this->validFilter(@$_POST['filter_month']);

        update_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter', $filter);

        wp_die();
    }

    private function validFilter($timestamp) {
        if (!empty($timestamp) && ((string) (int) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX) && is_numeric($timestamp)) {
            return $timestamp;
        } else {
            return null;
        }
    }

    public function widget_dashboard_ajax_script($hook) {

        if ('index.php' != $hook) {
            // Only applies to dashboard panel
            return;
        }

        wp_enqueue_style('adsterra-dashboard-widget-admin-theme', plugins_url('/css/style.css', __FILE__), $this->pluginDetails['Version']);

        wp_enqueue_script('chartjs', plugins_url('/js/chartjs.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_enqueue_script('adsterra-dashboard-widget-admin-ajax-script', plugins_url('/js/main.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_localize_script('adsterra-dashboard-widget-admin-ajax-script', 'adsterra_ajax_url', admin_url('admin-ajax.php'));
    }

    public function add_plugin_actions($links) {
        $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')) . '">Settings</a>';
        return $links;
    }

    public function create_admin_menu() {
        add_options_page('Adsterra Settings', 'Adsterra Settings', 'administrator', __FILE__, [$this, 'viewAdminSettingsPage']);
        add_action('admin_init', [$this, '_registerOptions']);
    }

    function optionTokenValidate($value) {

        if (!preg_match("/^[a-z0-9]{32}$/", str_replace(" ", "", trim($value)))) {
            add_settings_error('adsterra_plugins_option_token', 'adsterra_plugins_option_token', 'Token invalid. (32 characters) ', 'error');
            return false;
        } else {
            return $value;
        }
    }

    public function _registerOptions() {
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled');
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token', [$this, 'optionTokenValidate']);
        register_setting(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP, ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id');
    }

    public function dashboard_widget() {
        wp_add_dashboard_widget('adsterra_dashboard_widget', 'Earnings Dashboard for Adsterra', [$this, 'adsterra_dashboard_widget']);
    }

    public function viewAdminSettingsPage() {

        $domains = [];
        $errorMessage = null;

        $pluginSettings = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        if (!empty($pluginSettings['token'])) {
            require_once ADSTERRA_DASHBOARD_PLUGIN_DIR . 'libs/class-api-client.php';
            $adsterraAPIClient = new WPAdsterraDashboardAPIClient($pluginSettings['token'], $pluginSettings['domain_id']);

            $domains = $adsterraAPIClient->getDomains();

            if (!empty($domains['code']) && in_array($domains['code'], [401, 403])) {
                $errorMessage = 'Adsterra Dashboard. API Token error Code: ' . $domains['code'] . ' Message: ' . $domains['message'];

                $domains = [];
            }
        }
?>

        <style>
            .left_adsterra_bar {
                width: 200px;
            }
        </style>
        <div class="wrap">
            <h2>WP Adsterra Settings</h2>

            <?php if ($errorMessage) { ?>
                <div class="error notice">
                    <p><?php _e($errorMessage); ?></p>
                </div>
            <?php } ?>

            <form method="post" action="options.php">
                <?php settings_fields(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP); ?>
                <?php do_settings_sections(ADSTERRA_DASHBOARD_PLUGIN_SETTINGS_GROUP); ?>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <td scope="row" class="left_adsterra_bar">Enabled</td>
                            <td><input type="checkbox" <?php echo get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_enabled" /></td>
                        </tr>

                        <tr valign="top">
                            <td scope="row" class="left_adsterra_bar">API Token</td>
                            <td>
                                <input type="text" name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_token" value="<?php echo htmlspecialchars(get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'), ENT_QUOTES) ?>" placeholder="32 characters" style="width:300px;" required />
                                <p class="description">
                                    Simply visit <a href="https://beta.publishers.adsterra.com/api-token" target="_blank">API Token</a> page.
                                    Generate new token and copy it.
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adsterra_bar">Domain</td>
                            <td>
                                <?php if (!empty($domains)) { ?>
                                    <select name="<?php print ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX; ?>_domain_id">
                                        <?php
                                        $selectedDomainID = get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id');

                                        foreach ($domains as $domain) {

                                            $selectedDom = '';
                                            if (intval($selectedDomainID) === intval($pluginSettings['domain_id'])) {
                                                $selectedDom = ' selected ';
                                            }

                                            print '<option value="' . $domain['id'] . '" ' . $selectedDom . ' >' . $domain['title'] . '</option>' . PHP_EOL;
                                        }
                                        ?>
                                    </select>
                                <?php } else { ?>
                                    <p class="description">
                                        To view the list of domains, enter the correct Token API and save.
                                    </p>
                                <?php } ?>
                            </td>
                        </tr>


                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Update Settings') ?>" />
                </p>
            </form>
        </div>
    <?php
    }

    public function adsterra_dashboard_widget() {
        require_once ADSTERRA_DASHBOARD_PLUGIN_DIR . 'libs/class-api-client.php';

        $pluginSettings = [
            'enabled' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'token' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_token'),
            'domain_id' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_domain_id'),
            'widget_filter_month' => get_option(ADSTERRA_DASHBOARD_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        if (empty($pluginSettings['enabled']) || empty($pluginSettings['token'])) {
            print '<h3>Plugin not active or token invalid, please enter into <a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')) . '">Setting page</a> and enable it';
            return;
        }

        $adsterraAPIClient = new WPAdsterraDashboardAPIClient($pluginSettings['token'], $pluginSettings['domain_id']);

        $dateFilter = null;
        $errorMessage = null;

        if ($pluginSettings['widget_filter_month']) {
            $dateFilter = date('Y-m-d', $pluginSettings['widget_filter_month']);
            $monthActiveName = date('F', $pluginSettings['widget_filter_month']);
        } else {
            $monthActiveName = date('F');
        }

        $placements = $adsterraAPIClient->getPlacementsByDomainID($pluginSettings['domain_id']);

        if (!empty($placements['code']) && in_array($placements['code'], [401, 403])) {
            $errorMessage = 'Adsterra Dashboard. API Token error Code: ' . $placements['code'] . ' Message: ' . $placements['message'];

            $placements = [];
        }

        $totalImpressions = 0;
        $totalClicks = 0;
        $totalCTR = 0;
        $totalCPM = 0;
        $totalRevenue = 0;

        $labels_X = [];
        $values_Y = [];

        $statParams = [];

        if (!empty($dateFilter)) {
            $statParams['start_date'] = date('Y-m-01', strtotime($dateFilter));
            $statParams['finish_date'] = date('Y-m-t', strtotime($dateFilter));
        } else {
            $statParams['start_date'] = date('Y-m-01');
            $statParams['finish_date'] = date('Y-m-t');
        }

        $period = new DatePeriod(
            new DateTime($statParams['start_date']),
            new DateInterval('P1D'),
            new DateTime($statParams['finish_date'])
        );

        foreach ($period as $key => $value) {
            //$labels_X[] = $value->format('Y-m-d');
            $labels_X[date('j', strtotime($value->format('Y-m-d')))] = $value->format('d');
        }

        foreach ($placements as $placementStats) {

            $placementID = $placementStats['id'];
            $placementTitle = $placementStats['title'];

            $statsSinglePlacement = $adsterraAPIClient->getStatsByPlacementID($pluginSettings['domain_id'], $placementID, $statParams);

            foreach ($statsSinglePlacement['items'] as $statSinglePlacement) {

                $day = $statSinglePlacement['date'];

                $values_Y[$placementTitle][date('j', strtotime($day))] = $statSinglePlacement['revenue'];

                //$values_Y[$placementTitle]['impression'] = $statSinglePlacement['impression'];
                //$values_Y[$placementTitle]['clicks'] = $statSinglePlacement['clicks'];
                //$values_Y[$placementTitle]['ctr'] = $statSinglePlacement['ctr'];
                //$values_Y[$placementTitle]['cpm'] = $statSinglePlacement['cpm'];
                //$values_Y[$placementTitle]['revenue'] = $statSinglePlacement['revenue'];

                $totalImpressions += $statSinglePlacement['impression'];
                $totalClicks += $statSinglePlacement['clicks'];
                $totalCTR += $statSinglePlacement['ctr'];
                $totalCPM += $statSinglePlacement['cpm'];
                $totalRevenue += $statSinglePlacement['revenue'];
            }
        }

        foreach ($values_Y as $placementTitle => $dataPlacement) {
            foreach ($dataPlacement as $day => $data) {
                foreach ($labels_X as $kDay => $vDay) {
                    if (!isset($values_Y[$placementTitle][$kDay])) {
                        $values_Y[$placementTitle][$kDay] = 0;
                    }
                }
            }
        }
    ?>

        <div id="container-box">

            <?php if ($errorMessage) { ?>
                <div class="error notice">
                    <p><?php _e($errorMessage); ?></p>
                </div>
                <p>Please enter into <a href="<?php print esc_url(get_admin_url(null, 'options-general.php?page=wp-adsterra-dashboard%2Findex.php')); ?>">Setting page</a> for resolve problem.</p>
            <?php } else { ?>

                <div style="height: 300px;" id="containerChartjs">
                    <canvas id="adsterraStatsCanvas"></canvas>
                </div>

                <table style="width:100%;">
                    <tr>
						<td style="width:30%;">Filter Month:</td>
						<td style="width:70%; font-weight: bold;">
                            <select id="adsterra_dashboard_widget_filter_month">
                                <?php
                                for ($i = 0; $i <= 12; $i++) {

                                    $selectValue = date('F Y', strtotime("-$i month"));

                                    $selectedDom = '';
                                    if (
                                        (!$dateFilter && date('Y-m', strtotime($selectValue)) == date('Y-m')) ||
                                        ($dateFilter && date('Y-m', strtotime($dateFilter)) == date('Y-m', strtotime($selectValue)))
                                    ) {
                                        $selectedDom = ' selected ';
                                    }

                                    print '<option value="' . strtotime($selectValue) . '" ' . $selectedDom . ' >' . $selectValue . '</option>' . PHP_EOL;
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <table>
                    <tr>
                        <td>
                            <div class="small-box">
                                <h3>Tot. Impressions</h3>
                                <p><?php print number_format($totalImpressions, 0, '.', '.'); ?></p>
                            </div>
                            <div class="small-box">
                                <h3>Tot. CPM</h3>
                                <p><?php print number_format($totalCPM, 3, '.', ','); ?></p>
                            </div>
                            <div class="small-box">
                                <h3>Tot. CTR</h3>
                                <p><?php print number_format($totalCTR, 3, '.', ','); ?></p>
                            </div>

                            <div class="small-box small-md-6">
                                <h3>Total Clicks</h3>
                                <p><?php print number_format($totalClicks, 0, '.', '.'); ?></p>
                            </div>
                            <div class="small-box small-md-6">
                                <h3>Grand Earnings</h3>
                                <p><?php print number_format($totalRevenue, 3, '.', ','); ?> $</p>
                            </div>
                        </td>
                    </tr>
                </table>
            <?php } ?>
        </div>

        <script>
            function adsterraRandomRGBAColor() {
                var o = Math.round,
                    r = Math.random,
                    s = 255;
                //return 'rgba(' + o(r() * s) + ',' + o(r() * s) + ',' + o(r() * s) + ',' + r().toFixed(1) + ')';
                return 'rgba(' + o(r() * s) + ',' + o(r() * s) + ',' + o(r() * s) + ', 1)';
            }

            var ADSTERRA_LABELS_X = [<?php print implode(",", $labels_X); ?>];

            var adsterraChartConfig = {
                type: 'line',
                data: {
                    labels: ADSTERRA_LABELS_X,
                    datasets: [
                        <?php foreach ($values_Y as $key => $value) { ?> {
                                label: '<?php print strtoupper($key); ?>',
                                backgroundColor: adsterraRandomRGBAColor(),
                                borderColor: adsterraRandomRGBAColor(),
                                data: [<?php print implode(",", $values_Y[$key]); ?>],
                                fill: false,
                            },
                        <?php } ?>
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    title: {
                        display: false,
                        text: 'Adsterra Stats'
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var corporation = data.datasets[tooltipItem.datasetIndex].label;
                                var valor = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];

                                var total = 0;
                                for (var i = 0; i < data.datasets.length; i++)
                                    total += data.datasets[i].data[tooltipItem.index];

                                if (tooltipItem.datasetIndex != data.datasets.length - 1) {
                                    return corporation + " : $ " + valor.toFixed(3).replace(/(\d)(?=(\d{3})+\.)/g, '$1,');
                                } else { // .. else, you display the dataset and the total, using an array
                                    return [corporation + " : $ " + valor.toFixed(3).replace(/(\d)(?=(\d{3})+\.)/g, '$1,'), "Total : $ " + total.toFixed(3)];
                                }
                            }
                        }
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    },
                    scales: {
                        xAxes: [{
                            display: true,
                            scaleLabel: {
                                display: true,
                                labelString: 'Days of <?php print $monthActiveName; ?>'
                            }
                        }],
                        yAxes: [{
                            display: true,
                            scaleLabel: {
                                display: false,
                                labelString: 'Values'
                            }
                        }]
                    }
                }
            };

            document.addEventListener("DOMContentLoaded", function() {

                var adsterraCtx = document.getElementById('adsterraStatsCanvas').getContext('2d');
                var adsterraStatsCanvas = new Chart(adsterraCtx, adsterraChartConfig);
            });
        </script>
<?php
    }
}

new WPAdsterraDashboard();
?>