<?php

namespace InDesign2Post;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class InDesign2Post_SettingsPage {

	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	public function getOptions() {
		$this->options = get_option( 'indesign2post_option' );
		return $this->options;
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page( 
			'Settings Admin',
			'InDesign 2 Post',
			'manage_options',
			'idesign2post-setting-admin',
			array( $this, 'create_admin_page' ) );
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		// Set class property
		$this->options = get_option( 'indesign2post_option' );
		?>
<div class="wrap">
	<h1>InDesign 2 Post Settings</h1>
	<form method="post" action="options.php">
            <?php
		// This prints out all hidden setting fields
		settings_fields( 'indesign2post_option_group' );
		do_settings_sections( 'idesign2post-setting-admin' );
		submit_button();
		?>
            </form>
</div>
<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting( 'indesign2post_option_group', // Option group
		'indesign2post_option', // Option name
		array( $this, 'sanitize' ) ); // Sanitize
		
		add_settings_section( 'indesign2post_setting_section_id', // ID
		'Custom post types', // Title
		array( $this, 'print_section_info' ), // Callback
		'idesign2post-setting-admin' ); // Page
		/*
		 * add_settings_field('id_number', // ID
		 * 'ID Number', // Title
		 * array(
		 * $this,
		 * 'id_number_callback'
		 * ), // Callback
		 * 'idesign2post-setting-admin', // Page
		 * 'indesign2post_setting_section_id'); // Section
		 */
		
		add_settings_field( 
			'cpt_range',
			'Restrict CPT to show to post creator',
			array( $this, 'indesign2post_cpt_setting' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'title_tag',
			'Title tag, name of tag (for ex. h1) to search in content, tag value wil be used as post title',
			array( $this, 'title_tag_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'tags_to_replace',
			'Coma separated list of HTML tags to replace by a space character',
			array( $this, 'setting_chk1_fn' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'tags_to_keep',
			'HTML Tags to keep ($allowable_tags parameter of <a target="_blank" href="https://www.php.net/strip-tags">strip_tags</a>)',
			array( $this, 'html_tags_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'tag_class_to_acf',
			'HTML Tags to map to ACF fields. Should be json format like : <pre>{"tag_class":"acf_field_id","tag_class_2":"acf_field_id2",...}</pre>',
			array( $this, 'html_tags_to_acf_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'images_block_tag',
			'Class name of tag containing one (or more) a caption and one (or more) images. The plugin will detect those blocs to try to associate images and captions',
			array( $this, 'images_block_tag_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'legend_class_tag',
			'Class name of tag containing captions of images. The plugin will detect the figure number inside these tags',
			array( $this, 'legend_tag_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'figure_call_class_tag',
			'Class name of tag containing figure call inside the body of the text. The plugin will detect the figure number inside these tags and try to associate it with correct images insertions',
			array( $this, 'figure_call_tag_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'featured_img_class_tag',
			'Class name added to img tag containing featured image.',
			array( $this, 'featured_img_class_tag_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'resize_images',
			'Automatic image resizing.',
			array( $this, 'resize_images_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'resize_images_width',
			'Resize width (px) to',
			array( $this, 'resize_images_width_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'resize_images_height',
			'Resize height (px) to',
			array( $this, 'resize_images_height_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'resize_mode',
			'Choose resize mode',
			array( $this, 'indesign2post_resize_setting' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'use_imagick',
			'Use ImageMagick if present',
			array( $this, 'use_imagick_setting' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
		
		add_settings_field( 
			'enable_swipe',
			'Enable swipe on image galleries',
			array( $this, 'enable_swipe_callback' ),
			'idesign2post-setting-admin',
			'indesign2post_setting_section_id' );
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input
	 *            Contains all settings fields as array keys
	 */
	public function sanitize( $input ) {
		$new_input = array();
		if ( isset( $input['id_number'] ) )
			$new_input['id_number'] = absint( $input['id_number'] );
		
		if ( isset( $input['title_tag'] ) )
			$new_input['title_tag'] = wp_kses_post( $input['title_tag'] );
		
		if ( isset( $input['cpt_range'] ) )
			$new_input['cpt_range'] = $input['cpt_range'];
		
		if ( isset( $input['tags_to_replace'] ) )
			$new_input['tags_to_replace'] = wp_kses_post( $input['tags_to_replace'] );
		if ( isset( $input['tags_to_keep'] ) )
			$new_input['tags_to_keep'] = wp_kses_post( $input['tags_to_keep'] );
		
		if ( isset( $input['tag_class_to_acf'] ) )
			$new_input['tag_class_to_acf'] = wp_kses_post( $input['tag_class_to_acf'] );
		
		if ( isset( $input['images_block_tag'] ) )
			$new_input['images_block_tag'] = wp_kses_post( $input['images_block_tag'] );
		
		if ( isset( $input['legend_class_tag'] ) )
			$new_input['legend_class_tag'] = wp_kses_post( $input['legend_class_tag'] );
		
		if ( isset( $input['figure_call_class_tag'] ) )
			$new_input['figure_call_class_tag'] = wp_kses_post( $input['figure_call_class_tag'] );
		
		if ( isset( $input['featured_img_class_tag'] ) )
			$new_input['featured_img_class_tag'] = wp_kses_post( $input['featured_img_class_tag'] );
		
		if ( isset( $input['resize_images'] ) )
			$new_input['resize_images'] = wp_kses_post( $input['resize_images'] );
		
		if ( isset( $input['resize_images_width'] ) )
			$new_input['resize_images_width'] = wp_kses_post( $input['resize_images_width'] );
		
		if ( isset( $input['resize_images_height'] ) )
			$new_input['resize_images_height'] = wp_kses_post( $input['resize_images_height'] );
		
		if ( isset( $input['resize_mode'] ) )
			$new_input['resize_mode'] = $input['resize_mode'];
		
		if ( isset( $input['use_imagick'] ) )
			$new_input['use_imagick'] = $input['use_imagick'];
		
		if ( isset( $input['enable_swipe'] ) )
			$new_input['enable_swipe'] = $input['enable_swipe'];
		
		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		print 
			__( 
				'Input .zip file should be an HTML export from InDesign software, containing both .html file and a folder with all images.' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function id_number_callback() {
		printf( 
			'<input type="text" id="id_number" name="indesign2post_option[id_number]" value="%s" />',
			isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number'] ) : '' );
	}

	public function title_tag_callback() {
		$defaultTag = 'h1';
		printf( 
			'<input class="widefat" type="text" id="title_tag" name="indesign2post_option[title_tag]" value="%s" />',
			isset( $this->options['title_tag'] ) ? esc_attr( $this->options['title_tag'] ) : $defaultTag );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function html_tags_callback() {
		$defaultTagsToKeep = '<h2><h3><h4><h5><ul><ol><li>';
		printf( 
			'<input class="widefat" type="text" id="tags_to_keep" name="indesign2post_option[tags_to_keep]" value="%s" />',
			isset( $this->options['tags_to_keep'] ) ? esc_attr( $this->options['tags_to_keep'] ) : $defaultTagsToKeep );
	}

	public function html_tags_to_acf_callback() {
		$defaultTagsToKeep = '';
		printf( 
			'<input class="widefat" type="text" id="tag_class_to_acf" name="indesign2post_option[tag_class_to_acf]" value="%s" />',
			isset( $this->options['tag_class_to_acf'] ) ? esc_attr( $this->options['tag_class_to_acf'] ) : $defaultTagsToKeep );
	}

	public function images_block_tag_callback() {
		$defaultTagsToKeep = 'blocimage';
		printf( 
			'<input class="widefat" type="text" id="images_block_tag" name="indesign2post_option[images_block_tag]" value="%s" />',
			isset( $this->options['images_block_tag'] ) ? esc_attr( $this->options['images_block_tag'] ) : $defaultTagsToKeep );
	}

	public function legend_tag_callback() {
		$defaultTagsToKeep = 'legende';
		printf( 
			'<input class="widefat" type="text" id="legend_class_tag" name="indesign2post_option[legend_class_tag]" value="%s" />',
			isset( $this->options['legend_class_tag'] ) ? esc_attr( $this->options['legend_class_tag'] ) : $defaultTagsToKeep );
	}

	public function figure_call_tag_callback() {
		$defaultTagsToKeep = 'appelfig';
		printf( 
			'<input class="widefat" type="text" id="figure_call_class_tag" name="indesign2post_option[figure_call_class_tag]" value="%s" />',
			isset( $this->options['figure_call_class_tag'] ) ? esc_attr( $this->options['figure_call_class_tag'] ) : $defaultTagsToKeep );
	}

	public function featured_img_class_tag_callback() {
		$defaultTagsToKeep = 'imageune';
		printf( 
			'<input class="widefat" type="text" id="featured_img_class_tag" name="indesign2post_option[featured_img_class_tag]" value="%s" />',
			isset( $this->options['featured_img_class_tag'] ) ? esc_attr( $this->options['featured_img_class_tag'] ) : $defaultTagsToKeep );
	}

	public function resize_images_height_callback() {
		$defaultValue = '';
		$currentValue = isset( $this->options['resize_images_height'] ) ? esc_attr( 
			$this->options['resize_images_height'] ) : $defaultValue;
		// $checked = checked( 1, $currentValue, false );
		$html = '<input class="widefat" type="text" id="resize_images_height" name="indesign2post_option[resize_images_height]" value="%s" />';
		printf( $html, $currentValue );
	}

	public function resize_images_width_callback() {
		$defaultValue = '1024';
		$currentValue = isset( $this->options['resize_images_width'] ) ? esc_attr( 
			$this->options['resize_images_width'] ) : $defaultValue;
		// $checked = checked( 1, $currentValue, false );
		$html = '<input class="widefat" type="text" id="resize_images_width" name="indesign2post_option[resize_images_width]" value="%s" />';
		printf( $html, $currentValue );
	}

	public function resize_images_callback() {
		$defaultValue = '';
		$currentValue = isset( $this->options['resize_images'] ) ? esc_attr( $this->options['resize_images'] ) : $defaultValue;
		$checked = checked( 1, $currentValue, false );
		$html = '<input type="checkbox" id="resize_images" name="indesign2post_option[resize_images]" value="1" %s/>';
		printf( $html, $checked );
	}

	public function use_imagick_setting() {
		$defaultValue = '';
		$currentValue = isset( $this->options['use_imagick'] ) ? esc_attr( $this->options['use_imagick'] ) : $defaultValue;
		$checked = checked( 1, $currentValue, false );
		$html = '<input type="checkbox" id="use_imagick" name="indesign2post_option[use_imagick]" value="1" %s/>';
		printf( $html, $checked );
	}
	
	public function enable_swipe_callback() {
		$defaultValue = '';
		$currentValue = isset( $this->options['enable_swipe'] ) ? esc_attr( $this->options['enable_swipe'] ) : $defaultValue;
		$checked = checked( 1, $currentValue, false );
		$html = '<input type="checkbox" id="enable_swipe" name="indesign2post_option[enable_swipe]" value="1" %s/>';
		printf( $html, $checked );
	}
	
	// CHECKBOX - Name: plugin_options[tags_to_replace]
	function setting_chk1_fn() {
		/*
		 * $options = $this->options; // get_option('plugin_options');
		 * if ($options['tags_to_replace']) {
		 * $checked = ' checked="checked" ';
		 * }
		 */
		$defaultTagsToReplace = '<br/>,<br>';
		printf( 
			'<input class="widefat" id="tags_to_replace" name="indesign2post_option[tags_to_replace]" type="text" value="%s"/>',
			isset( $this->options['tags_to_replace'] ) ? esc_attr( $this->options['tags_to_replace'] ) : $defaultTagsToReplace );
	}

	function indesign2post_cpt_setting() {
		$post_types = get_post_types( $args, $output );
		
		InDesignHTML2Post_log( $this->options );
		
		$selectItem = '<select name="indesign2post_option[cpt_range][]" multiple="multiple" class="widefat" size="5" style="margin-bottom:10px">';
		foreach ( $post_types as $post_type ) {
			$selectedState = '';
			$id = $post_type->name;
			$optionLabel = $post_type->label;
			if ( isset( $this->options['cpt_range'] ) && is_array( $this->options['cpt_range'] ) ) {
				$needSelection = in_array( $id, $this->options['cpt_range'] );
				$selectedState = $needSelection ? 'selected="selected"' : '';
			}
			
			$new_option = sprintf( 
				'<option value="%s" %s style="margin-bottom:3px;">%s</option>',
				$id,
				$selectedState,
				$optionLabel );
			$selectItem .= $new_option;
		}
		
		$selectItem .= "</select>";
		
		InDesignHTML2Post_log( $selectItem );
		echo $selectItem;
	}

	function indesign2post_resize_setting() {
		$possible_values = array( 
			'default' => 'default',
			'exact' => 'exact',
			'maxWidth' => 'maxWidth',
			'maxHeight' => 'maxHeight' );
		
		if ( $this->options ) {
			$curent_resize_mode = isset( $this->options['resize_mode'] ) ? $this->options['resize_mode'] : 'default';
		}
		$selectItem = '<select name="indesign2post_option[resize_mode]" class="widefat" style="margin-bottom:10px">';
		foreach ( $possible_values as $key => $label ) {
			$selectedState = '';
			InDesignHTML2Post_log( "$curent_resize_mode == $key" );
			if ( $curent_resize_mode == $key ) {				
				$selectedState = 'selected="selected"';
			}
			
			$new_option = sprintf( 
				'<option value="%s" %s style="margin-bottom:3px;">%s</option>',
				$key,
				$selectedState,
				$label );
			$selectItem .= $new_option;
		}
		$selectItem .= "</select>";
		InDesignHTML2Post_log( $selectItem );
		echo $selectItem;
	}
}