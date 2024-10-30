<?php
/*
 * @package ImageSnippets_Gallery
 * @version 2.7
 */

/*
  Plugin Name: ImageSnippets Gallery
  Description: Create a responsive image gallery using ImageSnippets.com
  Author: Metadata Authoring Systems, LLC
  Version: 2.7
  Author URI: http://www.imagesnippets.com
  License: GPLv2 or later
 */
/*
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
defined('ABSPATH') or die('No script kiddies please!');
include_once "class.imagesnippet.php";
include_once "next-page-is.php";

/**
* REGISTER ACTIVATION/DEACTIVATION HOOKS + ADD REQUIRED PLUGIN ACTIONS
*/
register_activation_hook(__FILE__, 'isnippet_install_options');
register_deactivation_hook(__FILE__, 'isnippet_delete_options');
add_action('admin_menu', 'isnippet_add_menu_links');
add_action('wp_enqueue_scripts', 'isnippet_add_scripts');
add_action( 'admin_enqueue_scripts', 'isnippet_add_scripts' );
add_action( 'wp_ajax_snippet', 'isnippet_request_graph' );
add_action( 'wp_ajax_nopriv_regeneratesnippet', 'isnippet_request_graph' );
add_action( 'wp_ajax_regeneratesnippet', 'isnippet_request_graph' );
add_action( 'wp_ajax_delete_settings', 'isnippet_delete_gallery' );
add_action( 'wp_ajax_save_cron', 'isnippet_cron_settings' );
add_action( 'daily_autogenerate_gallery', 'isnippet_request_graph' );

/**
* REGISTER GLOBAL VARIABLES
*/
global $is_settings_version, $is_graph_version, $is_cron_version, $table_settings, $wpdb, $table_graph, $table_cron, $table_options;
$is_settings_version = '1.0';
$is_graph_version = '1.0';
$is_cron_version = '1.0';
$table_settings = $wpdb->prefix . 'snippet_settings';
$table_graph = $wpdb->prefix . 'snippet_graph';
$table_cron = $wpdb->prefix . 'snippet_cron';
$table_options = $wpdb->prefix . 'options';
$charset_collate = $wpdb->get_charset_collate();

/**
* CREATE GRAPH TABLES ON ACTIVATION
*/
function isnippet_install_options() {

    if (!class_exists('LP_Toggle_wpautop')) {
         // dependency not installed, bail
         die ("Plugin Toggle_wpautop must be activated before activating this plugin");
    }
    
    global $wpdb, $table_settings, $is_settings_version, $table_graph, $is_graph_version, $table_cron, $is_cron_version, $charset_collate;

    if ($wpdb->get_var("show tables like '$table_settings'") != $table_settings) {
        $sql = "CREATE TABLE $table_settings (
		is_id INT(11) NOT NULL AUTO_INCREMENT,
		is_directory TEXT NOT NULL,
		is_username TEXT ,
		is_property TEXT NOT NULL,
		is_object TEXT NOT NULL,
        is_automation VARCHAR(5) NOT NULL,
        is_time VARCHAR(8) NOT NULL,
        PRIMARY KEY (is_id)
	) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('is_settings_version', $is_settings_version);
    }
    
    if ($wpdb->get_var("show tables like '$table_graph'") != $table_graph) {
        $sql = "CREATE TABLE $table_graph (
		graph_id INT(11) NOT NULL AUTO_INCREMENT,
        settings_id INT(11) NOT NULL,
        graph_source TEXT DEFAULT Null,
        graph_uri TEXT DEFAULT Null,
		graph_title TEXT DEFAULT Null,
		graph_thumb TEXT DEFAULT Null,
		graph_subject TEXT DEFAULT Null,
		graph_creator_uri TEXT DEFAULT Null,
        graph_creator TEXT DEFAULT Null,
        graph_description TEXT DEFAULT Null,
        graph_notice TEXT DEFAULT Null,
        graph_terms TEXT DEFAULT Null,
        graph_ldscript TEXT DEFAULT Null,
        PRIMARY KEY (graph_id),
        FOREIGN KEY (settings_id) REFERENCES $table_settings (is_id) ON UPDATE CASCADE ON DELETE CASCADE
	) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('is_graph_version', $is_graph_version);
    }
    
    if ($wpdb->get_var("show tables like '$table_cron'") != $table_cron) {
        $sql = "CREATE TABLE $table_cron (
		id INT(11) NOT NULL AUTO_INCREMENT,
        is_cron_id INT(11) NOT Null,
        is_cron_name VARCHAR(10) DEFAULT Null,
        PRIMARY KEY (id)
	) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('is_cron_version', $is_cron_version);
    }
    
}

