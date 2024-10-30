<?php

defined('ABSPATH') or die('No script kiddies please!');
define('IS_URL', plugins_url()."/".dirname( plugin_basename( __FILE__ ) ) );
define('IS_PATH', WP_PLUGIN_DIR."/".dirname( plugin_basename( __FILE__ ) ) );
include_once 'assets/libs/rdf/vendor/autoload.php';
include_once "assets/libs/rdf/lib/EasyRdf.php";
include_once "assets/libs/httpful/bootstrap.php";
add_action('wp_head', array(new Image_Snippet, 'isnippet_add_ldscript'));

global $wpdb, $endpoint, $post;

EasyRdf_Namespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
EasyRdf_Namespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
EasyRdf_Namespace::set('owl', 'http://www.w3.org/2002/07/owl#');
EasyRdf_Namespace::set('acl', 'http://www.w3.org/ns/auth/acl#');
EasyRdf_Namespace::set('void', 'http://vocab.deri.ie/void#');
EasyRdf_Namespace::set('photoshop', 'http://ns.adobe.com/photoshop/1.0/');
EasyRdf_Namespace::set('freebase', 'http://rdf.freebase.com/ns/');
EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');
EasyRdf_Namespace::set('foaf', 'http://xmlns.com/foaf/0.1/');
EasyRdf_Namespace::set('dbo', 'http://dbpedia.org/ontology/');
EasyRdf_Namespace::set('dbpedia', 'http://dbpedia.org/resource/');
EasyRdf_Namespace::set('lio', 'https://w3id.org/lio/v1#');
EasyRdf_Namespace::set('yago', 'http://yago-knowledge.org/resource/');
EasyRdf_Namespace::set('dc', 'http://purl.org/dc/elements/1.1/');
EasyRdf_Namespace::set('geo', 'http://www.w3.org/2003/01/geo/wgs84_pos#');
EasyRdf_Namespace::set('gvp', 'http://vocab.getty.edu/ontology#');
EasyRdf_Namespace::set('geonames', 'http://sws.geonames.org/');
EasyRdf_Namespace::set('scn', 'http://cv.iptc.org/newscodes/scene/');
EasyRdf_Namespace::set('dbp', 'http://dbpedia.org/property/');
EasyRdf_Namespace::set('aat', 'http://vocab.getty.edu/aat/');
EasyRdf_Namespace::set('ulan', 'http://vocab.getty.edu/ulan/');
EasyRdf_Namespace::set('sioc', 'http://rdfs.org/sioc/ns#');
EasyRdf_Namespace::set('dcterms', 'http://purl.org/dc/terms/');
EasyRdf_Namespace::set('schema', 'http://schema.org/');
EasyRdf_Namespace::set('xmprights', 'http://ns.adobe.com/xap/1.0/rights/');
EasyRdf_Namespace::set('wikidata', 'http://www.wikidata.org/entity/');
EasyRdf_Namespace::set('w3cgeo', 'http://www.w3.org/2003/01/geo/wgs84_pos#');

$endpoint = new EasyRdf_Sparql_Client('https://imagesnippets.com/sparql/dbpedia');

class Image_Snippet {
    
    /**
    * FETCH GRAPH DATA
    */
    function isnippet_request_graph_data($username, $properties) {
        
        global $endpoint;
        
        $add_creator = $username != "null" ? "?graph dcterms:creator <https://imagesnippets.com/imgtag/users/$username>.":"";
        
        $query = "
          SELECT * WHERE { graph ?graph {";
	foreach($properties as $property) {
		$query .= "{?image " . $property['property'] . " " . $property['object'] . " . }\r\n"; 
	}
/**                {?image $property $object . }*/
	$query .= "
                $add_creator
                ?image dc:title ?title.
                optional { ?image schema:thumbnail ?Image. }
                optional { ?image schema:thumbnail ?thumb. }
                optional { ?image dc:subject ?subject. }
                optional {
                 ?image dc:creator ?creator_uri.
                 ?creator_uri rdfs:label ?creator.
                }
                optional {
                 ?image dc:creator ?creator.
                 filter isLiteral(?creator)
                }
                optional { ?image rdfs:comment ?description. }
                optional { ?image dc:rights ?copynotice. }
                optional { ?image xmprights:usageTerms ?copyterms. }
            }
        }
        ORDER BY ?title
        ";
        
        try {
            $result = $endpoint->query($query);
            
            if(count($result) > 0) {
                return array(
                    'Status' => 'Success',
                    'Result' => $result
                );
            }
            else if(count($result) <= 0) {
                return array(
                    'Status' => 'Fault',
                    'Result' => 'No data found. Please check your settings and try again.'
                );
            }
        }
        catch (Exception $e) {
error_log($e);
            return array(
                    'Status' => 'Error',
                    'Result' => 'Error generating gallery. Please check your settings and try again.'
                );
        }
        
    }
    
