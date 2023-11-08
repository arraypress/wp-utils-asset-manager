<?php
/**
 * Manages the inclusion and handling of WordPress scripts and styles.
 *
 * This class streamlines the process of registering, enqueuing, and managing
 * scripts and styles within WordPress. It simplifies setting default parameters,
 * handling file paths, and utilizing minified assets. Additionally, it provides
 * the ability to register assets with dependencies, versioning, and specific conditions
 * for loading. It includes checks for asset existence and utilities for minification.
 * The class also supports conditional asset loading based on WordPress admin screens
 * and can be extended with custom callbacks for asset registration.
 *
 * @package     ArrayPress/Utils/WP/Asset_Manager
 * @copyright   Copyright (c) 2023, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\Utils\WP;

use InvalidArgumentException;
use Exception;

/**
 * Manages the registration and enqueue of WordPress scripts and styles.
 *
 * This class handles the intricacies of asset management for WordPress, ensuring best practices
 * are adhered to and assets are correctly registered and enqueued.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Asset_Manager' ) ) :

	class Asset_Manager {

		/**
		 * @var string Default script version.
		 */
		private string $default_version = '';

		/**
		 * @var array Default allowed screens.
		 */
		private array $default_allowed_screens = [];

		/**
		 * @var string Default scope.
		 */
		private string $default_scope = '';

		/**
		 * @var string|null Default callback.
		 */
		private $default_callback = null;

		/**
		 * @var bool Use minified assets when SCRIPT_DEBUG is not defined or false.
		 */
		private bool $use_minified_assets = false;

		/**
		 * @var string Default script base path.
		 */
		private string $default_script_path = '';

		/**
		 * @var string Default style base path.
		 */
		private string $default_style_path = '';

		/**
		 * @var array Default script dependencies.
		 */
		private array $default_script_deps = [];

		/**
		 * @var array Default style dependencies.
		 */
		private array $default_style_deps = [];

		/**
		 * @var array Registered scripts.
		 */
		private array $scripts = [];

		/**
		 * @var array Registered styles.
		 */
		private array $styles = [];

		/**
		 * Constructor to initialize the class with default settings.
		 *
		 * @param array $args Configuration arguments for the Asset Manager.
		 *
		 * @throws Exception If validation fails for any of the provided asset settings.
		 */
		public function __construct( array $args = [] ) {
			$defaults = [
				'version'         => $this->is_script_debug() ? current_time( 'timestamp' ) : '1.0.0',
				'allowed_screens' => [],
				'scope'           => 'both',
				'callback'        => null,
				'minified'        => false,
				'script_path'     => '',
				'style_path'      => '',
				'script_deps'     => [],
				'style_deps'      => []
			];

			$args = wp_parse_args( $args, $defaults );

			$this->validate_defaults( $args );

			// Set the default version
			$this->default_version = trim( $args['version'] );

			// Set default allowed screens
			$this->default_allowed_screens = $args['allowed_screens'];

			// Set the default scope
			$this->default_scope = strtolower( trim( $args['scope'] ) );

			// Set the default callback (if callable)
			$this->default_callback = $args['callback'];

			// Set the use_minified_assets flag (no further validation as it's a boolean)
			$this->use_minified_assets = $args['minified'];

			// Set the default script path
			$this->default_script_path = trailingslashit( $args['script_path'] );

			// Set the default style path
			$this->default_style_path = trailingslashit( $args['style_path'] );

			// Set default script dependencies (no further validation as it's an array)
			$this->default_script_deps = $args['script_deps'];

			// Set default style dependencies (no further validation as it's an array)
			$this->default_style_deps = $args['style_deps'];

			if ( ! function_exists( 'get_current_screen' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/screen.php' );
			}

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		}

		/** Setup *****************************************************************/

		/**
		 * Validate default asset settings.
		 *
		 * @param array $args Asset details.
		 *
		 * @throws Exception If validation fails for any of the provided asset settings.
		 */
		protected function validate_defaults( array $args ) {

			if ( ! empty( $args['version'] ) && ! is_string( $args['version'] ) ) {
				throw new Exception( "Version should be a string." );
			}

			if ( ! is_array( $args['script_deps'] ) ) {
				throw new Exception( "Script dependencies should be an array." );
			}

			if ( ! is_array( $args['style_deps'] ) ) {
				throw new Exception( "Style dependencies should be an array." );
			}

			if ( ! in_array( $args['scope'], [ 'frontend', 'admin', 'both' ] ) ) {
				throw new Exception( "Scope should be 'frontend', 'admin', or 'both'." );
			}

			if ( $args['callback'] !== null && ! is_callable( $args['callback'] ) ) {
				throw new Exception( "Callback should be callable." );
			}

			if ( $args['scope'] === 'admin' && ! is_array( $args['allowed_screens'] ) ) {
				throw new Exception( "Allowed screens should be an array for the admin scope." );
			}

			if ( ! empty( $args['script_path'] ) && ! $this->is_url_or_path( $args['script_path'] ) ) {
				throw new Exception( "Script path should be a URL or a local system path." );
			}

			if ( ! empty( $args['style_path'] ) && ! $this->is_url_or_path( $args['style_path'] ) ) {
				throw new Exception( "Style path should be a URL or a local system path." );
			}

			if ( ! is_bool( $args['minified'] ) ) {
				throw new InvalidArgumentException( "The 'minified' field should be a boolean (true or false)." );
			}

		}

		/** Scripts ***************************************************************/

		/**
		 * Registers multiple scripts.
		 *
		 * @param array $scripts An array of script definitions.
		 *
		 * @throws Exception If the scripts array is empty.
		 */
		public function register_scripts( array $scripts ) {
			if ( empty( $scripts ) ) {
				throw new Exception( 'The provided scripts array is empty and cannot be registered.' );
			}

			foreach ( $scripts as $script ) {
				$this->register_script( $script );
			}
		}

		/**
		 * Register a script.
		 *
		 * @param array $script Script details.
		 *
		 * @throws Exception
		 */
		public function register_script( array $script ) {
			$defaults = [
				'handle'          => '',
				'src'             => '',
				'deps'            => $this->default_script_deps,
				'version'         => $this->default_version,
				'in_footer'       => true,
				'allowed_screens' => $this->default_allowed_screens,
				'enqueue'         => true,
				'localize'        => [], // New array to hold localization variables
				'localize_name'   => '', // Optional specific localization name
				'scope'           => $this->default_scope,
				'callback'        => $this->default_callback,
				'strategy'        => '', // Add the strategy option
			];

			$script = wp_parse_args( $script, $defaults );

			// Resolve and validate script path
			$script['src'] = $this->resolve_script_path( $script );

			$this->validate_asset( 'script', $script );

			$script['args'] = $this->generate_script_args( $script );

			// Prepare the localization name if not provided
			if ( ! empty( $script['localize'] ) ) {
				if ( empty( $script['localize_name'] ) ) {
					$script['localize_name'] = $this->generate_localize_name( $script['handle'] );
				}
			}

			// Register the script for enqueueing later
			$this->register_asset( 'script', $script );
		}

		/**
		 * Resolve and validate the script path.
		 *
		 * @param array $script The script details.
		 *
		 * @return string The validated and resolved script path.
		 * @throws Exception When the script path is invalid.
		 */
		protected function resolve_script_path( array $script ): string {
			$src = $script['src'] ?? '';

			return $this->resolve_path( $src, $this->default_script_path );
		}

		/**
		 * Generate in_footer and strategy options for a script.
		 *
		 * @param array $script Script details.
		 *
		 * @return array In_footer and strategy options.
		 */
		protected function generate_script_args( array $script ): array {
			$script_args = [];

			if ( $script['in_footer'] === true ) {
				$script_args['in_footer'] = true;
			}

			if ( ! empty( $script['strategy'] ) && in_array( $script['strategy'], [ 'defer', 'async' ] ) ) {
				$script_args['strategy'] = $script['strategy'];
			}

			return $script_args;
		}

		/**
		 * Generates a standardized name for use in script localization.
		 *
		 * This method takes a script handle and processes it to create a consistent variable name
		 * for use with `wp_localize_script`. It converts all characters to lowercase, replaces
		 * spaces and hyphens with underscores, and appends '_vars' to the end of the string to
		 * signify that it contains localized variables.
		 *
		 * @param string $handle The original handle of the script.
		 *
		 * @return string The generated name to be used for localizing the script.
		 */
		protected function generate_localize_name( string $handle ): string {
			$name = strtolower( str_replace( [ ' ', '-' ], '_', $handle ) );

			return $name . '_vars';
		}

		/** Styles ****************************************************************/

		/**
		 * Registers multiple styles.
		 *
		 * @param array $styles An array of style definitions.
		 *
		 * @throws Exception If the styles array is empty.
		 */
		public function register_styles( array $styles ) {
			if ( empty( $styles ) ) {
				throw new Exception( 'The provided styles array is empty and cannot be registered.' );
			}

			foreach ( $styles as $style ) {
				$this->register_style( $style );
			}
		}

		/**
		 * Register a style.
		 *
		 * @param array $style Style details.
		 *
		 * @throws Exception
		 */
		public function register_style( array $style ) {
			$defaults = [
				'handle'          => '',
				'src'             => '',
				'deps'            => $this->default_style_deps,
				'version'         => $this->default_version,
				'media'           => 'all',
				'allowed_screens' => $this->default_allowed_screens,
				'enqueue'         => true,
				'callback'        => $this->default_callback,
				'scope'           => $this->default_scope,
			];

			$style = wp_parse_args( $style, $defaults );

			$style['src'] = $this->resolve_style_path( $style );

			$this->validate_asset( 'style', $style );
			$this->register_asset( 'style', $style );
		}

		/**
		 * Resolve and validate the style path.
		 *
		 * @param array $style The style details.
		 *
		 * @return string The validated and resolved style path.
		 * @throws Exception When the style path is invalid.
		 */
		protected function resolve_style_path( array $style ): string {
			$src = $style['src'] ?? '';

			return $this->resolve_path( $src, $this->default_style_path );
		}

		/** Validation ************************************************************/

		/**
		 * Validate assets.
		 *
		 * @param string $type  'script' or 'style'
		 * @param array  $asset Asset details.
		 *
		 * @throws Exception
		 */
		protected function validate_asset( string $type, array $asset ) {
			// Validations
			if ( empty( $asset['handle'] ) ) {
				throw new Exception( ucfirst( $type ) . " handle is required." );
			}

			if ( empty( $asset['src'] ) ) {
				throw new Exception( ucfirst( $type ) . " src is required." );
			} elseif ( ! filter_var( $asset['src'], FILTER_VALIDATE_URL ) && ! file_exists( $asset['src'] ) ) {
				throw new Exception( ucfirst( $type ) . " local src must exist." );
			}

			if ( empty( $asset['version'] ) || ! is_string( $asset['version'] ) ) {
				throw new Exception( ucfirst( $type ) . " enqueue should be a string." );
			}

			if ( ! is_array( $asset['deps'] ) ) {
				throw new Exception( ucfirst( $type ) . " dependencies should be an array." );
			}

			if ( $asset['scope'] === 'admin' && ! is_array( $asset['allowed_screens'] ) ) {
				throw new Exception( ucfirst( $type ) . " allowed_screens should be an array for admin scope." );
			}

			if ( $asset['callback'] !== null && ! is_callable( $asset['callback'] ) ) {
				throw new Exception( ucfirst( $type ) . " callback should be callable." );
			}

			if ( ! is_bool( $asset['enqueue'] ) ) {
				throw new Exception( ucfirst( $type ) . " enqueue should be a boolean." );
			}

			$valid_scopes = [ 'frontend', 'admin', 'both' ];
			if ( ! in_array( $asset['scope'], $valid_scopes ) ) {
				throw new Exception( ucfirst( $type ) . " scope should be 'frontend', 'admin', or 'both'." );
			}

			// Validate the 'strategy' option
			if ( ! empty( $asset['strategy'] ) && $asset['strategy'] !== 'defer' && $asset['strategy'] !== 'async' ) {
				throw new Exception( ucfirst( $type ) . " strategy should be 'defer' or 'async'." );
			}

			// Validate localization variables
			if ( ! empty( $asset['localize'] ) ) {
				if ( ! is_array( $asset['localize'] ) || empty( array_filter( $asset['localize'], 'strlen' ) ) ) {
					throw new Exception( ucfirst( $type ) . " localization variables should be a non-empty key/value pair array." );
				}
			}

		}

		/** Register **************************************************************/

		/**
		 * Register assets.
		 *
		 * @param string $type  'script' or 'style'
		 * @param array  $asset Asset details.
		 */
		protected function register_asset( string $type, array $asset ) {
			// Check if callback is true or if no callback is provided
			$callback_check = ( $asset['callback'] === null || call_user_func( $asset['callback'] ) );

			if ( $callback_check ) {
				if ( $type === 'script' ) {
					if ( $asset['enqueue'] === false ) {
						wp_register_script( $asset['handle'], $asset['src'], $asset['deps'], $asset['version'], $asset['args'] );

						// Handle script localization
						$this->maybe_localize_script( $asset );
					} else {
						$this->scripts[] = $asset;
					}
				} else {
					if ( $asset['enqueue'] === false ) {
						wp_register_style( $asset['handle'], $asset['src'], $asset['deps'], $asset['version'], $asset['media'] );
					} else {
						$this->styles[] = $asset;
					}
				}
			}
		}

		/** Helpers ***************************************************************/

		/**
		 * Resolve and validate a given path, either a script or a style.
		 *
		 * @param string $src          The source path to resolve.
		 * @param string $default_path The default base path to use if necessary.
		 *
		 * @return string The validated and resolved path.
		 * @throws Exception When the path is invalid.
		 */
		protected function resolve_path( string $src, string $default_path ): string {
			if ( empty( $src ) ) {
				throw new Exception( "The source path is empty." );
			}

			// Minify assets if necessary (assuming that this logic applies to both scripts and styles)
			if ( $this->should_use_minified_assets() ) {
				$src = $this->convert_to_minified_version( $src, strrchr( $src, '.' ) );
			}

			// If src is a valid URL or a valid local system path with a filename, return it
			if ( $this->is_url_or_path( $src ) && $this->has_valid_basename( $src ) ) {
				return $src;
			}

			// Attempt to prepend the default path if src is not a URL/system path
			if ( ! empty( $default_path ) ) {
				$resolved_src = rtrim( $default_path, '/' ) . '/' . ltrim( $src, '/' );
				if ( $this->is_url_or_path( $resolved_src ) && $this->has_valid_basename( $resolved_src ) ) {
					return $resolved_src;
				}
			}

			throw new Exception( "Source '{$src}' is not a valid URL or local system path with a filename." );
		}

		/**
		 * Check if a string is a URL or a local system path.
		 *
		 * @param string $path The string to check.
		 *
		 * @return bool True if it's a URL or a local system path, false otherwise.
		 */
		protected function is_url_or_path( string $path ): bool {
			// Check if it's a valid URL
			if ( filter_var( $path, FILTER_VALIDATE_URL ) !== false ) {
				return true;
			}

			// Check if it's a local system path (assuming WordPress)
			$uploads_dir      = wp_upload_dir();
			$uploads_dir_path = $uploads_dir['basedir'];

			if ( strpos( $path, $uploads_dir_path ) === 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * Check if the given path has a valid basename (filename and extension).
		 *
		 * @param string $path The path to check.
		 *
		 * @return bool True if the path has a valid basename, false otherwise.
		 */
		protected function has_valid_basename( string $path ): bool {
			return basename( $path ) !== '';
		}

		/**
		 * Convert the asset source to its minified version.
		 *
		 * @param string $src       The source of the asset.
		 * @param string $extension The extension of the asset (e.g., '.js' or '.css').
		 *
		 * @return string The minified source.
		 */
		protected function convert_to_minified_version( string $src, string $extension ): string {
			return str_replace( $extension, '.min' . $extension, $src );
		}

		/**
		 * Determines if minified assets should be used.
		 *
		 * @return bool True if minified assets should be used, false otherwise.
		 */
		protected function should_use_minified_assets(): bool {
			return ! $this->is_script_debug() && $this->use_minified_assets;
		}

		/**
		 * Check if SCRIPT_DEBUG is defined and true.
		 *
		 * @return bool
		 */
		public function is_script_debug(): bool {
			return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		}

		/** Hook Methods **********************************************************/

		/**
		 * Enqueue registered scripts and styles for admin.
		 */
		public function enqueue_admin_assets() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$this->enqueue_assets( 'admin', $screen->id );
		}

		/**
		 * Enqueue registered scripts and styles for frontend.
		 */
		public function enqueue_frontend_assets() {
			$this->enqueue_assets( 'frontend' );
		}

		/**
		 * Enqueue assets based on scope and screen.
		 *
		 * @param string $scope     'admin', 'frontend' or 'both'
		 * @param string $screen_id Optional for admin assets.
		 */
		private function enqueue_assets( string $scope, string $screen_id = '' ) {
			if ( ! empty( $this->scripts ) ) {
				foreach ( $this->scripts as $script ) {
					if ( $this->should_enqueue_asset( $script, $scope, $screen_id ) ) {
						wp_enqueue_script( $script['handle'], $script['src'], $script['deps'], $script['version'], $script['args'] );

						// Handle script localization
						$this->maybe_localize_script( $script );
					}
				}
			}

			if ( ! empty( $this->styles ) ) {
				foreach ( $this->styles as $style ) {
					if ( $this->should_enqueue_asset( $style, $scope, $screen_id ) ) {
						wp_enqueue_style( $style['handle'], $style['src'], $style['deps'], $style['version'], $style['media'] );
					}
				}
			}
		}

		/**
		 * Determine if a script should be localized.
		 *
		 * @param array $script Script details.
		 */
		protected function maybe_localize_script( array $script ) {
			if ( ! empty( $script['localize'] ) ) {
				wp_localize_script( $script['handle'], $script['localize_name'], $script['localize'] );
			}
		}

		/**
		 * Determine if a script should be enqueued.
		 *
		 * @param array  $asset     Asset details.
		 * @param string $scope     'admin', 'frontend' or 'both'
		 * @param string $screen_id Screen ID.
		 *
		 * @return bool
		 */
		protected function should_enqueue_asset( array $asset, string $scope, string $screen_id ): bool {
			return ( $asset['scope'] === $scope || $asset['scope'] === 'both' ) &&
			       ( $scope !== 'admin' || empty( $asset['allowed_screens'] ) || in_array( $screen_id, $asset['allowed_screens'] ) );
		}

	}

endif;