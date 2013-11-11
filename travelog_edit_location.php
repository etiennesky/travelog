<?php

/*
	Plugin: Travelog
	Component: Location editor
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

		if(isset($_GET['action']) && 'edit' == $_GET['action']) {
			// Will be editing an existing location
			$location = Travelog::get_location($_GET['id']);
			$action = 'edit';
			$todo = 'update';
			$address_info = 'hide';
			$trip_info = 'hide';
			if('' == $location->latitude || '' == $location->longitude) {$coordinate_info = 'show';}else{$coordinate_info = 'hide';}
			$visit_info = 'hide';
			$posts_info = 'hide';
		}elseif (isset($_GET['action']) && 'add' == $_GET['action']) {
			$action = 'add';
			$todo = 'insert';
			$address_info = 'show';
			$coordinate_info = 'show';
			$visit_info = 'show';
			$trip_info = 'show';
			$posts_info = 'hide';
		}
		
		$categories = Travelog::get_categories();
		$trips = Travelog::get_trips();
		
		// Get posts from the location
		if('edit' == $action) {
			$location->get_posts();
		}
		
		// Show Travelog Manager submeny
		Travelog::adminheader();
  ?>
  		<script type="text/javascript" src="<?php bloginfo('wpurl');?>/wp-content/plugins/travelog/mapfunction.js"></script>

        <?php wp_enqueue_script('jquery-ui-datepicker'); 
	 	      wp_enqueue_style('jquery-style', 'http://code.jquery.com/ui/1.10.2/themes/smoothness/jquery-ui.css'); ?>

  		<script type="text/javascript">
  			function formValidate() {
  				if(document.editLocationForm.edit_new_visit.value == 'yyyy/mm/dd') document.editLocationForm.edit_new_visit.value = '';
  				if(document.editLocationForm.edit_new_visit_time.value == 'hh:mm') document.editLocationForm.edit_new_visit_time.value = '';
  				if(document.editLocationForm.edit_new_visit_time.value != '') 
					document.editLocationForm.edit_new_visit.value = document.editLocationForm.edit_new_visit.value+' '+document.editLocationForm.edit_new_visit_time.value;
  				return true;
  			}
  			
  			function showHide (target_id, controller) {
				var obj = document.getElementById(target_id);
				if(controller.innerHTML.substr(0,4) == '<?=__("show", DOMAIN)?>') {
					controller.innerHTML = '<?=__("hide", DOMAIN)?>';
					obj.style.display = 'block';
				}else{
					controller.innerHTML = '<?=__("show", DOMAIN)?>';
					obj.style.display = 'none';
				}
  			}
jQuery(document).ready(function() {
    jQuery('#edit_new_visit').datepicker({
        dateFormat : 'yy/mm/dd'
    });
});
  		</script>
  	
		<form method="post" name="editLocationForm" action="tools.php?page=travelog.php&area=locations&action=<?= $todo?>" onsubmit="return formValidate()">
			
			<div class="wrap">
        		<p style="float: right;margin-top: 2px;"><a href="options-general.php?page=travelog.php">Edit Travelog Options</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
                <h2><?php if('add' == $action) { echo __("Add Location", DOMAIN); }else{ echo __("Edit Location", DOMAIN); } ?></h2>
				<div style="float: right; width: 400px; height: 400px; margin-right: 20px; clear: right;" id="mapContainer">
				<?php echo Travelog::embed_map(array('ids'=>$location->id,'width'=>400,'height'=>400,'type'=>'editLocation'));?>
				</div>
				<script type="text/javascript">
					TravelogMap.prototype.setLocCoords = function() {
						var lat_field = document.getElementById('edit_latitude');
						var lon_field = document.getElementById('edit_longitude');
						this.map.clearOverlays();
						var center = this.map.getCenterLatLng();
						lon_field.value = decimalRound(center.lng(),6);
						lat_field.value = decimalRound(center.lat(),6);
						var locationName = document.getElementById('edit_name').value;
						
						var marker = createMarker(center, locationName);
						this.map.addOverlay(marker);
					}
					
					var centerFunc = '';
					function toggle_marker() {
						if(isGMapsJSLoaded) {
							var edit_coords = document.getElementById('interactive_set_coords');
							if(edit_coords.checked) { // Start coordinate editing
								centerFunc = GEvent.addListener(maps[0], "moveend", this.setLocCoords());
								var instructions = 'To add/change coordinates for this location, use the map at right. The latitude/longitude will be constantly updated to reflect the center of the map. To re-center the map, simply double click, or you can pan the map as desired by dragging it. Move, pan and zoom them map until the center (shown by the marker) is in the place you want, and then deselect this checkbox to lock in the new coordinates. Finally click the Update Location button to save the changes.';
								alert(instructions);
							}else{
								GEvent.removeListener(centerFunc);
							}
						}
					}
				</script>
                <table cellpadding="3" cellspacing="3">
                    <tbody>
                         <?php if($action == 'edit') : ?>
							 <tr class="alternate">
								<td><label><?=__('ID#', DOMAIN)?></label></td><td style="width: auto;"><?= $location->id ?><input type="hidden" name="edit_id" value="<?=$location->id ?>" /></td>
							 </tr>
						<?php endif; ?>
						<tr>
							<td><label for="edit_name"><?=__('Name', DOMAIN)?></label></td><td><input type="text" id="edit_name" name="edit_name" value="<?=$location->name ?>" size="15" /></td>
                         </tr>
                         <tr class="alternate">
                         	<td><label for="edit_category"><?=__('Category', DOMAIN)?></label></td><td><select name="edit_category">
								<option value="Default">-- <?=__('Select', DOMAIN)?> --</option>';
							<?php foreach ($categories as $key => $category) {
								echo "<option value='$category'";
								if($category == $location->category) echo ' selected="selected"';
								echo ">$category</option>";
							} ?>
							</select>						
							</td>
                         </tr>
                         
                        </tbody>
					</table>
					<fieldset name="address_form" style="width: 33%;margin: 5px 0;">
						<legend><strong><?= __('Address', DOMAIN) ?>:</strong> (<a href="javascript:void(0)" onclick="showHide('address_info', this);"><?php if($address_info =='show') {echo 'hide';}else{echo 'show';} ?></a>)</legend>
						<div id='address_info' style="display: <?php if($address_info =='show') {echo 'block';}else{echo 'none';} ?>;">
							<table>
								<tbody>
								 <tr>
									<td><label for="edit_address"><?=__('Address', DOMAIN)?></label></td><td><input type="text" name="edit_address" value="<?=$location->address?>" size="30" /></td>
								 </tr>
								 <tr class="alternate">
									<td><label for="edit_city"><?=__('City', DOMAIN)?></label></td><td><input type="text" id="edit_city" name="edit_city" value="<?=$location->city?>" size="15" /></td>
								 </tr>
								 <tr>
									<td><label for="edit_state"><?=__('State', DOMAIN)?></label></td><td><input type="text" id="edit_state" name="edit_state" value="<?=$location->state?>" size="15" /></td>
								 </tr>
								 <tr class="alternate">
									<td><label for="edit_country"><?=__('Country', DOMAIN)?></label></td><td><input type="text" id="edit_country" name="edit_country" value="<?=$location->country?>" size="15" /></td>
								 </tr>
								</tbody>
							</table>
						</div>
					</fieldset>
					<fieldset name="coordinate_form" style="width: 33%;margin: 5px 0;">
						<legend><strong><?= __('Coordinates', DOMAIN) ?>:</strong> (<a href="javascript:void(0)" onclick="showHide('coordinate_info', this);"><?php if($visit_info == __('show', DOMAIN)) {echo __('hide', DOMAIN);}else{echo __('show', DOMAIN);} ?></a>)</legend>
						<div id="coordinate_info" style="display: <?php if($coordinate_info =='show') {echo 'block';}else{echo 'none';} ?>;">
							<table>
								<tbody>
								 <tr>
									<td><label for="edit_latitude"><?=__('Latitude', DOMAIN)?></label></td><td><input type="text" name="edit_latitude" id="edit_latitude" value="<?=$location->latitude ?>" size="10"/></td>
								 </tr>
								 <tr class="alternate">
									<td><label for="edit_longitude"><?=__('Longitude', DOMAIN)?></label></td><td><input type="text" name="edit_longitude" id="edit_longitude" value="<?=$location->longitude ?>" size="10" /></td>
								 </tr>
								 <tr>
									<?php $elev_unit = get_option('travelog_elevation_unit'); ?>
									<td><label for="edit_elevation"><?=__("Elevation ($elev_unit)", DOMAIN)?></label></td><td><input type="text" id="edit_elevation" name="edit_elevation" value="<?=$location->elevation ?>" size="10" /></td>
								</tr>
								<tr>
<!-- // TODO fix this
									<td colspan="2"><input type="checkbox" name="interactive_set_coords" id="interactive_set_coords" onclick="toggle_marker();" value="1"> <label for="interactive_set_coords" style="font-weight: normal;"><?=__('Add/update coordinates using map', DOMAIN)?></label></td>
								</tr>
-->
							</tbody>
						</table>
<!-- // TODO fix this
						<?php if(get_option('travelog_show_edit_map')) : ?>&nbsp;&nbsp;<input type="checkbox" name="interactive_set_coords" id="interactive_set_coords" onclick="toggle_marker();" value="1"> <label for="edit_country" style="font-weight: normal;"><?=__('Add/update coordinates using map', DOMAIN)?></label><?php endif; ?>
-->
					</div>
				</fieldset>
				<fieldset name="visits_form" style="width: 33%;margin: 5px 0;">
					<?php $visits = ($action=='edit') ? $location->get_datetimes() : 0; if(count($visits) === 1) $message = 'Visit'; else $message = 'Visits';?>
					<legend><strong><?=count($visits)?> <?= __($message, DOMAIN) ?>:</strong> (<a href="javascript:void(0)" onclick="showHide('visit_info', this);"><?php if($visit_info == __('show', DOMAIN)) {echo __('hide', DOMAIN);}else{echo __('show', DOMAIN);} ?></a>)</legend>
					<div id="visit_info" style="display: <?php if($visit_info =='show') {echo 'block';}else{echo 'none';} ?>;">
						<input type="hidden" name="edit_dates_visited" value="<?= $location->dates_visited ?>" />
						<?php 
							if('edit' == $action) :
								$visits = $location->get_datetimes(); 
								if(count($visits) > 0) : ?>
						<table style="width: 80%;">
							<thead>
								<tr>
									<th scope="col"><?= __('Date', DOMAIN) ?></th>
									<th scope="col"><?= __('Time', DOMAIN) ?></th>
									<th scope="col"><?= __('Remove', DOMAIN) ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
									$alternate = 'class="alternate"';
									foreach($visits as $id => $visit) {
										echo "<tr $alternate><td>$visit[date]</td><td>$visit[time]</td><td style='text-align: center;'><input type='checkbox' name='remove_date_$id' /></td></tr>";
										if('class="alternate"' == $alternate) {$alternate = '';}else{$alternate = 'class="alternate"';}
									}
								?>
							</tbody>
						</table>
						<?php else :
							echo "<p>This location has never been visited</p>";
							endif;
						endif; ?>
                         <br>
						<label for="edit_new_visit"><?= __('Add Visit', DOMAIN) ?>:</label></td><td><input type="text" name="edit_new_visit" id="edit_new_visit" size="10" value="yyyy/mm/dd" &nbsp;&nbsp;>
						&nbsp;<?= __('at', DOMAIN) ?>:&nbsp;<input type="text" name="edit_new_visit_time" id="edit_new_visit_time" size="10" value="hh:mm" onfocus="if(this.value == 'hh:mm') this.value = '';"/>
                        &nbsp;<input type="checkbox" name="travelog_add_date_today" value="1" onclick="var new_date=document.getElementById('edit_new_visit'); if(this.checked){new_date.value ='<?=date('Y/m/d'); ?>';}else{if(new_date.value == '<?=date('Y/m/d'); ?>'){new_date.value ='';}}" /> <?= __('Today', DOMAIN) ?>
					</div>
				</fieldset>
				<fieldset name="trips_form" style="width: 33%;margin: 5px 0;">
				<?php if('' != $location->trips) $location_trips = explode(",", $location->trips); if(count($location_trips) === 1) $message = 'Trip'; else $message = 'Trips';?>
					<legend><strong><?=count($location_trips)?> <?= __($message, DOMAIN)?>:</strong> (<a href="javascript:void(0)" onclick="showHide('trip_info', this);"><?php if($trip_info =='show') {echo 'hide';}else{echo 'show';} ?></a>)</legend>
					<div id="trip_info" style="display: <?php if($trip_info =='show') {echo 'block';}else{echo 'none';} ?>;">
						<input type="hidden" name="edit_trips" value="<?= $location->trips ?>" />
						<?php if(count($location_trips) > 0) : ?>
						<table>
							<thead>
								<tr>
									<th scope="col"><?= __('Trip', DOMAIN) ?></th>
									<th scope="col"><?= __('Remove', DOMAIN) ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
									$alternate = 'class="alternate"';
									foreach ($location_trips as $trip_id) {
										echo "<tr $alternate><td><a href='tools.php?page=travelog.php&area=trips&action=edit&id=" . $trip_id . "' title='Edit this trip'>" . $trips[$trip_id]->name . "</a></td><td style='text-align: center;'><input type='checkbox' name='remove_trip_$trip_id' /></td></tr>";
									}
								?>
							</tbody>
						</table>
						<?php
							if(count($trips) > 0) {
								foreach($trips as $trip) {
									if('' != $trip && !in_array($trip->id, $location_trips)) {
										$options .= "<option value='$trip->id'>$trip->name</option>";
									}
								}
							}else{}
							else :
								echo "<p>This location is not part of any trip</p>";
								if(count($trips) > 0) {
									foreach($trips as $trip) {
										if('' != $trip) {
											$options .= "<option value='$trip->id'>$trip->name</option>";
										}
									}
								}
							endif; ?>
						<label for='edit_new_trip'><?= __('Add Trip', DOMAIN) ?>:</label>
						<?php
							if(count($trips) > 0) {
								if('' != $options) {
									echo "<select name='edit_new_trip' id='edit_new_trip'>
											<option value=''>-- Select --</option>
											$options
											</select>";
								}else{
									echo "No other trips available";
								}
							}else{
								echo "<a href='tools.php?page=travelog.php&area=trips'>Add trips to Travelog</a> &raquo;";
							}
						?>
						
					</div>
				</fieldset>
				<fieldset name="posts_form" style="width: 33%;margin: 5px 0;">
					<?php if(count($location->posts) === 1) $message = 'Post'; else $message = 'Posts';?>
						<legend><strong><?=count($location->posts)?> <?= __($message, DOMAIN) ?>:</strong> (<a href="javascript:void(0)" onclick="showHide('posts_info', this);"><?php if($post_info == __('show', DOMAIN)) {echo __('hide', DOMAIN);}else{echo __('show', DOMAIN);} ?></a>)</legend>
						<div id="posts_info" style="display: <?php if($posts_info =='show') {echo 'block';}else{echo 'none';} ?>;">
							<ul>
							<?php
								if(count($location->posts) > 0) {
									foreach($location->posts as $post) {
										echo "<li><a href='".get_permalink($post['ID'])."'>".$post['title']."</a> <small>(<a href='post.php?action=edit&post=".$post['ID']."'>Edit</a>)</small></li>";
									}
								}else{
									echo "<li>There are no posts from this location</li>";
								}
							?>
							</ul>
					</div>
				</fieldset>
				<table>
					<tbody>
						<tr>
							<td colspan="2"><label for="edit_description"><?=__('Description', DOMAIN)?></label><br />
							<textarea rows="6" cols="50" name="edit_description" id="content"><?=$location->description ?></textarea></td>
						</tr>
						<tr>
							<td colspan="2" class="submit"><input type="submit" value="<?php if('add' == $action) { echo __("Add Location", DOMAIN); }else{ echo __("Edit Location", DOMAIN); } ?> &raquo;" /> <input type="button" value="<?=__("Cancel", DOMAIN)?>" onclick="window.location='tools.php?page=travelog.php&area=locations'" /></td>
						</tr>
					</tbody>
                </table>
                <div style="clear: both;"></div>
			</div>
		</form>