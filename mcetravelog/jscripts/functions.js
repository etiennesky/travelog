var formObj = new Object();
var inTinyMCE = true;
var callingForm = '';
var callingField = '';
var oldTagAttrs = '';
var dataForm = null;

function setupPage() {
	var temp = document.getElementById('inTinyMCE').value;
	if (temp == 1) inTinyMCE = true; else inTinyMCE = false;
	var initType = document.getElementById('initType').value;
	callingForm = document.getElementById('callingForm').value;
	callingField = document.getElementById('callingField').value;
	formObj = document.forms[0]; // Set up formObj for easy reference
	
	maps[0] = new TravelogMap('maps[0]', 'map', new Object());
	dataForm = new TravelogDataForm('dataForm', 'travelogMCEForm', maps[0], 'renderResults(this)', 'travelogLocationResults', 'list', 'showNumResults', 'locationSearchQuery', 'showCategory', 'showOrder', '');
	dataForm.showLinks = true;
	maps[0].dataForm = dataForm;
	
	if(initType == '') initType = 'link';
	setTagType(initType); // Show the proper form fields
	if(initType == 'map') document.getElementById('mcetlInsertWhat_travelogmap').checked = true
	
	// Check to see if there is any info passed (we're modifying an existing tag)
	if (inTinyMCE) {
		oldTagAttrs = tinyMCE.getWindowArg('oldTagAttrs'); // string containing the attributes from previous state of the tag
		if(typeof(oldTagAttrs) == 'undefined') var oldTagAttrs = '';
		selectedText = tinyMCE.getWindowArg('linkText'); // string containing the attributes from previous state of the tag
		if(typeof(selectedText) == 'undefined') var selectedText = '';
	}
	if(typeof(oldTagAttrs) != 'undefined' && oldTagAttrs != '') {
		// Restore quotes in the old tag attributes so we can do processing normally
		oldTagAttrs = oldTagAttrs.replace(/\|/gi,'"');
		var startPos = 0;
		var mapAttrs = new Array('map_type', 'height', 'width', 'zoom', 'controls', 'show_types', 'scale');
		var params = new Object();
		for(key in mapAttrs) {
			// Set map view params to old values
			startPos = oldTagAttrs.indexOf(mapAttrs[key]+'=');
			if(startPos != -1) params[mapAttrs[key]] = oldTagAttrs.substring(startPos+mapAttrs[key].length+2, oldTagAttrs.indexOf('"', startPos+mapAttrs[key].length+2));
		}
		
		if(typeof(params.map_type) != 'undefined') formObj.mapType.value = params.map_type;
		if(typeof(params.height) != 'undefined') formObj.mapHeight.value = params.height;
		if(typeof(params.width) != 'undefined') formObj.mapWidth.value = params.width;
		if(typeof(params.zoom) != 'undefined') formObj.zoomLevel.value = params.zoom;
		if(typeof(params.controls) != 'undefined') formObj.mapControls.value = params.controls;
		if(typeof(params.show_types) != 'undefined') if(params.show_types === '1') {formObj.mapShowTypes.checked = true;}else{formObj.mapShowTypes.checked = false;}
		if(typeof(params.scale) != 'undefined') if(params.scale === '1') {formObj.mapShowScale.checked = true;}else{formObj.mapShowScale.checked = false;}
		
		// Initialize locations already set
		var lids = '';
		startPos = oldTagAttrs.indexOf('ids=');
		if(startPos != -1) lids = oldTagAttrs.substring(startPos+5, oldTagAttrs.indexOf('"', startPos+5));
		if(lids.length > 0) maps[0].mapLocations(lids);
		
		// Initialize trips already set
		var tids = '';
		startPos = oldTagAttrs.indexOf('trips=');
		if(startPos != -1) tids = oldTagAttrs.substring(startPos+7, oldTagAttrs.indexOf('"', startPos+7));
		if(tids.length > 0) maps[0].mapTrips(tids);
		
		// Map is updated when locations & trips are added, so it should now be up-to-date, but need to refresh checkboxes
		dataForm.refreshLocationBoxes();
	}
	
	if(typeof(selectedText) != 'undefined' && selectedText != '') {
		var linkText = document.getElementById('linkText');
		linkText.value = selectedText;
	}
	
	dataForm.doSearch();
}

