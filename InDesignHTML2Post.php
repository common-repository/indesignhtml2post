<?php
/**
 * Plugin Name: InDesignHTML2Post
 * Plugin URI: https://fr.wordpress.org/plugins/indesignhtml2post/
 * Description: Convert HTML InDesign exports to posts (imports all text and images - and attach images automatically to newly created posts).
 * Version: 1.6.5
 * Author: termel
 * Author URI: https://www.indesign2wordpress.com/
 */

// to port to windows : use https://codex.wordpress.org/Function_Reference/wp_normalize_path
if (! defined('ABSPATH')) {
    die();
}

ob_get_contents();
ob_end_clean();

require_once (sprintf("%s/SettingsPage.php", dirname(__FILE__)));
require_once (sprintf("%s/ResizeImage.php", dirname(__FILE__)));

use InDesign2Post\InDesign2Post_SettingsPage;
use InDesign2Post\ResizeImage;

function InDesignHTML2Post_log($message)
{
    // add xmpr("\r\n##################################################"); for dev site
    if (/*WP_DEBUG === */true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

if (! class_exists('InDesignHTML2Post')) {
    
    class InDesignHTML2Post
    {
        
        protected $indesign2post_settings = null;
        
        private $uploadDirectoryName = 'indd2post';
        
        private $imagickEnabled = false;
        
        function __construct()
        {
            add_action('admin_menu', array(
                $this,
                'InDesignHTML2Post_setup_menu'
            ));
            // add_action('init', array($this,'indd2post_process_post_creation'));
            // if ( is_admin() ) {
            $this->indesign2post_settings = new InDesign2Post_SettingsPage();
            // } else {
            if (! is_admin()) {
                add_shortcode('indd2post_demo', array(
                    $this,
                    'indd2post_front_end_create_post_form'
                ));
            }
            
            $override_gallery_shortcode = $this->getSettingFromOptions('enable_swipe');
            if ($override_gallery_shortcode) {
                // Init hook
                add_action('init', array(
                    $this,
                    'override_gallery'
                ));
                add_action('wp_enqueue_scripts', array(
                    $this,
                    'indd2post_enqueue_scripts'
                ));
            }
        }
        
        function indd2post_enqueue_scripts()
        {
            wp_enqueue_style('indd2post-swipe-js-css', plugins_url('/libs/node_modules/swiper/css/swiper.min.css', __FILE__));
            wp_enqueue_style('indd2post-swipe-css', plugins_url('/css/swiper.css', __FILE__));
            wp_enqueue_script('indd2post-swipe-js', plugins_url('/js/swiper.js', __FILE__));
            wp_enqueue_script('indd2post-swipe-js-js', plugins_url('/libs/node_modules/swiper/js/swiper.min.js', __FILE__));
        }
        
        // Override function
        function override_gallery()
        {
            remove_shortcode('gallery');
            add_shortcode('gallery', array(
                $this,
                'indd2post_gallery_shortcode'
            ));
        }
        
        function indd2post_gallery_shortcode($attrs)
        {
            foreach (explode(',', $attrs['ids']) as $id) {
                $attachments[] = get_post($id);
            }
            
            $content = '<div class="gallery-container" itemscope itemtype="http://schema.org/ImageGallery"><div class="gallery-slider swiper-container"><div class="swiper-wrapper"> ';
            foreach ($attachments as $attachment) {
                $attachmentSrc = wp_get_attachment_image_src($attachment->ID, 'full');
                $content .= '<div class="swiper-slide"><figure itemprop="associatedMedia" itemscope itemtype="http://schema.org/ImageObject"><a class="zoom" itemprop="contentUrl" title="' . esc_attr(get_post_field('post_excerpt', $attachment->ID)) . '" href="' .
                    $attachmentSrc[0] . '">' . wp_get_attachment_image($attachment->ID, 'medium') . '</a><figcaption class="slide__body" itemprop="description">' . nl2br(get_post_field('post_excerpt', $attachment->ID)) . '</figcaption></figure></div>';
            }
            $content .= '</div><!-- .swiper-wrapper -->';
            if (count($attachments) > 1) {
                $content .= '<div class="swiper-button-next swiper-button-black"></div><!-- .swiper-button-prev -->';
                $content .= '<div class="swiper-button-prev swiper-button-black"></div><!-- .swiper-button-next -->';
                $content .= '<div class="swiper-pagination swiper-pagination-black"></div><!-- .swiper-pagination -->';
            }
            $content .= '</div><!-- .swiper-container -->';
            $content .= '</div><!-- .gallery-container -->';
            
            return $content;
        }
        
        function InDesignHTML2Post_setup_menu()
        {
            $menuTitle = __('New Post from InDesign HTML export', 'indesign2post');
            
            add_submenu_page('edit.php', $menuTitle, $menuTitle, 'delete_others_posts', 'new-post-from-id-html', array(
                $this,
                'InDesignHTML2Post_upload_page'
            ));
        }
        
        function indd2post_front_end_create_post_form()
        {
            /*
             * if ($this->indesign2post_settings == null){
             * $this->indesign2post_settings = new InDesign2Post_SettingsPage();
             * }
             */
            ob_start();
            $style = "background: #f5f5f5; border-radius: 4px; padding: 1em; border: 1px solid #a3a3a3;";
            echo '<div style="' . $style . 'font-size: 0.8rem;">';
            echo '<h3>Document processing results</h3>';
            $post_max_size = ini_get('post_max_size');
            $this->handle_indesign_html_export($post_max_size);
            echo '</div>';
            ?>
<h2>Upload a .zip file (max size:<?php echo $post_max_size; ?>)</h2>
<p>
	as exported by InDesign (containing single <em>.html</em> and all
	cropped images)
</p>
<form style="<?php echo $style ?>" id="indd2post_create_post" action="" method="POST"
	enctype="multipart/form-data">
	<fieldset style="padding-bottom: 3rem;">
		<legend>Choose a zip containing an html document exported from
			InDesign and all images</legend>
		<label for="indesign_html_export_file_to_upload">Select zip file:<abbr
			title="required">*</abbr></label> <input style="display: inline;"
			type='file' id='indesign_html_export_file_to_upload'
			name='indesign_html_export_file_to_upload'></input>

	</fieldset>
	<fieldset style="padding-bottom: 3rem;">
		<legend>Create an standart post or any Custom Post Type listed below</legend>
		<label for="selected_post_type">Create content of type:<abbr
			title="required">*</abbr></label> <select name="selected_post_type"
			id="selected_post_type_id">
	                        <?php
            $args = array();
            $output = 'objects'; // names or objects
            InDesignHTML2Post_log("get post types...");
            $post_types = get_post_types($args, $output);
            $authorizedCPT = null;
            InDesignHTML2Post_log($this->indesign2post_settings);
            if ($this->indesign2post_settings) {
                $authorizedCPT = $this->indesign2post_settings->getOptions()['cpt_range'];
            }
            InDesignHTML2Post_log($authorizedCPT);
            foreach ($post_types as $post_type) {
                $shownVal = $post_type->label;
                $key = $post_type->name;
                if (isset($authorizedCPT) && is_array($authorizedCPT)) {
                    if (! in_array($key, $authorizedCPT)) {
                        continue;
                    }
                }
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $shownVal);
            }
            ?></select>

	</fieldset>
	<fieldset style="padding-bottom: 3rem;">
		<legend>The post will be created in the following status</legend>
		<label for="status">Create content in status:<abbr title="required">*</abbr></label>
		<select name="status" id="status_id">
    			<?php
            $statuses = get_post_statuses();
            foreach ($statuses as $key => $val) {
                if (! is_admin() && $key != 'publish') {
                    continue;
                }
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
            }
            ?></select>

	</fieldset>
	<fieldset style="padding-bottom: 3rem;">
		<legend>The image processor can basically add images as gallery at the
			end of the post or try a "smart insert" images and/or galleries as
			they are called inside the article body</legend>
		<label for="imgManagement">Image processing:</label><abbr
			title="required">*</abbr> <select name="imgManagement"
			id="imgManagement_id">
    			<?php
            $imgManagement = array(
                'inline' => 'Smart Insert : try to insert images when they are mentionned in the text',
                'gallery' => 'Standard : create gallery at the end of post with all images',
                'inline_gallery' => 'Both'
            );
            foreach ($imgManagement as $key => $val) {
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
            }
            ?></select>
	</fieldset>
	<fieldset style="margin-top: 1rem;">
	<?php
            
            if (is_user_logged_in()) {
                wp_nonce_field('InDesignHTML2Post_pdf_upload', 'InDesignHTML2Post_upload_nonce');
                ?>
		<input style="color: lime;" type="submit" name="indd2post_submit"
			value="<?php _e('Convert to post now!', 'indd2post'); ?>" />
		<?php } else {?>
		
		<a
			style="border-radius: 4px; color: lightcoral; padding: 1rem; background-color: black;"
			href="<?php
                global $wp;
                $redirect = add_query_arg($wp->query_vars, home_url($wp->request));
                echo wp_login_url($redirect);
                ?>"><?php _e('Please log in before converting :)', 'indd2post'); ?></a>
	
		<?php } ?>
	</fieldset>
</form>
<?php
            return ob_get_clean();
        }

        function indd2post_process_post_creation()
        {
            InDesignHTML2Post_log("Process front end submitted file...");
            $post_max_size = ini_get('post_max_size');
            $this->handle_indesign_html_export($post_max_size);
        }

        function outputUploadForm($post_max_size = 8000)
        {
            ?>
<h2>
	Upload a .zip file as exported by InDesign (containing single <em>.html</em>
	and all cropped images) | max size: <?php echo $post_max_size; ?>
</h2>
<!-- Form to handle the upload - The enctype value here is very important -->
<form method="post" enctype="multipart/form-data">

	<label for="indesign_html_export_file_to_upload">Select zip file:<abbr title="required">*</abbr></label> <input style="display: inline;" type='file' id='indesign_html_export_file_to_upload' name='indesign_html_export_file_to_upload'></input>
<!--  
	<span>Select zip file: </span><input type='file'
		id='indesign_html_export_file_to_upload'
		name='indesign_html_export_file_to_upload'></input>
		-->
						<?php wp_nonce_field( 'InDesignHTML2Post_pdf_upload', 'InDesignHTML2Post_upload_nonce' ); ?>
	
	<br /> <span>Create post of type:</span> <select name="selected_post_type" id="selected_post_type_id">
	                        <?php
            $args = array();
            $output = 'objects'; // names or objects
            InDesignHTML2Post_log("get post types...");
            $post_types = get_post_types($args, $output);
            $authorizedCPT = null;
            InDesignHTML2Post_log($this->indesign2post_settings);
            if ($this->indesign2post_settings) {
                $authorizedCPT = $this->indesign2post_settings->getOptions()['cpt_range'];
            }
            InDesignHTML2Post_log($authorizedCPT);
            foreach ($post_types as $post_type) {
                $shownVal = $post_type->label;
                $key = $post_type->name;
                if (isset($authorizedCPT) && is_array($authorizedCPT)) {
                    if (! in_array($key, $authorizedCPT)) {
                        continue;
                    }
                }
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $shownVal);
            }
            ?></select><br /> <span>Create post in status:</span> <select name="status" id="status_id">
    			<?php
            $statuses = get_post_statuses();
            foreach ($statuses as $key => $val) {
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
            }
            ?></select><br />Image processing: <select name="imgManagement" id="imgManagement_id">
    			<?php
            $imgManagement = array(
                'inline' => 'try to insert images when they are mentionned in the text',
                'gallery' => 'create gallery at the end of post with all images',
                'inline_gallery' => 'both'
            );
            foreach ($imgManagement as $key => $val) {
                printf('<option value="%s" style="margin-bottom:3px;">%s</option>', $key, $val);
            }
            ?></select> <br />
    		<?php
            
            if (is_admin()) {
                submit_button('Create post from InDesign HTML export zip');
            } else {
                ?><input type="submit" id="submit" name="submit" value="Go" /><?php
            }
            ?>
    	</form>
<?php
        }

        function InDesignHTML2Post_upload_page()
        {
            set_time_limit(240);
            exec("php -m | grep imagick", $out, $rcode4);
            $imagemagickInstalled = ! empty($out);
            $this->imagickEnabled = $imagemagickInstalled;
            $post_max_size = ini_get('post_max_size');
            if (is_admin()) {
                //$this->displayHTMLStatusOfCmd("Process file", "-------------- START --------------", 0);
                //$this->displayHTMLStatusOfCmd("input file", $_FILES, 0);
                
                $this->handle_indesign_html_export($post_max_size);
                //$this->displayHTMLStatusOfCmd("Process file", "-------------- END --------------", 0);
                
            }
            
            $installedColor = 'orange';
            $installedText = 'N/A';
            $additionnalText = '';
            if (extension_loaded('zip')) {
                $installedColor = 'green';
                $installedText = 'Installed';
            } else {
                $installedColor = 'red';
                $installedText = 'Not installed!';
                $additionnalText = 'Use something like : <pre>apt-get install php7.0-zip</pre>';
            }
            $zipText = '<span style="color:' . $installedColor . ';">' . $installedText . '</span>';
            if ($additionnalText) {
                $zipText .= $additionnalText;
            }
            
            // test imagick presence on server
            
            $imagickResults = implode(" ", $out);
            $this->displayHTMLStatusOfCmd("imagick", $out, 0);
            $imagickText = '<span style="color:' . ($imagemagickInstalled == true ? "green" : "red") . '">' . ($imagemagickInstalled == true ? "Installed " . $imagickResults : "Not installed") . '</span>';
            
            $here = plugin_dir_url(__FILE__);
            $indesignLogoFilename = "logoIndesign_128px.png";
            $InDesignLogoImg = $here . "img/" . $indesignLogoFilename;
            $mainFilePath = /*plugin_dir_path(__FILE__) .*/ __FILE__;
            $meta_datas = get_file_data($mainFilePath, array(
                'Version'
            ), 'plugin');
            
            ?>
<div style="text-align: center; padding: 5px;">
	<h1>
		<img style="height: 32px; width: auto;"
			src="<?php echo $InDesignLogoImg;?>"> InDesign HTML Export 2 Post
	</h1>
	<h2><?php echo $meta_datas[0] ?></h2>
	<a class="button-primary"
		href="https://wordpress.org/plugins/indesign2post/" target="_blank"><?php echo __( 'Visit Plugin Site', 'indesign2post' ); ?>  </a>
	<a class="button-primary" style="color: #FFF600;"
		href="https://wordpress.org/support/plugin/indesign2post/reviews/#new-post"
		target="_blank"><?php echo __ ( 'Please Rate!', 'indesign2post' ); ?>  </a>
</div>
<div
	style="background: #e0e0e0; border-radius: 4px; padding: 1em; border: 1px solid #a3a3a3; font-size: 1.2em;">
	The following libraries <em>NEED</em> to be installed on your server :
	<?php
            // echo $mainFilePath;
            InDesignHTML2Post_log($meta_datas);
            // echo '<span style="font-weight:bold;">'.$meta_datas[0].'</span>';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                echo '<span style="color:red;">Unfortunately, this plugin has not been tested on Windows OS.</span>';
            } else {
                echo '<pre>' . php_uname() . '</pre>';
            }
            ?>
	<ul style="list-style: inside;">
		<li><a href="http://php.net/manual/fr/class.ziparchive.php"
			target="_blank">ZipArchive</a> <?php echo $zipText;?></li>
		<li><a href="" target="_blank">ImageMagick</a> <?php echo $imagickText;?></li>

	</ul>
</div>

<?php
            
            $this->outputUploadForm($post_max_size);
        }

        function attach_image_to_post($filename, $resizedImagesArrayNames, $post_id, $images_legends, $featured)
        {
            
            if (!file_exists($filename)){
                InDesignHTML2Post_log("ERROR::No file $filename to attach to post: ".$post_id);
                return -1;
            } else {
                InDesignHTML2Post_log("+++++++++++++++++");
                $msg = "+++ Attach image ".basename($filename). " to post ".$post_id;
                InDesignHTML2Post_log($msg);
            }
            
            // The ID of the post this attachment is for.
            $parent_post_id = $post_id;
            
            // Check the type of file. We'll use this as the 'post_mime_type'.
            $filetype = wp_check_filetype(basename($filename), null);
            
            // Get the path to the upload directory.
            $wp_upload_dir = wp_upload_dir();
            $my_image_caption = $my_image_content = $my_image_description = '';
            
            $msg = basename($filename) . ' / '. $resizedImagesArrayNames[basename($filename)]. ' / '. $images_legends[basename($filename)]. ' / '. $images_legends[$resizedImagesArrayNames[basename($filename)]];
            InDesignHTML2Post_log($msg);
            
            if (is_array($images_legends) && (isset($images_legends[basename($filename)]) || isset($images_legends[$resizedImagesArrayNames[basename($filename)]]))) {
                if (isset($images_legends[basename($filename)])) {
                    $my_image_caption = $my_image_content = $my_image_description = $images_legends[basename($filename)];
                } else if (isset($images_legends[$resizedImagesArrayNames[basename($filename)]])) {
                    $my_image_caption = $my_image_content = $my_image_description = $images_legends[$resizedImagesArrayNames[basename($filename)]];
                }
            }
            if ($my_image_caption) {
                InDesignHTML2Post_log($my_image_caption);
                echo $this->displayHTMLStatusOfCmd('caption', "Caption found: " . $my_image_caption, 0);
            } else {
                $msg = "No image caption for " . basename($filename);
                InDesignHTML2Post_log($msg);
                InDesignHTML2Post_log($images_legends);
                InDesignHTML2Post_log($resizedImagesArrayNames);
                echo $this->displayHTMLStatusOfCmd('caption', $msg, 1);
            }
            // Prepare an array of post data for the attachment.
            $attachment = array(
                'guid' => $wp_upload_dir['url'] . '/' . basename($filename),
                'post_mime_type' => $filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content' => $my_image_content,
                'post_status' => 'inherit',
                'post_excerpt' => $my_image_caption, // Set image Caption (Excerpt) to sanitized title
                'post_content' => $my_image_description // Set image Description (Content) to sanitized title
            );
            
            // Insert the attachment.
            $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);
            if (wp_attachment_is_image($attach_id)) {
                // Set the image Alt-Text
                update_post_meta($attach_id, '_wp_attachment_image_alt', $my_image_caption);
            } else {
                InDesignHTML2Post_log("WARN::Attachement is not image: ".$attach_id." ".$filename);
            }
            
            
            // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
            require_once (ABSPATH . 'wp-admin/includes/image.php');
            
            // Generate the metadata for the attachment, and update the database record.
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            InDesignHTML2Post_log($attach_data);
            // (int|bool) False if $post is invalid.
            $success = wp_update_attachment_metadata($attach_id, $attach_data);
            InDesignHTML2Post_log($success);
            if (!$success){
                InDesignHTML2Post_log("ERROR:: updating attachment metadata $attach_id / $filename");
            }
            
            if ($featured) {
                InDesignHTML2Post_log("*** Setting image as featured ***");
                set_post_thumbnail($parent_post_id, $attach_id);
            }
            return $attach_id;
        }

        function pdf_upload_filter($file)
        {
            InDesignHTML2Post_log("|||||| Filtering File name " . $file['name']);
            $file['name'] = preg_replace("/ /", "-", $file['name']);
            
            return $file;
        }

        function normpath($path)
        {
            if (empty($path))
                return '.';
            
            if (strpos($path, '/') === 0)
                $initial_slashes = true;
            else
                $initial_slashes = false;
            if (($initial_slashes) && (strpos($path, '//') === 0) && (strpos($path, '///') === false))
                $initial_slashes = 2;
            $initial_slashes = (int) $initial_slashes;
            
            $comps = explode('/', $path);
            $new_comps = array();
            foreach ($comps as $comp) {
                if (in_array($comp, array(
                    '',
                    '.'
                )))
                    continue;
                if (($comp != '..') || (! $initial_slashes && ! $new_comps) || ($new_comps && (end($new_comps) == '..')))
                    array_push($new_comps, $comp);
                elseif ($new_comps)
                    array_pop($new_comps);
            }
            $comps = $new_comps;
            $path = implode('/', $comps);
            if ($initial_slashes)
                $path = str_repeat('/', $initial_slashes) . $path;
            if ($path)
                return $path;
            else
                return '.';
        }

        function clean($string)
        {
            $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
            
            return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
        }

        function dir_tree($dir)
        {
            // http://www.php.net/manual/de/function.scandir.php#102505
            $paths = array();
            $stack[] = $dir;
            while ($stack) {
                $thisdir = array_pop($stack);
                if ($dircont = scandir($thisdir)) {
                    $i = 0;
                    while (isset($dircont[$i])) {
                        if ($dircont[$i] !== '.' && $dircont[$i] !== '..') {
                            $current_file = "{$thisdir}/{$dircont[$i]}";
                            if (is_file($current_file)) {
                                $paths[] = "{$thisdir}/{$dircont[$i]}";
                            } elseif (is_dir($current_file)) {
                                $paths[] = "{$thisdir}/{$dircont[$i]}";
                                $stack[] = $current_file;
                            }
                        }
                        $i ++;
                    }
                }
            }
            return $paths;
        }

        function getInsertionPosition($pContent, $figPos)
        {
            $insert_postion = - 1;
            if ($figPos < 0) {
                InDesignHTML2Post_log("No position for match");
            } else {
                
                $end_of_EOL_pos = strpos($pContent, PHP_EOL, $figPos);
                if ($end_of_EOL_pos === false) {
                    $end_of_EOL_pos = - 1;
                }
                $end_of_paragraph_pos = strpos($pContent, "</p>", $figPos);
                if ($end_of_paragraph_pos === false) {
                    $end_of_paragraph_pos = - 1;
                }
                $end_of_line_pos = strpos($pContent, "<br>", $figPos);
                if ($end_of_line_pos === false) {
                    $end_of_line_pos = - 1;
                }
                $end_of_line_pos_2 = strpos($pContent, "<br/>", $figPos);
                if ($end_of_line_pos_2 === false) {
                    $end_of_line_pos_2 = - 1;
                }
                
                $insert_postion = max($end_of_EOL_pos, $end_of_paragraph_pos, $end_of_line_pos, $end_of_line_pos_2);
                $positions_msg = "----> insertion position : $figPos / positions found : $end_of_EOL_pos, $end_of_paragraph_pos, $end_of_line_pos, $end_of_line_pos_2 / retained : $insert_postion";
                InDesignHTML2Post_log($positions_msg);
                // echo $positions_msg . '<br/>';
            }
            return $insert_postion;
        }

        function buildNumChain($arr)
        {
            $ret = [];
            foreach (range($arr[0], $arr[1]) as $letter) {
                $ret[] = $letter;
            }
            return $ret;
        }

        function getAllFigCallsAsArray($postContent)
        {
            // InDesignHTML2Post_log($postContent);
            // InDesignHTML2Post_log(html_entity_decode($postContent));
            $result = $matches = array();
            // |[\s\p{Z}\p{C}\x85\xA0\x{0085}\x{00A0}\x{FFFD}]+
            $pattern = '/(?:\(fig\.\h*|\G(?!^))(?:&nbsp;)?(?<img_nb>\d+)(?<img_first_letter>[a-z])?(?:-(?<img_second_letter>[a-z])?)?(?:(?<separator>,\h*|\h*(?:&nbsp;)*à(?:&nbsp;)*\h*|\h*(?:&nbsp;)*et(?:&nbsp;)*\h*))?(?=[^)]*\))/m';
            
            $flags = PREG_OFFSET_CAPTURE;
            // FIXME : use of html_entity_decode may change image position in text, but it should remain very rare
            preg_match_all($pattern, $postContent, $matches, $flags); // preg_match_all means /g option
            
            InDesignHTML2Post_log("All figure calls found: " . count($matches[1]));
            InDesignHTML2Post_log($matches);
            
            foreach ($matches['img_nb'] as $idx => $imgNumberData) {
                $startLetter = $endLetter = $endNumber = "";
                $imgNumber = $imgNumberData[0];
                $imgNumberPosition = $imgNumberData[1];
                $insertAtPosition = $this->getInsertionPosition($postContent, $imgNumberPosition);
                
                if (isset($matches[0][$idx]) && ! empty($matches[0][$idx])) {
                    $fullmatch = $matches[0][$idx][0];
                }
                
                if (isset($matches['img_first_letter'][$idx]) && ! empty($matches['img_first_letter'][$idx]) && ctype_alpha($matches['img_first_letter'][$idx][0])) {
                    $startLetter = $matches['img_first_letter'][$idx][0];
                }
                
                if (isset($matches['separator'][$idx]) && ! empty($matches['separator'][$idx])) {
                    $separator = $matches['separator'][$idx][0];
                }
                
                if (isset($matches['img_second_letter'][$idx]) && ! empty($matches['img_second_letter'][$idx])) {
                    if (is_numeric($matches['img_second_letter'][$idx][0])) {
                        $endNumber = $matches['img_second_letter'][$idx][0];
                    } else if (ctype_alpha($matches['img_second_letter'][$idx][0])) {
                        $endLetter = $matches['img_second_letter'][$idx][0];
                    }
                }
                
                InDesignHTML2Post_log($fullmatch . " | nb " . $imgNumber . " => " . $endNumber . ") $startLetter => $endLetter " . $imgNumberPosition . ' converts to ' . $insertAtPosition);
                
                $letters = array();
                if (! empty($startLetter)) {
                    $letters[] = $startLetter;
                    if (! empty($endLetter)) {
                        // if ($separator == "-" || $separator == " à "){
                        $letters = $this->buildNumChain(array(
                            $startLetter,
                            $endLetter
                        ));
                        InDesignHTML2Post_log("interpolated letters:");
                        InDesignHTML2Post_log($letters);
                        // }
                    }
                }
                
                if (isset($result[$insertAtPosition]) && isset($result[$insertAtPosition][$imgNumber]) && is_array($result[$insertAtPosition][$imgNumber])) {
                    $result[$insertAtPosition][$imgNumber] = array_merge($result[$insertAtPosition][$imgNumber], $letters);
                } else /* if (isset($result[$insertAtPosition]) && isset( $result[$insertAtPosition][$imgNumber])) */
                {
                    $result[$insertAtPosition][$imgNumber] = $letters;
                } /*
                   * else {
                   * $msg = "WARNING:: No value at position [$insertAtPosition][$imgNumber]";
                   * InDesignHTML2Post_log( $msg );
                   * }
                   */
            }
            
            // the interpolate numbers if number range provided
            foreach ($result as $pointOfInsertion => $datas) {
                $imgCount = count($datas);
                if ($imgCount < 2) {
                    InDesignHTML2Post_log(" only " . $imgCount . " images");
                    continue;
                }
                
                $maxK = max(array_keys($datas));
                $minK = min(array_keys($datas));
                InDesignHTML2Post_log("Interpolate from " . $minK . " to " . $maxK);
                if (is_numeric($minK) && is_numeric($maxK)) {
                    $interpolated = array_fill_keys(range($minK, $maxK), "");
                    InDesignHTML2Post_log("interpolated numbers:");
                    InDesignHTML2Post_log($interpolated);
                    $result[$pointOfInsertion] = $interpolated;
                } else {
                    InDesignHTML2Post_log("ERROR::Mixed Interpolatation impossible");
                    continue;
                }
            }
            
            // InDesignHTML2Post_log( $result );
            return $result;
        }

        public function getFinalDimensions($origWidth, $origHeight, $width, $height, $resizeOption = 'default')
        {
            $result = array(
                'resizeWidth' => 0,
                'resizeHeight' => 0
            );
            switch (strtolower($resizeOption)) {
                case 'exact':
                    $result['resizeWidth'] = $width;
                    $result['resizeHeight'] = $height;
                    break;
                case 'maxwidth':
                    $result['resizeWidth'] = $width;
                    $result['resizeHeight'] = $this->resizeHeightByWidth($origWidth, $origHeight, $width);
                    break;
                case 'maxheight':
                    $result['resizeWidth'] = $this->resizeWidthByHeight($origWidth, $origHeight, $height);
                    $result['resizeHeight'] = $height;
                    break;
                default:
					/*if($this->origWidth > $width || $this->origHeight > $height)
					 {*/
					// horizontal image
					if ($origWidth > $origHeight) {
                        $result['resizeHeight'] = $this->resizeHeightByWidth($origWidth, $origHeight, $width);
                        $result['resizeWidth'] = $width;
                        // vertical image
                    } else if ($origWidth < $origHeight) {
                        $result['resizeWidth'] = $this->resizeWidthByHeight($origWidth, $origHeight, $height);
                        $result['resizeHeight'] = $height;
                    }
                    /*
                     * } else {
                     * $this->resizeWidth = $width;
                     * $this->resizeHeight = $height;
                     * }
                     */
                    break;
            }
            
            return $result;
        }

        /**
         * Get the resized height from the width keeping the aspect ratio
         *
         * @param int $width
         *            - Max image width
         *            
         * @return Height keeping aspect ratio
         */
        private function resizeHeightByWidth($origWidth, $origHeight, $width)
        {
            return floor(($origHeight / $origWidth) * $width);
        }

        /**
         * Get the resized width from the height keeping the aspect ratio
         *
         * @param int $height
         *            - Max image height
         *            
         * @return Width keeping aspect ratio
         */
        private function resizeWidthByHeight($origWidth, $origHeight, $height)
        {
            return floor(($origWidth / $origHeight) * $height);
        }

        private function getSettingFromOptions($setting_name, $default = false)
        {
            // $def = isset($default) ? $default : false;
            return isset($this->indesign2post_settings->getOptions()[$setting_name]) ? $this->indesign2post_settings->getOptions()[$setting_name] : $default;
        }

        function tryToResize($target_width, $target_height, $resize_mode, $inZipImageFile, $use_imagick, $destinationFile)
        {
            InDesignHTML2Post_log("<-> Resizing ($target_width, $target_height, $resize_mode) " . $inZipImageFile);
            if (! $this->imagickEnabled || ! $use_imagick) {
                $wp_resize = true;
                if ($wp_resize) {
                    $image = wp_get_image_editor($inZipImageFile);
                    if (! is_wp_error($image)) {
                        // $image->rotate( 90 );
                        InDesignHTML2Post_log("WP image resize " . $destinationFile);
                        $image->resize($target_width, $target_height, true);
                        $image->save($destinationFile);
                    } else {
                        InDesignHTML2Post_log("error loading file");
                    }
                } else {
                    
                    InDesignHTML2Post_log("manual resize");
                    $resize = new ResizeImage($inZipImageFile);
                    $resize->resizeTo($target_width, $target_height, $resize_mode);
                    $resize->saveImage($destinationFile);
                }
            } else {
                // use imagick if present
                $img = new Imagick();
                $img->readImage($inZipImageFile);
                InDesignHTML2Post_log("Imagick resize " . $img->getImageColorspace());
                
                // $image = new Imagick($image_src);
                $d = $img->getImageGeometry();
                $w = $d['width'];
                $h = $d['height'];
                $finalDimensions = $this->getFinalDimensions($w, $h, $target_width, $target_height, $resize_mode);
                // $img->setImageColorSpace(Imagick::COLORSPACE_RGB);
                
                if ($img->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
                    
                    $profiles = $img->getImageProfiles('*', false); // get profiles
                    $has_icc_profile = (array_search('icc', $profiles) !== false); // we're interested
                                                                                       // if
                                                                                       // ICC profile(s)
                                                                                       // exist
                    
                    if ($has_icc_profile === false) {
                        InDesignHTML2Post_log("No ICC profile");
                        $icc_cmyk = file_get_contents(dirname(__FILE__) . '/profiles/cmyk/UncoatedFOGRA29.icc');
                        $img->profileImage('icc', $icc_cmyk);
                        unset($icc_cmyk);
                    } else {
                        InDesignHTML2Post_log($profiles);
                    }
                    
                    // then we add an RGB profile
                    $icc_rgb = file_get_contents(dirname(__FILE__) . '/profiles/rgb/sRGB_v4_ICC_preference.icc');
                    $img->profileImage('icc', $icc_rgb);
                    unset($icc_rgb);
                    
                    $img->transformimagecolorspace(Imagick::COLORSPACE_SRGB);
                }
                
                $img->resizeImage(intval($finalDimensions['resizeWidth']), intval($finalDimensions['resizeHeight']), Imagick::FILTER_LANCZOS, 1);
                $img->stripImage();
                $img->writeImage($destinationFile);
            }
            InDesignHTML2Post_log("..done");
        }

        function getNewfilename($writeToPath, $existing_filename)
        {
            $actual_name = pathinfo($existing_filename, PATHINFO_FILENAME);
            $original_name = $actual_name;
            $extension = pathinfo($existing_filename, PATHINFO_EXTENSION);
            
            $i = 1;
            while (file_exists(trailingslashit($writeToPath) . $actual_name . "." . $extension)) {
                $actual_name = (string) $original_name . $i;
                $non_existing_filename = $actual_name . "." . $extension;
                $i ++;
            }
            
            return trailingslashit($writeToPath) . $non_existing_filename;
        }

        function processImages($writeToPath, $imgManagement, $unzipped, $created_post_id, $images_infos, $images_legends, $featured_img)
        {
            $result = array(
                'value' => false,
                'message' => ''
            );
            $attachedImagesArray = array();
            if (is_wp_error($created_post_id)) {
                $msg = "Stop process, no post created";
                InDesignHTML2Post_log($msg);
                $result['message'] .= $msg;
            } else {
                $msg = "then process images and attach to created post " . $created_post_id;
                InDesignHTML2Post_log($msg);
                $result['message'] .= $msg;
                $image_idx = 0;
                $total_images = count($unzipped);
                $resizedImagesArrayNames = [];
                foreach ($unzipped as $file) {
                    
                    $filename = $file['name'];
                    InDesignHTML2Post_log("-------------------------------------------------------------");
                    InDesignHTML2Post_log("---> Processing file $image_idx / $total_images : " . $filename);
                    $file_parts = pathinfo($filename);
                    switch ($file_parts['extension']) {
                        case "jpg":
                        case "png":
                            
                            $inZipImageFile = $file["tmp_name"];
                            // FIXME : can overwrite existing image, check before if destination already exists!
                            
                            $destinationFile = $writeToPath . basename($file["tmp_name"]);
                            if (file_exists($destinationFile)) {
                                InDesignHTML2Post_log("Warn: destination file already exists: " . $destinationFile);
                                $newDestination = $this->getNewfilename($writeToPath, $destinationFile);
                                InDesignHTML2Post_log(basename($file["tmp_name"])." renamed in " . basename($newDestination));
                                $destinationFile = $newDestination;
                                $resizedImagesArrayNames[basename($file["tmp_name"])] = basename($newDestination);
                                
                                // exit();
                                // continue;
                            }
                            // resize image if needed
                            $resize_enabled = false;
                            if ($this->indesign2post_settings) {
                                $postFeaturedImgTag = $this->getSettingFromOptions('featured_img_class_tag');
                                $resize_enabled = $this->getSettingFromOptions('resize_images');
                                $use_imagick = $this->getSettingFromOptions('use_imagick');
                                $target_width = $this->getSettingFromOptions('resize_images_width');
                                $target_height = $this->getSettingFromOptions('resize_images_height');
                                $resize_mode = $this->getSettingFromOptions('resize_mode', 'default');
                            }
                            if ($resize_enabled) {
                                try {
                                    $this->tryToResize($target_width, $target_height, $resize_mode, $inZipImageFile, $use_imagick, $destinationFile);
                                } catch (Exception $e) {
                                    InDesignHTML2Post_log($e->getMessage());
                                    if (! copy($inZipImageFile, $destinationFile)) {
                                        $msg = $e->getMessage() . " file copy did not work";
                                        InDesignHTML2Post_log($msg);
                                        $result['message'] .= $this->displayHTMLStatusOfCmd('attach', $msg, 1);
                                        break;
                                    } else {
                                        $msg = $inZipImageFile . " copied but resize exception catched";
                                        InDesignHTML2Post_log($msg);
                                    }
                                }
                            } else {
                                if (! copy($inZipImageFile, $destinationFile)) {
                                    $msg = "file copy did not work";
                                    InDesignHTML2Post_log($msg);
                                    $result['message'] .= $this->displayHTMLStatusOfCmd('attach', $msg, 1);
                                    break;
                                } else {
                                    $msg = $inZipImageFile . " copied to ".$destinationFile;
                                    InDesignHTML2Post_log($msg);
                                }
                            }
                            $absFilename = $destinationFile;
                            
                            $msg = "After resize, working on image : " . $absFilename;
                            InDesignHTML2Post_log($msg);
                            // $request_answer .= $msg . "<br/>";
                            if (is_file($absFilename)) {
                                $attachMsg = "-> Attaching image to post: " . $absFilename;
                                // InDesignHTML2Post_log($attachMsg);
                                // $result['message'] .= $attachMsg . '<br/>';
                                // FIXME specify if featured image or not
                                $featured = false;
                                if (! empty($featured_img)) {
                                    $featured_img_basename = basename($featured_img);
                                    $attached_img_basename = basename($absFilename);
                                    $featured = $featured_img_basename == $attached_img_basename;
                                    // InDesignHTML2Post_log($featured . ' : ' . $featured_img_basename);
                                    if ($featured) {
                                        InDesignHTML2Post_log("Featured image found : " . $featured_img);
                                    }
                                } else {
                                    InDesignHTML2Post_log("get first image as featured");
                                    $featured = $image_idx == 0;
                                }
                                
                                if ($featured) {
                                    InDesignHTML2Post_log("*** featured image ***");
                                } else {
                                    InDesignHTML2Post_log("not featured image");
                                }
                                
                                $attachedReturnValue = $this->attach_image_to_post($absFilename, array_flip($resizedImagesArrayNames), $created_post_id, $images_legends, $featured);
                                if ($attachedReturnValue > 0) {
                                    $attachedImagesArray[basename($absFilename)] = $attachedReturnValue;
                                    $attachMsg = "Attached sucessfully to post: " . $absFilename;
                                    // InDesignHTML2Post_log($attachMsg);
                                    // $result['message'] .= $attachMsg . '<br/>';
                                    $result['message'] .= $this->displayHTMLStatusOfCmd('attach', $attachMsg, 0);
                                } else {
                                    $attachMsg = "Error attaching image to post: " . $absFilename;
                                    
                                    $result['message'] .= $this->displayHTMLStatusOfCmd('attach', $attachMsg, 1);
                                }
                            }
                            
                            $image_idx ++;
                            break;
                        
                        default:
                            break;
                    }
                }
                
                // now we have array of attachements to create gallery
                InDesignHTML2Post_log("Attached image array: img name => img attachement id");
                InDesignHTML2Post_log($attachedImagesArray);
                
                if (count($attachedImagesArray) > 0) {
                    if (($imgManagement == "gallery" || $imgManagement == "inline_gallery")) {
                        
                        $current_post_obj = get_post($created_post_id);
                        $current_post_content = $current_post_obj->post_content;
                        
                        $gallery_shortcode = '[gallery size="full" link="file" columns="4" ids="' . implode(',', $attachedImagesArray) . '"]';
                        
                        // Update post
                        $update_post = array(
                            'ID' => $created_post_id,
                            'post_content' => $current_post_content . $gallery_shortcode
                        );
                        
                        // Update the post into the database
                        $result['message'] .= 'Creating image gallery...<br/>';
                        $updated_id = wp_update_post($update_post);
                        if (is_wp_error($updated_id)) {
                            $errors = $updated_id->get_error_messages();
                            foreach ($errors as $error) {
                                InDesignHTML2Post_log($error);
                                $result['message'] .= '<span style="color:red;">' . $error . '</span><br/>';
                            }
                        } else {
                            $attachMsg = 'Gallery successfully created : ' . $gallery_shortcode;
                            InDesignHTML2Post_log($attachMsg);
                            // $result['message'] .= $attachMsg . '<br/>';
                            $result['message'] .= $this->displayHTMLStatusOfCmd('', $attachMsg, 0);
                        }
                    } else {
                        $msg = '<span style="color:green;">No images to add to gallery</span>';
                        InDesignHTML2Post_log($msg);
                        $result['message'] .= $msg . '<br/>';
                    }
                    
                    $insertGalleriesOnlyOnce = true;
                    InDesignHTML2Post_log("Images resize name changes array");
                    InDesignHTML2Post_log($resizedImagesArrayNames);
                    
                    if (($imgManagement == "inline" || $imgManagement == "inline_gallery")) {
                        $positionOfFigures = array();
                        // add images inside the text body, detecting classes
                        // update post $created_post_id
                        if ($this->indesign2post_settings && $created_post_id > 0) {
                            $post_created = get_post($created_post_id);
                            $pContent = $post_created->post_content;
                            $bodyLength = strlen($pContent);
                            InDesignHTML2Post_log("positions should be found between 0 and " . $bodyLength);
                            /*
                             * $figureCall = $this->getSettingFromOptions('figure_call_class_tag');//
                             * $this->indesign2post_settings->getOptions()['figure_call_class_tag'];
                             * $msg = "figure call tag class: " . $figureCall;
                             * InDesignHTML2Post_log( $msg );
                             * $result['message'] .= '<br/>' . $msg;
                             *
                             * InDesignHTML2Post_log( "images infos" );
                             * InDesignHTML2Post_log( $images_infos );
                             * // InDesignHTML2Post_log($pContent);
                             * $result['message'] .= '<br/>attach images ids:<br/>';
                             * $result['message'] .= implode( ' | ', $attachedImagesArray );
                             *
                             * $regex = '\(fig\. [0-9]{1,3}';
                             * $figuresRetrieved = array_keys( $images_infos );
                             * InDesignHTML2Post_log( "All fig calls with regex:" );
                             */
                            $positionOfFigures = $this->getAllFigCallsAsArray($pContent);
                        }
                        
                        InDesignHTML2Post_log("position of figures");
                        
                        InDesignHTML2Post_log($positionOfFigures);
                        
                        $this->insertImagesIntoPost($positionOfFigures, $result, $created_post_id, $pContent, $images_infos, $attachedImagesArray, $resizedImagesArrayNames);
                    }
                }
            }
            return $result;
        }

        function init_images_infos($images_infos)
        {
            /*
             * $result = array();
             * foreach ($images_infos) {
             *
             * }
             * return $result;
             */
            foreach ($images_infos as $key => $image_infos) {
                $images_infos[$key]['marked_as_used'] = false; // this is the only new data
            }
            
            return $images_infos;
        }

        function multi_array_search($array, $search)
        {
            
            // Create the result array
            $result = array();
            
            // Iterate over each array element
            foreach ($array as $key => $value) {
                
                // Iterate over each search condition
                foreach ($search as $k => $v) {
                    
                    // If the array element does not meet the search condition then continue to the next element
                    if (! isset($value[$k]) || $value[$k] != $v) {
                        continue 2;
                    }
                }
                
                // Add the array element's key to the result array
                $result[] = $key;
            }
            
            // Return the result array
            return $result;
        }

        function image_info_exists(&$images_infos, $figureNumber, $letter)
        {
            // multi_array_search($list_of_phones, array('Manufacturer' => 'Apple', 'Model' => 'iPhone 6')));
            InDesignHTML2Post_log("image_info_exists::getting info on image $figureNumber, $letter");
            $matching_array_keys = $this->multi_array_search($images_infos, array(
                'image_number' => $figureNumber,
                'image_letter' => $letter
            ));
            $result = false;
            if (empty($matching_array_keys)) {
                InDesignHTML2Post_log("no info available");
            } else if (count($matching_array_keys) === 1) {
                $result = $images_infos[reset($matching_array_keys)];
            } else if (count($matching_array_keys) > 1) {
                foreach ($matching_array_keys as $matching_key) {
                    if ($images_infos[$matching_key]['marked_as_used'] == true) {
                        continue;
                    } else {
                        $result = $images_infos[$matching_key];
                        $images_infos[$matching_key]['marked_as_used'] = true;
                        break;
                    }
                }
            }
            
            return $result;
        }
        
        function getAttachmentId($image_infos, $attachedImagesArray, $resizedImagesArrayNames) {
            
            $foundAttachmentId = -1;
            $imgFileToInsert = $image_infos['filename'];
            
            if (isset($attachedImagesArray[$imgFileToInsert])) {
                $attachId = is_numeric($attachedImagesArray[$imgFileToInsert]);
                $foundAttachmentId = is_numeric($attachId) ? $attachId : -1;
            } else if (isset($resizedImagesArrayNames[$imgFileToInsert]) && isset($attachedImagesArray[$resizedImagesArrayNames[$imgFileToInsert]])) {
                // if image has been resized and there was already an existing image with same name, then here is the new name
                $attachId = $attachedImagesArray[$resizedImagesArrayNames[$imgFileToInsert]];
                $foundAttachmentId = is_numeric($attachId) ? $attachId : -1;
            } else {
                InDesignHTML2Post_log("ERROR::Cannot find attachment ".$imgFileToInsert);
            }
            InDesignHTML2Post_log("--> find attachment ID for ".$imgFileToInsert." ".$attachId." ".$foundAttachmentId);
            return $foundAttachmentId;
        }

        function insertImagesIntoPost(&$positionOfFigures, &$result, $created_post_id, $pContent, $images_infos, $attachedImagesArray, $resizedImagesArrayNames)
        {
            if (empty($positionOfFigures)) {
                $msg = "No position found";
                InDesignHTML2Post_log($msg);
                $result['message'] .= $msg;
            } else {
                // reverse array in order to insert galleries from the end of the content, so as
                // not to modify insertion positions previously computed
                ksort($positionOfFigures);
                $positionOfFigures = array_reverse($positionOfFigures, true);
                
                // reorganise images info by image number
                $images_infos = $this->init_images_infos(array_reverse($images_infos));
                InDesignHTML2Post_log($images_infos);
                
                InDesignHTML2Post_log("--> --> --> --> --> INSERT IMAGES IN POST <-- <-- <-- <-- <--");
                InDesignHTML2Post_log($positionOfFigures);
                foreach ($positionOfFigures as $insertAt => $figureNumbers) {
                    $msg = "-------------------------------------------------------";
                    // $msg .= "Insert at " . $insertAt . " ---> figures with numbers : ";
                    // FIXME : consider multiple similar figure numbers can be given inside same article !
                    InDesignHTML2Post_log($msg);
                    InDesignHTML2Post_log($figureNumbers);
                    
                    $result['message'] .= $msg . '<br/>';
                    $galleryImgIds = array();
                    
                    foreach ($figureNumbers as $key => $datas) {
                        InDesignHTML2Post_log("--> Processing fig $key");
                        
                        if (! empty($datas)) {
                            foreach ($datas as $letter) {
                                $image_infos = $this->image_info_exists($images_infos, $key, $letter);
                                InDesignHTML2Post_log($image_infos);
                                if ($image_infos) {
                                    
                                    $foundAttachmentId = $this->getAttachmentId($image_infos, $attachedImagesArray, $resizedImagesArrayNames);
                                    
                                    if ($foundAttachmentId > 0) {
                                        //$imgFileToInsert = $image_infos['filename']; // $images_infos[$key][$letter];
                                        $galleryImgIds[] = $foundAttachmentId;
                                        $msg = "+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++";
                                        InDesignHTML2Post_log($msg);
                                        $msg = $key . " | $letter) +++ Adding file " . $image_infos['filename'] . " to gallery with ID " . $foundAttachmentId;
                                        InDesignHTML2Post_log($msg);
                                        $result['message'] .= $msg;
                                    } else {
                                        InDesignHTML2Post_log("ERROR::Image not attached in array : " . $image_infos['filename']);
                                        InDesignHTML2Post_log($attachedImagesArray);
                                    }
                                } else {
                                    $msg = "No infos collected about image : " . $key . " | " . $letter;
                                    InDesignHTML2Post_log($msg);
                                    $result['message'] .= $msg;
                                }
                            }
                        } else {
                            $no_letter = 'no_letter';
                            $image_infos = $this->image_info_exists($images_infos, $key, $no_letter);
                            InDesignHTML2Post_log($image_infos);
                            if ($image_infos) {
                                // InDesignHTML2Post_log( $images_infos[$key] );
                                // $imgFilesToInsert = $images_infos[$key][$no_letter];
                                
                                $foundAttachmentId = $this->getAttachmentId($image_infos, $attachedImagesArray, $resizedImagesArrayNames);
                                
                                if ($foundAttachmentId > 0) {
                                    $galleryImgIds[] = intval($foundAttachmentId);
                                    $msg = "+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++";
                                    InDesignHTML2Post_log($msg);
                                    $msg = $key . " | $no_letter) +++ Adding file " . $image_infos['filename'] . " to gallery with ID " . $foundAttachmentId;
                                    InDesignHTML2Post_log($msg);
                                    $result['message'] .= $msg;
                                } else {
                                    InDesignHTML2Post_log("ERROR: cannot match ".$image_infos['filename']. " inside attachement array, name changed ?");
                                }
                            } else {
                                $msg = "WARN::No infos about image $key in infos array";
                                
                                InDesignHTML2Post_log($msg);
                                // InDesignHTML2Post_log( $attachedImagesArray );
                                $result['message'] .= $msg;
                                // InDesignHTML2Post_log( $images_infos );
                            }
                        }
                    }
                    
                    if (! empty($galleryImgIds)) {
                        InDesignHTML2Post_log(count($galleryImgIds) . " imgs in gallery at " . $insertAt);
                        $gallery_shortcode = '[gallery size="full" link="file" columns="4" ids="' . implode(',', $galleryImgIds) . '"]';
                        $result['message'] .= $gallery_shortcode . '<br/>';
                        InDesignHTML2Post_log($gallery_shortcode);
                        $pContent = substr_replace($pContent, $gallery_shortcode, $insertAt + 1, 0);
                    }
                }
                
                InDesignHTML2Post_log("images infos should be marked as used almost everywhere");
                InDesignHTML2Post_log($images_infos);
                
                // Update post
                $update_post = array(
                    'ID' => $created_post_id,
                    'post_content' => $pContent
                );
                
                // Update the post into the database
                $result['message'] .= 'Inserting image galleries inside post...<br/>';
                InDesignHTML2Post_log("update post with new content - galleries added");
                $updated_id = wp_update_post($update_post);
                if (is_wp_error($updated_id)) {
                    $errors = $updated_id->get_error_messages();
                    foreach ($errors as $error) {
                        InDesignHTML2Post_log($error);
                        $result['message'] .= '<span style="color:red;">' . $error . '</span><br/>';
                    }
                } else {
                    $attachMsg = 'Post successfully updated :)';
                    InDesignHTML2Post_log($attachMsg);
                    // $result['message'] .= $attachMsg . '<br/>';
                    $result['message'] .= $this->displayHTMLStatusOfCmd('', $attachMsg, 0);
                    $result['value'] = true;
                }
            }
        }

        function getUploadDirectory()
        {
            $upload = wp_upload_dir();
            $upload_dir = $upload['basedir'];
            $upload_dir = $upload_dir . '/' . $this->uploadDirectoryName;
            // $this->images_abs_path = $upload_dir;
            if (! is_dir($upload_dir)) {
                mkdir($upload_dir, 0755);
                InDesignHTML2Post_log("upload dir created : " . $upload_dir);
                // self::getLogger()->info('+++ Image directory created : ' . $this->images_abs_path);
            } else {
                // self::getLogger()->debug('+++ Image directory already exists : ' . $this->images_abs_path);
                chmod($upload_dir, 0755);
                InDesignHTML2Post_log("upload dir already exists : " . $upload_dir);
            }
            
            if (is_writable($upload_dir)) {
                InDesignHTML2Post_log("folder writable : " . $upload_dir);
                // self::getLogger()->debug("Folder writable : " . $upload_dir);
            } else {
                InDesignHTML2Post_log("upload dir not writable : " . $upload_dir);
                // self::getLogger()->error("Folder not writable : " . $upload_dir);
            }
            
            return $upload_dir;
        }
        
        private function codeToMessage($code)
        {
            switch ($code) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "The uploaded file was only partially uploaded";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "No file was uploaded";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "Missing a temporary folder";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Failed to write file to disk";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = "File upload stopped by extension";
                    break;
                    
                default:
                    $message = "Unknown upload error";
                    break;
            }
            return $message;
        }

        function handle_indesign_html_export($post_max_size = 8000)
        {
            $request_answer = '';
            // First check if the file appears on the _FILES array
            //InDesignHTML2Post_log($_FILES);
            //$post_max_size = ini_get('post_max_size');
            if($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) { 
                $msg = "File is too big: ".$_SERVER['CONTENT_LENGTH']." > ".$post_max_size;
                $request_answer .= $msg;
                echo $this->displayHTMLStatusOfCmd("file", $msg, 1);
                //echo $request_answer;
            } else if (isset($_FILES['indesign_html_export_file_to_upload'])) {
                InDesignHTML2Post_log($_POST);
                if (wp_verify_nonce($_POST['InDesignHTML2Post_upload_nonce'], 'InDesignHTML2Post_pdf_upload')) {
                    InDesignHTML2Post_log("wp_verify_nonce : OK");
                    $request_answer .= $this->displayHTMLStatusOfCmd("wp_verify_nonce", "OK", 0);
                    $file = $_FILES['indesign_html_export_file_to_upload'];
                    $filename = sanitize_file_name($file['name']);
                    $status = $_POST['status'];
                    $post_type_to_create = sanitize_text_field($_POST['selected_post_type']);
                    $imgManagement = $_POST['imgManagement'];
                    InDesignHTML2Post_log($file);
                    InDesignHTML2Post_log($status);
                    InDesignHTML2Post_log($imgManagement);
                    if (stripos($filename, '.zip')) {
                        // multiple files to process
                        InDesignHTML2Post_log("<---");
                        InDesignHTML2Post_log("<--- Zip File received (" . $status . ") : ");
                        InDesignHTML2Post_log("<---");
                        
                        $post_id = 0;
                        $unzipped = array();
                        if (! is_admin()) {
                            $path_to_include = ABSPATH . 'wp-admin/includes/file.php';
                            InDesignHTML2Post_log($path_to_include);
                            require_once ($path_to_include);
                        } else {
                            InDesignHTML2Post_log("Is admin page");
                        }
                        WP_Filesystem();
                        $uploadDir = wp_upload_dir();
                        InDesignHTML2Post_log($uploadDir);
                        $writeToPath = trailingslashit($uploadDir['path']);
                        InDesignHTML2Post_log($writeToPath);
                        foreach ($_FILES as $fid => $array) {
                            $request_answer = "-> file to upload : " . $fid . ' / ' . $filename . " (" . $array['name'] . ") / " . $array['type'] . " / " . $array['tmp_name'];
                            
                            if ($array['error'] !== UPLOAD_ERR_OK) {
                                $err_id = $array['error'];
                                $msg = $this->codeToMessage($err_id);
                                InDesignHTML2Post_log($msg);
                                $request_answer .= $this->displayHTMLStatusOfCmd($filename, $msg, $array['error']);
                                return $msg;
                            } else {
                                $msg = "File uploaded without error :)";
                                InDesignHTML2Post_log($msg);
                            }
                            
                            $useWPUploadDirectory = true;
                            if ($useWPUploadDirectory) {
                                $globalTempDir = $this->getUploadDirectory();
                            } else {
                                $globalTempDir = get_temp_dir();
                            }
                            $tempWritableRes = is_writable($globalTempDir);
                            
                            $msgNotWritable = "Writable " . $globalTempDir;
                            InDesignHTML2Post_log($msgNotWritable);
                            $request_answer .= $this->displayHTMLStatusOfCmd($globalTempDir, $msgNotWritable, ! $tempWritableRes);
                            
                            $tempDir = trailingslashit(trailingslashit($globalTempDir) . wp_generate_uuid4());
                            // $chmodPermissions = 0755;
                            
                            if (! file_exists($tempDir)) {
                                if (wp_mkdir_p($tempDir)) {
                                    $msg = "Directory created successfully: " . $tempDir;
                                    $request_answer .= $this->displayHTMLStatusOfCmd($tempDir, $msg, 0);
                                    if (chmod($tempDir, 0775)) {
                                        $msg = "0775 mod successfully: " . $tempDir;
                                        $request_answer .= $this->displayHTMLStatusOfCmd($tempDir, $msg, 0);
                                    } else {
                                        $msg = "0775 mod failed: " . $tempDir;
                                        $request_answer .= $this->displayHTMLStatusOfCmd($tempDir, $msg, 1 );
									}
								} else {
									$msg = "Cannot create directory: " . $tempDir;
									$request_answer .= $this->displayHTMLStatusOfCmd( $tempDir, $msg, 1 );
								}
							} else {
								$msg = "Directory already exists: " . $tempDir;
								$request_answer .= $this->displayHTMLStatusOfCmd( $tempDir, $msg, 0 );
							}
							
							$tempDirWritableRes = is_writable( $tempDir );
							$msgNotWritable = "Writable " . $tempDirWritableRes;
							InDesignHTML2Post_log( $msgNotWritable );
							$request_answer .= $this->displayHTMLStatusOfCmd( 
								$tempDir,
								$msgNotWritable,
								! $tempDirWritableRes );
							
							$msg = "Will unzip file " . $array['tmp_name'] . " toward directory used : " . $tempDir;
							InDesignHTML2Post_log( $msg );
							$request_answer .= $msg . '<br/>';
							$unzip_return = unzip_file( $array['tmp_name'], $tempDir );
							if ( is_wp_error( $unzip_return ) ) {
								$msg = "Cannot unzip file : " . $array['tmp_name'] . " to folder " . $tempDir . " : " .
									$unzip_return->get_error_message();
								;
								InDesignHTML2Post_log( $msg );
								
								$request_answer .= $msg;
							} else {
								$filepaths = $this->dir_tree( $tempDir );
								foreach ( $filepaths as $k => $filepath ) {
									if ( is_file( $filepath ) ) {
										$file = array();
										$file['name'] = basename( $filepath );
										$file['size'] = filesize( $filepath );
										$file['tmp_name'] = $filepath;
										$unzipped["unzipped_$k"] = $file;
									}
								}
							}
						}
						
						// indesign export produces a zip like this:
						// article name . html
						// article_name-web-resources/
						// article_name-web-resources/css/idGeneratedStyles.css
						// article_name-web-resources/images/*all images.jpg
						$created_post_id = null;
						$created_post_name = null;
						$featured_img = null;
						$htmlFileFound = false;
						// need to upload main html and all images
						foreach ( $unzipped as $file ) {
							// InDesignHTML2Post_log("----- File in zip:");
							// InDesignHTML2Post_log($file);
							$filename = $file['name'];
							
							$file_parts = pathinfo( $filename );
							
							// first process text content and create post
							switch ( $file_parts['extension'] ) {
								
								case "html" :
									$htmlFileFound = true;
									$msg = "HTML found in zip:" . $file['name'];
									InDesignHTML2Post_log( $msg );
									// $inputFileName = basename($file['name']);
									$created_post_name = preg_replace( 
										'/\\.[^.\\s]{3,4}$/',
										'',
										basename( $file['name'] ) );
									// $request_answer .= $msg . '<br/>';
									$request_answer .= $this->displayHTMLStatusOfCmd( "HTML lookup", $msg, 0 );
									InDesignHTML2Post_log( "========================================" );
									InDesignHTML2Post_log( "=====  Create post from HTML file  =====" );
									InDesignHTML2Post_log( "========================================" );
									// parse html and create WP post
									$returnVals = $this->createPostFromInDesignHtmlExportedFile( 
										$file,
										$status,
										$post_type_to_create );
									$created_post_id = $returnVals['inserted'];
									$images_infos = $returnVals['images_infos'];
									$images_legends = $returnVals['images_legends'];
									$featured_img = $returnVals['featured_img'];
									break;
								
								default :
									break;
							}
						}
						
						if ( ! $htmlFileFound ) {
							$msg = "No HTML file found in zip";
							$request_answer .= $this->displayHTMLStatusOfCmd( "html", $msg, 1 );
							echo $request_answer;
							return false;
						}
						
						InDesignHTML2Post_log( $images_legends );
						
						InDesignHTML2Post_log( "========================================" );
						InDesignHTML2Post_log( "=====  Process images              =====" );
						InDesignHTML2Post_log( "========================================" );
						
						// process images, resize and add as attachements to previously created post
						
						$image_processing = $this->processImages( 
							$writeToPath,
							$imgManagement,
							$unzipped,
							$created_post_id,
							$images_infos,
							$images_legends,
							$featured_img );
						
						if ( ! $image_processing['value'] ) {
							$img_err_msg = "Post $created_post_id : Error processing images<br/>";
							$img_err_msg .= $image_processing['message'];
							echo $this->displayHTMLStatusOfCmd( "images processing", $img_err_msg, 1 );
							InDesignHTML2Post_log( $img_err_msg );
						} else {
							echo $this->displayHTMLStatusOfCmd( "images processing", "Successfully processed images", 0 );
						}
						
						$newposturl = get_edit_post_link( $created_post_id );
						$preview = get_preview_post_link( $created_post_id );
						?>
<br />
<br />
<span style="font-size: 1.3em;"> <a target="_blank"
	href="<?php echo $preview ?>">Preview created post #<?php echo $created_post_id; ?> : <?php echo $returnVals['post_title'] ?></a><br />
	<a target="_blank" href="<?php echo $newposturl ?>">Edit created post #<?php echo $created_post_id; ?> : <?php echo $returnVals['post_title'] ?></a></span>
<?php
					} else {
						$attachMsg = "Not a zip file";
						
						echo $this->displayHTMLStatusOfCmd( 'File type', $attachMsg, 1 );
					}
				} else {
					$msg = "The security check failed";
					// The security check failed, maybe show the user an error.
					InDesignHTML2Post_log( $msg );
					$request_answer .= $msg;
					if ( is_admin() ) {
						echo $request_answer;
					} else {
						echo $request_answer;
					}
					return null;
				}
			} else {
				$msg = "WARNING:Not uploading file";				
				InDesignHTML2Post_log( $msg );
				InDesignHTML2Post_log( $_FILES );	
				if ( is_admin() ) {
				    echo $request_answer;
				} else {
				    echo $request_answer;
				}
			}
		}

		function getTextBetweenTags( $string, $tagname, $tags_to_replace ) {
			$pattern = "/<$tagname ?.*>(.*)<\/$tagname>/";
			preg_match( $pattern, $string, $matches );
			InDesignHTML2Post_log( $pattern );
			InDesignHTML2Post_log( $tagname );
			InDesignHTML2Post_log( $matches );
			$result = $matches[0];
			foreach ( $tags_to_replace as $tag ) {
				$result = str_replace( $tag, " ", $result );
			}
			
			$result = strip_tags( $result );
			InDesignHTML2Post_log( $result );
			// return $matches[1];
			return $result;
		}

		function cleanHtmlCharactersFromString( $input ) {
			//$output = preg_replace( "/(&#[0-9]+;)/", ' ', $input );
			$output = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $input);
			
			return $output;
		}

		function findAllImagesFilenamesAndLegends( $doc, $finder, $imgBlocClassname, $legend ) {
			$images_legends = $imagesArray = array();
			$nodes = $finder->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $imgBlocClassname ')]" );
			$msg = "--- find all figure blocs and search for img and legends";
			InDesignHTML2Post_log( $msg );
			foreach ( $nodes as $node ) {
				InDesignHTML2Post_log( "=======================================================================" );
				$imgNodes = $node->getElementsByTagName( 'img' );
				$pNodes = $node->getElementsByTagName( 'p' );
				$imgSize = count( $imgNodes );
				$pSize = count( $pNodes );
				$figureNumber = - 1;
				if ( $pSize > 0 && $imgSize > 0 ) {
					
					$fullMatch = "";
					$imgNumber = - 1;
					$letterStart = "";
					$letterEnd = "";
					$currentNodeImgArray = array();
					$currentNodeLegendArray = array();
					
					InDesignHTML2Post_log( count( $imgNodes ) . ' img nodes found' );
					
					foreach ( $imgNodes as $imgNode ) {
						$currentNodeImgArray[] = basename( $imgNode->getAttribute( 'src' ) );
					}
					InDesignHTML2Post_log( $currentNodeImgArray );
					InDesignHTML2Post_log( count( $pNodes ) . ' legend nodes found' );
					foreach ( $pNodes as $maybeLegend ) {
						// here we try to detect image(s) associated with legend, can be multiple (!)
						$classesOfNode = $maybeLegend->getAttribute( 'class' );
						
						$pos = strpos( $classesOfNode, $legend );
						if ( $pos !== false ) {
							$msg = "============ Legend found : " . $maybeLegend->textContent . " =============";
							InDesignHTML2Post_log( $msg );
							$request_answer = '<br/>' . $msg;
							
							$legend_regex = "/^(?<img_nb>[0-9]{1,3})(?<first_img_first_letter>[a-z])?(?:-(?<first_img_second_letter>[a-z])?)?(?:(, | à | et ))?(?<img_second_nb>[0-9]{1,3})?(?<second_img_first_letter>[a-z])?(:-(?<second_img_second_letter>[a-z])?)?\.?/";
							
							preg_match( $legend_regex, $maybeLegend->textContent, $matches );
							
							InDesignHTML2Post_log( "Legend " . $maybeLegend->textContent . " regex result" );
							InDesignHTML2Post_log( $matches );
							
							if ( isset( $matches[0] ) ) {
								$fullMatch = isset( $matches[0] ) ? $matches[0] : "";
								$imgNumber = isset( $matches['img_nb'] ) ? $matches['img_nb'] : "";
								$firstImgFirstLetter = isset( $matches['first_img_first_letter'] ) ? $matches['first_img_first_letter'] : "";
								$firstImgSecondLetter = isset( $matches['first_img_second_letter'] ) ? $matches['first_img_second_letter'] : "";
								// $letterEnd = isset( $matches[4] ) ? $matches[4] : "";
								InDesignHTML2Post_log( 
									"Legend analysis : $fullMatch / $imgNumber / $firstImgFirstLetter / $firstImgSecondLetter / $letterEnd" );
								$figureNumber = $imgNumber;
								InDesignHTML2Post_log( "$fullMatch | $imgNumber | $firstImgFirstLetter | $letterEnd" );
								if ( ! empty( $firstImgFirstLetter ) ) {
									if ( empty( $firstImgSecondLetter ) ) {
										// $figureNumber = $imgNumber . $firstImgFirstLetter;
										// $figureNumber = $imgNumber;
										// $currentNodeLegendArray[][$firstImgFirstLetter] = array('legend' =>
										// $maybeLegend->textContent, 'img_number' => $imgNumber);
										$newItem = array('legend' => $maybeLegend->textContent, 'img_number' => $imgNumber,'image_letter' => $firstImgFirstLetter);
										$currentNodeLegendArray[] = $newItem;
										/*
										$currentNodeLegendArray[$imgNumber][$firstImgFirstLetter]['legend'] = $maybeLegend->textContent;
										$currentNodeLegendArray[$imgNumber][$firstImgFirstLetter]['img_number'] = $imgNumber;*/
									} else {
										// FIXME should add multiple legends for each image letter
										// $figureNumber = $imgNumber . $firstImgFirstLetter . $firstImgSecondLetter;
										// $figureNumber = $imgNumber;
										$letters = $this->buildNumChain( 
											array( $firstImgFirstLetter, $firstImgSecondLetter ) );
										foreach ( $letters as $letter ) {
											
											$newItem = array('legend' => $maybeLegend->textContent, 'img_number' => $imgNumber,'image_letter' => $letter);
											$currentNodeLegendArray[] = $newItem;
											/*
											$currentNodeLegendArray[$imgNumber][$letter]['legend'] = $maybeLegend->textContent;
											$currentNodeLegendArray[$imgNumber][$letter]['img_number'] = $imgNumber;*/
										}
										// $currentNodeLegendArray[] = $maybeLegend->textContent;
									}
								} else {
									// $figureNumber = $imgNumber;
									$newItem = array('legend' => $maybeLegend->textContent, 'img_number' => $imgNumber,'image_letter' => 'no_letter');
									$currentNodeLegendArray[] = $newItem;
									/*
									$currentNodeLegendArray[$imgNumber]["no_letter"]['legend'] = $maybeLegend->textContent;
									$currentNodeLegendArray[$imgNumber]["no_letter"]['img_number'] = $imgNumber;*/
								}
								// InDesignHTML2Post_log($currentNodeLegendArray[$imgNumber]);
							} else {
								InDesignHTML2Post_log( "*************************************" );
								$warnMsg = "WARNING::Does this legend respect figure names normalization ? " .
									$maybeLegend->textContent;
								InDesignHTML2Post_log( $warnMsg );
								
								if ( is_numeric( $maybeLegend->textContent ) ) {
									InDesignHTML2Post_log( "Legend seems to be numeric, get it as figure number" );
									$figureNumber = intval( $maybeLegend->textContent );
									$newItem = array('legend' => $maybeLegend->textContent, 'img_number' => $figureNumber,'image_letter' => 'no_letter');
									$currentNodeLegendArray[] = $newItem;
									/*
									$currentNodeLegendArray[$figureNumber]["no_letter"]['legend'] = $maybeLegend->textContent;
									$currentNodeLegendArray[$figureNumber]["no_letter"]['img_number'] = $figureNumber;*/
								} else {
									$figureNumber = - 1;
									$this->displayHTMLStatusOfCmd( 'Legend regex', $warnMsg, 1 );
								}
								// continue;
							}
							
							if ( $figureNumber > 0 ) {
								$foundMsg = '<br/>' . $fullMatch . " fig number found in legend : " .
									$maybeLegend->textContent;
							} else {
								$foundMsg = '<br/>' . $fullMatch . " fig number NOT found in legend : " .
									$maybeLegend->textContent;
							}
							InDesignHTML2Post_log( $foundMsg );
							$request_answer .= $foundMsg;
							
							$maybeLegend->nodeValue = "";
						}
					}
					InDesignHTML2Post_log( 
						"=== All legends until now (current node " . $imgSize . " / " . $pSize . " :) ===" );
					InDesignHTML2Post_log( $currentNodeLegendArray );
					
					$currentNodeNbOfImages = count( $currentNodeImgArray );
					$leaves = 0;
					array_walk_recursive( 
						$currentNodeLegendArray,
						function () use (&$leaves ) {
							$leaves++;
						} );
					$currentNodeNbOfLegends = $leaves; // count( $currentNodeLegendArray, COUNT_RECURSIVE );
					if ( $currentNodeNbOfImages == $currentNodeNbOfLegends ) {
						InDesignHTML2Post_log( 
							$currentNodeNbOfImages . " nb of images and legends matches : " . $currentNodeNbOfLegends );
					} else {
						$idx = 0;
						InDesignHTML2Post_log( 
							"WARN::Nb of images ($currentNodeNbOfImages) and legends ($currentNodeNbOfLegends) DOES NOT match" );
					}
					
					$imgArrayIdx = 0;
					$previousLegend = '';
					// add, image file names, replicate legends if associated with multiple image numbers
					foreach ( $currentNodeLegendArray as $key => $image_infos ) {
						/*
						foreach ( $imgLetters as $imgLetter => $imgletterDatas ) {
							$img_number = $imgletterDatas['img_number'];*/
							InDesignHTML2Post_log( 
								"Process img idx: " . $figureNumber . " / letter: " . $imgLetter . ' / img_number: ' .
								$img_number );
							InDesignHTML2Post_log($image_infos);
							if ( isset( $image_infos['legend'] ) ) {
								$currentLegend = $image_infos['legend'];
								$currentImgName = $currentNodeImgArray[$imgArrayIdx++];
								InDesignHTML2Post_log( 
									"Process img " . $currentImgName . " and legend " . $currentLegend );
								
								$previousLegend = $currentLegend;
							} else {
								InDesignHTML2Post_log( "No legend set for $figureNumber " );
								InDesignHTML2Post_log( $imgletterDatas );
								$currentLegend = $previousLegend;
							}
							$imagesArray[] = array( 
								'legend' => $currentLegend,
								'filename' => $currentImgName,
								'image_number' => $image_infos['img_number'],
								'image_letter' => $image_infos['image_letter'] );
							
							$images_legends[$currentImgName] = $currentLegend;
						//}
					}
					
					InDesignHTML2Post_log( count( $imagesArray ) . " image legends retrieved" );
					
					InDesignHTML2Post_log( $images_legends );
					InDesignHTML2Post_log( $imagesArray );
				}
				
				// FIXME : need to remove all nodes from content
				$node->parentNode->removeChild( $node );
			}
			
			return array( 
				"html" => $doc->saveHTML(),
				"images_datas" => $imagesArray,
				"images_legends" => $images_legends );
		}

		function createPostFromInDesignHtmlExportedFile( $file, $status, $post_type_to_create ) {
			$absFilename = $file['tmp_name'];
			InDesignHTML2Post_log( "Start creating post from HTML " . $absFilename );
			
			$contentOfHtml = file_get_contents( $absFilename );
			$tags_to_replace = array();
			if ( $this->indesign2post_settings ) {
				$tags_to_keep = $this->indesign2post_settings->getOptions()['tags_to_keep'];
				$toReplaceStr = $this->indesign2post_settings->getOptions()['tags_to_replace'];
				$postTitleTag = $this->indesign2post_settings->getOptions()['title_tag'];
				$postFeaturedImgTag = $this->indesign2post_settings->getOptions()['featured_img_class_tag'];
				$jsonTagClassToACFFieldId = $this->indesign2post_settings->getOptions()['tag_class_to_acf'];
				$imagesBloc = $this->indesign2post_settings->getOptions()['images_block_tag'];
				$legend = $this->indesign2post_settings->getOptions()['legend_class_tag'];
				$figureCall = $this->indesign2post_settings->getOptions()['figure_call_class_tag'];
				
				$tags_to_replace = explode( ',', $toReplaceStr );
				InDesignHTML2Post_log( "replace: " . $toReplaceStr );
				InDesignHTML2Post_log( "keep: " . $tags_to_keep );
				InDesignHTML2Post_log( "ACF map: " . $jsonTagClassToACFFieldId );
				
				$request_answer .= 'settings';
				$optionsMsg = implode( '<br/>', $this->indesign2post_settings->getOptions() );
				$request_answer .= $optionsMsg;
				InDesignHTML2Post_log( $optionsMsg );
			}
			
			// build images array
			$imagesArray = array();
			
			$doc = new DOMDocument();
			$doc->loadHTML( $contentOfHtml );
			$finder = new DomXPath( $doc );
			$imgBlocClassname = $imagesBloc;
			
			InDesignHTML2Post_log( "========================================" );
			InDesignHTML2Post_log( "=====  Find all images and legends =====" );
			InDesignHTML2Post_log( "========================================" );
			
			$imagesAllDatas = $this->findAllImagesFilenamesAndLegends( $doc, $finder, $imgBlocClassname, $legend );
			$images_legends = $imagesAllDatas['images_legends'];
			$imagesArray = $imagesAllDatas['images_datas'];
			$contentOfHtml = $imagesAllDatas['html'];
			
			InDesignHTML2Post_log( "========================================" );
			InDesignHTML2Post_log( "All images and legends found:" );
			InDesignHTML2Post_log( $imagesArray );
			InDesignHTML2Post_log( "----------------------------------------" );
			InDesignHTML2Post_log( $images_legends );
			InDesignHTML2Post_log( "========================================" );
			// get title content
			$post_title = $this->getTextBetweenTags( $contentOfHtml, $postTitleTag, $tags_to_replace );
			InDesignHTML2Post_log( "inside tag " . $postTitleTag . " : " );
			InDesignHTML2Post_log( $post_title );
			
			// get post featured image
			// FIXME not looking for a tag, bug a class!
			/*
			 * <div id="_idContainer004" class="imageune _idGenObjectStyle-Disabled">
			 * <img class="_idGenObjectAttribute-1" src="villeroy3-web-resources/image/1.jpg" alt="" />
			 * </div>
			 */
			
			$dom = new \DOMDocument();
			$dom->loadHTML( $contentOfHtml );
			
			$xpath = new DOMXpath( $dom );
			$featuredSrc = '';
			InDesignHTML2Post_log( "featured image tag " . $postFeaturedImgTag . " : " );
			if ( ! empty( $postFeaturedImgTag ) ) {
				$nodeList = $xpath->query( "//*[contains(@class, '$postFeaturedImgTag')]" ); // instance of DOMNodeList
				
				foreach ( $nodeList as $tag ) {
					// $request_answer .= $dom->saveXML($tag);
					InDesignHTML2Post_log( $dom->saveXML( $tag ) );
					$document = new \DOMDocument();
					$document->loadHTML( $dom->saveXML( $tag ) );
					$imgTags = $document->getElementsByTagName( 'img' );
					
					foreach ( $imgTags as $img ) {
						$featuredSrc = $img->getAttribute( 'src' );
						InDesignHTML2Post_log( "Featured img set to source : " . $featuredSrc );
					}
				}
			} else {
				InDesignHTML2Post_log( "No featured img class provided, will set first image found as featured" );
			}
			
			$retrievedTagContentArray = array();
			$classesToSearch = array_keys( json_decode( $jsonTagClassToACFFieldId, true ) );
			InDesignHTML2Post_log( $classesToSearch );
			foreach ( $classesToSearch as $classToSearch ) {
				
				preg_match_all( "'<p class=\"" . $classToSearch . "\">(.*?)</p>'si", $contentOfHtml, $match );
				
				if ( isset( $match[1] ) && ! empty( $match[1] ) ) {
					
					$multipleResult = '';
					foreach ( $match[1] as $val ) {
						$multipleResult .= '<p>' . strip_tags( $val ) . '</p>';
					}
					$multipleResult .= '';
					$retrievedTagContentArray[$classToSearch] = $multipleResult;
				}
				
				preg_match_all( "'<li class=\"" . $classToSearch . "\">(.*?)</li>'si", $contentOfHtml, $match );
				if ( ! empty( $match[1] ) ) {
					$multipleResult = '<ol>';
					foreach ( $match[1] as $val ) {
						
						$multipleResult .= '<li>' . strip_tags( $val ) . '</li>';
					}
					$multipleResult .= '<ol>';
					$retrievedTagContentArray[$classToSearch] = $multipleResult;
				}
				
				// then remove from text body
				$contentOfHtml = preg_replace( 
					"'<p class=\"" . $classToSearch . "\">(.*?)</p>'si",
					"",
					$contentOfHtml,
					- 1,
					$removeCount );
				// preg_replace ( mixed $pattern , mixed $replacement , mixed $subject
				$contentOfHtml = preg_replace( 
					"'<li class=\"" . $classToSearch . "\">(.*?)</li>'si",
					"",
					$contentOfHtml,
					- 1,
					$removeCount2 );
				
				// remove legends from body
				// $imgBlocClassname
				/*
				 * $contentOfHtml = preg_replace(
				 * "'<div class=\"" . $imgBlocClassname . "\">(.*?)</div>'si",
				 * "",
				 * $contentOfHtml,
				 * - 1,
				 * $removeCount3 );
				 */
				
				$removed = $removeCount + $removeCount2;
				InDesignHTML2Post_log( "removed " . $removed . " elements from text body" );
			}
			
			InDesignHTML2Post_log( "content size is : " . strlen( $contentOfHtml ) );
			
			// replace tags by
			foreach ( $tags_to_replace as $tag ) {
				$contentOfHtml = str_replace( $tag, " ", $contentOfHtml );
			}
			
			InDesignHTML2Post_log( "content size is : " . strlen( $contentOfHtml ) );
			
			// remove all tags except
			$text_without_html_tags = strip_tags( $contentOfHtml, $tags_to_keep );
			
			$text_without_html_tags = $this->cleanHtmlCharactersFromString( $text_without_html_tags );
			
			
			
			
			$inputFileName = basename( $file['name'] );
			$inputFileNameWithoutExt = preg_replace( '/\\.[^.\\s]{3,4}$/', '', $inputFileName );
			
			if ( empty( $post_title ) ) {
				
				$post_title = $inputFileNameWithoutExt;
			}
			
			InDesignHTML2Post_log( "Title will be " . $post_title );
			
			$user_id = get_current_user_id();
			InDesignHTML2Post_log( "Current user id: " . $user_id );
			
			if ( ! is_admin() ) {
				$category_ids = [];
				$slugs = [ 'demo', 'html2post' ];
				foreach ( $slugs as $slug ) {
					$idObj = get_category_by_slug( $slug );
					if ( $idObj instanceof WP_Term ) {
						$category_ids[] = $idObj->term_id;
					}
				}
			} else {
				$category_ids = [ 1 ];
			}
			
			$postarr = array( 
				'post_author' => $user_id,
				'post_content' => $text_without_html_tags,
				'post_content_filtered' => '',
				'post_title' => $post_title,
				'post_excerpt' => '',
				'post_status' => $status,
				'post_type' => $post_type_to_create,
				'post_category' => $category_ids,
				'comment_status' => '',
				'ping_status' => '',
				'post_password' => '',
				'to_ping' => '',
				'pinged' => '',
				'post_parent' => 0,
				'menu_order' => 0,
				'guid' => '',
				'import_id' => 0,
				'context' => '' );
						
			$inserted = wp_insert_post( $postarr, true );
			if ( is_wp_error( $inserted ) ) {
				$errMsg = "Error creating post: " . $inserted->get_error_message();
				InDesignHTML2Post_log( $errMsg );
				$this->displayHTMLStatusOfCmd( '', $errMsg, 1 );
			} else {
				$attachedImagesArray = array();
				$sucessMsg = '<span style="color:green;">Post successfully inserted with ID ' . $inserted . " :)</span>";
				InDesignHTML2Post_log( $sucessMsg );
				
				$this->displayHTMLStatusOfCmd( '', $sucessMsg, 0 );
				
				if ( ! empty( $jsonTagClassToACFFieldId ) ) {
					$associativeArray = json_decode( $jsonTagClassToACFFieldId, true );
					InDesignHTML2Post_log( $associativeArray );
					foreach ( $associativeArray as $classKey => $acfFieldId ) {
						InDesignHTML2Post_log( $classKey . " -> " . $acfFieldId );
						if ( ! isset( $retrievedTagContentArray[$classKey] ) ) {
							InDesignHTML2Post_log( "ACF::WARN::Not set" );
							continue;
						}
						$value = $retrievedTagContentArray[$classKey];
						
						InDesignHTML2Post_log( 
							"ACF::update " . $acfFieldId . " with " . strlen( $value ) . ' on post ' . $inserted );
						update_field( $acfFieldId, $value, $inserted );
						InDesignHTML2Post_log( $value );
					}
				}
			}
			
			return array( 
				'featured_img' => $featuredSrc,
				'inserted' => $inserted,
				'post_title' => $post_title,
				'images_infos' => $imagesArray,
				'images_legends' => $images_legends );
		}

		function displayHTMLStatusOfCmd( $cmd, $output, $ret_val, $additionnalMesg = '' ) {
			$show_status = '<span style="color:grey;font-size:0.7rem;">' . $cmd . '</span>';
			$show_status .= '<span ';
			if ( $ret_val == 0 ) {
				$color = 'green';
			} else {
				$color = 'red';
			}
			$show_status .= 'style="color:' . $color . '">';
			if ( empty( $output ) ) {
				$show_status .= 'Ok';
			} else {
				$show_status .= '<ul><li>';
				if ( is_array( $output ) ) {
					$show_status .= implode( '</li><li>', $output );
				} else {
					$show_status .= $output;
				}
				$show_status .= '</li></ul>';
			}
			
			if ( ! empty( $additionnalMesg ) ) {
				$show_status .= '' . $additionnalMesg;
			}
			
			$show_status .= '</span>';
			if ( is_array( $output ) ) {
				InDesignHTML2Post_log( implode( ' | ', $output ) );
			} else {
				InDesignHTML2Post_log( $output );
			}
			return $show_status;
		}
	}
}
$obj = new InDesignHTML2Post();