    /**
    * SAVE USER SETTINGS
    */
    function isnippet_insert_settings($table, $data_id, $directory, $username, $property, $object, $automation, $time, $graph_action) {
        
        global $wpdb;
        
        if($graph_action == "Regenerate") {            
            $result = $wpdb->update($table, 
                                array( 
                                    'is_directory' => $directory,
                                    'is_username' => $username,
                                    'is_property' => $property,
                                    'is_object' => $object,
                                    'is_automation' => $automation,
                                    'is_time' => $time 
                                ), 
                                array( 'is_id' => $data_id ), 
                                array( 
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s'
                                ), 
                                array( '%d' ) 
            );
            
            if($result === FALSE) {
                return array(
                    "Status" => "Error",
                    "Message" => "Error updating user settings"
                );
            }
            else {
                return $data_id;
            }
        }
        else {
            $result = $wpdb->insert($table,
                                array(
                                    'is_id' => '',
                                    'is_directory' => $directory,
                                    'is_username' => $username,
                                    'is_property' => $property,
                                    'is_object' => $object,
                                    'is_automation' => $automation,
                                    'is_time' => $time
                                ),
                                array(
                                    '%d',
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s',
                                    '%s'
                                )
                   );

            if($result === FALSE) {
                return array(
                    "Status" => "Error",
                    "Message" => "Error inserting user settings"
                );
            }
            else {
                return $wpdb->insert_id;
            }
        }
        
    }
    
    /**
    * SAVE CRON SETTINGS
    */
    function isnippet_insert_cron($table, $cron_id, $cron_name) {
        
        global $wpdb;
        
        $result = $wpdb->insert($table,
                                array(
                                    'id' => 1,
                                    'is_cron_id' => $cron_id,
                                    'is_cron_name' => $cron_name
                                ),
                                array(
                                    '%d',
                                    '%d',
                                    '%s'
                                )
                   );

        if($result === FALSE) {
            return array(
                "Status" => "Error",
                "Message" => "Error inserting cron settings"
            );
        }
        else {
            return array(
                "Status" => "Success",
                "Message" => "Cron settings saved successfully"
            );
        }
    }
    
    /**
    * SAVE GRAPH DATA
    */
    function isnippet_insert_graph($table, $p_key, $graph, $image, $title, $thumbnail, $subject, $creator_uri, $creator, $description, $notice, $terms) {
        
        global $wpdb, $endpoint;
        
        if(!empty($graph) || $graph != "Null") {
            $construct = "CONSTRUCT { ?s ?p ?o } WHERE { GRAPH <" . $graph."> { ?s ?p ?o }}";
            $jsonld = $endpoint->query($construct);
            $create_graph = new EasyRdf_Collection("'".$graph."'", $jsonld);
            $script = new EasyRdf_Serialiser_JsonLd();
            $data = $script->serialise($create_graph->getGraph(), "jsonld");
        }
        
        $result = $wpdb->insert($table,
                                array( 
                                    'graph_id' => '',
                                    'settings_id' => $p_key,
                                    'graph_source' => $graph,
                                    'graph_uri' => $image, 
                                    'graph_title' => $title, 
                                    'graph_thumb' => $thumbnail, 
                                    'graph_subject' => $subject, 
                                    'graph_creator_uri' => $creator_uri, 
                                    'graph_creator' => $creator, 
                                    'graph_description' => $description, 
                                    'graph_notice' => $notice, 
                                    'graph_terms' => $terms, 
                                    'graph_ldscript' => !empty($data) ? $data:"Null" 
                                ), 
                                array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                    );

            if($result === FALSE) {
                return array(
                    "Status" => "Error",
                    "Message" => "Error inserting image data"
                );
            }
            else {
                return array(
                    "Status" => "Success",
                    "Message" => "Image data saved successfully"
                );
            }             
        
    }
    
