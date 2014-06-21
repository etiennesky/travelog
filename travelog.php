<?php
/*
Plugin Name: Travelog
Plugin URI: http://dev.wp-plugins.org/wiki/Travelog
Description: Travelog adds a geographic dimension to WordPress by helping you keep track of the places you've been. You can create trips, add locations to posts ("posted from...") and easily create embedded <a href="http://maps.google.com/" title="GoogleMaps">GoogleMaps</a> of any places/trips in your Travelog.
Version: 2.5
Author: Shane Warner
Author URI: http://www.sublimity.ca/
Minimum WordPress Version Required: 2.0

Parts of this plugin are based on Owen Winkler's 'Geo' plugin, which can be found at http://www.asymptomatic.net/wp-hacks/

LICENSE INFORMATION:

This program is free software; you can redistribute it
and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation;
either version 2 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be
useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public
License along with this program; if not, write to the Free
Software Foundation, Inc., 59 Temple Place, Suite 330,
Boston, MA 02111-1307 USA

INSTRUCTIONS:

Drop the 'travelog' folder into your WordPress plugins directory, and then
activate the Travelog plugin on the Plugins tab of the WordPress admin console.

See http://dev.wp-plugins.org/wiki/Travelog for more
detailed installation instructions and usage information.

*/
global $table_prefix;
$table = $table_prefix . 'travelog';
define('DB_TABLE', $table);
define('DB_TRIPS_TABLE', $table_prefix . 'travelog_trips');
define('DOMAIN', 'Travelog');

load_plugin_textdomain(DOMAIN);

// Static class Travelog protects these functions, so set them with an array('Travelog', tag)
add_action('edit_form_advanced', array('Travelog', 'edit_form_advanced'));
add_action('wp_head', array('Travelog', 'coordinate_metatags'));
add_action('save_post', array('Travelog', 'update_post'));
add_action('admin_menu', array('Travelog', 'add_menus'));
add_filter('the_content', array('Travelog', 'inline_locations'));
// add_action('template_redirect', array('Travelog', 'map_archive'));  Redirect archives to a map based interface

// Enable the TinyMCE Travelog plugin
// disabled because it is not compatible with current TinyMCE
/* add_filter('admin_footer', array('Travelog', 'travelog_quicktagjs'),0); */
/* add_filter('mce_plugins', array('Travelog', 'travelog_extended_editor_mce_plugins'), 0); */
/* add_filter('mce_buttons', array('Travelog', 'travelog_extended_editor_mce_buttons'), 0); */
/* add_filter('mce_valid_elements', array('Travelog', 'travelog_extended_editor_mce_valid_elements'), 0); */

//If the user has just activated the plugin, initialize the database
if(isset($_GET['activate']) && $_GET['activate'] == 'true') {
	add_action('init', array('Travelog','prepare_db'));
}

add_shortcode('travelog', 'travelog_shortcode');


class location_db {
	// Declare the location object that will be used throughout this plugin
	var $id;
	var $name = '';
	var $category = '';
	var $address = '';
	var $city = '';
	var $state = '';
	var $country = '';
	var $description = '';
	var $latitude = 'NULL';
	var $longitude = 'NULL';
	var $elevation = 'NULL';
	var $dates_visited = '';
	var $trips = '';
}

class location extends location_db {	
	// Calculated properities (not stored in database)
	var $visits = 0;
	var $posts_from = 0;

	function get_visits() {
		// Parse $dates_visited into an array of visits
		$visits = array();
		if('' != $this->dates_visited) $visits = explode(",", $this->dates_visited);
		return $visits;
	}
	
	function get_datetimes() {
		// Parse $dates_visited into an array of visits
		$visits = array();
		$datetime = array();
		if('' != $this->dates_visited) {
			$visits = explode(",", $this->dates_visited);
			foreach($visits as $visit) {
				$temp = explode(" ", $visit);
				$datetime[] = array('date' => $temp[0], 'time' => $temp[1]); 
			}
		}
		return $datetime;
	}
	
	function loadInfo($loadArray) {
		foreach($loadArray as $varname => $value) {
			if(array_key_exists($varname, get_object_vars($this))) {
				$this->$varname = $loadArray[$varname];
			}
		}
        // TODO check this
        $this->contents = "<b>" . $this->name . "</b>";
	}
	
	function get_posts() {
		global $wpdb;
		$query = "SELECT po.post_title as title, po.ID, po.comment_count as comments FROM $wpdb->posts po LEFT JOIN $wpdb->postmeta pm ON po.ID = pm.post_id WHERE pm.meta_key = '_travelog_location_id' AND pm.meta_value = $this->id AND po.post_status = 'publish' ORDER BY po.post_date DESC";
		$this->posts = $wpdb->get_results($query, ARRAY_A);
	}
}

class trip_db {
	// Declare the trips object that will be used throughout this plugin
	var $id;
	var $name = '';
	var $start_date = '';
	var $end_date = '';
	var $description = '';
	var $collection = '';
}

class trip extends trip_db {
	// Calculated vars (not stored in database)
	var $stops = array();
	
	function loadInfo($loadArray) {
		foreach($loadArray as $varname => $value) {
			if(array_key_exists($varname, get_object_vars($this))) {
				$this->$varname = $loadArray[$varname];
			}
		}
	}
	
	function get_itinerary() {
		// Returns an array of location ids sorted in order they were visited on the trip
		global $wpdb;
		$this->stops = array();$itinerary = array();
		$start_date = strlen($this->start_date) > 12 ? $this->start_date : $this->start_date . ' 00:00';
		$end_date = strlen($this->end_date) > 12 ? $this->end_date : $this->end_date . ' 23:59';
		// Create an array of relevant locations with key being location_id
		$query_sql = 'SELECT id, dates_visited, name FROM ' . DB_TABLE . ' WHERE FIND_IN_SET('.$this->id.', trips) > 0';
		$locations = $wpdb->get_results($query_sql);
		if(count($locations) > 0) { // We now know which all locations on this trip, but need to find which visits are within trip period
			foreach($locations as $location) {
				$visits = explode(",", $location->dates_visited);
				foreach($visits as $visit) {
					if((0 == strcmp($visit, $start_date) || 0 < strcmp($visit, $start_date)) && (0 == strcmp($visit, $end_date) || 0 > strcmp($visit, $end_date))) {
						// This visit occured during the trip time window
						$itinerary[] = array('date' => "$visit", 'location_id' => $location->id, 'name' => $location->name); 
					}
				}
			}
			if(count($itinerary) > 0) { // Now sort $itinerary by date element
				usort($itinerary, create_function('$a,$b','return strcmp($a[date], $b[date]);'));
				$this->stops = $itinerary;
			}
		} // no locations are associated with this trip
	} // end get_itinerary()
	
