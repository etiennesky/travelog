<?php

/*
	Plugin: Travelog
	Component: Option manager
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
    
	//Get information from the database
	$defaultLocationID = get_option('travelog_default_location_id');
	if($defaultLocationID != '') $location = Travelog::get_location($defaultLocationID);
?>
	<style type="text/css">
	<!--
		.acBackground {
			border: 1px solid #ccc;
			border-top-style: none;
			margin: 0;
		}
		
		.acHighlight {
			text-decoration: underline;
		}
		
		.acNotSelected {
			background-color: #eee;
			padding: 3px 4px 3px 17px;
		}
		
		.acSelected {
			background-color: #9cf;
			padding: 3px 4px 3px 17px;
			cursor: pointer;
		}
	-->
	</style>
	<form id="travelogOptions" method="post" onsubmit="formValidate();">
	<div class="wrap">
		<p style="float: right;margin-top: 2px;"><a href="tools.php?page=travelog.php" >Manage Travelog Locations</a> &raquo;&nbsp;&nbsp;&nbsp;&nbsp;</p>
		<h2><?= __('Travelog Options', DOMAIN) ?></h2>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="33%" scope="row"><?= __('Use Default Location', DOMAIN) ?>:</th>
					<td>
						<input type="checkbox" id="travelog_use_default_location" name="travelog_use_default_location" value="1" <?php if($defaultLocationID != '') echo 'checked="checked"';?> onclick="swapDisp('defaultLocationSetter', 'inline');"/>
						<span id="defaultLocationSetter" style="display:<?php if($defaultLocationID == ''){echo 'none';}else{echo'inline';}?>;"> <input type="text" id="travelogLocationSelector" name="travelogLocationSelector" value="<?=$location->name?>"/><input type="hidden" name="travelog_default_location_id" id="travelog_default_location_id" value="<?php echo $defaultLocationID?>"/></span>
						<script type="text/javascript">
							<!--
							var XMLAddress = '<?=get_settings("siteurl")?>/wp-content/plugins/travelog/travelog_xml.php';
							var locationChooser = new TravelogDataForm('locationChooser', 'travelogOptions', null, '', '', 'autocomplete', '', 'travelogLocationSelector', '', '', '');
							locationChooser.ac.action = 'setTravelogLocation';
							
							function setTravelogLocation(locationID) {
								document.getElementById('travelogLocationSelector').value = tLocations[locationID].name; 
								document.getElementById('travelog_default_location_id').value = locationID;
							}
							
							function formValidate() {
								if(!document.getElementById('travelog_use_default_location').checked) document.getElementById('travelog_default_location_id').value = '';
								return true;
							}
							
							function swapDisp(elemID, onStyle) {
								if(typeof(onStyle) == 'undefined') var onStyle = 'block';
								var elem = document.getElementById(elemID);
								if(elem.style.display != onStyle) elem.style.display = onStyle;
								else elem.style.display = 'none';
							}
							//-->
						</script>
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><?= __('Elevation Units', DOMAIN) ?>:</th>
					<td>
						<?php if (get_option('travelog_elevation_unit') == "m") {
									$elev_unit['m'] = ' checked="checked"';
									$elev_unit['ft'] = "";
								}elseif (get_option('travelog_elevation_unit') == "ft") {
									$elev_unit['ft'] = ' checked="checked"';
									$elev_unit['m'] = "";
								}	?> 
						<label for="elevation_unit_m"><input type="radio" name="travelog_elevation_unit" id="elevation_unit_m" <?= $elev_unit['m'] ?> value="m" />&nbsp;<?= __('Meters (m)', DOMAIN) ?></label><br />
						<label for="elevation_unit_ft"><input type="radio" name="travelog_elevation_unit" id="elevation_unit_ft" <?= $elev_unit['ft'] ?>value="ft" />&nbsp;<?= __('Feet (ft)', DOMAIN) ?></label><br />
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><?= __('Make map URLs using', DOMAIN) ?>:</th>
					<td>
						<?php if (get_option('travelog_map_url_type') == "coordinates") {
									$map_url_type['coordinates'] = ' checked="checked"';
									$map_url_type['address'] = "";
								}elseif (get_option('travelog_map_url_type') == "address") {
									$map_url_type['address'] = ' checked="checked"';
									$map_url_type['coordinates'] = "";
								}	?> 
						<label for="map_url_type_coordinates"><input type="radio" name="travelog_map_url_type" id="map_url_type_coordinates" <?= $map_url_type['coordinates'] ?> value="coordinates" />&nbsp;<?= __('Coordinates (latitude/longitude)', DOMAIN) ?></label><br />
						<label for="map_url_type_address"><input type="radio" name="travelog_map_url_type" id="map_url_type_address" <?= $map_url_type['address'] ?>value="address" />&nbsp;<?= __('Address (street, city, state, country)', DOMAIN) ?></label><br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small style="color: #c00;">(<b>Warning:</b> Mapping by address only works reliably in large towns/cities)</small><br />
					</td>
				</tr>
			</table>
	
			<h2><?= __('GoogleMaps Options', DOMAIN) ?></h2>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				<tr valign="top">
					<th width="33%" scope="row"><?= __('GoogleMaps API Key', DOMAIN) ?>:</th>
					<td>
						<input type="text" name="travelog_googlemaps_key" id="travelog_googlemaps_key" value="<?=get_option('travelog_googlemaps_key')?>" size="40"/> &nbsp;&nbsp;<a href="javascript:swapDisp('gMapKeyInfo')" title="Click for info about this option">Info</a>
					<div id="gMapKeyInfo" style="background: #ddd; border: 1px solid #666;padding: 5px;margin-top:10px;display:none;"><small><a href="http://www.google.com/apis/maps/">Get your free GoogleMaps API key here</a>. Use the full website address of your WordPress installation when creating your API key (eg. http://www.yoursite.com/ or http://www.yoursite.com/path-to-wordpress/).</small></div>
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><label for="travelog_googlemaps_view"><?= __('Default Map Type', DOMAIN) ?></label>:</th>
					<td>
						<select name="travelog_googlemaps_view">
							<option value="map" <?php if(get_option('travelog_googlemaps_view') =='map') echo 'selected="selected"'; ?>>Map</option>
							<option value="satellite" <?php if(get_option('travelog_googlemaps_view') =='satellite') echo 'selected="selected"'; ?>>Satellite</option>
							<option value="hybrid" <?php if(get_option('travelog_googlemaps_view') =='hybrid') echo 'selected="selected"'; ?>>Hybrid</option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><label for="travelog_googlemaps_width"><?= __('Default Map Width', DOMAIN) ?></label>:</th>
					<td>
						<input type="text" name="travelog_googlemaps_width" id="travelog_googlemaps_width" value="<?=get_option('travelog_googlemaps_width')?>" size="5"/> px
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><label for="travelog_googlemaps_height"><?= __('Default Map Height', DOMAIN) ?></label>:</th>
					<td>
						<input type="text" name="travelog_googlemaps_height" id="travelog_googlemaps_height" value="<?=get_option('travelog_googlemaps_height')?>" size="5"/> px
					</td>
				</tr>
				<tr valign="top">
					<th width="33%" scope="row"><label for="travelog_googlemaps_zoom"><?= __('Default Zoom Level', DOMAIN) ?></label>: </th>
					<td>
						<select name="travelog_googlemaps_zoom" id="travelog_googlemaps_zoom">
							<?php for ($i=1; $i <= 17; $i++) {
								echo "<option value='$i'";
								if(get_option('travelog_googlemaps_zoom') == $i) echo " selected='selected'";
								echo ">$i";
								if(17 == $i) echo " (closest)";
								if(1 == $i) echo " (whole earth)";
								echo "</option>";
							} ?>
						</select>
					</td>
				</tr>
			</table>
			<div class="submit"><input type="submit" name="submit" value="<?= __('Update Options', DOMAIN) ?> &raquo;" /></div>
		</div>
	</form>