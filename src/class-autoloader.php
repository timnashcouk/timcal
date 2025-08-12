<?php
/**
 * Custom Autoloader Class
 *
 * @package Timnashcouk\Timcal
 */

declare(strict_types=1);

namespace Timnashcouk\Timcal;

/**
 * Custom PSR-4 compliant autoloader for the plugin.
 *
 * This autoloader is independent from Composer and handles WordPress
 * file naming conventions (class-*.php, interface-*.php).
 *
 * @since 0.1.0
 */
final class Autoloader {

	/**
	 * Namespace prefix for this autoloader.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'Timnashcouk\\Timcal\\';

	/**
	 * Base directory for the namespace prefix.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Array of loaded classes for performance tracking.
	 *
	 * @since 0.1.0
	 * @var array<string, bool>
	 */
	private array $loaded_classes = array();

	/**
	 * Debug mode flag.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private bool $debug_mode = false;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param string $base_dir The base directory for the namespace prefix.
	 * @param bool   $debug_mode Whether to enable debug logging.
	 */
	public function __construct( string $base_dir, bool $debug_mode = false ) {
		$this->base_dir   = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		$this->debug_mode = $debug_mode;
	}

	/**
	 * Register the autoloader.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function register(): bool {
		return spl_autoload_register( array( $this, 'load_class' ) );
	}

	/**
	 * Unregister the autoloader.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function unregister(): bool {
		return spl_autoload_unregister( array( $this, 'load_class' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class The fully-qualified class name.
	 * @return void
	 */
	public function load_class( string $class ): void {
		// Guard clause: Check if class is already loaded.
		if ( isset( $this->loaded_classes[ $class ] ) ) {
			return;
		}

		// Guard clause: Check if class uses our namespace prefix.
		if ( ! $this->has_namespace_prefix( $class ) ) {
			return;
		}

		// Guard clause: Check if class already exists.
		if ( class_exists( $class, false ) || interface_exists( $class, false ) || trait_exists( $class, false ) ) {
			$this->loaded_classes[ $class ] = true;
			return;
		}

		// Get the relative class name.
		$relative_class = substr( $class, strlen( self::NAMESPACE_PREFIX ) );

		// Convert namespace to file path.
		$file_path = $this->get_file_path( $relative_class );

		// Try to load the file.
		$loaded = $this->load_file( $file_path, $class );

		// Cache the result.
		$this->loaded_classes[ $class ] = $loaded;
	}

	/**
	 * Check if a class name has the namespace prefix.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class The fully-qualified class name.
	 * @return bool True if the class has the namespace prefix, false otherwise.
	 */
	private function has_namespace_prefix( string $class ): bool {
		return 0 === strpos( $class, self::NAMESPACE_PREFIX );
	}

	/**
	 * Convert relative class name to file path.
	 *
	 * @since 0.1.0
	 *
	 * @param string $relative_class The relative class name.
	 * @return string The file path.
	 */
	private function get_file_path( string $relative_class ): string {
		// Replace namespace separators with directory separators.
		$file_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );

		// Split the path to get directory and class name.
		$path_parts = explode( DIRECTORY_SEPARATOR, $file_path );
		$class_name = array_pop( $path_parts );
		$directory  = implode( DIRECTORY_SEPARATOR, $path_parts );

		// Convert class name to WordPress file naming convention.
		$file_name = $this->convert_class_name_to_file_name( $class_name );

		// Build the full file path.
		$full_path = $this->base_dir;
		if ( ! empty( $directory ) ) {
			$full_path .= $directory . DIRECTORY_SEPARATOR;
		}
		$full_path .= $file_name;

