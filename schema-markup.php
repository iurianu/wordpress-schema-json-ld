<?php

/*	

Author: Iulian Andriescu (https://iurianu.rocks)

This file holds all the schema.org markup logic.

By using PHP, and custom wordpress functions, 
the script reads the content of the page, and 
creates a json-ld script, which is added in the 
footer of the wordpress page

The script creates separate json-ld for every 
page, populated with the provided information.

The json-ld will be added both on the existent, 
and on the new pages, and posts.

IMPLEMENTATION

-- OPTION 1 (recommended) --
 
Create a Child Theme, and add the entire script in its functions.php file

-- OPTION 2 --

Upload the file via FTP
Path: http://website.com/wp-content/themes/[Your-Theme]/inc/schema-markup.php
Register: Add one of these lines to your theme's functions.php file (follow the code structure): 

 	require_once( 'inc/schema-markup.php' );					
 		or
 	require_once get_template_directory() . '/inc/schema_markup.php';

-- OPTION 3 --

Install a Custom Code Plugin, and create a php snippet to add the script

*/


/*******************************************************************************/
/* FUNCTIONS USED TO CREATE THE CODE LOGIC, AND VALIDATION OF THE LD+JSON FILE */
/*******************************************************************************/

// Page Publisher
// outputs publisher information in json format
function add_page_publisher(){
	global $post;
	function publisher_information() {
		return json_encode(
			[
			"publisher" =>[
				"@type" => "Organization",
				"name"=> 'kazenokodomo',
				"url" => 'https://www.facebook.com/kazenokodomo',
				"logo" => [
					"@type" => "ImageObject",
					"url" => 'https://www.facebook.com/kazenokodomo',
					"image" => 'https://scontent.fotp3-2.fna.fbcdn.net/v/t31.18172-1/11229375_906232166124459_4933964724193381109_o.jpg?stp=dst-jpg_p148x148&_nc_cat=100&ccb=1-5&_nc_sid=1eb0c7&_nc_eui2=AeHEUelwVZxwuVPy8_I5ePV31sxK7a9whJDWzErtr3CEkLDLIV0pVSj2FQYJGATbarQ0gsmUssZ9ezrPn1-PjTKO&_nc_ohc=7aIHvycpMJ4AX_n3JUx&tn=0x_n4hSVDwry3554&_nc_ht=scontent.fotp3-2.fna&oh=00_AT8HbGiOm58aomOMYZmZDFG4rxaL-gKgRI5dPlhvHVTZIQ&oe=626C3B23',
					"width" => '150',
					"height" => '150'
					]
				]
			]
		);	
	}	
	echo process_data_json(publisher_information()) . ',';
}

// Schema Publisher
// outputs schema creator information in json format
function add_schema_publisher(){
	global $post;
	function sd_publisher_information() {
		return json_encode(
			[
			"sdPublisher" =>[
				"@type" => "Person",
				"name"=> "Iulian Andriescu",
				"url" => "https://iurianu.rocks"
			]]
		);	
	}	
	echo process_data_json(sd_publisher_information()) . ',';
}

/**
 * extract_tags()
 * Extract specific HTML tags and their attributes from a string.
 *
 * You can either specify one tag, an array of tag names, or a regular expression that matches the tag name(s). 
 * If multiple tags are specified you must also set the $selfclosing parameter and it must be the same for 
 * all specified tags (so you can't extract both normal and self-closing tags in one go).
 * 
 * The function returns a numerically indexed array of extracted tags. Each entry is an associative array
 * with these keys :
 *  tag_name    - the name of the extracted tag, e.g. "a" or "img".
 *  offset      - the numberic offset of the first character of the tag within the HTML source.
 *  contents    - the inner HTML of the tag. This is always empty for self-closing tags.
 *  attributes  - a name -> value array of the tag's attributes, or an empty array if the tag has none.
 *  full_tag    - the entire matched tag, e.g. '<a href="http://example.com">example.com</a>'. This key 
 *                will only be present if you set $return_the_entire_tag to true.      
 *
 * @param string $html The HTML code to search for tags.
 * @param string|array $tag The tag(s) to extract.                           
 * @param bool $selfclosing Whether the tag is self-closing or not. Setting it to null will force the script to try and make an educated guess. 
 * @param bool $return_the_entire_tag Return the entire matched tag in 'full_tag' key of the results array.  
 * @param string $charset The character set of the HTML code. Defaults to ISO-8859-1.
 *
 * @return array An array of extracted tags, or an empty array if no matching tags were found. 
 */