/**
* DELETE GRAPH TABLES AND WP OPTIONS
*/
function isnippet_delete_options() {
    global $wpdb, $table_graph, $table_settings, $table_cron, $table_options;
    
    $crons = _get_cron_array();
    if ( !empty( $crons ) ) {
        foreach( $crons as $timestamp => $cron ) {
            unset($crons[$timestamp]['daily_autogenerate_gallery']);
            if ( empty($crons[$timestamp]['daily_autogenerate_gallery']) )
	             unset( $crons[$timestamp]['daily_autogenerate_gallery'] );
            if ( empty($crons[$timestamp]) )
	             unset( $crons[$timestamp] );
        }
        _set_cron_array( $crons );
    }
    
    $row = $wpdb->get_var( "SELECT COUNT(*) FROM $table_cron" );
    if($row != 0) {
        $delete_cron = "https://www.setcronjob.com/api/cron.delete?token=".get_option('is_cron_key')."&id=$row->is_cron_id";
        $response = \Httpful\Request::get($delete_cron)->expectsJson()->send();
    }

    $wpdb->query("DROP TABLE IF EXISTS $table_graph ;");
    delete_option("is_graph_version");
    
    $wpdb->query("DROP TABLE IF EXISTS $table_settings ;");
    delete_option("is_settings_version");
    
    $wpdb->query("DROP TABLE IF EXISTS $table_cron ;");
    delete_option("is_cron_version");
    
    $count_gallery = $wpdb->get_results( "SELECT * FROM $table_options WHERE option_name LIKE 'is_gallery%' " ); 
    foreach ( $count_gallery as $option ) {
        
        $parent_id = get_option($option->option_name);
        $posts = get_posts( array( 'post_parent' => $parent_id, 'post_type' => 'page', 'numberposts' => -1 ) );

        if (is_array($posts) && count($posts) > 0) {
            foreach($posts as $post){
                wp_delete_post($post->ID, true);
            }
        }

        wp_delete_post($parent_id, true);
    }
    
    $wpdb->query($wpdb->prepare("DELETE FROM $table_options WHERE option_name LIKE %s", $wpdb->esc_like('is_gallery') . '%'));
    
    delete_option("is_cron_key");
    delete_option("is_cron_name");
}

