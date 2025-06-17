/**
 * Location.js
 * Gere a funcionalidade para a página location.php
 * Permite aos utilizadores selecionar localizações a partir de dropdowns ou clicando no mapa
 * Obtém e exibe dados da GeoAPI.pt
 */

// Inicializar variáveis
let map;
let locationMarker;
let locationPolygon;
let freguesiaPolygons = []; // Novo array para armazenar limites de freguesias individuais
let currentLocation = null;
let currentClickedCoordinates;
let genderChart = null;
let censusSidebarActive = false;
let currentCensusYear = 2021; // Padrão para 2021
let showFreguesias = false; // Rastreia se deve mostrar as freguesias

// Fornecedores de estilo de mapa
const mapProviders = {
    osm: {
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contribuidores'
    },
    positron: {
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contribuidores &copy; <a href="https://carto.com/attributions">CARTO</a>'
    },
    dark_matter: {
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contribuidores &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }
};

// Configuração dos Ícones de POI
const poiIcons = {
    hospitals: { icon: 'hospital', color: '#e74c3c' },
    health_centers: { icon: 'clinic-medical', color: '#e67e22' },
    pharmacies: { icon: 'prescription-bottle-alt', color: '#27ae60' },
    dentists: { icon: 'tooth', color: '#3498db' },
    
    schools: { icon: 'school', color: '#3498db' },
    universities: { icon: 'graduation-cap', color: '#9b59b6' },
    kindergartens: { icon: 'baby', color: '#f1c40f' },
    libraries: { icon: 'book', color: '#34495e' },
    
    supermarkets: { icon: 'shopping-basket', color: '#27ae60' },
    malls: { icon: 'shopping-bag', color: '#e67e22' },
    restaurants: { icon: 'utensils', color: '#e74c3c' },
    atms: { icon: 'money-bill-wave', color: '#2ecc71' },
    
    parks: { icon: 'tree', color: '#27ae60' },
    sports: { icon: 'dumbbell', color: '#3498db' },
    bus_stops: { icon: 'bus', color: '#f39c12' },
    police_stations: { icon: 'shield-alt', color: '#34495e' }
};

// Inicializa o mapa quando o DOM está totalmente carregado
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    loadDistritos();
    setupEventListeners();
    showLocationTutorial(); // Mostrar tutorial na primeira visita
});

/**
 * Inicializa o mapa Leaflet
 */
function initializeMap() {
    // Criar o mapa centrado em Portugal
    map = L.map('map', {
        center: [39.6, -8.0],
        zoom: 7,
        zoomControl: false,
        attributionControl: false
    });
    
    // Adicionar controlo de atribuição ao canto inferior direito
    L.control.attribution({
        position: 'bottomright'
    }).addTo(map);
    
    // Definir o estilo de mapa padrão (Positron)
    setMapStyle('positron');
    
    // Adicionar evento de clique ao mapa
    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        console.log(`Mapa clicado nas coordenadas: ${lat}, ${lng}`);
        
        // Validar coordenadas (garantir que estão dentro da área aproximada de Portugal)
        const inPortugal = lat >= 36.8 && lat <= 42.2 && lng >= -9.6 && lng <= -6.1;
        
        if (!inPortugal) {
            console.warn('Coordenadas fora da caixa delimitadora de Portugal');
            // Continuaremos, mas avisaremos o utilizador
            alert('As coordenadas selecionadas parecem estar fora de Portugal. Os dados podem não estar disponíveis.');
        }
        
        // Limpar seleção anterior
        clearLocationSelection();
        
        // Armazenar as coordenadas clicadas
        currentClickedCoordinates = {
            lat: parseFloat(lat.toFixed(6)),
            lng: parseFloat(lng.toFixed(6))
        };
        
        // Adicionar marcador na localização clicada
        locationMarker = L.marker([lat, lng]).addTo(map);
        
        // Focar o botão Carregar Dados para indicar o próximo passo
        document.querySelector('.calculate-button').focus();
        document.querySelector('.calculate-button').classList.add('highlight');
        
        // Remover destaque após 2 segundos
        setTimeout(() => {
            document.querySelector('.calculate-button').classList.remove('highlight');
        }, 2000);
    });
}

/**
 * Define o estilo do mapa com base no fornecedor selecionado
 */
function setMapStyle(provider) {
    // Remover camada de 'tiles' existente, se houver
    if (window.tileLayer && map.hasLayer(window.tileLayer)) {
        map.removeLayer(window.tileLayer);
    }
    
    // Criar nova camada de 'tiles' com o fornecedor selecionado
    window.tileLayer = L.tileLayer(mapProviders[provider].url, {
        attribution: mapProviders[provider].attribution,
        maxZoom: 19
    }).addTo(map);
    
    // Atualizar estilo ativo na UI
    document.querySelectorAll('.map-style-option').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelector(`.map-style-option[data-provider="${provider}"]`).classList.add('active');
}

/**
 * Carrega a lista de distritos da API
 */
function loadDistritos() {
    console.log('A carregar distritos...');
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=fetchAllDistritos'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro HTTP! Estado: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Dados dos distritos recebidos:', data);
            const distritoSelect = document.getElementById('distrito-select');
            
            // Garantir que os dados estão corretamente estruturados
            const distritos = data.data || [];
            
            // Verificar se os distritos são um array de objetos com propriedade distrito
            let distritoNames = [];
            
            if (distritos.length > 0 && typeof distritos[0] === 'object' && distritos[0].distrito) {
                // Extrair nomes de distritos dos objetos
                distritoNames = distritos.map(d => d.distrito);
            } else if (Array.isArray(distritos)) {
                // Se for apenas um array de strings, usar como está
                distritoNames = distritos;
            }
            
            // Ordenar nomes de distritos alfabeticamente
            distritoNames.sort((a, b) => a.localeCompare(b));
            
            // Adicionar opções à seleção
            distritoNames.forEach(distritoName => {
                const option = document.createElement('option');
                option.value = distritoName;
                option.textContent = distritoName;
                distritoSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar distritos:', error);
            // Exibir erro na UI
            const distritoSelect = document.getElementById('distrito-select');
            distritoSelect.innerHTML = '<option value="">Erro ao carregar distritos</option>';
        });
}

/**
 * Carrega concelhos para o distrito selecionado
 */
