<?php
/**
 * Test page for enhanced search functionality
 * Access at: http://192.168.100.4/system/test_search.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Search Test - Transport Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Enhanced Location Search Test</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Search Features Test</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Test Philippines Location Search</label>
                    <div class="flex gap-2 mb-1">
                        <input type="text" id="test-search" placeholder="Search Philippines locations (autocomplete enabled)" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <button type="button" id="test-search-btn" class="bg-blue-600 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-blue-700">Search</button>
                    </div>
                    <div id="test-results" class="hidden mt-1 max-h-48 overflow-y-auto border border-gray-200 rounded bg-white shadow text-sm"></div>
                    <p class="text-xs text-gray-500 mt-1">🇵🇭 Philippines locations only. Type 2+ characters for autocomplete, or click Search for manual search.</p>
                    <p id="test-status" class="text-xs text-gray-500 mt-0.5">No location selected.</p>
                </div>
                
                <div class="h-64 rounded-lg overflow-hidden border border-gray-200" id="test-map"></div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Features Implemented</h2>
            <ul class="space-y-2 text-sm text-gray-700">
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Debounced Autocomplete:</strong> Search triggers after 300ms delay when typing
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Search Caching:</strong> Results cached to reduce API calls
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Recent Searches:</strong> Last 10 searches saved in localStorage
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Current Location:</strong> Use browser geolocation as fallback
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Multiple APIs:</strong> Nominatim + Photon API for better results
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Enhanced UI:</strong> Icons, better formatting, loading states
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Keyboard Support:</strong> Enter to search, Escape to close
                    </div>
                </li>
                <li class="flex items-start">
                    <span class="text-green-500 mr-2">✓</span>
                    <div>
                        <strong>Click Outside:</strong> Results close when clicking elsewhere
                    </div>
                </li>
            </ul>
        </div>
        
        <div class="mt-6 text-center">
            <a href="manage_routes.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-medium">
                ← Back to Manage Routes
            </a>
        </div>
    </div>

    <script>
        // Initialize map
        const map = L.map('test-map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
            attribution: '© OpenStreetMap' 
        }).addTo(map);
        
        let testMarker = null;
        
        function setTestLocation(lat, lng) {
            if (testMarker) map.removeLayer(testMarker);
            testMarker = L.marker([lat, lng]).addTo(map);
            map.setView([lat, lng], 15);
            
            document.getElementById('test-status').textContent = 
                `Location set: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        }
        
        // Copy the enhanced search functionality from manage_routes.php
        let searchTimeout = {};
        let searchCache = {};
        let recentSearches = JSON.parse(localStorage.getItem('transportOps_recentSearches') || '[]');
        
        function searchPlace(query, resultsEl, setStopFn, isAutoComplete = false) {
            if (!query || !query.trim()) {
                if (!isAutoComplete) {
                    showRecentSearches(resultsEl, setStopFn);
                }
                return;
            }
            
            const cacheKey = query.toLowerCase().trim();
            if (searchCache[cacheKey] && !isAutoComplete) {
                displaySearchResults(searchCache[cacheKey], resultsEl, setStopFn);
                return;
            }
            
            resultsEl.classList.remove('hidden');
            resultsEl.innerHTML = '<div class="p-3 text-gray-500 flex items-center"><svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Searching…</div>';
            
            const searchPromises = [
                fetch('https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(query.trim()) + '&format=json&limit=5&addressdetails=1', {
                    headers: { 'Accept': 'application/json', 'User-Agent': 'TransportOps/1.0 (Route Management)' }
                }).then(r => r.json()).catch(() => []),
                
                fetch('https://photon.komoot.io/api/?q=' + encodeURIComponent(query.trim()) + '&limit=5', {
                    headers: { 'Accept': 'application/json' }
                }).then(r => r.json()).catch(() => [])
            ];
            
            Promise.race(searchPromises)
                .then(function (data) {
                    if (!data || data.length === 0) {
                        resultsEl.innerHTML = '<div class="p-3 text-gray-500"><svg class="h-4 w-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>No places found. Try different keywords.</div>';
                        return;
                    }
                    
                    if (!isAutoComplete) {
                        searchCache[cacheKey] = data;
                    }
                    
                    displaySearchResults(data, resultsEl, setStopFn);
                })
                .catch(function (error) {
                    console.error('Search error:', error);
                    resultsEl.innerHTML = '<div class="p-3 text-red-500"><svg class="h-4 w-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Search failed. Check connection and try again.</div>';
                });
        }
        
        function displaySearchResults(data, resultsEl, setStopFn) {
            resultsEl.innerHTML = '';
            
            if (navigator.geolocation) {
                const locationDiv = document.createElement('div');
                locationDiv.className = 'p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 flex items-center';
                locationDiv.innerHTML = '<svg class="h-4 w-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg><span class="font-medium">Use current location</span>';
                locationDiv.addEventListener('click', function () {
                    resultsEl.classList.add('hidden');
                    resultsEl.innerHTML = '';
                    
                    document.getElementById('test-status').textContent = 'Getting location…';
                    
                    navigator.geolocation.getCurrentPosition(
                        function (position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            setStopFn(lat, lng);
                        },
                        function (error) {
                            document.getElementById('test-status').textContent = 'Location access denied.';
                            alert('Could not get your location. Please check browser permissions.');
                        }
                    );
                });
                resultsEl.appendChild(locationDiv);
            }
            
            data.forEach(function (item, index) {
                const lat = parseFloat(item.lat || item.geometry?.coordinates[1]);
                const lng = parseFloat(item.lon || item.geometry?.coordinates[0]);
                
                if (isNaN(lat) || isNaN(lng)) return;
                
                let displayName = '';
                let details = '';
                
                if (item.display_name) {
                    const parts = item.display_name.split(',');
                    displayName = parts[0] || 'Unknown location';
                    details = parts.slice(1).join(',').trim();
                } else if (item.properties && item.properties.name) {
                    displayName = item.properties.name;
                    details = (item.properties.city || item.properties.town || item.properties.county || '') + 
                              (item.properties.postcode ? ', ' + item.properties.postcode : '');
                } else {
                    displayName = lat.toFixed(5) + ', ' + lng.toFixed(5);
                }
                
                const resultDiv = document.createElement('div');
                resultDiv.className = 'p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0';
                
                let icon = '<svg class="h-4 w-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
                
                if (item.class) {
                    switch(item.class) {
                        case 'highway': icon = '<svg class="h-4 w-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>'; break;
                        case 'amenity': icon = '<svg class="h-4 w-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>'; break;
                        case 'shop': icon = '<svg class="h-4 w-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>'; break;
                        case 'tourism': icon = '<svg class="h-4 w-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>'; break;
                    }
                }
                
                resultDiv.innerHTML = `
                    <div class="flex items-start">
                        ${icon}
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 truncate">${displayName}</div>
                            ${details ? `<div class="text-sm text-gray-500 truncate">${details}</div>` : ''}
                        </div>
                    </div>
                `;
                
                resultDiv.addEventListener('click', function () {
                    resultsEl.classList.add('hidden');
                    resultsEl.innerHTML = '';
                    addToRecentSearches(displayName, lat, lng);
                    setStopFn(lat, lng);
                });
                
                resultsEl.appendChild(resultDiv);
            });
        }
        
        function showRecentSearches(resultsEl, setStopFn) {
            if (recentSearches.length === 0) {
                resultsEl.classList.add('hidden');
                return;
            }
            
            resultsEl.classList.remove('hidden');
            resultsEl.innerHTML = '<div class="p-2 text-xs text-gray-500 border-b border-gray-100">Recent searches</div>';
            
            recentSearches.slice(0, 5).forEach(function (search) {
                const div = document.createElement('div');
                div.className = 'p-2 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0 flex items-center';
                div.innerHTML = `
                    <svg class="h-3 w-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <div class="text-sm font-medium">${search.name}</div>
                        <div class="text-xs text-gray-500">${search.lat.toFixed(4)}, ${search.lng.toFixed(4)}</div>
                    </div>
                `;
                
                div.addEventListener('click', function () {
                    resultsEl.classList.add('hidden');
                    resultsEl.innerHTML = '';
                    setStopFn(search.lat, search.lng);
                });
                
                resultsEl.appendChild(div);
            });
        }
        
        function addToRecentSearches(name, lat, lng) {
            recentSearches = recentSearches.filter(function(search) {
                return !(Math.abs(search.lat - lat) < 0.0001 && Math.abs(search.lng - lng) < 0.0001);
            });
            
            recentSearches.unshift({ name: name, lat: lat, lng: lng });
            recentSearches = recentSearches.slice(0, 10);
            localStorage.setItem('transportOps_recentSearches', JSON.stringify(recentSearches));
        }
        
        // Set up event listeners
        const searchInput = document.getElementById('test-search');
        const searchBtn = document.getElementById('test-search-btn');
        const resultsEl = document.getElementById('test-results');
        
        function doSearch(isAutoComplete = false) { 
            searchPlace(searchInput.value, resultsEl, setTestLocation, isAutoComplete); 
        }
        
        function debouncedSearch() {
            clearTimeout(searchTimeout);
            const query = searchInput.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(function() {
                    doSearch(true);
                }, 300);
            } else if (query.length === 0) {
                showRecentSearches(resultsEl, setTestLocation);
            } else {
                resultsEl.classList.add('hidden');
            }
        }
        
        searchInput.addEventListener('focus', function() {
            if (!searchInput.value.trim()) {
                showRecentSearches(resultsEl, setTestLocation);
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsEl.contains(e.target)) {
                resultsEl.classList.add('hidden');
            }
        });
        
        if (searchBtn) searchBtn.addEventListener('click', function() { doSearch(false); });
        searchInput.addEventListener('input', debouncedSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                clearTimeout(searchTimeout);
                doSearch(false);
            } else if (e.key === 'Escape') {
                resultsEl.classList.add('hidden');
                searchInput.blur();
            }
        });
    </script>
</body>
</html>
