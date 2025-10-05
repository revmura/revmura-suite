<?php
/**
 * Hello module (demo). Safe to remove/rename.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models\Hello;

use Revmura\Suite\Models\Contracts\ModuleInterface;

final class HelloModule implements ModuleInterface {

	public function id(): string {
		return 'hello';
	}

	public function label(): string {
		return __( 'Hello', 'revmura' );
	}

	public function version(): string {
		return '0.1.0';
	}

	public function required_core_api_min(): string {
		return '1.0.0';
	}

	public function required_wp_min(): string {
		return '6.5';
	}

	public function required_php_min(): string {
		return '8.3';
	}

	public function boot(): void {
		// Register its Manager panel at `init` to avoid early i18n.
		add_action(
			'init',
			static function (): void {
				if ( has_action( 'revmura_manager_register_panel' ) ) {
					do_action(
						'revmura_manager_register_panel',
						array(
							'id'        => 'hello',
							'label'     => __( 'Hello', 'revmura' ),
							'render_cb' => static function (): void {
								echo '<div class="wrap"><h2>' . esc_html__( 'Hello Module', 'revmura' ) . '</h2>';
								echo '<p>' . esc_html__( 'This is a demo module. You can remove it later.', 'revmura' ) . '</p></div>';
							},
						)
					);
				}
			},
			20
		);
	}

	public function on_enable(): void {
		// no-op for demo.
	}

	public function on_disable(): void {
		// no-op for demo.
	}

	public function uninstall(): void {
		// no-op for demo. Real modules should remove options/tables/meta etc.
	}
}
