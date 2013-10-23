/*
	Plugin: Travelog
	Component: AJAX & GoogleMap Javascript
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

// ######### Variables to be set on calling page #########
	var XMLAddress = ''; // path to the Travelog XML file (http://www.yoursite.com/your_wp_directiory/wp_contents/plugins/travelog/travelog_xml.php)
	var isMapEnabled = true;
    var isGMapsJSLoaded = true; // TODO test with v3

// ########### Static Master Variables ##################	
	var tLocations = new Array(); // Master cache array containing all information about any location ever loaded
	var tTrips = new Array(); // Master cache array containing all information about any trips ever loaded
	var maps = new Array(); // Master array holding all the TravelogMap instances
	var icons = new Array(); // Master array holding all the GMap icons
	
	var lastResults = new Object(); // Contains the ids of the Travelog entities returned by the last query
		lastResults.locations = new Array();
		lastResults.trips = new Array();

	var handler = '';
	
	var XMLHttpRequests = new Array(); // Array of XMLHttp connections. Each value is an array where [0] is the connection, [1] is a boolean indicating if it is in use and [2] is the function to call on completion of data loading

// ################## Data Fetching Functions ########################

function travelogGetXMLData(XMLAddress, searchQuery, searchWhat, locationIds, trips, showCategory, numResults, showOrder) {				
	var queryString = '';
	if (searchQuery != '') queryString += 's='+searchQuery + '&';
	if (searchWhat != '') queryString += 't='+searchWhat + '&';
	if (locationIds != '') queryString += 'ids='+locationIds + '&';
	if (showCategory != '') queryString += 'category='+showCategory + '&';
	if (numResults != '') queryString += 'limit='+numResults + '&';
	if (trips != '') queryString += 'trips='+trips + '&';
	if (showOrder != '') queryString += 'order='+showOrder + '&';
	
	if(queryString != '') queryString = queryString.substring(0,(queryString.length-1));
	var requestURL = XMLAddress+"?"+queryString;
	
	// Complicated stuff to allow multiple queries at the same time using this function
	var responseCallback = function(key) {
		if (XMLHttpRequests[key][0].readyState != 4) return;
		var xmlDoc = XMLHttpRequests[key][0].responseXML.documentElement;
		travelogLoadXMLData(xmlDoc, XMLHttpRequests[key][2]);
		XMLHttpRequests[key][1] = false; // We're done with it
    }
	var key = selectXMLHttpRequest();
	SendHttpGet(key, requestURL, responseCallback);
	
	return key;
}

function selectXMLHttpRequest() {
	var key = 0;
	for(i=0;i<=XMLHttpRequests.length;i++) {
		if(typeof(XMLHttpRequests[i]) != 'undefined') {
			if(XMLHttpRequests[i][1] === false) { XMLHttpRequests[i][1] = true; XMLHttpRequests[i][2] = handler; key = i; break; }
		}else{
			// We've reached the end of the array and are using all existing connections, so create a new one
			key = XMLHttpRequests.length;
			XMLHttpRequests[XMLHttpRequests.length] = new Array(createXmlHttp(),true, handler); break;
		}
	}
	return key;
}

function createXmlHttp() {
    var xmlHttp = null;
    try {
		xmlHttp = new ActiveXObject("Msxml2.XMLHTTP");
    } catch (e) {
		try {
			xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
		} catch (e) {
			try {
				xmlHttp = new XMLHttpRequest();
			} catch (e) {
				xmlHttp = false;
			}
		}
    }
    if (!xmlHttp && typeof XMLHttpRequest!='undefined') {
		xmlHttp = new XMLHttpRequest();
    }
    return xmlHttp;
}

function SendHttpPost(key, url, args, callback) {
    XMLHttpRequests[key][0].open("POST", url, /* async */ true);
    XMLHttpRequests[key][0].setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    XMLHttpRequests[key][0].onreadystatechange = function() { callback(key); }
    XMLHttpRequests[key][0].send(args);
}

function SendHttpGet(key, url, callback) {
    XMLHttpRequests[key][0].open("GET", url, /* async */ true);
    XMLHttpRequests[key][0].onreadystatechange = function() { callback(key); }
    XMLHttpRequests[key][0].send("FOO");
}
			