/**
* ENQUEUE STYLES/SCRIPTS ON ACTIVATION
*/
function isnippet_add_scripts() {
    wp_enqueue_style( 'font-awesome', IS_URL.'/assets/css/font-awesome.min.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'timepicker-css', IS_URL.'/assets/css/jquery.timepicker.css', array(), '1.0', 'all' );
    wp_enqueue_style( 'snippet-styles', IS_URL.'/assets/css/snippet-styles.css', array(), '1.0', 'all' );
    if ( ! is_admin() ) {
        wp_enqueue_script('zoomwall-js', IS_URL.'/assets/js/zoomwall.js', array('jquery'), '1.0', true);
        wp_enqueue_script('gallery-js', IS_URL.'/assets/js/gallery.js', array('jquery'), '1.0', true);
    }
    wp_enqueue_script('timepicker-js', IS_URL.'/assets/js/jquery.timepicker.min.js', array('jquery'), '1.0', true);
    wp_enqueue_script('functions-js', IS_URL.'/assets/js/isnippet.js', array('jquery'), '', true);
    wp_localize_script( 'functions-js', 'snippetrequest', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

/**
* CREATE WORDPRESS PLUGIN MENU
*/
function isnippet_add_menu_links() {
    add_menu_page('ImageSnippets Gallery', 'ImageSnippets Gallery', 'manage_options', 'image-snippets-gallery', 'isnippet_load_plugin', '', 26);
    add_submenu_page( 'image-snippets-gallery', 'View Galleries', 'Galleries', 'manage_options', 'galleries', 'isnippet_load_galleries' );
    add_submenu_page( 'image-snippets-gallery', 'Image Snippets', 'Settings', 'manage_options', 'image-snippets-gallery', 'isnippet_load_plugin' );
    add_submenu_page( 'image-snippets-gallery', 'Cron Settings', 'Cron Settings', 'manage_options', 'cron-settings', 'isnippet_cron_settings' );
    unset($GLOBALS['submenu']['image-snippets-gallery'][0]);
}

/**
* Fix up URLs for SPARQL query
*/
function fix_url_for_sparql($url) {
        if (preg_match("/(https?:\/\/)?((?:(\w+-)*\w+)\.)+(?:[a-z]{2})(\/?\w?-?=?_?\??&?)+[\.]?([a-z0-9\?=&_\-%#])?/i", $url)) { 
            if(substr($url, 0, 7) == "http://" || substr($url, 0, 8) == "https://") {
                return "<".$url.">";
            }
            else if(substr($url, 0, 3) == "www") {
                return "<http://".$url.">";
            }
            else if(substr($url, 0, 10) != "http://www") {
                return "<http://www.".$url.">";
            }
        } 
        else { 
            return $url;
        }
}

/**
* REQUEST IMAGE SNIPPET RDF DATA
*/
function isnippet_request_graph($args) {
    
    global $wpdb, $table_settings, $table_graph, $table_cron;
    
    $isnippet = new Image_snippet();
    
    $postdata = $_POST;
    
    $properties = [];
    $sparql_props = [];
    if($postdata['action'] == "snippet") {
	error_log("first case, action == snippet");
	foreach ($postdata as $key => $value) {
	  if (preg_match("/^property([0-9]*)$/", $key, $matches)) {
		error_log("found property ".$key." = ".$value);
		error_log(print_r($matches, true));
		$objValue = $postdata['object'.$matches[1]];
		error_log("object value: ".$objValue);
                if (!empty($value) && !empty($objValue)) {
                    $propertyArray = ['property' => $value, 'object' => $objValue];
                    array_push($properties, $propertyArray); 
                    $sPropertyArray = ['property' => fix_url_for_sparql($value), 'object' => fix_url_for_sparql($objValue)];
                    array_push($sparql_props, $sPropertyArray); 
                }
	  }
	  else {
		error_log("no match for : ".$key);
	  }
        }

        $action = "snippet";
        $directory = $postdata['directory'];
        $username = $postdata['username'] != "" ? $postdata['username']:"null";
        $cronjob = isset($postdata['cronjob']) ? "on":"off";
        $cron_service = $postdata['cronservice'];
        $graph_action = $postdata['graph_action'] == "Insert" ? "Insert":"Regenerate";
        $data_id = $postdata['data_id'];
		
        if($cronjob == "on") {
            $automation = "true";
            $time = $postdata['time'];
            
            if($cron_service == "on") {
                $count_cron = $wpdb->get_var( "SELECT COUNT(*) FROM $table_cron" ); 
           
                if($count_cron == 0) {
                    $create_cron = "https://www.setcronjob.com/api/cron.add?token=".get_option('is_cron_key')."&hour=0,12&url=".rawurlencode(site_url('wp-cron.php?doing_cron'))."&name=".get_option('is_cron_name');
                    $response = \Httpful\Request::get($create_cron)->expectsJson()->send();
                    if($response->body->status == "error") {
                        if($response->body->code == 1) {
                            if(!get_option('is_cron_key')) {
                                $status = array(
                                    "Status" => "Error",
                                    "Message" => "API Key is missing."
                                );
                            }
                            else {
                                $status = array(
                                    "Status" => "Error",
                                    "Message" => "The hostname 'localhost' is not allowed, please use a live site url to enable cron job service."
                                );
                            }
                            
                            die(json_encode($status));
                        }
                        else if($response->body->code == 11) {
                            $status = array(
                                "Status" => "Error",
                                "Message" => "Invalid API Key. Please check your API Key."
                            );
                            die(json_encode($status));
                        }
                    }
                    else {
                        $isnippet->isnippet_insert_cron($table_cron, $response->body->data->id, $response->body->data->name);
                    }                    
                }
            }
            
            $row_id = $isnippet->isnippet_insert_settings($table_settings, $data_id, $directory, $username, json_encode($properties), '', $automation, $time, $graph_action);
            
            $cron_args = array("update_id" => intval($row_id));
            
            if($graph_action == "Regenerate") {
                $crons = _get_cron_array();
                $key = md5(serialize($cron_args));
                if ( !empty( $crons ) ) {
                    foreach( $crons as $timestamp => $cron ) {
                        if ( isset( $cron[ 'daily_autogenerate_gallery' ][ $key ] ) ) {
                            wp_unschedule_event( $timestamp, 'daily_autogenerate_gallery', $cron_args );
                        }
                    }
                }
            }
            
            if( !wp_next_scheduled( 'daily_autogenerate_gallery' ) ) {
                wp_schedule_event( strtotime($time), 'daily', 'daily_autogenerate_gallery', $cron_args );
            }

        }
        else {
            $automation = "false";
            $time = '0:00 am';
            
            if($graph_action == "Regenerate") {
                $crons = _get_cron_array();
                $cron_args = array("update_id" => intval($data_id));
                $key = md5(serialize($cron_args));
                if ( !empty( $crons ) ) {
                    foreach( $crons as $timestamp => $cron ) {
                        if ( isset( $cron[ 'daily_autogenerate_gallery' ][ $key ] ) ) {
                            wp_unschedule_event( $timestamp, 'daily_autogenerate_gallery', $cron_args );
                        }
                    }
                }
            }
            
            $row_id = $isnippet->isnippet_insert_settings($table_settings, $data_id, $directory, $username, json_encode($properties), '', $automation, $time, $graph_action);

        }
        
    }
    else {
error_log("were in the second case");
	foreach ($postdata as $key => $value) {
	  if (preg_match("/^property([0-9]*)$/", $key)) {
		error_log("found property ".$key." = ".$value);
	  }
	  else {
		error_log("no match for : ".$key);
	  }
        }

        $id = isset($postdata['update_id']) ? $postdata['update_id']:$args['update_id'];
        $get_settings = $wpdb->get_row( "SELECT * FROM $table_settings WHERE is_id = $id");
        $action = "regeneratesnippet";
        $graph_action = "Regenerate";
        $row_id = $get_settings->is_id;
        $directory = $get_settings->is_directory;
        $username = $get_settings->is_username;
        $property = $get_settings->is_property;
        $object = $get_settings->is_object;
error_log('$object is ' . $object);
	if (!empty($object)) {
	  $propertyArray =['property' => $property, 'object' => $object];
	  array_push($properties, $propertyArray); 	  
        }
        else {
	  $properties = json_decode($property, true);
	}
	foreach ($properties as $property) {
		$sPropertyArray = ['property' => fix_url_for_sparql($property['property']), 'object' => fix_url_for_sparql($property['object'])];
		array_push($sparql_props, $sPropertyArray); 
	}
    }
error_log("about to request graph data");
        
    $graph_data = $isnippet->isnippet_request_graph_data($username, $sparql_props, $object);
error_log("sent request");
    if($graph_data["Status"] == "Success") {
error_log("got graph data successfully");
error_log($graph_data);
        if($graph_action == "Regenerate") {
            $wpdb->delete( $table_graph, array( 'settings_id' => $row_id ), array( '%d' ) );
        }
        foreach($graph_data["Result"] as $row) {
            
            $row_graph = !empty($row->graph) ? $row->graph:"Null";
            $row_image = !empty($row->image) ? $row->image:"Null";
            $row_title = !empty($row->title) ? $row->title:"Null";
            $row_thumb = !empty($row->thumb) ? $row->thumb:"Null";
            $row_subject = !empty($row->subject) ? $row->subject:"Null";
            $row_creator_uri = !empty($row->creator_uri) ? $row->creator_uri:"Null";
            $row_creator = !empty($row->creator) ? $row->creator:"Null";
            $row_description = !empty($row->description) ? $row->description:"Null";
            $row_copynotice = !empty($row->copynotice) ? $row->copynotice:"Null";
            $row_copyterms = !empty($row->copyterms) ? $row->copyterms:"Null";
            
            $isnippet->isnippet_insert_graph($table_graph, $row_id, $row_graph, $row_image, $row_title, $row_thumb, $row_subject, $row_creator_uri, $row_creator, $row_description, $row_copynotice, $row_copyterms);
        }
        $status = $isnippet->isnippet_create_gallery($directory, $table_graph, $row_id, $action, $graph_action);
    }
    elseif($graph_data["Status"] == "Fault") {
        $status = array('Status' => 'Error', 'Message' => $graph_data["Result"]);
    }
    else {
error_log("not successful");
error_log($graph_data["Status"]);
error_log($graph_data["Result"]);
        $status = array('Status' => 'Error', 'Message' => $graph_data["Result"]);
    }
    
    die(json_encode($status));
}

/**
* DELETE GRAPH DATA
*/
function isnippet_delete_gallery() {
    
    global $wpdb, $table_settings;
    
    $postdata = $_POST;
    
    if($postdata['delete_id'] != "" || $postdata['delete_id'] != null) {
        
        $crons = _get_cron_array();
        $cron_args = array("update_id" => intval($postdata['delete_id']));
        $key = md5(serialize($cron_args));
        if ( !empty( $crons ) ) {
            foreach( $crons as $timestamp => $cron ) {
                if ( isset( $cron[ 'daily_autogenerate_gallery' ][ $key ] ) ) {
                    wp_unschedule_event( $timestamp, 'daily_autogenerate_gallery', $cron_args );
                }
            }
        }
        
        $result = $wpdb->delete( $table_settings, array( 'is_id' => $postdata['delete_id'] ), array( '%d' ) );
        
        if($result === FALSE) {
            $status = array(
                "Status" => "Error",
                "Message" => "Error deleting graph data"
            );
        }
        else {
            
            $parent_id = get_option("is_gallery_".$postdata['delete_id']);
            $posts = get_posts( array( 'post_parent' => $parent_id, 'post_type' => 'page', 'numberposts' => -1 ) );

            if (is_array($posts) && count($posts) > 0) {
                foreach($posts as $post){
                    wp_delete_post($post->ID, true);
                }
            }
            
            wp_delete_post($parent_id, true);
            delete_option("is_gallery_".$postdata['delete_id']);
            
            $status = array(
                "Status" => "Success",
                "Message" => $postdata['delete_name']." has been deleted"
            );
        }
        
        die(json_encode($status));
    }
}

/**
* CREATE PLUGIN PAGE
*/
function isnippet_load_plugin() {
    global $wpdb, $table_options;
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
?>
    <img src="<?php echo IS_URL; ?>/assets/images/bg.png" style="width:100%;">
    <div class="wrap">

        <h1>ImageSnippets Gallery Generator</h1>

        <div class="updated notice">
            <p>This plugin generates an HTML image gallery on your website.</p>
        </div>

        <div class="postbox-container" style="width:41%; ">

            <div id="status_generate"></div>

            <form method="POST" id="snippet-settings">

                <div class="meta-box-sortables">
                    <div class="postbox">

                        <h3 class="hndle" style="margin:0; padding:11px; cursor:default;"><span>Add New Gallery</span></h3>

                        <div class="inside">
                            <p>
                                <label>Gallery Name :</label>
                                <input type="text" name="directory" id="snippet-settings-directory" style="width: 100%">
                            </p>
                        </div>

                        <div class="inside">
                            <p>
                                <label>ImageSnippets Username:</label>
                                <input type="text" name="username" id="snippet-settings-username" style="width: 100%">
                            </p>
                        </div>

		<div id="galleryProperties">
				<datalist id="propertyList">
				  <option label="depicts" value="lio:depicts">
				  <option label="shows" value="lio:shows">
				  <option label="looksLike" value="lio:looksLike">
				  <option label="conveys" value="lio:conveys">
				  <option label="is" value="lio:is">
				  <option label="isIn" value="lio:isIn">
				  <option label="usesPictorially" value="lio:usesPictorially">
				  <option label="hasArtisticElement" value="lio:hasArtisticElement">
				  <option label="hasInForeground" value="lio:hasInForeground">
				  <option label="hasInBackground" value="lio:hasInBackground">
				  <option label="hasSetting" value="lio:hasSetting">
				  <option label="style" value="lio:style">
				  <option label="materials" value="lio:materials">
				  <option label="technique" value="lio:technique">
				</datalist>
		    <div class="inside">
		      <fieldset class="galleryProperty">
			<legend>Filter 1</legend>
                        <div class="inside">
                            <p>
                                <label>Property (URI or CURI):</label>
                                <input type="text" name="property1" id="snippet-settings-property1" style="width: 100%" autocomplete="whatever" list="propertyList">
                            </p>
                        </div>

                        <div class="inside">
                            <p>
                                <label>Object (URI or CURI):</label>
                                <input type="text" name="object1" id="snippet-settings-object1" style="width: 100%">
                            </p>
                        </div>
		      </fieldset> <!-- galleryProperty -->
		    </div> <!-- inside -->
		</div> <!-- galleryProperties -->
			<div class="inside" style="text-align:right">
				<button type="button" class="button button-primary button-large btn-disabled" id="addPropertyBtn">Add Filter</button>
			</div>

                        <div class="inside">
                            <p>
                                <input type="checkbox" name="cronjob" class="automation">
                                <label>Add an automated daily refresh for this gallery</label>
                            </p>
                        </div>

                        <div class="inside time" style="display:none;">
                            <p>
                                <label>Create cron job service?</label>
                                <input type="radio" name="cronservice" value="on"> Yes
                                <input type="radio" name="cronservice" value="off" checked> No
                            </p>
                            <p>
                                <label>Select a time you want it to run:</label>
                                <input type="text" name="time" class="timepicker">
                            </p>
                        </div>

                        <div class="inside" style="text-align:right">
                            <p>
                                <input type="submit" value="Generate" class="button button-primary button-large btn-disabled" id="generate">
                                <input type="hidden" name="graph_action" value="Insert">
                                <input type="hidden" name="data_id" value="0">
                            </p>
                        </div>

                    </div>
                </div>


            </form>
        </div>

        <div class="postbox-container" style="margin-left: 30px; width: 50%; float: right;">
            <div class="meta-box-sortables">

                <div id="status_update"></div>

                <div class="postbox">
                    <h3 class="hndle" style="margin:0; padding:11px; cursor:default;"><span>Gallery Settings</span></h3>
                    <table>
                        <thead style="display:block;">
                            <tr style="display:table; width:100%;">
                                <th>Gallery Name</th>
                                <th colspan="2">Action</th>
                            </tr>
                        </thead>
                        <tbody style="display:block; max-height:378px; overflow-y:auto;" id="autoload">
                            <?php 
                                global $wpdb, $table_settings; 
                                $row_data = $wpdb->get_results( "SELECT * FROM $table_settings" );
                                if($row_data) : foreach($row_data as $row) :
                            ?>
                                <tr style="display:table; width:100%;">
                                    <td>
                                        <a href="<?php echo site_url(sanitize_title($row->is_directory)); ?>" target="_blank">
                                            <?php echo $row->is_directory; ?>
                                        </a>
                                    </td>
                                    <td style="text-align:right;">
                                        <button type="button" class="button button-primary button-large btn-regenerate-gallery btn-disabled" id="<?php echo $row->is_id; ?>">Regenerate</button>
                                        <a href="<?php echo admin_url('admin.php?page=galleries&id='.$row->is_id); ?>" class="button button-primary button-large btn-edit-gallery btn-disabled">Edit</a>
                                        <button type="button" class="button button-primary button-large btn-delete-gallery btn-disabled" id="<?php echo $row->is_id; ?>" data-name="<?php echo $row->is_directory; ?>">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; else : ?>
                                    <tr style="display:table; width:100%;">
                                        <td colspan="2">No Gallery to Update</td>
                                    </tr>
                                    <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="postbox-container" style="width:100%;">
            <p><b>Note:</b> If your Wordpress theme sets a maximum width on page content, please add this CSS to your theme:<br>
<pre><code>@media screen and (min-width:640px) {
  .zoomwall img {
     max-height: YOUR_THEME_MAX_PAGE_WIDTH/4;
  }
}</code></pre>
               For example, if your theme's max page width is 600px, then <code>max-height</code> should be ~150px (do not change the <code>min-width</code> selector)
            </p>
            <p><b>Note:</b> To use cron job service you need to setup an account and generate API key for free at <a href="http://www.setcronjob.com" target="_blank">setcronjob.com</a>. After generating your API key go to ImageSnippets Gallery > Cron Settings and save your API key and name of Cron Job.</p>
        </div>

    </div>
<script type="text/javascript" defer="defer">
jQuery(document).ready(function($){
  var propUniqueIdx = 2;
  $("#addPropertyBtn").click(function() {
	console.log("click!");
    var html = '<div class="inside"><fieldset class="galleryProperty"><legend>Filter ' + propUniqueIdx + '</legend><div class="inside"><p><label>Property (URI or CURI):</label><input type="text" name="property' + propUniqueIdx + '" id="property' + propUniqueIdx + '" style="width: 100%" autocomplete="whatever" list="propertyList"></p></div>';
    html += '<div class="inside"><p><label>Object (URI or CURI):</label><input type="text" name="object' + propUniqueIdx + '" id="object' + propUniqueIdx + '" style="width: 100%"></p></div></fieldset></div>';
    propUniqueIdx++;

    $(html).appendTo("#galleryProperties");
  });
});
</script>
    <?php
}

/**
* CREATE GALLERIES PAGE
*/
function isnippet_load_galleries() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
?>
        <img src="<?php echo IS_URL; ?>/assets/images/bg.png" style="width:100%;">
        <div class="wrap">
            <h1>View Galleries</h1>

            <div class="updated notice">
                <p>Update your gallery settings</p>
            </div>

            <div class="postbox-container">
                <div class="meta-box-sortables">

                    <div id="status_update"></div>
                    <div id="status_generate"></div>

                    <div class="postbox">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:10%;">Gallery Name</th>
                                    <th style="width:10%;">Username</th>
                                    <th style="width:20%;">Property</th>
                                    <th style="width:20%;">Object</th>
                                    <th style="width:10%;">Automated Refresh</th>
                                    <th style="width:10%;">Time</th>
                                    <th style="width:20%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="autoload" style="text-align:center;">
                                <?php 
                                global $wpdb, $table_settings; 
                                
                                if(isset($_GET['id']) && !empty($_GET['id'])) {
                                    $where_clause = 'WHERE is_id = '.$_GET['id'];
                                    $display = "block";
                                }
                                else {
                                    $where_clause = '';
                                    $display = "none";
                                }

                                $patterns = array();
                                $replacements = array();
                                $patterns[0] = '/</';
                                $patterns[1] = '/>/';
                                $replacements[0] = '';
                                $replacements[1] = '';
    
                                $row_data = $wpdb->get_results( "SELECT * FROM $table_settings $where_clause" );
                                if($row_data) : foreach($row_data as $row) :
					$properties = [];
					if (!empty($row->is_object)) {
					  $propertyArray =['property' => $row->is_property, 'object' => $row->is_object];
					  array_push($properties, $propertyArray); 	  
				        }
				        else {
					  $properties = json_decode($row->is_property, true);
					}
                            ?>
                                    <tr>
                                        <td style="width:10%;">
                                            <a href="<?php echo site_url(sanitize_title($row->is_directory)); ?>" target="_blank">
                                                <?php echo $row->is_directory; ?>
                                            </a>
                                        </td>
                                        <td style="width:10%;">
                                            <?php echo $row->is_username; ?>
                                        </td>
                                        <td style="width:20%;">
					    <?php $isFirst = true; ?>
					    <?php foreach ($properties as $property): ?>
					      <?php if (!$isFirst) echo '</br>'; $isFirst = false; ?>
					      <?php echo $property['property']; ?>
                                            <?php endforeach; ?>					
                                        </td>
                                        <td style="width:20%;">
					    <?php $isFirst = true; ?>
                                            <?php foreach ($properties as $property): ?>
					      <?php if (!$isFirst) echo '</br>'; $isFirst = false; ?>
					      <?php echo $property['object']; ?>
					    <?php endforeach; ?>
                                        </td>
                                        <td style="width:10%;">
                                            <?php echo $row->is_automation; ?>
                                        </td>
                                        <td style="width:10%;">
                                            <?php echo $row->is_time; ?>
                                        </td>
                                        <td style="width:20%;">
                                            <?php if(!isset($_GET['id'])) : ?>
                                                <button type="button" class="button button-primary button-large toggle-form btn-disabled" id="<?php echo $row->is_id; ?>">Edit</button>
                                                <?php endif; ?>
                                                    <button type="button" class="button button-primary button-large btn-delete-gallery btn-disabled" id="<?php echo $row->is_id; ?>" data-name="<?php echo $row->is_directory; ?>">Delete</button>
                                        </td>

                                    </tr>
                                    <tr>
                                        <td colspan="7">
                                            <div id="toggle-form-container-<?php echo $row->is_id; ?>" class="toggle-form-container" style="display:<?php echo $display ;?>">                   
                                                <form method="POST" class="update-snippet-settings" id="<?php $formId = "form-".$row->is_id; echo $formId; ?>">
                                                    <div class="meta-box-sortables">
                                                        <div class="postbox">
                                                            <div class="inside">
                                                                <p>
                                                                    <label for="anps_subtitle">Gallery Name :</label>
                                                                    <input type="text" name="directory" id="<?php echo $formId; ?>-directory" value="<?php echo $row->is_directory; ?>" style="width: 100%">
                                                                </p>
                                                            </div>

                                                            <div class="inside">
                                                                <p>
                                                                    <label for="anps_subtitle">ImageSnippets Username:</label>
                                                                    <input type="text" name="username" id="<?php echo $formId; ?>-username" value="<?php echo $row->is_username; ?>" style="width: 100%">
                                                                </p>
                                                            </div>
                                                            <div class="galleryProperties">
				<datalist id="propertyList">
				  <option label="depicts" value="lio:depicts">
				  <option label="shows" value="lio:shows">
				  <option label="looksLike" value="lio:looksLike">
				  <option label="conveys" value="lio:conveys">
				  <option label="is" value="lio:is">
				  <option label="isIn" value="lio:isIn">
				  <option label="usesPictorially" value="lio:usesPictorially">
				  <option label="hasArtisticElement" value="lio:hasArtisticElement">
				  <option label="hasInForeground" value="lio:hasInForeground">
				  <option label="hasInBackground" value="lio:hasInBackground">
				  <option label="hasSetting" value="lio:hasSetting">
				  <option label="style" value="lio:style">
				  <option label="materials" value="lio:materials">
				  <option label="technique" value="lio:technique">
				</datalist>
                                            <?php $filterCount = 1; foreach ($properties as $property): ?>
                                                <div class="inside">
					      	<fieldset class="galleryProperty">
							<legend>Filter <?php echo $filterCount; ?></legend>
                                                            <div class="inside">
                                                                <p>
                                                                    <label for="anps_subtitle">Property (URI or CURI):</label>
                                                                    <input type="text" name="property<?php echo $filterCount; ?>" id="<?php echo $formId."-property".$filterCount; ?>" autocomplete="whatever" list="propertyList" value="<?php echo $property['property']; ?>" style="width: 100%">

                                                                </p>
                                                            </div>

                                                            <div class="inside">
                                                                <p>
                                                                    <label>Object (URI or CURI):</label>
                                                                    <input type="text" name="object<?php echo $filterCount; ?>" id="<?php echo $formId."-object".$filterCount; ?>" value="<?php echo preg_replace($patterns, $replacements, $property['object']); ?>" style="width: 100%">
                                                                </p>
                                                            </div>
							</fieldset>
                                                        </div>
						<?php $filterCount++; endforeach; ?>
                                                            </div>
                                                            <div class="inside" style="text-align:right">
                                                                    <button type="button" class="button button-primary button-large btn-disabled addPropertyBtn">Add Filter</button>
                                                            </div>

                                                            <div class="inside">
                                                                <p>
                                                                    <input type="checkbox" name="cronjob" class="automation" <?php echo $row->is_automation == "true" ? "checked":""; ?>>
                                                                    <label>Add an automated daily refresh for this gallery</label>
                                                                </p>
                                                            </div>

                                                            <div class="inside time" style="display:none;">
                                                                <p>
                                                                    <label>Select a time you want it to run:</label>
                                                                    <input type="text" name="time" class="timepicker" value="<?php echo $row->is_time; ?>">
                                                                </p>
                                                            </div>

                                                            <div class="inside" style="text-align:right">
                                                                <p>
                                                                    <input type="submit" value="Update" class="button button-primary button-large btn-disabled updateSettingsBtn">
                                                                    <input type="hidden" name="graph_action" value="Update">
                                                                    <input type="hidden" name="cronservice" value="off">
                                                                    <input type="hidden" name="data_id" value="<?php echo $row->is_id; ?>">
                                                                </p>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; else : ?>
                                        <tr>
                                            <td colspan="7">No Gallery to Update. Click <a href="<?php echo admin_url('admin.php?page=image-snippets-gallery');?>">here</a> to create a new gallery</td>
                                        </tr>
                                        <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
<script type="text/javascript" defer="defer">
jQuery(document).ready(function($){
  var propUniqueIdx = <?php $filterCount++; echo $filterCount; ?>;
  $(".addPropertyBtn").click(function() {
	console.log("click!");
    var html = '<div class="inside"><fieldset class="galleryProperty"><legend>Filter ' + propUniqueIdx + '</legend><div class="inside"><p><label>Property (URI or CURI):</label><input type="text" name="property' + propUniqueIdx + '" style="width: 100%" autocomplete="whatever" list="propertyList"></p></div>';
    html += '<div class="inside"><p><label>Object (URI or CURI):</label><input type="text" name="object' + propUniqueIdx + '" style="width: 100%"></p></div></fieldset></div>';
    propUniqueIdx++;

    var siblings = $(this).parent().siblings(".galleryProperties");
    $(html).appendTo(siblings.eq(0));
  });
});
</script>
        
        <?php } 

/**
* CRON SETTINGS
*/
function isnippet_cron_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if(isset($_POST["action"])) {
        $postdata = $_POST;
        $cron_key = sanitize_text_field($postdata["cron_key"]);
        $cron_name = sanitize_text_field($postdata["cron_name"]);        
        
        
        if(get_option("is_cron_key") == FALSE || get_option("is_cron_key") == FALSE) {
            add_option("is_cron_key", $cron_key);
            add_option("is_cron_name", $cron_name);
            die(json_encode(
                array(
                    "Status" => "Success",
                    "Message" => "Cron settings saved successfully."
                )
            ));
        }
        else {
            update_option("is_cron_key", $cron_key);
            update_option("is_cron_name", $cron_name);
            die(json_encode(
                array(
                    "Status" => "Success",
                    "Message" => "Settings updated successfully."
                )
            ));
        }
        
    }
?>
            <img src="<?php echo IS_URL; ?>/assets/images/bg.png" style="width:100%;">
            <div class="wrap">
                <h1>Cron Job Configuration</h1>
                <div class="updated notice">
                    <p>Please enter your API key and name for cron job</p>
                </div>
                <div class="postbox-container" style="width:45%;">
                    <div class="meta-box-sortables">
                        <div id="status_generate"></div>
                        <form method="POST" id="cron-settings">
                            <div class="meta-box-sortables">
                                <div class="postbox">
                                    <h3 class="hndle" style="margin:0; padding:11px; cursor:default;"><span>Cron Settings</span></h3>

                                    <div class="inside">
                                        <p>
                                            <label for="anps_subtitle">API Key :</label>
                                            <input type="text" name="cron_key" value="<?php echo get_option(" is_cron_key ") != FALSE ? get_option("is_cron_key "):" " ?>" id="cron_key" style="width: 100%">
                                        </p>
                                    </div>

                                    <div class="inside">
                                        <p>
                                            <label for="anps_subtitle">Cron Name:</label>
                                            <input type="text" name="cron_name" value="<?php echo get_option(" is_cron_name ") != FALSE ? get_option("is_cron_name "):" " ?>" id="cron_name" style="width: 100%">
                                        </p>
                                    </div>

                                    <div class="inside" style="text-align:right">
                                        <p>
                                            <input type="submit" name="cron_save" value="Save" class="button button-primary button-large btn-disabled" id="cron_save">
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="postbox-container" style="width:100%;">
                    <p><b>Note:</b> To generate your API key for free sign up at <a href="http://www.setcronjob.com" target="_blank">setcronjob.com</a>.</p>
                </div>
            </div>
            <?php } ?>