	function get_distance() { // Returns the total distance (straight-line) of the trip
		if(count($this->stops) == 0) $this->get_itinerary();
		$distance = 0;
		foreach($this->stops as $stopkey => $stop) {
			$locationIds[] = $stop['location_id'];
		}
        if(empty($locationIds))
            return 0;
		$locationIds = array_unique($locationIds);
		$locations = Travelog::get_locations('','','','',implode(',',$locationIds));
		foreach($this->stops as $stopkey => $stop) {
			if($stopkey === 0) {
				$prevLocationId = $stop['location_id'];
				continue;
			}
			$distance += distance_between($locations[$prevLocationId],$locations[$stop['location_id']],'k');
			$prevLocationId = $stop['location_id'];
		}
		return round($distance,1);
	}
} // end Trip Class

class Travelog {
// Protect these pretty functions from similarly named function in other plugins
	
	function add_menus () {
		// Adds the Travelog tabs to the Admin menus in the Options and Manage categories
		add_options_page(__('Travelog Options', DOMAIN), __('Travelog', DOMAIN), 5, basename(__FILE__), array('Travelog', 'manage_options'));
		add_management_page(__('Travelog Manager', DOMAIN), __('Travelog', DOMAIN), 5, basename(__FILE__), array('Travelog','manage_travelog'));
	}

// ################################################## End function add_menus ()
	
	function adminheader() {
	
	if ($_GET['page'] == 'travelog.php') {
		// Outputs the CSS & HTML that makes the nice menu on the Manage - Travelog page
		?>
		<link rel="stylesheet" href="<?php bloginfo('wpurl');?>/wp-content/plugins/travelog/travelog_admin.css" type="text/css" />
		<ul id="travelog_menu">
			<li<?php if ('locations' == $_GET['area'] || !isset($_GET['area'])){ echo ' class="current"'; }?>><a href="tools.php?page=travelog.php">Locations</a></li>
			<li<?php if ('trips' == $_GET['area']){ echo ' class="current"'; }?>><a href="tools.php?page=travelog.php&area=trips">Trips</a></li>
			<li<?php if ('categories' == $_GET['area']){ echo ' class="current"'; }?>><a href="tools.php?page=travelog.php&area=categories">Categories</a></li>
		</ul>
        <BR> 
		<?php
		}
	}
	
// ################################################## End function adminheader()	
	
	function manage_travelog() {
		// Manage (add/update/delete) all the info about locations, trips and categories
		global $wpdb;

		// Determine what page to display
		if(isset($_GET['area'])) {
			switch ($_GET['area']) {
				case "trips" :
					// Manage trips
					if('edit' == $_GET['action']) {
						include('travelog_trips_edit.php');
					}else{
						include('travelog_trips.php');
					}
					break;
				case "categories" :
					// Manage categories
					include('travelog_categories.php');
					break;
				case "locations" :
					// Manage locations
					if('edit' == $_GET['action'] || 'add' == $_GET['action']) {
						// Add or edit a location
						include('travelog_edit_location.php');
					}else{
						// view or update locations
						include('travelog_manage.php');
					}
					break;
				default :
					// Show default (manage locations)
					include('travelog_manage.php');
					break;
			}
		}else{
			// Show default (manage locations)
			include('travelog_manage.php');
		}
		
	}
		
// ################################################## End function manage_travelog()
	
	function manage_options() {
		// Displays the Options admin interface for the plugin.
		global $wpdb;

		$current_elev_unit = get_option('travelog_elevation_unit');
		$new_elev_unit = $_POST['travelog_elevation_unit'];
		
		if($current_elev_unit != $new_elev_unit) {
			if($new_elev_unit == 'ft') {
				//convert m to ft
				$wpdb->query("UPDATE ". DB_TABLE . " SET elevation = (elevation*3.2808399)");
			}elseif($new_elev_unit == 'm') {
				//convert ft to m
				$wpdb->query("UPDATE ". DB_TABLE . " SET elevation = (elevation*0.3048)");
			}
		}
			
		// Handle changes to options
		if(isset($_POST['submit'])) {
			update_option('travelog_elevation_unit', $_POST['travelog_elevation_unit']);
			update_option('travelog_default_location_id', $_POST['travelog_default_location_id']);
			update_option('travelog_show_edit_map', $_POST['travelog_show_edit_map']);
			update_option('travelog_googlemaps_key', $_POST['travelog_googlemaps_key']);
			update_option('travelog_googlemaps_view', $_POST['travelog_googlemaps_view']);
			update_option('travelog_map_url_type', $_POST['travelog_map_url_type']);
			update_option('travelog_googlemaps_width', $_POST['travelog_googlemaps_width']);
			update_option('travelog_googlemaps_height', $_POST['travelog_googlemaps_height']);
			update_option('travelog_googlemaps_zoom', $_POST['travelog_googlemaps_zoom']);
			echo '<div class="updated"><p><strong>' . __('Options updated.', DOMAIN) . '</strong></p></div>';  
		}
		
		//Display options page
		include('travelog_options.php');
	}
		
// ################################################## End function manage_options()

	function add_location($location) {
		// Inserts a location entry into the Travelog database with values set from location object passed as the parameter.
		global $wpdb;
		
		// Array of variables to process, with variable name as the key and its default value as the value
		$allowed_vars = get_class_vars('location_db');
		
		// Assemble the SQL query
		$insert_sql = "INSERT INTO " . DB_TABLE ." ( ";
		foreach($allowed_vars as $var => $default) {
			$insert_sql .= $var . ", ";
		}
		$insert_sql = substr($insert_sql, 0, -2) . " ) VALUES ( ";
		foreach($allowed_vars as $var => $default) {
			if('NULL' == $location->$var) {
				$insert_sql .=  $location->$var . ", ";
			}else{
				$insert_sql .= "'" . $location->$var . "', ";
			}
		}
		$insert_sql = substr($insert_sql, 0, -2) . ")";
		
		if (FALSE !== $wpdb->query($insert_sql)) {
			return '<p>'.__('Location added to Travelog sucessfully', DOMAIN).'</p>';
		}
		else {
			return '<p>'.__('An error has occured and the location was not added', DOMAIN).'</p>';
		}
	} 

// ################################################## End function add_location($location)