function travelogLoadXMLData(xmlDoc, dataHandler) {
	// Parses the passed travelogdata XML and loads it into master cache
	
	var trips='';var locations='';
	for(i=0;i<xmlDoc.childNodes.length;i++) {
		if(xmlDoc.childNodes[i].nodeName == 'trips') trips = xmlDoc.childNodes[i];
		if(xmlDoc.childNodes[i].nodeName == 'locations') locations = xmlDoc.childNodes[i];
	}

	var loadedLocations = new Array();
	var loadedTrips = new Array();

	// Load Locations
	if(typeof(locations) != 'string') {
		var locationElems = locations.getElementsByTagName('location');
		loadedLocations = travelogLoadXMLLocations(locationElems);
	}
	
	// Load Trips
	if(typeof(trips) != 'string') {
		var locationsToLoad = new Array();
		var tripElems = xmlDoc.getElementsByTagName('trip');
		for (var i = 0; i < tripElems.length; i++) {
			var tripID = parseInt(tripElems[i].getAttribute('id'));
			var tl = new Object();
			tl.ID = tripID;
			tl.name = tripElems[i].getAttribute('name');
			tl.start = tripElems[i].getAttribute('start');
			tl.end = tripElems[i].getAttribute('end');
            // TOD fix html content...
			if(tripElems[i].getElementsByTagName('description')[0].firstChild) tl.description = tripElems[i].getElementsByTagName('description')[0].firstChild.nodeValue;
			tl.stops = new Array();
			// Process stops
			var stops = tripElems[i].getElementsByTagName('stop');
			for (var j = 0; j < stops.length; j++) {
				var stop = new Object();
				stop.ID = parseInt(stops[j].getAttribute('id'));
				stop.name = stops[j].getAttribute('name');
				stop.date = stops[j].getAttribute('date');
				tl.stops[tl.stops.length] = stop;
				if(typeof(tLocations[stop.ID]) == 'undefined') locationsToLoad[locationsToLoad.length] = stop.ID;
			}
			tTrips[tl.ID] = tl;
			loadedTrips[loadedTrips.length] = tl.ID;
		}
		
		var tripStops = trips.getElementsByTagName('location');
		if(tripStops.length > 0) {
			travelogLoadXMLLocations(tripStops);
		}
	}
	
	// Clear & update last loaded array
	lastResults.locations = loadedLocations;
	lastResults.trips = loadedTrips;
	
	// Call the handler function
	eval(dataHandler);
}

function travelogLoadXMLLocations(xmlElems) {
	var loadedLocations = new Array();
	for (var i = 0; i < xmlElems.length; i++) {
		var locationID = parseInt(xmlElems[i].getAttribute('id'));
		if(typeof(tLocations[locationID]) == 'undefined') {
			var tl = new Object();
			tl.ID = locationID;
			tl.name = xmlElems[i].getAttribute('name');
			tl.category = xmlElems[i].getElementsByTagName('category')[0].firstChild.nodeValue;
			tl.latitude = decimalRound(xmlElems[i].getAttribute('latitude'),5);
			tl.longitude = decimalRound(xmlElems[i].getAttribute('longitude'),5);
			tl.elevation = xmlElems[i].getAttribute('elevation');
            // TODO fix html content...
			if(xmlElems[i].getElementsByTagName('description')[0].firstChild) tl.description = xmlElems[i].getElementsByTagName('description')[0].firstChild.nodeValue;
			// Address processing
			var address = xmlElems[i].getElementsByTagName('address')[0];
			if(address.getElementsByTagName('street')[0].firstChild) tl.address = address.getElementsByTagName('street')[0].firstChild.nodeValue;
			if(address.getElementsByTagName('city')[0].firstChild) tl.city = address.getElementsByTagName('city')[0].firstChild.nodeValue;
			if(address.getElementsByTagName('state')[0].firstChild) tl.state = address.getElementsByTagName('state')[0].firstChild.nodeValue;
			if(address.getElementsByTagName('country')[0].firstChild) tl.country = address.getElementsByTagName('country')[0].firstChild.nodeValue;
			// Visit processing
			var temp = new Array();
			var visits = xmlElems[i].getElementsByTagName('visit');
			for (j=0;j<visits.length;j++) {
				temp[j] = new Array();
				temp[j]['date'] = visits[j].getAttribute('date');
				temp[j]['time'] = visits[j].getAttribute('time');
			}
			tl.visits = temp;
			tl.visitCount = temp.length;
			if(xmlElems[i].getElementsByTagName('intrips')[0].firstChild) {tl.trips = xmlElems[i].getElementsByTagName('intrips')[0].firstChild.nodeValue;}else{tl.trips = '';}
			var temp = new Array();
			var posts = xmlElems[i].getElementsByTagName('post');
			for (j=0;j<posts.length;j++) {
				temp[j] = new Array();
				temp[j]['id'] = posts[j].getAttribute('id');
				temp[j]['comments'] = posts[j].getAttribute('comments');
				temp[j]['title'] = posts[j].firstChild;
			}
			tl.posts = temp;
			if(xmlElems[i].getElementsByTagName('marker')[0].firstChild) tl.marker = xmlElems[i].getElementsByTagName('marker')[0].firstChild.nodeValue;
			
			// Add location info to master cache
			tLocations[tl.ID] = tl;
		}
		loadedLocations[loadedLocations.length] = locationID;
	}
	return loadedLocations;
}

