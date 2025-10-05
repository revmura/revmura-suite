<?php
/**
 * CPT & Tax module.
 *
 * Provides a Manager panel to quickly compose a CPT (and optional taxonomy)
 * and apply it via Core's REST Import/Export endpoints.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models\Cpt;

use Revmura\Suite\Models\Contracts\ModuleInterface;

/**
 * CPT & Tax module for Suite.
 */
final class CptModule implements ModuleInterface {

	/**
	 * Machine id for this module (used in toggles).
	 *
	 * @return string
	 */
	public function id(): string {
		return 'cpt';
	}

	/**
	 * Human label for the module.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'CPT & Tax', 'revmura' );
	}

	/**
	 * Module version.
	 *
	 * @return string
	 */
	public function version(): string {
		return '1.0.0';
	}

	/**
	 * Minimum Core API required.
	 *
	 * @return string
	 */
	public function required_core_api_min(): string {
		return '1.0.0';
	}

	/**
	 * Minimum WordPress version required.
	 *
	 * @return string
	 */
	public function required_wp_min(): string {
		return '6.5';
	}

	/**
	 * Minimum PHP version required.
	 *
	 * @return string
	 */
	public function required_php_min(): string {
		return '8.3';
	}

	/**
	 * Boot the module: register its Manager panel when enabled.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_action(
			'init',
			static function (): void {
				// Only add the panel if this module is enabled.
				if ( has_action( 'revmura_manager_register_panel' ) ) {
					do_action(
						'revmura_manager_register_panel',
						array(
							'id'        => 'cpt-tax',
							'label'     => __( 'CPT & Tax', 'revmura' ),
							'render_cb' => static function (): void {
								if ( ! current_user_can( 'manage_options' ) ) {
									wp_die( esc_html__( 'Access denied', 'revmura' ) );
								}

								// Nonces + endpoints for Core REST.
								$rest_nonce   = wp_create_nonce( 'wp_rest' );
								$action_nonce = wp_create_nonce( 'revmura_import' );
								$export_url   = rest_url( 'revmura/v1/export' );
								$dry_url      = rest_url( 'revmura/v1/import/dry-run' );
								$apply_url    = rest_url( 'revmura/v1/import/apply' );

								echo '<div class="wrap">';
								echo '<h2>' . esc_html__( 'CPT & Tax (via Core Importer)', 'revmura' ) . '</h2>';
								echo '<p>' . esc_html__( 'Use this quick form to generate a minimal CPT (and optional taxonomy), preview the JSON, and apply it using the Core importer. You can also Export current schema on the right.', 'revmura' ) . '</p>';

								// Two-column layout (simple).
								echo '<div style="display:flex; gap:24px; align-items:flex-start;">';

								// Left: form builder.
								echo '<div style="flex:1; min-width:420px;">';
								echo '<h3>' . esc_html__( 'Quick Builder', 'revmura' ) . '</h3>';
								echo '<table class="form-table"><tbody>';

								// CPT fields.
								echo '<tr><th><label for="cpt-slug">' . esc_html__( 'CPT Slug', 'revmura' ) . '</label></th><td>';
								echo '<input id="cpt-slug" class="regular-text" type="text" placeholder="e.g. offer" />';
								echo '</td></tr>';

								echo '<tr><th><label for="cpt-label">' . esc_html__( 'CPT Label', 'revmura' ) . '</label></th><td>';
								echo '<input id="cpt-label" class="regular-text" type="text" placeholder="e.g. Offers" />';
								echo '</td></tr>';

								echo '<tr><th><label for="cpt-rewrite">' . esc_html__( 'Rewrite Slug', 'revmura' ) . '</label></th><td>';
								echo '<input id="cpt-rewrite" class="regular-text" type="text" placeholder="e.g. offers" />';
								echo '</td></tr>';

								echo '<tr><th>' . esc_html__( 'Supports', 'revmura' ) . '</th><td>';
								echo '<label><input type="checkbox" class="cpt-support" value="title" checked> ' . esc_html__( 'Title', 'revmura' ) . '</label><br />';
								echo '<label><input type="checkbox" class="cpt-support" value="editor" checked> ' . esc_html__( 'Editor', 'revmura' ) . '</label><br />';
								echo '<label><input type="checkbox" class="cpt-support" value="thumbnail"> ' . esc_html__( 'Featured Image', 'revmura' ) . '</label><br />';
								echo '<label><input type="checkbox" class="cpt-support" value="revisions"> ' . esc_html__( 'Revisions', 'revmura' ) . '</label>';
								echo '</td></tr>';

								// Taxonomy optional.
								echo '<tr><th colspan="2"><h4>' . esc_html__( 'Optional Taxonomy', 'revmura' ) . '</h4></th></tr>';

								echo '<tr><th><label for="tax-slug">' . esc_html__( 'Tax Slug', 'revmura' ) . '</label></th><td>';
								echo '<input id="tax-slug" class="regular-text" type="text" placeholder="e.g. offer_cat" />';
								echo '</td></tr>';

								echo '<tr><th><label for="tax-label">' . esc_html__( 'Tax Label', 'revmura' ) . '</label></th><td>';
								echo '<input id="tax-label" class="regular-text" type="text" placeholder="e.g. Offer Categories" />';
								echo '</td></tr>';

								echo '<tr><th><label for="tax-rewrite">' . esc_html__( 'Tax Rewrite', 'revmura' ) . '</label></th><td>';
								echo '<input id="tax-rewrite" class="regular-text" type="text" placeholder="e.g. offer-category" />';
								echo '</td></tr>';

								echo '<tr><th>' . esc_html__( 'Tax Hierarchical', 'revmura' ) . '</th><td>';
								echo '<label><input type="checkbox" id="tax-hier" checked> ' . esc_html__( 'Hierarchical', 'revmura' ) . '</label>';
								echo '</td></tr>';

								echo '</tbody></table>';

								echo '<p>';
								echo '<button class="button" id="cpt-preview">' . esc_html__( 'Preview JSON', 'revmura' ) . '</button> ';
								echo '<button class="button button-secondary" id="cpt-dry">' . esc_html__( 'Dry-run Import', 'revmura' ) . '</button> ';
								echo '<button class="button button-primary" id="cpt-apply">' . esc_html__( 'Apply Import', 'revmura' ) . '</button>';
								echo '</p>';

								echo '<textarea id="cpt-json" rows="14" style="width:100%;"></textarea>';
								echo '</div>';

								// Right: export viewer.
								echo '<div style="flex:1; min-width:420px;">';
								echo '<h3>' . esc_html__( 'Current Schema (Export)', 'revmura' ) . '</h3>';
								echo '<p><button class="button" id="cpt-export">' . esc_html__( 'Export JSON', 'revmura' ) . '</button></p>';
								echo '<textarea id="cpt-export-json" rows="20" style="width:100%;"></textarea>';
								echo '</div>';

								echo '</div>'; // end flex.

								?>
								<script>
								(() => {
									const restNonce   = '<?php echo esc_js( $rest_nonce ); ?>';
									const actionNonce = '<?php echo esc_js( $action_nonce ); ?>';
									const exportUrl   = '<?php echo esc_url_raw( $export_url ); ?>';
									const dryUrl      = '<?php echo esc_url_raw( $dry_url ); ?>';
									const applyUrl    = '<?php echo esc_url_raw( $apply_url ); ?>';

									const $ = (sel) => document.querySelector(sel);
									const $$ = (sel) => Array.from(document.querySelectorAll(sel));

									const ta = $('#cpt-json');
									const taExport = $('#cpt-export-json');

									async function request(url, opts = {}) {
										const baseHeaders = { 'X-WP-Nonce': restNonce };
										const res = await fetch(url, {
											credentials: 'same-origin',
											headers: Object.assign(baseHeaders, opts.headers || {}),
											method: opts.method || 'GET',
											body: opts.body || undefined
										});
										const text = await res.text();
										try { return { ok: res.ok, data: JSON.parse(text) }; } catch { return { ok: res.ok, data: text }; }
									}

									function buildSchema() {
										const cptSlug = ($('#cpt-slug').value || '').trim();
										const cptLabel = ($('#cpt-label').value || '').trim() || cptSlug;
										const cptRewrite = ($('#cpt-rewrite').value || '').trim() || (cptSlug ? cptSlug + 's' : '');

										if (!cptSlug) {
											alert('Please enter a CPT slug.');
											return null;
										}
										const supports = $$('.cpt-support:checked').map(i => i.value);

										const schema = {
											schema_version: '1.0',
											cpts: {}
										};
										schema.cpts[cptSlug] = {
											label: cptLabel || cptSlug,
											supports: supports,
											rewrite: { slug: cptRewrite || cptSlug, with_front: false }
										};

										// Optional taxonomy.
										const tSlug = ($('#tax-slug').value || '').trim();
										if (tSlug) {
											const tLabel = ($('#tax-label').value || '').trim() || tSlug;
											const tRewrite = ($('#tax-rewrite').value || '').trim() || tSlug;
											const tHier = $('#tax-hier').checked;

											// NOTE: If your Core importer expects an object or array for taxes, adjust here.
											// We'll send an object map similar to cpts for flexibility.
											schema.taxes = {};
											schema.taxes[tSlug] = {
												label: tLabel,
												object_types: [cptSlug],
												hierarchical: !!tHier,
												rewrite: { slug: tRewrite, with_front: false }
											};
										} else {
											// keep alignment with your current exporter shape if needed:
											// schema.taxes = [];
											schema.taxes = {};
										}

										return schema;
									}

									$('#cpt-preview').addEventListener('click', () => {
										const schema = buildSchema();
										if (!schema) return;
										ta.value = JSON.stringify(schema, null, 2);
									});

									$('#cpt-export').addEventListener('click', async () => {
										const { ok, data } = await request(exportUrl);
										taExport.value = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
									});

									$('#cpt-dry').addEventListener('click', async () => {
										const payload = ta.value && ta.value.trim() ? ta.value : (JSON.stringify(buildSchema(), null, 2) || '');
										if (!payload) return;
										const { ok, data } = await request(dryUrl, {
											method: 'POST',
											headers: { 'Content-Type': 'application/json', 'X-Revmura-Nonce': actionNonce },
											body: payload
										});
										alert(JSON.stringify(data, null, 2));
									});

									$('#cpt-apply').addEventListener('click', async () => {
										const payload = ta.value && ta.value.trim() ? ta.value : (JSON.stringify(buildSchema(), null, 2) || '');
										if (!payload) return;
										if (!confirm('<?php echo esc_js( __( 'Apply import? This will modify CPT/Tax and flush rewrites.', 'revmura' ) ); ?>')) return;
										const { ok, data } = await request(applyUrl, {
											method: 'POST',
											headers: { 'Content-Type': 'application/json', 'X-Revmura-Nonce': actionNonce },
											body: payload
										});
										alert(JSON.stringify(data, null, 2));
									});
								})();
								</script>
								<?php
								echo '</div>';
							},
						)
					);
				}
			},
			20
		);
	}

	/**
	 * Handle enable lifecycle.
	 *
	 * @return void
	 */
	public function on_enable(): void {
		// no-op.
	}

	/**
	 * Handle disable lifecycle.
	 *
	 * @return void
	 */
	public function on_disable(): void {
		// no-op.
	}

	/**
	 * Permanent cleanup when user deletes data for this module.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// Remove any options/transients you may add in future versions.
	}
}
