<?php
/**
 * Contains the Options_Proxy class
 *
 * @package skaut-google-drive-gallery
 */

namespace Sgdg\Frontend;

/**
 * A proxy for overridable options
 *
 * Servers as a proxy for all options overridable on a case-by-case basis (in a shortcode or a block). Returns the overriden values or the global values if the option isn't overriden.
 */
class Options_Proxy {
	/**
	 * A list of all currently overriden options.
	 *
	 * @var array $overriden {
	 *     All the fields are optional.
	 *
	 *     @type int    $grid_height The height of a row in the image grid.
	 *     @type int    $grid_spacing Item spacing in the image grid.
	 *     @type string $dir_title_size Directory title size, including CSS units.
	 *     @type string $dir_counts Whether to show directory item counts. Accepts `true`, `false`.
	 *     @type int    $page_size Number of items per 1 page.
	 *     @type string $page_autoload Whether to autoload new images. Accepts `true`, `false`.
	 *     @type string $dir_prefix A prefix separator to cut a prefix from the start of all directory names.
	 *     @type int    $preview_size Maximum size of an image in the lightbox.
	 *     @type int    $preview_speed Lightbox animation speed.
	 *     @type string $preview_arrows Whether to show lightbox navigation arrows. Accepts `true`, `false`.
	 *     @type string $preview_close_button Whether to show lightbox close button. Accepts `true`, `false`.
	 *     @type string $preview_loop Whether to loop the images in the lightbox. Accepts `true`, `false`.
	 *     @type string $preview_activity_indicator Whether to show an activity indicator while the lightbox is loading. Accepts `true`, `false`.
	 *     @type string $preview_captions Whether to show image captions in the lightbox. Accepts `true`, `false`.
	 *     @type string $image_ordering_by What to order images by. Accepts `name`, `time`.
	 *     @type string $image_ordering_order Whether to order images in ascending or descending order. Accepts `ascending`, `descending`.
	 *     @type string $dir_ordering_by What to order images by. Accepts `name`, `time`.
	 *     @type string $dir_ordering_order Whether to order images in ascending or descending order. Accepts `ascending`, `descending`.
	 * }
	 */
	public $overriden;
	/**
	 * All the options that can be overriden, except for ordering options.
	 *
	 * @see $ordering_option_list
	 *
	 * @var array $option_list {
	 *     All the fields are mandatory.
	 *
	 *     @type \Sgdg\Frontend\BoundedIntegerOption $grid_height The height of a row in the image grid.
	 *     @type \Sgdg\Frontend\IntegerOption        $grid_spacing Item spacing in the image grid.
	 *     @type \Sgdg\Frontend\StringOption         $dir_title_size Directory title size.
	 *     @type \Sgdg\Frontend\BooleanOption        $dir_counts Whether to show directory item counts.
	 *     @type \Sgdg\Frontend\BoundedIntegerOption $page_size Number of items per 1 page.
	 *     @type \Sgdg\Frontend\BooleanOption        $page_autoload Whether to autoload new images.
	 *     @type \Sgdg\Frontend\StringOption         $dir_prefix A prefix separator to cut a prefix from the start of all directory names.
	 *     @type \Sgdg\Frontend\BoundedIntegerOption $preview_size Maximum size of an image in the lightbox.
	 *     @type \Sgdg\Frontend\BoundedIntegerOption $preview_speed Lightbox animation speed.
	 *     @type \Sgdg\Frontend\BooleanOption        $preview_arrows Whether to show lightbox navigation arrows.
	 *     @type \Sgdg\Frontend\BooleanOption        $preview_close_button Whether to show lightbox close button.
	 *     @type \Sgdg\Frontend\BooleanOption        $preview_loop Whether to loop the images in the lightbox.
	 *     @type \Sgdg\Frontend\BooleanOption        $preview_activity_indicator Whether to show an activity indicator while the lightbox is loading.
	 *     @type \Sgdg\Frontend\BooleanOption        $preview_activity_captions Whether to show image captions in the lightbox.
	 * }
	 */
	private $option_list;
	/**
	 * All the ordering options that can be overriden.
	 *
	 * @see $option_list
	 *
	 * @var array $ordering_option_list {
	 *     All the fields are mandatory.
	 *
	 *     @type \Sgdg\Frontend\OrderingOption $image_ordering How to order images in the gallery.
	 *     @type \Sgdg\Frontend\OrderingOption $dir_ordering How to order directories in the gallery.
	 * }
	 */
	private $ordering_option_list;

