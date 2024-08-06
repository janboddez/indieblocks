<?php
/**
 * Location-related functions.
 *
 * @package IndieBlocks
 */

namespace IndieBlocks;

/**
 * Holds location (and weather) functions.
 */
class Location {
	/**
	 * Hooks and such.
	 */
	public static function register() {
		// Enqueue block editor script.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_scripts' ), PHP_INT_MAX );

		// Allow location meta to be edited through the block editor.
		add_action( 'rest_api_init', array( __CLASS__, 'register_meta' ) );

		// Register our fields for use with the Location block.
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );

		// Add a "Location" meta box.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );

		// Look up a location name (and weather info).
		foreach ( apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ) as $post_type ) {
			add_action( "save_post_{$post_type}", array( __CLASS__, 'update_meta' ) );
			add_action( "save_post_{$post_type}", array( __CLASS__, 'set_location' ), 20 );
			add_action( "rest_after_insert_{$post_type}", array( __CLASS__, 'set_location' ), 20 );
		}

		add_action( 'admin_footer', array( __CLASS__, 'add_script' ) );
	}

	/**
	 * Adds the Location panel to Gutenberg's document sidebar.
	 */
	public static function enqueue_scripts() {
		if ( apply_filters( 'indieblocks_location_meta_box', false ) ) {
			// Using a classic meta box instead.
			return;
		}

		$current_screen = get_current_screen();

		if (
			isset( $current_screen->post_type ) &&
			in_array( $current_screen->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true )
		) {
			wp_enqueue_style(
				'indieblocks-location',
				plugins_url( '/assets/location.css', __DIR__ ),
				array(),
				\IndieBlocks\Plugin::PLUGIN_VERSION
			);

			wp_enqueue_script(
				'indieblocks-location',
				plugins_url( '/assets/location.js', __DIR__ ),
				array(
					'wp-element',
					'wp-components',
					'wp-i18n',
					'wp-data',
					'wp-core-data',
					'wp-api-fetch',
					'wp-plugins',
					'wp-editor',
				),
				\IndieBlocks\Plugin::PLUGIN_VERSION,
				false
			);

			global $post;

			// Whether we should have browsers attempt to automatically fill out
			// a location.
			$should_update = '1';

			if ( ! static::is_recent( $post ) ) {
				// Post is over one hour old.
				$should_update = '0';
			}

			if ( '' !== get_meta( $post, 'geo_latitude' ) && '' !== get_meta( $post, 'geo_longitude' ) ) {
				// Latitude and longitude were set previously.
				$should_update = '0';
			}

			if ( '' !== get_meta( $post, 'geo_address' ) ) {
				// Location was set previously.
				$should_update = '0';
			}

			wp_localize_script(
				'indieblocks-location',
				'indieblocks_location_obj',
				array(
					'should_update' => apply_filters( 'indieblocks_location_should_update', $should_update, $post ),
				)
			);

			// When our Gutenberg panel is active, hide these fields from the
			// Custom Fields panel, to prevent them from being accidentally
			// overwritten with stale values.
			// @todo: Make this a proper callback so that it can be unhooked.
			add_filter( 'is_protected_meta', array( __CLASS__, 'hide_meta' ), 10, 2 );
		}
	}

	/**
	 * Allows location-related fields to be edited through the REST API. Used by
	 * the editor sidebar panel.
	 */
	public static function register_meta() {
		$post_types = apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) );

		foreach ( $post_types as $post_type ) {
			if ( use_block_editor_for_post_type( $post_type ) ) {
				// Allow these fields to be *set* by the block editor.
				register_post_meta(
					$post_type,
					'geo_latitude',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( (float) $meta_value );
						},
					)
				);

				register_post_meta(
					$post_type,
					'geo_longitude',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( (float) $meta_value );
						},
					)
				);

				register_post_meta(
					$post_type,
					'geo_address',
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => 'string',
						'default'           => '',
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
						'sanitize_callback' => function ( $meta_value ) {
							return sanitize_text_field( $meta_value );
						},
					)
				);
			}
		}
	}

	/**
	 * Hides certain custom fields from the Custom Fields panel to prevent them
	 * from getting accidentally overwritten.
	 *
	 * @param  bool   $is_protected Whether the key is considered protected.
	 * @param  string $meta_key     Metadata key.
	 * @return bool                 Whether the meta key is considered protected.
	 */
	public static function hide_meta( $is_protected, $meta_key ) {
		if ( in_array( $meta_key, array( 'geo_latitude', 'geo_longitude', 'geo_address' ), true ) ) {
			return true;
		}

		return $is_protected;
	}

	/**
	 * Registers a custom REST API endpoint for reading (but not writing) our
	 * location data. Used by the Location block.
	 */
	public static function register_rest_field() {
		$post_types = (array) apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) );

		foreach ( $post_types as $post_type ) {
			register_rest_field(
				$post_type,
				'indieblocks_location',
				array(
					'get_callback'    => array( __CLASS__, 'get_meta' ),
					'update_callback' => null,
				)
			);
		}
	}

	/**
	 * Returns location metadata.
	 *
	 * Could be used as both a `register_rest_route()` and
	 * `register_rest_field()` callback.
	 *
	 * @param  \WP_REST_Request|array $request API request (parameters).
	 * @return array|\WP_Error                 Response (or error).
	 */
	public static function get_meta( $request ) {
		if ( is_array( $request ) ) {
			// `register_rest_field()` callback.
			$post_id = $request['id'];
		} else {
			// `register_rest_route()` callback.
			$post_id = $request->get_param( 'post_id' );
		}

		if ( empty( $post_id ) || ! is_int( $post_id ) ) {
			return new \WP_Error( 'invalid_id', 'Invalid post ID.', array( 'status' => 400 ) );
		}

		$post_id  = (int) $post_id;
		$weather  = get_post_meta( $post_id, '_indieblocks_weather', true );
		$location = array(
			'geo_address' => get_post_meta( $post_id, 'geo_address', true ),
			'weather'     => is_array( $weather ) ? $weather : array(),
		);

		return $location; // Either an empty string, or an associated array (which gets translated into a JSON object).
	}

	/**
	 * Registers a new meta box.
	 */
	public static function add_meta_box() {
		$options = get_options();

		// This'll hide the meta box for Gutenberg users, who by default get the
		// new sidebar panel.
		$args = array(
			'__back_compat_meta_box' => true,
		);

		if ( apply_filters( 'indieblocks_location_meta_box', false ) ) {
			// And this will bring it back.
			$args = null;
		}

		add_meta_box(
			'indieblocks-location',
			__( 'Location', 'indieblocks' ),
			array( __CLASS__, 'render_meta_box' ),
			apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ),
			'side',
			'default',
			$args
		);
	}

	/**
	 * ("Classic" meta box only.) Renders the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'indieblocks_loc_nonce' );

		$lat = get_post_meta( $post->ID, 'geo_latitude', true );
		$lon = get_post_meta( $post->ID, 'geo_longitude', true );
		?>
		<div style="margin-bottom: 6px;">
			<label><input type="checkbox" name="indieblocks_loc_enabled" value="1" /> <?php esc_html_e( 'Update location data?', 'indieblocks' ); ?></label>
		</div>
		<div style="display: flex; justify-content: space-between;">
			<div style="width: 47.5%;"><label for="indieblocks-lat"><?php esc_html_e( 'Latitude', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_lat" value="<?php echo esc_attr( '' !== $lat ? round( (float) $lat, 8 ) : '' ); ?>" id="indieblocks-lat" style="max-width: 100%; box-sizing: border-box;" /></div>

			<div style="width: 47.5%;"><label for="indieblocks-lon"><?php esc_html_e( 'Longitude', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_lon" value="<?php echo esc_attr( '' !== $lon ? round( (float) $lon, 8 ) : '' ); ?>" id="indieblocks-lon" style="max-width: 100%; box-sizing: border-box;" /></div>
		</div>
		<div>
			<label for="indieblocks-address"><?php esc_html_e( 'Name', 'indieblocks' ); ?></label><br />
			<input type="text" name="indieblocks_address" value="<?php echo esc_attr( get_post_meta( $post->ID, 'geo_address', true ) ); ?>" id="indieblocks-address" style="width: 100%; box-sizing: border-box;" />
		</div>
		<?php
	}

	/**
	 * ("Classic" meta box only.) Asks browsers for location coordinates.
	 *
	 * @todo: Move to, you know, an actual JS file.
	 */
	public static function add_script() {
		?>
		<script type="text/javascript">
		var indieblocks_loc = document.querySelector( '[name="indieblocks_loc_enabled"]' );

		function indieblocks_update_location() {
			var indieblocks_lat = document.querySelector( '[name="indieblocks_lat"]' );
			var indieblocks_lon = document.querySelector( '[name="indieblocks_lon"]' );

			if ( indieblocks_lat && '' === indieblocks_lat.value && indieblocks_lon && '' === indieblocks_lon.value ) {
				// If the "Latitude" and "Longitude" fields are empty, ask the
				// browser for location information.
				navigator.geolocation.getCurrentPosition( function ( position ) {
					indieblocks_lat.value = position.coords.latitude;
					indieblocks_lon.value = position.coords.longitude;

					<?php if ( static::is_recent() ) : // If the post is less than one hour old. ?>
						indieblocks_loc.checked = true; // Auto-enable.
					<?php endif; ?>
				}, function ( error ) {
					// Do nothing.
				} );
			}
		}

		indieblocks_update_location();

		if ( indieblocks_loc ) {
			indieblocks_loc.addEventListener( 'click', function ( event ) {
				if ( indieblocks_loc.checked ) {
					indieblocks_update_location();
				}
			} );
		}
		</script>
		<?php
	}

	/**
	 * ("Classic" meta box only.) Updates post meta after save.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 */
	public static function update_meta( $post ) {
		$post = get_post( $post );

		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		if ( ! isset( $_POST['indieblocks_loc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['indieblocks_loc_nonce'] ), basename( __FILE__ ) ) ) {
			// Nonce missing or invalid.
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true ) ) {
			// Unsupported post type.
			return;
		}

		if ( empty( $_POST['indieblocks_loc_enabled'] ) ) {
			// Save location meta only if the checkbox was checked. Helps
			// prevent overwriting existing data.
			return;
		}

		if ( isset( $_POST['indieblocks_lat'] ) && is_numeric( $_POST['indieblocks_lat'] ) ) {
			update_post_meta( $post->ID, 'geo_latitude', round( (float) $_POST['indieblocks_lat'], 8 ) );
		}

		if ( isset( $_POST['indieblocks_lon'] ) && is_numeric( $_POST['indieblocks_lon'] ) ) {
			update_post_meta( $post->ID, 'geo_longitude', round( (float) $_POST['indieblocks_lon'], 8 ) );
		}

		if ( ! empty( $_POST['indieblocks_address'] ) ) {
			update_post_meta( $post->ID, 'geo_address', sanitize_text_field( wp_unslash( $_POST['indieblocks_address'] ) ) );
		}
	}

	/**
	 * Cleans up location metadata, and, when applicable, adds a location name
	 * and weather info.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 */
	public static function set_location( $post ) {
		$post = get_post( $post );

		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, apply_filters( 'indieblocks_location_post_types', array( 'post', 'indieblocks_note' ) ), true ) ) {
			// Unsupported post type.
			return;
		}

		// Saved previously.
		$lat = get_post_meta( $post->ID, 'geo_latitude', true );
		$lon = get_post_meta( $post->ID, 'geo_longitude', true );

		if ( '' === $lat || '' === $lon ) {
			// Nothing to do.
			return;
		}

		$updated = false;

		// Add address, or rather, city/town data.
		if ( '' === get_post_meta( $post->ID, 'geo_address', true ) ) {
			// Okay, so we've got coordinates but no name; let's change that.
			$geo_address = static::get_address( $lat, $lon );

			if ( ! empty( $geo_address ) ) {
				// Add town and country metadata.
				update_post_meta( $post->ID, 'geo_address', $geo_address );
				$updated = true;
			}
		}

		// Only add weather data to sufficiently recent posts.
		if ( ! static::is_recent( $post ) ) {
			return;
		}

		$indieblocks_weather = get_post_meta( $post->ID, '_indieblocks_weather', true ); // String or array.

		if ( $updated || empty( $indieblocks_weather ) ) {
			// Let's do weather information, too.
			$weather = static::get_weather( $lat, $lon );

			if ( ! empty( $weather ) ) {
				update_post_meta( $post->ID, '_indieblocks_weather', $weather ); // Will be an associated array.
			}
		}
	}

	/**
	 * Given a latitude and longitude, returns address data (i.e., reverse
	 * geolocation).
	 *
	 * Uses OSM's Nominatim for geocoding.
	 *
	 * @param  float $lat Latitude.
	 * @param  float $lon Longitude.
	 * @return string     (Currently) town, city, or municipality.
	 */
	public static function get_address( $lat, $lon ) {
		$location = get_transient( "indieblocks_loc_{$lat}_{$lon}" );

		if ( empty( $location ) ) {
			$response = remote_get(
				add_query_arg(
					array(
						'format'         => 'json',
						'lat'            => $lat,
						'lon'            => $lon,
						'zoom'           => 18,
						'addressdetails' => 1,
					),
					'https://nominatim.openstreetmap.org/reverse'
				),
				true
			);

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				debug_log( "[IndieBlocks/Location] Failed to retrieve address data for {$lat}, {$lon}" );
				return '';
			}

			$location = json_decode( $response['body'], true );

			if ( empty( $location ) ) {
				debug_log( "[IndieBlocks/Location] Failed to decode address data for {$lat}, {$lon}" );
				return '';
			}

			// Since town names don't change overnight, we cache them for a while.
			set_transient( "indieblocks_loc_{$lat}_{$lon}", $location, MONTH_IN_SECONDS );
		}

		$geo_address = '';

		if ( ! empty( $location['address']['town'] ) ) {
			$geo_address = $location['address']['town'];
		} elseif ( ! empty( $location['address']['city'] ) ) {
			$geo_address = $location['address']['city'];
		} elseif ( ! empty( $location['address']['municipality'] ) ) {
			$geo_address = $location['address']['municipality'];
		}

		if ( ! empty( $geo_address ) && ! empty( $location['address']['country_code'] ) ) {
			$geo_address .= ', ' . strtoupper( $location['address']['country_code'] );
		}

		return sanitize_text_field( $geo_address );
	}

	/**
	 * Given a latitude and longitude, returns current weather information.
	 *
	 * @param  float $lat Latitude.
	 * @param  float $lon Longitude.
	 * @return array      Weather data.
	 */
	public static function get_weather( $lat, $lon ) {
		$weather = get_transient( "indieblocks_weather_{$lat}_{$lon}" );

		if ( empty( $weather ) ) {
			if ( ! defined( 'OPEN_WEATHER_MAP_API_KEY' ) || empty( OPEN_WEATHER_MAP_API_KEY ) ) {
				// No need to try and fetch weather data when no API key is set.
				return array();
			}

			// As of version 0.7, we no longer pass along `units=metric`, and
			// convert temperatures on display.
			$response = remote_get(
				add_query_arg(
					array(
						'lat'   => $lat,
						'lon'   => $lon,
						'appid' => OPEN_WEATHER_MAP_API_KEY,
					),
					'https://api.openweathermap.org/data/2.5/weather'
				),
				true
			);

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				debug_log( "[IndieBlocks/Location]  Failed to retrieve weather data for {$lat}, {$lon}" );
				return array();
			}

			$weather = json_decode( $response['body'], true );

			if ( empty( $weather ) ) {
				debug_log( "[IndieBlocks/Location]  Failed to decode weather data for {$lat}, {$lon}" );
				return array();
			}

			// Valid JSON. Store response for half an hour.
			set_transient( "indieblocks_weather_{$lat}_{$lon}", $weather, HOUR_IN_SECONDS / 2 );
		}

		$weather_data = array();

		$weather_data['temperature'] = isset( $weather['main']['temp'] ) && is_numeric( $weather['main']['temp'] )
			? (float) round( $weather['main']['temp'], 2 )
			: null;

		$weather_data['humidity'] = isset( $weather['main']['humidity'] ) && is_numeric( $weather['main']['humidity'] )
			? (int) round( $weather['main']['humidity'] )
			: null;

		if ( ! empty( $weather['weather'] ) ) {
			$weather = ( (array) $weather['weather'] )[0];

			$weather_data['id'] = isset( $weather['id'] ) && is_int( $weather['id'] )
				? static::icon_map( (int) $weather['id'] )
				: '';

			$weather_data['description'] = isset( $weather['description'] )
				? ucfirst( sanitize_text_field( $weather['description'] ) )
				: '';
		}

		return array_filter( $weather_data ); // Removes empty values.
	}

	/**
	 * Maps OpenWeather's IDs to SVG icons. Kindly borrowed from the Simple
	 * Location plugin by David Shanske.
	 *
	 * @link https://github.com/dshanske/simple-location
	 *
	 * @param int $id OpenWeather ID.
	 */
	public static function icon_map( $id ) {
		if ( in_array( $id, array( 200, 201, 202, 230, 231, 232 ), true ) ) {
			return 'wi-thunderstorm';
		}

		if ( in_array( $id, array( 210, 211, 212, 221 ), true ) ) {
			return 'wi-lightning';
		}

		if ( in_array( $id, array( 300, 301, 321, 500 ), true ) ) {
			return 'wi-sprinkle';
		}

		if ( in_array( $id, array( 302, 311, 312, 314, 501, 502, 503, 504 ), true ) ) {
			return 'wi-rain';
		}

		if ( in_array( $id, array( 310, 511, 611, 612, 615, 616, 620 ), true ) ) {
			return 'wi-rain-mix';
		}

		if ( in_array( $id, array( 313, 520, 521, 522, 701 ), true ) ) {
			return 'wi-showers';
		}

		if ( in_array( $id, array( 531, 901 ), true ) ) {
			return 'wi-storm-showers';
		}

		if ( in_array( $id, array( 600, 601, 621, 622 ), true ) ) {
			return 'wi-snow';
		}

		if ( in_array( $id, array( 602 ), true ) ) {
			return 'wi-sleet';
		}

		if ( in_array( $id, array( 711 ), true ) ) {
			return 'wi-smoke';
		}

		if ( in_array( $id, array( 721 ), true ) ) {
			return 'wi-day-haze';
		}

		if ( in_array( $id, array( 731, 761 ), true ) ) {
			return 'wi-dust';
		}

		if ( in_array( $id, array( 741 ), true ) ) {
			return 'wi-fog';
		}

		if ( in_array( $id, array( 771, 801, 802, 803 ), true ) ) {
			return 'wi-cloudy-gusts';
		}

		if ( in_array( $id, array( 781, 900 ), true ) ) {
			return 'wi-tornado';
		}

		if ( in_array( $id, array( 800 ), true ) ) {
			return 'wi-day-sunny';
		}

		if ( in_array( $id, array( 804 ), true ) ) {
			return 'wi-cloudy';
		}

		if ( in_array( $id, array( 902, 962 ), true ) ) {
			return 'wi-hurricane';
		}

		if ( in_array( $id, array( 903 ), true ) ) {
			return 'wi-snowflake-cold';
		}

		if ( in_array( $id, array( 904 ), true ) ) {
			return 'wi-hot';
		}

		if ( in_array( $id, array( 905 ), true ) ) {
			return 'wi-windy';
		}

		if ( in_array( $id, array( 906 ), true ) ) {
			return 'wi-day-hail';
		}

		if ( in_array( $id, array( 957 ), true ) ) {
			return 'wi-strong-wind';
		}

		if ( in_array( $id, array( 762 ), true ) ) {
			return 'wi-volcano';
		}

		if ( in_array( $id, array( 751 ), true ) ) {
			return 'wi-sandstorm';
		}

		return '';
	}

	/**
	 * Whether a post is new or under one hour old.
	 *
	 * @param  int|WP_Post $post The post (or `null`, which means `global $post`).
	 * @return bool              True if the post is unpublished or less than one hour old.
	 */
	protected static function is_recent( $post = null ) {
		$post_time = get_post_time( 'U', true, $post );

		return false === $post_time || time() - $post_time < HOUR_IN_SECONDS;
	}
}
