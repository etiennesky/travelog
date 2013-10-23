<?php

/*
	Plugin: Travelog
	Component: XML Data output
	Author: Shane Warner
	Author URI: http://www.sublimity.ca/

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

*/

	// Trigger WordPress to load all its goodies so we can do stuff
	require_once('../../../wp-config.php');

	global $wpdb;

	header('Content-type: text/xml; charset=' . get_settings('blog_charset'), true);
	
	// Get settings that control output passed via GET
	$search = (isset($_GET['s'])) ? $_GET['s'] : ''; // String to search names by
	$searchtypes = (isset($_GET['t'])) ? $_GET['t'] : ''; // What data to search in ('l' for locations, 't' for trips, 'lt' for both)
	$limit = (isset($_GET['limit'])) ? $_GET['limit'] : ''; // Number of results returned
	$idstring = (isset($_GET['ids'])) ? $_GET['ids'] : ''; // ids of locations to map
	$order = (isset($_GET['order'])) ? $_GET['order'] : 'id'; // Sort order of results
	$category = (isset($_GET['category'])) ? $_GET['category'] : ''; // Map all locations in this category
	$tripstring = (isset($_GET['trips'])) ? $_GET['trips'] : ''; // Map all locations in this category
	
	$lids = ($idstring != '') ? explode(',',$idstring) : ''; // Arrayify the list of locations to map
	$tids = ($tripstring != '') ? explode(',',$tripstring) : ''; // Arrayify the list of trips to map
	$marker = 'redFlag';

	// Determine if what data to search with the search term 
	$lsearch = false;$tsearch = false;
	if(strpos($searchtypes,'t') !== false) $tsearch = true;
	if(strpos($searchtypes, 'l') !== false) $lsearch = true;

	$trips = array();$locations = array();$stop_locations=array();
	if($tripstring != '' || $tsearch) $trips = Travelog::get_trips($limit, $order, $search, $tripstring);
	if($idstring != '' || $lsearch || $category != '') $locations = Travelog::get_locations($category, $limit, $order, $search, $idstring);
	
	if(count($trips) > 0) {
		$fetchlocations = array();
		foreach($trips as $tripid => $trip) {
			$trips[$tripid]->get_itinerary();
			if(count($trips[$tripid]->stops) > 0) {
				foreach($trips[$tripid]->stops as $stop_num => $stop) {
					if(!array_key_exists($stop['location_id'], $stop_locations)) {
						$fetchlocations[] = $stop['location_id'];
					}
				}
			}
		}
		$stop_locations = Travelog::get_locations('','','','',implode(',',$fetchlocations));
	}
?>
<?php echo '<?xml version="1.0" encoding="'.get_settings('blog_charset').'"?'.'>'; ?>
<travelogdata>
 <?php if($trips) {?><trips>
	<?php foreach ($trips as $trip) { ?>
	<trip id="<?=$trip->id?>" name="<?=$trip->name?>" start="<?=$trip->start_date?>" end="<?=$trip->end_date?>">
		<description><?=$trip->description?></description>
		<stops>
		<? foreach ($trip->stops as $stop) { ?>
		<stop id="<?=$stop['location_id']?>" name="<?=$stop['name']?>" date="<?=$stop['date']?>" />
		<? } // end foreach stops ?>
		</stops>
	</trip>
	<? } // end foreach trip ?>
	<locations>
		<? foreach($stop_locations as $locationID => $location) {
               //limit location visits during this trip only 
               dumpLocationXML($location,$trip->start_date,$trip->end_date);
		} // end foreach stop locations ?>		
		</locations>
</trips>
<?php } // end if trips

	
if($locations) {?><locations>
	<?php foreach ($locations as $location) { 
		dumpLocationXML($location);
	} //end locations loop ?>
</locations><? } // end if locations ?>
</travelogdata>

<?php 

// Function to dump the location XML

function dumpLocationXML($location,$start_date='',$end_date='') { 
	$location->get_posts();
	?>
	<location id="<?=$location->id?>" latitude="<?=$location->latitude?>" longitude="<?=$location->longitude?>" elevation="<?=$location->elevation?>" name="<?=htmlspecialchars($location->name)?>">
		<category><?=htmlspecialchars($location->category)?></category>
		<visits><?php if($location->dates_visited) {
				$visits = explode(',', $location->dates_visited);
				foreach($visits as $visit) {
                    // quick hack to limit visits to given dates, use when one trip is queried only
                    if( strtotime($visit) >= strtotime($start_date) &&
                        strtotime($visit) <= strtotime($end_date) ) {
                            list($date, $time) = explode(" ", $visit);
                            echo "<visit date='".$date."' time='".$time."'/>";
                    }
                } //close visits loop 
				} // close if visits
		?></visits>
		<description><?=htmlspecialchars($location->description)?></description>
		<address>
			<street><?=htmlspecialchars($location->address)?></street>
			<city><?=htmlspecialchars($location->city)?></city>
			<state><?=htmlspecialchars($location->state)?></state>
			<country><?=htmlspecialchars($location->country)?></country>
		</address>
		<posts_from><?php foreach($location->posts as $post) { ?>
			<post id="<?=$post['ID']?>" comments="<?=$post['comments']?>"><?=htmlspecialchars($post['title'])?></post>
		<?php } ?></posts_from>
		<intrips><?=$location->trips?></intrips>
		<marker><?=$marker?></marker>
	</location><?php 
} // close function
?>