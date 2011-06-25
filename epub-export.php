<?php

/* 
	Plugin Name: ePub Export
	Plugin URI: 
	Description: Export individual posts in ePub format.
	Author: Martin Fenner
	Version: 1.1.1
	Author URI: http://blogs.plos.org/mfenner
	
	GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
	
	This plugin uses the PHP ePub class: http://www.phpclasses.org/package/6115
	This plugin uses the htmLawed library: http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed/
*/

// Require the ePub PHP class by Asbjorn Grandt 
include_once("epub/EPub.php");
// Require the htmLawed library by Santosh Patnaik 
include_once("htmLawed/htmLawed.php");

// Main function to generate ePub
function ee_add_epub( $post_ID ) {
	$the_post = get_post( $post_ID ); 
	
	// Get post properties
	$title = $the_post->post_title;
	$description = $the_post->post_excerpt;
	$chapterData = apply_filters('the_content', $the_post->post_content);
	$chapterData = str_replace(']]>', ']]&gt;', $chapterData);
	$author_ID = $the_post->post_author;
	$author = get_the_author_meta( 'display_name', $author_ID );
	$last_name = get_the_author_meta( 'last_name', $author_ID );
	$first_name = get_the_author_meta( 'first_name', $author_ID );
	$date = $the_post->post_date;
	$identifier = get_permalink( $post_ID );
	$sourceURL = $identifier;
	
	// Get blog properties
	$language = get_bloginfo('language');
	$publisher_name = get_bloginfo('name');
	$publisher_url = get_bloginfo('url');
	$uploads_url = site_url('/wp-content/uploads/');
	$rightsText = get_option('ee_rights');
	$subject = "Blog";
	
	// Clean up HTML
	$config["valid_xhtml"] = 1; 
	// Leave CDATA sections untouched
	$config["cdata"] = 3;
	$parsed_content = htmLawed($chapterData, $config); 
	$parsed_description = htmLawed($description, $config);
	
	// Create ePub object
	$book = new ePub();
	
	// Add images
	$parsed_content = $book->extractImages($parsed_content);
	
	// Add CSS. Use epub.css from current theme, epub.css from plugin as fallback
	if ( $epub_file = locate_template( array('epub.css') ) ) {
	  $fname =  $epub_file;
	}
	else if (file_exists( dirname( __FILE__ ) . '/epub.css' ) ) {
	  $fname = dirname( __FILE__ ) . '/epub.css';
	}
	else {
	  $fname = ABSPATH . 'wp-content/plugins/epub-export/epub.css';
	}
	if (file_exists($fname))
	  $cssData = file_get_contents($fname);
	  $book->addCSSFile("epub.css", "css1", $cssData);
	
	// Add cover page. Requires gd library with freetype extension. Title page as fallback.
	$book->addCover($title, $author, $publisher_name);
	
	// Add content of post
	$chapterName = "Content";
	$fileName = "text.xhtml";
	$book->addChapter($chapterName, $fileName, $parsed_content, true);
	
	// Set required properties
	$book->setTitle($title);
	$book->setLanguage($language);
	$book->setIdentifier($identifier, "URI");
	
	// Set optional properties
	$book->setDescription($parsed_description);
	$book->setAuthor($author, $last_name . ", " . $first_name);
	$book->setPublisher($publisher_name, $publisher_url);
	$book->setSubject($subject);	
	$book->setRights($rightsText);
	$book->setSourceURL($sourceURL);
	
	// Save post as ePub, overwrite previous version if it exists
	$book->finalize();
	$content = $book->getBook();
	$uploads = wp_upload_dir();
	$fname = $uploads[path] . "/" . $post_ID . ".epub";
	$result = file_put_contents($fname, $content);
	return $result;
}
// Different hooks for posts, pages and custom post types
add_action('publish_post', 'ee_add_epub');
add_action('publish_page', 'ee_add_epub');
add_action('publish_article', 'ee_add_epub');

// Delete ePub when post is deleted
function ee_delete_epub( $post_ID ) {
	$uploads = wp_upload_dir();
	$fname = $uploads[path] . "/" . $post_ID . ".epub";
	if( file_exists( $fname ) )
		unlink( $fname );
	return $post_ID;
}
add_action('deleted_post', 'ee_delete_epub');

// Add admin menu --
if (is_admin()) {
  add_action('admin_menu', 'ee_fields_menu');
	add_action('admin_init', 'ee_fields_register');
}

// Add whitelist options --
function ee_fields_register() {
	register_setting('ee_fields_optiongroup', 'ee_add_link');
	register_setting('ee_fields_optiongroup', 'ee_rights');
}

// Admin menu page details --
function ee_fields_menu() {
	add_management_page('ePub Export', 'ePub Export', 8, 'ee_fields', 'ee_fields_options');
}

// Add actual menu page --
function ee_fields_options() { ?>
	<div class="wrap">
		<div id="icon-tools" class="icon32" ><br/></div>
		<h2>ePub Export</h2>
		
		<form method="post" action="options.php">
		<?php settings_fields('ee_fields_optiongroup'); ?>
				
		<table class="form-table">
			<tr valign="top">
			  <td>
			    <p>Link to ePub</p>
			  </td>
				<td>
					<p><input type="checkbox" name="ee_add_link" value="1" <?php echo (get_option('ee_add_link')) ? 'checked="checked"' : ''; ?> id="ee_add_link" /> Automatically add ePub link to posts and pages</p>
				</td>
			</tr>
			<tr valign="top">
			  <td>
			    <p>License used for ePub</p>
			  </td>
				<td>
					<p><textarea cols="50" rows="5" name="ee_rights" id="ee_rights"><?php echo (get_option('ee_rights')); ?></textarea></p>
				</td>
			</tr>
		</table>
								
		<p class="submit">
			<input type="submit" class="button-primary" value="Save Changes" />
		</p>
		</form>
	</div>
  <?php 
}

// Auto add epub link if option enabled and we are on a single post, page or article and epub exists
function ee_add_epub_link( $the_content ) {
	if( !is_single() && !is_page() )
	return $the_content;
	// Get ID if post or page
  global $post;
  $post_ID = $post->ID;
	$uploads = wp_upload_dir();
	$fname = $uploads[path] . "/" . $post_ID . ".epub";
	if (!file_exists($fname)) return $the_content;
  // Display link if ePub for post or page exists
  $epublink = $uploads[url] . "/" . $post_ID . ".epub";
	$the_content .= "<span class=\"epub_link\"><a href=\"$epublink\"><img src=\"/wp-content/plugins/epub-export/epub.gif\"> Download as ePub</a></span>";
	return $the_content;
}
if( get_option('ee_add_link') ) {
	add_filter( 'the_content', 'ee_add_epub_link', 10000 );
}

?>