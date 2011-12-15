<?php
/*
Plugin Name:	Table of Contents Plus
Plugin URI: 	http://dublue.com/plugins/toc/
Description: 	A powerful yet user friendly plugin that automatically creates a table of contents. Can also output a sitemap listing all pages and categories.
Author: 		Michael Tran
Author URI: 	http://dublue.com/
Version: 		1112.1
License:		GPL2
*/

/*  Copyright 2011  Michael Tran  (michael@dublue.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
GPL licenced Oxygen icon used for the colour wheel - http://www.iconfinder.com/search/?q=iconset%3Aoxygen
*/

/**
FOR CONSIDERATION:
- back to top links
- sitemap
	- easier exclude pages/categories
	- support other taxonomies
- advanced options
	- highlight target css
*/


define( 'TOC_POSITION_BEFORE_FIRST_HEADING', 1 );
define( 'TOC_POSITION_TOP', 2 );
define( 'TOC_POSITION_BOTTOM', 3 );
define( 'TOC_MIN_START', 3 );
define( 'TOC_MAX_START', 10 );
define( 'TOC_WRAPPING_NONE', 0 );
define( 'TOC_WRAPPING_LEFT', 1 );
define( 'TOC_WRAPPING_RIGHT', 2 );
define( 'TOC_THEME_GREY', 1 );
define( 'TOC_THEME_LIGHT_BLUE', 2 );
define( 'TOC_THEME_WHITE', 3 );
define( 'TOC_THEME_BLACK', 4 );
define( 'TOC_THEME_TRANSPARENT', 99 );
define( 'TOC_THEME_CUSTOM', 100 );
define( 'TOC_DEFAULT_BACKGROUND_COLOUR', '#f9f9f9' );
define( 'TOC_DEFAULT_BORDER_COLOUR', '#aaaaaa' );
define( 'TOC_DEFAULT_TITLE_COLOUR', '#' );
define( 'TOC_DEFAULT_LINKS_COLOUR', '#' );
define( 'TOC_DEFAULT_LINKS_HOVER_COLOUR', '#' );
define( 'TOC_DEFAULT_LINKS_VISITED_COLOUR', '#' );


