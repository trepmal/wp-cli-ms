<?php
// Plugin Name: MS Quicklook

if ( !defined( 'WP_CLI' ) ) return;

/**
 * WP MS QUICKLOOK
 */
class WP_MS_QUICKLOOK extends WP_CLI_Command {

	/**
	 * View plugins on all sites
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins, by slug, to look at
	 *
	 * [--hide_empty]
	 * : Hide sites with no active plugins
	 *
	 * ## EXAMPLES
	 *
	 *     wp ms plugins
	 *     wp ms plugins akismet hello --hide_empty
	 *
	 */
	function plugins( $args, $assoc_args ) {

		if ( ! is_multisite() ) {
			WP_CLI::error( 'Must be multisite.' );
		}

		global $wpdb;

		$allblogs = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid), ARRAY_A );
		$blog_list = wp_list_pluck( $allblogs, 'blog_id', 'blog_id');

		$find = array();
		// did we specify a limited set of plugins...
		if ( isset( $args[0] ) ) {
			$this->fetcher = new \WP_CLI\Fetchers\Plugin;
			$find = $this->fetcher->get_many( $args );
			$find = array_flip( wp_list_pluck( $find, 'file' ) );
		}

		$allplugins = get_plugins();

		// if we specified which plugin(s) to look for, reduce our list
		if ( $find ) {
			$allplugins = array_intersect_key( $allplugins, $find );
		}
		// get plugin names
		$_plugin_legend = $plugin_legend = array_values( wp_list_pluck( $allplugins, 'Name' ) );

		// get network-activated plugins
		$_network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
		$network_plugins = array_intersect_key( $allplugins, $_network_plugins );
		$network_plugin_names = array_values( wp_list_pluck( $network_plugins, 'Name' ) );

		// build a legend using full plugin names and key-based abbrvs
		// e.g. array( 0 => 'Akismet') would become array( key => p0, name => Akismet )
		$legend = $nalegend = array();
		$total = count( $_plugin_legend );
		foreach( $_plugin_legend as $_plk => $_plv ) {
			$k = $_plk;
			$v = $_plv;
			// net-act: colorize, add paren, bump to end
			if ( in_array( $v, $network_plugin_names ) ) {
				$k = "%rp$k%n";
				$v = "%r$v (network activated)%n";
				$_plk += $total; // push to end of list
			} else {
				$k = "p$k";
				$plugin_legend_keys[ $k ] = '';
			}
			$legend[ $_plk ] = array( 'key' => $k, 'name' => $v );
		}
		ksort( $legend );

		// output legend
		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'key', 'name' ), 'stash' );
		$formatter->display_items( $legend );

		// iterate over every blog on the network
		foreach ( $allblogs as $blog ) {
			$blog_id = $blog['blog_id'];

			// start builing the table. For the headings we want ID, Blog Name, and each plugin abbreviation
			$keys = array_merge( array( 'id' => '', 'blog_name' => '' ), $plugin_legend_keys );

			// set up our keys (columns) for the current blog (row)
			$blog_list[ $blog_id ] = $keys;
			// and insert the blog id for current row
			$blog_list[ $blog_id ]['id'] = $blog_id;

			switch_to_blog( $blog_id );
			// fetch the blog name and insert into the row
			$blog_list[ $blog_id ]['blog_name'] = get_bloginfo('name');

			// find the options table
			$blog_tables = $wpdb->tables('blog');
			// get the value
			$plugins = $wpdb->get_var( $wpdb->prepare("SELECT option_value FROM {$blog_tables['options']} WHERE option_name = %s", 'active_plugins' ) );
			// unserizialize plugin list
			$plugins = maybe_unserialize( $plugins );

			$hasplugins = false; // flag

			// iterate over each active plugin, update column in row as needed
			foreach( $plugins as $plugin ) {
				$fullpath = WP_PLUGIN_DIR. '/' . $plugin;
				$deets = get_plugin_data( $fullpath );

				if ( false !== ( $key = array_search( $deets['Name'], $plugin_legend ) ) ) {
					$hasplugins = true;
					$blog_list[ $blog_id ][ "p$key" ] = 'X';
				}

			}

			// if no plugins, hide row when requested
			if ( ! $hasplugins && isset( $assoc_args['hide_empty'] ) ) {
				unset( $blog_list[ $blog_id ] );
			}

			restore_current_blog();
		}

		ksort( $blog_list );

		// output table
		$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $keys ), 'stash' );
		$formatter->display_items( $blog_list );

	}

	/**
	 * View theme on all sites
	 *
	 * ## OPTIONS
	 *
	 * [<theme>...]
	 * : One or more themes, by slug, to look at
	 *
	 * ## EXAMPLES
	 *
	 *     wp ms themes
	 *     wp ms themes twentytwelve
	 *
	 */
	function themes( $args, $assoc_args ) {

		if ( ! is_multisite() ) {
			WP_CLI::error( 'Must be multisite.' );
		}

		global $wpdb;

		$allblogs = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid), ARRAY_A );
		$blog_list = wp_list_pluck( $allblogs, 'blog_id', 'blog_id');

		$find = array();
		if ( isset( $args[0] ) ) {
			$this->fetcher = new \WP_CLI\Fetchers\Theme;
			$find = $this->fetcher->get_many( $args );

			foreach( $find as $k => $v ) {
				$find[ $k ] = $v['Name'];
			}

			$find = array_flip( $find );
		}


		$allthemes = wp_get_themes();
		foreach( $allthemes as $k => $v ) {
			$allthemes[ $k ] = $v['Name'];
		}

		foreach ( $allblogs as $blog ) {
			$blog_id = $blog['blog_id'];

			$keys = array( 'id' => '', 'blog_name' => '', 'theme' => '' );

			$blog_list[ $blog_id ] = $keys;
			$blog_list[ $blog_id ]['id'] = $blog_id;

			switch_to_blog( $blog_id );
			$blog_list[ $blog_id ]['blog_name'] = get_bloginfo('name');

			$blog_tables = $wpdb->tables('blog');
			$stylesheet = $wpdb->get_var( $wpdb->prepare("SELECT option_value FROM {$blog_tables['options']} WHERE option_name = %s", 'stylesheet' ) );
			$theme = wp_get_theme( $stylesheet );

			$blog_list[ $blog_id ]['theme'] = $theme['Name'];

			if ( ! empty( $find ) && ! isset( $find[$theme['Name']] ) ) {
				unset( $blog_list[ $blog_id ] );
			}

			restore_current_blog();
		}

		ksort( $blog_list );
		$formatter = new \WP_CLI\Formatter( $assoc_args, array_keys( $keys ), 'stash' );
		$formatter->display_items( $blog_list );

	}

}

WP_CLI::add_command( 'ms', 'WP_MS_QUICKLOOK' );