function setTagType(tagType) {
	if(tagType == 'link') {
		setDisp('travelogmapOptions1', 'none');
		setDisp('travelogmapOptions2', 'none');
		setDisp('traveloglinkOptions1', 'block');
//		setDisp('travelogInstructions','inline');
//		setDisp('travelogmapInstructions','none');
		setDisp('tripResults','none');
		setDisp('mapInsertButton','none');
		setDisp('linkInsertButton','block');
	}else{
		setDisp('travelogmapOptions1', 'inline');
		setDisp('travelogmapOptions2', 'block');
		setDisp('traveloglinkOptions1', 'none');
//		setDisp('travelogInstructions','none');
//		setDisp('travelogmapInstructions','inline');
		setDisp('tripResults','block');
		setDisp('mapInsertButton','block');
		setDisp('linkInsertButton','none');
	}
}

function setLinkText(box) {
	var toggle = document.getElementById('linkTextUseName');
	var linkText = document.getElementById('linkText');
	if(toggle.checked) {
		if(typeof(box) != 'undefined' && box.checked) {
			var tLocationID = box.id.substring(1, box.id.length);
			linkText.value = tLocations[tLocationID].name;
		}else{
			for(tLocationID in dataForm.linkedMap.contents.locations) {
				if(dataForm.linkedMap.contents.locations[tLocationID] == 'l') {
					linkText.value = tLocations[tLocationID].name;
					return;
				}
			}
			linkText.value = '';
		}
	}
}

function renderResults(obj) {
	// Clear existing results
	var locationRender = document.getElementById('travelogLocationResults');
	var tripRender = document.getElementById('travelogTripResults');
	
	// Render locations
	obj.emptyRenderer(locationRender);
	var shown = 0;
	if(getObjLength(lastResults.locations) > 0) {
		for (tLocationKey in lastResults.locations) {
			var tLocation = tLocations[lastResults.locations[tLocationKey]];
			var isMapped = false;
			var inTrip = false;
			if(obj.linkedMap.contents.locations[tLocation.ID] == 't') inTrip = true;
			if(obj.linkedMap.contents.locations[tLocation.ID] == 'l' || obj.linkedMap.contents.locations[tLocation.ID] == 't') isMapped = true;
	
			var rowData = '<input type="checkbox" class="locationMapToggle" id="l'+tLocation.ID+'" value="0" title="Map this location" onclick="'+obj.myName+'.locationBoxClicked(this);setLinkText(this);"';
			if(isMapped) rowData += ' checked="checked"';
			if(inTrip || !isMapEnabled) rowData += ' disabled="disabled"';
			rowData += '/> ';
			rowData += tLocation.name;
			locationRender.appendChild(document.createElement('li'));
			locationRender.lastChild.innerHTML = rowData;
			if(shown%2==1) locationRender.lastChild.className = "alternate";
			shown++;
		}
	}else{
		locationRender.appendChild(document.createElement('li'));
		locationRender.lastChild.innerHTML = 'No locations match your search';
		locationRender.lastChild.style.padding = '4px';
	}
	var numLocationResults = document.getElementById('numLocationResults');
	numLocationResults.innerHTML = shown;
	
	// Render trips
	obj.emptyRenderer(tripRender);
	var shown = 0;
	if(getObjLength(lastResults.trips) > 0) {
		for (tTripKey in lastResults.trips) {
			var tTrip = tTrips[lastResults.trips[tTripKey]];
			var isMapped = false;
			if(obj.linkedMap.contents.trips[tTrip.ID] === true) isMapped = true;
	
			var rowData = '<input type="checkbox" class="tripMapToggle" id="t'+tTrip.ID+'" value="0" title="Map this trip" onclick="'+obj.myName+'.tripBoxClicked(this)"';
			if(isMapped) rowData += ' checked="checked"';
			rowData += '/> ';
			rowData += tTrip.name;
			tripRender.appendChild(document.createElement('li'));
			tripRender.lastChild.innerHTML = rowData;
			if(shown%2==1) tripRender.lastChild.className = "alternate";
			shown++;
		}
	}else{
		tripRender.appendChild(document.createElement('li'));
		tripRender.lastChild.innerHTML = 'No trips match your search';
		tripRender.lastChild.style.padding = '4px';
	}
	var numTripResults = document.getElementById('numTripResults');
	numTripResults.innerHTML = shown;
}

function BlockToggle(objId, togId, display) {
	var o = document.getElementById(objId), t = document.getElementById(togId);
	if (o.style.display == 'none') {
	if (!display) display = 'block';
	if (display == 'table-row') {   /* No table-row for IE */
		var agent = navigator.userAgent.toLowerCase();
		if (agent.indexOf('msie') >= 0 && agent.indexOf('opera') < 0) display = 'block';
	}
	o.style.display = display;
	t.innerHTML = '&#150;';
	} else {
	o.style.display = 'none';
	t.innerHTML = '+';
	}
}

