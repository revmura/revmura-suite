<?php
/**
 * CPT & Tax module (Suite)
 * CPT & Tax module (Suite)
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models\Cpt;

use Revmura\Suite\Models\Contracts\ModuleInterface;

/**
 * Module that manages CPT & Taxonomy schema (create/edit/export/import/delete)
 * and registers them at runtime from a stored snapshot.
 *
 * @since 1.0.0
 */
final class CptModule implements ModuleInterface {

	/**
	 * Get the module ID.
	 *
	 * @return string Module slug.
	 */
	public function id(): string {
		return 'cpt';
	}

	/**
	 * Get the human-readable module label.
	 *
	 * @return string Translated label.
	 */
	public function label(): string {
		return __( 'CPT & Tax', 'revmura' );
	}

	/**
	 * Get the module version.
	 *
	 * @return string SemVer string.
	 */
	public function version(): string {
		return '1.0.0';
	}

	/**
	 * Minimum compatible Revmura Core API version.
	 *
	 * @return string SemVer string.
	 */
	public function required_core_api_min(): string {
		return '1.0.0';
	}

	/**
	 * Minimum required WordPress version.
	 *
	 * @return string WP version string.
	 */
	public function required_wp_min(): string {
		return '6.5';
	}

	/**
	 * Minimum required PHP version.
	 *
	 * @return string PHP version string.
	 */
	public function required_php_min(): string {
		return '8.3';
	}