// ################## Result Rendering Functions ########################

function TravelogDataForm(myName, formID, mapObj, renderfunc, rendererID, displayType, limiterID, searcherID, categoryChooserID, sorterID, tripChooserID) {
	this.linkedMap = mapObj;
	this.formObj = document.getElementById(formID);
	this.renderObj = document.getElementById(rendererID);
	this.numResults = document.getElementById(limiterID);
	this.searchQuery = document.getElementById(searcherID);
	this.categoryChooser = document.getElementById(categoryChooserID);
	this.sortOrder = document.getElementById(sorterID);
	this.tripChooser = document.getElementById(tripChooserID);
	this.displayType = displayType;
	this.oldQuery = '';
	this.timer = null;
	this.myName = myName;
	this.showLinks = false;
	this.renderFunction = renderfunc;
	
	if(this.displayType == 'autocomplete') {
		this.ac = new Object();
		// Create & initialize the results div
		var resultsObj = document.createElement('div');
		resultsObj.className = 'autoCompleteBackground';
		resultsObj.style.position = 'absolute';
	    resultsObj.style.top = eval(acGetTop(this.searchQuery) + this.searchQuery.offsetHeight) + 'px';
	    resultsObj.style.left = acGetLeft(this.searchQuery) + 'px';
		resultsObj.id = this.searchQuery.id+'_autocomplete';
		document.body.appendChild(resultsObj);
		this.ac.renderObj = document.getElementById(resultsObj.id);
		this.ac.current = -1;
		this.ac.action = '';
	}
	
	// Attach actions to controllers automatically
	if(this.displayType == 'list' || this.displayType == 'table' || this.displayType == 'tablesummary') {
		this.numResults.onchange = new Function(this.myName+'.doSearch();');
		this.categoryChooser.onchange = new Function(this.myName+'.doSearch();');
		this.sortOrder.onchange = new Function(this.myName+'.doSearch();');
		this.searchQuery.onkeyup = new Function(this.myName+'.doDelayedSearch();');
	}else if(this.displayType == 'autocomplete') {	
		this.searchQuery.onblur = new Function(this.myName+'.acHide();');
		this.searchQuery.onkeydown = new Function('event',this.myName+'.acHandleEvent(event);');
	}
}

TravelogDataForm.prototype.doSearch = function() {
	handler = this.myName+'.render()';
	travelogGetXMLData(XMLAddress, this.searchQuery.value, 'lt', '', '', this.categoryChooser.value, this.numResults.value, this.sortOrder.value)
}

TravelogDataForm.prototype.doDelayedSearch = function() {
	if (this.timer) window.clearTimeout(this.timer);
	if(this.searchQuery.value != this.oldQuery) {
		var toRun = this.myName+'.doSearch()';
		this.timer = window.setTimeout(toRun,200);
		this.oldQuery = this.searchQuery.value
	}
}

TravelogDataForm.prototype.listMappedLocations = function() {
	if(getObjLength(this.linkedMap.contents.locations) > 0) {
		var ids = '';
		for (item in this.linkedMap.contents.locations) {
			if(this.linkedMap.contents.locations[item] == 'l' || this.linkedMap.contents.locations[item] == 't') ids+= item+',';
		}
		ids = ids.substring(0, ids.length-1);
		if(ids.length > 0) {
			lastResults.locations = ids.split(',');
		}else{
			lastResults.locations = new Array();
		}
		this.render();
	}
}

TravelogDataForm.prototype.mapCurrent = function() {
	var listedLocations = this.getListedIds('locationMapToggle');
	var ids = '';
	if(listedLocations.length > 0) {
		for(i=0;i<listedLocations.length;i++) {
			if(this.linkedMap.contents.locations[listedLocations[i]] == 'n' || typeof(this.linkedMap.contents.locations[listedLocations[i]]) == 'undefined') ids += listedLocations[i]+',';
		}
		if(ids.length > 0) {
			ids = ids.substring(0, ids.length-1);
			this.linkedMap.mapLocations(ids);
		}
		this.refreshLocationBoxes();
	}
}

