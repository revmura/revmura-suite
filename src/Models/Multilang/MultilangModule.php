<?php
/**
 * Multilang Bridge (read-only status + deep links)
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models\Multilang;

use Revmura\Suite\Models\Contracts\ModuleInterface;

final class MultilangModule implements ModuleInterface {

	public function id(): string {
		return 'multilang'; }
	public function label(): string {
		return __( 'Multilang', 'revmura' ); }
	public function version(): string {
		return '1.0.0'; }
	public function required_core_api_min(): string {
		return '1.0.0'; }
	public function required_wp_min(): string {
		return '6.5'; }
	public function required_php_min(): string {
		return '8.3'; }

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

	public static function render_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		global $wpdb;
		$base = $wpdb->base_prefix;

		$need = array(
			"{$base}revmura_post_mappings",
			"{$base}revmura_term_mappings",
			"{$base}revmura_media_mappings",
		);

		$missing = array();
		foreach ( $need as $t ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$t
				)
			);
			if ( $exists !== $t ) {
				$missing[] = $t;
			}
		}

		if ( ! empty( $missing ) ) {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Multilang database tables are missing:', 'revmura' );
			echo '</p><ul>';
			foreach ( $missing as $m ) {
				echo '<li><code>' . esc_html( $m ) . '</code></li>';
			}
			echo '</ul><p>';
			if ( is_multisite() ) {
				echo wp_kses_post(
					sprintf(
						/* translators: %s is a URL to Network Plugins */
						__( 'Go to <a href="%s">Network Plugins</a>, deactivate/activate the Multilang plugin to re-run DB creation.', 'revmura' ),
						esc_url( network_admin_url( 'plugins.php' ) )
					)
				);
			}
			echo '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'All Multilang tables detected.', 'revmura' );
			echo '</p></div>';
		}

		// Network settings (site option).
		$settings = get_site_option(
			'revmura_settings',
			array(
				'source_site_id'  => 0,
				'target_site_id'  => 0,
				'source_language' => 'ar',
				'target_language' => 'en',
			)
		);

		$current_blog_id = get_current_blog_id();
		$current_lang    = get_blog_option( $current_blog_id, 'revmura_site_lang', '' );

		// Detect plugin (network) activation status (best-effort).
		$is_network_active = false;
		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$is_network_active = is_plugin_active_for_network( 'revmura-multilang/revmura-multilang.php' );
		}

		// Counts (filtered to current site where sensible).
		$post_table  = $wpdb->base_prefix . 'revmura_post_mappings';
		$term_table  = $wpdb->base_prefix . 'revmura_term_mappings';
		$media_table = $wpdb->base_prefix . 'revmura_media_mappings';

		$sql_posts = 'SELECT COUNT(1) FROM ' . $post_table . ' WHERE source_site_id = %d OR target_site_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is base_prefix + fixed suffix; no user input
		$sql_terms = 'SELECT COUNT(1) FROM ' . $term_table . ' WHERE source_site_id = %d OR target_site_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is base_prefix + fixed suffix; no user input
		$sql_media = 'SELECT COUNT(1) FROM ' . $media_table . ' WHERE source_site_id = %d OR target_site_id = %d'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is base_prefix + fixed suffix; no user input

		$count_posts = (int) $wpdb->get_var( $wpdb->prepare( $sql_posts, $current_blog_id, $current_blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql_posts contains a fixed table name (base_prefix + constant suffix)
		$count_terms = (int) $wpdb->get_var( $wpdb->prepare( $sql_terms, $current_blog_id, $current_blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql_terms contains a fixed table name (base_prefix + constant suffix)
		$count_media = (int) $wpdb->get_var( $wpdb->prepare( $sql_media, $current_blog_id, $current_blog_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql_media contains a fixed table name (base_prefix + constant suffix)

		// URLs (network admin).
		$settings_url = is_multisite() ? network_admin_url( 'settings.php?page=revmura-settings' ) : '';
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Multilang status', 'revmura' ); ?></h2>
			<div id="revmura-ml-notices" style="margin:12px 0"></div>

			<table class="widefat striped" style="max-width:940px;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Plugin (network active)', 'revmura' ); ?></th>
						<td><?php echo $is_network_active ? '<span class="dashicons dashicons-yes" style="color:#46b450"></span>' : '<span class="dashicons dashicons-warning" style="color:#d63638"></span>'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Current site', 'revmura' ); ?></th>
						<td><?php echo esc_html( (string) get_bloginfo( 'name' ) ) . ' (ID ' . esc_html( (string) $current_blog_id ) . ')'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Current language', 'revmura' ); ?></th>
						<td><?php echo esc_html( ! empty( $current_lang ) ? (string) $current_lang : '-' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Configured source → target', 'revmura' ); ?></th>
						<td>
							<?php
							echo esc_html(
								sprintf(
									'Site %d (%s) → Site %d (%s)',
									(int) ( $settings['source_site_id'] ?? 0 ),
									(string) ( $settings['source_language'] ?? 'ar' ),
									(int) ( $settings['target_site_id'] ?? 0 ),
									(string) ( $settings['target_language'] ?? 'en' )
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mappings (this site)', 'revmura' ); ?></th>
						<td><?php printf( '%d posts, %d terms, %d media', $count_posts, $count_terms, $count_media ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:12px">
				<?php if ( $settings_url ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>">
						<?php esc_html_e( 'Open network settings', 'revmura' ); ?>
					</a>
				<?php endif; ?>
				<button class="button" id="revmura-ml-copy-shortcode">[language_switcher]</button>
			</p>

			<details style="margin-top:10px; max-width:940px;">
				<summary><strong><?php esc_html_e( 'How to use the Language Switcher', 'revmura' ); ?></strong></summary>
				<ol>
					<li><?php esc_html_e( 'Add the “Language Switcher” block in the editor; or place the shortcode below:', 'revmura' ); ?></li>
					<li><code>[language_switcher]</code></li>
					<li><code>[language_switcher id="123"]</code></li>
				</ol>
			</details>
		</div>
		<script>
		(() => {
			const btn = document.getElementById('revmura-ml-copy-shortcode');
			if (!btn) return;
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				navigator.clipboard.writeText('[language_switcher]').then(() => {
					const box = document.getElementById('revmura-ml-notices');
					if (box) box.innerHTML = '<div class="notice notice-success is-dismissible"><p><?php echo esc_js( __( 'Shortcode copied.', 'revmura' ) ); ?></p></div>';
				});
			});
		})();
		</script>
		<?php
	}

	public function on_enable(): void {}
	public function on_disable(): void {}
	public function uninstall(): void {}
}