	/**
	 * Option class constructor.
	 *
	 * @param array $overriden {
	 *     A list of options to override.
	 *
	 *     @see $overriden
	 * }
	 */
	public function __construct( $overriden = [] ) {
		$this->option_list          = [
			'grid_height'                => \Sgdg\Options::$grid_height,
			'grid_spacing'               => \Sgdg\Options::$grid_spacing,
			'dir_title_size'             => \Sgdg\Options::$dir_title_size,
			'dir_counts'                 => \Sgdg\Options::$dir_counts,
			'page_size'                  => \Sgdg\Options::$page_size,
			'page_autoload'              => \Sgdg\Options::$page_autoload,
			'dir_prefix'                 => \Sgdg\Options::$dir_prefix,

			'preview_size'               => \Sgdg\Options::$preview_size,
			'preview_speed'              => \Sgdg\Options::$preview_speed,
			'preview_arrows'             => \Sgdg\Options::$preview_arrows,
			'preview_close_button'       => \Sgdg\Options::$preview_close_button,
			'preview_loop'               => \Sgdg\Options::$preview_loop,
			'preview_activity_indicator' => \Sgdg\Options::$preview_activity_indicator,
			'preview_captions'           => \Sgdg\Options::$preview_captions,
		];
		$this->ordering_option_list = [
			'image_ordering' => \Sgdg\Options::$image_ordering,
			'dir_ordering'   => \Sgdg\Options::$dir_ordering,
		];
		$this->overriden            = [];
		if ( is_array( $overriden ) ) {
			foreach ( $overriden as $key => $value ) {
				if ( array_key_exists( $key, $this->option_list ) ) {
					$this->overriden[ $key ] = $value;
				}
				if ( substr( $key, -6 ) === '_order' ) {
					if ( array_key_exists( substr( $key, 0, -6 ), $this->ordering_option_list ) ) {
						$this->overriden[ $key ] = $value;
					}
				}
				if ( substr( $key, -3 ) === '_by' ) {
					if ( array_key_exists( substr( $key, 0, -3 ), $this->ordering_option_list ) ) {
						$this->overriden[ $key ] = $value;
					}
				}
			}
		}
	}

	/**
	 * Gets a value of an option.
	 *
	 * Returns the overriden value if it exists, otherwise returns the global value.
	 *
	 * @see \Sgdg\Frontend\Option::get()
	 *
	 * @param string $name The name of the requested option.
	 * @param mixed  $default_value A default value to return if the option isn't overriden and has no value. If null, the default value from the option will be used. Default null.
	 */
	public function get( $name, $default_value = null ) {
		if ( array_key_exists( $name, $this->overriden ) ) {
			return $this->overriden[ $name ];
		}
		if ( array_key_exists( $name . '_order', $this->overriden ) && array_key_exists( $name . '_by', $this->overriden ) ) {
			return ( 'name' === $this->overriden[ $name . '_by' ] ? 'name_natural' : 'modifiedTime' ) . ( 'ascending' === $this->overriden[ $name . '_order' ] ? '' : ' desc' );
		}
		if ( array_key_exists( $name, $this->option_list ) ) {
			return $this->option_list[ $name ]->get( $default_value );
		}
		if ( array_key_exists( $name, $this->ordering_option_list ) ) {
			return $this->ordering_option_list[ $name ]->get( $default_value );
		}
		return $default_value;
	}

	/**
	 * Gets the title of an option.
	 *
	 * Returns a human-readable name of the option.
	 *
	 * @see \Sgdg\Frontend\Option::get_title()
	 *
	 * @param string $name The name of the requested option.
	 */
	public function get_title( $name ) {
		if ( array_key_exists( $name, $this->option_list ) ) {
			return $this->option_list[ $name ]->get_title();
		}
		if ( array_key_exists( $name, $this->ordering_option_list ) ) {
			return $this->ordering_option_list[ $name ]->get_title();
		}
		return null;
	}

	/**
	 * Gets the "order" part of an OrderingOption.
	 *
	 * Returns the overriden value if it exists, otherwise returns the global value.
	 *
	 * @see \Sgdg\Frontend\OrderingOption::get_order()
	 *
	 * @param string      $name The name of the requested option.
	 * @param string|null $default_value A default value to return if the option isn't overriden and has no value. If null, the default value from the option will be used. Accepts `ascending`, `descending`, null. Default null.
	 */
	public function get_order( $name, $default_value = null ) {
		if ( array_key_exists( $name . '_order', $this->overriden ) ) {
			return $this->overriden[ $name . '_order' ];
		}
		if ( array_key_exists( $name, $this->ordering_option_list ) ) {
			return $this->ordering_option_list[ $name ]->get_order( $default_value );
		}
		return $default_value;
	}

	/**
	 * Gets the "by" part of an OrderingOption.
	 *
	 * Returns the overriden value if it exists, otherwise returns the global value.
	 *
	 * @see \Sgdg\Frontend\OrderingOption::get_by()
	 *
	 * @param string      $name The name of the requested option.
	 * @param string|null $default_value A default value to return if the option isn't overriden and has no value. If null, the default value from the option will be used. Accepts `name`, `time`, null. Default null.
	 */
	public function get_by( $name, $default_value = null ) {
		if ( array_key_exists( $name . '_by', $this->overriden ) ) {
			return $this->overriden[ $name . '_by' ];
		}
		if ( array_key_exists( $name, $this->ordering_option_list ) ) {
			return $this->ordering_option_list[ $name ]->get_by( $default_value );
		}
		return $default_value;
	}
}