	function prepare_db () {
		// Creates and initalizes the Travelog database table and WP options.
		global $table_prefix, $wpdb, $user_level;
	
		$table_name = $table_prefix . "travelog";
	
		get_currentuserinfo();
		if ($user_level < 8) { return; }
	
		$result = mysql_list_tables(DB_NAME);
		$tables = array();
	
		while ($row = mysql_fetch_row($result)) { $tables[] = $row[0]; }
	  
		$first_install = false;
	  
		if (!in_array($table_name, $tables)) {
			$version = '0';
		}elseif(!in_array($table_prefix . 'travelog_trips', $tables)) {
			$version = '1.1';
		}else{
			$version = '2.5';
		}
		
		// Initialize/update database - always runs & automatically detects what changes are necessary
		$sql = "CREATE TABLE ".$table_name." (
				 id mediumint(9) NOT NULL AUTO_INCREMENT,
				 name tinytext NOT NULL,
				 address tinytext NOT NULL,
				 city tinytext NOT NULL,
				 state tinytext NOT NULL,
				 country tinytext NOT NULL,
				 category tinytext NOT NULL,
				 description text NOT NULL,
				 latitude double DEFAULT NULL,
				 longitude double DEFAULT NULL,
				 elevation float DEFAULT NULL,
				 dates_visited text,
				 trips tinytext NOT NULL,
				 UNIQUE KEY id (id)
			   );";

		 require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		 dbDelta($sql);
		 
		 // Add trips database
		 $sql = "CREATE TABLE ". DB_TRIPS_TABLE ." (
				 id mediumint(9) NOT NULL AUTO_INCREMENT,
				 name tinytext NOT NULL,
				 description text NOT NULL,
				 start_date tinytext NOT NULL,
				 end_date tinytext NOT NULL,
				 collection tinytext NOT NULL,
				 UNIQUE KEY id (id)
			   );";
			   
		 dbDelta($sql);

		// Perform upgrade/install (no break; because each old version needs a complete upgrade)
		switch($version) {
			case '0' :
				// New install, do it all
				add_option('travelog_default_location_id', "");
				add_option('travelog_elevation_unit', "m");
				add_option('travelog_categories', array());
				add_option('travelog_show_edit_map', '1');
				add_option('travelog_googlemaps_key', '');
				add_option('travelog_googlemaps_view', 'map');
				add_option('travelog_map_url_type', 'coordinates');
				echo '<div class="updated"><p><strong>' . __('Travelog database installed and initalized sucessfully.', DOMAIN) . '</strong></p></div>';
			case '1.1' :
				add_option('travelog_googlemaps_key_embedded', '');
				add_option('travelog_googlemaps_width', '300');
				add_option('travelog_googlemaps_height', '300');
				add_option('travelog_googlemaps_zoom', 3);
				$wpdb->query('ALTER TABLE ' . DB_TABLE . ' CHANGE latitude latitude DOUBLE NULL DEFAULT NULL, CHANGE longitude longitude DOUBLE NULL DEFAULT NULL, CHANGE elevation elevation FLOAT NULL DEFAULT NULL');
				
				// Remove legacy user_id field from v1.0 installs
				$fields = $wpdb->get_results('SHOW COLUMNS FROM ' . DB_TABLE, ARRAY_A);
				foreach($fields as $key => $field_record) {
					if('user_id' == $field_record['Field']) {
						$wpdb->query('ALTER TABLE ' . DB_TABLE . ' DROP COLUMN user_id');
					}
				}
			case '2.0' :
				delete_option('travelog_googlemaps_key_embedded');
		}

	}

// ################################################## End function travelog_install()

	function edit_form_advanced() {
		// Outputs form to post edit page to allow location information to be set
	
		include('travelog_edit_post.php');
	}

// ################################################## End function edit_form_advanced()

	function get_locations($category = '', $limit = '', $order = 'id', $search = '', $ids = '') {
		// Returns an array of objects, one for each of the locations in the Travelog database
		global $wpdb;

		// Prepare SQL
		$query_sql = "SELECT tl.*, CHAR_LENGTH(tl.dates_visited) as visits_length, count(pm.meta_value) as posts_from FROM ".DB_TABLE." tl LEFT JOIN $wpdb->postmeta pm ON tl.id = pm.meta_value";
		
		$q_params = array('category','search','ids');
		if($category != '' || $search != '' || $ids != '') {
			$query_sql .= ' WHERE';
			
			if($category != '') $query_sql .= " tl.category = '$category' AND";
			if($search != '') $query_sql .= "  tl.name LIKE '%$search%' AND";
			if($ids != '') {
				$query_sql .= ' tl.id IN ('.$ids.') AND';
			}
		
			// Remove trailing AND from query
			$query_sql = substr($query_sql,0,-4);
		}
		
		// Determine sort order
		switch ($order) {
			case 'recent' :
				$sort_clause = 'tl.dates_visited DESC';
				break;
			case 'visits' :
				$sort_clause = 'visits_length DESC';
				break;
			case 'posts_from' :
				$sort_clause = 'posts_from DESC';
				break;
			case 'name' :
				$sort_clause = 'tl.name ASC';
				break;	
			case 'id' :
			default :
				$sort_clause = 'tl.id DESC';
				break;
		}
		// Set GROUP BY so we get the proper posts_from count
		$query_sql .= ' GROUP BY tl.id';
		
		$query_sql .= ' ORDER BY ' . $sort_clause;
		
		// Set result limit
		if($limit != '') $query_sql .= " LIMIT $limit";

		// Get locations
		$results = $wpdb->get_results($query_sql, ARRAY_A);
		
		// Adjust results array so that the key is the location id
		if($results) {
			foreach ($results as $result) {
				$locations[$result['id']] = new location();
				$locations[$result['id']]->loadInfo($result);
			}
			
			foreach ($locations as $id => $location) {
				// add the visits element based on the number of visits
				$visits = $location->get_visits();
				if(count($visits) > 0) {
					$locations[$id]->visits = count($visits);
				}else{
					$locations[$id]->visits = 0;
				}
				
			
			}
			
			return $locations;
		}else{
			return;
		}
	}