TravelogDataForm.prototype.clearLocations = function() {
	if(getObjLength(this.linkedMap.contents.locations) > 0) {
		var ids = '';
		for(item in this.linkedMap.contents.locations) {
			if(this.linkedMap.contents.locations[item] == 'l') ids += item+',';
		}
		ids = ids.substring(0, ids.length-1);
		this.linkedMap.unmapLocations(ids);
		this.refreshLocationBoxes();
	}
}

TravelogDataForm.prototype.refreshLocationBoxes = function() {
	var locationBoxIds = this.getListedIds('locationMapToggle');
	for (i=0;i<locationBoxIds.length;i++) {
		var box = document.getElementById('l'+locationBoxIds[i]);
		if(isMapEnabled){
			if(this.linkedMap.contents.locations[locationBoxIds[i]] == 't') {
				box.checked = true;
				box.disabled = true;
			}else if (this.linkedMap.contents.locations[locationBoxIds[i]] == 'l'){
				box.checked = true;
				box.disabled = false;
			}else{
				box.checked = false;
				box.disabled = false;
			}
		}else{
			box.disabled = true;
		}
	}
}

TravelogDataForm.prototype.refreshTripBoxes = function() {
	var tripBoxIds = this.getListedIds('tripMapToggle');
	for (i=0;i<tripBoxIds.length;i++) {
		var box = document.getElementById('t'+tripBoxIds[i]);
		if(isMapEnabled){
			if(this.linkedMap.contents.trips[tripBoxIds[i]] === true) {
				box.checked = true;
				box.disabled = false;
			}else{
				box.checked = false;
				box.disabled = false;
			}
		}else{
			box.disabled = true;
		}
	}
}

TravelogDataForm.prototype.locationBoxClicked = function(item) {
	var locationId = item.id.substring(1);
	if(item.checked) {
		this.linkedMap.mapLocations(locationId);
	}else{
		this.linkedMap.unmapLocations(locationId);
	}
	this.refreshLocationBoxes();
}

TravelogDataForm.prototype.getListedIds = function(classname) {
	var inputs = this.formObj.getElementsByTagName('input');
	var ids = new Array();
	for (i=0;i<inputs.length;i++) {
		if(inputs[i].className == classname) {
			ids[ids.length] = inputs[i].id.substring(1);
		}
	}
	return ids;
}

TravelogDataForm.prototype.tripBoxClicked = function(item) {
	var tripId = item.id.substring(1);
	if(item.checked) {
		this.linkedMap.mapTrips(tripId);
	}else{
		this.linkedMap.unmapTrips(tripId);
	}
	this.refreshLocationBoxes();
}

TravelogDataForm.prototype.mapCurrentTrips = function() {
	var tripids = this.getListedIds('tripMapToggle');
	this.linkedMap.mapTrips(tripids.toString());
	this.refreshTripBoxes();
}

TravelogDataForm.prototype.clearTrips = function() {
	var tripids = '';
	for(tripkey in this.linkedMap.contents.trips) {
		if(this.linkedMap.contents.trips[tripkey] == true) tripids += tripkey+',';
	}
	tripids = tripids.substring(0,tripids.length-1);
	if(tripids.length > 0) this.linkedMap.unmapTrips(tripids);
	this.refreshTripBoxes();
}

TravelogDataForm.prototype.render = function() {
	if(this.renderFunction != '') {
		eval(this.renderFunction);
	}else if(this.displayType == 'table') {
		this.renderTableResults();
	}else if(this.displayType == 'autocomplete') {
		this.acRender();
	}else{
		return false;
	}
}

TravelogDataForm.prototype.emptyRenderer = function(obj) {
	while (obj.childNodes.length > 0) {
 		obj.removeChild(obj.childNodes[obj.childNodes.length-1]);
 	}
}
// ################## GoogleMap Functions ########################

function initializeMap(mapNum, mapID, dataFormObj, mapType, controls, showTypes, scale) {
	// Setup the map instance
	maps[mapNum] = new TravelogMap('maps['+mapNum+']', mapID, dataFormObj);
	maps[mapNum].setUpMap(mapType, controls, showTypes, scale);
	//icons = initializeFlags();
    icons = Array();
}

function TravelogMap(objName, mapID, dataFormObj) {
	this.contents = new Object();
	this.contents.locations = new Object();
	this.contents.trips = new Object();
	this.markers = new Array();// Set up the array for markers
	this.overlays = new Array();// Set up the array for polylines & other overlays
	this.dataForm = dataFormObj;
	this.myName = objName;
	this.divName = mapID;
}

