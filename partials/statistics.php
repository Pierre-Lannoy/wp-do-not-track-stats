<?php

/**
 * @author Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 * @since 1.0.0
 */

$active_tab = (isset($_GET['tab']) ? $_GET['tab'] : 'charts');
$cache_str = __('Charts and raw data are cached for two hours. If you want the most recent data to be taken into account, please click on the following button:', 'do-not-track-stats');
$note = sprintf(__('Note: the settings of these statistics are available via the <a href="%s">settings menu</a>.', 'do-not-track-stats'), esc_url(dnts_get_settings_page_url()));

$explication1 = __('Do Not Track Stats performs an analysis of the HTTP headers received by your website - while respecting the privacy choices made by your visitors - to compile statistical measurements about the use of the "Do Not Track" policy.', 'do-not-track-stats');
$explication2 = __('This policy signal is sent to your site by some of your visitors, indicating that they do not want to be tracked.', 'do-not-track-stats');
$explication3 = __('For each request received by your site, if it is not excluded by your settings, Do Not Track Stats checks the header and stores the presence or absence of this signal.', 'do-not-track-stats');
$explication4 = __('The possible values are:', 'do-not-track-stats');
$explication5 = __('unset: the visitor did not specify anything', 'do-not-track-stats');
$explication6 = __('consent (opt-in): the visitor explicitly consents to the tracking', 'do-not-track-stats');
$explication7 = __('opposition (opt-out): the visitor explicitly opposes tracking - if you want to comply with the GDPR, you must act accordingly to that non-consent', 'do-not-track-stats');

?>

<div class="wrap">

    <h2>Do Not Track Stats - <?php echo __('Statistics', 'do-not-track-stats');?></h2>
    <?php settings_errors(); ?>
    <p>
        <?php echo $explication1;?><br/>
        <?php echo $explication2;?><br/>
        <?php echo $explication3;?> <?php echo $explication4;?><br/>
        &nbsp;<span style="color:#8da0cb;">●</span>&nbsp;<?php echo $explication5;?><br/>
        &nbsp;<span style="color:#f4c63d;">●</span>&nbsp;<?php echo $explication6;?><br/>
        &nbsp;<span style="color:#fc8d62;">●</span>&nbsp;<?php echo $explication7;?><br/>
    </p>

    <div>
        <p><?php echo $cache_str;?></p>
        <a class="button button-primary" href="<?php echo esc_url(dnts_get_statistics_page_url('dnts-statistics', $active_tab, 'reset-cache')); ?>"><?php echo __('Refresh Data', 'do-not-track-stats');?></a>
    </div>

    <p>&nbsp;</p>
    <h2 class="nav-tab-wrapper">
        <a href="?page=dnts-statistics&tab=charts" class="nav-tab <?php echo $active_tab == 'charts' ? 'nav-tab-active' : ''; ?>"><?php echo __('Charts', 'do-not-track-stats');?></a>
        <a href="?page=dnts-statistics&tab=rawdata" class="nav-tab <?php echo $active_tab == 'rawdata' ? 'nav-tab-active' : ''; ?>"><?php echo __('Raw Data', 'do-not-track-stats');?></a>
    </h2>

    <?php if ($active_tab == 'charts') { ?>
    <p>&nbsp;</p>
    <h2><?php echo __('Requests breakdown', 'do-not-track-stats');?></h2>
    <div class="main-container-donut">
        <div class="container-small-donut">
            <div class="line-small-donut">
                <div class="small-donut">
                    <?php echo do_shortcode('[dnts-breakdown range="day-0" size="small"]'); ?>
                    <div class="legend-donut"><?php echo __('Today', 'do-not-track-stats');?></div>
                </div>
                <div class="small-donut">
                    <?php echo do_shortcode('[dnts-breakdown range="day-1" size="small"]'); ?>
                    <div class="legend-donut"><?php echo __('Yesterday', 'do-not-track-stats');?></div>
                </div>
            </div>
            <div class="line-small-donut">
                <div class="small-donut">
                    <?php echo do_shortcode('[dnts-breakdown range="month-0" size="small"]'); ?>
                    <div class="legend-donut"><?php echo __('This Month', 'do-not-track-stats');?></div>
                </div>
                <div class="small-donut">
                    <?php echo do_shortcode('[dnts-breakdown range="month-1" size="small"]'); ?>
                    <div class="legend-donut"><?php echo __('Last Month', 'do-not-track-stats');?></div>
                </div>
            </div>
        </div>
        <div class="container-large-donut">
            <?php echo do_shortcode('[dnts-breakdown]'); ?>
            <div class="legend-donut"><?php echo __('All Time', 'do-not-track-stats');?></div>
        </div>
    </div>

    <p>&nbsp;</p>
    <h2><?php echo __('Requests evolution', 'do-not-track-stats');?></h2>
    <div class="main-container-timeseries">
        <?php echo do_shortcode('[dnts-timeseries exclude="consent"]'); ?>
    </div>

    <p>&nbsp;</p>
    <h2><?php echo __('Explicit consent vs. explicit opposition', 'do-not-track-stats');?></h2>
    <div class="main-container-timeseries">
        <?php echo do_shortcode('[dnts-timeseries exclude="unset"]'); ?>
    </div>
    <style>
        .main-container-donut {
            display: inline-flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            align-content: center;
        }
        .container-small-donut {
            display: inline-flex;
            flex-direction: column;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: center;
            align-content: center;
        }
        .line-small-donut {
            display: inline-flex;
            flex-direction: row;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: center;
        }
        .small-donut{
            width:160px !important;
            height: 170px !important;
            text-align: center;
        }
        .container-large-donut{
            width:340px !important;
            height: 350px !important;
            text-align: center;
        }
        .ct-line {
            stroke-width: 2px !important;
        }
    </style>
    <?php } ?>

    <?php if ($active_tab == 'rawdata') { ?>
        <p>&nbsp;</p>
        <div class="main-container-rawdata">
            <?php echo do_shortcode('[dnts-table]'); ?>
        </div>
        <style>
            table.dnts-container-table {
                border-spacing: 0px !important;
            }
            table.dnts-container-table td {
                text-align: right;
                padding-right: 5%;
            }
            table.dnts-container-table td.td-date {
                text-align: center;
                padding-right: 0;
            }
            table.dnts-container-table thead tr th{
                background:#d0d0d0;
            }
            table.dnts-container-table tr:nth-child(odd) td{
                background:#f8f8f8;
            }
            table.dnts-container-table tr:nth-child(even) td{
                background:#f0f0f0;
            }
        </style>
    <?php } ?>

    <p>&nbsp;</p>
    <em><?php echo $note;?></em>

</div>