function insertAtCursor(myField, myValue) {
	//IE support
	if (document.selection && !window.opera) {
		myField.focus();
		sel = window.opener.document.selection.createRange();
		sel.text = myValue;
	}
	//MOZILLA/NETSCAPE/OPERA support
	else if (myField.selectionStart || myField.selectionStart == '0') {
		var startPos = myField.selectionStart;
		var endPos = myField.selectionEnd;
		myField.value = myField.value.substring(0, startPos)
		+ myValue
		+ myField.value.substring(endPos, myField.value.length);
	} else {
		myField.value += myValue;
	}
}

function insertTravelogLink() {
	var htmlCode = '';
	var zoom = document.getElementById('zoomLevel').value;
	var mapType = document.getElementById('mapType').value;
	var linkText = document.getElementById('linkText').value;
	
	// Get ID of location to map (first one if multiple are selected)
	var locationID = '';
	for(tLocationID in dataForm.linkedMap.contents.locations) {
		if(dataForm.linkedMap.contents.locations[tLocationID] == 'l') {
			locationID = tLocationID;
			break;
		}
	}

	// Build a dummy <a> tag for TinyMCE
	var mapURL = 'http://maps.google.com/?id='+locationID+'&';
	if('' != mapType) mapURL += 'view='+mapType+'&';
	if('' != zoom) mapURL += 'zoom='+zoom+'&';
	var fakeTag = '<a id="mce_plugin_travelog_travelog" href="'+mapURL+'" title="Map Location">'+linkText+'</a>';

	// Build a proper <travelog> tag
	var realTag = '<travelog id="'+locationID+'"';
	if('' != mapType) realTag += ' view="'+mapType+'"';
	if('' != zoom) realTag += ' zoom="'+zoom+'"';
	realTag += '>'+ linkText + '</travelog>';

	if(window.tinyMCE)
		window.opener.tinyMCE.execCommand("mceInsertContent",true,fakeTag);
	else
		insertAtCursor(window.opener.document.forms[callingForm].elements[callingField],realTag);
	window.close();
}

function insertTravelogMap() {
	// Gather all the info we need to build the tag
	var params = new Object();
	
	// Get trips
	params.trips = '';
	for(tripkey in maps[0].contents.trips) {
		if(maps[0].contents.trips[tripkey]) params.trips += tripkey+',';
	}
	params.trips = params.trips.substring(0, params.trips.length-1);
	
	// Get locations
	params.ids = '';
	for(locationkey in maps[0].contents.locations) {
		if(maps[0].contents.locations[locationkey] == 'l') params.ids += locationkey+',';
	}
	params.ids = params.ids.substring(0, params.ids.length-1);
	
	// Get map settings
	params.map_type = formObj.mapType.value;
	params.zoom = formObj.zoomLevel.value;
	params.controls = formObj.mapControls.value;
	params.width = formObj.mapWidth.value;
	params.height = formObj.mapHeight.value;
	params.show_types = (formObj.mapShowTypes.checked) ? 1 : 0;
	params.scale = (formObj.mapShowScale.checked) ? 1 : 0;
	
	var tagAttrs = '';
	// Build our tag attributes
	for (param in params) {
		if (params.eval(param) !== '') tagAttrs += ' ' + param + '=|' + params.eval(param) + '|'; // quotes are HTML encoded so they don't mess up the img's alt attribute
	}
	var placeholdermap_path = '';
	if(window.tinyMCE) placeholdermap_path = tinyMCE.baseURL;
	placeholdermap_path += "/plugins/mcetravelog/images/map_placeholder.jpg";
	
	var realTag = '<!--travelogmap' + tagAttrs.replace(/\|/gi,'\"') + '-->';
	var fakeTag = '<img id="mce_plugin_travelog_travelogmap" src="'+placeholdermap_path+'" alt="'+ tagAttrs + '" height="200" width="200" />';
	
	var mapAlignLeft = document.getElementById('mapAlignLeft');
	var mapAlignRight = document.getElementById('mapAlignRight');
	
	if(mapAlignLeft.checked) {
		fakeTag = '<div style="float:left;margin-right:5px;">'+fakeTag+'</div>';
		realTag = '<div style="float:left;margin-right:5px;">'+realTag+'</div>';
	}else if(mapAlignRight.checked) {
		fakeTag = '<div style="float:right;margin-left:5px;">'+fakeTag+'</div>';
		realTag = '<div style="float:right;margin-left:5px;">'+realTag+'</div>';
	}

	if(window.tinyMCE)
		window.opener.tinyMCE.execCommand("mceInsertContent",true,fakeTag);
	else
		insertAtCursor(window.opener.document.forms[callingForm].elements[callingField],realTag);
	window.close();
}