function loadConcelhos(distrito) {
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchMunicipiosByDistrito&distrito=${encodeURIComponent(distrito)}`
    })
        .then(response => response.json())
        .then(data => {
            const concelhoSelect = document.getElementById('concelho-select');
            
            // Limpar opções anteriores
            concelhoSelect.innerHTML = '<option value="">Selecione um concelho...</option>';
            
            // Obter o array de municípios da resposta
            const municipios = data.data?.municipios || [];
            
            // Ordenar municípios alfabeticamente (por nome)
            municipios.sort((a, b) => a.nome.localeCompare(b.nome));
            
            // Adicionar opções à seleção
            municipios.forEach(municipio => {
                const option = document.createElement('option');
                option.value = municipio.nome;
                option.textContent = municipio.nome;
                concelhoSelect.appendChild(option);
            });
            
            // Ativar a seleção
            concelhoSelect.disabled = false;
            
            // Desativar a seleção de freguesia até que o concelho seja selecionado
            document.getElementById('freguesia-select').disabled = true;
            document.getElementById('freguesia-select').innerHTML = '<option value="">Selecione uma freguesia...</option>';
        })
        .catch(error => {
            console.error('Erro ao carregar concelhos:', error);
            alert('Ocorreu um erro ao carregar os concelhos.');
        });
}

/**
 * Carrega freguesias para o concelho selecionado
 */
function loadFreguesias(concelho) {
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchFreguesiasByMunicipio&municipio=${encodeURIComponent(concelho)}`
    })
        .then(response => response.json())
        .then(data => {
            console.log('Dados das freguesias recebidos:', data);
            
            const freguesiaSelect = document.getElementById('freguesia-select');
            
            // Limpar opções anteriores
            freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
            
            // Obter o array de nomes de freguesias da resposta, lidando com a nova estrutura
            let freguesias = data.data?.freguesias || [];
            const freguesiaGeojsons = data.data?.geojsons?.freguesias || [];

            console.log('Array de freguesias original:', freguesias);

            // Lidar com o caso em que as freguesias podem ser objetos em vez de strings
            if (freguesias.length > 0 && typeof freguesias[0] !== 'string') {
                // Verificar se as freguesias são objetos com a propriedade 'nome'
                if (freguesias[0] && typeof freguesias[0].nome === 'string') {
                    console.log('Freguesias são objetos com a propriedade nome');
                    freguesias = freguesias.map(f => f.nome);
                } else {
                    console.log('Freguesias têm formato inesperado, a tentar converter para strings');
                    freguesias = freguesias.map(f => String(f));
                }
            }
            
            console.log('Array de freguesias processado:', freguesias);
            
            // Criar um mapa de nomes de freguesias para os seus respetivos códigos dos geojsons
            const freguesiaCodes = {};
            freguesiaGeojsons.forEach(geojson => {
                if (geojson?.properties?.Freguesia && geojson?.properties?.Dicofre) {
                    freguesiaCodes[geojson.properties.Freguesia] = geojson.properties.Dicofre;
                }
            });
            
            // Ordenar nomes de freguesias alfabeticamente apenas se forem strings
            if (freguesias.length > 0 && typeof freguesias[0] === 'string') {
                try {
                    freguesias.sort((a, b) => a.localeCompare(b));
                } catch (error) {
                    console.error('Erro ao ordenar nomes de freguesia:', error);
                    console.log('Não foi possível ordenar nomes de freguesia, usando como estão');
                }
            }
            
            // Adicionar opções à seleção
            freguesias.forEach(freguesiaName => {
                const option = document.createElement('option');
                // Armazenar tanto o código quanto o nome - o nome é o que usaremos para chamadas à API
                option.value = freguesiaName; // Armazenar nome da freguesia como valor para chamadas à API
                option.dataset.code = freguesiaCodes[freguesiaName] || ''; // Armazenar código como atributo de dados
                option.textContent = freguesiaName;
                freguesiaSelect.appendChild(option);
            });
            
            // Ativar a seleção
            freguesiaSelect.disabled = false;
        })
        .catch(error => {
            console.error('Erro ao carregar freguesias:', error);
            alert('Ocorreu um erro ao carregar as freguesias.');
        });
}

/**
 * Obtém dados de localização por coordenadas
 */
function fetchLocationByCoordinates(lat, lng) {
    console.log(`A obter dados de localização para coordenadas: ${lat}, ${lng}`);
    
    // Obter dados da API
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByGps&latitude=${lat}&longitude=${lng}`
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro HTTP! Estado: ${response.status}`);
            }
            console.log("Resposta recebida:", response);
            return response.text().then(text => {
                console.log("Texto da resposta bruta:", text);
                if (!text || text.trim() === '') {
                    throw new Error('Resposta vazia recebida');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Erro de análise JSON:", e);
                    throw new Error(`Erro de análise JSON: ${e.message}`);
                }
            });
        })
        .then(data => {
            // Redefinir estado da UI
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success && data.data) {
                // Armazenar os dados de localização atuais
                currentLocation = data.data;
                
                // Atualizar dropdowns para corresponder à localização selecionada
                if (currentLocation.distrito) {
                    const distritoSelect = document.getElementById('distrito-select');
                    distritoSelect.value = currentLocation.distrito;
                    
                    // Carregar concelhos para este distrito
                    loadConcelhos(currentLocation.distrito);
                    
                    // Esperar que os concelhos carreguem, depois definir o concelho
                    setTimeout(() => {
                        if (currentLocation.concelho) {
                            const concelhoSelect = document.getElementById('concelho-select');
                            concelhoSelect.value = currentLocation.concelho;
                            
                            // Carregar freguesias para este concelho
                            loadFreguesias(currentLocation.concelho);
                            
                            // Esperar que as freguesias carreguem, depois definir a freguesia
                            setTimeout(() => {
                                if (currentLocation.freguesia) {
                                    const freguesiaSelect = document.getElementById('freguesia-select');
                                    
                                    // Encontrar a opção com texto correspondente
                                    const options = Array.from(freguesiaSelect.options);
                                    const option = options.find(opt => opt.textContent === currentLocation.freguesia);
                                    
                                    if (option) {
                                        freguesiaSelect.value = option.value;
                                    }
                                }
                            }, 1000);
                        }
                    }, 1000);
                }
                
                // Desenhar o limite da localização
                if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
                
                // Exibir dados de localização
                displayLocationData(currentLocation);
                
                // Se tivermos coordenadas, centrar o mapa
                if (currentLocation.centroid) {
                    map.setView([currentLocation.centroid.lat, currentLocation.centroid.lng], 12);
                }
            } else {
                // Mostrar mensagem de erro
                document.getElementById('location-data').innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Não foi possível obter dados para esta localização.</p>
                        <p class="error-details">${data.message || 'Erro desconhecido'}</p>
                    </div>
                `;
                
                // Show the data panel
                document.querySelector('.location-data-panel').classList.add('visible');
            }
        })
        .catch(error => {
            console.error('Error fetching location data:', error);
            
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            // Show error message
            document.getElementById('location-data').innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao obter dados da localização.</p>
                    <p class="error-details">${error.message}</p>
                </div>
            `;
            
            // Show the data panel
            document.querySelector('.location-data-panel').classList.add('visible');
        });
}

/**
 * Obtém dados de localização por freguesia
 */
