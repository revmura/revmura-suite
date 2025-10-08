<?php
/**
 * Multilang Bridge (read-only status + deep links).
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models\Multilang;

use Revmura\Suite\Models\Contracts\ModuleInterface;

/**
 * Read-only Multilang status module.
 * Shows DB table health and a concise configuration summary.
 *
 * @since 1.0.0
 */
final class MultilangModule implements ModuleInterface {

	/**
	 * Module ID.
	 *
	 * @return string Module slug.
	 */
	public function id(): string {
		return 'multilang';
	}

	/**
	 * Human-readable label.
	 *
	 * @return string Translated label.
	 */
	public function label(): string {
		return __( 'Multilang', 'revmura' );
	}

	/**
	 * Module version.
	 *
	 * @return string SemVer string.
	 */
	public function version(): string {
		return '1.0.0';
	}

	/**
	 * Minimum required Core API version.
	 *
	 * @return string SemVer string.
	 */
	public function required_core_api_min(): string {
		return '1.0.0';
	}

	/**
	 * Minimum required WordPress version.
	 *
	 * @return string WP version.
	 */
	public function required_wp_min(): string {
		return '6.5';
	}

	/**
	 * Minimum required PHP version.
	 *
	 * @return string PHP version.
	 */
	public function required_php_min(): string {
		return '8.3';
	}

	/**
	 * Register the Manager panel.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				if ( has_action( 'revmura_manager_register_panel' ) ) {
					do_action(
						'revmura_manager_register_panel',
						array(
							'id'        => 'multilang',
							'label'     => __( 'Multilang', 'revmura' ),
							'render_cb' => array( self::class, 'render_panel' ),
						)
					);
				}
			},
			20
		);
	}

	/**
	 * Render the Multilang status panel.
	 *
	 * @return void
	 */
	public static function render_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		global $wpdb;
		$base    = $wpdb->base_prefix;
		$need    = array(
			"{$base}revmura_post_mappings",
			"{$base}revmura_term_mappings",
			"{$base}revmura_media_mappings",
		);
		$missing = array();
		foreach ( $need as $t ) {
			// INFORMATION_SCHEMA query to avoid LIKE wildcards.
			$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$t
				)
			);
			if ( $exists !== $t ) {
				$missing[] = $t;
			}
		}

		echo '<div class="wrap">';

		if ( ! empty( $missing ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Multilang database tables are missing:', 'revmura' ) . '</p><ul>';
			foreach ( $missing as $m ) {
				echo '<li><code>' . esc_html( $m ) . '</code></li>';
			}
			echo '</ul>';
			if ( is_multisite() ) {
				$plugins_url = network_admin_url( 'plugins.php' );
				$msg         = sprintf(
					/* translators: %s: URL to the Network Plugins screen. */
					__( 'Go to <a href="%s">Network Plugins</a>, deactivate/activate the Multilang plugin to re-run DB creation.', 'revmura' ),
					esc_url( $plugins_url )
				);

				echo '<p>' . wp_kses_post( $msg ) . '</p>';
			}
			echo '</div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All Multilang tables detected.', 'revmura' ) . '</p></div>';
		}

		// Example: safe read of a site option.
		$settings = get_site_option(
			'revmura_settings',
			array(
				'source_site_id'  => 0,
				'target_site_id'  => 0,
				'source_language' => 'ar',
				'target_language' => 'en',
			)
		);

		// Minimal, escaped summary table.
		echo '<h2>' . esc_html__( 'Multilang status', 'revmura' ) . '</h2>';
		echo '<table class="widefat striped" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Plugin (network active)', 'revmura' ) . '</th><td>' . ( is_multisite() ? esc_html__( 'Yes', 'revmura' ) : esc_html__( 'N/A (single site)', 'revmura' ) ) . '</td></tr>';

		/* translators: 1: source site ID, 2: source language, 3: target site ID, 4: target language. */
		$map_text = sprintf(
			/* translators: 1: source site ID, 2: source language, 3: target site ID, 4: target language. */
			esc_html__( 'Site %1$d (%2$s) → Site %3$d (%4$s)', 'revmura' ),
			(int) $settings['source_site_id'],
			(string) $settings['source_language'],
			(int) $settings['target_site_id'],
			(string) $settings['target_language']
		);
		echo '<tr><th>' . esc_html__( 'Configured source → target', 'revmura' ) . '</th><td>' . esc_html( $map_text ) . '</td></tr>';
		echo '</tbody></table>';

		echo '</div>';
	}

	/**
	 * Lifecycle: enable.
	 *
	 * @return void
	 */
	public function on_enable(): void {}

	/**
	 * Lifecycle: disable.
	 *
	 * @return void
	 */
	public function on_disable(): void {}

	/**
	 * Lifecycle: uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {}
}