TravelogMap.prototype.setUpMap = function(mapType, controls, showTypes, scale) {
    if(isGMapsJSLoaded) {
        // define map options from controls value
        var zoomType = google.maps.ZoomControlStyle.DEFAULT;
        var pan = true;
        var streetView = true;
		if(controls == 'large') {
            zoomType = google.maps.ZoomControlStyle.LARGE;
        }
		else if(controls == 'small') {
            zoomType = google.maps.ZoomControlStyle.DEFAULT;
            pan = true;
            streetView = false;
        }
		else if(controls == 'zoom') {
            zoomType = google.maps.ZoomControlStyle.SMALL;
            pan = false;
            streetView = false;
        }

        this.map = new google.maps.Map(
            document.getElementById(this.divName), {
                center: new google.maps.LatLng(0, 0),
                zoom: 1,
                mapTypeId: this.mapType(mapType),
                mapTypeControl: showTypes,
                scaleControl: scale,
                zoomControl: true,
                zoomControlOptions: { style: zoomType },
                panControl: pan,
                streetViewControl: streetView
	        });
    }
}

TravelogMap.prototype.updateMap = function() {
	if(!isGMapsJSLoaded) {
		var debug = '';
		if (getObjLength(this.contents.locations) > 0) {
			for (tLocationID in this.contents.locations) {
				if(this.contents.locations[tLocationID] == 't' || this.contents.locations[tLocationID] == 'l') debug += tLocationID+', ';
			}
			document.getElementById(this.divName).innerHTML = '<p>Locations: '+debug+'</p>';
		}
		debug = '';
		if (getObjLength(this.contents.trips) > 0) {
			for (tripID in this.contents.trips) {
				if(this.contents.trips[tripID] == true) debug += tripID+', ';
			}
			document.getElementById(this.divName).innerHTML += '<p>Trips: '+debug+'</p>';
		}
	}		
	
	if(isMapEnabled && isGMapsJSLoaded) {
		// Determine view bounds & position map appropriately (must be done before adding markers for G_MAP_TYPE!)
		var mapBounds = new google.maps.LatLngBounds();
		var shown = 0;
		for (tLocationID in this.contents.locations) {
			if(this.contents.locations[tLocationID] == 'l' || this.contents.locations[tLocationID] == 't') { // only if the location is currently displayed
				mapBounds.extend(new google.maps.LatLng(tLocations[tLocationID].latitude, tLocations[tLocationID].longitude));
				shown++;
			}
		}
		
		if (shown > 1) {
			centerAndZoomOnBounds(this.map,mapBounds, 20);
		}else if(shown === 1) {
			this.map.setCenter(mapBounds.getSouthWest(),10);
		}
	}
	
}

TravelogMap.prototype.mapLocations = function(locationIDs) {
	if(isMapEnabled) {
		var ids = new Array();
		ids = locationIDs.split(',');
		var getIDs = '';
		for(i=0;i<ids.length;i++) {
			this.contents.locations[ids[i]] = 'l';
			if(!tLocations[ids[i]]) getIDs += ids[i]+',';
		}
		getIDs = getIDs.substring(0, getIDs.length-1);
		
		// Load any missing locations
		if(getIDs.length > 0) {
			handler = this.myName+'.displayLocations(lastResults.locations)';
			travelogGetXMLData(XMLAddress,'','l',getIDs,'','','','');
		}else{
			// Update the map if we already have all the info
			this.displayLocations(locationIDs);
		}
	}
}

TravelogMap.prototype.displayLocations = function(locationIDs) {
	if(isMapEnabled && isGMapsJSLoaded) {
		if(typeof(locationIDs) == 'string') {var locations = locationIDs.split(',');}else{var locations = locationIDs;}
		i=0;
		for (locKey in locations) {
			tLocationID = locations[locKey];
			var point = new google.maps.LatLng(tLocations[tLocationID].latitude, tLocations[tLocationID].longitude);
			var contents = tLocations[tLocationID].name;
//			this.markers[tLocationID] = this.createMarker(point, contents, tLocations[tLocationID].marker);
			this.markers[tLocationID] = this.createNumberedMarker(point, contents, contents, i+1);
			i++;
		}
	}
	if(isMapEnabled) this.updateMap();
}

TravelogMap.prototype.unmapLocations = function(locationIDs) {
	if(isMapEnabled) {
		var ids = new Array(); ids = locationIDs.split(',');
		for(id in ids) {
			this.contents.locations[ids[id]] = 'n';
			if(isGMapsJSLoaded) {
				this.map.removeOverlay(this.markers[ids[id]]);
				this.markers[ids[id]] = false;
			}
		}
		this.updateMap();
	}
}

