<?php
/**
 * Display NodePing status page within WordPress.
 *
 * @link https://wordpress.org/extend/plugins/nodeping-status/
 * @package NodePing_Status
 */

/*
Plugin Name: NodePing Status
Plugin URI: https://wordpress.org/extend/plugins/nodeping-status/
Description: Display NodePing Status page within WordPress.
Author: Exactly WWW
Text Domain: nodeping-status
Version: 1.2.1
Author URI: https://ewww.io/
License: GPLv3
*/

// This is the full path of the plugin file itself.
define( 'NODEPING_STATUS_PLUGIN_FILE', __FILE__ );
// This is the path of the plugin file relative to the plugins/ folder.
define( 'NODEPING_STATUS_PLUGIN_FILE_REL', 'nodeping-status/nodeping-status.php' );

define( 'NODEPING_STATUS_VERSION', '1.2.1' );

/**
 * Hooks
 */
// Variable for plugin settings link.
$np_plugin_slug = plugin_basename( NODEPING_STATUS_PLUGIN_FILE );
add_filter( "plugin_action_links_$np_plugin_slug", 'nodeping_status_settings_link' );
add_action( 'init', 'nodeping_status_init' );
add_action( 'admin_init', 'nodeping_status_admin_init' );
add_action( 'admin_menu', 'nodeping_status_admin_menu', 60 );
add_action( 'wp_enqueue_scripts', 'nodeping_status_scripts' );

/**
 * Plugin initialization function
 */