    /**
    * CREATE GALLERY BASED ON GRAPH DATA
    */
    function isnippet_create_gallery($directory, $table, $parent_key, $action, $graph_action) {
        
        global $wpdb;
        
        $get_graph = $wpdb->get_results( "SELECT * FROM $table WHERE settings_id = $parent_key" );
        
        if($graph_action == "Regenerate") {
            $parent_id = get_option( "is_gallery_$parent_key" );
            $posts = get_posts( array( 'post_parent' => $parent_id, 'post_type' => 'page', 'numberposts' => -1  ) );

            if (is_array($posts) && count($posts) > 0) {
                foreach($posts as $post){
                    wp_delete_post($post->ID, true);
                }
            }

			// don't delete the page, since any links to it (in wordpress) will break
			// we will re-use it and just update the content
            //wp_delete_post($parent_id, true);
            //delete_option("is_gallery_$parent_key");
        }
        
        $gallery_title = $directory;
        $gallery_name = sanitize_title($directory);

        $the_page = get_page_by_title( $gallery_title );

        if ( ! $the_page ) {
            $add_gallery = array(
                'post_title' => $gallery_title,
                'post_name' => $gallery_name,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_category' => array(1)
            );
            
            $gallery_id = wp_insert_post( $add_gallery );
            add_option( "is_gallery_$parent_key", $gallery_id );
		}
		else {
			$gallery_id = $the_page->ID;
		}
		
		$data = '<div id="gallery" class="zoomwall">';
		$pagenames = array();
		foreach($get_graph as $image) {
			$original_page_name = sanitize_title($image->graph_title);
			$page_name = $original_page_name;
			for ($i=2; in_array($page_name, $pagenames); $i++) {
				$page_name = $original_page_name."-".$i;
			}
			array_push($pagenames, $page_name);
			$this->isnippet_create_child_page($gallery_id, $image->graph_source, $image->graph_uri, $image->graph_title, $image->graph_subject, $image->graph_description, $image->graph_notice, $image->graph_terms, $image->graph_creator, $image->graph_ldscript, $page_name, $table);
			$data .= "<img src='".$image->graph_uri."' title='".$image->graph_title."' longdesc='".site_url("/$gallery_name/$page_name/")."'>";
		}

		$data .= '</div><button type="button" id="'.$parent_key.'" class="regenerate-gallery">Regenerate Gallery</button><span id="notice" style="margin-left:15px;"></span>';

		$update_post = array(
			'ID' => $gallery_id,
			'post_content' => $data
		);

		wp_update_post( $update_post );

		if($action == "snippet") {
			if($graph_action == "Regenerate") {
				$response = array(
					'Status' => 'Success',
					'Message' => 'Your gallery has been generated with new settings. Click <a href="'.site_url("/$gallery_name").'" target="_blank">here</a> to view your gallery.'
				);
			}
			else {
				$response = array(
					'Status' => 'Success',
					'Message' => 'Your gallery has been generated. Click <a href="'.site_url("/$gallery_name").'" target="_blank">here</a> to view your gallery.'
				);
			}

		}
		else if ($action == "regeneratesnippet") {
			$response = array(
				'Status' => 'Success',
				'Url' => site_url("/$gallery_name/"),
				'Message' => $gallery_title.' has been updated. Click <a href="'.site_url("/$gallery_name/").'" target="_blank">here</a> to view your gallery.'
			);
		}

		return $response;            
	}
    