TravelogMap.prototype.mapTrips = function(tripIds) {
	var toLoad = '';
	var trips = tripIds.split(',');
	for (tripkey in trips) {
		this.contents.trips[trips[tripkey]] = true;
		if(typeof(tTrips[trips[tripkey]]) == 'undefined') toLoad += trips[tripkey]+',';
	}
	toLoad = toLoad.substring(0, toLoad.length-1);
	
	if(toLoad.length > 0) {
		handler = this.myName+'.parseAddedTrips(lastResults.trips)';
		var requestKey = travelogGetXMLData(XMLAddress, '', 't', '', toLoad, '', '', '');
		
	}else{
		this.parseAddedTrips(tripIds);
	}
}

TravelogMap.prototype.parseAddedTrips = function(tripIds) {
	var locationID = '';
	if(tripIds.length > 0) {
		if(typeof(tripIds) == 'string') {var trips = tripIds.split(',');}else{var trips = tripIds;}
		for(tripkey in trips) {
			var tripPath = new Array();
			var tmpStr="";
			for(k = 0; k < tTrips[trips[tripkey]].stops.length; k++) {
				locationID = tTrips[trips[tripkey]].stops[k].ID;
				this.contents.locations[locationID] = 't';
				if(isGMapsJSLoaded) {
					tripPath[k] = new google.maps.LatLng(tLocations[locationID].latitude, tLocations[locationID].longitude);
					if(typeof(this.markers[locationID]) != 'object') {
//						this.markers[locationID] = createMarker(tripPath[k], tLocations[locationID].name, tLocations[locationID].marker); // TODO test with argument
                        tmpStr="<center><b>"+tLocations[locationID].name+"</b></center>";
						this.markers[locationID] = this.createNumberedMarker(tripPath[k], tLocations[locationID].name, tmpStr, k+1); 
					}
				}
			}
			if(isGMapsJSLoaded) {
				this.overlays[trips[tripkey]] = new google.maps.Polyline( 
                    { map: this.map, path: tripPath, strokeColor: '#ff0000', strokeOpacity: 0.9, strokeWeight: 2 } );
			}
		}
	}
	
	this.updateMap();
	if(this.dataForm != null) {
		this.dataForm.refreshLocationBoxes();
	}
	
}

TravelogMap.prototype.unmapTrips = function(tripIds) {
	var trips = tripIds.split(',');
	for (tripkey in trips) {
		this.contents.trips[trips[tripkey]] = false;
		if(isGMapsJSLoaded) this.map.removeOverlay(this.overlays[trips[tripkey]]);
		if(typeof(tTrips[trips[tripkey]]) != 'undefined') {
			for(stopkey in tTrips[trips[tripkey]].stops) {
				locationID = tTrips[trips[tripkey]].stops[stopkey].ID;
				this.contents.locations[locationID] = 'n';
				if(isGMapsJSLoaded) {
					this.map.removeOverlay(this.markers[locationID]);
					this.markers[locationID] = null;
				}
			}
		}
	}
	this.updateMap();
	if(typeof(this.dataForm.displayType) != 'undefined') {
		this.dataForm.refreshLocationBoxes();
	}
}

TravelogMap.prototype.mapType = function(mapCode) {
	switch(mapCode) {
		case "map" :
			return google.maps.MapTypeId.NORMAL;
		case "satellite" :
			return google.maps.MapTypeId.SATELLITE;
		case "hybrid" :
			return google.maps.MapTypeId.HYBRID;
	}
}
// ################## GoogleMap Helper Functions ########################

