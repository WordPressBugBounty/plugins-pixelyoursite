<?php
/**
 * Protects the shared `mcp_adapter_init` action from cross-copy fatals.
 *
 * Several plugins now bundle `wordpress/mcp-adapter`: PYS ships a php-scoper
 * prefixed copy (`PYS_PRO_GLOBAL\WP\MCP\…` — Free reuses the Pro prefix) while
 * WooCommerce, Angie and others ship the un-prefixed one (`WP\MCP\…`).
 * php-scoper rewrites classes but NOT hook-name strings, so every copy fires
 * the very same global `mcp_adapter_init` action and every registered callback
 * receives whichever adapter instance happened to fire it.
 *
 * Callbacks that declare a strict parameter type then fatal on a foreign
 * adapter, e.g.:
 *
 *   Angie\Modules\WpAbilities\Classes\Mcp_Server_Registrar::register_servers():
 *   Argument #1 ($adapter) must be of type WP\MCP\Core\McpAdapter,
 *   PYS_PRO_GLOBAL\WP\MCP\Core\McpAdapter given
 *
 * Our own callbacks are untyped and guarded with `instanceof`, so foreign
 * adapters never break us. This guard covers the opposite direction: while OUR
 * adapter fires the action, callbacks that cannot accept our instance are
 * detached and restored immediately afterwards, so each plugin only ever sees
 * an adapter it can actually handle. Nothing is removed permanently — the
 * detached callbacks keep running for their own adapter's init.
 *
 * @package PixelYourSite\MCP
 */

declare( strict_types = 1 );

