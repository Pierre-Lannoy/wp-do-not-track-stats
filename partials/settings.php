<?php

/**
 * @author Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 * @since 1.0.0
 */

$active_tab = (isset($_GET['tab']) ? $_GET['tab'] : 'misc');
$buttons = str_replace('</p>', '', get_submit_button()) . ' &nbsp;&nbsp;&nbsp; ' . str_replace('<p class="submit">', '', get_submit_button(__('Reset to Defaults', 'do-not-track-stats'), 'secondary', 'reset'));
$note = sprintf(__('Note: the statistics are available via the <a href="%s">tools menu</a>.', 'do-not-track-stats'), esc_url(dnts_get_statistics_page_url()));

?>

<div class="wrap">

    <h2>Do Not Track Stats - <?php echo __('Settings', 'do-not-track-stats');?></h2>
    <?php settings_errors(); ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=dnts-settings&tab=misc" class="nav-tab <?php echo $active_tab == 'misc' ? 'nav-tab-active' : ''; ?>"><?php echo __('General', 'do-not-track-stats');?></a>
    </h2>

    <form action="<?php echo esc_url(dnts_get_settings_page_url('dnts-settings', $active_tab)); ?>" method="POST">
        <?php do_settings_sections('dnts_'.$active_tab); ?>
        <?php wp_nonce_field('dnts-settings'); ?>
        <?php echo $buttons;?>
    </form>

    <p>&nbsp;</p>
    <em><?php echo $note;?></em>

</div>