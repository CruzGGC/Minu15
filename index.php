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
    <div id="map"></div>
    
    <div class="overlay-panel">
        <h2>15-Minute City Explorer</h2>
        
        <div class="panel-section">
            <div class="panel-header" id="poi-header">
                <span>Points of Interest</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="poi-content">
                <div class="poi-category">
                    <div class="panel-header">
                        <span>Health</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="poi-options">
                        <div><input type="checkbox" id="poi-hospitals" checked> <label for="poi-hospitals">Hospitals</label></div>
                        <div><input type="checkbox" id="poi-health" checked> <label for="poi-health">Health Centers</label></div>
                    </div>
                </div>
                <div class="poi-category">
                    <div class="panel-header">
                        <span>Education</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="poi-options">
                        <div><input type="checkbox" id="poi-schools" checked> <label for="poi-schools">Schools</label></div>
                    </div>
                </div>
                <div class="poi-category">
                    <div class="panel-header">
                        <span>Shopping</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="poi-options">
                        <div><input type="checkbox" id="poi-shops" checked> <label for="poi-shops">Shopping</label></div>
                    </div>
                </div>
                <div class="poi-category">
                    <div class="panel-header">
                        <span>Culture & Leisure</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="poi-options">
                        <div><input type="checkbox" id="poi-culture" checked> <label for="poi-culture">Cultural Facilities</label></div>
                        <div><input type="checkbox" id="poi-parks" checked> <label for="poi-parks">Parks</label></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Transport Mode</span>
            </div>
            <div class="transport-mode">
                <div class="transport-option" data-mode="walking">
                    <div class="transport-icon"><i class="fas fa-walking"></i></div>
                    <span>Walking</span>
                </div>
                <div class="transport-option active" data-mode="cycling">
                    <div class="transport-icon"><i class="fas fa-bicycle"></i></div>
                    <span>Cycling</span>
                </div>
                <div class="transport-option" data-mode="driving">
                    <div class="transport-icon"><i class="fas fa-car"></i></div>
                    <span>Driving</span>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Distance (minutes)</span>
            </div>
            <input type="range" class="distance-slider" id="max-distance" min="5" max="30" step="5" value="15">
            <div id="distance-value">15 minutes</div>
        </div>
        
        <div class="panel-section">
            <input type="text" class="search-box" placeholder="Search for a location...">
            <button class="search-button"><i class="fas fa-search"></i></button>
        </div>
        
        <button class="calculate-button">Calculate</button>
    </div>

    <div class="statistics-panel">
        <div class="statistics-title">
            <span>Area Statistics</span>
            <span class="close-stats"><i class="fas fa-times"></i></span>
        </div>
        <div class="stats-content" id="area-stats">
            <p>Click on the map to see statistics</p>
        </div>
    </div>

    <div class="poi-details-panel">
        <div class="poi-details-title">
            <span>POI Details</span>
            <span class="close-poi-details"><i class="fas fa-times"></i></span>
        </div>
        <div class="poi-details-content" id="poi-info">
            <p>Click on a point of interest for details</p>
        </div>
    </div>

    <div class="poi-legend">
        <div class="poi-legend-title"><strong>Legend</strong></div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-hospital"></div>
            <span>Hospitals</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-health"></div>
            <span>Health Centers</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-school"></div>
            <span>Schools</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-culture"></div>
            <span>Cultural Facilities</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-shop"></div>
            <span>Shopping</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-park"></div>
            <span>Parks</span>
        </div>
    </div>

    <!-- Modal overlay for popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- Footer attribution -->
    <div class="footer-attribution">
        <p>&copy; <?php echo date('Y'); ?> 15-Minute City Explorer | Data from <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
    </div>
    
    <!-- Custom JS -->
    <script src="js/map.js"></script>
    <script src="js/controls.js"></script>
</body>
</html>