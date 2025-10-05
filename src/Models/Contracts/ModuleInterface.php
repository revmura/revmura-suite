<?php
/**
 * Module interface for Revmura Suite modules.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

namespace Revmura\Suite\Models\Contracts;

interface ModuleInterface {
	/**
	 * Unique module id (slug).
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Human-readable label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Module semantic version (e.g., "1.2.0").
	 *
	 * @return string
	 */
	public function version(): string;

	/**
	 * Minimum required Core API version (e.g., "1.0.0").
	 *
	 * @return string
	 */
	public function required_core_api_min(): string;

	/**
	 * Minimum required WordPress version (e.g., "6.5").
	 *
	 * @return string
	 */
	public function required_wp_min(): string;

	/**
	 * Minimum required PHP version (e.g., "8.3").
	 *
	 * @return string
	 */
	public function required_php_min(): string;

	/**
	 * Boot module (register CPTs, hooks, panels, etc).
	 *
	 * @return void
	 */
	public function boot(): void;

	/**
	 * Called when user enables the module from the toggle UI (first time or re-enable).
	 *
	 * @return void
	 */
	public function on_enable(): void;

	/**
	 * Called when user disables the module from the toggle UI.
	 *
	 * @return void
	 */
	public function on_disable(): void;

	/**
	 * Delete all module-owned data (called by 'Delete data' action).
	 * Should be idempotent and safe to run when module is disabled.
	 *
	 * @return void
	 */
	public function uninstall(): void;
}
