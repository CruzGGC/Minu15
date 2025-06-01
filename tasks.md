## Tarefas de Desenvolvimento

1.  **Estatísticas Interativas:**
    *   Ao clicar num ponto aleatório nas estatísticas, identificar a freguesia correspondente. (mudar na parte Informacoes Gerais, adicionar informacao a essa parte com dados do INE no fundo das estatisticas, adicionar um botão que vai buscar as coordenadas selecionadas e vai usar a GEO API com essas coordenadas)
    *   Utilizar dados oficiais do GEO API para obter informações demográficas corretas da freguesia identificada.

2.  **Nova Página de Localização e Dados:**
    *   Adicionar uma nova página na aplicação que seja parecida a app.php.
    *   Permitir ao utilizador escolher um distrito, concelho ou freguesia de Portugal, ou poder escolher no mapa um ponto em que GEO API vai utilizar essas coordenandas para servir os dados dessa localização.
    *   Gerar um centroide no local especificado.
    *   Obter dados da GEO API para o local especificado.
    *   Obter o número de infraestruturas para o local especificado através da base de dados do Geofrabik.

3.  **Cálculo Automático no Mapa:** Completo!
    *   Modificar o mapa para que, ao clicar num local, o cálculo seja realizado automaticamente, sem a necessidade de clicar num botão "calcular".

4.  **Sistema de Pesquisa com Autocompletar:** Completo!
    *   Implementar um sistema de pesquisa com funcionalidade de autocompletar na barra de pesquisa já existente. 

5.  **Accessibility Score:** Colocar no topo das estatisticas Completo!
    *   Desenvolver um "accessibility score" para qualquer ponto selecionado.
    *   O score deve ser um cálculo ponderado baseado no número e variedade de POIs essenciais (supermercados, centros de saúde, escolas, etc.) alcançáveis no tempo selecionado pelo utilizador.
    *   Exibir o score no painel de estatísticas. 

6.  **Ideal Location Finder:** Criar uma nova pagina que seja igual a app.php
    *   Permitir que os utilizadores especifiquem um conjunto de POIs de que necessitam regularmente (ex: escola, supermercado, parque).
    *   Destacar áreas no mapa que melhor satisfazem esses critérios dentro de um raio de 15 minutos.

7.  **Day in the Life Simulation:** Criar uma nova pagina que seja igual a app.php
    *   Permitir que os utilizadores selecionem um ponto de partida e encadeiem uma série de atividades (ex: casa -> escola -> supermercado -> parque -> casa).
    *   Visualizar se esta rotina é realizável dentro de prazos razoáveis utilizando o conceito de cidade de 15 minutos. 