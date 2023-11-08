# WordPress Asset Manager Library

The WordPress Asset Manager Library provides a powerful interface to efficiently manage the inclusion of JavaScript and CSS files within WordPress. It is designed to enhance the way assets are registered and enqueued, with a focus on performance and flexibility.

## Features

- **Easy Registration**: Quickly register scripts and styles with minimal code.
- **Conditional Loading**: Load assets selectively on specified admin screens or frontend pages.
- **Version Control**: Effectively manage asset versions to utilize browser caching and avoid conflicts.
- **Minification Support**: Choose between minified or full versions of assets to optimize load times.
- **Error Handling**: Gracefully manage registration errors with custom callback functions.
- **Conditional Callbacks**: Implement custom logic to determine whether an asset should be enqueued using conditional callbacks.
- **Script Localization**: Fully support script localization to pass PHP data to JavaScript.
- **Conditional Enqueueing**: Decide when and where assets should be loaded based on custom conditions, enhancing page load speeds and user experience.

## Installation and set up

The extension in question needs to have a `composer.json` file, specifically with the following:

```json 
{
  "require": {
    "arraypress/wp-utils-asset-manager": "*"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/arraypress/wp-utils-asset-manager"
    }
  ]
}
```

Once set up, run `composer install --no-dev`. This should create a new `vendors/` folder
with `arraypress/wp-utils-asset-manager/` inside.

## Example Usage

The following examples illustrate how to register scripts and styles using the `Asset_Manager` class via the helper functions provided.

### Registering Scripts

To register scripts for use within WordPress, you can define an array of script definitions and pass them to the `register_scripts` function along with default parameters and an optional error callback.

```php
// Require vendor libraries
require_once dirname( __FILE__ ) . '/vendor/autoload.php';

// Define scripts to be registered
$scripts = [
	[
		'handle'    => 'my-custom-script',
		'src'       => 'my-custom-script.js',
		'deps'      => [ 'jquery' ],  // Dependency on jQuery
		'version'   => '1.0.0',
		'in_footer' => true,
		'localize'  => [
			'ajax_url' => admin_url( 'admin-ajax.php' )  // Localizing script
		],
	],
	// ... additional script definitions
];

// Optional default arguments
$default_args = [
	'scope'       => 'frontend',  // Frontend only
	'script_path' => plugins_url( 'assets/js/', __FILE__ ), // Set the default script path
	'style_path'  => plugins_url( 'assets/css/', __FILE__ ), // Set the default style path
	// ... other default arguments
];

// Register the scripts
register_scripts( $scripts, $default_args, function ( Exception $e ) {
	error_log( 'Error registering scripts: ' . $e->getMessage() );
} );
```

### Registering Styles

To register styles for use within WordPress, you can define an array of style definitions and pass them to the `register_styles` function along with default parameters and an optional error callback.

```php
// Define scripts to be registered
$styles = [
	[
		'handle'   => 'my-custom-style',
		'src'      => 'my-custom-style.css',
		'callback' => function () {
			// Only load this style on the homepage or a specific page ID (e.g., page ID 42)
			return is_front_page() || is_page( 42 );
		}
	],
	[
		'handle'   => 'my-primary-style',
		'src'      => 'my-primary-style.css',
		'callback' => function () {
			// Only load this style on the homepage or a specific page ID (e.g., page ID 42)
			return is_front_page() || is_page( 42 );
		}
	],
	// ... additional style definitions
];

// Optional default arguments
$default_args = [
	'scope'      => 'frontend',  // Frontend only
	'style_path' => plugins_url( 'assets/css/', __FILE__ ), // Set the default style path
	// ... other default arguments
];

// Register the scripts
register_styles( $styles, $default_args, function ( Exception $e ) {
	error_log( 'Error registering styles: ' . $e->getMessage() );
} );
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug
fixes or new features. Share feedback and suggestions for improvements.

## License

This library is licensed under
the [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).