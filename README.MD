# Explorador de Cidade em 15 Minutos

Uma aplicação SIG que explora o conceito da "cidade de 15 minutos" - uma visão de planeamento urbano onde todas as necessidades da vida quotidiana são acessíveis a 15 minutos de distância de qualquer localização, seja a pé, de bicicleta ou de automóvel.

## Visão Geral do Projeto

Esta aplicação permite aos utilizadores:

- Explorar o que é acessível num raio de 15 minutos a partir de qualquer ponto em Portugal
- Visualizar diferentes modos de deslocação (a pé, de bicicleta, de automóvel)
- Descobrir vários pontos de interesse (hospitais, escolas, centros de saúde, etc.)
- Ver estatísticas sobre a área selecionada
- Aceder a detalhes sobre cada ponto de interesse

## Funcionalidades

- **Mapa Interativo**: Clique em qualquer lugar para ver o que é acessível dentro do intervalo de tempo escolhido
- **Múltiplos Modos de Transporte**: Alterne entre andar a pé, de bicicleta e de automóvel
- **Tempo de Viagem Ajustável**: Deslize para ajustar o tempo máximo de viagem (5-30 minutos)
- **Filtragem de POI**: Mostrar ou ocultar tipos específicos de pontos de interesse
- **Estatísticas da Área**: Visualize estatísticas sobre a área selecionada, incluindo contagens de POI e população estimada
- **Detalhes de POI**: Clique em qualquer ponto de interesse para ver informações detalhadas sobre o mesmo

## Arquitetura Técnica

- **Frontend**: HTML, CSS, JavaScript com Leaflet.js e Turf.js
- **Backend**: PHP
- **Base de Dados**: PostgreSQL com extensão PostGIS
- **Fonte de Dados**: Dados do OpenStreetMap via Geofabrik

## Instalação e Configuração

### Pré-requisitos

- Servidor web com PHP 7.4+ (ex: Apache, Nginx com XAMPP/WAMP)
- PostgreSQL 12+ com extensão PostGIS
- Ferramenta Osmosis para importação de dados OSM

### Passos de Instalação

1. **Configurar a base de dados**:
   - Instalar PostgreSQL e PostGIS
   - Executar o script de inicialização: `scripts/init_database.bat`

2. **Importar dados do OpenStreetMap**:
   - Descarregar os dados OSM de Portugal do Geofabrik manualmente ou utilizar o script fornecido
   - Executar o script de atualização: `php scripts/update_geofabrik_data.php`
   - Aguardar pela conclusão da importação (isto pode demorar algum tempo)
   
3. **Agendar atualizações semanais de dados** (opcional):
   - Executar `scripts/schedule_geofabrik_update.bat` para criar uma tarefa agendada
   - Isto irá descarregar dados atualizados do Geofabrik todos os domingos às 3:00

4. **Configurar o servidor web**:
   - Direcionar a raiz de documentos do servidor web para o diretório do projeto
   - Certificar-se de que o PHP está configurado para funcionar com PostgreSQL

5. **Aceder à aplicação**:
   - Abrir o navegador web e navegar para o URL do servidor web
   - Começar a explorar o conceito de cidade de 15 minutos!

## Guia de Utilização

1. **Selecionar uma localização**: Clique em qualquer lugar no mapa para definir o seu ponto de partida
2. **Escolher modo de transporte**: Selecione andar a pé, de bicicleta ou de automóvel
3. **Ajustar tempo de viagem**: Mova o controlo deslizante para alterar o tempo máximo de viagem
4. **Filtrar POIs**: Utilize as caixas de seleção para mostrar ou ocultar tipos específicos de pontos de interesse
5. **Ver estatísticas**: Consulte o painel de estatísticas para obter informações sobre a área selecionada
6. **Explorar detalhes dos POI**: Clique em qualquer marcador de ponto de interesse para obter mais informações

## Estrutura de Dados

A aplicação utiliza dados do OpenStreetMap importados para PostgreSQL utilizando a seguinte estrutura:

- **planet_osm_point**: Contém elementos pontuais como hospitais, escolas, lojas
- **planet_osm_line**: Contém elementos lineares como estradas, ferrovias
- **planet_osm_polygon**: Contém elementos de área como edifícios, parques
- **planet_osm_roads**: Contém a rede de estradas principais

## Estrutura do Projeto

```
/
├── assets/             # Imagens e outros recursos
├── config/             # Ficheiros de configuração
│   └── db_config.php   # Definições de ligação à base de dados
├── css/                # Folhas de estilo
│   └── style.css       # Ficheiro CSS principal
├── includes/           # Ficheiros de inclusão PHP
│   ├── fetch_pois.php  # Endpoint API para obter POIs
│   └── fetch_statistics.php # Endpoint API para obter estatísticas da área
├── js/                 # Ficheiros JavaScript
│   ├── controls.js     # Funcionalidade dos controlos da interface
│   └── map.js          # Funcionalidade do mapa e geoespacial
├── scripts/            # Scripts utilitários
│   ├── init_database.php        # Inicialização da base de dados
│   ├── init_database.bat        # Batch Windows para inicialização da BD
│   ├── update_geofabrik_data.php # Script de atualização de dados
│   └── schedule_geofabrik_update.bat # Agendador de tarefas
└── index.php           # Ponto de entrada principal da aplicação
```

## Notas para Desenvolvedores

- A geração de isócronas atualmente utiliza círculos de buffer básicos baseados na velocidade de deslocação. Para resultados mais precisos, considere implementar algoritmos de encaminhamento reais.
- As velocidades de deslocação são estimadas como: a pé (5 km/h), de bicicleta (15 km/h), de automóvel (60 km/h).
- As estimativas de população são aproximações baseadas na contagem de edifícios.

## Melhorias Futuras

- Implementar algoritmos de encaminhamento para polígonos de tempo de viagem mais precisos
- Adicionar informações mais detalhadas sobre POI
- Integrar opções de transporte público
- Adicionar capacidade de guardar e partilhar localizações
- Incluir dados demográficos para melhores estimativas populacionais
- Expandir para além de Portugal para outras regiões

## Licença

Este projeto é para fins educativos.

## Créditos

- Dados OpenStreetMap de [Geofabrik](https://download.geofabrik.de/)
- Visualização de mapas com [Leaflet](https://leafletjs.com/)
- Cálculos geoespaciais com [Turf.js](https://turfjs.org/)