// ################## GoogleMap Helper Functions ########################
// TODO test this
function initializeFlags(filePath) {
	if(isGMapsJSLoaded) {
		// Setup Travelog flag icons
		var flagIcon = new google.maps.Marker( {
            //shadow : filePath+"flag_shadow.png"
		    //iconSize = new GSize(13, 19),
		    //shadowSize = new GSize(19, 19),
		    //anchorPoint = new GPoint(1, 18),
		    //infoWindowAnchor = new GPoint(12, 3),
        } );
		// baseIcon.infoShadowAnchor = new GPoint(18, 25);
		
		var blueFlagIcon = new google.maps.Marker(flagIcon); 
			blueFlagIcon.image=filePath+"flag_blue.png";
		var greenFlagIcon = new google.maps.Marker(flagIcon); 
			greenFlagIcon.image=filePath+"flag_green.png";
		var greyFlagIcon = new google.maps.Marker(flagIcon); 
			greyFlagIcon.image=filePath+"flag_grey.png";
		var orangeFlagIcon = new google.maps.Marker(flagIcon); 
			orangeFlagIcon.image=filePath+"flag_orange.png";
		var pinkFlagIcon = new google.maps.Marker(flagIcon); 
			pinkFlagIcon.image=filePath+"flag_pink.png";
		var purpleFlagIcon = new google.maps.Marker(flagIcon); 
			purpleFlagIcon.image=filePath+"flag_purple.png";
		var redFlagIcon = new google.maps.Marker(flagIcon); 
			redFlagIcon.image=filePath+"flag_red.png";
		var yellowFlagIcon = new google.maps.Marker(flagIcon); 
			yellowFlagIcon.image=filePath+"flag_yellow.png";
		
		// Create an array of the icons so they are easily accessible
		var icons = new Object();
			icons['redFlag'] = redFlagIcon;
			icons['blueFlag'] = blueFlagIcon;
			icons['greenFlag'] = greenFlagIcon;
			icons['orangeFlag'] = orangeFlagIcon;
			icons['yellowFlag'] = yellowFlagIcon;
			icons['pinkFlag'] = pinkFlagIcon;
			icons['purpleFlag'] = purpleFlagIcon;
			icons['greyFlag'] = greyFlagIcon;
			
		return icons;
	}else{
		return new Array();
	}
}

TravelogMap.prototype.createMarker = function(latlng, contents, icon) {
	// Creates a marker whose info window displays the given number
    if(icon != '') {
	    var marker = new google.maps.Marker( { 
            position : latlng, 
            title: contents
        } );
    }else{
	    var marker = new google.maps.Marker( { 
            position : latlng, 
            title: contents,
            icon : icon//,
            //shadow : "flag_shadow.png"
        } );
    }

    var infoWindow = new google.maps.InfoWindow();
    google.maps.event.addListener(marker, 'click', function () {
        infoWindow.setContent(contents);
        infoWindow.open(map,marker);
    });
       
    marker.setMap(this.map);

    return marker;
}

TravelogMap.prototype.createNumberedMarker = function(latlng, title, contents, number) {
    if (isNaN(parseInt(number))) {
        number = "";
    } else if (!isNaN(parseInt(number)) && ((number < 0) || (number > 99))) {
        number = "";
    } else if ((typeof(number)=="undefined") || (number==null)) { 
        number = "" 
    }

	// Creates a marker whose info window displays the given number
	var marker = new google.maps.Marker( { 
        position : latlng, 
        title: title,
        //http://www.geocodezip.com/basic8j.asp?filename=example_number.xml
        icon : XMLAddress + "/../markers/marker" + number + ".png"
    } );

    var infoWindow = new google.maps.InfoWindow();
    google.maps.event.addListener(marker, 'click', function () {
        infoWindow.setContent(contents);
        infoWindow.open(map,marker);
    });

    marker.setMap(this.map);

    return marker;
}
// Function for setting map display to a certain bounds box
function centerAndZoomOnBounds(map,bounds, padPercent) { 
	var sw = bounds.getSouthWest();
	var ne = bounds.getNorthEast();
	var scaler = 1+padPercent/100;
	var boundsSpan = bounds.toSpan();
	var newSpan = new google.maps.LatLng(boundsSpan.lat()*scaler,boundsSpan.lng()*scaler);
	var spanDiff = new google.maps.LatLng((newSpan.lat()-boundsSpan.lat())/2,(newSpan.lng()-boundsSpan.lng())/2);
	var newBounds = new google.maps.LatLngBounds(new google.maps.LatLng(sw.lat()-spanDiff.lat(),sw.lng()-spanDiff.lng()),
                                                     new google.maps.LatLng(ne.lat()+spanDiff.lat(),ne.lng()+spanDiff.lng()));
    map.fitBounds(newBounds);
}


// ################## General Helper Functions ########################

function decimalRound(num, places) {
	var newNum = num * Math.pow(10,places);
	newNum = Math.round(newNum);
	return newNum/Math.pow(10,places);
}

function getObjLength(obj) {
	var i=0;
	for(item in obj) i++;
	return i;
}

function setDisp(elemID, newVal) {
	document.getElementById(elemID).style.display = newVal;
}

// ################## AutoComplete Functions ########################

TravelogDataForm.prototype.acIsVisible = function() {
    return this.ac.renderObj.style.visibility == 'visible';
}

