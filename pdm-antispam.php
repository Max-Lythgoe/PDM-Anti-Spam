<?php

/**
 * Plugin Name: PDM Anti-Spam
 * Description: Protect your forms and comments with invisible spam protection. Forked from GF Spam Hexer by Gravity Wiz.
 * Version: 1.0.1
 * Requires PHP: 8.0
 * Author: Performance Driven Marketing
 * Author URI: https://performancedrivenmarketing.com
 * License: GPL2
 * Text Domain: pdm-antispam
 * Domain Path: /languages
 */
if (! defined('ABSPATH')) {
	exit;
}


// Plugin Update Checker
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker-5.6/plugin-update-checker-5.6/load-v5p6.php';
$pdm_antispam_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/Max-Lythgoe/PDM-Anti-Spam',
	__FILE__,
	'pdm-antispam'
);

add_filter('puc_request_info_result-pdm-antispam', function ($info) {
	if (is_object($info)) {
		$info->icons = [
			'2x' => plugin_dir_url(__FILE__) . 'assets/img/icon.png',
		];
	}
	return $info;
});

// Include Composer Autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload_packages.php';

define('PDM_ANTISPAM_VERSION', '1.0.1');

add_action('gform_loaded', function () {
	require_once __DIR__ . '/class-pdm-antispam.php';

	GFAddOn::register('PDM_Antispam');
});

if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command(
		'gfsh benchmark',
		\PDM_Antispam\CLI\Benchmark_Command::class,
		['when' => 'after_wp_load']
	);
}