function nodeping_status_init() {
	load_plugin_textdomain( 'nodeping-status', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Plugin initialization for admin area
 */
function nodeping_status_admin_init() {
	nodeping_status_init();
	// Register the settings.
	register_setting( 'nodeping_status_options', 'nodeping_status_api_token', 'nodeping_status_token_verify' );
}

/**
 * Adds the settings page to the admin menu
 */
function nodeping_status_admin_menu() {
	$nps_options_page = add_options_page(
		'NodePing Status',           // Title of page.
		'NodePing Status',           // Sub-menu title.
		'manage_options',            // Permissions required.
		NODEPING_STATUS_PLUGIN_FILE, // Menu slug.
		'nodeping_status_options'    // Function to call.
	);
}

/**
 * Enqueue custom scripts/stylesheets.
 */
function nodeping_status_scripts() {
	wp_enqueue_style( 'np-datatable', plugins_url( 'css/datatable.css', __FILE__ ), array(), NODEPING_STATUS_VERSION );
	wp_enqueue_style( 'np-fontawesome', plugins_url( 'css/font-awesome.min.css', __FILE__ ), array(), NODEPING_STATUS_VERSION );
}

/**
 * Adds a link on the Plugins page settings.
 *
 * @param array $links The links for the plugin listing on the Plugins page.
 * @return array The plugin listing links, with the Settings added.
 */
function nodeping_status_settings_link( $links ) {
	// Load the html for the settings link.
	$settings_link = '<a href="options-general.php?page=' . plugin_basename( NODEPING_STATUS_PLUGIN_FILE ) . '">' . __( 'Settings', 'nodeping-status' ) . '</a>';
	// Load the settings link into the plugin links array.
	array_unshift( $links, $settings_link );
	// Send back the plugin links array.
	return $links;
}

/**
 * Checks the api token for proper results.
 *
 * @param string $api_token The NodePing account token.
 * @return bool True if the token is valid, false otherwise.
 */
function nodeping_status_token_verify( $api_token ) {
	if ( ! $api_token ) {
		return false;
	}
	$api_token = trim( $api_token );
	$url       = "https://api.nodeping.com/api/1/accounts?token=$api_token";
	$result    = wp_remote_get( $url );

	if ( is_wp_error( $result ) ) {
		return false;
	} elseif ( ! empty( $result['body'] ) && preg_match( '/parent.+name.+status.+count/', $result['body'] ) ) {
		return $api_token;
	}
}

/**
 * Generates the status table when the legacy-style shortcode is used.
 *
 * @param array $atts Any attributes passed via shortcode.
 * @return string The output for the shortcode.
 */
function nodeping_legacy_status_shortcode( $atts ) {
	$api_token = get_option( 'nodeping_status_api_token' );
	// Don't do anything more if we don't have a valid token.
	if ( ! $api_token ) {
		return '';
	}
	// Set default attribute values if the user didn't set them.
	$atts = shortcode_atts(
		array(
			'days'  => '7',
			'total' => '30',
		),
		$atts
	);
	// Retrieve a list of checks for the provided token.
	$url    = "https://api.nodeping.com/api/1/checks?token=$api_token";
	$result = wp_remote_get( $url );
	if ( is_wp_error( $result ) ) {
		return '';
	} elseif ( ! empty( $result['body'] ) && preg_match( '/_id.+label.+type/', $result['body'] ) ) {
		$output      = '';
		$date_offset = 0;
		$alternate   = true;
		// If someone tried to set the total smaller than the number of days being queried, fix it, otherwise we won't be able to display the requested number of days.
		if ( $atts['total'] < $atts['days'] ) {
			$atts['total'] = $atts['days'];
		}
		// Set the beginning date for the uptime query.
		$start_date = gmdate( 'Y-m-d', time() - 86400 * $atts['total'] );

		$output .= "<table id='statusgrid'>\n<thead>\n<tr>\n";
		$output .= "<th>&nbsp;</th>\n";
		$output .= "<th class='center'>" . esc_html__( 'Status', 'nodeping-status' ) . "</th>\n";

		while ( $date_offset < $atts['days'] ) {
			$current_date = gmdate( 'Y-m-d', time() - 86400 * $date_offset );
			$date_offset++;
			$output .= "<th class='center'>$current_date</th>\n";
		}

		$output .= "<th class='center'>" . (int) $atts['total'] . ' ' . esc_html__( 'Days', 'nodeping-status' ) . "</th>\n";
		$output .= "</tr>\n</thead>\n<tbody>\n";

		// Put the list of checks we retrieved into an array (the API gives us JSON data).
		$checks = json_decode( $result['body'], true );
		foreach ( $checks as $id => $check ) {
			$sorted_checks[ $id ] = $check['label'];
		}
		natcasesort( $sorted_checks );
		foreach ( $sorted_checks as $id => $label ) {
			$check       = $checks[ $id ];
			$date_offset = 0;
			// If a check isn't marked as public, we won't display it (might be configurable in the future).
			if ( empty( $check['public'] ) ) {
				continue;
			}
			if ( $alternate ) {
				$output .= "<tr class='odd'>\n";
			} else {
				$output .= "<tr class='even'>\n";
			}
			$output .= "<td class='sorting_1'>" . esc_html( $label ) . "</td>\n";

			// Query the API for current status.
			$url    = "https://api.nodeping.com/api/1/checks/$id?token=$api_token&lastresult=1";
			$result = wp_remote_get( $url );
			if ( ! is_wp_error( $result ) ) {
				$lastresult = json_decode( $result['body'], true );
				// if the check is in a failed state...
				if ( empty( $lastresult['lastresult']['su'] ) ) {
					$output .= "<td class='fail center'><i class='fa fa-arrow-circle-down'>&nbsp;</i></td>\n";
				} else {
					// Otherwise, thumbs up!
					$output .= "<td class='pass center'><i class='fa fa-arrow-circle-up'>&nbsp;</i></td>\n";
				}
			}
			// Query the API for uptime stats for the number of days specified.
			$url    = "https://api.nodeping.com/api/1/results/uptime/$id?token=$api_token&interval=days&start=$start_date";
			$result = wp_remote_get( $url );
			if ( ! is_wp_error( $result ) ) {
				$uptime = json_decode( $result['body'], true );
				// Even though the query may have retrieved more results than we need, we limit the number of columns to what the user requested.
				while ( $date_offset < $atts['days'] ) {
					// We start with today, and work our way back in time.
					$current_date = gmdate( 'Y-m-d', time() - 86400 * $date_offset );
					$date_offset++;
					// Make sure there is actually some data to display.
					if ( empty( $uptime[ $current_date ] ) ) {
						$output .= "<td>--</td>\n";
					} else {
						// 100 = green, 99+ is orange, below 99 is red
						if ( 100 === (int) $uptime[ $current_date ]['uptime'] ) {
							$uptime_class = 'pass';
						} elseif ( $uptime[ $current_date ]['uptime'] >= 99 ) {
							$uptime_class = 'disrupt';
						} else {
							$uptime_class = 'fail';
						}
						$output .= "<td class='$uptime_class center'>" . esc_html( $uptime[ $current_date ]['uptime'] ) . "%</td>\n";
					}
				}
				// Again, make sure we have something to output, just in case the query failed.
				if ( empty( $uptime['total']['uptime'] ) ) {
					$output .= "<td class='month'>--</td>\n";
				} else {
					// Output the total uptime for the 'total' days specified by the user.
					if ( 100 === (int) $uptime['total']['uptime'] ) {
						$uptime_class = 'pass';
					} elseif ( $uptime['total']['uptime'] >= 99 ) {
						$uptime_class = 'disrupt';
					} else {
						$uptime_class = 'fail';
					}
					$output .= "<td class='month $uptime_class center'>" . esc_html( $uptime['total']['uptime'] ) . "%</td>\n";
				}
			}
			$alternate = ! $alternate;
			$output   .= "</tr>\n";
		}
		$output .= "</tbody>\n</table>\n";
		// Send back our nice and pretty table.
		return $output;
	}
}

/**
 * Generates the status table when the shortcode is used.
 *
 * @param array $atts Any attributes passed via shortcode.
 * @return string The output for the shortcode.
 */
function nodeping_status_shortcode( $atts ) {
	if ( empty( $atts['report'] ) ) {
		return nodeping_legacy_status_shortcode( $atts );
	}

	// Set default attribute values if the user didn't set them.
	$atts = shortcode_atts(
		array(
			'days'   => '7',
			'report' => '',
		),
		$atts
	);

	$report_id = $atts['report'];

	// Retrieve a list of checks for the provided token.
	$url    = "https://api.nodeping.com/reports/status/$report_id/json";
	$result = wp_remote_get( $url );
	if ( is_wp_error( $result ) ) {
		return '';
	} elseif ( ! empty( $result['body'] ) && preg_match( '/uuid.+label.+type/', $result['body'] ) ) {
		$output      = '';
		$date_offset = 0;
		$alternate   = true;

		$output .= "<table id='statusgrid'>\n<thead>\n<tr>\n";
		$output .= "<th>&nbsp;</th>\n";
		$output .= "<th class='center'>" . esc_html__( 'Status', 'nodeping-status' ) . "</th>\n";

		while ( $date_offset < $atts['days'] ) {
			$current_date = gmdate( 'Y-m-d', time() - 86400 * $date_offset );
			$date_offset++;
			$output .= "<th class='center'>" . esc_html( $current_date ) . "</th>\n";
		}

		/* translators: %d: number of days */
		$output .= "<th class='center'>" . sprintf( esc_html__( '%d Days', 'nodeping-status' ), 30 ) . "</th>\n";
		$output .= "</tr>\n</thead>\n<tbody>\n";

		// Put the list of checks we retrieved into an array (the API gives us JSON data).
		$checks = json_decode( $result['body'], true );
		foreach ( $checks as $id => $check ) {
			$sorted_checks[ $id ] = $check['label'];
		}
		natcasesort( $sorted_checks );
		foreach ( $sorted_checks as $id => $label ) {
			$check       = $checks[ $id ];
			$check_uuid  = $check['uuid'];
			$date_offset = 0;
			if ( $alternate ) {
				$output .= "<tr class='odd'>\n";
			} else {
				$output .= "<tr class='even'>\n";
			}

			if ( $check['public'] ) {
				$output .= "<td class='sorting_1'><a href='" . esc_url( "https://nodeping.com/reports/results/$check_uuid" ) . "'>" . esc_html( $label ) . "</a></td>\n";
			} else {
				$output .= "<td class='sorting_1'>" . esc_html( $label ) . "</td>\n";
			}

			if ( ! $check['state'] ) {
				$output .= "<td class='fail center'><i class='fa fa-arrow-circle-down'>&nbsp;</i></td>\n";
			} else {
				// Otherwise, thumbs up!
				$output .= "<td class='pass center'><i class='fa fa-arrow-circle-up'>&nbsp;</i></td>\n";
			}

			$uptime = $check['uptime'];
			// Even though the query may have retrieved more results than we need, we limit the number of columns to what the user requested.
			while ( $date_offset < $atts['days'] ) {
				// We start with today, and work our way back in time.
				$current_date = gmdate( 'Y-m-d', time() - 86400 * $date_offset );
				$date_offset++;
				// Make sure there is actually some data to display.
				if ( empty( $uptime[ $current_date ] ) ) {
					$output .= "<td>--</td>\n";
				} else {
					// 100 = green, 99+ is orange, below 99 is red
					if ( 100 === (int) $uptime[ $current_date ]['uptime'] ) {
						$uptime_class = 'pass';
					} elseif ( $uptime[ $current_date ]['uptime'] >= 99 ) {
						$uptime_class = 'disrupt';
					} else {
						$uptime_class = 'fail';
					}
					$output .= "<td class='$uptime_class center'>" . esc_html( $uptime[ $current_date ]['uptime'] ) . "%</td>\n";
				}
			}
			// Again, make sure we have something to output, just in case the query failed.
			if ( empty( $uptime['total']['uptime'] ) ) {
				$output .= "<td class='month'>--</td>\n";
			} else {
				// Output the total uptime for the 'total' days specified by the user.
				if ( 100 === (int) $uptime['total']['uptime'] ) {
					$uptime_class = 'pass';
				} elseif ( $uptime['total']['uptime'] >= 99 ) {
					$uptime_class = 'disrupt';
				} else {
					$uptime_class = 'fail';
				}
				$output .= "<td class='month $uptime_class center'>" . esc_html( $uptime['total']['uptime'] ) . "%</td>\n";
			}
			$alternate = ! $alternate;
			$output   .= "</tr>\n";
		}
		$output .= "</tbody>\n</table>\n";
		// Send back our nice and pretty table.
		return $output;
	}
}
add_shortcode( 'nodeping_status', 'nodeping_status_shortcode' );

/**
 * Displays the NodePing options page.
 */
function nodeping_status_options() {
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'NodePing Status Settings', 'nodeping-status' ); ?></h2>
		<p><a href="http://wordpress.org/extend/plugins/nodeping-status/"><?php esc_html_e( 'Plugin Home Page', 'nodeping-status' ); ?></a> |
		<a href="http://wordpress.org/support/plugin/nodeping-status"><?php esc_html_e( 'Plugin Support', 'nodeping-status' ); ?></a></p>
		<p><?php esc_html_e( 'A NodePing report page can be embedded with this shortcode:', 'nodeping-status' ); ?><br />
			<pre>[nodeping_status report="XYZ" days="7"]</pre>
		<?php esc_html_e( 'The days attribute is optional and determines how many days of uptime stats to display.', 'nodeping-status' ); ?></p>
		<p>
			<?php
			echo '<strong>' . esc_html__( 'The API Token is no longer necessary and should be left empty.', 'nodeping-status' ) . '</strong><br>' .
			'<ol><li>' . esc_html__( 'Create a public status report in your NodePing account (Account Settings->Reporting).', 'nodeping-status' ) . '</li>' .
			'<li>' . esc_html__( 'Enter the report ID at the end of the report link as the report attribute in the shortcode.', 'nodeping-status' ) . '</li>' .
			'<li>' . esc_html__( 'Enable public reports on each check and the plugin will automatically include a link to the public report page.', 'nodeping-status' ) . '</li></ol>';
			?>
		</p>
	<?php if ( get_option( 'nodeping_status_api_token' ) ) { ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'nodeping_status_options' ); ?>
			<p class="description"><?php esc_html_e( 'Be very careful with your API Token, as it can be used to make changes to your account.', 'nodeping-status' ); ?></p>
			<table class="form-table">
				<tr>
					<th>
						<label for="nodeping_status_api_token"><?php esc_html_e( 'NodePing API Token (deprecated)', 'nodeping-status' ); ?></label>
					</th>
					<td>
						<input type="text" id="nodeping_status_api_token" name="nodeping_status_api_token" value="<?php echo esc_attr( get_option( 'nodeping_status_api_token' ) ); ?>" size="40" /> <?php echo ( get_option( 'nodeping_status_api_token' ) ? "<i class='fa fa-check-circle' style='color: #366836'>&nbsp;</i>" : '' ); ?>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes', 'nodeping-status' ); ?>" /></p>
		</form>
	<?php } ?>
	</div>
	<?php
}
