<?php

/*
	Plugin: Travelog
	Component: Location manager
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

// init default empty location object
$location = new location();

// ### Insert a new location into Travelog ###
	if(isset($_GET['action']) && 'insert' == $_GET['action']) {
		// Process the incoming form values
		$formvar_prefix = 'edit_';
		
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
		
		// Add a visit if specified
		if(isset($_POST['edit_new_visit']) && '' != $_POST['edit_new_visit'] && 'yyyy/mm/dd hh:mm' != $_POST['edit_new_visit']) {
			$location->dates_visited = $_POST['edit_new_visit'];
		}
		
		// Add a trip if specified
		if(isset($_POST['edit_new_trip']) && '' != $_POST['edit_new_trip']) {
			$location->trips = $_POST['edit_new_trip'];
		}
		
		$message .= Travelog::add_location($location);
	}

// ### Update an existing location ###
	if(isset($_GET['action']) && 'update' == $_GET['action']) {
		// Process the incoming form values
		$formvar_prefix = 'edit_';
		
		// Array of variables to process, with variable name as the key and its default value as the value
		$allowed_vars = get_class_vars('location_db');
		
		// The heavy lifting, check if each allowed_var was passed and is not empty
		foreach($allowed_vars as $var => $default) {
			if(array_key_exists($formvar_prefix.$var, $_POST) && '' != $_POST[$formvar_prefix.$var]) {
				$location->$var = $_POST[$formvar_prefix.$var];
			}else{
				$location->$var = $default;
			}
		}
		
	// ### Update dates_visited list if needed ###
		$i=0;
		foreach($_POST as $key => $value) {
			if($remove_date = stristr($key, 'remove_date_')) $delete_dates[$i++] = str_replace('remove_date_', "", $remove_date);
		}
		
		if($_POST['edit_dates_visited'] != "") {
			$dates = explode(",",$_POST['edit_dates_visited']);
			if(count($delete_dates) > 0 ) {
				foreach($delete_dates as $key => $delete_date) {
					unset($dates[$delete_date]);
				}
			}
		}else{$dates = array();}
		
		// Add new date visited if one was passed
		if($_POST['edit_new_visit'] != "" && $_POST['edit_new_visit'] != 'yyyy/mm/dd hh:mm') {
			$dates = array_merge($dates, $_POST['edit_new_visit']);
		}
		
		if('' != $dates && count($dates) > 0) {
			arsort($dates); //sort the dates so the most recent is always at the end of the list		
			$location->dates_visited = implode(',', $dates);
		}else{
			$location->dates_visited = '';
		}
		
	// ### Update trips list if needed ###
		$i=0;
		foreach($_POST as $key => $value) {
			if($remove_trip = stristr($key, 'remove_trip_')) $delete_trips[$i++] = str_replace('remove_trip_', "", $remove_trip);
		}
		
		if($_POST['edit_trips'] != "") {
			$trips = explode(",",$_POST['edit_trips']);
			if(count($delete_trips) > 0 ) {
				foreach($delete_trips as $key => $delete_trip) {
					$delete_key = array_search($delete_trip, $trips);
					unset($trips[$delete_key]);
				}
			}
		}else{$trips = array();}
		
		// Add new trip if one was passed
		if($_POST['edit_new_trip'] != "") {
			$trips = array_merge($trips, array($_POST['edit_new_trip']));
		}
		
		if('' != $trips && count($trips) > 0) {	
			$location->trips = implode(',', $trips);
		}else{
			$location->trips = '';
		}
		
	// ### Assemble the SQL query ###
		$update_sql = "UPDATE " . DB_TABLE ." SET ";
		foreach($allowed_vars as $var => $default) {
			if('NULL' == $location->$var) {
				$update_sql .= $var . " = " . $location->$var . ", ";
			}else{
				$update_sql .= $var . " = '" . $location->$var . "', ";
			}
		}
		
		$update_sql = substr($update_sql, 0, -2) . " WHERE id = '$location->id'";
		
		if (FALSE !== $wpdb->query($update_sql)) {
			$message = '<p>'.__('Location updated sucessfully', DOMAIN).'</p>';
		}
		else {
			$message = '<p>'.__('An error has occured and the location was not updated', DOMAIN).'</p>';
		}
	}
	
// ### Remove any locations if requested on main page ###
	if(isset($_GET['action']) && 'delete' == $_GET['action']) {		
		//Check to see if any locations need to be deleted
		$i=0;
		foreach($_POST as $variable => $value) {
			if($delete_id = stristr($variable, 'delete_id_')) $delete_ids[$i++] = str_replace('delete_id_', "", $delete_id);
		}
		
		$reset_postmetas = false;
		if(count($delete_ids) >= 1) {
			foreach($delete_ids as $key => $id) {
				$location = Travelog::get_location($id);
				$delete_sql = 'DELETE FROM ' . DB_TABLE . ' WHERE id = ' . $id;
				if (FALSE !== $wpdb->query($delete_sql)) {
					$message .= '<p>'.__('"'.$location->name.'" deleted from your Travelog. ', DOMAIN);
				}else{
					$message .= '<p>'.__('An error has occured and the location was not deleted', DOMAIN).'</p>';
				}
				if($location->posts_from > 0) $reset_postmetas = true;
			}
		}
		if($reset_postmetas) {
			//Remove the post-meta from any post associated with this location
			$del_sql = "DELETE FROM $wpdb->postmeta WHERE meta_key = '_travelog_location_id' AND meta_value IN ('";
			$del_sql .= implode("', '", $delete_ids);
			$del_sql .= "')";
			if (FALSE !== $wpdb->query($del_sql)) {
					$message .= __('Posts from this location have had their location reset', DOMAIN).'</p>';
				}else{
					$message .= '<p>'.__('An error has occured and post-meta information was not updated', DOMAIN).'</p>';
			}
		}
	}
	
// ### Output messages ###
	if($message != "") echo "<div class='updated'><p><strong>$message</strong></p></div>";


// ### Check for display variables ###
	$show_category = (isset($_POST['showCategory'])) ? $_POST['show_category'] : '' ;
	$show_num_locations = (isset($_POST['showNumResults'])) ? $_POST['showNumResults'] : '10' ;
	$show_order = (isset($_POST['showOrder'])) ? $_POST['showOrder'] : 'id' ;
	$show_search = (isset($_POST['locationSearchQuery'])) ? $_POST['locationSearchQuery'] : '' ;
	
// ### Get information from the database ###
	$locations = Travelog::get_locations($show_category, $show_num_locations, $show_order, $show_search);
	$categories = Travelog::get_categories();
	
	// Show Travelog Manager submeny
	Travelog::adminheader();
?>

<script type="text/javascript" src="<?php bloginfo('wpurl');?>/wp-content/plugins/travelog/mapfunction.js"></script>
<style type="text/css">
		<!--
	#travelogResultsTable {
		text-align: center;
		white-space: nowrap;
	}
	
	.alignleft {
		text-align: left;
	}
		-->
</style>

	<form method="post" action="tools.php?page=travelog.php&amp;area=locations&amp;action=delete" id="travelogManagerForm">
		<div class="wrap">
			<p style="float: right;margin-top: 2px;"><a href="options-general.php?page=travelog.php" >Edit Travelog Options</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
			<h2><?=__('Travelog Locations', DOMAIN)?></h2>
			<p style="float: right;margin-top: 0.5em;clear: right;"><a href="tools.php?page=travelog.php&amp;area=locations&amp;action=add" >Add a new location</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
			<p>Name: <input type="text" name="locationSearchQuery" id="locationSearchQuery" size="15" value="<?php echo $show_search;?>"/>
			
			&nbsp;&nbsp;&nbsp;&nbsp;Show: <select name="showNumResults" id="showNumResults">
					<option value="" >All</option>
					<option value="5" <?php if($show_num_locations == 5) echo 'selected="selected"';?>>5</option>
					<option value="10" <?php if($show_num_locations == 10) echo 'selected="selected"';?>>10</option>
					<option value="20" <?php if($show_num_locations == 20) echo 'selected="selected"';?>>20</option>
					<option value="50" <?php if($show_num_locations == 50) echo 'selected="selected"';?>>50</option>
			</select>&nbsp;&nbsp;&nbsp;&nbsp;Category:
			<select name="showCategory" id="showCategory">
				<option value="">All</option>
				   <?php foreach ($categories as $category) {
						echo "<option value=\"$category\"";
						if($_POST['show_category'] == $category) echo ' selected="selected"';
						echo ">$category</option>";
					} ?>
			</select>&nbsp;&nbsp;&nbsp;&nbsp;Sort by:
			<select name="showOrder" id="showOrder">
				<option value="id">ID#</option>
				<option value="name" <?php if($_POST['show_order'] == 'name') echo 'selected="selected"';?>>Name</option>
				<option value="recent" <?php if($_POST['show_order'] == 'recent') echo 'selected="selected"';?>>Recently visited</option>
				<option value="visits" <?php if($_POST['show_order'] == 'visits') echo 'selected="selected"';?>>Most visited</option>
				<option value="posts_from" <?php if($_POST['show_order'] == 'posts_from') echo 'selected="selected"';?>>Most posted from</option>
			</select>
			</p>
			<div style="text-align:center;float:left;"><small><a href="javascript: dataForm.listMappedLocations();" title="List Mapped Locations">List mapped</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript:dataForm.mapCurrent()" title="Map all listed locations">Map listed</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript:dataForm.clearLocations()" title="Clear map">Clear map</a></small>
			<table cellpadding="3" cellspacing="3" id="travelogResultsTable">
				<thead>
				  <tr>
					<th scope="col"><?= __('ID #', DOMAIN) ?></th>						
					<th scope="col"><?= __('Name', DOMAIN) ?></th>
					<th scope="col"><?= __('Category', DOMAIN) ?></th>
					<th scope="col"><?= __('Visits', DOMAIN) ?></th>
					<th scope="col"><?= __('Posts From', DOMAIN) ?></th>
					<th scope="col"><?= __('Delete?', DOMAIN) ?></th>
				  </tr>
			  </thead>
			  <tbody id="travelogResults">
			<?php					
			if(count($locations) == 0) :
				echo '<td colspan="8">No locations match your search</td>';
			else :
				foreach($locations as $id => $location) :
					$alternate = $alternate == ''? ' class="alternate"' : '';
					$post_count = 0; ?>
					<tr <?= $alternate ?>>
						<td style="text-align:center"><?= $location->id ?></td>
						<td class="alignleft"><input type="checkbox" class="locationMapToggle" id="l<?= $location->id ?>" value="0" title="Map this location" onclick="dataForm.locationBoxClicked(this);"/> <a href="tools.php?page=travelog.php&amp;area=locations&amp;action=edit&amp;id=<?= $location->id ?>" title="Edit this location"><?= $location->name ?></a></td>
						<td style="text-align:center"><?= $location->category ?></td>
						<td style="text-align:center"><?= $location->visits ?></td>
						<td style="text-align:center"><?= $location->posts_from ?></td>
						<td style="text-align: center;"><input type="checkbox" name="delete_id_<?= $location->id ?>" /></td>
					</tr>
			<?php endforeach;
				endif; ?>
				</tbody>
				</table>
				</div>
				<div id="locationsMap" style="float:right;position:relative;">
					<?php echo Travelog::embed_map(array('height'=>400,'width'=>400,'controls'=>large));?>
				</div>
				<script type="text/javascript">
					dataForm = new TravelogDataForm('dataForm', 'travelogManagerForm', maps[0], 'renderResults(this)', 'travelogResults', 'table', 'showNumResults', 'locationSearchQuery', 'showCategory', 'showOrder', '');
					maps[0].dataForm = dataForm;
					
					function renderResults(obj) {
						var locationRender = document.getElementById('travelogResults');
						
						// Clear existing results
						obj.emptyRenderer(locationRender);
						
						// Populate result table
						var shown = 0;
						for (tLocationKey in lastResults.locations) {
							var tLocation = tLocations[lastResults.locations[tLocationKey]];
							var isMapped = false;
							if(obj.linkedMap.contents.locations[tLocation.ID] == 'l' || obj.linkedMap.contents.locations[tLocation.ID] == 't') isMapped = true;
							var rowData = '<td>'+tLocation.ID+'</td><td class="alignleft"><input type="checkbox" class="locationMapToggle" id="l'+tLocation.ID+'" value="0" title="Map this location" onclick="'+obj.myName+'.locationBoxClicked(this);"';
							if(isMapped) rowData += ' checked="checked"';
							if(!isMapEnabled) rowData += ' disabled="disabled"';
							rowData += '/> <a href="tools.php?page=travelog.php&amp;area=locations&amp;action=edit&amp;id='+tLocation.ID+'" title="Edit this location">'+tLocation.name+'</a></td><td>'+tLocation.category+'</td><td>'+tLocation.visitCount+'</td><td>'+tLocation.posts.length+'</td><td><input type="checkbox" name="delete_id_'+tLocation.ID+'" /></td>';
							obj.renderObj.appendChild(document.createElement('tr'));
							obj.renderObj.lastChild.innerHTML = rowData;
							if(shown%2==1) obj.renderObj.lastChild.className = "alternate";
							shown++;
						}
						if (shown == 0) {
							obj.renderObj.appendChild(document.createElement('tr'));
							obj.renderObj.lastChild.innerHTML = '<td colspan="8">No locations match your search</td>';
						}
					}
				</script>
				<p style="clear:both;">&nbsp;</p>
				<p class="submit"><input type="submit" name="update" value="<?= __("Update Travelog", DOMAIN) ?> &raquo;" /></p>
			</div>
     	</form>