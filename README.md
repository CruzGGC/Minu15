# Minu15 - Cidade em 15 Minutos

## Descrição do Projeto

O Minu15 é uma aplicação web que permite visualizar e analisar a acessibilidade urbana baseada no conceito de "Cidade de 15 Minutos" - um modelo de desenvolvimento urbano onde os cidadãos podem aceder a todas as suas necessidades básicas num raio de 15 minutos a pé ou de bicicleta da sua residência.

Através da utilização de dados geoespaciais e análise de proximidade, o Minu15 ajuda a identificar áreas bem servidas e áreas com potencial para melhoria, promovendo cidades mais sustentáveis, acessíveis e habitáveis.

## Funcionalidades Principais

### Mapa Interativo
- Visualização de áreas acessíveis em intervalos de tempo (5-30 minutos)
- Suporte para diferentes modos de transporte (a pé, bicicleta, automóvel)
- Cálculo automático de isócronas ao clicar num ponto do mapa
- Sistema de pesquisa com autocompletar para localidades

### Pontos de Interesse (POIs)
- Visualização de múltiplas categorias de POIs:
  - Saúde (hospitais, centros de saúde, farmácias, clínicas dentárias)
  - Educação (escolas, universidades, jardins de infância, bibliotecas)
  - Comércio e serviços (supermercados, centros comerciais, restaurantes, caixas multibanco)
  - Segurança e serviços públicos (polícia, bombeiros, serviços governamentais)
  - Administração pública (câmaras municipais, correios)
  - Cultura e lazer (museus, teatros, ginásios, parques)

### Análise de Acessibilidade
- Cálculo de pontuação de acessibilidade para qualquer ponto selecionado
- Ponderação personalizada para diferentes tipos de POIs
- Estatísticas detalhadas sobre serviços acessíveis

### Dados Demográficos
- Exploração de dados demográficos de qualquer freguesia, concelho ou distrito em Portugal
- Comparação de dados dos Censos 2011 e 2021
- Visualização de limites administrativos

### Localizador Ideal (Conceito)
- Especificação de POIs necessários com níveis de importância
- Identificação de áreas que melhor satisfazem os critérios especificados
- Visualização de resultados em mapa de calor

## Arquitetura Técnica

### Visão Geral da Arquitetura
O Minu15 segue uma arquitetura cliente-servidor tradicional com processamento híbrido:
- **Frontend**: Renderização do mapa e interface do utilizador, processamento de dados geoespaciais leves
- **Backend**: Processamento pesado de dados geoespaciais, comunicação com APIs externas, gestão de cache
- **APIs Externas**: Fornecimento de dados geográficos, isócronas e informações demográficas

```
┌─────────────┐     ┌─────────────┐     ┌─────────────────────┐
│             │     │             │     │                     │
│  Frontend   │◄───►│   Backend   │◄───►│   APIs Externas     │
│  (Browser)  │     │   (PHP)     │     │   (OpenRouteService,│
│             │     │             │     │    GeoAPI.pt, etc)  │
└─────────────┘     └─────────────┘     └─────────────────────┘
                          ▲
                          │
                          ▼
                    ┌─────────────┐
                    │             │
                    │   Cache     │
                    │             │
                    └─────────────┘
```

### Fluxo de Dados
1. O utilizador interage com a interface (seleciona um ponto, ajusta parâmetros)
2. O frontend envia pedidos ao backend PHP
3. O backend verifica a cache para dados existentes
4. Se necessário, o backend comunica com APIs externas
5. Os dados são processados e enviados de volta ao frontend
6. O frontend renderiza os resultados no mapa e nos painéis informativos

## Detalhes Técnicos

### Sistema de Cache
O Minu15 implementa um sistema de cache em dois níveis para otimizar o desempenho:

1. **Cache de Dados da GeoAPI**:
   - Armazena dados administrativos e demográficos
   - Estrutura: ficheiros JSON organizados por tipo (distritos, concelhos, freguesias)
   - Tempo de vida: 30 dias (dados relativamente estáticos)
   - Implementação: `cache/geoapi/[tipo]/[id].json`

2. **Cache de Dados de Localização**:
   - Armazena resultados de isócronas e POIs
   - Estrutura: ficheiros JSON organizados por coordenadas e parâmetros
   - Tempo de vida: 7 dias (dados que podem mudar com alguma frequência)
   - Formato de nome: `cache/location_data/[lat]_[lng]_[modo]_[tempo].json`

### Algoritmos e Cálculos

#### Cálculo de Isócronas
- Utiliza o algoritmo de Dijkstra modificado da API OpenRouteService
- Parâmetros configuráveis:
  - Ponto de origem (latitude, longitude)
  - Modo de transporte (a pé, bicicleta, automóvel)
  - Tempo máximo (5-30 minutos)
  - Resolução (número de pontos no polígono resultante)