// ################################################## End function get_locations()

	function get_location($id) {
		// Returns an object of all the information about the location specified by $id
		
		global $wpdb;
		$query_sql = "SELECT tl.*, count(pm.meta_value) as posts_from FROM ".DB_TABLE." tl LEFT JOIN $wpdb->postmeta pm ON tl.id = pm.meta_value  WHERE tl.id = '$id' GROUP BY tl.id LIMIT 1";
		$result = $wpdb->get_row($query_sql, ARRAY_A);
		$location = new location();
		$location->loadInfo($result);
		
		// Get the number of visits
		$visits = $location->get_visits();
		$location->visits = count($visits);
		
		return $location;    
	}

// ################################################## End function get_location()
	
	function get_post_location() {
		// Returns the $location object with all the info about the location associated with the post.
		global $post;
	
		$post_location_id = get_post_meta($post->ID, '_travelog_location_id', true);
		if($post_location_id != "") {
			$location = Travelog::get_location($post_location_id);
			return $location;
		}elseif($default_location_id = get_option('travelog_default_location_id')) {
			$location = Travelog::get_location($default_location_id);
			return $location;
		}else{
			return NULL;
		}
	}

// ################################################## End function get_post_location()

	function get_categories() {
		// Returns an array of all the categories in the Travelog database
		global $wpdb;
	
		$categories = get_option('travelog_categories');
		
		return $categories;
	
	}
	
// ################################################## End function get_categories()

	function get_trips($limit = '', $order = '', $search = '', $ids = '') {
		// Returns an array of all the trips in the Travelog database
		global $wpdb;
		
		switch ($order) {
			case 'id' :
				$order_sql = '\'id\' DESC';
				break;
			case 'name' :
				$order_sql = '\'name\' ASC';
				break;
			case 'recent':
				$order_sql = '\'end_date\' DESC';
				break;
			case 'oldest':
				$order_sql = '\'end_date\' ASC';
				break;
			default :
				$order_sql = '\'id\' DESC';
				break;	
		}
		
		$query_sql = "SELECT * FROM ".DB_TRIPS_TABLE;
			
		if($search != '' || $ids != '') {
			$query_sql .= ' WHERE';
			if($search != '') $query_sql .= " name LIKE '%$search%' AND";
			if($ids != '') $query_sql .= ' id IN ('.$ids.') AND';
			$query_sql = substr($query_sql,0,-4);
		}
		$query_sql .= " ORDER BY $order_sql";
		if($limit != '') $query_sql .= " LIMIT $limit";
		
		$results = $wpdb->get_results($query_sql, ARRAY_A);

		if($results) {
			foreach ($results as $result) {
				$trip = new trip();
				$trip->loadInfo($result);
				$trips[$trip->id] = $trip;
			}
			return $trips;
		}else{
			return array();
		}

		return $results;
	}
	
// ################################################## End function get_trips()

	function get_trip($trip_id) {
		// Returns a trip object containing all the info about the trip specified in $trip_id
		global $wpdb;
		$query_sql = "SELECT * FROM " . DB_TRIPS_TABLE . " WHERE id = '$trip_id' LIMIT 1";
		$result = $wpdb->get_row($query_sql, ARRAY_A);
		if(! $result) return false;
		$trip = new trip();
		$trip->loadInfo($result);
		$trip->get_itinerary();
		
		return $trip;
	}
	
// ################################################## End function get_trip()

	function update_post($post_id) {
		// Updates the Travelog location for a post when the post is updated.
		global $wpdb;
		
		$new_location_id = $_POST["travelog_location_id"];

		// Create a new location if requested
		if($_POST['travelog_create_new_location'] && $_POST["travelog_name"] != "") {
				// Process the incoming form values
				$formvar_prefix = 'travelog_';
				
				// Array of variables to process, with variable name as the key and its default value as the value
				$allowed_vars = get_class_vars('location');
				
				// The heavy lifting, check if each allowed_var was passed and is not empty
				foreach($allowed_vars as $var => $default) {
					if(array_key_exists($formvar_prefix.$var, $_POST) && '' != $_POST[$formvar_prefix.$var]) {
						$location->$var = $_POST[$formvar_prefix.$var];
					}else{
						$location->$var = $default;
					}
				}

				if($location->dates_visited == 'yyyy/mm/dd') $location->dates_visited = '';
				else if($location->dates_visited == 'yyyy/mm/dd hh:mm') $location->dates_visited = '';
                
				$message = Travelog::add_location($location);
				
				$locations = Travelog::get_locations();
				foreach($locations as $key => $location) {
					if($location->name == $_POST["travelog_name"]) $new_location_id = $location->id;
				}
        }

		//echo "new loc1!";print_r($_POST); exit;
		if($new_location_id != "") { // Only run this if there is a location to associate the post/page (pages call this too!) with
			// If a new location has been added, create it and then retreive its id into $new_location_id			
			
			// Add/update post-meta value to reflect new travelog location_id
			if(get_post_meta($post_id, "_travelog_location_id", true)) {
				update_post_meta($post_id, "_travelog_location_id", $new_location_id);
			}else{
				add_post_meta($post_id, "_travelog_location_id", $new_location_id);
			}
		
			// Add the date to the dates_visited for the location associated with that post
			if( ($_POST["travelog_add_visit"] != "")
				&& ($_POST["travelog_add_visit"] != "yyyy/mm/dd")
				&& ($_POST["travelog_add_visit"] != "yyyy/mm/dd hh:mm") ) {
				$location = Travelog::get_location($new_location_id);
				$dates = $location->get_visits();
				//$dates = array_merge($dates, Array($_POST["travelog_add_visit"]));
				$tmp_date = $_POST["travelog_add_visit"];
				if($_POST["travelog_add_visit_time"] != "" && $_POST["travelog_add_visit_time"] != "hh:mm")
					$tmp_date .= " " . $_POST["travelog_add_visit_time"];
				$dates = array_merge($dates, Array($tmp_date));
				arsort($dates);
				$new_dates_visited = implode(",", $dates);
				$wpdb->query("UPDATE ".DB_TABLE." SET dates_visited = '".$new_dates_visited."' WHERE id = $location->id");
			}
			
			// Delete post-meta value to if location information is to be removed
			if(isset($_POST["travelog_unset_location"])) {
				delete_post_meta($post_id, "_travelog_location_id");
			}
		}
	}

// ################################################## End function update_post()