	/**
	 * Boot hooks: panel register, saved schema auto-register, admin-post handlers.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Register Manager panel (via Manager hook). Use init (i18n timing).
		// Register Manager panel (via Manager hook). Use init (i18n timing).
		add_action(
			'init',
			static function (): void {
				if ( has_action( 'revmura_manager_register_panel' ) ) {
					do_action(
						'revmura_manager_register_panel',
						array(
							'id'        => 'cpt',
							'label'     => __( 'CPT & Tax', 'revmura' ),
							'render_cb' => array( self::class, 'render_panel' ),
						)
					);
				}
			},
			20
		);

		// Always register previously saved schema so CPT/Tax persist across requests.
		add_action(
			'init',
			static function (): void {
				$schema = get_option(
					'revmura_cpt_schema',
					array(
						'schema_version' => '1.0',
						'cpts'           => array(),
						'taxes'          => array(),
					)
				);
				if ( is_array( $schema ) && ( ! empty( $schema['cpts'] ) || ! empty( $schema['taxes'] ) ) ) {
					self::register_from_snapshot( $schema );
				}
			},
			30
		);

		// Admin-post endpoints.
		add_action( 'admin_post_revmura_cpt_apply', array( self::class, 'handle_apply' ) );
		add_action( 'admin_post_revmura_cpt_export_one', array( self::class, 'handle_export_one' ) );
		add_action( 'admin_post_revmura_cpt_delete', array( self::class, 'handle_delete_cpt' ) );
	}

	/**
	 * Render the Manager panel UI (create/edit/export/import/delete).
	 *
	 * @return void
	 */
	public static function render_panel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}

		// Saved schema.
		$schema = get_option(
			'revmura_cpt_schema',
			array(
				'schema_version' => '1.0',
				'cpts'           => array(),
				'taxes'          => array(),
			)
		);
		$cpts   = is_array( $schema['cpts'] ?? null ) ? $schema['cpts'] : array();
		$taxes  = is_array( $schema['taxes'] ?? null ) ? $schema['taxes'] : array();

		// Default prefill.
		$prefill = array(
			'slug'            => '',
			'label'           => '',
			'rewrite_slug'    => '',
			'supports'        => array(
				'title'     => true,
				'editor'    => true,
				'thumbnail' => true,
				'revisions' => true,
			),
			'has_archive'     => false,
			'hierarchical'    => false,
			'map_meta_cap'    => false,
			'capability_type' => 'post',
			'menu_icon'       => 'dashicons-admin-post',
			'menu_position'   => 26,
			// A single linked tax (simple UI); first tax found is used.
			'tax_slug'        => '',
			'tax_label'       => '',
			'tax_rewrite'     => '',
			'tax_hier'        => false,
		);

		// Preselect first CPT if any.
		if ( ! empty( $cpts ) ) {
			$first = array_key_first( $cpts );
			if ( is_string( $first ) ) {
				self::fill_from_cpt_and_tax( $prefill, $cpts, $taxes, $first );
			}
		}

		// Nonces/URLs.
		$apply_nonce  = wp_create_nonce( 'revmura_cpt_apply' );
		$export_nonce = wp_create_nonce( 'revmura_cpt_export_one' );
		$delete_nonce = wp_create_nonce( 'revmura_cpt_delete' );
		$post_url     = admin_url( 'admin-post.php' );

		// Render.
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'CPT & Tax (via Core Importer)', 'revmura' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use this quick form to create or edit a CPT (and one linked taxonomy), preview JSON, and Save & Register. You can also Export/Import JSON, or Delete the current CPT.', 'revmura' ) . '</p>';

		// Existing CPT selector.
		echo '<div class="card" style="max-width:900px;padding:16px;margin-bottom:12px">';
		echo '<h3>' . esc_html__( 'Choose CPT to edit (or select “(new)”)', 'revmura' ) . '</h3>';
		echo '<select id="revmura-cpt-select" class="regular-text">';
		echo '<option value="">' . esc_html__( '(new)', 'revmura' ) . '</option>';
		foreach ( $cpts as $slug => $cfg ) {
			$label = is_array( $cfg ) ? (string) ( $cfg['label'] ?? $slug ) : (string) $slug;
			echo '<option value="' . esc_attr( (string) $slug ) . '">' . esc_html( $label . ' (' . $slug . ')' ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
		// Render.
		echo '<div class="wrap">';
		echo '<h2>' . esc_html__( 'CPT & Tax (via Core Importer)', 'revmura' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use this quick form to create or edit a CPT (and one linked taxonomy), preview JSON, and Save & Register. You can also Export/Import JSON, or Delete the current CPT.', 'revmura' ) . '</p>';

		// Existing CPT selector.
		echo '<div class="card" style="max-width:900px;padding:16px;margin-bottom:12px">';
		echo '<h3>' . esc_html__( 'Choose CPT to edit (or select “(new)”)', 'revmura' ) . '</h3>';
		echo '<select id="revmura-cpt-select" class="regular-text">';
		echo '<option value="">' . esc_html__( '(new)', 'revmura' ) . '</option>';
		foreach ( $cpts as $slug => $cfg ) {
			$label = is_array( $cfg ) ? (string) ( $cfg['label'] ?? $slug ) : (string) $slug;
			echo '<option value="' . esc_attr( (string) $slug ) . '">' . esc_html( $label . ' (' . $slug . ')' ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		// Builder/edit form.
		?>
		<div class="card" style="max-width:900px;padding:16px;">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="revmura-cpt-slug"><?php esc_html_e( 'CPT Slug', 'revmura' ); ?></label></th>
						<td><input id="revmura-cpt-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['slug'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-cpt-label"><?php esc_html_e( 'CPT Label', 'revmura' ); ?></label></th>
						<td><input id="revmura-cpt-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Offers', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-rewrite-slug"><?php esc_html_e( 'Rewrite Slug', 'revmura' ); ?></label></th>
						<td><input id="revmura-rewrite-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offers', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['rewrite_slug'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Supports', 'revmura' ); ?></th>
						<td>
							<label><input type="checkbox" id="sup-title" <?php checked( $prefill['supports']['title'] ); ?>> <?php esc_html_e( 'Title', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-editor" <?php checked( $prefill['supports']['editor'] ); ?>> <?php esc_html_e( 'Editor', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-thumb" <?php checked( $prefill['supports']['thumbnail'] ); ?>> <?php esc_html_e( 'Featured Image', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-revs" <?php checked( $prefill['supports']['revisions'] ); ?>> <?php esc_html_e( 'Revisions', 'revmura' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'CPT Options', 'revmura' ); ?></th>
						<td>
							<label><input type="checkbox" id="opt-archive" <?php checked( $prefill['has_archive'] ); ?>> <?php esc_html_e( 'Has archive', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="opt-hier" <?php checked( $prefill['hierarchical'] ); ?>> <?php esc_html_e( 'Hierarchical', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="opt-meta" <?php checked( $prefill['map_meta_cap'] ); ?>> <?php esc_html_e( 'Map meta caps', 'revmura' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-menu-icon"><?php esc_html_e( 'Menu Icon (dashicon or URL)', 'revmura' ); ?></label></th>
						<td><input id="revmura-menu-icon" type="text" class="regular-text" value="<?php echo esc_attr( $prefill['menu_icon'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-menu-pos"><?php esc_html_e( 'Menu Position', 'revmura' ); ?></label></th>
						<td><input id="revmura-menu-pos" type="number" class="small-text" value="<?php echo esc_attr( (string) $prefill['menu_position'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-cap-type"><?php esc_html_e( 'Capability Type', 'revmura' ); ?></label></th>
						<td><input id="revmura-cap-type" type="text" class="regular-text" value="<?php echo esc_attr( $prefill['capability_type'] ); ?>"></td>
					</tr>

					<tr><th colspan="2"><hr></th></tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Optional Taxonomy', 'revmura' ); ?></th>
						<td>
							<label for="revmura-tax-slug"><?php esc_html_e( 'Tax Slug', 'revmura' ); ?></label>
							<input id="revmura-tax-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer_cat', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_slug'] ); ?>"><br><br>
							<label for="revmura-tax-label"><?php esc_html_e( 'Tax Label', 'revmura' ); ?></label>
							<input id="revmura-tax-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Offer Categories', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_label'] ); ?>"><br><br>
							<label for="revmura-tax-rewrite"><?php esc_html_e( 'Tax Rewrite', 'revmura' ); ?></label>
							<input id="revmura-tax-rewrite" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer-category', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_rewrite'] ); ?>"><br><br>
							<label><input type="checkbox" id="revmura-tax-hier" <?php checked( $prefill['tax_hier'] ); ?>> <?php esc_html_e( 'Hierarchical', 'revmura' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>

			<details style="margin-top:10px;">
				<summary><strong><?php esc_html_e( 'Preview JSON', 'revmura' ); ?></strong></summary>
				<textarea id="revmura-cpt-json" rows="12" style="width:100%;"></textarea>
			</details>

			<p style="margin-top:10px;">
				<button class="button button-primary" id="revmura-cpt-apply"><?php esc_html_e( 'Save & Register', 'revmura' ); ?></button>
				<button class="button" id="revmura-cpt-export"><?php esc_html_e( 'Export this CPT', 'revmura' ); ?></button>
				<button class="button button-link-delete" id="revmura-cpt-delete"><?php esc_html_e( 'Delete this CPT', 'revmura' ); ?></button>
				<input type="file" id="revmura-cpt-import-file" accept="application/json">
				<button class="button" id="revmura-cpt-import"><?php esc_html_e( 'Import JSON', 'revmura' ); ?></button>
			</p>
		</div>
		<?php
		// JS helpers.
		$db_js = wp_json_encode(
			array(
				'cpts'  => $cpts,
				'taxes' => $taxes,
			),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
		);
		if ( ! is_string( $db_js ) ) {
			$db_js = '{"cpts":{},"taxes":[]}';
		}
		?>
		<script>
		(() => {
			const postUrl     = '<?php echo esc_url( $post_url ); ?>';
			const applyAction = 'revmura_cpt_apply';
			const exportAction= 'revmura_cpt_export_one';
			const deleteAction= 'revmura_cpt_delete';
			const applyNonce  = '<?php echo esc_js( $apply_nonce ); ?>';
			const exportNonce = '<?php echo esc_js( $export_nonce ); ?>';
			const deleteNonce = '<?php echo esc_js( $delete_nonce ); ?>';
			const db = <?php echo $db_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

			const els = {
				select: document.getElementById('revmura-cpt-select'),
				slug: document.getElementById('revmura-cpt-slug'),
				label: document.getElementById('revmura-cpt-label'),
				rew: document.getElementById('revmura-rewrite-slug'),
				supTitle: document.getElementById('sup-title'),
				supEditor: document.getElementById('sup-editor'),
				supThumb: document.getElementById('sup-thumb'),
				supRevs: document.getElementById('sup-revs'),
				optArchive: document.getElementById('opt-archive'),
				optHier: document.getElementById('opt-hier'),
				optMeta: document.getElementById('opt-meta'),
				menuIcon: document.getElementById('revmura-menu-icon'),
				menuPos: document.getElementById('revmura-menu-pos'),
				capType: document.getElementById('revmura-cap-type'),
				taxSlug: document.getElementById('revmura-tax-slug'),
				taxLabel: document.getElementById('revmura-tax-label'),
				taxRew: document.getElementById('revmura-tax-rewrite'),
				taxHier: document.getElementById('revmura-tax-hier'),
				jsonTa: document.getElementById('revmura-cpt-json'),
				btnDelete: document.getElementById('revmura-cpt-delete'),
			};

			function arrayHas(arr, val){ return Array.isArray(arr) && arr.indexOf(val) !== -1; }

			function loadFromSlug(slug) {
				// Reset defaults first.
				els.slug.value = slug || '';
				els.label.value = '';
				els.rew.value = '';
				els.supTitle.checked = true;
				els.supEditor.checked = true;
				els.supThumb.checked = true;
				els.supRevs.checked = true;
				els.optArchive.checked = false;
				els.optHier.checked = false;
				els.optMeta.checked = false;
				els.capType.value = 'post';
				els.menuIcon.value = 'dashicons-admin-post';
				els.menuPos.value = '26';
				els.taxSlug.value = '';
				els.taxLabel.value = '';
				els.taxRew.value = '';
				els.taxHier.checked = false;

				if (!slug || !db.cpts || !db.cpts[slug]) {
					renderPreview();
					return;
				}
				const cfg = db.cpts[slug];
				els.label.value = (cfg.label || slug);
				els.rew.value = (cfg.rewrite && cfg.rewrite.slug) ? cfg.rewrite.slug : slug;

				const supports = (cfg.supports || []);
				els.supTitle.checked = arrayHas(supports, 'title');
				els.supEditor.checked = arrayHas(supports, 'editor');
				els.supThumb.checked  = arrayHas(supports, 'thumbnail');
				els.supRevs.checked   = arrayHas(supports, 'revisions');

				els.optArchive.checked = !!cfg.has_archive;
				els.optHier.checked    = !!cfg.hierarchical;
				els.optMeta.checked    = !!cfg.map_meta_cap;
				els.capType.value      = (cfg.capability_type || 'post');
				els.menuIcon.value     = (cfg.menu_icon || 'dashicons-admin-post');
				els.menuPos.value      = String(cfg.menu_position != null ? cfg.menu_position : 26);

				// First tax pointing to this CPT.
				if (Array.isArray(db.taxes)) {
					for (const t of db.taxes) {
						if (t && Array.isArray(t.object_types) && t.object_types.indexOf(slug) !== -1) {
							els.taxSlug.value  = t.slug || '';
							els.taxLabel.value = t.label || '';
							els.taxRew.value   = (t.rewrite && t.rewrite.slug) ? t.rewrite.slug : (t.slug || '');
							els.taxHier.checked= !!t.hierarchical;
							break;
						}
					}
				}

				renderPreview();
			}
		// Builder/edit form.
		?>
		<div class="card" style="max-width:900px;padding:16px;">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="revmura-cpt-slug"><?php esc_html_e( 'CPT Slug', 'revmura' ); ?></label></th>
						<td><input id="revmura-cpt-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['slug'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-cpt-label"><?php esc_html_e( 'CPT Label', 'revmura' ); ?></label></th>
						<td><input id="revmura-cpt-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Offers', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-rewrite-slug"><?php esc_html_e( 'Rewrite Slug', 'revmura' ); ?></label></th>
						<td><input id="revmura-rewrite-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offers', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['rewrite_slug'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Supports', 'revmura' ); ?></th>
						<td>
							<label><input type="checkbox" id="sup-title" <?php checked( $prefill['supports']['title'] ); ?>> <?php esc_html_e( 'Title', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-editor" <?php checked( $prefill['supports']['editor'] ); ?>> <?php esc_html_e( 'Editor', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-thumb" <?php checked( $prefill['supports']['thumbnail'] ); ?>> <?php esc_html_e( 'Featured Image', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="sup-revs" <?php checked( $prefill['supports']['revisions'] ); ?>> <?php esc_html_e( 'Revisions', 'revmura' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'CPT Options', 'revmura' ); ?></th>
						<td>
							<label><input type="checkbox" id="opt-archive" <?php checked( $prefill['has_archive'] ); ?>> <?php esc_html_e( 'Has archive', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="opt-hier" <?php checked( $prefill['hierarchical'] ); ?>> <?php esc_html_e( 'Hierarchical', 'revmura' ); ?></label><br>
							<label><input type="checkbox" id="opt-meta" <?php checked( $prefill['map_meta_cap'] ); ?>> <?php esc_html_e( 'Map meta caps', 'revmura' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-menu-icon"><?php esc_html_e( 'Menu Icon (dashicon or URL)', 'revmura' ); ?></label></th>
						<td><input id="revmura-menu-icon" type="text" class="regular-text" value="<?php echo esc_attr( $prefill['menu_icon'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-menu-pos"><?php esc_html_e( 'Menu Position', 'revmura' ); ?></label></th>
						<td><input id="revmura-menu-pos" type="number" class="small-text" value="<?php echo esc_attr( (string) $prefill['menu_position'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="revmura-cap-type"><?php esc_html_e( 'Capability Type', 'revmura' ); ?></label></th>
						<td><input id="revmura-cap-type" type="text" class="regular-text" value="<?php echo esc_attr( $prefill['capability_type'] ); ?>"></td>
					</tr>

					<tr><th colspan="2"><hr></th></tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Optional Taxonomy', 'revmura' ); ?></th>
						<td>
							<label for="revmura-tax-slug"><?php esc_html_e( 'Tax Slug', 'revmura' ); ?></label>
							<input id="revmura-tax-slug" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer_cat', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_slug'] ); ?>"><br><br>
							<label for="revmura-tax-label"><?php esc_html_e( 'Tax Label', 'revmura' ); ?></label>
							<input id="revmura-tax-label" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Offer Categories', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_label'] ); ?>"><br><br>
							<label for="revmura-tax-rewrite"><?php esc_html_e( 'Tax Rewrite', 'revmura' ); ?></label>
							<input id="revmura-tax-rewrite" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. offer-category', 'revmura' ); ?>" value="<?php echo esc_attr( $prefill['tax_rewrite'] ); ?>"><br><br>
							<label><input type="checkbox" id="revmura-tax-hier" <?php checked( $prefill['tax_hier'] ); ?>> <?php esc_html_e( 'Hierarchical', 'revmura' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>

			<details style="margin-top:10px;">
				<summary><strong><?php esc_html_e( 'Preview JSON', 'revmura' ); ?></strong></summary>
				<textarea id="revmura-cpt-json" rows="12" style="width:100%;"></textarea>
			</details>

			<p style="margin-top:10px;">
				<button class="button button-primary" id="revmura-cpt-apply"><?php esc_html_e( 'Save & Register', 'revmura' ); ?></button>
				<button class="button" id="revmura-cpt-export"><?php esc_html_e( 'Export this CPT', 'revmura' ); ?></button>
				<button class="button button-link-delete" id="revmura-cpt-delete"><?php esc_html_e( 'Delete this CPT', 'revmura' ); ?></button>
				<input type="file" id="revmura-cpt-import-file" accept="application/json">
				<button class="button" id="revmura-cpt-import"><?php esc_html_e( 'Import JSON', 'revmura' ); ?></button>
			</p>
		</div>
		<?php
		// JS helpers.
		$db_js = wp_json_encode(
			array(
				'cpts'  => $cpts,
				'taxes' => $taxes,
			),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
		);
		if ( ! is_string( $db_js ) ) {
			$db_js = '{"cpts":{},"taxes":[]}';
		}
		?>
		<script>
		(() => {
			const postUrl     = '<?php echo esc_url( $post_url ); ?>';
			const applyAction = 'revmura_cpt_apply';
			const exportAction= 'revmura_cpt_export_one';
			const deleteAction= 'revmura_cpt_delete';
			const applyNonce  = '<?php echo esc_js( $apply_nonce ); ?>';
			const exportNonce = '<?php echo esc_js( $export_nonce ); ?>';
			const deleteNonce = '<?php echo esc_js( $delete_nonce ); ?>';
			const db = <?php echo $db_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

			const els = {
				select: document.getElementById('revmura-cpt-select'),
				slug: document.getElementById('revmura-cpt-slug'),
				label: document.getElementById('revmura-cpt-label'),
				rew: document.getElementById('revmura-rewrite-slug'),
				supTitle: document.getElementById('sup-title'),
				supEditor: document.getElementById('sup-editor'),
				supThumb: document.getElementById('sup-thumb'),
				supRevs: document.getElementById('sup-revs'),
				optArchive: document.getElementById('opt-archive'),
				optHier: document.getElementById('opt-hier'),
				optMeta: document.getElementById('opt-meta'),
				menuIcon: document.getElementById('revmura-menu-icon'),
				menuPos: document.getElementById('revmura-menu-pos'),
				capType: document.getElementById('revmura-cap-type'),
				taxSlug: document.getElementById('revmura-tax-slug'),
				taxLabel: document.getElementById('revmura-tax-label'),
				taxRew: document.getElementById('revmura-tax-rewrite'),
				taxHier: document.getElementById('revmura-tax-hier'),
				jsonTa: document.getElementById('revmura-cpt-json'),
				btnDelete: document.getElementById('revmura-cpt-delete'),
			};

			function arrayHas(arr, val){ return Array.isArray(arr) && arr.indexOf(val) !== -1; }

			function loadFromSlug(slug) {
				// Reset defaults first.
				els.slug.value = slug || '';
				els.label.value = '';
				els.rew.value = '';
				els.supTitle.checked = true;
				els.supEditor.checked = true;
				els.supThumb.checked = true;
				els.supRevs.checked = true;
				els.optArchive.checked = false;
				els.optHier.checked = false;
				els.optMeta.checked = false;
				els.capType.value = 'post';
				els.menuIcon.value = 'dashicons-admin-post';
				els.menuPos.value = '26';
				els.taxSlug.value = '';
				els.taxLabel.value = '';
				els.taxRew.value = '';
				els.taxHier.checked = false;

				if (!slug || !db.cpts || !db.cpts[slug]) {
					renderPreview();
					return;
				}
				const cfg = db.cpts[slug];
				els.label.value = (cfg.label || slug);
				els.rew.value = (cfg.rewrite && cfg.rewrite.slug) ? cfg.rewrite.slug : slug;

				const supports = (cfg.supports || []);
				els.supTitle.checked = arrayHas(supports, 'title');
				els.supEditor.checked = arrayHas(supports, 'editor');
				els.supThumb.checked  = arrayHas(supports, 'thumbnail');
				els.supRevs.checked   = arrayHas(supports, 'revisions');

				els.optArchive.checked = !!cfg.has_archive;
				els.optHier.checked    = !!cfg.hierarchical;
				els.optMeta.checked    = !!cfg.map_meta_cap;
				els.capType.value      = (cfg.capability_type || 'post');
				els.menuIcon.value     = (cfg.menu_icon || 'dashicons-admin-post');
				els.menuPos.value      = String(cfg.menu_position != null ? cfg.menu_position : 26);

				// First tax pointing to this CPT.
				if (Array.isArray(db.taxes)) {
					for (const t of db.taxes) {
						if (t && Array.isArray(t.object_types) && t.object_types.indexOf(slug) !== -1) {
							els.taxSlug.value  = t.slug || '';
							els.taxLabel.value = t.label || '';
							els.taxRew.value   = (t.rewrite && t.rewrite.slug) ? t.rewrite.slug : (t.slug || '');
							els.taxHier.checked= !!t.hierarchical;
							break;
						}
					}
				}

				renderPreview();
			}

			function buildSchema() {
				const slug = (els.slug.value || '').trim();
				const label = (els.label.value || '').trim();
				const rew = (els.rew.value || '').trim();

				const supports = [];
				if (els.supTitle.checked) supports.push('title');
				if (els.supEditor.checked) supports.push('editor');
				if (els.supThumb.checked) supports.push('thumbnail');
				if (els.supRevs.checked) supports.push('revisions');

				const cpt = {};
				if (label) cpt.label = label;
				if (supports.length) cpt.supports = supports;
				cpt.rewrite = { slug: rew || slug, with_front: false };
				cpt.has_archive = !!els.optArchive.checked;
				cpt.hierarchical = !!els.optHier.checked;
				cpt.map_meta_cap = !!els.optMeta.checked;
				cpt.capability_type = (els.capType.value || 'post').trim() || 'post';
				cpt.menu_icon = (els.menuIcon.value || 'dashicons-admin-post').trim() || 'dashicons-admin-post';
				cpt.menu_position = parseInt(els.menuPos.value || '26', 10);

				const taxes = [];
				const taxSlug = (els.taxSlug.value || '').trim();
				const taxLabel = (els.taxLabel.value || '').trim();
				const taxRew = (els.taxRew.value || '').trim();
				if (taxSlug && taxLabel && slug) {
					taxes.push({
						slug: taxSlug,
						label: taxLabel,
						rewrite: { slug: taxRew || taxSlug, with_front: false },
						hierarchical: !!els.taxHier.checked,
						object_types: [slug]
					});
				}

				const obj = {
					schema_version: '1.0',
					cpts: {},
					taxes
				};
				if (slug) obj.cpts[slug] = cpt;
				return obj;
			}

			function renderPreview() {
				const obj = buildSchema();
				els.jsonTa.value = JSON.stringify(obj, null, 2);
			}

			// Populate form when selecting an existing CPT.
			els.select.addEventListener('change', () => {
				loadFromSlug(els.select.value);
			});

			// Render preview on load & when inputs change.
			document.querySelectorAll('.form-table input').forEach(el => {
				el.addEventListener('input', renderPreview);
				el.addEventListener('change', renderPreview);
			});

			// Initial state: load selected option if any; else default.
			if (els.select.value) loadFromSlug(els.select.value); else renderPreview();

			async function applySchema(obj) {
				const res = await fetch(`${postUrl}?action=${applyAction}`, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-Revmura-Nonce': applyNonce
					},
					body: JSON.stringify(obj)
				});
				const text = await res.text();
				let data;
				try { data = JSON.parse(text); } catch { data = { ok:false, error:text }; }
				alert(JSON.stringify(data, null, 2));
				// After save, reload to refresh selector/options.
				if (data && data.ok) window.location.reload();
				return data;
			}

			document.getElementById('revmura-cpt-apply').addEventListener('click', async (e) => {
				e.preventDefault();
				const obj = buildSchema();
				if (!Object.keys(obj.cpts).length) {
					alert('Please enter a CPT slug and label.');
					return;
				}
				await applySchema(obj);
			});

			document.getElementById('revmura-cpt-export').addEventListener('click', (e) => {
				e.preventDefault();
				const slug = (els.slug.value || '').trim();
				if (!slug) { alert('Enter the CPT slug first.'); return; }
				const url = `${postUrl}?action=${exportAction}&_wpnonce=${encodeURIComponent(exportNonce)}&slug=${encodeURIComponent(slug)}`;
				window.location.href = url;
			});

			document.getElementById('revmura-cpt-import').addEventListener('click', async (e) => {
				e.preventDefault();
				const f = document.getElementById('revmura-cpt-import-file').files[0];
				if (!f) { alert('Choose a JSON file.'); return; }
				const text = await f.text();
				let obj;
				try { obj = JSON.parse(text); } catch { alert('Invalid JSON.'); return; }
				await applySchema(obj);
			});

			els.btnDelete.addEventListener('click', (e) => {
				e.preventDefault();
				const slug = (els.slug.value || '').trim();
				if (!slug) { alert('No CPT slug to delete.'); return; }
				if (!confirm(`Delete CPT "${slug}"? Linked taxes will be updated.`)) return;
				const url = `${postUrl}?action=${deleteAction}&_wpnonce=${encodeURIComponent(deleteNonce)}&slug=${encodeURIComponent(slug)}`;
				window.location.href = url;
			});
		})();
		</script>
		<?php
		echo '</div>';
	}

	/**
	 * Fill the prefill array from a given CPT and its first linked taxonomy.
	 *
	 * @param array<string,mixed> $prefill Prefill (by reference).
	 * @param array<string,mixed> $cpts    CPTs map.
	 * @param array<int,mixed>    $taxes   Taxonomies list.
	 * @param string              $slug    CPT slug to load.
	 * @return void
	 */
	private static function fill_from_cpt_and_tax( array &$prefill, array $cpts, array $taxes, string $slug ): void {
		$cfg = $cpts[ $slug ] ?? array();
		if ( ! is_array( $cfg ) ) {
			return;
		}
		$prefill['slug']         = $slug;
		$prefill['label']        = (string) ( $cfg['label'] ?? $slug );
		$prefill['rewrite_slug'] = (string) ( $cfg['rewrite']['slug'] ?? $slug );

		$supports = (array) ( $cfg['supports'] ?? array() );
		foreach ( array( 'title', 'editor', 'thumbnail', 'revisions' ) as $s ) {
			$prefill['supports'][ $s ] = in_array( $s, $supports, true );
		}

		$prefill['has_archive']     = (bool) ( $cfg['has_archive'] ?? false );
		$prefill['hierarchical']    = (bool) ( $cfg['hierarchical'] ?? false );
		$prefill['map_meta_cap']    = (bool) ( $cfg['map_meta_cap'] ?? false );
		$prefill['capability_type'] = (string) ( $cfg['capability_type'] ?? 'post' );
		$prefill['menu_icon']       = (string) ( $cfg['menu_icon'] ?? 'dashicons-admin-post' );
		$prefill['menu_position']   = (int) ( $cfg['menu_position'] ?? 26 );

		// First taxonomy linking to this CPT.
		foreach ( $taxes as $tax ) {
			if ( is_array( $tax ) && ! empty( $tax['object_types'] ) && in_array( $slug, (array) $tax['object_types'], true ) ) {
				$prefill['tax_slug']    = (string) ( $tax['slug'] ?? '' );
				$prefill['tax_label']   = (string) ( $tax['label'] ?? '' );
				$prefill['tax_rewrite'] = (string) ( $tax['rewrite']['slug'] ?? '' );
				$prefill['tax_hier']    = (bool) ( $tax['hierarchical'] ?? false );
				break;
			}
		}
	}

	/**
	 * Handle "Save & Register": persist schema, write LKG, register now, flush.
	 *
	 * @return void
	 */
	public static function handle_apply(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json(
				array(
					'ok'    => false,
					'error' => 'forbidden',
				),
				403
			);
		}

		$nonce = isset( $_SERVER['HTTP_X_REVMURA_NONCE'] ) ? (string) $_SERVER['HTTP_X_REVMURA_NONCE'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by wp_verify_nonce
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'revmura_cpt_apply' ) ) {
			wp_send_json(
				array(
					'ok'    => false,
					'error' => 'bad_nonce',
				),
				403
			);
		}

		// Read JSON request body.
		$raw  = (string) file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading JSON request body.
		$data = json_decode( $raw, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_decode_json_decode
		if ( ! is_array( $data ) ) {
			wp_send_json(
				array(
					'ok'    => false,
					'error' => 'invalid_json',
				),
				400
			);
		}

		// Normalize.
		$data['schema_version'] = isset( $data['schema_version'] ) ? (string) $data['schema_version'] : '1.0';
		if ( ! isset( $data['cpts'] ) || ! is_array( $data['cpts'] ) ) {
			$data['cpts'] = array();
		}
		if ( ! isset( $data['taxes'] ) || ! is_array( $data['taxes'] ) ) {
			$data['taxes'] = array();
		}

		update_option( 'revmura_cpt_schema', $data, false );

		// Ask Core to write LKG cache file (MU Guard uses it).
		do_action( 'revmura_core_committed', $data );

		// Register now.
		self::register_from_snapshot( $data );

		// Rewrite rules now (pretty permalinks).
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		wp_send_json(
			array(
				'ok'         => true,
				'registered' => array(
					'cpts'  => array_keys( $data['cpts'] ),
					'taxes' => array_values(
						array_map(
							static fn( $t ) => isset( $t['slug'] ) ? (string) $t['slug'] : '',
							$data['taxes']
						)
					),
				),
				'flushed'    => true,
			),
			200
		);
	}

	/**
	 * Export a single CPT and its linked taxes as a JSON download.
	 *
	 * @return void
	 */
	public static function handle_export_one(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}
		check_admin_referer( 'revmura_cpt_export_one' );

		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( (string) $_GET['slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_die( esc_html__( 'Missing CPT slug.', 'revmura' ) );
		}

		$schema = get_option(
			'revmura_cpt_schema',
			array(
				'schema_version' => '1.0',
				'cpts'           => array(),
				'taxes'          => array(),
			)
		);

		$out = array(
			'schema_version' => '1.0',
			'cpts'           => array(),
			'taxes'          => array(),
		);

		if ( isset( $schema['cpts'][ $slug ] ) && is_array( $schema['cpts'][ $slug ] ) ) {
			$out['cpts'][ $slug ] = $schema['cpts'][ $slug ];
			if ( ! empty( $schema['taxes'] ) && is_array( $schema['taxes'] ) ) {
				foreach ( $schema['taxes'] as $tax ) {
					if ( is_array( $tax )
						&& ! empty( $tax['object_types'] )
						&& in_array( $slug, (array) $tax['object_types'], true )
					) {
						$out['taxes'][] = $tax;
					}
				}
			}
		}

		$json = wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_headers, WordPressVIPMinimum.Headers.HeaderManipulation
		header( 'Content-Disposition: attachment; filename="cpt-' . $slug . '.json"' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_headers, WordPressVIPMinimum.Headers.HeaderManipulation
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Delete a CPT and clean linked taxes (remove CPT from object_types; drop empty taxes).
	 * Flush rewrite rules afterwards.
	 *
	 * @return void
	 */
	public static function handle_delete_cpt(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied', 'revmura' ) );
		}
		check_admin_referer( 'revmura_cpt_delete' );

		$slug = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( (string) $_GET['slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_die( esc_html__( 'Missing CPT slug.', 'revmura' ) );
		}

		$schema = get_option(
			'revmura_cpt_schema',
			array(
				'schema_version' => '1.0',
				'cpts'           => array(),
				'taxes'          => array(),
			)
		);
		$cpts   = is_array( $schema['cpts'] ?? null ) ? $schema['cpts'] : array();
		$taxes  = is_array( $schema['taxes'] ?? null ) ? $schema['taxes'] : array();

		// Remove CPT.
		if ( isset( $cpts[ $slug ] ) ) {
			unset( $cpts[ $slug ] );
		}

		// Update taxes: drop slug from object_types; drop tax if empty after removal.
		$new_taxes = array();
		foreach ( $taxes as $tax ) {
			if ( ! is_array( $tax ) ) {
				continue;
			}
			$objs = (array) ( $tax['object_types'] ?? array() );
			$objs = array_values( array_filter( $objs, static fn( $o ) => (string) $o !== $slug ) );
			if ( empty( $objs ) ) {
				continue; // Drop tax if no objects left.
			}
			$tax['object_types'] = $objs;
			$new_taxes[]         = $tax;
		}

		$new_schema = array(
			'schema_version' => '1.0',
			'cpts'           => $cpts,
			'taxes'          => $new_taxes,
		);

		update_option( 'revmura_cpt_schema', $new_schema, false );
		do_action( 'revmura_core_committed', $new_schema );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'revmura',
					'tab'     => 'cpt',
					'deleted' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Register CPTs and taxonomies from a snapshot array (same shape as Core exporter).
	 *
	 * @param array<string,mixed> $snapshot Snapshot of schema.
	 * @return void
	 */
	public static function register_from_snapshot( array $snapshot ): void {
		$cpts  = isset( $snapshot['cpts'] ) && is_array( $snapshot['cpts'] ) ? $snapshot['cpts'] : array();
		$taxes = isset( $snapshot['taxes'] ) && is_array( $snapshot['taxes'] ) ? $snapshot['taxes'] : array();

		foreach ( $cpts as $slug => $cfg ) {
			$slug = sanitize_key( (string) $slug );
			if ( ! is_string( $slug ) || '' === $slug || ! is_array( $cfg ) ) {
				continue;
			}

			$label        = (string) ( $cfg['label'] ?? $slug );
			$rewrite_slug = (string) ( $cfg['rewrite']['slug'] ?? $slug );

			$supports = (array) ( $cfg['supports'] ?? array( 'title', 'editor', 'thumbnail', 'revisions' ) );

			$args = array(
				'label'           => $label,
				'public'          => true,
				'show_in_rest'    => true,
				'supports'        => $supports,
				'rewrite'         => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
				'has_archive'     => (bool) ( $cfg['has_archive'] ?? false ),
				'hierarchical'    => (bool) ( $cfg['hierarchical'] ?? false ),
				'map_meta_cap'    => (bool) ( $cfg['map_meta_cap'] ?? false ),
				'capability_type' => (string) ( $cfg['capability_type'] ?? 'post' ),
				'menu_icon'       => (string) ( $cfg['menu_icon'] ?? 'dashicons-admin-post' ),
				'menu_position'   => (int) ( $cfg['menu_position'] ?? 26 ),
				'show_ui'         => true,
				'show_in_menu'    => true,
			);

			register_post_type( $slug, $args );
		}

		// Register taxonomies.
		foreach ( $taxes as $tax ) {
			if ( ! is_array( $tax ) ) {
				continue;
			}
			$tax_slug  = (string) ( $tax['slug'] ?? '' );
			$tax_label = (string) ( $tax['label'] ?? $tax_slug );
			$tax_rew   = (string) ( $tax['rewrite']['slug'] ?? $tax_slug );
			$tax_hier  = (bool) ( $tax['hierarchical'] ?? false );
			$obj_types = (array) ( $tax['object_types'] ?? array() );
			$tax_slug  = sanitize_key( $tax_slug );
			$obj_types = array_map( 'sanitize_key', $obj_types );

			if ( '' === $tax_slug || empty( $obj_types ) ) {
				continue;
			}

			register_taxonomy(
				$tax_slug,
				$obj_types,
				array(
					'label'        => $tax_label,
					'show_in_rest' => true,
					'hierarchical' => $tax_hier,
					'rewrite'      => array(
						'slug'       => $tax_rew,
						'with_front' => false,
					),
				)
			);
		}
	}

	/**
	 * Lifecycle hook: enable.
	 *
	 * @return void
	 */
	public function on_enable(): void {
		// No-op for now.
	}

	/**
	 * Lifecycle hook: disable.
	 *
	 * @return void
	 */
	public function on_disable(): void {
		// No-op for now.
	}

	/**
	 * Lifecycle hook: uninstall.
	 * Clears stored schema and rewrites LKG cache; flushes rewrites.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$empty = array(
			'schema_version' => '1.0',
			'cpts'           => array(),
			'taxes'          => array(),
		);

		delete_option( 'revmura_cpt_schema' );
		do_action( 'revmura_core_committed', $empty );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		$empty = array(
			'schema_version' => '1.0',
			'cpts'           => array(),
			'taxes'          => array(),
		);

		delete_option( 'revmura_cpt_schema' );
		do_action( 'revmura_core_committed', $empty );

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}
}
