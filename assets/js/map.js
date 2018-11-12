function initMap() {
// Styles a map in night mode.
var map = new google.maps.Map(document.getElementById('map'), {
	center: {lat: 40.674, lng: -73.945},
	zoom: 12,
	styles: [
	{elementType: 'geometry', stylers: [{color: '#00bbae'}]},
	{elementType: 'labels.text.stroke', stylers: [{color: '#00bbae'}]},
	{elementType: 'labels.text.fill', stylers: [{color: '#000000'}]},
	{
		featureType: 'administrative.locality',
		elementType: 'labels.text.fill',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'poi',
		elementType: 'labels.text.fill',
		stylers: [{color: '#ffffff'}]
	},
	
	{
		featureType: 'poi.park',
		elementType: 'labels.text.fill',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'road',
		elementType: 'geometry',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'road',
		elementType: 'geometry.stroke',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'road',
		elementType: 'labels.text.fill',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'road.highway',
		elementType: 'geometry',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'road.highway',
		elementType: 'geometry.stroke',
		stylers: [{color: '#1f2835'}]
	},
	{
		featureType: 'road.highway',
		elementType: 'labels.text.fill',
		stylers: [{color: '#f3d19c'}]
	},
	{
		featureType: 'transit',
		elementType: 'geometry',
		stylers: [{color: '#2f3948'}]
	},
	{
		featureType: 'transit.station',
		elementType: 'labels.text.fill',
		stylers: [{color: '#ffffff'}]
	},
	{
		featureType: 'water',
		elementType: 'geometry',
		stylers: [{color: '#17263c'}]
	},
	{
		featureType: 'water',
		elementType: 'labels.text.fill',
		stylers: [{color: '#515c6d'}]
	},
	{
		featureType: 'water',
		elementType: 'labels.text.stroke',
		stylers: [{color: '#17263c'}]
	}
	]
});
}
