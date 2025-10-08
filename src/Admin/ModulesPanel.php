<?php
/**
 * Modules admin panel: toggle modules on/off and delete per-module data.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

namespace Revmura\Suite\Admin;

use Revmura\Suite\Models\ModuleRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render + handlers for the Modules panel.
 */
final class ModulesPanel {

	/**
	 * Render the Modules tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		$all      = ModuleRegistry::all();
		$enabled  = function_exists( '\revmura_suite_get_enabled' ) ? \revmura_suite_get_enabled() : array();
		$versions = function_exists( '\revmura_suite_get_versions' ) ? \revmura_suite_get_versions() : array();

		// After-handler notice (read-only).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only state feedback
		$updated = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['updated'] ) ) : '';

		echo '<div class="wrap">';

		if ( '1' === $updated ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Modules updated.', 'revmura' ) . '</p></div>';
		} elseif ( 'deleted' === $updated ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Module data deleted.', 'revmura' ) . '</p></div>';
		}

		echo '<h2>' . esc_html__( 'Modules', 'revmura' ) . '</h2>';
		echo '<p>' . esc_html__( 'Enable or disable modules. Changes apply after Save.', 'revmura' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'revmura_modules_save', '_revmura_modules_nonce' );
		echo '<input type="hidden" name="action" value="revmura_modules_save" />';

		echo '<table class="widefat striped" role="presentation">';
		echo '<thead><tr>';
		echo '<th style="width:100px;">' . esc_html__( 'Enable', 'revmura' ) . '</th>';
		echo '<th>' . esc_html__( 'Module', 'revmura' ) . '</th>';
		echo '<th style="width:160px;">' . esc_html__( 'Actions', 'revmura' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $all as $module ) {
			$id         = $module->id();
			$label      = $module->label();
			$is_enabled = in_array( $id, $enabled, true );
			$version    = isset( $versions[ $id ] ) ? (string) $versions[ $id ] : '0';

			$delete_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'revmura_modules_delete',
						'module' => $id,
					),
					admin_url( 'admin-post.php' )
				),
				'revmura_modules_delete',
				'_revmura_modules_delete'
			);

			echo '<tr>';
			echo '<td>';
			echo '<label>';
			echo '<input type="checkbox" name="modules[]" value="' . esc_attr( $id ) . '"';
			// Append checked attribute safely.
			if ( $is_enabled ) {
				echo ' checked="checked"';
			}
			echo ' />';
			echo '</label>';
			echo '</td>';
			echo '<td>' . esc_html( $label ) . ' <code>' . esc_html( $version ) . '</code></td>';
			echo '<td><a class="button button-secondary" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete data', 'revmura' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Save Changes', 'revmura' ) . '</button></p>';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Save toggles: update enabled option and run lifecycle hooks.
	 *
	 * @return void
	 */
	public static function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		check_admin_referer( 'revmura_modules_save', '_revmura_modules_nonce' );

		// Read and sanitize strictly (array of slugs/ids).
		$mods = filter_input( INPUT_POST, 'modules', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized next line
		$mods = is_array( $mods ) ? array_map( 'sanitize_key', wp_unslash( $mods ) ) : array();

		$prev = function_exists( '\revmura_suite_get_enabled' ) ? \revmura_suite_get_enabled() : array();
		update_option( REVMURA_SUITE_OPT, $mods, false );

		// Lifecycle diffs.
		$enabled_now  = array_fill_keys( $mods, true );
		$enabled_prev = array_fill_keys( $prev, true );

		$all = ModuleRegistry::all();
		foreach ( $all as $module ) {
			$id  = $module->id();
			$was = isset( $enabled_prev[ $id ] );
			$is  = isset( $enabled_now[ $id ] );

			if ( ! $was && $is ) {
				try {
					$module->on_enable();
				} catch ( \Throwable $e ) {
					// Surface to optional listeners; avoids error_log() in production.
					do_action( 'revmura_suite_log', 'on_enable() failed: ' . $e->getMessage(), $e );
				}
			} elseif ( $was && ! $is ) {
				try {
					$module->on_disable();
				} catch ( \Throwable $e ) {
					do_action( 'revmura_suite_log', 'on_disable() failed: ' . $e->getMessage(), $e );
				}
			}
		}

		$redirect = add_query_arg(
			array(
				'page'    => 'revmura',
				'tab'     => 'modules',
				'updated' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Delete per-module data (uninstall hook).
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		check_admin_referer( 'revmura_modules_delete', '_revmura_modules_delete' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified via check_admin_referer above.
		$mod = isset( $_GET['module'] ) ? sanitize_key( (string) wp_unslash( $_GET['module'] ) ) : '';

		if ( '' !== $mod ) {
			// Remove from enabled.
			$current = function_exists( '\revmura_suite_get_enabled' ) ? \revmura_suite_get_enabled() : array();
			$current = array_values(
				array_filter(
					$current,
					static fn( $m ): bool => (string) $m !== $mod
				)
			);
			update_option( REVMURA_SUITE_OPT, $current, false );

			// Run uninstall + forget stored version.
			$all = ModuleRegistry::all();
			foreach ( $all as $module ) {
				if ( $module->id() === $mod ) {
					try {
						$module->uninstall();
					} catch ( \Throwable $e ) {
						do_action( 'revmura_suite_log', 'uninstall() failed: ' . $e->getMessage(), $e );
					}
					break;
				}
			}

			$versions = function_exists( '\revmura_suite_get_versions' ) ? \revmura_suite_get_versions() : array();
			if ( isset( $versions[ $mod ] ) ) {
				unset( $versions[ $mod ] );
				update_option( REVMURA_SUITE_VER_OPT, $versions, false );
			}
		}

		$redirect = add_query_arg(
			array(
				'page'    => 'revmura',
				'tab'     => 'modules',
				'updated' => 'deleted',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}
}
