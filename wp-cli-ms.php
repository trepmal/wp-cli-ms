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
		if ( isset( $args[0] ) ) {
			$this->fetcher = new \WP_CLI\Fetchers\Plugin;
			$find = $this->fetcher->get_many( $args );
			$find = array_flip( wp_list_pluck( $find, 'file' ) );
		}

		$allplugins = get_plugins();
		if ( $find ) {
			$allplugins = array_intersect_key($allplugins, $find );
		}

		$plugin_legend = array_values( wp_list_pluck( $allplugins, 'Name' ) );
		$_plugin_legend = $plugin_legend;
		$legend = array();
		foreach( $_plugin_legend as $_plk => $_plv ) {
			$legend[] = array( 'key' => "p$_plk", 'name' => $_plv );
			$plugin_legend_keys["p$_plk"] = '';
		}

		// output legend
		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'key', 'name' ), 'stash' );
		$formatter->display_items( $legend );

		foreach ( $allblogs as $blog ) {
			$blog_id = $blog['blog_id'];

			$keys = array_merge( array( 'id' => '', 'blog_name' => '' ), $plugin_legend_keys );

			$blog_list[ $blog_id ] = $keys;
			$blog_list[ $blog_id ]['id'] = $blog_id;

			switch_to_blog( $blog_id );
			$blog_list[ $blog_id ]['blog_name'] = get_bloginfo('name');

			$blog_tables = $wpdb->tables('blog');
			$plugins = $wpdb->get_var( $wpdb->prepare("SELECT option_value FROM {$blog_tables['options']} WHERE option_name = %s", 'active_plugins' ) );
			$plugins = maybe_unserialize( $plugins );

			$hasplugins = false;
			foreach( $plugins as $plugin ) {
				$fullpath = WP_PLUGIN_DIR. '/' . $plugin;
				$deets = get_plugin_data( $fullpath );

				if ( false !== ( $key = array_search( $deets['Name'], $plugin_legend ) ) ) {
					$hasplugins = true;
					$blog_list[ $blog_id ][ "p$key" ] = 'X';
				}

			}

			if ( ! $hasplugins && isset( $assoc_args['hide_empty'] ) ) {
				unset( $blog_list[ $blog_id ] );
			}

			restore_current_blog();
		}

		ksort( $blog_list );
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