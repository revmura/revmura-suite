<?php
/**
 * Module registry (in-memory) for Revmura Suite.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

// phpcs:disable WordPress.Files.FileName

namespace Revmura\Suite\Models;

use Revmura\Suite\Models\Contracts\ModuleInterface;

final class ModuleRegistry {

	/**
	 * @var array<string, ModuleInterface>
	 */
	private static array $modules = array();

	/**
	 * Register a module instance.
	 *
	 * @param ModuleInterface $module Module instance.
	 * @return void
	 */
	public static function register( ModuleInterface $module ): void {
		self::$modules[ $module->id() ] = $module;
	}

	/**
	 * All modules (assoc by id).
	 *
	 * @return array<string, ModuleInterface>
	 */
	public static function all(): array {
		return self::$modules;
	}

	/**
	 * Find module by id.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface|null
	 */
	public static function find( string $id ): ?ModuleInterface {
		return self::$modules[ $id ] ?? null;
	}
}
