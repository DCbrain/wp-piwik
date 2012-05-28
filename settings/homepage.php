<?php
$strVersion = $this->callPiwikAPI('ExampleAPI.getPiwikVersion');
// http://wordpress.org/support/rss/tags/wp-piwik
?><tr><td><strong><?php _e('Thanks for using WP-Piwik!', 'wp-piwik'); ?></strong></td></tr>
<tr><td><?php 
if (is_array($strVersion) && $strVersion['result'] == 'error') self::showErrorMessage($strVersion['message']);
elseif (empty($strVersion)) self::showErrorMessage('Piwik did not answer. Please check your entered Piwik URL.');
else echo __('You are using Piwik','wp-piwik').' '.$strVersion.' '.__('and', 'wp-piwik').' WP-Piwik '.self::$strVersion.(!is_plugin_active_for_network('wp-piwik/wp-piwik.php')?' in network mode':'').'.';
?></td></tr>
<tr><td><?php _e('Auto site configuration is'); ?> <strong><?php echo (self::$aryGlobalSettings['auto_site_config']?__('enabled'):__('disabled')); ?>.</strong></td></tr>
<tr><td><?php _e('Tracking code insertion is'); ?> <strong><?php echo (self::$aryGlobalSettings['add_tracking_code']?__('enabled'):__('disabled')); ?>.</strong></td></tr>