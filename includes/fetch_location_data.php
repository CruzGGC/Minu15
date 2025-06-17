<?php
require_once __DIR__ . '/api_cache.php';

class LocationFetcher {
    private $baseUrl = "http://json.localhost:9090";
    private $cache;
    private $cacheEnabled = true;
    private $cacheExpiry = 604800; // 7 dias em segundos

    public function __construct($cacheEnabled = true, $cacheExpiry = null) {
        $this->cacheEnabled = $cacheEnabled;
        if ($cacheExpiry !== null) {
            $this->cacheExpiry = $cacheExpiry;
        }
        $this->cache = new ApiCache(__DIR__ . '/../cache/location_data/', $this->cacheExpiry);
    }

    private function callApi($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        // Registar a depuração do URL
        error_log("A chamar o URL da API: " . $url);

        // Verificar se temos um cache válido para este pedido
        if ($this->cacheEnabled) {
            $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
            if ($this->cache->hasValidCache($cacheKey)) {
                error_log("A usar dados em cache para: " . $url);
                return $this->cache->get($cacheKey);
            }
        }

        // Para URLs com espaços ou caracteres especiais, tentar primeiro diretamente
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']); // Pedir resposta JSON
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Definir tempo limite para 30 segundos

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // Se a chamada direta falhar (provavelmente devido a espaços ou caracteres especiais), tentar com codificação URL adequada
        if ($httpCode === 404 && (strpos($url, ' ') !== false || strpos($url, '+') !== false)) {
            error_log("URL com espaços ou caracteres especiais falhou, a tentar com URL codificado");
            curl_close($ch);
            
            // Codificar corretamente o URL, mantendo a estrutura da string de consulta
            $urlParts = parse_url($url);
            
            // Dividir o caminho por / e codificar cada segmento separadamente
            $pathSegments = explode('/', $urlParts['path']);
            $encodedSegments = [];
            
            foreach ($pathSegments as $segment) {
                if (!empty($segment)) {
                    // Substituir + por espaço antes de codificar para evitar dupla codificação
                    $segment = str_replace('+', ' ', $segment);
                    $encodedSegments[] = rawurlencode($segment);
                } else {
                    $encodedSegments[] = '';
                }
            }
            
            $encodedPath = implode('/', $encodedSegments);
            
            // Garantir que mantemos a barra inicial
            if (substr($urlParts['path'], 0, 1) === '/') {
                $encodedPath = '/' . ltrim($encodedPath, '/');
            }
            
            $encodedUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $encodedPath;
            if (isset($urlParts['query'])) {
                $encodedUrl .= '?' . $urlParts['query'];
            }
            
            error_log("URL codificado: " . $encodedUrl);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $encodedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
        }
        
        curl_close($ch);

        if ($curlError) {
            error_log("Erro cURL: " . $curlError . " para o URL: " . $url);
            return ['success' => false, 'message' => 'Erro cURL: ' . $curlError];
        }

        // Registar a depuração da resposta
        error_log("Resposta da API: " . substr($response, 0, 200) . (strlen($response) > 200 ? "..." : ""));
        error_log("Código HTTP: " . $httpCode);

        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            error_log("Erro de descodificação JSON: " . json_last_error_msg());
        }