function fetchLocationByFreguesia(freguesia, municipio) {
    console.log(`A obter dados da freguesia: ${freguesia}, ${municipio}`);
    
    // Atualizar UI para mostrar estado de carregamento
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByFreguesiaAndMunicipio&freguesia=${encodeURIComponent(freguesia)}&municipio=${encodeURIComponent(municipio)}`
    })
        .then(response => response.json())
        .then(data => {
            // Redefinir estado da UI
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Mostrar o painel de dados de localização
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Registar a estrutura de dados para depuração
                console.log('Estrutura de dados da freguesia:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasFreguesias: currentLocation.geojsons && !!currentLocation.geojsons.freguesias,
                    freguesiasLength: currentLocation.geojsons && currentLocation.geojsons.freguesias ? currentLocation.geojsons.freguesias.length : 0,
                    hasFreguesia: currentLocation.geojsons && !!currentLocation.geojsons.freguesia
                });
                
                // Desenhar o limite da localização se a geometria estiver disponível
                if (currentLocation.geojsons) {
                    // Passar o objeto de localização completo para preservar a estrutura de geojsons
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para esta freguesia.');
            }
        })
        .catch(error => {
            console.error('Erro ao obter dados da freguesia:', error);
            alert('Ocorreu um erro ao obter os dados da freguesia.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Obtém dados de localização por município
 */
function fetchLocationByMunicipio(municipio) {
    console.log(`A obter dados do município: ${municipio}`);
    
    // Atualizar interface de utilizador para mostrar estado de carregamento
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByMunicipio&municipio=${encodeURIComponent(municipio)}`
    })
        .then(response => response.json())
        .then(data => {
            // Redefinir o estado da interface de utilizador
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Mostrar o painel de dados de localização
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Registar a estrutura de dados para depuração
                console.log('Estrutura de dados do município:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasFreguesias: currentLocation.geojsons && !!currentLocation.geojsons.freguesias,
                    freguesiasLength: currentLocation.geojsons && currentLocation.geojsons.freguesias ? currentLocation.geojsons.freguesias.length : 0,
                    hasMunicipio: currentLocation.geojsons && !!currentLocation.geojsons.municipio
                });
                
                // Desenhar o limite da localização se a geometria estiver disponível
                if (currentLocation.geojsons) {
                    // Passar o objeto de localização completo para preservar a estrutura de geojsons
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este município.');
            }
        })
        .catch(error => {
            console.error('Erro ao obter dados do município:', error);
            alert('Ocorreu um erro ao obter os dados do município.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Obtém dados de localização por distrito
 */
function fetchLocationByDistrito(distrito) {
    console.log(`A obter dados do distrito: ${distrito}`);
    
    // Atualizar interface de utilizador para mostrar estado de carregamento
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByDistrito&distrito=${encodeURIComponent(distrito)}`
    })
        .then(response => response.json())
        .then(data => {
            // Redefinir o estado da interface de utilizador
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Mostrar o painel de dados de localização
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Registar a estrutura de dados para depuração
                console.log('Estrutura de dados do distrito:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasDistrito: currentLocation.geojsons && !!currentLocation.geojsons.distrito
                });
                
                // Desenhar o limite da localização se a geometria estiver disponível
                if (currentLocation.geojsons) {
                    // Passar o objeto de localização completo para preservar a estrutura de geojsons
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este distrito.');
            }
        })
        .catch(error => {
            console.error('Erro ao obter dados do distrito:', error);
            alert('Ocorreu um erro ao obter os dados do distrito.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Exibir dados de localização no painel direito
 */
function displayLocationData(location) {
    console.log('A exibir dados de localização:', location);
    currentLocation = location;
    
    // Garantir que o painel antigo está escondido
    document.querySelector('.location-data-panel').classList.remove('visible');
    
    // Mostrar a barra lateral do censo com dados de localização
    showCensusSidebar(location);
}

/**
 * Mostrar a barra lateral do censo com animações suaves
 */
function showCensusSidebar(location) {
    if (!location) return;
    
    console.log('A mostrar a barra lateral do censo com dados de localização:', location);
    
    // Definir nome e tipo da localização
    const locationName = document.getElementById('census-location-name');
    const locationType = document.getElementById('census-location-type');
    
    // Determinar nome e tipo da localização
    let name = '';
    let type = '';
    
    if (location.freguesia) {
        name = location.freguesia;
        type = 'Freguesia';
    if (location.municipio || location.concelho) {
            type += ' de ' + (location.municipio || location.concelho);
    }
    } else if (location.municipio || location.concelho) {
        name = location.municipio || location.concelho;
        type = 'Concelho';
    if (location.distrito) {
            type += ' de ' + location.distrito;
        }
    } else if (location.distrito) {
        name = location.distrito;
        type = 'Distrito';
    } else if (location.nome) {
        name = location.nome;
        type = location.tipo || 'Localidade';
    } else {
        name = 'Localização';
        type = 'Coordenadas GPS';
    }
    
    locationName.textContent = name;
    locationType.textContent = type;
    
    // Obter dados do censo - verificar locais diretos e aninhados
    let census2021 = location.censos2021 || null;
    let census2011 = location.censos2011 || null;
    
    // Se os dados do censo não estiverem diretamente no objeto de localização, verificar objetos aninhados
    if (!census2021) {
        if (location.detalhesFreguesia && location.detalhesFreguesia.censos2021) {
            census2021 = location.detalhesFreguesia.censos2021;
            console.log('Encontrados dados do censo 2021 em detalhesFreguesia');
        } else if (location.detalhesMunicipio && location.detalhesMunicipio.censos2021) {
            census2021 = location.detalhesMunicipio.censos2021;
            console.log('Encontrados dados do censo 2021 em detalhesMunicipio');
        }
    }
    
    if (!census2011) {
        if (location.detalhesFreguesia && location.detalhesFreguesia.censos2011) {
            census2011 = location.detalhesFreguesia.censos2011;
            console.log('Encontrados dados do censo 2011 em detalhesFreguesia');
        } else if (location.detalhesMunicipio && location.detalhesMunicipio.censos2011) {
            census2011 = location.detalhesMunicipio.censos2011;
            console.log('Encontrados dados do censo 2011 em detalhesMunicipio');
        }
    }
    
    // Se não houver dados do censo, mostrar mensagem
    if (!census2021 && !census2011) {
        console.warn('Nenhum dado do censo encontrado para esta localização');
        const ageContainer = document.getElementById('age-bars');
        if (ageContainer) {
            ageContainer.innerHTML = '<p class="no-data">Não existem dados censitários disponíveis para esta localização.</p>';
        }
        
        // Mostrar barra lateral com dados vazios
        document.getElementById('census-sidebar').classList.add('active');
        censusSidebarActive = true;
        return;
    }
    
    // Usar dados de 2021 se disponíveis, caso contrário, usar 2011
    const primaryCensus = census2021 || census2011;
    const secondaryCensus = census2021 && census2011 ? census2011 : null;
    
    console.log('A usar dados do censo:', { primary: primaryCensus, secondary: secondaryCensus });
    
    // Atualizar visibilidade do toggle
    const yearToggle = document.getElementById('census-year-toggle');
    if (census2021 && census2011) {
        // Ambos os anos do censo disponíveis, mostrar toggle
        yearToggle.parentElement.parentElement.style.display = 'flex';
        yearToggle.checked = census2021 ? true : false; // Padrão para 2021, se disponível
    } else {
        // Apenas um ano do censo disponível, esconder toggle
        yearToggle.parentElement.parentElement.style.display = 'none';
    }
    
    // Atualizar ano no estado atual
    currentCensusYear = census2021 ? 2021 : 2011;
    
    // Atualizar estatísticas principais
    updateCensusStats(primaryCensus, secondaryCensus);
    
    // Criar/atualizar mini gráficos
    createMiniCharts(primaryCensus, secondaryCensus);
    
    // Criar barras de distribuição etária
    createAgeBars(primaryCensus);
    
    // Definir link "Ver Dados Completos"
    const viewFullData = document.getElementById('census-view-full-data');
    if (viewFullData) {
        viewFullData.href = buildFullDataUrl(location);
    }
    
    // Mostrar barra lateral com animação
    document.getElementById('census-sidebar').classList.add('active');
    censusSidebarActive = true;
}

/**
 * Atualiza as estatísticas do censo com base nos dados do censo primário e secundário
 */
function updateCensusStats(primaryCensus, secondaryCensus) {
    // População
    const populationValue = document.getElementById('population-value');
    const populationChange = document.getElementById('population-change');
        
    // Obter valor da população
    const population = getCensusValue(primaryCensus, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
    
    if (population) {
        // Formatar com separador de milhares
        populationValue.textContent = new Intl.NumberFormat('pt-PT').format(population);
        
        // Calcular mudança se ambos os dados do censo estiverem disponíveis
        if (secondaryCensus) {
            const oldPopulation = getCensusValue(secondaryCensus, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            
            if (oldPopulation && oldPopulation > 0) {
                const changePercent = ((population - oldPopulation) / oldPopulation) * 100;
                const changeSign = changePercent > 0 ? '+' : '';
                const changeIcon = changePercent > 0 ? 'arrow-up' : (changePercent < 0 ? 'arrow-down' : 'minus');
                const changeClass = changePercent > 0 ? 'positive' : (changePercent < 0 ? 'negative' : '');
                
                populationChange.innerHTML = `<i class="fas fa-${changeIcon}"></i> ${changeSign}${Math.abs(changePercent).toFixed(1)}%`;
                populationChange.className = `stat-change ${changeClass}`;
                populationChange.style.display = 'flex';
            } else {
                populationChange.style.display = 'none';
            }
        } else {
            populationChange.style.display = 'none';
        }
    } else {
        populationValue.textContent = 'N/A';
        populationChange.style.display = 'none';
    }
    
    // Edifícios
    const buildingsValue = document.getElementById('buildings-value');
    const buildings = getCensusValue(primaryCensus, ['N_EDIFICIOS_CLASSICOS', 'N_EDIFICIOS']);
    
    if (buildings) {
        buildingsValue.textContent = new Intl.NumberFormat('pt-PT').format(buildings);
    } else {
        buildingsValue.textContent = 'N/A';
    }
        
    // Alojamentos
    const dwellingsValue = document.getElementById('dwellings-value');
    const dwellings = getCensusValue(primaryCensus, ['N_ALOJAMENTOS_TOTAL', 'N_ALOJAMENTOS']);
    
    if (dwellings) {
        dwellingsValue.textContent = new Intl.NumberFormat('pt-PT').format(dwellings);
    } else {
        dwellingsValue.textContent = 'N/A';
    }
    
    // Densidade populacional
    const densityValue = document.getElementById('density-value');
    let density = null;
    
    // Verificar área em múltiplos locais possíveis
    let areaHa = null;
    
    // Depurar campos de área na localização
    console.log('Dados de localização para cálculo de densidade:', {
        location: currentLocation
    });
    
    // Verificar área em múltiplos locais possíveis
    if (currentLocation) {
        // Propriedades diretas
        areaHa = currentLocation.area_ha || currentLocation.areaha || currentLocation.area;
        
        // Aninhado em detalhesFreguesia
        if (!areaHa && currentLocation.detalhesFreguesia) {
            areaHa = currentLocation.detalhesFreguesia.areaha || 
                    currentLocation.detalhesFreguesia.area_ha ||
                    currentLocation.detalhesFreguesia.area;
        }
        
        // Aninhado em detalhesMunicipio
        if (!areaHa && currentLocation.detalhesMunicipio) {
            areaHa = currentLocation.detalhesMunicipio.areaha || 
                    currentLocation.detalhesMunicipio.area_ha ||
                    currentLocation.detalhesMunicipio.area;
        }
        
        // Tentar analisar se for uma string
        if (typeof areaHa === 'string') {
            areaHa = parseFloat(areaHa);
        }
    }
    
    console.log(`Área encontrada: ${areaHa} ha`);
    
    if (population && areaHa) {
        const areaKm2 = areaHa / 100;
        density = Math.round(population / areaKm2);
        console.log(`Densidade calculada: ${density} de população ${population} e área ${areaHa} ha (${areaKm2} km²)`);
    } else if (population) {
        // Estimativa de densidade de fallback
        density = Math.round(population / 10);
        console.log(`Usando densidade de fallback: ${density} com base na população ${population}`);
    }
    
    if (density) {
        densityValue.textContent = `${new Intl.NumberFormat('pt-PT').format(density)} h/km²`;
    } else {
        densityValue.textContent = 'N/A';
    }
}

/**
 * Criar mini gráficos para distribuição de género e idade
 */
function createMiniCharts(primaryCensus, secondaryCensus) {
    // Sempre carregar Chart.js primeiro, depois criar gráficos
    loadChartJS(() => {
        createGenderChart(primaryCensus);
        calculateAverageAge(primaryCensus);
    });
}

/**
 * Carregar Chart.js dinamicamente
 */
function loadChartJS(callback) {
    // Verificar se Chart.js já está carregado
    if (typeof Chart !== 'undefined') {
        callback();
        return;
    }
    
    // Criar elemento script
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
    script.onload = callback;
    document.head.appendChild(script);
}

/**
 * Criar gráfico de pizza de distribuição de género
 */
function createGenderChart(census) {
    const males = getCensusValue(census, ['N_INDIVIDUOS_H']);
    const females = getCensusValue(census, ['N_INDIVIDUOS_M']);
    
    if (!males || !females) {
        document.getElementById('gender-chart').innerHTML = '<div class="no-data">Sem dados</div>';
        return;
    }
    
    // Obter o elemento contentor
    const container = document.getElementById('gender-chart');
    
    // Limpar conteúdo anterior
    container.innerHTML = '';
    
    // Criar um novo elemento canvas
    const canvas = document.createElement('canvas');
    canvas.width = 100;
    canvas.height = 100;
    container.appendChild(canvas);
    
    // Destruir gráfico existente, se houver
    if (genderChart) {
        genderChart.destroy();
    }
    
    // Criar novo gráfico
    genderChart = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: ['Homens', 'Mulheres'],
            datasets: [{
                data: [males, females],
                backgroundColor: ['#3498db', '#e74c3c'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1000,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = males + females;
                            const percent = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${new Intl.NumberFormat('pt-PT').format(context.raw)} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Calcular e exibir idade média
 */
function calculateAverageAge(census) {
    const ageElement = document.getElementById('average-age-value');
    
    // Obter dados do grupo etário
    const age0_14 = getCensusValue(census, ['N_INDIVIDUOS_0_14', 'N_INDIVIDUOS_RESIDENT_0A14']) || 0;
    const age15_24 = getCensusValue(census, ['N_INDIVIDUOS_15_24', 'N_INDIVIDUOS_RESIDENT_15A24']) || 0;
    const age25_64 = getCensusValue(census, ['N_INDIVIDUOS_25_64', 'N_INDIVIDUOS_RESIDENT_25A64']) || 0;
    const age65plus = getCensusValue(census, ['N_INDIVIDUOS_65_OU_MAIS', 'N_INDIVIDUOS_RESIDENT_65']) || 0;
    
    // Calcular idade média (usando pontos médios de faixas etárias)
    const totalPeople = age0_14 + age15_24 + age25_64 + age65plus;
    
    if (totalPeople > 0) {
        // Usar pontos médios aproximados para cada grupo etário
        const avgAge = (
            (age0_14 * 7) +         // ponto médio de 0-14 é 7
            (age15_24 * 19.5) +     // ponto médio de 15-24 é 19.5
            (age25_64 * 44.5) +     // ponto médio de 25-64 é 44.5
            (age65plus * 75)        // ponto médio aproximado para 65+ (estimativa conservadora)
        ) / totalPeople;
        
        // Exibir com uma casa decimal
        ageElement.textContent = avgAge.toFixed(1).replace('.', ',');
    } else {
        ageElement.textContent = 'N/A';
    }
}

/**
 * Criar barras de distribuição etária
 */
function createAgeBars(census) {
    const ageContainer = document.getElementById('age-bars');
    ageContainer.innerHTML = ''; // Limpar barras anteriores
    
    // Definir grupos etários
    const ageGroups = [
        { label: '0-14 anos', keys: ['N_INDIVIDUOS_0_14', 'N_INDIVIDUOS_RESIDENT_0A14'], color: '#3498db' },
        { label: '15-24 anos', keys: ['N_INDIVIDUOS_15_24', 'N_INDIVIDUOS_RESIDENT_15A24'], color: '#2ecc71' },
        { label: '25-64 anos', keys: ['N_INDIVIDUOS_25_64', 'N_INDIVIDUOS_RESIDENT_25A64'], color: '#f39c12' },
        { label: '65+ anos', keys: ['N_INDIVIDUOS_65_OU_MAIS', 'N_INDIVIDUOS_RESIDENT_65'], color: '#9b59b6' }
    ];
    
    // Obter população total
    const population = getCensusValue(census, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
    
    if (!population) {
        ageContainer.innerHTML = '<p class="no-data">Não existem dados de distribuição etária disponíveis.</p>';
        return;
    }
    
    // Criar barras para cada grupo etário
    ageGroups.forEach((group, index) => {
        const value = getCensusValue(census, group.keys);
        
        if (value) {
            const percent = (value / population) * 100;
            const barGroup = document.createElement('div');
            barGroup.className = 'age-bar-group';
            barGroup.style.setProperty('--item-index', index);
            
            barGroup.innerHTML = `
                <div class="age-bar-label">
                    <span class="age-bar-label-text">${group.label}</span>
                    <span class="age-bar-value">${new Intl.NumberFormat('pt-PT').format(value)} (${percent.toFixed(1)}%)</span>
            </div>
                <div class="age-bar-container">
                    <div class="age-bar-fill" style="--width: ${percent}%; background: linear-gradient(to right, ${group.color}, ${adjustColor(group.color, -20)});"></div>
        </div>
    `;
    
            ageContainer.appendChild(barGroup);
        }
    });
    
    if (ageContainer.children.length === 0) {
        ageContainer.innerHTML = '<p class="no-data">Não existem dados de distribuição etária disponíveis.</p>';
    }
}

/**
 * Ajustar a luminosidade da cor
 */
function adjustColor(color, amount) {
    return color; // Simplificado por enquanto
}

/**
 * Construir URL para a página de dados completos
 */
function buildFullDataUrl(location) {
    let url = 'location_data.php?';
    
    if (location.freguesia && (location.municipio || location.concelho)) {
        url += `type=freguesia&id=${encodeURIComponent(location.freguesia)}&municipio=${encodeURIComponent(location.municipio || location.concelho)}`;
    } else if (location.municipio || location.concelho) {
        url += `type=municipio&id=${encodeURIComponent(location.municipio || location.concelho)}`;
    } else if (location.distrito) {
        url += `type=distrito&id=${encodeURIComponent(location.distrito)}`;
    } else if (currentClickedCoordinates) {
        url += `type=gps&id=${currentClickedCoordinates.lat},${currentClickedCoordinates.lng}`;
    }
    
    return url;
}

/**
 * Função auxiliar para extrair valores do censo de forma segura
 */
function getCensusValue(censusData, possibleKeys) {
    if (!censusData) return null;
    
    for (const key of possibleKeys) {
        if (censusData[key] !== undefined) {
            return censusData[key];
        }
    }
    
    return null;
}

/**
 * Desenhar o limite da localização no mapa
 */
function drawLocationBoundary(geojson) {
    console.log('A desenhar limite com dados:', geojson);
    
    // Remover polígonos existentes
    clearBoundaries();
    
    // Verificar se temos dados GeoJSON válidos
    if (!geojson) {
        console.warn('Nenhum dado GeoJSON fornecido');
        return;
    }
    
    try {
        // Se geojson for uma string, tentar analisá-la
        if (typeof geojson === 'string') {
            try {
                geojson = JSON.parse(geojson);
            } catch (e) {
                console.error('Erro ao analisar string GeoJSON:', e);
                return;
            }
        }
        
        // Armazenar os dados originais para lidar com a alternância da freguesia
        const originalData = JSON.parse(JSON.stringify(geojson));
        
        // Registar a estrutura de dados para depuração
        console.log('Estrutura GeoJSON:', {
            hasGeojsons: !!geojson.geojsons,
            hasFreguesias: geojson.geojsons && !!geojson.geojsons.freguesias,
            freguesiasLength: geojson.geojsons && geojson.geojsons.freguesias ? geojson.geojsons.freguesias.length : 0,
            hasMunicipio: geojson.geojsons && !!geojson.geojsons.municipio,
            showFreguesias: showFreguesias
        });
        
        // Lidar com o caso em que temos um objeto geojsons com múltiplas geometrias
        if (geojson.geojsons) {
            // Verificar se temos freguesias e se o interruptor está ligado
            if (showFreguesias && geojson.geojsons.freguesias && geojson.geojsons.freguesias.length > 0) {
                console.log('A desenhar limites de freguesias individuais, contagem:', geojson.geojsons.freguesias.length);
                
                // Desenhar cada freguesia como uma camada separada
                geojson.geojsons.freguesias.forEach((freguesiaGeoJson, index) => {
                    console.log(`A desenhar freguesia ${index}:`, freguesiaGeoJson.properties ? freguesiaGeoJson.properties.Freguesia || freguesiaGeoJson.properties.freguesia : 'Desconhecido');
                    
                    const freguesiaLayer = L.geoJSON(freguesiaGeoJson, {
                        style: function () {
                            return {
                                color: '#2ecc71', // Verde para freguesias
                                weight: 2,
                                opacity: 0.7,
                                fillOpacity: 0.2,
                                className: 'freguesia-boundary'
                            };
                        },
                        onEachFeature: function (feature, layer) {
                            let name = 'Freguesia';
                            
                            // Tentar extrair o nome das propriedades
                            if (feature.properties) {
                                name = feature.properties.Freguesia || 
                                       feature.properties.freguesia || 
                                       feature.properties.FREGUESIA || 
                                       feature.properties.Nome || 
                                       feature.properties.nome || 
                                       feature.properties.NOME || 
                                       name;
                            }
                            
                            layer.bindPopup(name);
                        }
                    }).addTo(map);
                    
                    freguesiaPolygons.push(freguesiaLayer);
                });
                
                // Tentar ajustar o mapa aos limites de todas as freguesias
                if (freguesiaPolygons.length > 0) {
                    const bounds = freguesiaPolygons[0].getBounds();
                    for (let i = 1; i < freguesiaPolygons.length; i++) {
                        bounds.extend(freguesiaPolygons[i].getBounds());
                    }
                    map.fitBounds(bounds);
                }
                
                // Opcionalmente, desenhar também o limite do município, com estilo diferente
                if (geojson.geojsons.municipio) {
                    locationPolygon = L.geoJSON(geojson.geojsons.municipio, {
                        style: function () {
                            return {
                                color: '#3498db', // Azul para município
                                weight: 3,
                                opacity: 0.5,
                                fillOpacity: 0.05,
                                className: 'concelho-boundary'
                            };
                        },
                        onEachFeature: function (feature, layer) {
                            let name = 'Município';
                            
                            // Tentar extrair o nome das propriedades
                            if (feature.properties) {
                                name = feature.properties.Concelho || 
                                       feature.properties.concelho || 
                                       feature.properties.Municipio || 
                                       feature.properties.municipio || 
                                       feature.properties.Nome || 
                                       feature.properties.nome || 
                                       name;
                            }
                            
                            layer.bindPopup(name);
                        }
                    }).addTo(map);
                }
                
                console.log('Terminado o desenho das freguesias');
                return; // Ignorar o resto da função
            }
            
            // Se não estivermos a mostrar freguesias ou não houver dados de freguesia, mostrar o município
            if (geojson.geojsons.municipio) {
                console.log('A usar geometria do município');
                geojson = geojson.geojsons.municipio;
            }
            // Para freguesias, usar a geometria da freguesia
            else if (geojson.geojsons.freguesia) {
                console.log('A usar geometria da freguesia');
                geojson = geojson.geojsons.freguesia;
            }
            // Se tivermos um array de freguesias mas não estivermos a mostrá-las todas, usar a primeira
            else if (geojson.geojsons.freguesias && geojson.geojsons.freguesias.length > 0) {
                console.log('A usar a primeira freguesia do array de freguesias');
                geojson = geojson.geojsons.freguesias[0];
            }
        }
        
        // Normalizar objeto GeoJSON, se necessário
        if (!geojson.type && geojson.coordinates) {
            // Se estiver a faltar o tipo mas tiver coordenadas, assumir que é um Polígono ou MultiPolígono
            geojson = {
                type: Array.isArray(geojson.coordinates[0][0][0]) ? 'MultiPolygon' : 'Polygon',
                coordinates: geojson.coordinates
            };
        }
        
        // Criar uma Feature GeoJSON adequada, se necessário
        if (geojson.coordinates && !geojson.features) {
            if (!geojson.type || (geojson.type !== 'Feature' && !geojson.geometry)) {
                geojson = {
                    type: 'Feature',
                    properties: {},
                    geometry: {
                        type: Array.isArray(geojson.coordinates[0][0][0]) ? 'MultiPolygon' : 'Polygon',
                        coordinates: geojson.coordinates
                    }
                };
            }
        }
        
        // Para coleções de features, garantir que têm pelo menos uma feature
        if (geojson.type === 'FeatureCollection' && (!geojson.features || geojson.features.length === 0)) {
            console.warn('FeatureCollection GeoJSON vazia');
            return;
        }
        
        // Adicionar novo polígono
        locationPolygon = L.geoJSON(geojson, {
            style: function (feature) {
                // Determinar o estilo com base no tipo de limite
                let style = {
                    weight: 3,
                    opacity: 0.7,
                    fillOpacity: 0.2
                };
                
                // Tentar determinar o tipo de limite a partir das propriedades
                if (feature.properties) {
                    if (feature.properties.Freguesia || feature.properties.freguesia) {
                        // Estilo de Freguesia
                        style.color = '#2ecc71'; // Verde
                        style.className = 'freguesia-boundary';
                    } else if (feature.properties.Concelho || feature.properties.concelho || feature.properties.Municipio || feature.properties.municipio) {
                        // Estilo de Concelho/Município
                        style.color = '#3498db'; // Azul
                        style.className = 'concelho-boundary';
                    } else if (feature.properties.Distrito || feature.properties.distrito) {
                        // Estilo de Distrito
                        style.color = '#9b59b6'; // Roxo
                        style.className = 'distrito-boundary';
                    } else {
                        // Estilo padrão
                        style.color = '#3498db';
                    }
                } else {
                    // Estilo padrão
                    style.color = '#3498db';
                }
                
                return style;
            },
            onEachFeature: function (feature, layer) {
                let name = 'Localização';
                
                // Tentar extrair o nome das propriedades
                if (feature.properties) {
                    name = feature.properties.Nome || 
                           feature.properties.nome || 
                           feature.properties.NOME || 
                           feature.properties.name ||
                           feature.properties.NAME ||
                           feature.properties.Freguesia ||
                           feature.properties.freguesia ||
                           feature.properties.Concelho ||
                           feature.properties.concelho ||
                           feature.properties.Municipio ||
                           feature.properties.municipio ||
                           feature.properties.Distrito ||
                           feature.properties.distrito ||
                           name;
                }
                
                layer.bindPopup(name);
            }
        }).addTo(map);
        
        // Ajustar mapa aos limites do polígono, se o polígono tiver limites válidos
        if (locationPolygon && locationPolygon.getBounds && !locationPolygon.getBounds().isValid()) {
            console.warn('Limites inválidos para o polígono desenhado');
        } else if (locationPolygon && locationPolygon.getBounds) {
            map.fitBounds(locationPolygon.getBounds());
        }
        
        // Armazenar os dados originais na localização atual para redesenhar
        if (currentLocation) {
            if (originalData.geojsons) {
                currentLocation._originalGeojsons = originalData.geojsons;
            } else {
                // Se os dados originais não tiverem geojsons mas a localização atual tiver
                currentLocation._originalGeojsons = currentLocation.geojsons;
            }
        }
        
        console.log('Limite desenhado com sucesso');
    } catch (error) {
        console.error('Erro ao desenhar limite:', error);
    }
}

/**
 * Limpar todas as camadas de limites
 */
function clearBoundaries() {
    // Remover polígono existente, se houver
    if (locationPolygon) {
        console.log('A remover polígono existente');
        map.removeLayer(locationPolygon);
        locationPolygon = null;
    }
    
    // Remover todos os polígonos de freguesia
    if (freguesiaPolygons.length > 0) {
        console.log('A remover polígonos de freguesia:', freguesiaPolygons.length);
        freguesiaPolygons.forEach(polygon => {
            if (polygon) {
                map.removeLayer(polygon);
            }
        });
        freguesiaPolygons = [];
    }
}

/**
 * Limpar a seleção de localização atual
 */
function clearLocationSelection() {
    console.log('A limpar seleção de localização');
    
    // Remover marcador, se houver
    if (locationMarker) {
        console.log('A remover marcador');
        map.removeLayer(locationMarker);
        locationMarker = null;
    }
    
    // Remover todos os polígonos de limite
    clearBoundaries();
    
    // Redefinir coordenadas clicadas
    currentClickedCoordinates = null;
    
    // Redefinir dropdowns
    document.getElementById('distrito-select').value = '';
    document.getElementById('concelho-select').value = '';
    document.getElementById('concelho-select').innerHTML = '<option value="">Selecione um concelho...</option>';
    document.getElementById('concelho-select').disabled = true;
    document.getElementById('freguesia-select').value = '';
    document.getElementById('freguesia-select').innerHTML = '<option value="">Selecione uma freguesia...</option>';
    document.getElementById('freguesia-select').disabled = true;
    
    // Limpar painel de dados de localização
    document.getElementById('location-data').innerHTML = '<p>Selecione uma localização para ver os dados</p>';
    
    // Esconder o painel de dados
    const locationPanel = document.querySelector('.location-data-panel');
    console.log('A fechar painel, antes de remover classe:', locationPanel.className);
    locationPanel.classList.remove('visible');
    console.log('Painel depois de remover classe:', locationPanel.className);
    
    // Esconder o botão "Página Completa"
    const fullDataLink = document.getElementById('view-full-data');
    if (fullDataLink) {
        fullDataLink.style.display = 'none';
    }
    
    // Redefinir dados de localização atuais
    currentLocation = null;
}

/**
 * Configurar todos os ouvintes de evento para elementos da UI
 */
function setupEventListeners() {
    // Alternar menu móvel
    document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.toggle('active');
    });

    // Fechar painel móvel
    document.getElementById('mobile-panel-close').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.remove('active');
    });

    // Inicializar o botão "Página Completa" como escondido
    const fullDataLink = document.getElementById('view-full-data');
    if (fullDataLink) {
        fullDataLink.style.display = 'none';
    }

    // Opções de estilo de mapa
    // Opções de estilo do mapa
    document.querySelectorAll('.map-style-option').forEach(option => {
        option.addEventListener('click', function() {
            const provider = this.dataset.provider;
            setMapStyle(provider);
        });
    });
    
    // Evento de alteração do seletor de distrito
    document.getElementById('distrito-select').addEventListener('change', function() {
        const selectedDistrito = this.value;
        
        // Reiniciar concelho e freguesia
        const concelhoSelect = document.getElementById('concelho-select');
        concelhoSelect.value = '';
        concelhoSelect.innerHTML = '<option value="">Selecione um concelho...</option>';
        concelhoSelect.disabled = true;
        
        const freguesiaSelect = document.getElementById('freguesia-select');
        freguesiaSelect.value = '';
        freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
        freguesiaSelect.disabled = true;
        
        // Remover quaisquer marcadores ou polígonos do mapa de seleções anteriores
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reiniciar currentClickedCoordinates
        currentClickedCoordinates = null;
        
        if (selectedDistrito) {
            loadConcelhos(selectedDistrito);
        }
    });

    // Evento de alteração do seletor de concelho
    document.getElementById('concelho-select').addEventListener('change', function() {
        const selectedConcelho = this.value;
        
        // Reiniciar freguesia
        const freguesiaSelect = document.getElementById('freguesia-select');
        freguesiaSelect.value = '';
        freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
        freguesiaSelect.disabled = true;
        
        // Remover quaisquer marcadores ou polígonos do mapa de seleções anteriores
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reiniciar currentClickedCoordinates
        currentClickedCoordinates = null;
        
        if (selectedConcelho) {
            loadFreguesias(selectedConcelho);
        }
    });

    // Evento de alteração do seletor de freguesia
    document.getElementById('freguesia-select').addEventListener('change', function() {
        // Remover quaisquer marcadores ou polígonos do mapa de seleções anteriores
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reiniciar currentClickedCoordinates
        currentClickedCoordinates = null;
    });

    // Evento do botão Calcular - agora este é o único local onde os dados são obtidos
    document.querySelector('.calculate-button').addEventListener('click', function() {
        // Obter os valores selecionados
        const selectedDistrito = document.getElementById('distrito-select').value;
        const selectedConcelho = document.getElementById('concelho-select').value;
        const selectedFreguesia = document.getElementById('freguesia-select').value;
        
        // Limpar quaisquer dados de localização existentes (mas manter o marcador, se existir)
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Garantir que a barra lateral do censo está oculta antes de obter novos dados
        document.getElementById('census-sidebar').classList.remove('active');
        censusSidebarActive = false;
        
        // Atualizar a interface de utilizador para mostrar o estado de carregamento
        document.querySelector('.calculate-button').textContent = 'A carregar...';
        document.querySelector('.calculate-button').disabled = true;
        
        // Determinar o que obter com base na seleção ou nas coordenadas clicadas
        if (currentClickedCoordinates) {
            // Se o mapa foi clicado, priorizar essas coordenadas
            fetchLocationByCoordinates(
                currentClickedCoordinates.lat, 
                currentClickedCoordinates.lng
            );
        } else if (selectedFreguesia && selectedConcelho) {
            // Se freguesia está selecionada, obter dados da freguesia
            fetchLocationByFreguesia(selectedFreguesia, selectedConcelho);
        } else if (selectedConcelho) {
            // Se apenas concelho está selecionado (sem freguesia), obter dados do concelho
            fetchLocationByMunicipio(selectedConcelho);
        } else if (selectedDistrito) {
            // Se apenas distrito está selecionado (sem concelho ou freguesia), obter dados do distrito
            fetchLocationByDistrito(selectedDistrito);
        } else {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            alert('Selecione uma localização antes de carregar os dados.');
        }
    });

    // Fechar painel de dados de localização
    document.querySelector('.location-data-panel .close-panel').addEventListener('click', function() {
        const locationPanel = document.querySelector('.location-data-panel');
        console.log('Close button clicked, panel before:', locationPanel.className);
        locationPanel.classList.remove('visible');
        console.log('Panel after removing class:', locationPanel.className);
        
        // Verificar se o painel está realmente oculto
        setTimeout(() => {
            const computedStyle = window.getComputedStyle(locationPanel);
            console.log('Panel computed right value:', computedStyle.right);
            console.log('Panel is visible:', locationPanel.classList.contains('visible'));
        }, 100);
    });

    // Botão de fechar da barra lateral do censo
    document.getElementById('census-close-btn').addEventListener('click', function() {
        document.getElementById('census-sidebar').classList.remove('active');
        censusSidebarActive = false;
    });
    
    // Interruptor de ano do censo
    document.getElementById('census-year-toggle').addEventListener('change', function() {
        if (!currentLocation) return;
        
        const selectedYear = this.checked ? 2021 : 2011;
        if (currentCensusYear === selectedYear) return;
        
        currentCensusYear = selectedYear;
        
        // Obter os dados do censo relevantes
        const primaryCensus = selectedYear === 2021 ? currentLocation.censos2021 : currentLocation.censos2011;
        const secondaryCensus = selectedYear === 2021 ? currentLocation.censos2011 : currentLocation.censos2021;
        
        if (!primaryCensus) return;
        
        // Atualizar estatísticas com animação
        animateStatUpdate('population-value', primaryCensus);
        animateStatUpdate('buildings-value', primaryCensus);
        animateStatUpdate('dwellings-value', primaryCensus);
        animateStatUpdate('density-value', primaryCensus);
        
        // Atualizar gráficos
        updateCensusStats(primaryCensus, secondaryCensus);
        
        // Recriar gráficos com novos dados
        if (typeof Chart !== 'undefined') {
            createGenderChart(primaryCensus);
            calculateAverageAge(primaryCensus);
        }
        
        // Recriar barras etárias
        createAgeBars(primaryCensus);
    });
    
    // Ligação para location_data.php
    document.getElementById('census-view-full-data').addEventListener('click', function(e) {
        if (currentLocation) {
            const url = buildFullDataUrl(currentLocation);
            if (url) {
                this.href = url;
                return true;
            }
        }
        
        e.preventDefault();
        alert('Por favor, selecione uma localização primeiro.');
        return false;
    });
    
    // Lidar com a tecla ESC para fechar a barra lateral do censo
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && censusSidebarActive) {
            document.getElementById('census-sidebar').classList.remove('active');
            censusSidebarActive = false;
        }
    });

    // Interruptor de freguesias
    document.getElementById('show-freguesias-toggle').addEventListener('change', function() {
        showFreguesias = this.checked;
        console.log('Show freguesias toggle changed to:', showFreguesias);
        
        // Redesenhar limites se tivermos dados de localização atuais com freguesias
        if (currentLocation) {
            console.log('Redrawing with freguesias toggle:', showFreguesias);
            
            if (currentLocation._originalGeojsons) {
                console.log('Usando geojsons originais armazenados');
                const tempData = { ...currentLocation };
                tempData.geojsons = currentLocation._originalGeojsons;
                drawLocationBoundary(tempData);
            } else if (currentLocation.geojsons) {
                console.log('Usando geojsons atuais');
                drawLocationBoundary(currentLocation);
            }
        }
    });
}

/**
 * Animar atualização de valor de estatística
 */
function animateStatUpdate(elementId, censusData) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    let targetValue;
    
    switch (elementId) {
        case 'population-value':
            targetValue = getCensusValue(censusData, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            break;
        case 'buildings-value':
            targetValue = getCensusValue(censusData, ['N_EDIFICIOS_CLASSICOS', 'N_EDIFICIOS']);
            break;
        case 'dwellings-value':
            targetValue = getCensusValue(censusData, ['N_ALOJAMENTOS_TOTAL', 'N_ALOJAMENTOS']);
            break;
        case 'density-value':
            const population = getCensusValue(censusData, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            // Verificar múltiplos nomes de propriedade de área possíveis
            const areaHa = currentLocation.area_ha || currentLocation.areaha || currentLocation.area || 
                          (currentLocation.data && currentLocation.data.area_ha) || 
                          (currentLocation.data && currentLocation.data.areaha);
            
            if (population && areaHa) {
                const areaKm2 = areaHa / 100;
                targetValue = Math.round(population / areaKm2);
            } else if (population) {
                // Estimativa de densidade de recurso
                targetValue = Math.round(population / 10);
            }
            break;
        case 'average-age-value':
            // Vamos lidar com isto de forma especial
            calculateAverageAge(censusData);
            return;
    }
    
    if (!targetValue) {
        element.textContent = 'N/A';
        return;
    }
    
    // Obter valor atual
    let currentValue = parseInt(element.textContent.replace(/[^\d]/g, '')) || 0;
    const diff = targetValue - currentValue;
    
    // Usar frame de animação para atualização suave
    let startTime;
    const duration = 1000; // 1 segundo
    
    function updateValue(timestamp) {
        if (!startTime) startTime = timestamp;
        
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress); // Exponential ease out
        
        const currentVal = Math.round(currentValue + diff * easeProgress);
        
        // Formatar com base no tipo de elemento
        if (elementId === 'density-value') {
            element.textContent = `${new Intl.NumberFormat('pt-PT').format(currentVal)} h/km²`;
        } else {
            element.textContent = new Intl.NumberFormat('pt-PT').format(currentVal);
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateValue);
        }
    }
    
    requestAnimationFrame(updateValue);
}

/**
 * Mostra um tutorial moderno para utilizadores pela primeira vez, explicando como usar a página de localização
 */
function showLocationTutorial() {
    // Verificar se o utilizador já viu o tutorial antes
    if (localStorage.getItem('minu15_location_tutorial_seen') === 'true') {
        return;
    }
    
    // Create the tutorial container
    const tutorialBox = document.createElement('div');
    tutorialBox.id = 'location-tutorial-box';
    tutorialBox.style.position = 'absolute';
    tutorialBox.style.top = '50%';
    tutorialBox.style.left = '50%';
    tutorialBox.style.transform = 'translate(-50%, -50%)';
    tutorialBox.style.background = 'rgba(255, 255, 255, 0.97)';
    tutorialBox.style.padding = '30px';
    tutorialBox.style.borderRadius = '16px';
    tutorialBox.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.2)';
    tutorialBox.style.zIndex = '1000';
    tutorialBox.style.maxWidth = '550px';
    tutorialBox.style.textAlign = 'left';
    tutorialBox.style.color = '#333';
    tutorialBox.style.fontFamily = "'Poppins', sans-serif";
    
    // Tutorial content
    tutorialBox.innerHTML = `
        <div style="display: flex; align-items: center; margin-bottom: 20px;">
            <div style="background: #3498db; width: 50px; height: 50px; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin-right: 15px;">
                <i class="fas fa-map-marked-alt" style="color: white; font-size: 24px;"></i>
            </div>
            <h2 style="margin: 0; color: #2c3e50; font-size: 24px; font-weight: 600;">Explorador de Localização</h2>
        </div>
        
        <p style="margin-bottom: 20px; line-height: 1.6; color: #555;">
            Bem-vindo ao Explorador de Localização! Esta ferramenta permite-lhe visualizar dados demográficos e estatísticos de qualquer localidade em Portugal.
        </p>
        
        <div style="margin-bottom: 25px;">
            <h3 style="color: #3498db; font-size: 18px; margin-bottom: 10px; border-bottom: 2px solid #3498db; padding-bottom: 5px; display: inline-block;">
                Como utilizar:
            </h3>
            
            <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                    <span>1</span>
                </div>
                <div>
                    <strong style="font-weight: 600; color: #2c3e50;">Selecione uma localidade</strong> 
                    <p style="margin-top: 5px; color: #555;">Utilize os menus dropdown no painel lateral para escolher um distrito, concelho e freguesia, ou <span style="background: #f1f8fe; color: #3498db; padding: 0 5px; font-weight: 500;">simplesmente clique diretamente no mapa</span> para selecionar uma localização exata.</p>
                </div>
            </div>
            
            <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                    <span>2</span>
                </div>
                <div>
                    <strong style="font-weight: 600; color: #2c3e50;">Carregue os dados</strong> 
                    <p style="margin-top: 5px; color: #555;">Após selecionar uma localidade, clique no botão <span style="background: #f1f8fe; color: #3498db; padding: 0 5px; font-weight: 500;">"Carregar Dados"</span> para obter as informações estatísticas da área.</p>
                </div>
            </div>
            
            <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                    <span>3</span>
                </div>
                <div>
                    <strong style="font-weight: 600; color: #2c3e50;">Explore os dados</strong> 
                    <p style="margin-top: 5px; color: #555;">Visualize os dados demográficos, compare informações entre os Censos de 2011 e 2021, e veja os limites geográficos da localidade.</p>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 12px 15px; background: #f8f9fa; border-left: 4px solid #3498db; border-radius: 4px;">
                <div style="display: flex; align-items: center;">
                    <i class="fas fa-lightbulb" style="color: #f39c12; margin-right: 10px; font-size: 18px;"></i>
                    <strong style="color: #2c3e50;">Dica:</strong>
                </div>
                <p style="margin-top: 8px; color: #555; font-size: 14px;">
                    Ao clicar no mapa, um marcador será colocado e o botão "Carregar Dados" ficará destacado. Clique nele para visualizar os dados dessa localização específica.
                </p>
             </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <label style="display: flex; align-items: center; color: #7f8c8d; cursor: pointer;">
                <input type="checkbox" id="dont-show-location-tutorial" style="margin-right: 8px;">
                <span>Não mostrar novamente</span>
            </label>
            
            <button id="location-tutorial-btn" style="background: #3498db; color: white; border: none; border-radius: 30px; padding: 12px 30px; font-size: 16px; font-weight: 500; cursor: pointer; transition: all 0.3s ease;">
                Começar a explorar
            </button>
        </div>
    `;
    
    document.body.appendChild(tutorialBox);
    
    // Add entrance animation for the tutorial box
    tutorialBox.style.opacity = '0';
    tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
    tutorialBox.style.transition = 'all 0.3s ease-out';
    
    // Trigger the animation after a small delay
    setTimeout(() => {
        tutorialBox.style.opacity = '1';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(1)';
        
        // Show a visual hint of map click after tutorial appears
        setTimeout(() => {
            showMapClickAnimation();
        }, 1000);
    }, 100);
    
    // Prevent clicks on the tutorial from propagating to the map
    tutorialBox.addEventListener('click', function(event) {
        event.stopPropagation();
    });
    
    // Add hover effect to the button
    const tutorialBtn = document.getElementById('location-tutorial-btn');
    tutorialBtn.addEventListener('mouseover', function() {
        this.style.background = '#2980b9';
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 5px 15px rgba(52, 152, 219, 0.4)';
    });
    
    tutorialBtn.addEventListener('mouseout', function() {
        this.style.background = '#3498db';
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    
    // Close button event
    tutorialBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        
        // Add exit animation
        tutorialBox.style.opacity = '0';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
        
        // Save preference if checkbox is checked
        if (document.getElementById('dont-show-location-tutorial').checked) {
            localStorage.setItem('minu15_location_tutorial_seen', 'true');
        }
        
        // Remove after animation completes
        setTimeout(() => {
            document.getElementById('location-tutorial-box').remove();
            
            // Highlight key UI elements after tutorial closes
            highlightKeyElements();
        }, 300);
    });
    
    // "Don't show again" checkbox
    document.getElementById('dont-show-location-tutorial').addEventListener('click', function(event) {
        event.stopPropagation();
    });
}

/**
 * Highlights key UI elements to guide users where to start
 */
function highlightKeyElements() {
    // Highlight the sidebar panel
    const panel = document.getElementById('overlay-panel');
    const calculateButton = document.querySelector('.calculate-button');
    
    // Create highlight effect elements
    const panelHighlight = document.createElement('div');
    panelHighlight.style.position = 'absolute';
    panelHighlight.style.top = '0';
    panelHighlight.style.left = '0';
    panelHighlight.style.width = '100%';
    panelHighlight.style.height = '100%';
    panelHighlight.style.boxShadow = '0 0 0 4px rgba(52, 152, 219, 0.7)';
    panelHighlight.style.borderRadius = 'inherit';
    panelHighlight.style.pointerEvents = 'none';
    panelHighlight.style.zIndex = '1000';
    panelHighlight.style.opacity = '0';
    panelHighlight.style.transition = 'opacity 0.5s ease-in-out';
    
    // Add the highlight to the panel
    panel.style.position = 'relative';
    panel.appendChild(panelHighlight);
    
    // Animate the highlight for the panel
    setTimeout(() => {
        panelHighlight.style.opacity = '1';
        
        // Add a pulsing animation to the calculate button
        calculateButton.classList.add('pulse-highlight');
        
        // Remove the highlights after 3 seconds
        setTimeout(() => {
            panelHighlight.style.opacity = '0';
            calculateButton.classList.remove('pulse-highlight');
            
            // Remove the highlight elements after fade out
            setTimeout(() => {
                panelHighlight.remove();
            }, 500);
        }, 3000);
    }, 500);
    
    // Add the pulse animation class if it doesn't exist
    if (!document.getElementById('pulse-animation-style')) {
        const style = document.createElement('style');
        style.id = 'pulse-animation-style';
        style.textContent = `
            @keyframes pulse-animation {
                0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
                100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
            }
            .pulse-highlight {
                animation: pulse-animation 1.5s infinite;
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Shows a visual animation suggesting to click on the map
 */
function showMapClickAnimation() {
    // Create the cursor element
    const cursor = document.createElement('div');
    cursor.style.position = 'absolute';
    cursor.style.width = '24px';
    cursor.style.height = '24px';
    cursor.style.background = 'url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3E%3Cpath fill=\'%23ffffff\' stroke=\'%23000000\' stroke-width=\'1\' d=\'M1 1 L15 8 L8 10 L10 15 L7 7 Z\'/%3E%3C/svg%3E") no-repeat';
    cursor.style.backgroundSize = 'contain';
    cursor.style.zIndex = '1001';
    cursor.style.pointerEvents = 'none';
    cursor.style.transition = 'transform 0.2s ease-out';
    cursor.style.transform = 'scale(1.2)';
    cursor.style.opacity = '0.9';
    document.body.appendChild(cursor);
    
    // Create the click effect element
    const clickEffect = document.createElement('div');
    clickEffect.style.position = 'absolute';
    clickEffect.style.width = '40px';
    clickEffect.style.height = '40px';
    clickEffect.style.borderRadius = '50%';
    clickEffect.style.background = 'rgba(52, 152, 219, 0.4)';
    clickEffect.style.transform = 'translate(-50%, -50%) scale(0)';
    clickEffect.style.zIndex = '1000';
    clickEffect.style.pointerEvents = 'none';
    document.body.appendChild(clickEffect);
    
    // Get a point in the center-right area of the map
    const mapElement = document.getElementById('map');
    const mapRect = mapElement.getBoundingClientRect();
    const startX = mapRect.left + mapRect.width * 0.65;
    const startY = mapRect.top + mapRect.height * 0.4;
    const targetX = startX + 50;
    const targetY = startY + 30;
    
    // Position the cursor at starting point
    cursor.style.left = startX + 'px';
    cursor.style.top = startY + 'px';
    
    // Animate cursor to target position
    setTimeout(() => {
        cursor.style.transition = 'all 1s ease-in-out';
        cursor.style.left = targetX + 'px';
        cursor.style.top = targetY + 'px';
        
        // Show click effect at target position
        setTimeout(() => {
            cursor.style.transform = 'scale(0.8)';
            
            // Position and animate the click effect
            clickEffect.style.left = targetX + 'px';
            clickEffect.style.top = targetY + 'px';
            clickEffect.style.transition = 'all 0.5s ease-out';
            clickEffect.style.transform = 'translate(-50%, -50%) scale(1)';
            clickEffect.style.opacity = '1';
            
            // Fade out click effect
            setTimeout(() => {
                clickEffect.style.transform = 'translate(-50%, -50%) scale(1.5)';
                clickEffect.style.opacity = '0';
                
                // Clean up after animation
                setTimeout(() => {
                    cursor.remove();
                    clickEffect.remove();
                }, 500);
            }, 500);
        }, 1000);
    }, 500);
} 