if ( !class_exists( 'toc' ) ) :
	class toc {
		
		private $path;		// eg /wp-content/plugins/toc
		private $options;
		private $show_toc;	// allows to override the display (eg through [no_toc] shortcode)
		private $exclude_post_types;
		private $collision_collector;	// keeps a track of used anchors for collision detecting
		
		function __construct()
		{
			$this->path = dirname( WP_PLUGIN_URL . '/' . plugin_basename( __FILE__ ) );
			$this->show_toc = true;
			$this->exclude_post_types = array( 'attachment', 'revision', 'nav_menu_item', 'safecss' );
			$this->collision_collector = array();

			// get options
			$defaults = array(		// default options
				'fragment_prefix' => 'i',
				'position' => TOC_POSITION_BEFORE_FIRST_HEADING,
				'start' => 4,
				'show_heading_text' => true,
				'heading_text' => 'Contents',
				'auto_insert_post_types' => array('page'),
				'show_heirarchy' => true,
				'ordered_list' => true,
				'smooth_scroll' => false,
				'visibility' => true,
				'visibility_show' => 'show',
				'visibility_hide' => 'hide',
				'width' => '275px',
				'width_custom' => '275',
				'width_custom_units' => 'px',
				'wrapping' => TOC_WRAPPING_NONE,
				'font_size' => '95',
				'font_size_units' => '%',
				'theme' => TOC_THEME_GREY,
				'custom_background_colour' => TOC_DEFAULT_BACKGROUND_COLOUR,
				'custom_border_colour' => TOC_DEFAULT_BORDER_COLOUR,
				'custom_title_colour' => TOC_DEFAULT_TITLE_COLOUR,
				'custom_links_colour' => TOC_DEFAULT_LINKS_COLOUR,
				'custom_links_hover_colour' => TOC_DEFAULT_LINKS_HOVER_COLOUR,
				'custom_links_visited_colour' => TOC_DEFAULT_LINKS_VISITED_COLOUR,
				'bullet_spacing' => false,
				'include_homepage' => false,
				'heading_levels' => array('1', '2', '3', '4', '5', '6'),
				'sitemap_show_page_listing' => true,
				'sitemap_show_category_listing' => true,
				'sitemap_heading_type' => 3,
				'sitemap_pages' => 'Pages',
				'sitemap_categories' => 'Categories',
				'show_toc_in_widget_only' => false
			);
			$options = get_option( 'toc-options', $defaults );
			$this->options = wp_parse_args( $options, $defaults );
			
			add_action( 'init', array(&$this, 'init') );
			add_action( 'wp_print_styles', array(&$this, 'public_styles') );
			add_action( 'template_redirect', array(&$this, 'template_redirect') );
			add_action( 'wp_head', array(&$this, 'wp_head') );
			add_action( 'admin_init', array(&$this, 'admin_init') );
			add_action( 'admin_menu', array(&$this, 'admin_menu') );
			add_action( 'widgets_init', array(&$this, 'widgets_init') );
			
			add_filter( 'the_content', array(&$this, 'the_content'), 11 );	// run after shortcodes are interpretted (level 10)
			add_filter( 'plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
			add_filter( 'widget_text', 'do_shortcode' );
			
			add_shortcode( 'toc', array(&$this, 'shortcode_toc') );
			add_shortcode( 'no_toc', array(&$this, 'shortcode_no_toc') );
			add_shortcode( 'sitemap', array(&$this, 'shortcode_sitemap') );
			add_shortcode( 'sitemap_pages', array(&$this, 'shortcode_sitemap_pages') );
			add_shortcode( 'sitemap_categories', array(&$this, 'shortcode_sitemap_categories') );
		}
		
		
		function __destruct()
		{
		}
		
		
		public function get_options()
		{
			return $this->options;
		}
		
		
		public function set_show_toc_in_widget_only( $value = false )
		{
			if ( $value )
				$this->options['show_toc_in_widget_only'] = true;
			else
				$this->options['show_toc_in_widget_only'] = false;
			
			update_option( 'toc-options', $this->options );
		}
		
		
		function plugin_action_links( $links, $file )
		{
			if ( $file == 'table-of-contents-plus/' . basename(__FILE__) ) {
				$settings_link = '<a href="options-general.php?page=toc">' . __('Settings', 'toc+') . '</a>';
				$links = array_merge( array( $settings_link ), $links );
			}
			return $links;
		}
		
		
		function shortcode_toc( $atts )
		{
			extract( shortcode_atts( array(
				'label' => $this->options['heading_text'],
				'no_label' => false,
				'wrapping' => $this->options['wrapping'],
				'heading_levels' => $this->options['heading_levels']
				), $atts )
			);
			
			if ( $no_label ) $this->options['show_heading_text'] = false;
			if ( $label ) $this->options['heading_text'] = html_entity_decode( $label );
			if ( $wrapping ) {
				switch ( strtolower(trim($wrapping)) ) {
					case 'left':
						$this->options['wrapping'] = TOC_WRAPPING_LEFT;
						break;
						
					case 'right':
						$this->options['wrapping'] = TOC_WRAPPING_RIGHT;
						break;
						
					default:
						// do nothing
				}
			}
			
			// if $heading_levels is an array, then it came from the global options
			// and wasn't provided by per instance
			if ( $heading_levels && !is_array($heading_levels) ) {
				// make sure they are numbers between 1 and 6 and put into 
				// the $clean_heading_levels array if not already
				$clean_heading_levels = array();
				foreach (explode(',', $heading_levels) as $heading_level) {
					if ( is_numeric($heading_level) ) {
						if ( 1 <= $heading_level && $heading_level <= 6 ) {
							if ( !in_array($heading_level, $clean_heading_levels) ) {
								$clean_heading_levels[] = $heading_level;
							}
						}
					}
				}
				
				if ( count($clean_heading_levels) > 0 )
					$this->options['heading_levels'] = $clean_heading_levels;
			}
		
			if ( !is_search() && !is_archive() )
				return '<!--TOC-->';
			else
				return;
		}
		
		
		function shortcode_no_toc( $atts )
		{
			$this->show_toc = false;

			return;
		}
		
		
		function shortcode_sitemap( $atts )
		{
			$html = '';
			
			// only do the following if enabled
			if ( $this->options['sitemap_show_page_listing'] || $this->options['sitemap_show_category_listing'] ) {
				$html = '<div class="toc_sitemap">';
				if ( $this->options['sitemap_show_page_listing'] )
					$html .=
						'<h' . $this->options['sitemap_heading_type'] . ' class="toc_sitemap_pages">' . htmlentities( $this->options['sitemap_pages'], ENT_COMPAT, 'UTF-8' ) . '</h' . $this->options['sitemap_heading_type'] . '>' .
						'<ul class="toc_sitemap_pages_list">' .
							wp_list_pages( array('title_li' => '', 'echo' => false ) ) .
						'</ul>'
					;
				if ( $this->options['sitemap_show_category_listing'] )
					$html .=
						'<h' . $this->options['sitemap_heading_type'] . ' class="toc_sitemap_categories">' . htmlentities( $this->options['sitemap_categories'], ENT_COMPAT, 'UTF-8' ) . '</h' . $this->options['sitemap_heading_type'] . '>' .
						'<ul class="toc_sitemap_categories_list">' .
							wp_list_categories( array( 'title_li' => '', 'echo' => false ) ) .
						'</ul>'
					;
				$html .= '</div>';
			}
			
			return $html;
		}
		
		
		function shortcode_sitemap_pages( $atts )
		{
			extract( shortcode_atts( array(
				'heading' => $this->options['sitemap_heading_type'],
				'label' => htmlentities( $this->options['sitemap_pages'], ENT_COMPAT, 'UTF-8' ),
				'no_label' => false,
				'exclude' => ''
				), $atts )
			);
			
			if ( $heading < 1 || $heading > 6 )		// h1 to h6 are valid
				$heading = $this->options['sitemap_heading_type'];
			
			$html = '<div class="toc_sitemap">';
			if ( !$no_label ) $html .= '<h' . $heading . ' class="toc_sitemap_pages">' . $label . '</h' . $heading . '>';
			$html .=
					'<ul class="toc_sitemap_pages_list">' .
						wp_list_pages( array('title_li' => '', 'echo' => false, 'exclude' => $exclude ) ) .
					'</ul>' .
				'</div>'
			;
			
			return $html;
		}
		
		
		function shortcode_sitemap_categories( $atts )
		{
			extract( shortcode_atts( array(
				'heading' => $this->options['sitemap_heading_type'],
				'label' => htmlentities( $this->options['sitemap_pages'], ENT_COMPAT, 'UTF-8' ),
				'no_label' => false,
				'exclude' => ''
				), $atts )
			);
			
			if ( $heading < 1 || $heading > 6 )		// h1 to h6 are valid
				$heading = $this->options['sitemap_heading_type'];
			
			$html = '<div class="toc_sitemap">';
			if ( !$no_label ) $html .= '<h' . $heading . ' class="toc_sitemap_categories">' . $label . '</h' . $heading . '>';
			$html .=
					'<ul class="toc_sitemap_categories_list">' .
						wp_list_categories( array('title_li' => '', 'echo' => false, 'exclude' => $exclude ) ) .
					'</ul>' .
				'</div>'
			;
			
			return $html;
		}
		
		
		function init()
		{
			wp_register_style( 'toc-screen', $this->path . '/screen.css' );
			wp_register_script( 'smooth-scroll', $this->path . '/jquery.smooth-scroll.min.js', array('jquery') );
			wp_register_script( 'cookie', $this->path . '/jquery.c.min.js', array('jquery') );
			wp_register_script( 'toc-front', $this->path . '/front.js', array('jquery') );
		}
		
		
		function admin_init()
		{
			wp_register_script( 'toc_admin_script', $this->path . '/admin.js' );
			wp_register_style( 'toc_admin_style', $this->path . '/admin.css' );
		}
		
		
		function admin_menu()
		{
			$page = add_submenu_page(
				'options-general.php', 
				__('TOC', 'toc+') . '+', 
				__('TOC', 'toc+') . '+', 
				'manage_options', 
				'toc', 
				array(&$this, 'admin_options')
			);
			
			add_action( 'admin_print_styles-' . $page, array(&$this, 'admin_options_head') );
		}
		
		
		function widgets_init()
		{
			register_widget('toc_widget');
		}
		
		
		/**
		 * Load needed scripts and styles only on the toc administration interface.
		 */
		function admin_options_head()
		{
			wp_enqueue_style( 'farbtastic' );
			wp_enqueue_script( 'farbtastic' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'toc_admin_script' );
			wp_enqueue_style( 'toc_admin_style' );
		}
		
		
		/**
		 * Tries to convert $string into a valid hex colour.
		 * Returns $default if $string is not a hex value, otherwise returns verified hex.
		 */
		private function hex_value( $string = '', $default = '#' )
		{
			$return = $default;
			
			if ( $string ) {
				// strip out non hex chars
				$return = preg_replace( '/[^a-fA-F0-9]*/', '', $string );
				
				switch ( strlen($return) ) {
					case 3:	// do next
					case 6:
						$return = '#' . $return;
						break;
					
					default:
						if ( strlen($return) > 6 )
							$return = '#' . substr($return, 0, 6);	// if > 6 chars, then take the first 6
						elseif ( strlen($return) > 3 && strlen($return) < 6 )
							$return = '#' . substr($return, 0, 3);	// if between 3 and 6, then take first 3
						else
							$return = $default;						// not valid, return $default
				}
			}
			
			return $return;
		}
		
		
		private function save_admin_options()
		{
			global $post_id;

			// security check
			if ( !wp_verify_nonce( @$_POST['toc-admin-options'], plugin_basename(__FILE__) ) )
				return false;

			// require an administrator level to save
			if ( !current_user_can( 'manage_options', $post_id ) )
				return false;
			
			// use stripslashes on free text fields that can have ' " \
			// WordPress automatically slashes these characters as part of 
			// wp-includes/load.php::wp_magic_quotes()
			
			$custom_background_colour = $this->hex_value( trim($_POST['custom_background_colour']), TOC_DEFAULT_BACKGROUND_COLOUR );
			$custom_border_colour = $this->hex_value( trim($_POST['custom_border_colour']), TOC_DEFAULT_BORDER_COLOUR );
			$custom_title_colour = $this->hex_value( trim($_POST['custom_title_colour']), TOC_DEFAULT_TITLE_COLOUR );
			$custom_links_colour = $this->hex_value( trim($_POST['custom_links_colour']), TOC_DEFAULT_LINKS_COLOUR );
			$custom_links_hover_colour = $this->hex_value( trim($_POST['custom_links_hover_colour']), TOC_DEFAULT_LINKS_HOVER_COLOUR );
			//$custom_links_visited_colour = $this->hex_value( trim($_POST['custom_links_visited_colour']), TOC_DEFAULT_LINKS_VISITED_COLOUR );

			$this->options = array(
				'fragment_prefix' => trim($_POST['fragment_prefix']),
				'position' => intval($_POST['position']),
				'start' => intval($_POST['start']),
				'show_heading_text' => (isset($_POST['show_heading_text']) && $_POST['show_heading_text']) ? true : false,
				'heading_text' => stripslashes( trim($_POST['heading_text']) ),
				'auto_insert_post_types' => @(array)$_POST['auto_insert_post_types'],
				'show_heirarchy' => (isset($_POST['show_heirarchy']) && $_POST['show_heirarchy']) ? true : false,
				'ordered_list' => (isset($_POST['ordered_list']) && $_POST['ordered_list']) ? true : false,
				'smooth_scroll' => (isset($_POST['smooth_scroll']) && $_POST['smooth_scroll']) ? true : false,
				'visibility' => (isset($_POST['visibility']) && $_POST['visibility']) ? true : false,
				'visibility_show' => stripslashes( trim($_POST['visibility_show']) ),
				'visibility_hide' => stripslashes( trim($_POST['visibility_hide']) ),
				'width' => trim($_POST['width']),
				'width_custom' => floatval($_POST['width_custom']),
				'width_custom_units' => trim($_POST['width_custom_units']),
				'wrapping' => intval($_POST['wrapping']),
				'font_size' => floatval($_POST['font_size']),
				'font_size_units' => trim($_POST['font_size_units']),
				'theme' => intval($_POST['theme']),
				'custom_background_colour' => $custom_background_colour,
				'custom_border_colour' => $custom_border_colour,
				'custom_title_colour' => $custom_title_colour,
				'custom_links_colour' => $custom_links_colour,
				'custom_links_hover_colour' => $custom_links_hover_colour,
				'bullet_spacing' => (isset($_POST['bullet_spacing']) && $_POST['bullet_spacing']) ? true : false,
				'include_homepage' => (isset($_POST['include_homepage']) && $_POST['include_homepage']) ? true : false,
				'heading_levels' => @(array)$_POST['heading_levels'],
				'sitemap_show_page_listing' => (isset($_POST['sitemap_show_page_listing']) && $_POST['sitemap_show_page_listing']) ? true : false,
				'sitemap_show_category_listing' => (isset($_POST['sitemap_show_category_listing']) && $_POST['sitemap_show_category_listing']) ? true : false,
				'sitemap_heading_type' => intval($_POST['sitemap_heading_type']),
				'sitemap_pages' => stripslashes( trim($_POST['sitemap_pages']) ),
				'sitemap_categories' => stripslashes( trim($_POST['sitemap_categories']) )
			);
			
			// update_option will return false if no changes were made
			update_option( 'toc-options', $this->options );
			
			return true;
		}
		
		
		function admin_options() 
		{
			$msg = '';
		
			if ( isset( $_GET['update'] ) ) {
				if ( $this->save_admin_options() )
					$msg = '<div id="message" class="updated fade"><p>' . __('Options saved.', 'toc+') . '</p></div>';
				else
					$msg = '<div id="message" class="error fade"><p>' . __('Save failed.', 'toc+') . '</p></div>';
			}

?>
<div id='toc' class='wrap'>
<div id="icon-options-general" class="icon32"><br /></div>
<h2><?php _e('Table of Contents Plus', 'toc+'); ?></h2>
<?php echo $msg; ?>
<form method="post" action="<?php echo htmlentities('?page=' . $_GET['page'] . '&update'); ?>">
<?php wp_nonce_field( plugin_basename(__FILE__), 'toc-admin-options' ); ?>

<ul id="tabbed-nav">
	<li><a href="#tab1"><?php _e('Main Options', 'toc+'); ?></a></li>
	<li><a href="#tab2"><?php _e('Sitemap', 'toc+'); ?></a></li>
	<li><a href="#tab3"><?php _e('Help', 'toc+'); ?></a></li>
</ul>
<div class="tab_container">
	<div id="tab1" class="tab_content">
  
<table class="form-table">
<tbody>
<tr>
	<th><label for="position"><?php _e('Position', 'toc+'); ?></label></th>
	<td>
		<select name="position" id="position">
			<option value="<?php echo TOC_POSITION_BEFORE_FIRST_HEADING; ?>"<?php if ( TOC_POSITION_BEFORE_FIRST_HEADING == $this->options['position'] ) echo ' selected="selected"'; ?>>Before first heading (default)</option>
			<option value="<?php echo TOC_POSITION_TOP; ?>"<?php if ( TOC_POSITION_TOP == $this->options['position'] ) echo ' selected="selected"'; ?>>Top</option>
			<option value="<?php echo TOC_POSITION_BOTTOM; ?>"<?php if ( TOC_POSITION_BOTTOM == $this->options['position'] ) echo ' selected="selected"'; ?>>Bottom</option>
		</select>
	</td>
</tr>
<tr>
	<th><label for="start"><?php _e('Show when', 'toc+'); ?></label></th>
	<td>
		<select name="start" id="start">
<?php
			for ($i = TOC_MIN_START; $i <= TOC_MAX_START; $i++) {
				echo '<option value="' . $i . '"';
				if ( $i == $this->options['start'] ) echo ' selected="selected"';
				echo '>' . $i . '</option>' . "\n";
			}
?>
		</select> <?php _e('or more headings are present', 'toc+'); ?>
	</td>
</tr>
<tr>
	<th><?php _e('Auto insert for the following content types', 'toc+'); ?></th>
	<td><?php
			foreach (get_post_types() as $post_type) {
				// make sure the post type isn't on the exclusion list
				if ( !in_array($post_type, $this->exclude_post_types) ) {
					echo '<input type="checkbox" value="' . $post_type . '" id="auto_insert_post_types_' . $post_type .'" name="auto_insert_post_types[]"';
					if ( in_array($post_type, $this->options['auto_insert_post_types']) ) echo ' checked="checked"';
					echo ' /><label for="auto_insert_post_types_' . $post_type .'"> ' . $post_type . '</label><br />';
				}
			}
?>
</tr>
<tr>
	<th><label for="show_heading_text"><?php _e('Heading text', 'toc+'); ?></label></th>
	<td>
		<input type="checkbox" value="1" id="show_heading_text" name="show_heading_text"<?php if ( $this->options['show_heading_text'] ) echo ' checked="checked"'; ?> /><label for="show_heading_text"> <?php _e('Show title on top of the table of contents', 'toc+'); ?></label><br />
		<div class="more_toc_options<?php if ( !$this->options['show_heading_text'] ) echo ' disabled'; ?>">
			<input type="text" class="regular-text" value="<?php echo htmlentities( $this->options['heading_text'], ENT_COMPAT, 'UTF-8' ); ?>" id="heading_text" name="heading_text" />
			<span class="description"><label for="heading_text"><?php _e('Eg: Contents, Table of Contents, Page Contents', 'toc+'); ?></label></span><br /><br />
			
			<input type="checkbox" value="1" id="visibility" name="visibility"<?php if ( $this->options['visibility'] ) echo ' checked="checked"'; ?> /><label for="visibility"> <?php _e( 'Allow the user to toggle the visibility of the table of contents', 'toc+'); ?></label><br />
			<div class="more_toc_options<?php if ( !$this->options['visibility'] ) echo ' disabled'; ?>">
				<table class="more_toc_options_table">
				<tbody>
				<tr>
					<th><label for="visibility_show"><?php _e('Show text', 'toc+'); ?></label></th>
					<td><input type="text" class="" value="<?php echo htmlentities( $this->options['visibility_show'], ENT_COMPAT, 'UTF-8' ); ?>" id="visibility_show" name="visibility_show" />
					<span class="description"><label for="visibility_show"><?php _e('Eg: show', 'toc+'); ?></label></span></td>
				</tr>
				<tr>
					<th><label for="visibility_hide"><?php _e('Hide text', 'toc+'); ?></label></th>
					<td><input type="text" class="" value="<?php echo htmlentities( $this->options['visibility_hide'], ENT_COMPAT, 'UTF-8' ); ?>" id="visibility_hide" name="visibility_hide" />
					<span class="description"><label for="visibility_hide"><?php _e('Eg: hide', 'toc+'); ?></label></span></td>
				</tr>
				</tbody>
				</table>
			</div>
		</div>
	</td>
</tr>
<tr>
	<th><label for="show_heirarchy"><?php _e('Show hierarchy', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="show_heirarchy" name="show_heirarchy"<?php if ( $this->options['show_heirarchy'] ) echo ' checked="checked"'; ?> /></td>
</tr>
<tr>
	<th><label for="ordered_list"><?php _e('Number list items', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="ordered_list" name="ordered_list"<?php if ( $this->options['ordered_list'] ) echo ' checked="checked"'; ?> /></td>
</tr>
<tr>
	<th><label for="smooth_scroll"><?php _e('Enable smooth scroll effect', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="smooth_scroll" name="smooth_scroll"<?php if ( $this->options['smooth_scroll'] ) echo ' checked="checked"'; ?> /><label for="smooth_scroll"> <?php _e( 'Scroll rather than jump to the anchor link', 'toc+'); ?></label></td>
</tr>
</tbody>
</table>

<h3><?php _e('Appearance', 'toc+'); ?></h3>
<table class="form-table">
<tbody>
<tr>
	<th><label for="width"><?php _e('Width', 'toc+'); ?></label></td>
	<td>
		<select name="width" id="width">
			<optgroup label="<?php _e('Fixed width', 'toc+'); ?>">
				<option value="200px"<?php if ( '200px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('200px'); ?></option>
				<option value="225px"<?php if ( '225px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('225px'); ?></option>
				<option value="250px"<?php if ( '250px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('250px'); ?></option>
				<option value="275px"<?php if ( '275px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('275px (default)'); ?></option>
				<option value="300px"<?php if ( '300px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('300px'); ?></option>
				<option value="325px"<?php if ( '325px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('325px'); ?></option>
				<option value="350px"<?php if ( '350px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('350px'); ?></option>
				<option value="375px"<?php if ( '375px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('375px'); ?></option>
				<option value="400px"<?php if ( '400px' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('400px'); ?></option>
			</optgroup>
			<optgroup label="<?php _e('Relative', 'toc+'); ?>">
				<option value="Auto"<?php if ( 'Auto' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('Auto'); ?></option>
				<option value="25%"<?php if ( '25%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('25%'); ?></option>
				<option value="33%"<?php if ( '33%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('33%'); ?></option>
				<option value="50%"<?php if ( '50%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('50%'); ?></option>
				<option value="66%"<?php if ( '66%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('66%'); ?></option>
				<option value="75%"<?php if ( '75%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('75%'); ?></option>
				<option value="100%"<?php if ( '100%' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('100%'); ?></option>
			</optgroup>
			<optgroup label="<?php _e('Other', 'toc+'); ?>">
				<option value="User defined"<?php if ( 'User defined' == $this->options['width'] ) echo ' selected="selected"'; ?>><?php _e('User defined', 'toc+'); ?></option>
			</optgroup>
		</select>
		<div class="more_toc_options<?php if ( 'User defined' != $this->options['width'] ) echo ' disabled'; ?>">
			<label for="width_custom"><?php _e('Please enter a number and', 'toc+'); ?></label><label for="width_custom_units"> <?php _e('select its units, eg: 100px, 10em', 'toc+'); ?></label><br />
			<input type="text" class="regular-text" value="<?php echo floatval($this->options['width_custom']); ?>" id="width_custom" name="width_custom" />
			<select name="width_custom_units" id="width_custom_units">
				<option value="px"<?php if ( 'px' == $this->options['width_custom_units'] ) echo ' selected="selected"'; ?>>px</option>
				<option value="%"<?php if ( '%' == $this->options['width_custom_units'] ) echo ' selected="selected"'; ?>>%</option>
				<option value="em"<?php if ( 'em' == $this->options['width_custom_units'] ) echo ' selected="selected"'; ?>>em</option>
			</select>
		</div>
	</td>
</tr>
<tr>
	<th><label for="wrapping"><?php _e('Wrapping', 'toc+'); ?></label></td>
	<td>
		<select name="wrapping" id="wrapping">
			<option value="<?php echo TOC_WRAPPING_NONE; ?>"<?php if ( TOC_WRAPPING_NONE == $this->options['wrapping'] ) echo ' selected="selected"'; ?>><?php _e('None (default)', 'toc+'); ?></option>
			<option value="<?php echo TOC_WRAPPING_LEFT; ?>"<?php if ( TOC_WRAPPING_LEFT == $this->options['wrapping'] ) echo ' selected="selected"'; ?>><?php _e('Left', 'toc+'); ?></option>
			<option value="<?php echo TOC_WRAPPING_RIGHT; ?>"<?php if ( TOC_WRAPPING_RIGHT == $this->options['wrapping'] ) echo ' selected="selected"'; ?>><?php _e('Right', 'toc+'); ?></option>
		</select>
	</td>
</tr>
<tr>
	<th><label for="font_size"><?php _e('Font size', 'toc+'); ?></label></th>
	<td>
		<input type="text" class="regular-text" value="<?php echo floatval($this->options['font_size']); ?>" id="font_size" name="font_size" />
		<select name="font_size_units" id="font_size_units">
			<option value="px"<?php if ( 'pt' == $this->options['font_size_units'] ) echo ' selected="selected"'; ?>>pt</option>
			<option value="%"<?php if ( '%' == $this->options['font_size_units'] ) echo ' selected="selected"'; ?>>%</option>
			<option value="em"<?php if ( 'em' == $this->options['font_size_units'] ) echo ' selected="selected"'; ?>>em</option>
		</select>
	</td>
</tr>
<tr>
	<th><?php _e('Presentation', 'toc+'); ?></th>
	<td>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_GREY; ?>" value="<?php echo TOC_THEME_GREY; ?>"<?php if ( $this->options['theme'] == TOC_THEME_GREY ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_GREY; ?>"> <?php _e('Grey (default)', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/grey.png" alt="" />
			</label>
		</div>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_LIGHT_BLUE; ?>" value="<?php echo TOC_THEME_LIGHT_BLUE; ?>"<?php if ( $this->options['theme'] == TOC_THEME_LIGHT_BLUE ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_LIGHT_BLUE; ?>"> <?php _e('Light blue', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/blue.png" alt="" />
			</label>
		</div>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_WHITE; ?>" value="<?php echo TOC_THEME_WHITE; ?>"<?php if ( $this->options['theme'] == TOC_THEME_WHITE ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_WHITE; ?>"> <?php _e('White', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/white.png" alt="" />
			</label>
		</div>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_BLACK; ?>" value="<?php echo TOC_THEME_BLACK; ?>"<?php if ( $this->options['theme'] == TOC_THEME_BLACK ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_BLACK; ?>"> <?php _e('Black', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/black.png" alt="" />
			</label>
		</div>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_TRANSPARENT; ?>" value="<?php echo TOC_THEME_TRANSPARENT; ?>"<?php if ( $this->options['theme'] == TOC_THEME_TRANSPARENT ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_TRANSPARENT; ?>"> <?php _e('Transparent', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/transparent.png" alt="" />
			</label>
		</div>
		<div class="toc_theme_option">
			<input type="radio" name="theme" id="theme_<?php echo TOC_THEME_CUSTOM; ?>" value="<?php echo TOC_THEME_CUSTOM; ?>"<?php if ( $this->options['theme'] == TOC_THEME_CUSTOM ) echo ' checked="checked"'; ?> /><label for="theme_<?php echo TOC_THEME_CUSTOM; ?>"> <?php _e('Custom', 'toc+'); ?><br />
			<img src="<?php echo $this->path; ?>/images/custom.png" alt="" />
			</label>
		</div>
		<div class="clear"></div>
		
		<div class="more_toc_options<?php if ( TOC_THEME_CUSTOM != $this->options['theme'] ) echo ' disabled'; ?>">
			<table id="theme_custom" class="more_toc_options_table">
			<tbody>
			<tr>
				<th><label for="custom_background_colour"><?php _e('Background', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_background_colour'] ); ?>" id="custom_background_colour" name="custom_background_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
			<tr>
				<th><label for="custom_border_colour"><?php _e('Border', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_border_colour'] ); ?>" id="custom_border_colour" name="custom_border_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
			<tr>
				<th><label for="custom_title_colour"><?php _e('Title', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_title_colour'] ); ?>" id="custom_title_colour" name="custom_title_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
			<tr>
				<th><label for="custom_links_colour"><?php _e('Links', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_links_colour'] ); ?>" id="custom_links_colour" name="custom_links_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
			<tr>
				<th><label for="custom_links_hover_colour"><?php _e('Links (hover)', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_links_hover_colour'] ); ?>" id="custom_links_hover_colour" name="custom_links_hover_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
<?php
/* visited links not applied when smooth scrolling enabled, leaving out for now
			<tr>
				<th><label for="custom_links_visited_colour"><?php _e('Links (visited)', 'toc+'); ?></label></th>
				<td><input type="text" class="custom_colour_option" value="<?php echo htmlentities( $this->options['custom_links_visited_colour'] ); ?>" id="custom_links_visited_colour" name="custom_links_visited_colour" /> <img src="<?php echo $this->path; ?>/images/colour-wheel.png" alt="" /></td>
			</tr>
*/
?>
			</tbody>
			</table>
			<div id="farbtastic_colour_wheel"></div>
			<div class="clear"></div>
			<p><?php _e('Leaving the value as', 'toc+'); echo ' <code>#</code> '; _e("will inherit your theme's styles", 'toc+'); ?></p>
		</div>
	</td>
</tr>
<tr>
	<th><label for="bullet_spacing"><?php _e('Preserve theme bullets', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="bullet_spacing" name="bullet_spacing"<?php if ( $this->options['bullet_spacing'] ) echo ' checked="checked"'; ?> /><label for="bullet_spacing"> <?php _e( 'If your theme includes background images for unordered list elements, enable this to support them', 'toc+'); ?></label></td>
</tr>
</tbody>
</table>

<h3><?php _e('Advanced', 'toc+'); ?> <span class="show_hide">(<a href="#toc_advanced_usage">show</a>)</span></h3>
<div id="toc_advanced_usage">
	<h4><?php _e('Power options', 'toc+'); ?></h4>
	<table class="form-table">
	<tbody>
	<tr>
		<th><label for="include_homepage"><?php _e('Include homepage', 'toc+'); ?></label></th>
		<td><input type="checkbox" value="1" id="include_homepage" name="include_homepage"<?php if ( $this->options['include_homepage'] ) echo ' checked="checked"'; ?> /><label for="include_homepage"> <?php _e( 'Show the table of contents for qualifying items on the homepage', 'toc+'); ?></label></td>
	</tr>
	<tr>
		<th><?php _e('Heading levels', 'toc+'); ?></th>
		<td>
		<p><?php _e('Include (or exclude) the following heading levels', 'toc+'); ?></p>
<?php
			// show heading 1 to 6 options
			for ($i = 1; $i <= 6; $i++) {
				echo '<input type="checkbox" value="' . $i . '" id="heading_levels' . $i .'" name="heading_levels[]"';
				if ( in_array($i, $this->options['heading_levels']) ) echo ' checked="checked"';
				echo ' /><label for="heading_levels' . $i .'"> ' . __('heading ') . $i . ' - h' . $i . '</label><br />';
			}
?>
		</td>
	</tr>
	<tr>
		<th><label for="fragment_prefix"><?php _e('Default anchor prefix', 'toc+'); ?></label></th>
		<td>
			<input type="text" class="regular-text" value="<?php echo htmlentities( $this->options['fragment_prefix'] ); ?>" id="fragment_prefix" name="fragment_prefix" /><br />
			<label for="fragment_prefix"><?php _e('Anchor targets are restricted to alphanumeric characters as per HTML specification (see readme for more detail). The default anchor prefix will be used when no characters qualify. When left blank, a number will be used instead.'); ?><br />
			<?php _e('This option normally applies to content written in character sets other than ASCII.', 'toc+'); ?><br />
			<span class="description"><?php _e('Eg: i, toc_index, index, _', 'toc+'); ?></span></label>
		</td>
	</tr>
	</tbody>
	</table>

	<h4><?php _e('Usage', 'toc+'); ?></h4>
	<p>If you would like to fully customise the position of the table of contents, you can use the <code>[toc]</code> shortcode by placing it at the desired position of your post, page or custom post type. This method allows you to generate the table of contents despite having auto insertion disabled for its content type. Please visit the help tab for further information about this shortcode.</p>
</div>


	</div>
	<div id="tab2" class="tab_content">
	

<p><?php _e('At its simplest, placing', 'toc+'); ?> <code>[sitemap]</code> <?php _e('into a page will automatically create a sitemap of all pages and categories. This also works in a text widget.', 'toc+'); ?></p>
<table class="form-table">
<tbody>
<tr>
	<th><label for="sitemap_show_page_listing"><?php _e('Show page listing', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="sitemap_show_page_listing" name="sitemap_show_page_listing"<?php if ( $this->options['sitemap_show_page_listing'] ) echo ' checked="checked"'; ?> /></td>
</tr>
<tr>
	<th><label for="sitemap_show_category_listing"><?php _e('Show category listing', 'toc+'); ?></label></th>
	<td><input type="checkbox" value="1" id="sitemap_show_category_listing" name="sitemap_show_category_listing"<?php if ( $this->options['sitemap_show_category_listing'] ) echo ' checked="checked"'; ?> /></td>
</tr>
<tr>
	<th><label for="sitemap_heading_type"><?php _e('Heading type', 'toc+'); ?></label></th>
	<td><label for="sitemap_heading_type"><?php _e('Use', 'toc+'); ?> h</label><select name="sitemap_heading_type" id="sitemap_heading_type">
<?php
			// h1 to h6
			for ($i = 1; $i <= 6; $i++) {
				echo '<option value="' . $i . '"';
				if ( $i == $this->options['sitemap_heading_type'] ) echo ' selected="selected"';
				echo '>' . $i . '</option>' . "\n";
			}
?>
		</select> <?php _e('to print out the titles', 'toc+'); ?>
	</td>
</tr>
<tr>
	<th><label for="sitemap_pages"><?php _e('Pages label', 'toc+'); ?></label></th>
	<td><input type="text" class="regular-text" value="<?php echo htmlentities( $this->options['sitemap_pages'], ENT_COMPAT, 'UTF-8' ); ?>" id="sitemap_pages" name="sitemap_pages" />
		<span class="description"><?php _e('Eg: Pages, Page List', 'toc+'); ?></span>
	</td>
</tr>
<tr>
	<th><label for="sitemap_categories"><?php _e('Categories label', 'toc+'); ?></label></th>
	<td><input type="text" class="regular-text" value="<?php echo htmlentities( $this->options['sitemap_categories'], ENT_COMPAT, 'UTF-8' ); ?>" id="sitemap_categories" name="sitemap_categories" />
		<span class="description"><?php _e('Eg: Categories, Category List', 'toc+'); ?></span>
	</td>
</tr>
</tbody>
</table>

<h3>Advanced usage <span class="show_hide">(<a href="#sitemap_advanced_usage">show</a>)</span></h3>
<div id="sitemap_advanced_usage">
	<p><code>[sitemap_pages]</code> lets you print out a listing of only pages. Similarly, <code>[sitemap_categories]</code> can be used to print out a category listing. They both can accept a number of attributes so visit the help tab for more information.</p>
	<p>Examples</p>
	<ol>
		<li><code>[sitemap_categories no_label="true"]</code> hides the heading from a category listing</li>
		<li><code>[sitemap_pages heading="6" label="This is an awesome listing" exclude="1,15"]</code> Uses h6 to display <em>This is an awesome listing</em> on a page listing excluding pages with IDs 1 and 15.</li>
	</ol>
</div>


	</div>
	<div id="tab3" class="tab_content">

<h3>Where's my table of contents?</h3>
<p>If you're reading this, then chances are you have successfully installed and enabled the plugin and you're just wondering why the index isn't appearing right?  Try the following:</p>
<ol>
	<li>In most cases, the post, page or custom post type has less than the minimum number of headings. By default, this is set to four so make sure you have at least four headings within your content. If you want to change this value, you can find it under 'Main Options' &gt; 'Show when'.</li>
	<li>Is auto insertion enabled for your content type? By default, only pages are enabled.</li>
	<li>Have you got <code>[no_toc]</code> somewhere within the content? This will disable the index for the current post, page or custom post type.</li>
	<li>If you are using the TOC+ widget, check if you have the <em>"Show the table of contents only in the sidebar"</em> enabled as this will limit its display to only the sidebar. You can check by going into Appearance &gt; Widgets.</li>
</ol>
	
<h3>How do I stop the table of contents from appearing on a single page?</h3>
<p>Place the following <code>[no_toc]</code> anywhere on the page to suppress the table of contents. This is known as a shortcode and works for posts, pages and custom post types that make use of the_content().</p>

<h3>I've set wrapping to left or right but the headings don't wrap around the table of contents</h3>
<p>This normally occurs when there is a CSS clear directive in or around the heading specified by the theme author. This directive tells the user agent to reset the previous wrapping specifications.</p>
<p>You can adjust your theme's CSS or try moving the table of contents position to the top of the page. If you didn't build your theme, I'd highly suggest you try the <a href="http://wordpress.org/extend/plugins/safecss/">Custom CSS plugin</a> if you wish to make CSS changes.</p>

<h3>The sitemap uses a strange font disimilar to the rest of the site</h3>
<p>No extra styles are created for the sitemap, instead it inherits any styles you used when adding the shortcode. If you copy and pasted, you probably also copied the 'code' tags surrounding it so remove them if this is the case.</p>
<p>In most cases, try to have the shortcode on its own line with nothing before or after the square brackets.</p>

<h3>What were those shortcodes and attributes again?</h3>
<p>When attributes are left out for the shortcodes below, they will fallback to the settings you defined under Settings &gt; TOC+.</p>
<table id="shortcode_table">
<thead>
<tr>
	<th>Shortcode</th>
	<th>Description</th>
	<th>Attributes</th>
</tr>
</thead>
<tbody>
<tr>
	<td>[toc]</td>
	<td>Lets you generate the table of contents at the preferred position. Also useful for sites that only require a TOC on a small handful of pages.</td>
	<td>
		<ul>
			<li><strong>label</strong>: text, title of the table of contents</li>
			<li><strong>no_label</strong>: true/false, shows or hides the title</li>
			<li><strong>wrapping</strong>: text, either "left" or "right"</li>
			<li><strong>heading_levels</strong>: numbers, this lets you select the heading levels you want included in the table of contents. Separate multiple levels with a comma. Example: include headings 3, 4 and 5 but exclude the others with <code>heading_levels="3,4,5"</code></li>
		</ul>
	</td>
</tr>
<tr>
	<td>[no_toc]</td>
	<td>Allows you to disable the table of contents for the current post, page, or custom post type.</td>
	<td>&nbsp;</td>
</tr>
<tr>
	<td>[sitemap]</td>
	<td>Produces a listing of all pages and categories for your site. You can use this on any post, page or even in a text widget.</td>
	<td>&nbsp;</td>
</tr>
<tr>
	<td>[sitemap_pages]</td>
	<td>Lets you print out a listing of only pages.</td>
	<td>
		<ul>
			<li><strong>heading</strong>: number between 1 and 6, defines which html heading to use</li>
			<li><strong>label</strong>: text, title of the list</li>
			<li><strong>no_label</strong>: true/false, shows or hides the list heading</li>
			<li><strong>exclude</strong>: IDs of the pages or categories you wish to exclude</li>
		</ul>
	</td>
</tr>
<tr>
	<td>[sitemap_categories]</td>
	<td>Similar to [sitemap_pages] but for categories.</td>
	<td>&nbsp;</td>
</tr>
</tbody>
</table>

<h3>I have another question...</h3>
<p>Visit the <a href="http://dublue.com/plugins/toc/">plugin homepage</a> to ask your question - who knows, maybe your question has already been answered. I'd really like to hear your suggestions if you have any.</p>

	</div>
</div>
	

<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e('Update Options', 'toc+'); ?>" /></p>
</form>
</div>
<?php
		}
		
		
		function public_styles()
		{
			wp_enqueue_style("toc-screen");
		}
		
							
		function wp_head()
		{
			if ( 
				$this->options['theme'] == TOC_THEME_CUSTOM || 
				$this->options['width'] != '275px' || 
				( '95%' != $this->options['font_size'] . $this->options['font_size_units'] )
			) :
?>
<style type="text/css">
div#toc_container {
<?php if ( $this->options['theme'] == TOC_THEME_CUSTOM ) : ?>
	background: <?php echo $this->options['custom_background_colour']; ?>;
	border: 1px solid <?php echo $this->options['custom_border_colour']; ?>;
<?php
	endif;
	
	if ( $this->options['width'] != '275px' ) {
		echo '	width: ';
		if ( $this->options['width'] != 'User defined' )
			echo $this->options['width'];
		else
			echo $this->options['width_custom'] . $this->options['width_custom_units'];
		echo ";\n";
		if ( $this->options['width'] == 'Auto' )
			echo '	display: table;' . "\n";
	}
	
	if ( '95%' != $this->options['font_size'] . $this->options['font_size_units'] ) {
		echo '	font-size: ' . $this->options['font_size'] . $this->options['font_size_units'] . ";\n";
	}
?>
}
<?php if ( $this->options['custom_title_colour'] != TOC_DEFAULT_TITLE_COLOUR ) : ?>
div#toc_container p.toc_title {
	color: <?php echo $this->options['custom_title_colour']; ?>;
}
<?php
	endif;
				
	if ( $this->options['custom_links_colour'] != TOC_DEFAULT_LINKS_COLOUR ) : ?>
div#toc_container p.toc_title a,
div#toc_container ul.toc_list a {
	color: <?php echo $this->options['custom_links_colour']; ?>;
}
<?php
	endif;
	
	if ( $this->options['custom_links_hover_colour'] != TOC_DEFAULT_LINKS_HOVER_COLOUR ) : ?>
div#toc_container p.toc_title a:hover,
div#toc_container ul.toc_list a:hover {
	color: <?php echo $this->options['custom_links_hover_colour']; ?>;
}
<?php
	endif;
	
	if ( $this->options['custom_links_visited_colour'] != TOC_DEFAULT_LINKS_VISITED_COLOUR ) : ?>
div#toc_container p.toc_title a:visited,
div#toc_container ul.toc_list a:visited {
	color: <?php echo $this->options['custom_links_visited_colour']; ?>;
}
<?php endif; ?>
</style>
<?php
			endif;
			
		}
		
		
		/**
		 * Load front end javascript only on front end pages.  Putting it into 'init' will
		 * load it on both frontend and backend pages.
		 */
		function template_redirect()
		{
			if ( $this->options['smooth_scroll'] ) wp_enqueue_script( 'smooth-scroll' );
			wp_enqueue_script( 'toc-front' );
			if ( $this->options['show_heading_text'] && $this->options['visibility'] ) {
				$width = ( $this->options['width'] != 'User defined' ) ? $this->options['width'] : $this->options['width_custom'] . $this->options['width_custom_units'];
				wp_enqueue_script( 'cookie' );
				wp_localize_script(
					'toc-front',
					'tocplus',
					array(
						'visibility_show' => esc_js($this->options['visibility_show']),
						'visibility_hide' => esc_js($this->options['visibility_hide']),
						'width' => esc_js($width)
					)
				);
			}
		}
		
		
		/**
		 * Returns a clean url to be used as the destination anchor target
		 */
		private function url_anchor_target( $title )
		{
			$return = false;
			
			if ( $title ) {
				$return = trim( strip_tags($title) );
				
				// remove &amp;
				$return = str_replace( '&amp;', '', $return );
				
				// remove non alphanumeric chars
				$return = preg_replace( '/[^a-zA-Z0-9 \-_]*/', '', $return );
				
				// convert spaces to _
				$return = str_replace(
					array('  ', ' '),
					'_',
					$return
				);
				
				// remove trailing - and _
				$return = rtrim( $return, '-_' );

				// if blank, then prepend with the fragment prefix
				// blank anchors normally appear on sites that don't use the latin charset
				if ( !$return ) {
					$return = ( $this->options['fragment_prefix'] ) ? $this->options['fragment_prefix'] : '_';
				}
			}
			
			if ( array_key_exists($return, $this->collision_collector) ) {
				$this->collision_collector[$return]++;
				$return .= '-' . $this->collision_collector[$return];
			}
			else
				$this->collision_collector[$return] = 1;
			
			return $return;
		}
		
		
		private function build_hierarchy( &$matches )
		{
			$current_depth = 100;	// headings can't be larger than h6 but 100 as a default to be sure
			$html = '';
			$numbered_items = array();
			$numbered_items_min = null;
			
			// reset the internal collision collection
			$this->collision_collector = array();
			
			// find the minimum heading to establish our baseline
			for ($i = 0; $i < count($matches); $i++) {
				if ( $current_depth > $matches[$i][2] )
					$current_depth = (int)$matches[$i][2];
			}
			
			$numbered_items[$current_depth] = 0;
			$numbered_items_min = $current_depth;

			for ($i = 0; $i < count($matches); $i++) {

				if ( $current_depth == (int)$matches[$i][2] )
					$html .= '<li>';
			
				// start lists
				if ( $current_depth != (int)$matches[$i][2] ) {
					for ($current_depth; $current_depth < (int)$matches[$i][2]; $current_depth++) {
						$numbered_items[$current_depth + 1] = 0;
						$html .= '<ul><li>';
					}
				}
				
				// list item
				if ( in_array($matches[$i][2], $this->options['heading_levels']) ) {
					$html .= '<a href="#' . $this->url_anchor_target( $matches[$i][0] ) . '">';
					if ( $this->options['ordered_list'] ) {
						// attach leading numbers when lower in hierarchy
						for ($j = $numbered_items_min; $j < $current_depth; $j++) {
							$number = ($numbered_items[$j]) ? $numbered_items[$j] : 0;
							$html .= $number . '.';
						}
						
						$html .= ($numbered_items[$current_depth] + 1) . ' ';
						$numbered_items[$current_depth]++;
					}
					$html .= strip_tags($matches[$i][0]) . '</a>';
				}
				
				
				// end lists
				if ( $i != count($matches) - 1 ) {
					if ( $current_depth > (int)$matches[$i + 1][2] ) {
						for ($current_depth; $current_depth > (int)$matches[$i + 1][2]; $current_depth--) {
							$html .= '</li></ul>';
							$numbered_items[$current_depth] = 0;
						}
					}
					
					if ( $current_depth == (int)@$matches[$i + 1][2] )
						$html .= '</li>';
				}
				else {
					// this is the last item, make sure we close off all tags
					for ($current_depth; $current_depth >= $numbered_items_min; $current_depth--) {
						$html .= '</li>';
						if ( $current_depth != $numbered_items_min ) $html .= '</ul>';
					}
				}
			}

			return $html;
		}
		
		
		/**
		 * Returns a string with all items from the $find array replaced with their matching
		 * items in the $replace array.  This does a one to one replacement (rather than
		 * globally).
		 *
		 * This function is multibyte safe.
		 *
		 * $find and $replace are arrays, $string is the haystack.  All variables are
		 * passed by reference.
		 */
		private function mb_find_replace( &$find = false, &$replace = false, &$string = '' )
		{
			if ( is_array($find) && is_array($replace) && $string ) {
				// check if multibyte strings are supported
				if ( function_exists( 'mb_substr' ) ) {
					for ($i = 0; $i < count($find); $i++) {
						$string = 
							mb_substr( $string, 0, mb_strpos($string, $find[$i]) ) .	// everything befor $find
							$replace[$i] .												// its replacement
							mb_substr( $string, mb_strpos($string, $find[$i]) + mb_strlen($find[$i]) )	// everything after $find
						;
					}
				}
				else {
					for ($i = 0; $i < count($find); $i++) {
						$string = substr_replace(
							$string,
							$replace[$i],
							strpos($string, $find[$i]),
							strlen($find[$i])
						);
					}
				}
			}
			
			return $string;
		}
		
		
		/**
		 * This function extracts headings from the html formatted $content.  It will pull out
		 * only the require headings as specified in the options.  For all qualifying headings,
		 * this function populates the $find and $replace arrays (both passed by reference)
		 * with what to search and replace with.
		 * 
		 * Returns a html formatted string of list items for each qualifying heading.  This 
		 * is everything between and not including <ul> and </ul>
		 */
		public function extract_headings( &$find, &$replace, $content = '' )
		{
			$matches = array();
			$anchor = '';
			$items = false;
			
			if ( is_array($find) && is_array($replace) && $content ) {
				// get all headings
				// the html spec allows for a maximum of 6 heading depths
				if ( preg_match_all('/(<h([1-6]{1})[^>]*>).*<\/h\2>/', $content, $matches, PREG_SET_ORDER) >= $this->options['start'] ) {

					// remove disqualified headings (if any) as defined by heading_levels
					if ( count($this->options['heading_levels']) != 6 ) {
						$new_matches = array();
						for ($i = 0; $i < count($matches); $i++) {
							if ( in_array($matches[$i][2], $this->options['heading_levels']) )
								$new_matches[] = $matches[$i];
						}
						$matches = $new_matches;
					}
				
					for ($i = 0; $i < count($matches); $i++) {
						// get anchor and add to find and replace arrays
						$anchor = $this->url_anchor_target( $matches[$i][0] );
						$find[] = $matches[$i][0];
						$replace[] = str_replace(
							array(
								$matches[$i][1],				// start of heading
								'</h' . $matches[$i][2] . '>'	// end of heading
							),
							array(
								$matches[$i][1] . '<span id="' . $anchor . '">',
								'</span></h' . $matches[$i][2] . '>'
							),
							$matches[$i][0]
						);

						// assemble flat list
						if ( !$this->options['show_heirarchy'] ) {
							$items .= '<li><a href="#' . $anchor . '">';
							if ( $this->options['ordered_list'] ) $items .= count($replace) . ' ';
							$items .= strip_tags($matches[$i][0]) . '</a></li>';
						}
					}

					// build a hierarchical toc?
					// we could have tested for $items but that var can be quite large in some cases
					if ( $this->options['show_heirarchy'] ) $items = $this->build_hierarchy( &$matches );
				}
			}
			
			return $items;
		}
		
		
		/**
		 * Returns true if the table of contents is eligible to be printed, false otherwise.
		 */
		public function is_eligible( $shortcode_used = false )
		{
			global $post;

			// if the shortcode was used, this bypasses many of the global options
			if ( $shortcode_used !== false ) {
				// shortcode is used, make sure it adheres to the exclude from 
				// homepage option if we're on the homepage
				if ( !$this->options['include_homepage'] && is_front_page() )
					return false;
				else
					return true;
			}
			else {
				if (
					( in_array(get_post_type($post), $this->options['auto_insert_post_types']) && $this->show_toc && !is_search() && !is_archive() && !is_front_page() ) || 
					( $this->options['include_homepage'] && is_front_page() )
				)
					return true;
				else
					return false;
			}
		}
		
		
		function the_content( $content )
		{
			global $post;
			$items = $css_classes = $anchor = '';
			$custom_toc_position = strpos($content, '<!--TOC-->');
			$find = $replace = array();
			
			// reset the internal collision collection as the_content may have been triggered elsewhere
			// eg by themes or other plugins that need to read in content such as metadata fields in
			// the head html tag, or to provide descriptions to twitter/facebook
			$this->collision_collector = array();

			if ( $this->is_eligible($custom_toc_position) ) {
			
				$items = $this->extract_headings( &$find, &$replace, $content );

				if ( $items ) {
					// do we display the toc within the content or has the user opted
					// to only show it in the widget?  if so, then we still need to 
					// make the find/replace call to insert the anchors
					if ( $this->options['show_toc_in_widget_only'] ) {
						$content = $this->mb_find_replace($find, $replace, $content);
					}
					else {

						// wrapping css classes
						switch( $this->options['wrapping'] ) {
							case TOC_WRAPPING_LEFT:
								$css_classes .= ' toc_wrap_left';
								break;
								
							case TOC_WRAPPING_RIGHT:
								$css_classes .= ' toc_wrap_right';
								break;

							case TOC_WRAPPING_NONE:
							default:
								// do nothing
						}
						
						// colour themes
						switch ( $this->options['theme'] ) {
							case TOC_THEME_LIGHT_BLUE:
								$css_classes .= ' toc_light_blue';
								break;
							
							case TOC_THEME_WHITE:
								$css_classes .= ' toc_white';
								break;
								
							case TOC_THEME_BLACK:
								$css_classes .= ' toc_black';
								break;
							
							case TOC_THEME_TRANSPARENT:
								$css_classes .= ' toc_transparent';
								break;
						
							case TOC_THEME_GREY:
							default:
								// do nothing
						}
						
						// bullets?
						if ( $this->options['bullet_spacing'] )
							$css_classes .= ' have_bullets';
						else
							$css_classes .= ' no_bullets';
						
						$css_classes = trim($css_classes);
						
						// an empty class="" is invalid markup!
						if ( !$css_classes ) $css_classes = ' ';
						
						// add container, toc title and list items
						$html = '<div id="toc_container" class="' . $css_classes . '">';
						if ( $this->options['show_heading_text'] ) $html .= '<p class="toc_title">' . htmlentities( $this->options['heading_text'], ENT_COMPAT, 'UTF-8' ) . '</p>';
						$html .= '<ul class="toc_list">' . $items . '</ul></div>' . "\n";
						
						if ( $custom_toc_position !== false ) {
							$find[] = '<!--TOC-->';
							$replace[] = $html;
							$content = $this->mb_find_replace($find, $replace, $content);
						}
						else {	
							if ( count($find) > 0 ) {
								switch ( $this->options['position'] ) {
									case TOC_POSITION_TOP:
										$content = $html . $this->mb_find_replace($find, $replace, $content);
										break;
									
									case TOC_POSITION_BOTTOM:
										$content = $this->mb_find_replace($find, $replace, $content) . $html;
										break;
								
									case TOC_POSITION_BEFORE_FIRST_HEADING:
									default:
										$replace[0] = $html . $replace[0];
										$content = $this->mb_find_replace($find, $replace, $content);
								}
							}
						}
					}
				}
			}
		
			return $content;
		}
		
	} // end class
endif;



if ( !class_exists( 'toc_widget' ) ) :
	class toc_widget extends WP_Widget {

		function __construct()
		{
			$widget_options = array( 
				'classname' => 'toc_widget', 
				'description' => 'Display the table of contents in the sidebar with this widget' 
			);
			$control_options = array( 
				'width' => 250, 
				'height' => 350, 
				'id_base' => 'toc-widget'
			);
			$this->WP_Widget( 'toc-widget', 'TOC+', $widget_options, $control_options );
		}
		

		/**
		 * Widget output to the public
		 */
		function widget( $args, $instance ) 
		{
			global $tic, $wp_query;
			$items = $custom_toc_position = '';
			$find = $replace = array();
			
			$toc_options = $tic->get_options();
			$post = get_post( $wp_query->post->ID );
			$custom_toc_position = strpos( $post->post_content, '[toc]' );	// at this point, shortcodes haven't run yet so we can't search for <!--TOC-->
			
			if ( $tic->is_eligible($custom_toc_position) ) {
				
				extract( $args );
				
				$items = $tic->extract_headings( &$find, &$replace, $post->post_content );
				$title = apply_filters('widget_title', $instance['title'] );
				$hide_inline = $toc_options['show_toc_in_widget_only'];
				
				if ( $items ) {
					// before widget (defined by themes)
					echo $before_widget;

					// display the widget title if one was input (before and after defined by themes)
					if ( !$title ) $title = $toc_options['heading_text'];
					echo 
						$before_title . $title . $after_title .
						'<ul class="toc_widget_list">' . $items . '</ul>'
					;
					
					// after widget (defined by themes)
					echo $after_widget;
				}
			}
		}
		

		/**
		 * Update the widget settings
		 */
		function update( $new_instance, $old_instance ) 
		{
			global $tic;
			
			$instance = $old_instance;

			// strip tags for title to remove HTML (important for text inputs)
			$instance['title'] = strip_tags( $new_instance['title'] );
			
			// no need to strip tags for the following
			//$instance['hide_inline'] = $new_instance['hide_inline'];
			$tic->set_show_toc_in_widget_only( $new_instance['hide_inline'] );

			return $instance;
		}
		

		/**
		 * Displays the widget settings on the widget panel.
		 */
		function form( $instance )
		{
			global $tic;
			$toc_options = $tic->get_options();
		
			$defaults = array( 
				'title' => ''
			);
			$instance = wp_parse_args( (array)$instance, $defaults );

?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
				<input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
			</p>

			<p>
				<input class="checkbox" type="checkbox" <?php checked( $toc_options['show_toc_in_widget_only'], 1 ); ?> id="<?php echo $this->get_field_id( 'hide_inline' ); ?>" name="<?php echo $this->get_field_name( 'hide_inline' ); ?>" value="1" /> 
				<label for="<?php echo $this->get_field_id( 'hide_inline' ); ?>"> <?php _e('Show the table of contents only in the sidebar'); ?></label>
			</p>
<?php
		}
		
	} // end class
endif;



// do the magic
$tic = new toc();

?>