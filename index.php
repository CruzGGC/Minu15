<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>15-Minute City Explorer</title>
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Turf.js for geospatial analysis -->
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>15-Minute City Explorer</h1>
            <p>Discover what's accessible within 15 minutes from any location in Portugal</p>
        </header>
        
        <div class="main-content">
            <div class="sidebar">
                <div class="control-panel">
                    <h3>Controls</h3>
                    
                    <!-- Transportation Mode Selector -->
                    <div class="control-group">
                        <label for="transport-mode">Transportation Mode:</label>
                        <select id="transport-mode">
                            <option value="walking">Walking</option>
                            <option value="cycling">Cycling</option>
                            <option value="driving">Driving</option>
                        </select>
                    </div>
                    
                    <!-- POI Filter -->
                    <div class="control-group">
                        <label>Points of Interest:</label>
                        <div class="poi-filters">
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-hospitals" checked>
                                <label for="poi-hospitals">Hospitals</label>
                            </div>
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-schools" checked>
                                <label for="poi-schools">Schools</label>
                            </div>
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-health" checked>
                                <label for="poi-health">Health Centers</label>
                            </div>
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-culture" checked>
                                <label for="poi-culture">Cultural Facilities</label>
                            </div>
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-shops" checked>
                                <label for="poi-shops">Shopping</label>
                            </div>
                            <div class="poi-filter">
                                <input type="checkbox" id="poi-parks" checked>
                                <label for="poi-parks">Parks</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Distance Slider -->
                    <div class="control-group">
                        <label for="max-distance">Maximum Distance (minutes):</label>
                        <input type="range" id="max-distance" min="5" max="30" value="15" step="5">
                        <span id="distance-value">15</span> minutes
                    </div>
                </div>
                
                <!-- Statistics Panel -->
                <div class="stats-panel">
                    <h3>Area Statistics</h3>
                    <div id="area-stats">
                        <p>Click on the map to see statistics</p>
                    </div>
                </div>
                
                <!-- POI Details Panel -->
                <div class="poi-details">
                    <h3>POI Details</h3>
                    <div id="poi-info">
                        <p>Click on a point of interest for details</p>
                    </div>
                </div>
            </div>
            
            <div id="map"></div>
        </div>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> 15-Minute City Explorer | Data from Geofabrik</p>
        </footer>
    </div>
    
    <!-- Custom JS -->
    <script src="js/map.js"></script>
    <script src="js/controls.js"></script>
</body>
</html>