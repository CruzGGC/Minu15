document.addEventListener('DOMContentLoaded', function() {
    // Backend API URL
    const API_URL = 'http://localhost:3000/api';
    
    // Initialize map
    const map = L.map('map').setView([40.640, -8.654], 13); // Aveiro, Portugal coordinates
    
    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Current marker and isochrone
    let currentMarker = null;
    let currentIsochrone = null;
    let currentParish = null;
    
    // Selected transport mode and POIs
    let transportMode = 'cycling';
    let selectedPOIs = {
        dentist: true,
        restaurant: true,
        cafe: true
    };
    
    // POI markers layer group
    let poiMarkers = L.layerGroup().addTo(map);
    
    // POI category icons and colors
    const poiIcons = {
        healthcare: { icon: '🏥', color: '#FF5252' },
        food: { icon: '🍽️', color: '#FF9800' },
        shopping: { icon: '🛒', color: '#2196F3' },
        education: { icon: '🏫', color: '#4CAF50' },
        leisure: { icon: '🎭', color: '#9C27B0' },
        default: { icon: '📍', color: '#757575' }
    };
    
    // Custom icon factory
    function createCustomIcon(category, subcategory) {
        const iconInfo = poiIcons[category] || poiIcons.default;
        
        return L.divIcon({
            className: 'custom-poi-icon',
            html: `<div style="background-color:${iconInfo.color}; width:30px; height:30px; border-radius:50%; display:flex; justify-content:center; align-items:center; color:white; font-size:16px; border:2px solid white;">
                      ${iconInfo.icon}
                   </div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -15]
        });
    }
    
    // Panel toggle functionality
    document.querySelectorAll('.panel-header').forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            if (content && content.classList.contains('panel-content')) {
                content.classList.toggle('expanded');
                this.querySelector('.dropdown-arrow').classList.toggle('up');
            }
        });
    });
    
    // Transport mode selection
    document.querySelectorAll('.transport-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.transport-option').forEach(opt => {
                opt.classList.remove('active');
            });
            this.classList.add('active');
            transportMode = this.dataset.mode;
        });
    });
    
    // Distance slider
    const distanceSlider = document.getElementById('distance-slider');
    const distanceValue = document.getElementById('distance-value');
    
    distanceSlider.addEventListener('input', function() {
        distanceValue.textContent = this.value + ' minutos';
    });
    
    // POI checkboxes
    document.querySelectorAll('.poi-options input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            selectedPOIs[this.id] = this.checked;
        });
    });
    
    // Calculate button
    document.querySelector('.calculate-button').addEventListener('click', function() {
        calculateIsochrone();
    });
    
    // Map click event for setting location
    map.on('click', function(e) {
        setMarker(e.latlng);
    });
    
    // Set marker at a location
    function setMarker(latlng) {
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        
        currentMarker = L.marker(latlng).addTo(map);
        
        // Find parish for the selected location
        findParish(latlng.lat, latlng.lng);
    }
    
    // Find parish at the given coordinates
    async function findParish(lat, lng) {
        try {
            const response = await fetch(`${API_URL}/parish?lat=${lat}&lng=${lng}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch parish data');
            }
            
            const data = await response.json();
            currentParish = data;
            
            // Get parish statistics
            if (currentParish) {
                getParishStatistics(currentParish.osm_id);
            }
        } catch (error) {
            console.error('Error finding parish:', error);
        }
    }
    
    // Get parish statistics
    async function getParishStatistics(parishId) {
        try {
            const response = await fetch(`${API_URL}/statistics/${parishId}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch parish statistics');
            }
            
            const data = await response.json();
            
            // Show parish statistics in the UI
            // This could be implemented as a panel or popup
            console.log('Parish statistics:', data);
            
            // Example: You could create a stats panel here
            showParishStatistics(data);
        } catch (error) {
            console.error('Error getting parish statistics:', error);
        }
    }
    
    // Show parish statistics in the UI
    function showParishStatistics(stats) {
        // This is a placeholder for where you would display parish statistics
        // For example, you could create a new panel or modal with this information
        
        // Example of creating a popup with basic statistics
        if (currentMarker) {
            let statsContent = `
                <h3>${stats.parish.name}</h3>
                <p><strong>Área:</strong> ${stats.parish.area_sqkm.toFixed(2)} km²</p>
                <p><strong>Extensão de estradas:</strong> ${stats.infrastructure.road_length_km.toFixed(2)} km</p>
                <h4>Pontos de Interesse:</h4>
                <ul>
            `;
            
            stats.poi_counts.forEach(poiStat => {
                statsContent += `<li>${getPoiCategoryName(poiStat.category)}: ${poiStat.count}</li>`;
            });
            
            statsContent += `</ul>`;
            
            currentMarker.bindPopup(statsContent).openPopup();
        }
    }
    
    // Helper function to translate POI category names to Portuguese
    function getPoiCategoryName(category) {
        const categoryNames = {
            healthcare: 'Saúde',
            food: 'Alimentação',
            shopping: 'Comércio',
            education: 'Educação',
            leisure: 'Lazer',
            other: 'Outros'
        };
        
        return categoryNames[category] || category;
    }
    
    // Calculate and draw isochrone
    async function calculateIsochrone() {
        if (!currentMarker) {
            alert("Por favor, selecione um local no mapa primeiro.");
            return;
        }
        
        if (currentIsochrone) {
            map.removeLayer(currentIsochrone);
        }
        
        const latlng = currentMarker.getLatLng();
        const minutes = parseInt(distanceSlider.value);
        
        try {
            // Use the backend API for accurate isochrones
            const response = await fetch(`${API_URL}/isochrone`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lat: latlng.lat,
                    lng: latlng.lng,
                    minutes: minutes,
                    transportMode: transportMode
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to generate isochrone');
            }
            
            const data = await response.json();
            
            // Create the isochrone from GeoJSON
            const geojson = JSON.parse(data.geojson);
            currentIsochrone = L.geoJSON(geojson, {
                style: {
                    color: '#4285f4',
                    weight: 3,
                    opacity: 0.6,
                    fillColor: '#4285f4',
                    fillOpacity: 0.2
                }
            }).addTo(map);
            
            // Fit the map to show the full isochrone
            map.fitBounds(currentIsochrone.getBounds());
            
            // Now fetch POIs within the isochrone
            fetchPOIs(latlng.lat, latlng.lng, minutes);
        } catch (error) {
            console.error('Error calculating isochrone:', error);
            
            // Fallback to simple circle if API call fails
            let radiusInMeters;
            switch(transportMode) {
                case 'walking':
                    radiusInMeters = minutes * 80; // ~4.8 km/h
                    break;
                case 'cycling':
                    radiusInMeters = minutes * 250; // ~15 km/h
                    break;
                case 'driving':
                    radiusInMeters = minutes * 400; // ~24 km/h in city
                    break;
                default:
                    radiusInMeters = minutes * 250;
            }
            
            currentIsochrone = L.circle(latlng, {
                radius: radiusInMeters,
                color: '#4285f4',
                className: 'isochrone-circle'
            }).addTo(map);
            
            map.fitBounds(currentIsochrone.getBounds());
            
            // Fetch POIs even with the fallback circle
            fetchPOIs(latlng.lat, latlng.lng, minutes);
        }
    }
    
    // Fetch and display POIs from database
    async function fetchPOIs(lat, lng, minutes) {
        // Clear existing POI markers
        poiMarkers.clearLayers();
        
        try {
            // Get selected POI types
            const poiTypes = Object.keys(selectedPOIs).filter(key => selectedPOIs[key]);
            
            if (poiTypes.length === 0) {
                return;
            }
            
            const response = await fetch(`${API_URL}/pois`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lat: lat,
                    lng: lng,
                    minutes: minutes,
                    transportMode: transportMode,
                    poiTypes: poiTypes
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch POIs');
            }
            
            const data = await response.json();
            
            // Add markers for POIs
            data.forEach(poi => {
                // Determine the POI category
                let category = 'default';
                if (poi.amenity) {
                    if (['hospital', 'pharmacy', 'dentist', 'clinic', 'doctors'].includes(poi.amenity)) {
                        category = 'healthcare';
                    } else if (['restaurant', 'cafe', 'bar', 'fast_food'].includes(poi.amenity)) {
                        category = 'food';
                    } else if (['school', 'university', 'kindergarten', 'library'].includes(poi.amenity)) {
                        category = 'education';
                    } else if (['theatre', 'cinema'].includes(poi.amenity)) {
                        category = 'leisure';
                    }
                } else if (poi.shop && ['supermarket', 'convenience', 'bakery', 'butcher', 'greengrocer'].includes(poi.shop)) {
                    category = 'shopping';
                } else if (poi.leisure && ['park', 'playground', 'sports_centre', 'swimming_pool'].includes(poi.leisure)) {
                    category = 'leisure';
                }
                
                // Create custom icon based on category
                const icon = createCustomIcon(category, poi.amenity || poi.shop || poi.leisure);
                
                // Create the marker
                const marker = L.marker([poi.lat, poi.lng], { icon: icon })
                    .bindPopup(`<div class="poi-popup">
                        <h3>${poi.name || poi.amenity || poi.shop || 'Ponto de interesse'}</h3>
                        <p>Tipo: ${poi.amenity || poi.shop || poi.leisure || 'Não especificado'}</p>
                        <button class="poi-details-btn" data-id="${poi.osm_id}">Ver detalhes</button>
                    </div>`)
                    .on('click', function() {
                        const popup = this.getPopup();
                        const detailsBtn = popup._contentNode.querySelector('.poi-details-btn');
                        if (detailsBtn) {
                            detailsBtn.addEventListener('click', function() {
                                getPOIDetails(this.dataset.id);
                            });
                        }
                    });
                
                poiMarkers.addLayer(marker);
            });
        } catch (error) {
            console.error('Error fetching POIs:', error);
            
            // Fallback to simulated data
            // This is reusing the existing simulated data from the original code
            const simulatedPOIs = {
                dentist: [
                    { lat: 40.638, lng: -8.654, name: "Dentista Centro" },
                    { lat: 40.645, lng: -8.650, name: "Clínica Dental Aveiro" }
                ],
                restaurant: [
                    { lat: 40.641, lng: -8.656, name: "Restaurante Beira Mar" },
                    { lat: 40.637, lng: -8.649, name: "Tasca do Peixe" },
                    { lat: 40.644, lng: -8.647, name: "Cozinha Tradicional" }
                ],
                cafe: [
                    { lat: 40.639, lng: -8.652, name: "Café Central" },
                    { lat: 40.643, lng: -8.655, name: "Pastelaria Avenida" },
                    { lat: 40.636, lng: -8.653, name: "Coffee Corner" }
                ],
                hospital: [
                    { lat: 40.635, lng: -8.657, name: "Hospital Distrital" }
                ],
                pharmacy: [
                    { lat: 40.640, lng: -8.651, name: "Farmácia Central" },
                    { lat: 40.647, lng: -8.654, name: "Farmácia Nova" }
                ],
                supermarket: [
                    { lat: 40.642, lng: -8.649, name: "Supermercado Aveiro" },
                    { lat: 40.638, lng: -8.658, name: "Mini Mercado" }
                ],
                bar: [
                    { lat: 40.641, lng: -8.653, name: "Bar Universitário" },
                    { lat: 40.644, lng: -8.656, name: "Irish Pub" }
                ]
            };
            
            // Add markers for selected POI types from simulated data
            for (const [poiType, isSelected] of Object.entries(selectedPOIs)) {
                if (isSelected && simulatedPOIs[poiType]) {
                    simulatedPOIs[poiType].forEach(poi => {
                        // Determine category for the icon
                        let category;
                        switch(poiType) {
                            case 'hospital':
                            case 'pharmacy':
                            case 'dentist':
                                category = 'healthcare';
                                break;
                            case 'restaurant':
                            case 'cafe':
                            case 'bar':
                                category = 'food';
                                break;
                            case 'supermarket':
                                category = 'shopping';
                                break;
                            default:
                                category = 'default';
                        }
                        
                        // Create custom icon based on category
                        const icon = createCustomIcon(category, poiType);
                        
                        // Create the marker
                        const marker = L.marker([poi.lat, poi.lng], { icon: icon })
                            .bindPopup(`<div class="poi-popup">
                                <h3>${poi.name}</h3>
                                <p>Tipo: ${poiType}</p>
                            </div>`);
                        
                        poiMarkers.addLayer(marker);
                    });
                }
            }
        }
    }
    
    // Fetch POI details
    async function getPOIDetails(poiId) {
        try {
            const response = await fetch(`${API_URL}/poi/${poiId}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch POI details');
            }
            
            const poi = await response.json();
            showPOIDetailsModal(poi);
        } catch (error) {
            console.error('Error fetching POI details:', error);
            alert('Erro ao obter detalhes do ponto de interesse.');
        }
    }
    
    // Show POI details in a modal
    function showPOIDetailsModal(poi) {
        // Create modal container if it doesn't exist
        let modal = document.getElementById('poi-details-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'poi-details-modal';
            modal.className = 'modal';
            document.body.appendChild(modal);
            
            // Style the modal
            modal.style.position = 'fixed';
            modal.style.top = '50%';
            modal.style.left = '50%';
            modal.style.transform = 'translate(-50%, -50%)';
            modal.style.backgroundColor = 'white';
            modal.style.padding = '20px';
            modal.style.borderRadius = '5px';
            modal.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
            modal.style.zIndex = '1000';
            modal.style.maxWidth = '500px';
            modal.style.width = '80%';
        }
        
        // Determine the category
        let category = 'default';
        if (poi.amenity) {
            if (['hospital', 'pharmacy', 'dentist', 'clinic', 'doctors'].includes(poi.amenity)) {
                category = 'healthcare';
            } else if (['restaurant', 'cafe', 'bar', 'fast_food'].includes(poi.amenity)) {
                category = 'food';
            } else if (['school', 'university', 'kindergarten', 'library'].includes(poi.amenity)) {
                category = 'education';
            } else if (['theatre', 'cinema'].includes(poi.amenity)) {
                category = 'leisure';
            }
        } else if (poi.shop && ['supermarket', 'convenience', 'bakery', 'butcher', 'greengrocer'].includes(poi.shop)) {
            category = 'shopping';
        } else if (poi.leisure && ['park', 'playground', 'sports_centre', 'swimming_pool'].includes(poi.leisure)) {
            category = 'leisure';
        }
        
        // Build the address string
        const address = [
            poi['addr:housenumber'],
            poi['addr:street'],
            poi['addr:postcode'],
            poi['addr:city']
        ].filter(Boolean).join(', ');
        
        // Fill the modal with POI details
        modal.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0; color: ${poiIcons[category].color}">${poi.name || poi.amenity || poi.shop || 'Ponto de interesse'}</h2>
                <span id="close-modal" style="cursor:pointer; font-size:24px;">&times;</span>
            </div>
            <div>
                <p><strong>Tipo:</strong> ${poi.amenity || poi.shop || poi.leisure || 'Não especificado'}</p>
                ${address ? `<p><strong>Endereço:</strong> ${address}</p>` : ''}
                ${poi.opening_hours ? `<p><strong>Horário:</strong> ${poi.opening_hours}</p>` : ''}
                ${poi.phone ? `<p><strong>Telefone:</strong> <a href="tel:${poi.phone}">${poi.phone}</a></p>` : ''}
                ${poi.website ? `<p><strong>Website:</strong> <a href="${poi.website}" target="_blank">${poi.website}</a></p>` : ''}
            </div>
            <hr>
            <div style="margin-top: 15px; text-align: right;">
                <button id="directions-btn" style="padding: 8px 15px; background-color: #4285f4; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Como chegar
                </button>
                <button id="close-btn" style="margin-left: 10px; padding: 8px 15px; background-color: #f0f0f0; border: none; border-radius: 4px; cursor: pointer;">
                    Fechar
                </button>
            </div>
        `;
        
        // Show the modal
        modal.style.display = 'block';
        
        // Add event listener to close button
        document.getElementById('close-modal').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        document.getElementById('close-btn').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Add event listener to directions button
        document.getElementById('directions-btn').addEventListener('click', function() {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${poi.lat},${poi.lng}`;
            window.open(url, '_blank');
        });
    }
    
    // Search functionality for the search box
    const searchBox = document.querySelector('.search-box');
    const searchIcon = document.querySelector('.search-icon');
    
    searchIcon.addEventListener('click', performSearch);
    searchBox.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
    
    function performSearch() {
        const searchTerm = searchBox.value.trim();
        
        if (searchTerm === '') {
            return;
        }
        
        // For a production app, you would use a geocoding service like Nominatim
        // Here's a simple example using Nominatim
        fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(searchTerm)},Aveiro&format=json&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const location = data[0];
                    const latlng = L.latLng(location.lat, location.lon);
                    map.setView(latlng, 15);
                    setMarker(latlng);
                } else {
                    alert('Local não encontrado. Tente um termo de pesquisa diferente.');
                }
            })
            .catch(error => {
                console.error('Error searching for location:', error);
                alert('Erro ao pesquisar localização.');
            });
    }
    
    // Initialize with expanded POI panel
    document.getElementById('poi-content').classList.add('expanded');
    document.querySelector('#poi-header .dropdown-arrow').classList.add('up');
    
    // Set initial marker in Aveiro
    setMarker(L.latLng(40.640, -8.654));
});