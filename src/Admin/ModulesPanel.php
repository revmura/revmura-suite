<?php
/**
 * Modules Panel for Manager: enable/disable Suite modules + delete data.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Admin;

use Revmura\Suite\Models\ModuleRegistry;

/**
 * Renders and handles the Modules panel UI.
 */
final class ModulesPanel {

	/**
	 * Output the Modules admin panel.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		$all     = ModuleRegistry::all();
		$enabled = get_option( 'revmura_modules_enabled', array() );
		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}
		$enabled = array_map( 'sanitize_key', $enabled );

		$save_action = 'revmura_modules_save';
		$post_url    = admin_url( 'admin-post.php' );

		echo '<div class="wrap"><h2>' . esc_html__( 'Modules', 'revmura' ) . '</h2>';

		// Read-only notices.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only feedback.
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Modules updated.', 'revmura' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only feedback.
		if ( isset( $_GET['deleted'], $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only param.
			$id_raw = (string) wp_unslash( $_GET['id'] );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Module data deleted.', 'revmura' ) . ' <code>' . esc_html( sanitize_key( $id_raw ) ) . '</code></p></div>';
		}

		echo '<p>' . esc_html__( 'Enable or disable modules. Changes apply after Save.', 'revmura' ) . '</p>';

		echo '<form method="post" action="' . esc_url( $post_url ) . '">';
		wp_nonce_field( $save_action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $save_action ) . '" />';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Enable', 'revmura' ) . '</th>';
		echo '<th>' . esc_html__( 'Module', 'revmura' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'revmura' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $all as $id => $module ) {
			$is_enabled = in_array( $id, $enabled, true );

			echo '<tr>';

			// Enable checkbox (WPCS-safe: call checked() inline).
			echo '<td><label>';
			echo '<input type="checkbox" name="modules[]" value="' . esc_attr( $id ) . '" ';
			checked( $is_enabled, true );
			echo ' /> ';
			echo '</label></td>';

			// Label + id.
			echo '<td><strong>' . esc_html( $module->label() ) . '</strong> <code>' . esc_html( $id ) . '</code></td>';

			// Actions: "Delete data" as a nonce-protected link. Disabled while enabled.
			echo '<td>';
			if ( $is_enabled ) {
				// Disabled look (no action while enabled).
				echo '<span class="button-link-delete" aria-disabled="true" style="opacity:.5;cursor:not-allowed;">' . esc_html__( 'Delete data', 'revmura' ) . '</span>';
			} else {
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'revmura_modules_delete',
							'module' => rawurlencode( (string) $id ),
						),
						$post_url
					),
					'revmura_modules_delete'
				);
				echo '<a class="button button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete module data?', 'revmura' ) ) . '\')">' . esc_html__( 'Delete data', 'revmura' ) . '</a>';
			}
			echo '</td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p>';
		submit_button( __( 'Save Changes', 'revmura' ), 'primary', 'submit', false );
		echo '</p></form></div>';
	}

	/**
	 * Handle the "Save Changes" postback (enable/disable modules).
	 *
	 * @return void
	 */
	public static function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}
		check_admin_referer( 'revmura_modules_save' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$raw = isset( $_POST['modules'] ) ? (array) wp_unslash( $_POST['modules'] ) : array();

		$selected = array();
		foreach ( $raw as $maybe ) {
			$selected[] = sanitize_key( (string) $maybe );
		}

		$previous = get_option( 'revmura_modules_enabled', array() );
		if ( ! is_array( $previous ) ) {
			$previous = array();
		}
		$previous = array_map( 'sanitize_key', $previous );

		$newly_enabled  = array_diff( $selected, $previous );
		$newly_disabled = array_diff( $previous, $selected );

		update_option( 'revmura_modules_enabled', $selected, false );

		$all = ModuleRegistry::all();
		foreach ( $newly_enabled as $mid ) {
			$m = $all[ $mid ] ?? null;
			if ( $m ) {
				try {
					$m->on_enable();
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'Revmura Suite: on_enable failed for module %s: %s', $mid, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
				}
			}
		}
		foreach ( $newly_disabled as $mid ) {
			$m = $all[ $mid ] ?? null;
			if ( $m ) {
				try {
					$m->on_disable();
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'Revmura Suite: on_disable failed for module %s: %s', $mid, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
				}
			}
		}

		$url = add_query_arg(
			array(
				'page'    => 'revmura',
				'tab'     => 'modules',
				'updated' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle "Delete data" (only when module is disabled).
	 *
	 * @return void
	 */
	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}
		check_admin_referer( 'revmura_modules_delete' );

		// Accept both GET (from wp_nonce_url link) and POST (future).
		$module_raw = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		if ( isset( $_GET['module'] ) ) {
			$module_raw = (string) wp_unslash( $_GET['module'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( '' === $module_raw && isset( $_POST['module'] ) ) {
			$module_raw = (string) wp_unslash( $_POST['module'] );
		}

		$id = sanitize_key( $module_raw );
		if ( '' === $id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=revmura&tab=modules' ) );
			exit;
		}

		$enabled = get_option( 'revmura_modules_enabled', array() );
		$enabled = is_array( $enabled ) ? array_map( 'sanitize_key', $enabled ) : array();

		// Safety: require module be disabled before allowing data deletion.
		if ( in_array( $id, $enabled, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=revmura&tab=modules' ) );
			exit;
		}

		$module = ModuleRegistry::find( $id );
		if ( $module ) {
			try {
				$module->uninstall();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'Revmura Suite: uninstall failed for module %s: %s', $id, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		$versions = get_option( 'revmura_module_versions', array() );
		if ( is_array( $versions ) && isset( $versions[ $id ] ) ) {
			unset( $versions[ $id ] );
			update_option( 'revmura_module_versions', $versions, false );
		}

		$url = add_query_arg(
			array(
				'page'    => 'revmura',
				'tab'     => 'modules',
				'deleted' => '1',
				'id'      => $id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