function map_type ($map_type, $context) {
		// Translates internal Travelog map_type constants to GoogleMap constants
		switch ($map_type) {
			case "map" :
				if('url' == $context) { $map_type_c = "m"; }else{ $map_type_c = "G_NORMAL_MAP"; }
				break;
			case "satellite" :
				if('url' == $context) { $map_type_c = "k"; }else{ $map_type_c = "G_SATELLITE_MAP"; }
				break;
			case "hybrid" :
				if('url' == $context) { $map_type_c = "h"; }else{ $map_type_c = "G_HYBRID_MAP"; }
				break;
		}
		
		return $map_type_c;
	}

// ################################################## End function googlemaps_javahook()

	function coordinate_metatags() {
	// Outputs the location coordinates info as meta tags in the blog head.

		global $wp_query;

		$post_location_id = get_post_meta($wp_query->post->ID, "_travelog_location_id", true);
		
		if($post_location_id != "") {
			$location = Travelog::get_location($post_location_id);
			$title = convert_chars(strip_tags(get_bloginfo("name")))." - ".$wp_query->post->post_title;
		}elseif($default_location_id = get_option("travelog_default_location_id")) {
			$location = Travelog::get_location($default_location_id);
			$title = convert_chars(strip_tags(get_bloginfo("name")));
		}else{
			return "";
		}
		
		echo "<meta name=\"ICBM\" content=\"$location->latitude, $location->longitude\" />\n";
		echo "<meta name=\"DC.title\" content=\"$title\" />\n";
		echo "<meta name=\"geo.position\" content=\"$location->latitude;$location->longitude\" />\n";
	}

// ################################################## End function coordinate_metatags()
		
	function coordinate_DMS($value, $coordinate) {
		// Converts from decimal degrees to DMS
		// returns a string of the DMS coordinate
		if($coordinate == "latitude") {
			if($value < 0) { $dir = "S"; }else{ $dir="N"; }
		}elseif($coordinate == "longitude") {
			if($value < 0) { $dir = "W"; }else{ $dir="E"; }
		}else{
			die("Must specify coordinate type, either \"latitude\" or \"longitude\"");
		}
		$value = abs($value);
		$deg = floor($value);
		$min = ($value - $deg)*60;
		$sec = round(($min - floor($min))*60);
		$min = floor($min);
		
		if($min<10) $min = "0".$min;
		if($sec<10) $sec = "0".$sec;
		return "$deg"."&#176; $min"."&#039; $sec&#034; $dir";
	}
	
// ################################################## End function coordinate_DMS()

	function inline_locations($content = "") {
		if ("" == $content) { return; }
		
		$matches = array();
		$replacement = array();
		$counter = 0;
		
		// look for Travelog Link tages (all instances of "<travelog ... >some text</travelog>" in the content)
			preg_match_all("/<(travelog)([^>]*)>(.*)(<\/\\1>)/", $content, $matches);
			// returns  2-D array where first dimension is preg expression, and second is the result from each consecutive search
			// $matches[0] is whole tag, $matches[1] is open tag name, $matches[2] is tag attributes, $matches[3] is text within tag and
			// $matches[4] is close tag. So $matches[3][2] is the text between the second set of <travelog></travelog> tags
			
			if (count($matches[0]) > 0) {
			
				foreach ($matches[0] as $key => $value) {
					$inline_tags[$key]["tag"] = $matches[0][$key];
					$inline_tags[$key]["attributes"] = $matches[2][$key];
					$inline_tags[$key]["text"] = $matches[3][$key];
				}
				
				// for each instance, let's try to parse it
				foreach ($inline_tags as $key => $tag) {
					$type = '';
					if(stristr($tag["attributes"], "id=")) {
						preg_match("/id=[\"]?([0-9]+)[\"]?/", $tag["attributes"], $location_id);
						//location_id is an array with [0] being whole id attribute, and [1] beign just the value
					}
					
					if(stristr($tag["attributes"], "use=")) {
						preg_match("/use=\"([^\"]+)\"/", $tag["attributes"], $use);
						//use is an array with [0] being whole id attribute, and [1] beign just the value
					}
					
					if(stristr($tag["attributes"], "view=")) {
						preg_match("/view=\"([^\"]+)\"/", $tag["attributes"], $map_type);
						//map_type is an array with [0] being whole id attribute, and [1] beign just the value
					}
					
					if(stristr($tag["attributes"], "zoom=")) {
						preg_match("/zoom=\"([0-9]+)\"/", $tag["attributes"], $zoom);
						//zoom is an array with [0] being whole id attribute, and [1] beign just the value
					}
					
					// Create link tag
					$map_url = Travelog::map_location_url("GoogleMaps", $location_id[1], $use[1], $map_type[1], $zoom[1]);
					$replacement = "<a href=\"$map_url\" title=\"Map location\">$tag[text]</a>";
					
					// Make the replacement
					if (stristr($content, "<br />\n".$tag["tag"])) {
						$content = str_replace("<br />\n".$tag["tag"], " ".$replacement, $content);
					}else{
						$content = str_replace($tag["tag"], $replacement, $content);
					}
				}
			}
			
		// Look for Travelogmap tags <!--travelogmap attr="value" attr="value" .. -->
			$matches = array();
			preg_match_all("/<!--travelogmap(.*?)-->/", $content, $matches);
			// returns  2-D array where first dimension is preg expression, and second is the result from each consecutive search
			// $matches[0] is whole tag and $matches[1] is tag attributes
			
			if (count($matches[0]) > 0) {
			
				foreach ($matches[0] as $key => $value) {
					$maptags[$key]["tag"] = $matches[0][$key];
					$maptags[$key]["attributes"] = $matches[1][$key];
				}

				// Allowable attributes to the <travelogmap /> tag
				$attrs = array('ids', 'category', 'trips', 'map_type', 'height', 'width', 'zoom', 'controls', 'show_types', 'scale', 'show_info', 'marker', 'add_points');
				
				// for each instance of the <travelogmap /> tag, let's try to parse it
				foreach ($maptags as $key => $tag) {
					$map_params = array();
					// Extract each attribute and put it into the associative array $map_params with key being the attribute and the value being the attribute value
					foreach($attrs as $attr) {
						if(stristr($tag["attributes"], "$attr=")) {
							preg_match("/$attr=\"([^\"]+)\"/", $tag["attributes"], $result);
							//result is an array with [0] being whole attribute assignment, and [1] beign just the attribute value
							$map_params[$attr] = $result[1];
						}
					}
					// Remove the <travelogmap /> tag, and instead add embedded map HTML
					if (stristr($content, "<br />\n".$tag["tag"])) {
						$content = str_replace("<br />\n".$tag["tag"], Travelog::embed_map($map_params), $content);
					}else{
						$content = str_replace($tag["tag"], Travelog::embed_map($map_params), $content);
					}
				}
			}
		
		return $content;
	}

