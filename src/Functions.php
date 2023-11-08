<?php
/**
 * Functions file for asset management within a WordPress environment.
 *
 * This file contains utility functions that leverage the Asset_Manager class to handle the registration and enqueueing
 * of scripts and styles. It simplifies the inclusion of assets on the front-end and admin areas of WordPress, with
 * capabilities to set default properties, manage dependencies, and conditionally load resources based on the current
 * page or admin screen. The provided functions offer a structured approach to registering scripts and styles, with
 * error handling to gracefully manage any issues encountered during the process.
 *
 * The asset registration functions accept an array of asset definitions and optional default arguments, along with an
 * error callback to handle exceptions. These functions ensure that the assets are correctly set up according to
 * WordPress standards and best practices.
 *
 * @package     ArrayPress/Utils/WP/Asset_Manager
 * @copyright   Copyright (c) 2023, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\Utils\WP;

use Exception;

if ( ! function_exists( 'register_scripts' ) ) {
	/**
	 * Registers multiple scripts with the Asset Manager, allowing customization through options.
	 *
	 * @param array         $scripts        An array of script definitions to register.
	 * @param array         $default_args   Optional. Default arguments for asset management.
	 * @param callable|null $error_callback Optional. A callback function for handling errors during registration.
	 *
	 * @throws Exception If an error occurs during script registration.
	 */
	function register_scripts( array $scripts, array $default_args = [], ?callable $error_callback = null ) {
		try {
			$asset_manager = new Asset_Manager( $default_args );

			$asset_manager->register_scripts( $scripts );
		} catch ( Exception $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}

			// Handle the exception or log it if needed
			return null; // Return null on failure
		}
	}
}

if ( ! function_exists( 'register_styles' ) ) {
	/**
	 * Registers multiple styles with the Asset Manager, allowing customization through options.
	 *
	 * @param array         $styles         An array of style definitions to register.
	 * @param array         $default_args   Optional. Default arguments for asset management.
	 * @param callable|null $error_callback Optional. A callback function for handling errors during registration.
	 *
	 * @throws Exception If an error occurs during style registration.
	 */
	function register_styles( array $styles, array $default_args = [], ?callable $error_callback = null ) {
		try {
			$asset_manager = new Asset_Manager( $default_args );

			$asset_manager->register_styles( $styles );
		} catch ( Exception $e ) {
			if ( is_callable( $error_callback ) ) {
				call_user_func( $error_callback, $e );
			}

			// Handle the exception or log it if needed
			return null; // Return null on failure
		}
	}
}