// CAUTION: DO NOT TOUCH THE `extract_tags` FUNCTION //

function extract_tags( $html, $tag, $selfclosing = null, $return_the_entire_tag = false, $charset = 'UTF-8' ){
     
    if ( is_array($tag) ){
        $tag = implode('|', $tag);
    }
     
    //If the user didn't specify if $tag is a self-closing tag we try to auto-detect it
    //by checking against a list of known self-closing tags.
    $selfclosing_tags = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta', 'col', 'param' );
    if ( is_null($selfclosing) ){
        $selfclosing = in_array( $tag, $selfclosing_tags );
    }
     
    //The regexp is different for normal and self-closing tags because I can't figure out 
    //how to make a sufficiently robust unified one.
    if ( $selfclosing ){
        $tag_pattern = 
            '@<(?P<tag>'.$tag.')           # <tag
            (?P<attributes>\s[^>]+)?       # attributes, if any
            \s*/?>                   # /> or just >, being lenient here 
            @xsi';
    } else {
        $tag_pattern = 
            '@<(?P<tag>'.$tag.')           # <tag
            (?P<attributes>\s[^>]+)?       # attributes, if any
            \s*>                 # >
            (?P<contents>.*?)         # tag contents
            </(?P=tag)>               # the closing </tag>
            @xsi';
    }
     
    $attribute_pattern = 
        '@
        (?P<name>\w+)                         # attribute name
        \s*=\s*
        (
            (?P<quote>[\"\'])(?P<value_quoted>.*?)(?P=quote)    # a quoted value
            |                           # or
            (?P<value_unquoted>[^\s"\']+?)(?:\s+|$)           # an unquoted value (terminated by whitespace or EOF) 
        )
        @xsi';
 
    //Find all tags 
    if ( !preg_match_all($tag_pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ){
        //Return an empty array if we didn't find anything
        return array();
    }
     
    $tags = array();
    foreach ($matches as $match){
         
        //Parse tag attributes, if any
        $attributes = array();
        if ( !empty($match['attributes'][0]) ){ 
             
            if ( preg_match_all( $attribute_pattern, $match['attributes'][0], $attribute_data, PREG_SET_ORDER ) ){
                //Turn the attribute data into a name->value array
                foreach($attribute_data as $attr){
                    if( !empty($attr['value_quoted']) ){
                        $value = $attr['value_quoted'];
                    } else if( !empty($attr['value_unquoted']) ){
                        $value = $attr['value_unquoted'];
                    } else {
                        $value = '';
                    }
                     
                    //Passing the value through html_entity_decode is handy when you want
                    //to extract link URLs or something like that. You might want to remove
                    //or modify this call if it doesn't fit your situation.
                    $value = html_entity_decode( $value, ENT_QUOTES, $charset );
                     
                    $attributes[$attr['name']] = $value;
                }
            }
             
        }
         
        $tag = array(
            'tag_name' => $match['tag'][0],
            'offset' => $match[0][1], 
            'contents' => !empty($match['contents'])?$match['contents'][0]:'', //empty for self-closing tags
            'attributes' => $attributes, 
        );
        if ( $return_the_entire_tag ){
            $tag['full_tag'] = $match[0][0];            
        }
          
        $tags[] = $tag;
    }
     
    return $tags;
}

// Returns a part of the slug :: useful when necessary
// $nice_name :: the $name_slug with the first letter capitalized
function get_slug_name() {
	global $post; 
	$post_slug=$post->post_name; 
	$name_slug=substr_replace($post_slug,"",-3);
	$nice_name=ucfirst($name_slug);
	echo $nice_name;
} 


// Targets the children of one specific page
function is_child( $page_id_or_slug ) { 
	global $post; 
		$page = get_page_by_path($page_id_or_slug);
		$page_id_or_slug = $page->ID;
	if(is_page() && $post->post_parent == $page_id_or_slug ) {
       		return true;
	} else { 
       		return false; 
	}
}

// checks if a plugin is active or not
// you need to use the path to the plugin file
// i.e. check_plugins('plugins/wordpress-seo/wp-seo.php')
function check_plugins($plugin){
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if (is_plugin_active($plugin)) {
		return false;
	} else {
		return true;
	}
}

// check if Yoast SEO is installed
function is_yoast_installed(){
	if (check_plugins('plugins/wordpress-seo/wp-seo.php')) {
		return true;
	}
}

// check if Woocommerce is installed
function is_woocommerce_installed(){
	if (check_plugins('plugins/woocommerce/woocommerce.php')) {
		return true;
	}
}

// Extracts the information from the attached image
function get_image_attachment_for_schema()
{
	global $post;

	$html = apply_filters('the_content', get_post_field('post_content', $post->ID));	
	$nodes = extract_tags( $html, 'img' );
	
	if(!count($nodes)) {
		return false;
	}

	$images = [];
	foreach ($nodes as $image) {    	
    	if(!array_key_exists('attributes', $image)) {
    		continue;    	
    	}
        	
    	$images[] = [
    		'@type' => 'ImageObject',
    		'width' => $image[attributes]['width'],
    		'height' => $image[attributes]['height'],
    		'image' => $image[attributes]['src'],
    		'url' => get_the_permalink(),    	
    	];
    }	
	
	return $images;
}

// find the images in the current post/page
// if there are no images in the content, returns the post thumbnail
// if there's no post thumbnail selected, returns the header image
// if there is no header image, returns the website logo
function add_post_image(){

	global $post; 

	function image_data() { 
	if ( $image_data = get_image_attachment_for_schema())  {
		// attachment: 	class / src / alt / width / height (srcset / sizes)    	
			return json_encode(["image" => $image_data]);	

	    } elseif ( has_post_thumbnail() ) {
		// thumbnail: 	width / height / src 	
	    	$image_data 	= wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), "wp_post_image" ); 
			$image_width 	= $image_data[1];		// width
			$image_height 	= $image_data[2];		// height
	    	$image_source 	= $image_data[0];		// src			

		} elseif ( has_header_image() ) {
		// header image	
			$image_data 	= get_header_image( $post->ID );
			$image_source 	= $image_data[0];		// src
			$image_width 	= $image_data[1];		// width
			$image_height 	= $image_data[2];		// height

	    } else {
	    	// Use the website Logo as Featured Image
			$image_source 	= "https://scontent.fotp3-2.fna.fbcdn.net/v/t31.18172-1/11229375_906232166124459_4933964724193381109_o.jpg?stp=dst-jpg_p148x148&_nc_cat=100&ccb=1-5&_nc_sid=1eb0c7&_nc_eui2=AeHEUelwVZxwuVPy8_I5ePV31sxK7a9whJDWzErtr3CEkLDLIV0pVSj2FQYJGATbarQ0gsmUssZ9ezrPn1-PjTKO&_nc_ohc=7aIHvycpMJ4AX_n3JUx&tn=0x_n4hSVDwry3554&_nc_ht=scontent.fotp3-2.fna&oh=00_AT8HbGiOm58aomOMYZmZDFG4rxaL-gKgRI5dPlhvHVTZIQ&oe=626C3B23";
			$image_width 	= "150";
			$image_height 	= "150";
	    }
		return json_encode(
			[
			"image" =>[
				"@type" => "ImageObject",
				"image"=> $image_source,
				"width" => $image_width,
				"height" => $image_height,
				"url" => get_the_permalink()
				]
			]
		);
	}
	echo process_data_json(image_data());
}

// returns all the media present in the content
function add_post_media() {
	global $post;

	$videos = get_post_videos();
	$iframes = get_content_iframes();
	$audios = get_post_audios();
	$allInfo = [];

	if(!$videos && !$iframes && !$audios) {
		return;
	}

	$allInfo = array_merge($videos, $iframes, $audios);	
		
	echo process_data_json(json_encode([
		"associatedMedia" => $allInfo
	])) . ',';
}

function get_post_audios() {
	global $post;
	
	$html = apply_filters('the_content', get_post_field('post_content', $post->ID));	
	$nodes = extract_tags( $html, 'audio' );

	if(!count($nodes)) {
		return false;
	}

	$audios = [];
	foreach ($nodes as $audio) {   
		if(!array_key_exists('attributes', $audio)) {
    		continue;    	
    	}
    	$aux = extract_tags($audio['contents'], 'a');

		$audioSource = $aux[0]['attributes']['href'];
    	$audios[] = [
    		'@type' => 'AudioObject',
    		'url' => $aux[0]['attributes']['href'],
    		'name' => get_file_name($audioSource)
    	];
        	
	}
	return $audios;
}

function get_content_iframes()
{
	global $post;
	
	$html = apply_filters('the_content', get_post_field('post_content', $post->ID));	
	$nodes = extract_tags( $html, 'iframe' );
	
	if(!count($nodes)) {
		return false;
	}

	$iframes = [];
	foreach ($nodes as $iframe) {    	
    	if(!array_key_exists('attributes', $iframe)) {
    		continue;    	
    	}
        	
    	$temp = [
    		'@type' => 'MediaObject',
    		'width' => $iframe['attributes']['width'],
    		'height' => $iframe['attributes']['height'],
    		"uploadDate" => get_the_date(),
			"thumbnailUrl" => "https://pixabay.com/photo-158157/",
    		'embedUrl' => $iframe['attributes']['src']
		];
    	$iframes[] = $temp;
    }	
    
    return $iframes;    
}

function get_post_videos()
{
	global $post;
	
	$html = apply_filters('the_content', get_post_field('post_content', $post->ID));	
	$nodes = extract_tags( $html, 'video' );
	
	if(!count($nodes)) {
		return false;
	}

	$videos = [];
	foreach ($nodes as $video) {    	
    	if(!array_key_exists('attributes', $video)) {
    		continue;    	
    	}
        	
    	$aux = extract_tags($video['contents'], 'a');

    	$videoSource = $aux[0]['attributes']['href'];
    	$temp = [
    		'@type' => 'VideoObject',
    		'width' => $video[attributes]['width'],
    		'height' => $video[attributes]['height'],
    		"uploadDate" => get_the_date(),
			"thumbnailUrl" => "https://pixabay.com/photo-158157/",
    		'description' => get_file_name($videoSource),
    		'name' => get_file_name($videoSource),
    		'embedUrl' => $videoSource
		];
    	$videos[] = $temp;
    }	
    
    return $videos;    
}

// returns the file name for media present in content
function get_file_name($fileUrl) {
	$aux = preg_split('/\//', $fileUrl);
	$last = end($aux);

	$aux2 = preg_split('/\./', $last);
	unset($aux2[count($aux2)-1]);
	
	return join('.', $aux2);

	
}

// remove first and last {} from data json
function process_data_json($data) {	
	return substr($data, 1, strlen($data) - 2);
}

// temporary debug function, if necessary in development
function wp_debug($data, $color='red')
{
	echo "<div style='color:{$color};' class='debug_something'>";
	print_r($data);
	echo "</div>";
} 

// Function to create the markup for a post loop page (i.e. `Blog`)
function add_loop_posts_data()
	{
		$temp_query = $wp_query;

		$posts_info = [];
		$has_posts = false;
		while ( have_posts() ) { 
			the_post(); 
			if(is_single() || is_search()) {
				continue;
			}
			$has_posts = true;

		$image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), "wp_post_image" ); 
		$image_width 	= $image_data[1];		// width
		$image_height 	= $image_data[2];		// height
		$image_source 	= $image_data[0];		// src	

		$posts_info[] = [
			"@type" => "BlogPosting",
			"name" => get_the_title(),
			"url" => get_the_permalink(),
			"headline" => get_the_title(),
			"datePublished" => get_the_date(),
	        "dateModified" => get_the_modified_date(),	
	        "commentCount" => get_comments_number(),
			"mainEntityOfPage" => [
					"@type" => "WebPageElement",
					"@id" => get_the_permalink() . '#post-' . get_the_ID()
				],
			"image" => [
				   "@type" => "ImageObject",
				   "image" => $image_source,
				   "width" => $image_width,
				   "height" => $image_height,
				   "url" => get_the_permalink()			   
			  ],
			"author" => [
				"@type" => "Person",
				"name" => get_the_author_meta('display_name', $auth_id),
				"url" => get_the_author_url(),
				"image" => get_avatar_url( get_the_author_meta( 'ID' ), 32 ),
				"email" => get_the_author_meta('email')
			   ],
			"publisher" => [
				"@type" => "Organization",
				"name" => "Colibri Studios",
				"url" => "https://colibridesign.ro",
				"logo" => [
					"@type" => "ImageObject",
					"url" => "https://colibridesign.ro",
					"image" => "https://colibridesign.ro/ui/images/colibri%20dot.svg",
					"width" => "20",
					"height" => "20"
				]
			]
		]; 		
	}	

	$wp_query = $temp_query;
	if($has_posts) {
		$result = [ "blogPost" => $posts_info ];
		return process_data_json(json_encode($result)) . ',';
	} 
	return '';
}

// Get the main category of a post
function add_post_category(){
	$categories = get_the_category();
	foreach($categories as $category) { 
		$cat_name = $category->name;  
	}
	echo '"articleSection": "'. $cat_name .'",';		
}

// Word count might be required if Article, or BlogPosting schema is use
function add_post_word_count() {
    global $post;
    $char_list = ''; 
    $wordcount = str_word_count(strip_tags($post->post_content), 0, $char_list);
    echo '"wordCount":"'. $wordcount .'",';
}

// Return a post's tags, and mark them up as keywords
function add_post_keywords() {
    if ( is_single() || is_page() ) {
    	if ( !is_search() ) {
        	$tags   = '"keywords" : [' . strip_tags(get_the_tag_list('"','", "','"')) . '],'; 
    	}
    } else { }
    echo $tags;
}

// Extract all heading tags (h1-h6) and output their text content:
function add_post_headlines() {
	$html = apply_filters('the_content', get_post_field('post_content', $post_id));
	$nodes = extract_tags( $html, 'h\d+', false );
	$headlines;
	foreach($nodes as $node){
   		$headlines =  $headlines . '"'. strip_tags($node['contents']) . '",';
	}
	echo '"headline": ['. rtrim($headlines,",") .'],';
}

// Extract all links and output their URLs:
function add_post_links() {
	$html = apply_filters('the_content', get_post_field('post_content', $post_id));
	$nodes = extract_tags( $html, 'a' );
	$links;
	foreach($nodes as $link){
	    $links = $links . '"'. $link['attributes']['href'] . '",';
	}
	echo '"isBasedOnUrl": ['. rtrim($links,",") .'],';
}

// Extract all text in the page content:
function add_post_text() {
	global $post;
	$text = htmlentities(strip_tags(apply_filters('the_content', get_post_field('post_content', $post_id))));
	$clean_text = trim(preg_replace('/[\s\t\n\r\s]+/', ' ',$text));	
	echo '"text": "'. $clean_text .'",';
}

// Select a custom excerpt as page description
function add_page_description(){
	global $post;

	// Returns a custom excerpt from a page (set the number of words to display)
	function get_the_page_description() { 
		global $post;
		$post_description = get_bloginfo('description');
		return $post_description;
	}
		echo '"description": "'. get_the_page_description() .'",';
}	

// Set the `content` id of the page as the MainEntity
function add_main_entity(){
	$link = get_the_permalink();
	$id = get_the_ID();
	echo '"mainEntityOfPage": "'. $link .'#post-'. $id .'",';
}

function add_date_published(){
	$date = get_the_date($post->ID);
	echo '"datePublished": "'. $date . '",';
}

function add_date_modified(){
	$date = get_the_modified_date($post->ID);
	echo '"dateModified": "'. $date . '",';
}

function add_schema_date(){
	$date = get_the_modified_date($post->ID);
	echo '"sdDatePublished": "'. $date . '",';
}

function add_page_title(){
	$title = get_the_title();
	echo '"name": "'. $title .'",';
}

// function to add the schema.org/Website markup on the Home Page if Yoast is not installed
function add_website_markup(){
	global $post;
	if (is_home() || is_front_page()) {
		function schema_website_markup() {
			return json_encode(
		    	[
		    		'@context' => "http://schema.org/",
		    		'@type' => "Website",
		    		'name' => get_bloginfo('name'),
		    		'url' => get_site_url(),
		    		'potentialAction' => [
			    		"@type" => "SearchAction",
			    		'target' => get_site_url() .'/?s={search_term_string}',
			    		'query-input' => "required name=search_term_string"
		    		]
				]
			);
		}		
		echo ',{'. process_data_json(schema_website_markup()) . '}';
	}	
}

// Outputs post author information in json format
function add_post_author(){
	global $post;
	function author_information() {

		$author_name 	= get_the_author_meta('display_name', $auth_id);
		$author_gname 	= get_the_author_meta('first_name');
		$author_fname	= get_the_author_meta('last_name');
		$author_url 	= get_the_author_url();
		$author_email	= get_the_author_meta('email');
		$author_image	= get_avatar_url( get_the_author_meta( 'ID' ), 32 );

		return json_encode(
			[
			"author" =>[
				"@type" => "Person",
				"name"=> $author_name,
				"givenName" => $author_gname,
				"familyName" => $author_fname,
				"url" => $author_url,
				"email" => $author_email,
				"image" => $author_image
				]
			]
		);	
	}	
	echo process_data_json(author_information()) . ',';
}

/****************************************************************************/
/* THE MAIN FUNCTION THAT CREATES THE LD+JSON SCRIPTS FOR SCHEMA.ORG MARKUP */
/****************************************************************************/

/* 
	For single pages, you should change 
	the slug in the following script to match 
	the ones in your website 
*/

function schema_output() { 

?>

<script type="application/ld+json">
[{   
	"@context": "http://schema.org/",
<?php 
	if ( is_front_page() || is_home() ) 
		{ echo '"@type": "WebPage",'; } 
	elseif ( !is_single() && !is_page() && !is_home() && !is_front_page() && !is_search() ) 
		{ echo '"@type": "Blog",';} 
	elseif ( is_single() && !is_search() && !is_page_template('archive.php')) 
		{ echo '"@type": "Blogposting",';} 
	elseif ( is_search() && is_page_template('archive.php') ) 
		{ echo '"@type": "SearchResultsPage",';} 
	elseif ( is_page('contact') || is_page('contact-us') ) 
		{ echo '"@type": "ContactPage",';} 
	elseif ( is_page('projects') || is_page('servicii') || is_page('services') ) 
		{ echo '"@type": "CollectionPage",';} 				
	elseif ( is_page('faq') ) 
		{ echo '"@type": "AboutPage",';} 
	elseif ( is_page('about-us') || is_page('privacy-policy') || is_page('terms-of-service')) 
		{ echo '"@type": "AboutPage",';} 
	elseif ( is_page('our-company') || is_page('our-team') || is_page('profile') ) 
		{ echo '"@type": "ProfilePage",';} 
	else 
		{ echo '"@type": "WebPage",';} 

	if ( !is_single() && !is_page()) { echo add_loop_posts_data(); }

	if ( is_single()) { 
		if ( !is_search() && !is_page_template('archive.php')) {
			add_post_category();
			add_post_keywords();
			add_post_word_count();
			add_post_author();
		}
	}
	add_page_title();
	add_page_description();
	add_post_text();
	add_post_headlines();
	add_post_links();	
	add_date_published();
	add_date_modified();
	add_main_entity();
	add_page_publisher();
	add_schema_publisher();
	add_schema_date();
	add_post_media();
	add_post_image();
?>
}
<?php add_website_markup(); ?>
]
</script> 
	<?php 
	}

add_action('wp_footer','schema_output'); 