#### Pontuação de Acessibilidade
A pontuação de acessibilidade é calculada através da seguinte fórmula:

```
Pontuação = Σ(Ni × Wi) / Σ(Wi)
```

Onde:
- Ni = Número de POIs do tipo i dentro da isócrona
- Wi = Peso atribuído ao tipo i de POI (configurável pelo utilizador)

O resultado é normalizado para uma escala de 0-100, onde:
- 0-20: Acessibilidade muito baixa
- 21-40: Acessibilidade baixa
- 41-60: Acessibilidade média
- 61-80: Acessibilidade boa
- 81-100: Acessibilidade excelente

#### Algoritmo do Localizador Ideal
1. Divide a área de interesse em células de 100x100 metros
2. Para cada célula:
   - Gera isócronas para o tempo especificado
   - Conta os POIs de cada tipo dentro da isócrona
   - Calcula a pontuação ponderada com base nas preferências do utilizador
3. Normaliza as pontuações em toda a área
4. Gera um mapa de calor onde cores mais quentes indicam áreas mais adequadas

### Estrutura de Dados

#### Formato de Dados Geoespaciais
- Coordenadas: Sistema de coordenadas WGS84 (EPSG:4326)
- Geometrias: GeoJSON para todas as estruturas espaciais
- Exemplo de estrutura de isócrona:

```json
{
  "type": "Feature",
  "properties": {
    "mode": "walking",
    "time": 15,
    "center": [longitude, latitude]
  },
  "geometry": {
    "type": "Polygon",
    "coordinates": [[[lng1, lat1], [lng2, lat2], ... , [lng1, lat1]]]
  }
}
```

#### Estrutura de Dados dos POIs
```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "id": "n12345678",
        "category": "health",
        "subcategory": "hospital",
        "name": "Hospital Santa Maria",
        "address": "Av. Prof. Egas Moniz",
        "opening_hours": "24/7"
      },
      "geometry": {
        "type": "Point",
        "coordinates": [longitude, latitude]
      }
    },
    ...
  ]
}
```

### API Interna

O Minu15 implementa uma API interna para comunicação entre o frontend e o backend:

#### Endpoints Principais

1. **Obtenção de Dados de Localização**
   - Endpoint: `location_data.php`
   - Método: POST
   - Parâmetros:
     - `lat`: Latitude do ponto central
     - `lng`: Longitude do ponto central
     - `mode`: Modo de transporte (walking, cycling, driving)
     - `time`: Tempo máximo em minutos
   - Resposta: JSON com isócrona e POIs dentro da área

2. **Dados Administrativos e Demográficos**
   - Endpoint: `location.php`
   - Método: POST
   - Ações:
     - `fetchByGps`: Obter dados por coordenadas GPS
     - `fetchByFreguesiaAndMunicipio`: Obter dados por freguesia e município
     - `fetchByMunicipio`: Obter dados por município
     - `fetchByDistrito`: Obter dados por distrito
     - `fetchAllDistritos`: Obter lista de todos os distritos
     - `fetchMunicipiosByDistrito`: Obter municípios de um distrito
     - `fetchFreguesiasByMunicipio`: Obter freguesias de um município

### Otimizações de Desempenho

1. **Carregamento Assíncrono**
   - Carregamento progressivo de POIs por categoria
   - Lazy loading de imagens e recursos
   - Processamento em segundo plano para cálculos intensivos

2. **Redução de Pedidos à API**
   - Sistema de cache em dois níveis
   - Agrupamento de pedidos (batch requests)
   - Throttling para evitar sobrecarga

3. **Otimizações de Renderização**
   - Clustering de marcadores para grandes conjuntos de POIs
   - Simplificação de polígonos para isócronas grandes
   - Utilização de Web Workers para processamento paralelo

4. **Otimizações de Base de Dados**
   - Indexação espacial para consultas geográficas rápidas
   - Particionamento de dados por região para consultas mais eficientes

### Segurança

1. **Proteção de API**
   - Ocultação de chaves de API no lado do servidor
   - Rate limiting para prevenir abusos
   - Validação de todos os parâmetros de entrada

2. **Sanitização de Dados**
   - Validação e sanitização de todos os inputs do utilizador
   - Prevenção contra injeção de SQL e XSS

3. **Proteção de Cache**
   - Verificação de integridade de ficheiros em cache
   - Permissões restritas para diretorias de cache

## Tecnologias Utilizadas

### Frontend
- HTML5, CSS3, JavaScript
- jQuery e jQuery UI para interações e autocompletar
- Leaflet.js para renderização de mapas interativos
- Turf.js para análise geoespacial no lado do cliente
- Font Awesome para ícones
- CSS Grid e Flexbox para layouts responsivos

### Backend
- PHP 7.4+ para processamento de dados e API
- Sistema de cache personalizado para otimização de desempenho
- Manipulação de ficheiros JSON para armazenamento de dados

