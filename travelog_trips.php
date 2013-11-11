<?php

/*
	Plugin: Travelog
	Component: Trips manager
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

	// Get information from the database
	$trips = Travelog::get_trips();
	$changed = false;
	
	// Add a new trip if asked
	if(isset($_POST['add']) && '' != $_POST['new_trip_name']) {
        $new_trip = new trip();
		$new_trip->name = $_POST['new_trip_name'];
		$new_trip->start_date = $_POST['new_trip_start'];
		$new_trip->end_date = $_POST['new_trip_end'];
		$new_trip->description = $_POST['new_trip_desc'];
		
		$insert_sql = "INSERT INTO " . DB_TRIPS_TABLE ." ( name, start_date, end_date, description ) VALUES ( '" . $new_trip->name . "', '" . $new_trip->start_date . "', '" . $new_trip->end_date . "', '" . $new_trip->description . "')";
		if (FALSE !== $wpdb->query($insert_sql)) {
			$message .= '<p>'.__('The new trip was sucessfully added to your Travelog. ', DOMAIN);
		}else{
			$message .= '<p>'.__('An error has occured and the trip was not added', DOMAIN).'</p>';
		}
		
	}
	
	// Delete any trips if requested
    if(isset($_POST['update'])) {	
		$i=0;
		foreach($_POST as $key => $value) {
			if($remove_trip = stristr($key, 'remove_trip_')) $delete_trips[$i++] = str_replace('remove_trip_', "", $remove_trip);
		}
		
		if(count($delete_trips) > 0 ) {
			foreach($delete_trips as $key => $delete_trip_id) {
				$delete_sql = 'DELETE FROM '. DB_TRIPS_TABLE . ' WHERE id = '.$delete_trip_id;
				if (FALSE !== $wpdb->query($delete_sql)) {
					$message .= '<p>'.__('Trip '.$delete_trip_id.' deleted from your Travelog. ', DOMAIN);
				}else{
					$message .= '<p>'.__('An error has occured and the trip was not deleted', DOMAIN).'</p>';
				}
			}
			
			// Now we need to remove the trip_id from any locations that were associated with this trip
			foreach($delete_trips as $key => $delete_trip_id) {
				$find_sql = "SELECT id, trips FROM " . DB_TABLE . " WHERE FIND_IN_SET('$delete_trip_id', trips) > 0";
				$results = $wpdb->get_results($find_sql);
				if($results) {
					foreach($results as $result) {
						$location_trips = explode(',', $result->trips);
						$delete_key = array_search($delete_trip_id, $location_trips);
						if(false !== $delete_key) unset($location_trips[$delete_key]);
						if(count($location_trips) > 0) {
							$new_location_trips = implode(',', $location_trips);
						}else{
							$new_location_trips = '';
						}
						$update_sql = "UPDATE " . DB_TABLE . " SET trips = '$new_location_trips' WHERE id=" . $result->id;
						$wpdb->query($update_sql);
					}
				}
			}
		}
	}
	
	// ### Update an existing location ###
	if(isset($_GET['action']) && 'update' == $_GET['action']) {
		// Process the incoming form values
		$formvar_prefix = 'edit_';
		
		// Array of variables to process, with variable name as the key and its default value as the value
		$allowed_vars = get_class_vars('trip_db');
		
        $trip = new trip();
		// The heavy lifting, check if each allowed_var was passed and is not empty
		foreach($allowed_vars as $var => $default) {
			if(array_key_exists($formvar_prefix.$var, $_POST) && '' != $_POST[$formvar_prefix.$var]) {
				$trip->$var = $_POST[$formvar_prefix.$var];
			}else{
				$trip->$var = $default;
			}
		}
		
	// ### Assemble the SQL query ###
		$update_sql = "UPDATE " . DB_TRIPS_TABLE ." SET ";
		foreach($allowed_vars as $var => $default) {
			if('NULL' == $trip->$var) {
				$update_sql .= $var . " = " . $trip->$var . ", ";
			}else{
				$update_sql .= $var . " = '" . $trip->$var . "', ";
			}
		}
		
		$update_sql = substr($update_sql, 0, -2) . " WHERE id = '$trip->id'";
		
		if (FALSE !== $wpdb->query($update_sql)) {
			$message = '<p>'.__('Trip updated sucessfully', DOMAIN).'</p>';
		}
		else {
			$message = '<p>'.__('An error has occured and the trip was not updated', DOMAIN).'</p>';
		}
	}
	
// ### Check for display variables ###
	if(array_key_exists('show_num_trips', $_POST)) {
		$show_num_trips = $_POST['show_num_trips'];
	}else{
		$show_num_trips = 10; // Defaults output to 10
	}
	if(array_key_exists('show_order', $_POST)) { $show_order = $_POST['show_order']; }else{ $show_order="id DESC"; }
	
	// Reload trips after all the processing
	$trips = Travelog::get_trips($show_num_trips, $show_order);
	
		
	if($message != "") echo "<div class='updated'><p><strong>$message</strong></p></div>";
		
	$add_trip = 'hide';
	
	// Show Travelog Manager submenu
	Travelog::adminheader();
	
?>
        <?php wp_enqueue_script('jquery-ui-datepicker'); 
	 	      wp_enqueue_style('jquery-style', 'http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css'); ?>
<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery('.mydatepicker').datepicker({
        dateFormat : 'yy/mm/dd'
    });
});
  			function formValidate() {
  				if(document.tripsForm.new_trip_start.value == 'yyyy/mm/dd') document.tripsForm.new_trip_start.value = '';
  				if(document.tripsForm.new_trip_end.value == 'yyyy/mm/dd') document.tripsForm.new_trip_end.value = '';
  				return true;
  			}
  		</script>

	<div class="wrap">
		<p style="float: right;margin-top: 2px;"><a href="options-general.php?page=travelog.php" >Edit Travelog Options</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
		<h2><?= __('Travelog Trips', DOMAIN) ?></h2>
		<form method="post" name="tripsForm" onsubmit="formValidate();">
			<p style="float: right;margin-top: 0em;clear: right;"><a href="#add_trip" >Add a new trip</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
			<p>Show <select name="show_num_trips">
						<option value="" >All</option>
						<option value="5" <?php if($show_num_trips == 5) echo 'selected="selected"';?>>5</option>
						<option value="10" <?php if($show_num_trips == 10) echo 'selected="selected"';?>>10</option>
						<option value="20" <?php if($show_num_trips == 20) echo 'selected="selected"';?>>20</option>
						<option value="50" <?php if($show_num_trips == 50) echo 'selected="selected"';?>>50</option>
				</select> trips sorted by
				<select name="show_order">
					<option value="id">ID#</option>
					<option value="name" <?php if($_POST['show_order'] == 'name') echo 'selected="selected"';?>>Name</option>
					<option value="newest" <?php if($_POST['show_order'] == 'newest') echo 'selected="selected"';?>>Newest</option>
					<option value="oldest" <?php if($_POST['show_order'] == 'oldest') echo 'selected="selected"';?>>Oldest</option>
				</select>&nbsp;&nbsp;&nbsp;<input type="submit" name="display" value="<?=__('Display', DOMAIN)?> &raquo;" />
			</p>
			<table cellspacing="2" cellpadding="5">
				<tr valign="top">
					<th scope="col"><?= __('ID#', DOMAIN) ?></th>
					<th scope="col"><?= __('Name', DOMAIN) ?></th>
					<th scope="col"><?= __('# Stops', DOMAIN) ?></th>
					<th scope="col"><?= __('Start Date', DOMAIN) ?></th>
					<th scope="col"><?= __('End Date', DOMAIN) ?></th>
					<th scope="col"><?= __('Delete?', DOMAIN) ?></th>
				</tr>
						<?php
						if(is_array($trips)) {
							foreach ($trips as $key => $trip) {
								$trip->get_itinerary();
								$alternate = $alternate == ''? ' class="alternate"' : '';
								echo "<tr $alternate><td style='text-align: center;'>$trip->id</td><td><a href='tools.php?page=travelog.php&amp;area=trips&amp;action=edit&amp;id=".$trip->id."' title='Edit this trip'>$trip->name</a></td><td style='text-align: center;'>".count($trip->stops)."</td><td style='text-align: center;'>$trip->start_date</td><td style='text-align: center;'>$trip->end_date</td><td style='text-align: center;'><input type='checkbox' name='remove_trip_$trip->id' /></td></tr>";
							}
						}else{
							echo '<tr><td colspan="6" style="text-align: center;">There are no trips in your Travelog</td></tr>';
						}
						
						?>
			</table>
			<p class="submit"><input type="submit" name="update" value="<?= __("Update Trips", DOMAIN) ?> &raquo;" /></p>
			
			<a name="add_trip"></a><h2><?= __('Add a Trip', DOMAIN) ?></h2>
			<table>
				<tbody>
					<tr>
						<td style="text-align: right"><label for="new_trip_name" style="font-weight: normal;"><?=__('Trip Name', DOMAIN)?>:</label></td>
						<td colspan="3"><input type="text" value="" name="new_trip_name" size="18"/></td>
					</tr>
					<tr>
						<td style="text-align: right"><label for="new_trip_start" style="font-weight: normal;"><?=__('Start Date', DOMAIN)?>:</label></td>
						<td><input type="text" name="new_trip_start" id="new_trip_start" class="mydatepicker" size="10" value="yyyy/mm/dd" /></td>
						<td style="text-align: right"><label for="new_trip_end" style="font-weight: normal;"><?=__('End Date', DOMAIN)?>:</label></td>
						<td><input type="text" name="new_trip_end" id="new_trip_end" class="mydatepicker" size="10" value="yyyy/mm/dd"/></td>
					</tr>
					<tr>
						<td style="vertical-align: top;"><label for="new_trip_desc" style="font-weight: normal;"><?=__('Description', DOMAIN)?>:</label></td>
						<td colspan="3">
							<textarea name="new_trip_desc" cols="50" rows="4"></textarea> 
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit"><input type="submit" name="add" value="<?= __("Add Trip", DOMAIN) ?> &raquo;" /></p>
		</form>
	</div>