// ################################################## End function inline_locations()


	function embed_map($map_params) {
	
		static $travelogNumMaps;
		if(!isset($travelogNumMaps)) $travelogNumMaps =  0;
	
		// $map_params is an associative array with keys equal to GET variables for travelog_embedded.php (listed below)
		$params = array('ids', 'category', 'trips', 'map_type', 'height', 'width', 'zoom', 'controls', 'show_types', 'scale', 'show_info', 'marker', 'add_points');

		// Check if map initializations vars were passed, and if not, get defaults
		if(!array_key_exists('width', $map_params) || '' == $map_params['width']) {
			$map_params['width'] = get_option('travelog_googlemaps_width');
		}
		if(!array_key_exists('height', $map_params) || '' == $map_params['height']) {
			$map_params['height'] = get_option('travelog_googlemaps_height');
		}
		if(!array_key_exists('map_type', $map_params) || '' == $map_params['map_type']) {
			$map_params['map_type'] = get_option('travelog_googlemaps_view');
		}
		
		if(!array_key_exists('scale', $map_params) || '' == $map_params['scale']) {
			$map_params['scale'] = 1; // show scale by default
		}
		if(!array_key_exists('show_types', $map_params) || '' == $map_params['show_types']) {
			$map_params['show_types'] = 1; // show map type buttons by default
		}
		
		if('all' == $map_params['ids']) {
			$map_params['ids'] = implode(',' , array_keys(Travelog::get_locations('','','','',''))); // get ids for all locations
		}
		if('all' == $map_params['trips']) {
			$map_params['trips'] = implode(',' , array_keys(Travelog::get_trips('','','',''))); // get ids for all trips
		}
		if('last(' == substr($map_params['ids'],0,5)) {
			$map_params['ids'] = implode(',' , array_keys(Travelog::get_locations('',substr($map_params['ids'],5,-1),'recent','',''))); // get ids for X most recent locations
		}
		if('last(' == substr($map_params['trips'],0,5)) {
			$map_params['trips'] = implode(',' , array_keys(Travelog::get_trips(substr($map_params['trips'],5,-1),'recent','',''))); // get ids for X most recent trips
		}
		
		$show_info =  (isset($_GET['show_info'])) ? $_GET['show_info'] : ''; // Show info windows above points?
		$marker = (isset($_GET['marker'])) ? $_GET['marker'] : ''; // marker to use for points
		$add_points = (isset($_GET['add_points'])) ? $_GET['add_points'] : 0; // allow new points to be added

		// Determine output settings
		if ('' == $map_params['controls']) {
			// value not explicitly passed
			if ($map_params['height'] > 300) {
				$map_params['controls'] = 'large';
			}elseif ($map_params['height'] < 200) {
				$map_params['controls'] = 'zoom';
			}else{
				$map_params['controls'] = 'small';
			}
		}
		
		// Output HTML Code
		$htmlCode = '
    	    <script src="//maps.googleapis.com/maps/api/js?sensor=false&v=3.exp&key='.get_option('travelog_googlemaps_key').'" type="text/javascript"></script>
			<script type="text/javascript" src="'.get_bloginfo('wpurl').'/wp-content/plugins/travelog/mapfunction.js"></script>
			<script type="text/javascript"><!--
				var XMLAddress = "'.get_settings('siteurl').'/wp-content/plugins/travelog/travelog_xml.php";
				//--></script>
			<style type="text/css"><!--
				/* load VML for IE so polylines draw */
				v\:* { behavior:url(#default#VML); }
				//--></style>';
		
        if ($map_params['type'] == 'editLocation') {
            //https://developers.google.com/maps/documentation/javascript/examples/geocoding-simple
        $htmlCode .= '       
            <div id="panel">
            <input id="address" type="textbox" value="">
            <input type="button" value="Lookup" onclick="codeAddress(maps['.$travelogNumMaps.'],\'address\')">
            <input type="button" value="Set values" onclick="updateAddress(maps['.$travelogNumMaps.'],\'address\',\'edit_name\')">
            </div>';
        }

		$htmlCode .= '
			<div id="map'.$travelogNumMaps.'" style="width:'.$map_params['width'].'px;height:'.$map_params['height'].'px;margin:0 auto;padding:0;border: 1px solid #999;"></div>
			<script type="text/javascript"><!--
				initializeMap(\''.$travelogNumMaps.'\', \'map'.$travelogNumMaps.'\', null, \''.$map_params['map_type'].'\', \''.$map_params['controls'].'\', \''.$map_params['show_types'].'\',\''.$map_params['scale'].'\');';
		
		if($map_params['ids'] != '') $htmlCode .= "maps[$travelogNumMaps].mapLocations('".$map_params['ids']."');";
		if($map_params['trips'] != '') $htmlCode .= "maps[$travelogNumMaps].mapTrips('".$map_params['trips']."');";
		
		$htmlCode .= '
		//--></script>';
		
		$travelogNumMaps++; // Increment map counter
		
		return $htmlCode;
	}
	
// ################################################## End function embedded_map()
	
	function travelog_extended_editor_mce_plugins($plugins) {
		// Enables the travelog plugin for TinyMCE
		array_push($plugins, 'travelog');
		return $plugins;
	}
	
	function travelog_extended_editor_mce_buttons($buttons) {
		// Adds the Travelog button to the TinyMCE toolbar
		array_push($buttons, 'travelog');
		return $buttons;
	}
	
	function travelog_extended_editor_mce_valid_elements($valid_elements) {
		// Allows the travelog and travelogmap tags in TinyMCE
		$valid_elements .= 'travelog,travelogmap';
		return $valid_elements;
	}
	
	function travelog_quicktagjs() {
		// Adds the Travelog button to the WP quicktags bar
		if(strpos($_SERVER['REQUEST_URI'], 'post.php') || strpos($_SERVER['REQUEST_URI'], 'page-new.php') || strpos($_SERVER['REQUEST_URI'], 'bookmarklet.php')) {
			?>
			<script language="JavaScript" type="text/javascript"><!--
				var wp_toolbar = document.getElementById("ed_toolbar");
				if(wp_toolbar){
					var theButton = document.createElement('input');
					theButton.type = 'button';
					theButton.value = 'Travelog';
					theButton.onclick = travelog_open;
					theButton.className = 'ed_button';
					theButton.title = 'Insert Travelog Tag';
					theButton.id = "ed_travelog";
					wp_toolbar.appendChild(theButton);
				}
				function travelog_open() {
					var form = 'post';
					var field = 'content';
					var url = '<?php get_bloginfo("wpurl"); ?>/wp-includes/js/tinymce/plugins/mcetravelog/travelog_mce.php?form='+form+'&field='+field+'&tinymce=0';
					var name = 'travelog';
					var w = 550;
					var h = 500;
					var valLeft = (screen.width) ? (screen.width-w)/2 : 0;
					var valTop = (screen.height) ? (screen.height-h)/2 : 0;
					var features = 'width='+w+',height='+h+',left='+valLeft+',top='+valTop+',resizable=1,scrollbars=0';
					var travelogimageWindow = window.open(url, name, features);
					travelogimageWindow.focus();
				}
			
			//--></script>
		
			<?php
		}
	}
	
	// ################################################## End TinyMCE functions
	
	function map_location_url($map = "GoogleMaps", $location_id = "", $map_using = "", $map_type = "", $zoom = "") {
		// Returns a URL for a map of the location whose ID is provided, from the mapping service specified in $map. See below for list of $map options.
	
		if($location_id == "") {
			$location = Travelog::get_post_location();
		}else{
			$location = Travelog::get_location($location_id);
		}
		
		// Determine how to map the location (coordinates or address)
		if($map_using != "") {
			// User overides default via parameter $map_type
			$map_url_type = $map_using;
		}else{
			// use default type
			$map_url_type = get_option("travelog_map_url_type");
		}
		
		// Array of map URLs with first index being mapping service name, second index being either "coordinates" or "address" which determines what info is used to map the location
			
		// Mapquest URLs
		$map_urls["MapQuest"]['coordinates'] = "http://www.mapquest.com/maps/map.adp?latlongtype=decimal&amp;latitude=".$location->latitude."&amp;longitude=".$location->longitude;
	
		// GoogleMaps URLs				
		$map_urls["GoogleMaps"]['coordinates'] = "http://maps.google.com/maps?q=".$location->latitude."+".$location->longitude."+(".$location->name.")&amp;hl=en";
		$map_urls["GoogleMaps"]['address'] = "http://maps.google.com/maps?q=". str_replace(" ", "+", $location->address)."+". str_replace(" ", "+", $location->city) ."+". str_replace(" ", "+", $location->state) ."+". str_replace(" ", "+", $location->country) ."+(".$location->name.")&amp;hl=en";
	
		if('' == $map_type) $map_type = get_option('travelog_googlemaps_view');	
		if('' == $zoom && "map" != $map_type) $zoom = get_option('travelog_googlemaps_zoom');
	
		$map_type_c = Travelog::map_type($map_type, 'url');
		
		if('' != $map_type) {
			$map_urls['GoogleMaps']['coordinates'] .= "&amp;t=$map_type_c";
			$map_urls['GoogleMaps']['address'] .= "&amp;t=$map_type_c";
		}
		
		if('' != $zoom) {
			$map_urls['GoogleMaps']['coordinates'] .= "&amp;z=$zoom";
			$map_urls['GoogleMaps']['address'] .= "&amp;z=$zoom";
		}
		
		// Mapping services that only work in 'coordinate' mode	
		$map_urls["GeoURL"] = array(
						"coodinates" => "http://geourl.org/near/?lat=".$location->latitude."&amp;lon=".$location->longitude."&amp;dist=500" ,
						"address" => ''
					); 
		$map_urls["GeoCache"] = array(
						"coordinates" => "http://www.geocaching.com/seek/nearest.aspx?origin_lat=".$location->latitude."&amp;origin_long=".$location->longitude."&amp;dist=5" ,
						"address" => ''
					);
		$map_urls["DegreeConfluence"] = array(
						"coordinates" => "http://confluence.org/confluence.php?lat=".$location->latitude."&amp;lon=".$location->longitude ,
						"address" => ''
					); 
		// US Only
		$map_urls["TopoZone"] = array(
						"coordinates" => "http://www.topozone.com/map.asp?lat=".$location->latitude."&amp;lon=".$location->longitude ,
						"address" =>  ''
					);
		
	
		// Return appropriate map URL
		if($map_url_type == "coordinates") {
			return $map_urls[$map]["coordinates"];
		}elseif($map_url_type == "address") {
			return $map_urls[$map]["address"];
		}else{
			return false;
		}
	}
	
	// ################################################## End function map_location_url()

}		// End class Travelog

function pingGeoURL($blog_ID) {
	// Pings geourl.org with blog page ID.
	$ourUrl = get_settings("home") ."/index.php?p=".$blog_ID;
	$host="geourl.org";
	$path="/ping/?p=".$ourUrl;
	getRemoteFile($host,$path);
}

function get_latitude() {
	// Returns the latitude of the post"s location.
	$location = Travelog::get_post_location();
	return $location->latitude;
}

function get_longitude() {
	// Returns the longitude of the post"s location.
	$location = Travelog::get_post_location();
	return $location->longitude;
}

function get_location_name() {
	// Returns the name of the post"s location.
	$location = Travelog::get_post_location();
	return $location->name;
}

function get_location_description() {
	// Outputs the latitude of the post"s location.
	$location = Travelog::get_post_location();
	return $location->description;
}

function the_latitude() {
	// Outputs the latitude of the post"s location.
	$latitude = get_latitude();
	if( $latitude > 0) {
		echo "$latitude N";
	}else{
		echo "".abs($latitude)." S";
	}
}

function the_latitudeDMS () {
	// Outputs the latitude of the post"s location in dddû mm" ss" (N/S)
	echo Travelog::coordinate_DMS(get_latitude(),"latitude");
}

function the_longitude() {
	// Outputs the longitude of the post"s location.
    $longitude = get_longitude();
	if( $longitude > 0) {
		echo "$longitude N";
	} else {
		echo "".abs($longitude)." S";
	}
}

function the_longitudeDMS () {
	// Outputs the longitude of the post"s location in dddû mm" ss" (E/W)
	echo Travelog::coordinate_DMS(get_longitude(),"longitude");
}

function the_location_name() {
	// Outputs the name of the post"s location
	echo get_location_name();
}

function the_location_description() {
	// Outputs the description of the post"s location
	echo get_location_description();
}

function last_location() {
	// Returns the location object of the last location visited
	return Travelog::get_locations('',1,'recent','','');
}

function last_location_name() {
	$location = last_location();
	echo $location->name;
}

function last_location_latitude() {
	$location = last_location();
	echo $location->latitude;
}

function last_location_longitude() {
	$location = last_location();
	echo $location->longitude;
}

function distance_between($loc1, $loc2, $unit = "k" ) {
	// Calculates the distance between two locations (given their ids) based on their coordinates
	// and outputs the distance in the desired unit
	if(!is_object($loc1)) $location1=Travelog::get_location($loc1); else $location1=$loc1;
	if(!is_object($loc2)) $location2=Travelog::get_location($loc2); else $location2=$loc2;
	$theta = $location1->longitude - $location2->longitude;
	$dist = sin(deg2rad($location1->latitude)) * sin(deg2rad($location2->latitude)) +  cos(deg2rad($location1->latitude)) * cos(deg2rad($location2->latitude)) * cos(deg2rad($theta));
	$dist = acos($dist);
	$dist = rad2deg($dist);
	$nautical = $dist * 60;
	$unit = strtolower($unit);

	if ($unit == "k") {
		return ($nautical * 1.852);
	} else if ($unit == "m") {
		return ($nautical * 1.15077945);
	} else {
		return $nautical;
	}
}

function travelog_summary_info() {
	// Returns a block of HTML showing location name (linked to map of location)
	// designed to be shown along with post summaries (ie. index.php, archive.php or search.php)
	
	$location = Travelog::get_post_location();

	$output = "";
	if($location) {
        $output .= "<span class=\"entry-meta\">Posted from <a href='".Travelog::map_location_url("GoogleMaps")."' title='Map this location'>".$location->name;
        if ( $location->name != $location->city ) $output .= ", ".$location->city;
        if ( $location->country != "" ) $output .= ", ".$location->country;
        $output .= "</a></span>";
    }
	return $output;
}

function travelog_summary_info1() {
	// Outputs a block of HTML showing location name (linked to map of location) and location coordinate in degrees-minutes-seconds
	// designed to be shown along with post summaries (ie. index.php, archive.php or search.php)
	
	if(Travelog::get_post_location()) : ?>
		Posted from <a href="<?= Travelog::map_location_url("GoogleMaps") ?>" title="Map this location"><?php the_location_name() ?></a> &#64; <?php the_latitudeDMS() ?>, <?php the_longitudeDMS() ?><br />
	<? endif;
}

function travelog_single_info() {
	// Outputs a block of HTML code that is designed to go in the post information section on the bottom of detailed post pages (single.php)
	
	if(Travelog::get_post_location()) : ?>
		from <a href="<?= Travelog::map_location_url("GoogleMaps") ?>" title="Map this location"><?php the_location_name() ?></a> &#64; <?php the_latitudeDMS() ?>, <?php the_longitudeDMS() ?>,
	<? endif;
}

function travelog_list_trips($page='travel',$select=false) {
    // Returns a block of HTML code that is designed to go in the post information section on the bottom of detailed post pages (single.php)
	$output = "";
    $trips = Travelog::get_trips();
    if($trips) {
		if($select) {
			$output .="<select name='select_travel' onChange=\"if (this.selectedIndex>0 ) document.location.href='" . $page . "?trips='+this.options[this.selectedIndex].value;\" >";
			$output .= "<option value=0>Select trip</option>";
			foreach ($trips as $tripid => $trip) {
				$output .= "<option value=" . $tripid . ">" . $trip->name . "</option>";
			}
		    $output .= "</select>";
		}
		else {
			foreach ($trips as $tripid => $trip) {
				$output .= "<li><a href='".$page."?trips=$trip->id'>".$trip->name."</a></li>";
			}
		}
	}
	return $output;
}

function travelog_print_trip($trip,$options) {

	if ( is_int($trip) || is_string($trip) )
		$trip = Travelog::get_trip($trip);

	if ( ! $trip ) {
		echo "trip does not exist...<br>";
		return;
	}

	echo "<div>";

	if($trip->name!='') echo '<h3>Trip ' . $trip->name . '</h3>';

	//print desc and dates
	if ($trip->description!='') $tmp_desc = $trip->description; else $tmp_desc="";
	if ($trip->description!='') $tmp_desc = polyglot_filter_with_message($trip->description); else $tmp_desc="";
	if ($trip->start_date!='') $tmp_sd = $trip->start_date; else $tmp_sd="";
	if ($trip->end_date!='') $tmp_ed = $trip->end_date; else $tmp_ed="";
	if ($tmp_desc.$tmp_sd.$tmp_ed!="") {
		echo "<p>$tmp_desc";
		if ($tmp_desc!='') echo "<br>";
		echo "($tmp_sd - $tmp_ed)</p>";
	}

	if ( !isset($options['trips']) ) $options['trips'] = $trip->id; 
	//echo "options: ";print_r($options);
	echo Travelog::embed_map($options);
	//echo '[ map for trip '.$trip->id.' ]<br>';

	echo "</div>";
}

function travelog_shortcode($atts) {

	$options=(array('map_type'=>"hybrid", 'controls'=>"large", 'width'=>"600", 'height'=>"600",'show_types'=>1,'scale'=>1));

	if($atts) {
		foreach ($atts as $key => $value) {  
			$options[$key] = $value;
		}
	}	

	if ($_GET['trips'] != "") {
		$options['trips'] = $_GET['trips'];
	}

	if(isset($options['trips'])) {
		travelog_print_trip($options['trips'],$options);
	}
	else {
		/*
		$trips = Travelog::get_trips(5,'newest');
		if ($trips!=false) {
			foreach ($trips as $tripid => $trip) { 
				travelog_print_trip($trip, $options);
				echo '<br><br>';
			}
		}
		*/
		$trips = Travelog::get_trips(1,'newest');
		if(isset($trips[1])) {
			echo "<br>";
			travelog_print_trip($trips[1],$options);
		}
		//echo"<p>".__('You can find the other trips in the "Trips" section in the sidebar.')."</p>";  
	}

	if(count(Travelog::get_trips(1,'newest')) >= 1 ) {
		echo "<br>Other trips: " .  travelog_list_trips('travel',true);
		echo "<br><br>";
	}


 /*
<!--
<br>This page does not work with Internet Explorer.  Use <a href='http://get-firefox.com'>Firefox</a> instead!
<br>Cette page ne fonctionne pas avec Internet Explorer.  Utilisez <a href='http://get-firefox.com'>Firefox</a> &agrave; la place!
-->
	*/

}

?>