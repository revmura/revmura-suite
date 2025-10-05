<?php
/**
 * Plugin Name: Revmura Suite
 * Description: Modules bundle for Revmura. Loads internal modules and provides a Manager tab to enable/disable them.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Requires Plugins: revmura-power-core, revmura-manager
 * Author: Saleh Bamatraf
 * License: GPL-2.0-or-later
 * Text Domain: revmura
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const REVMURA_SUITE_VER     = '0.1.0';
const REVMURA_SUITE_OPT     = 'revmura_modules_enabled';
const REVMURA_SUITE_VER_OPT = 'revmura_module_versions';
const REVMURA_SUITE_CAP     = 'manage_options'; // Simple; can be custom later.

/**
 * Get enabled module ids (sanitized).
 *
 * @return array
 */
function revmura_suite_get_enabled(): array {
	$enabled = get_option( REVMURA_SUITE_OPT, array() );
	if ( ! is_array( $enabled ) ) {
		$enabled = array();
	}
	return array_map( 'sanitize_key', $enabled );
}

/**
 * Get stored module versions (assoc id => version).
 *
 * @return array
 */
function revmura_suite_get_versions(): array {
	$versions = get_option( REVMURA_SUITE_VER_OPT, array() );
	return is_array( $versions ) ? $versions : array();
}

/**
 * Update stored module version.
 *
 * @param string $id Module id.
 * @param string $ver Version string.
 * @return void
 */
function revmura_suite_set_module_version( string $id, string $ver ): void {
	$versions        = revmura_suite_get_versions();
	$versions[ $id ] = $ver;
	update_option( REVMURA_SUITE_VER_OPT, $versions, false );
}

/**
 * Get previous stored version for a module (or null).
 *
 * @param string $id Module id.
 * @return string|null
 */
function revmura_suite_get_module_prev_version( string $id ): ?string {
	$versions = revmura_suite_get_versions();
	return isset( $versions[ $id ] ) ? (string) $versions[ $id ] : null;
}

// Composer autoload.
$autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

// Load translations no earlier than `init` (WP 6.7+).
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain( 'revmura', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

// Imports for internal classes.
use Revmura\Suite\Models\ModuleRegistry;
use Revmura\Suite\Models\Hello\HelloModule;
use Revmura\Suite\Admin\ModulesPanel;

// Healthy boot flag (optional for MU tools).
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'REVMURA_SUITE_OK' ) ) {
			define( 'REVMURA_SUITE_OK', true );
		}
	}
);

// Register built-in modules and boot enabled ones with compatibility checks.
add_action(
	'plugins_loaded',
	static function (): void {
		// Ensure Core is present before modules boot.
		if ( ! defined( 'REVMURA_CORE_API' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Revmura Suite: Core API missing. Activate Revmura Power Core first.', 'revmura' ) . '</p></div>';
				}
			);
			return;
		}

		// Register internal modules here.
		ModuleRegistry::register( new HelloModule() );

		// Boot enabled modules with gates + safe boot.
		$enabled     = revmura_suite_get_enabled();
		$all_modules = ModuleRegistry::all();

		foreach ( $all_modules as $module ) {
			$id = $module->id();
			if ( ! in_array( $id, $enabled, true ) ) {
				continue;
			}

			// Compatibility gates.
			$php_ok  = version_compare( PHP_VERSION, $module->required_php_min(), '>=' );
			$wp_ok   = version_compare( get_bloginfo( 'version' ), $module->required_wp_min(), '>=' );
			$core_ok = defined( 'REVMURA_CORE_API' ) && version_compare( REVMURA_CORE_API, $module->required_core_api_min(), '>=' );

			if ( ! $php_ok || ! $wp_ok || ! $core_ok ) {
				add_action(
					'admin_notices',
					static function () use ( $module, $php_ok, $wp_ok, $core_ok ): void {
						$msg = sprintf(
							/* translators: 1: module label, 2: failed gates */
							__( 'Module "%1$s" is disabled due to unmet requirements: %2$s', 'revmura' ),
							$module->label(),
							implode(
								', ',
								array_filter(
									array(
										$php_ok ? '' : 'PHP',
										$wp_ok ? '' : 'WordPress',
										$core_ok ? '' : 'Core API',
									)
								)
							)
						);
						echo '<div class="notice notice-warning"><p>' . esc_html( $msg ) . '</p></div>';
					}
				);
				continue;
			}

			// Safe boot + version store.
			try {
				$module->boot();
				revmura_suite_set_module_version( $id, $module->version() );
			} catch ( \Throwable $e ) {
				add_action(
					'admin_notices',
					static function () use ( $module ): void {
						$msg = sprintf(
							/* translators: %s: module label */
							__( 'Module "%s" failed to boot and was skipped. Check PHP error log.', 'revmura' ),
							$module->label()
						);
						echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
					}
				);
				// Do not rethrow; keep site healthy.
			}
		}
	},
	30
);

// Manager integration: add "Modules" panel to toggle modules (register at `init` per i18n timing).
add_action(
	'init',
	static function (): void {
		if ( has_action( 'revmura_manager_register_panel' ) ) {
			do_action(
				'revmura_manager_register_panel',
				array(
					'id'        => 'modules',
					'label'     => __( 'Modules', 'revmura' ),
					'render_cb' => array( ModulesPanel::class, 'render' ),
				)
			);
		}
	},
	20
);

// Handle POST from the Modules panel (save toggles + lifecycle).
add_action( 'admin_post_revmura_modules_save', array( ModulesPanel::class, 'handle_post' ) );

// Handle per-module data deletion.
add_action( 'admin_post_revmura_modules_delete', array( ModulesPanel::class, 'handle_delete' ) );