### APIs e Fontes de Dados
- OpenRouteService para cálculo de isócronas e rotas
- GeoAPI.pt para dados administrativos e demográficos de Portugal
- OpenStreetMap (via Geofabrik) para dados de POIs
- Instituto Nacional de Estatística (INE) para dados demográficos

## Estrutura do Projeto

```
Minu15/
  ├── app.php                       # Aplicação principal do mapa interativo
  ├── ideal_finder.php              # Funcionalidade de localizador ideal (conceito)
  ├── location.php                  # Explorador de dados demográficos
  ├── location_data.php             # API para obtenção de dados de localização
  ├── index.php                     # Página inicial
  ├── cache/                        # Armazenamento de dados em cache
  │   ├── geoapi/                   # Cache de dados da GeoAPI.pt
  │   └── location_data/            # Cache de dados de localização
  ├── config/                       # Ficheiros de configuração
  │   ├── api_config.js             # Configuração de APIs
  │   └── map_config.js             # Configuração de mapas
  ├── css/                          # Folhas de estilo
  │   ├── style.css                 # Estilos principais
  │   ├── landing.css               # Estilos da página inicial
  │   └── location.css              # Estilos para página de dados demográficos
  ├── images/                       # Imagens e recursos gráficos
  │   └── landing/                  # Imagens para a página inicial
  ├── includes/                     # Ficheiros PHP incluídos
  │   ├── fetch_location_data.php   # Classe para obtenção de dados de localização
  │   └── cache_manager.php         # Gestor de cache
  ├── js/                           # Scripts JavaScript
  │   ├── app.js                    # Lógica principal da aplicação
  │   ├── isochrone.js              # Gestão de isócronas
  │   ├── poi.js                    # Gestão de POIs
  │   └── accessibility.js          # Cálculo de pontuação de acessibilidade
  └── scripts/                      # Scripts de utilidade
      ├── common/                   # Scripts comuns
      ├── ubuntu/                   # Scripts específicos para Ubuntu
      └── windows/                  # Scripts específicos para Windows
```

## Requisitos de Sistema

### Servidor
- PHP 7.4 ou superior
- Extensão cURL ativada para comunicação com APIs externas
- Extensão JSON para processamento de dados GeoJSON
- Extensão FileInfo para verificação de tipos de ficheiros
- Permissões de escrita para a diretoria `cache/`
- Recomendado: 2GB RAM ou superior para processamento de dados geoespaciais
- Espaço em disco: mínimo 500MB para aplicação e cache

### Cliente
- Navegador moderno com suporte a JavaScript ES6
- Recomendado: Google Chrome 80+, Mozilla Firefox 75+, Microsoft Edge 80+, Safari 13+
- Suporte a WebGL para renderização eficiente de mapas
- Ligação à Internet estável para carregamento de mapas e dados (mínimo 2Mbps)
- Resolução de ecrã mínima: 1280x720px (responsivo para dispositivos móveis)

## Instalação e Configuração

1. Clone o repositório:
   ```
   git clone https://github.com/CruzGGC/Minu15.git
   ```

2. Configure um servidor web (Apache, Nginx) para apontar para a diretoria do projeto.

3. Certifique-se de que o PHP está configurado corretamente e as extensões necessárias estão ativadas.

4. Crie as diretorias de cache se não existirem:
   ```
   mkdir -p cache/geoapi cache/location_data
   ```

5. Defina as permissões corretas:
   ```
   chmod -R 755 cache/
   ```

6. Configure as chaves de API no ficheiro `config/api_config.js` (substitua com as suas próprias chaves):
   ```javascript
   const API_KEYS = {
     openrouteservice: 'SUA_CHAVE_AQUI',
   };
   ```

7. Aceda à aplicação através do navegador.

## Resolução de Problemas Comuns

### Erro ao Carregar Isócronas
- **Problema**: Falha ao gerar isócronas para certas áreas
- **Solução**: Verifique os limites da API OpenRouteService (pode ser necessário aumentar o limite da sua chave)
- **Alternativa**: Reduza a resolução da isócrona nas configurações

### Cache Não Funcional
- **Problema**: Dados não estão a ser guardados em cache
- **Solução**: Verifique as permissões da diretoria `cache/` (deve ser gravável pelo utilizador do servidor web)
- **Diagnóstico**: Execute `php -r "echo is_writable('cache/') ? 'OK' : 'ERROR';"`

### POIs Não Visíveis
- **Problema**: Pontos de interesse não aparecem no mapa
- **Solução**: Verifique se as categorias estão ativadas no painel lateral
- **Alternativa**: Limpe a cache do navegador e recarregue a página

## Agradecimentos

- GeoAPI.pt pela disponibilização de dados geográficos de Portugal
- OpenStreetMap e Geofabrik pelos dados de POIs
- Instituto Nacional de Estatística pelos dados demográficos
- OpenRouteService pela API de cálculo de isócronas 