		return $full_path;
	}

	/**
	 * Convert class name to WordPress file naming convention.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class_name The class name.
	 * @return string The file name.
	 */
	private function convert_class_name_to_file_name( string $class_name ): string {
		// Convert PascalCase to kebab-case (WordPress standard).
		// First handle underscores by converting them to hyphens.
		$class_name = str_replace( '_', '-', $class_name );

		// Then convert PascalCase to kebab-case.
		$kebab_case = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name ) );

		// Determine file prefix based on class type.
		$prefix = 'class-';

		// Check if it's an interface (starts with uppercase I or contains Interface or ends with Interface).
		if ( preg_match( '/^I[A-Z]/', $class_name ) || false !== strpos( $class_name, 'Interface' ) || 'Migration' === $class_name ) {
			$prefix = 'interface-';
		}

		// Check if it's a trait (contains Trait).
		if ( false !== strpos( $class_name, 'Trait' ) ) {
			$prefix = 'trait-';
		}

		return $prefix . $kebab_case . '.php';
	}

	/**
	 * Load a file if it exists.
	 *
	 * @since 0.1.0
	 *
	 * @param string $file_path The file path to load.
	 * @param string $class The class name being loaded.
	 * @return bool True if the file was loaded successfully, false otherwise.
	 */
	private function load_file( string $file_path, string $class ): bool {
		// Try the exact file path first.
		if ( $this->try_load_file( $file_path, $class ) ) {
			return true;
		}

		// Try case-insensitive loading for WordPress compatibility.
		$directory = dirname( $file_path );
		$filename  = basename( $file_path );

		if ( is_dir( $directory ) ) {
			$files = scandir( $directory );
			if ( false !== $files ) {
				foreach ( $files as $file ) {
					if ( 0 === strcasecmp( $file, $filename ) ) {
						$case_insensitive_path = $directory . DIRECTORY_SEPARATOR . $file;
						return $this->try_load_file( $case_insensitive_path, $class );
					}
				}
			}
		}

		// Log debug information if enabled.
		if ( $this->debug_mode ) {
			$this->log_debug( "Failed to load class: {$class} from path: {$file_path}" );
		}

		return false;
	}

	/**
	 * Try to load a specific file.
	 *
	 * @since 0.1.0
	 *
	 * @param string $file_path The file path to load.
	 * @param string $class The class name being loaded.
	 * @return bool True if the file was loaded successfully, false otherwise.
	 */
	private function try_load_file( string $file_path, string $class ): bool {
		// Guard clause: Check if file exists and is readable.
		if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		try {
			require_once $file_path;

			// Verify that the class/interface/trait was actually loaded.
			if ( class_exists( $class, false ) || interface_exists( $class, false ) || trait_exists( $class, false ) ) {
				if ( $this->debug_mode ) {
					$this->log_debug( "Successfully loaded class: {$class} from path: {$file_path}" );
				}
				return true;
			}

			// Log warning if file was loaded but class wasn't found.
			if ( $this->debug_mode ) {
				$this->log_debug( "File loaded but class not found: {$class} from path: {$file_path}" );
			}
		} catch ( \Throwable $e ) {
			// Log error if file loading failed.
			if ( $this->debug_mode ) {
				$this->log_debug( "Error loading class: {$class} from path: {$file_path}. Error: " . $e->getMessage() );
			}
		}

		return false;
	}

	/**
	 * Log debug information.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The debug message.
	 * @return void
	 */
	private function log_debug( string $message ): void {
		// Guard clause: Check if debug mode is enabled.
		if ( ! $this->debug_mode ) {
			return;
		}

		// Use WordPress error_log if available, otherwise use PHP error_log.
		if ( function_exists( 'error_log' ) ) {
			error_log( '[TimCal Autoloader] ' . $message );
		}
	}

	/**
	 * Get loaded classes count.
	 *
	 * @since 0.1.0
	 *
	 * @return int The number of classes loaded by this autoloader.
	 */
	public function get_loaded_classes_count(): int {
		return count( array_filter( $this->loaded_classes ) );
	}

	/**
	 * Get list of loaded classes.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string> Array of loaded class names.
	 */
	public function get_loaded_classes(): array {
		return array_keys( array_filter( $this->loaded_classes ) );
	}

	/**
	 * Clear the loaded classes cache.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->loaded_classes = array();
	}

	/**
	 * Check if a specific class was loaded by this autoloader.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class The fully-qualified class name.
	 * @return bool True if the class was loaded, false otherwise.
	 */
	public function is_class_loaded( string $class ): bool {
		return isset( $this->loaded_classes[ $class ] ) && $this->loaded_classes[ $class ];
	}

	/**
	 * Get autoloader statistics.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed> Array of autoloader statistics.
	 */
	public function get_statistics(): array {
		return array(
			'namespace_prefix' => self::NAMESPACE_PREFIX,
			'base_directory'   => $this->base_dir,
			'debug_mode'       => $this->debug_mode,
			'loaded_classes'   => $this->get_loaded_classes_count(),
			'total_attempts'   => count( $this->loaded_classes ),
			'success_rate'     => count( $this->loaded_classes ) > 0
				? round( ( $this->get_loaded_classes_count() / count( $this->loaded_classes ) ) * 100, 2 )
				: 0,
		);
	}
}
