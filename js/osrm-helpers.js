/**
 * OSRM helpers: snap points to the road network and get route geometry along roads.
 * Uses public OSRM server (router.project-osrm.org). For production, consider self-hosting.
 */
(function (global) {
    var OSRM_BASE = 'https://router.project-osrm.org';

    /**
     * Snap a point to the nearest location on the road network.
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     * @param {function} callback - function(snappedLat, snappedLng) on success, or function(null) on error
     */
    function snapToRoad(lat, lng, callback) {
        var url = OSRM_BASE + '/nearest/v1/driving/' + lng + ',' + lat;
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 'Ok' && data.waypoints && data.waypoints[0]) {
                    var loc = data.waypoints[0].location; // [lng, lat]
                    callback(loc[1], loc[0]);
                } else {
                    callback(null);
                }
            })
            .catch(function () { callback(null); });
    }

    /**
     * Get route geometry that follows roads between waypoints.
     * @param {Array} waypoints - Array of [lat, lng]
     * @param {function} callback - function(latlngs) with Leaflet-style [lat, lng][] or function(null) on error
     */
    function getRouteGeometry(waypoints, callback) {
        if (!waypoints || waypoints.length < 2) {
            callback(null);
            return;
        }
        // OSRM expects longitude,latitude
        var coords = waypoints.map(function (w) { return w[1] + ',' + w[0]; }).join(';');
        var url = OSRM_BASE + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.code === 'Ok' && data.routes && data.routes[0] && data.routes[0].geometry && data.routes[0].geometry.coordinates) {
                    // GeoJSON is [lng, lat]; Leaflet wants [lat, lng]
                    var coords = data.routes[0].geometry.coordinates;
                    var latlngs = coords.map(function (c) { return [c[1], c[0]]; });
                    callback(latlngs);
                } else {
                    callback(null);
                }
            })
            .catch(function () { callback(null); });
    }

    global.snapToRoad = snapToRoad;
    global.getRouteGeometry = getRouteGeometry;
})(typeof window !== 'undefined' ? window : this);
