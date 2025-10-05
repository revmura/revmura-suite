<?php
/**
 * Module registry for Revmura Suite.
 *
 * Keeps a static list of registered modules and provides helpers to retrieve them.
 *
 * @package Revmura\Suite
 */

declare(strict_types=1);

namespace Revmura\Suite\Models;

use Revmura\Suite\Models\Contracts\ModuleInterface;

/**
 * In-memory registry for Suite modules.
 *
 * Stores module instances and exposes accessors for the loader and Manager UI.
 */
final class ModuleRegistry {

	/**
	 * Registered modules keyed by module id.
	 *
	 * @var array<string,ModuleInterface>
	 */
	private static array $modules = array();

	/**
	 * Register a module instance (id is taken from the instance).
	 *
	 * Last registration wins if the same id is used twice.
	 *
	 * @param ModuleInterface $module Module instance to register.
	 * @return void
	 */
	public static function register( ModuleInterface $module ): void {
		self::$modules[ $module->id() ] = $module;
	}

	/**
	 * Return all registered modules (values only).
	 *
	 * @return ModuleInterface[]
	 */
	public static function all(): array {
		return array_values( self::$modules );
	}

	/**
	 * Get a single module by id.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface|null
	 */
	public static function get( string $id ): ?ModuleInterface {
		return self::$modules[ $id ] ?? null;
	}
}