        if ($httpCode === 200 && $jsonError === JSON_ERROR_NONE) {
            // Garantir um formato de resposta consistente
            $result = null;
            if (is_array($decodedResponse)) {
                $result = ['success' => true, 'data' => $decodedResponse];
            } else {
                $result = ['success' => true, 'data' => ['response' => $decodedResponse]];
            }
            
            // Guardar em cache a resposta bem-sucedida se o cache estiver ativado
            if ($this->cacheEnabled) {
                $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
                $this->cache->set($cacheKey, $result);
                error_log("Resposta da API guardada em cache para: " . $url);
            }
            
            return $result;
        } else {
            // Lidar com erros da API ou resposta JSON inválida
            $errorMessage = 'A chamada à API falhou';
            if ($jsonError !== JSON_ERROR_NONE) {
                $errorMessage .= ': Resposta JSON inválida - ' . json_last_error_msg();
            } else if (isset($decodedResponse['message'])) {
                $errorMessage .= ': ' . $decodedResponse['message'];
            } else {
                $errorMessage .= ' - Código HTTP: ' . $httpCode;
            }
            error_log("A chamada à API falhou: " . $url . " - Código HTTP: " . $httpCode . " - Resposta: " . ($response === false ? 'false' : $response));
            return ['success' => false, 'message' => $errorMessage, 'http_code' => $httpCode, 'response' => $response];
        }
    }
    
    /**
     * Limpar o cache para um endpoint específico
     * 
     * @param string $endpoint O endpoint da API
     * @param array $params Parâmetros adicionais
     * @return bool Verdadeiro se o cache foi limpo, falso caso contrário
     */
    public function clearCache($endpoint, $params = []) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheKey = $this->cache->generateCacheKey($endpoint, $params);
        return $this->cache->delete($cacheKey);
    }
    
    /**
     * Limpar todos os dados em cache
     * 
     * @return bool Verdadeiro se o cache foi limpo, falso caso contrário
     */
    public function clearAllCache() {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        return $this->cache->clearAll();
    }
    
    /**
     * Ativar ou desativar o cache
     * 
     * @param bool $enabled Verdadeiro para ativar o cache, falso para desativar
     */
    public function setCacheEnabled($enabled) {
        $this->cacheEnabled = $enabled;
    }

    public function fetchByGps($latitude, $longitude) {
        error_log("fetchByGps chamado com lat: $latitude, lng: $longitude");
        
        // Buscar localização completa pelo GPS (já deve incluir censos2021 e censos2011)
        $endpoint = "/gps/{$latitude},{$longitude}/base/detalhes";
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do GPS - censos2021 presente: " .
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") .
                     ", censos2011 presente: " .
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Extrair informações da freguesia e município
            $freguesia = null;
            $municipio = null;
            
            if (isset($result['data']['freguesia'])) {
                $freguesia = $result['data']['freguesia'];
            } else if (isset($result['data']['detalhesFreguesia']) && isset($result['data']['detalhesFreguesia']['nome'])) {
                $freguesia = $result['data']['detalhesFreguesia']['nome'];
                // Adicionar ao resultado principal para consistência
                $result['data']['freguesia'] = $freguesia;
            }
            
            if (isset($result['data']['municipio'])) {
                $municipio = $result['data']['municipio'];
            } else if (isset($result['data']['concelho'])) {
                $municipio = $result['data']['concelho'];
            } else if (isset($result['data']['detalhesMunicipio']) && isset($result['data']['detalhesMunicipio']['nome'])) {
                $municipio = $result['data']['detalhesMunicipio']['nome'];
                // Adicionar ao resultado principal para consistência
                $result['data']['municipio'] = $municipio;
            }
            
            // Buscar geometria (GeoJSON) se não estiver presente
            if (!isset($result['data']['geojson']) && $freguesia && $municipio) {
                error_log("Buscando geometria para a freguesia: " . $freguesia);
                
                $geometryEndpoint = "/freguesia/" . urlencode($freguesia) . "/geometria";
                $geometryParams = ['municipio' => $municipio, 'json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha']) && $freguesia && $municipio) {
                error_log("Buscando dados de área para a freguesia: " . $freguesia);
                
                $areaEndpoint = "/freguesia/" . urlencode($freguesia) . "/area";
                $areaParams = ['municipio' => $municipio, 'json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByGps result: " . print_r($result, true));
        return $result;
    }

    public function fetchByFreguesiaAndMunicipio($freguesia, $municipio) {
        error_log("fetchByFreguesiaAndMunicipio chamado com: " . $freguesia . ", " . $municipio);
        
        // Usar rawurlencode para caracteres especiais nos nomes das freguesias
        $rawFreguesia = rawurlencode($freguesia);
        
        // Tentar o endpoint da freguesia primeiro com a freguesia codificada em raw
        $endpoint = "/freguesia/" . $rawFreguesia;
        $params = ['municipio' => $municipio, 'json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se falhar, tentar com codificação URL padrão
        if (!$result['success']) {
            error_log("Codificação URL raw falhou, a tentar codificação padrão");
            // Usar urlencode padrão
            $encodedFreguesia = urlencode($freguesia);
            $endpoint = "/freguesia/" . $encodedFreguesia;
            $params = ['municipio' => $municipio, 'json' => 'true'];
            $result = $this->callApi($endpoint, $params);
        }
        
        // Tentar o endpoint município/freguesia como último recurso
        if (!$result['success']) {
            error_log("Codificação URL padrão falhou, a tentar endpoint município/freguesia");
            // Usar rawurlencode em vez de urlencode
            $encodedMunicipio = rawurlencode($municipio);
            $encodedFreguesia = rawurlencode($freguesia);
            $endpoint = "/municipio/" . $encodedMunicipio . "/freguesia/" . $encodedFreguesia;
            $params = ['json' => 'true'];
            $result = $this->callApi($endpoint, $params);
        }
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos da freguesia - censos2021 presente: " .
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") .
                     ", censos2011 presente: " .
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Verificar se o objeto geojsons existe na resposta
            if (isset($result['data']['geojsons'])) {
                error_log("Objeto geojsons encontrado na resposta da API com chaves: " .
                         (isset($result['data']['geojsons']) ? implode(", ", array_keys($result['data']['geojsons'])) : "nenhuma"));
                
                // Manter a estrutura original dos geojsons mas também definir geojson para compatibilidade retroativa
                if (isset($result['data']['geojsons']['freguesia'])) {
                    $result['data']['geojson'] = $result['data']['geojsons']['freguesia'];
                    error_log("Definir geojson de geojsons.freguesia para compatibilidade retroativa");
                }
                // Se houver um array de freguesias, encontrar a freguesia correspondente para compatibilidade retroativa
                else if (isset($result['data']['geojsons']['freguesias']) && is_array($result['data']['geojsons']['freguesias'])) {
                    foreach ($result['data']['geojsons']['freguesias'] as $freguesiaGeoJson) {
                        if (isset($freguesiaGeoJson['properties']) &&
                            isset($freguesiaGeoJson['properties']['Freguesia']) &&
                            $freguesiaGeoJson['properties']['Freguesia'] === $freguesia) {
                            $result['data']['geojson'] = $freguesiaGeoJson;
                            error_log("Definir geojson da freguesia correspondente no array geojsons.freguesias");
                            break;
                        }
                    }
                }
                
                // Registar se temos freguesias nos geojsons
                if (isset($result['data']['geojsons']['freguesias'])) {
                    error_log("Encontradas " . count($result['data']['geojsons']['freguesias']) . " freguesias em geojsons.freguesias");
                }
            }
            // Se ainda não houver geojson, tentar obtê-lo
            else if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para a freguesia: " . $freguesia);
                
                // Usar codificação raw para o endpoint de geometria
                $geometryEndpoint = "/freguesia/" . $rawFreguesia . "/geometria";
                $geometryParams = ['municipio' => $municipio, 'json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                // Tentar codificação padrão se raw falhar
                if (!$geometryResult['success']) {
                    $geometryEndpoint = "/freguesia/" . rawurlencode($freguesia) . "/geometria";
                    $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                }
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que os nomes da freguesia e município estão incluídos na resposta
            if (!isset($result['data']['freguesia'])) {
                $result['data']['freguesia'] = $freguesia;
            }
            if (!isset($result['data']['municipio']) && !isset($result['data']['concelho'])) {
                $result['data']['municipio'] = $municipio;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para a freguesia: " . $freguesia);
                
                // Usar codificação raw para o endpoint de área
                $areaEndpoint = "/freguesia/" . $rawFreguesia . "/area";
                $areaParams = ['municipio' => $municipio, 'json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                // Tentar codificação padrão se raw falhar
                if (!$areaResult['success']) {
                    $areaEndpoint = "/freguesia/" . rawurlencode($freguesia) . "/area";
                    $areaResult = $this->callApi($areaEndpoint, $areaParams);
                }
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByFreguesiaAndMunicipio result: " . print_r($result, true));
        return $result;
    }

    public function fetchByMunicipio($municipio) {
        error_log("fetchByMunicipio chamado com: " . $municipio);
        
        // Buscar dados completos do município (já deve incluir censos2021 e censos2011)
        $endpoint = "/municipio/" . urlencode($municipio);
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do município - censos2021 presente: " .
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") .
                     ", censos2011 presente: " .
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Verificar se geojsons existe na resposta
            if (isset($result['data']['geojsons'])) {
                error_log("Objeto geojsons encontrado na resposta da API com chaves: " .
                         (isset($result['data']['geojsons']) ? implode(", ", array_keys($result['data']['geojsons'])) : "nenhuma"));
                
                // Manter a estrutura original dos geojsons mas também definir geojson para compatibilidade retroativa
                if (isset($result['data']['geojsons']['municipio'])) {
                    $result['data']['geojson'] = $result['data']['geojsons']['municipio'];
                    error_log("Definir geojson de geojsons.municipio para compatibilidade retroativa");
                }
                
                // Registar se temos freguesias nos geojsons
                if (isset($result['data']['geojsons']['freguesias'])) {
                    error_log("Encontradas " . count($result['data']['geojsons']['freguesias']) . " freguesias em geojsons.freguesias");
                }
            }
            // Se não houver geojsons, tentar obter geometria se geojson não estiver presente
            else if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para o município: " . $municipio);
                
                $geometryEndpoint = "/municipio/" . urlencode($municipio) . "/geometria";
                $geometryParams = ['json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que o nome do município está incluído na resposta
            if (!isset($result['data']['municipio']) && !isset($result['data']['concelho'])) {
                $result['data']['municipio'] = $municipio;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para o município: " . $municipio);
                
                $areaEndpoint = "/municipio/" . urlencode($municipio) . "/area";
                $areaParams = ['json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByMunicipio result: " . print_r($result, true));
        return $result;
    }

    public function fetchByDistrito($distrito) {
        error_log("fetchByDistrito chamado com: " . $distrito);
        
        // Buscar dados completos do distrito (já inclui censos2021 e censos2011)
        $endpoint = "/distrito/" . urlencode($distrito);
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        // Se a busca for bem-sucedida, verificar se precisamos completar os dados
        if ($result['success'] && isset($result['data'])) {
            // Log dos dados recebidos para depuração
            error_log("Dados recebidos do distrito - censos2021 presente: " .
                     (isset($result['data']['censos2021']) ? "SIM" : "NÃO") .
                     ", censos2011 presente: " .
                     (isset($result['data']['censos2011']) ? "SIM" : "NÃO"));
            
            // Garantir que a geometria (GeoJSON) está presente
            if (!isset($result['data']['geojson'])) {
                error_log("Buscando geometria para o distrito: " . $distrito);
                
                $geometryEndpoint = "/distrito/" . urlencode($distrito) . "/geometria";
                $geometryParams = ['json' => 'true'];
                $geometryResult = $this->callApi($geometryEndpoint, $geometryParams);
                
                if ($geometryResult['success'] && isset($geometryResult['data'])) {
                    $result['data']['geojson'] = $geometryResult['data'];
                }
            }
            
            // Garantir que o nome do distrito está incluído na resposta
            if (!isset($result['data']['distrito'])) {
                $result['data']['distrito'] = $distrito;
            }
            
            // Incluir área se não estiver presente
            if (!isset($result['data']['area_ha']) && !isset($result['data']['areaha'])) {
                error_log("Buscando dados de área para o distrito: " . $distrito);
                
                $areaEndpoint = "/distrito/" . urlencode($distrito) . "/area";
                $areaParams = ['json' => 'true'];
                $areaResult = $this->callApi($areaEndpoint, $areaParams);
                
                if ($areaResult['success'] && isset($areaResult['data']) && isset($areaResult['data']['area_ha'])) {
                    $result['data']['area_ha'] = $areaResult['data']['area_ha'];
                }
            }
        }
        
        error_log("fetchByDistrito result: " . print_r($result, true));
        return $result;
    }

    public function fetchAllDistritos() {
        // Obter todos os distritos
        $endpoint = "/distritos";
        $params = ['json' => 'true'];
        $result = $this->callApi($endpoint, $params);
        
        return $result;
    }

    public function fetchMunicipiosByDistrito($distrito) {
        // Obter municípios por distrito
        $endpoint = "/distritos/" . urlencode($distrito) . "/municipios";
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }

    public function fetchFreguesiasByMunicipio($municipio) {
        // Obter freguesias por município
        $endpoint = "/municipio/" . urlencode($municipio) . "/freguesias";
        $params = ['json' => 'true'];
        return $this->callApi($endpoint, $params);
    }
}

?> 