    function isnippet_create_child_page($parent, $source, $image, $title, $subject, $description, $notice, $terms, $creator, $ldscript, $name, $table) {
        
global $wpdb;
        $image = $image == "Null" ? "":$image;
        $title = $title == "Null" ? "":$title;
        $subject = $subject == "Null" ? "":$subject;
        $description = $description == "Null" ? "":$description;
        $notice = $notice == "Null" ? "":$notice;
        $terms = $terms == "Null" ? "":$terms;
        $creator = $creator == "Null" ? "":$creator;
        $ldscript = $ldscript == "Null" ? "":$ldscript;
        
        $defaults = array(
            'before'           => '<p>' . __( 'Pages:' ),
            'after'            => '</p>',
            'link_before'      => '',
            'link_after'       => '',
            'next_or_number'   => 'number',
            'separator'        => ' ',
            'nextpagelink'     => __( 'Next page' ),
            'previouspagelink' => __( 'Previous page' ),
            'pagelink'         => '%',
            'echo'             => 1
        );
        
        $dom = new DOMDocument();
error_log("opening: ".$source);
$s = file_get_contents($source);
preg_match_all('/<body>(.*?)<\/body>/s', $s, $matches);

//HTML array in $matches[1]
//print_r($matches[1]);
error_log(print_r($matches,true));
$bodyInner = $matches[1][0];

//@$dom->loadHTML($f);
//$body = $dom->getElementsByTagName("body")->item(0);
//$bodyInner = "";
//foreach ($body->childNodes as $child){
//    $bodyInner .= $dom->saveXML($child);
//};
error_log($bodyInner);

        $content = '<div class="gallery-container"><div class="link"><a href="'.$source.'" target="_blank">View on ImageSnippets</a></div>';
	$content .= $bodyInner;
/*        $content .= '<div class="info">';
            $content .= '<p class="description">IMAGE DESCRIPTION<br><br>'.urldecode($description).'</p>';
            $content .= '<p><i class="fa fa-check-square"></i> <b>Title: </b>'.$title.'</p>';
            $content .= '<p><i class="fa fa-check-square"></i> <b>Subject: </b>'.$subject.'</p>';
            $content .= '<p><i class="fa fa-check-square"></i> <b>Copyright Notice: </b>'.$notice.'</p>';
            $content .= '<p><i class="fa fa-check-square"></i> <b>Copyright Terms: </b>'.$terms.'</p>';
            $content .= '<p class="creator">Creator:'.$creator.'</p>';
            $content .= '<p>User-defined image metadata, which may include copyright and ownership information, is hosted at ImageSnippets™ and is also duplicated in the image and HTML file. User copyright intentions should be observed and respected.</p>';
            $content .= '<p><a href="#!" class="btn-prev">Prev</a></p>';
*/
        $content .= '</div>';
        $content .= '<p>[is_previous] [is_next]</p>';

        $new_post = array(
            'post_content' => 'this is my placeholder',
            'post_name'    => $name,
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => $parent,
            'post_category' => array(1),
            'filter'       => true
        );
        
//	remove_filter('content_save_pre', 'wp_filter_post_kses');
        $post_id = wp_insert_post( $new_post );
//	add_filter('content_save_pre', 'wp_filter_post_kses');
        $query = "UPDATE ".$wpdb->posts." SET post_content = %s WHERE ID = %d";
        $wpdb->query($wpdb->prepare($query, $content, $post_id));
        
        add_post_meta($post_id, 'ldscript-'.$post_id, $ldscript, true);
        add_post_meta($post_id, '_lp_disable_wpautop', true, true);        
    }
    
    /**
    * ADD LD SCRIPT IN CUSTOM POST
    */
    function isnippet_add_ldscript() {
        global $post;        
        $ldscript = get_post_meta($post->ID, 'ldscript-'.$post->ID, true);	
        if($ldscript != "") {
            echo '<script type="application/ld+json">'.$this->isnippet_pretty_json($ldscript).'</script>';
        }
	echo '<link rel="stylesheet" href="https://imagesnippets.com/imgtag/adeel/style.css"/>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
<!-- Go to www.addthis.com/dashboard to customize your tools -->
<script type="text/javascript">var switchTo5x=true;</script>
<script type="text/javascript" src="https://w.sharethis.com/button/buttons.js"></script>
<script type="text/javascript">stLight.options({publisher: "2efecc01-31a9-4782-8b1b-e960300da63e", doNotHash: true, doNotCopy: true, hashAddressBar: false, shorten:false});</script>';
    }
    
    /**
    * BEAUTIFY JSON LD SCRIPT
    */
    function isnippet_pretty_json($json) {

        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '  ';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            $char = substr($json, $i, 1);

            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

            } else if(($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $result .= $char;

            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
        
    }
    
}