namespace PixelYourSite\MCP;

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AdapterHookGuard {

	/** Shared (un-prefixable) action name every adapter copy fires. */
	private const HOOK = 'mcp_adapter_init';

	/**
	 * Callbacks detached for the duration of the current do_action, as
	 * [ callback, priority, accepted_args ] tuples.
	 *
	 * @var array<int, array{0: mixed, 1: int, 2: int}>
	 */
	private static array $detached = array();

	/**
	 * Wrap the shared action: strip incompatible callbacks first, restore them
	 * last. Registered once from McpServer::boot().
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'detach_incompatible' ), PHP_INT_MIN, 1 );
		add_action( self::HOOK, array( self::class, 'restore_detached' ), PHP_INT_MAX, 1 );
	}

	/**
	 * Detach every callback whose first parameter cannot accept the adapter
	 * instance currently being passed around.
	 *
	 * @param mixed $adapter The adapter instance firing the action.
	 * @return void
	 */
	public static function detach_incompatible( $adapter = null ): void {
		self::$detached = array();

		$hook = isset( $GLOBALS['wp_filter'][ self::HOOK ] ) ? $GLOBALS['wp_filter'][ self::HOOK ] : null;
		if ( !$hook instanceof \WP_Hook ) {
			return;
		}

		// foreach iterates a snapshot, so removing entries mid-loop is safe.
		foreach ( $hook->callbacks as $priority => $entries ) {
			foreach ( $entries as $entry ) {
				$callback      = $entry['function'];
				$accepted_args = (int) $entry['accepted_args'];

				// The adapter is never handed to a zero-arg callback.
				if ( 0 === $accepted_args ) {
					continue;
				}
				if ( self::accepts_argument( $callback, $adapter ) ) {
					continue;
				}

				self::$detached[] = array( $callback, (int) $priority, $accepted_args );
				remove_action( self::HOOK, $callback, (int) $priority );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'PYS MCP: skipped incompatible %s callback %s for %s.',
						self::HOOK,
						self::describe_callback( $callback ),
						is_object( $adapter ) ? get_class( $adapter ) : gettype( $adapter )
					) );
				}
			}
		}
	}

	/**
	 * Put the detached callbacks back so their own adapter's init still
	 * reaches them. Re-adding at a priority already passed by the running
	 * iteration does not re-fire them for this pass.
	 *
	 * @return void
	 */
	public static function restore_detached(): void {
		foreach ( self::$detached as $entry ) {
			list( $callback, $priority, $accepted_args ) = $entry;
			add_action( self::HOOK, $callback, $priority, $accepted_args );
		}

		self::$detached = array();
	}

	/**
	 * Whether the callback's first parameter tolerates the given value.
	 * Anything we cannot inspect is left alone.
	 *
	 * @param mixed $callback The hook callback.
	 * @param mixed $value    The value the action passes.
	 * @return bool
	 */
	private static function accepts_argument( $callback, $value ): bool {
		try {
			$reflection = self::reflect_callback( $callback );
		} catch ( \Throwable $e ) {
			return true;
		}

		if ( null === $reflection ) {
			return true;
		}

		$parameters = $reflection->getParameters();
		if ( empty( $parameters ) ) {
			return true; // Extra args are ignored by PHP for non-variadic calls.
		}

		$type = $parameters[0]->getType();
		if ( null === $type ) {
			return true; // Untyped — accepts anything.
		}

		return self::type_accepts( $type, $value );
	}

	/**
	 * Resolve a reflection object for any callable shape WP stores.
	 *
	 * @param mixed $callback The hook callback.
	 * @return \ReflectionFunctionAbstract|null Null when it cannot be resolved.
	 */
	private static function reflect_callback( $callback ): ?\ReflectionFunctionAbstract {
		if ( $callback instanceof \Closure ) {
			return new \ReflectionFunction( $callback );
		}

		if ( is_string( $callback ) ) {
			if ( false !== strpos( $callback, '::' ) ) {
				list( $target, $method ) = explode( '::', $callback, 2 );

				return method_exists( $target, $method ) ? new \ReflectionMethod( $target, $method ) : null;
			}

			return function_exists( $callback ) ? new \ReflectionFunction( $callback ) : null;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$target = $callback[0];
			$method = $callback[1];

			// method_exists() keeps __call()-only callbacks out of reflection.
			return method_exists( $target, $method ) ? new \ReflectionMethod( $target, $method ) : null;
		}

		if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			return new \ReflectionMethod( $callback, '__invoke' );
		}

		return null;
	}

	/**
	 * Whether a declared parameter type accepts the value.
	 *
	 * @param \ReflectionType $type  The declared type.
	 * @param mixed           $value The value the action passes.
	 * @return bool
	 */
	private static function type_accepts( \ReflectionType $type, $value ): bool {
		if ( null === $value && $type->allowsNull() ) {
			return true;
		}

		if ( $type instanceof \ReflectionNamedType ) {
			$name = $type->getName();

			if ( $type->isBuiltin() ) {
				// Only these builtins can hold an adapter object.
				return in_array( $name, array( 'mixed', 'object', 'iterable' ), true );
			}

			// Late static binding placeholders — assume the caller knows.
			if ( in_array( strtolower( $name ), array( 'self', 'static', 'parent' ), true ) ) {
				return true;
			}

			return $value instanceof $name;
		}

		// Union: one matching member is enough.
		if ( $type instanceof \ReflectionUnionType ) {
			foreach ( $type->getTypes() as $member ) {
				if ( self::type_accepts( $member, $value ) ) {
					return true;
				}
			}

			return false;
		}

		// Intersection: every member must match.
		if ( $type instanceof \ReflectionIntersectionType ) {
			foreach ( $type->getTypes() as $member ) {
				if ( !self::type_accepts( $member, $value ) ) {
					return false;
				}
			}

			return true;
		}

		return true;
	}

	/**
	 * Human-readable callback label for the debug log.
	 *
	 * @param mixed $callback The hook callback.
	 * @return string
	 */
	private static function describe_callback( $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$target = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];

			return $target . '::' . (string) $callback[1];
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return is_object( $callback ) ? get_class( $callback ) : gettype( $callback );
	}
}