TravelogDataForm.prototype.acRender = function() {
    // Escape regexp characters (quotemeta)..
    var regexp = new RegExp("(" + acEscape(this.searchQuery.value) + ")", "i");
    if (lastResults.locations.length > 0) {
		for (i = 0; i < lastResults.locations.length; i++) {
			var locationID = lastResults.locations[i];
			var row, newHTML = tLocations[locationID].name.replace(regexp,
					   '<span class="acHighlight">$1</span>');
			if (i >= this.ac.renderObj.childNodes.length) {
				row = document.createElement('div');
				this.ac.renderObj.appendChild(row);
			} else {
				row = this.ac.renderObj.childNodes[i];
				if (row.innerHTML == newHTML) {
					// Already up to date
					continue;
				}
			}
			row.className = 'acNotSelected';
			row.title = 'Click to chose this location';
			row.innerHTML = newHTML;
			row.ac_data = locationID;
			row.ac_index = i;
			row.onmousedown = new Function(this.myName+'.acChoose(this.ac_data);');
			row.onmouseover = new Function(this.myName+'.acSelect(this);');
			row.onmouseout = new Function(this.myName+'.acDeselect();');
		}
		while (i < this.ac.renderObj.childNodes.length) {
			this.ac.renderObj.removeChild(this.ac.renderObj.childNodes[i]);
		}
		this.ac.renderObj.style.top = eval(acGetTop(this.searchQuery) + this.searchQuery.offsetHeight) + 'px';
	    this.ac.renderObj.style.left = acGetLeft(this.searchQuery) + 'px';
		this.ac.renderObj.style.visibility = 'visible';
    } else {
		this.ac.renderObj.style.visibility = 'hidden';
    }
    this.ac.current = -1;
}

TravelogDataForm.prototype.acMove = function(delta) {
	var id = this.searchQuery.id;
    var acId = id + '_ac';
    if (this.ac.renderObj.childNodes.length == 0) return;
    var current = this.ac.current + delta;
    if (current < 0) {
		current += this.ac.renderObj.childNodes.length;
    }
    current = current % this.ac.renderObj.childNodes.length;
    this.acSelect(this.ac.renderObj.childNodes[current]);
}

TravelogDataForm.prototype.acSelect = function(row) {
    this.acDeselect();
    row.className = 'acSelected';
    this.ac.current = row.ac_index;
}

TravelogDataForm.prototype.acDeselect = function() {
    if (this.ac.current != -1) {
		this.ac.renderObj.childNodes[this.ac.current].className = 'acNotSelected';
		this.ac.current = -1;
    }
}

TravelogDataForm.prototype.acChoose = function(locationID) {
    var current = this.ac.current;
    this.ac.current = -1; // Reset the selected index
    if(locationID != -1) {
		eval(this.ac.action+'(locationID)');
		return;
    }else if (current != -1) {
		eval(this.ac.action+'('+this.ac.renderObj.childNodes[current].ac_data+')');
		return;
    }
}

TravelogDataForm.prototype.acHide = function() {
    if (this.timer) {
		clearTimeout(this.timer);
    }
	this.ac.renderObj.style.visibility = 'hidden';
}

TravelogDataForm.prototype.acHandleEvent = function(event) {
    switch(event.keyCode) {
    case 38: // up key
		this.acMove(-1);
		event.preventDefault();
	break;

    case 40: // down key
		this.acMove(1);
		event.preventDefault();
	break;

    case 9: // tab
	if (this.acIsVisible()) {
	    this.acChoose(-1);
	    return true;
	}
	break;

    case 13: // enter
	if (this.acIsVisible()) {
	   this.acChoose(-1);
	   return false;
	}
	break;

    case 27: // escape
		this.acDeselect();
		this.acHide();
	break;

    default:
	if (this.timer) {
	    clearTimeout(this.timer);
	}
	
	handler = this.myName+'.render()';
	this.timer = window.setTimeout("travelogGetXMLData(XMLAddress, "+this.myName+".searchQuery.value, 'l', '', '', '', '10', 'name')",200);
    }
    return true;
}

function acEscape(s) {
    // Convert to html entities, then escape regexp characters (quotemeta)..
    return s.replace(/&/, '&amp;').replace(/"/, '&quot;').replace(/>/, '&gt;').replace(/</, '&lt;')
	    .replace(/([\\\[\](){}^$.*+?|])/g, '\\$1');
}
function acUnentity(s) {
    return s.replace(/&quot;/, '"').replace(/&gt;/, '>').replace(/&lt;/, '<').replace(/&amp;/, '&');
}
function acGetLeft(element) {
    var offset = 0;
    while (element) {
	offset += element.offsetLeft;
	element = element.offsetParent;
    }
    return offset;
}
function acGetTop(element) {
    var offset = 0;
    while (element) {
	offset += element.offsetTop;
	element = element.offsetParent;
    }
    return offset;
}

