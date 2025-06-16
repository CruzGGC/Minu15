<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minu15 - Cidade em 15 Minutos</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>
    <!-- Hero Cursor Effect Container -->
    <div class="hero-cursor-effect"></div>
    
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <img src="images/Minu15.png" alt="Minu15 Logo" class="logo">
                <ul class="nav-links">
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section with Parallax -->
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="zoom-in">Minu15 - Cidade em 15 Minutos</h1>
            <p class="zoom-in">Descubra tudo o que está acessível a 15 minutos de onde se encontra</p>
            <div class="cta-buttons zoom-in">
                <a href="app.php" class="cta-button">
                    <i class="fas fa-map"></i>
                    Explorar Mapa
                </a>
                <a href="ideal_finder.php" class="cta-button cta-secondary">
                    <i class="fas fa-search-location"></i>
                    Localizador Ideal <span class="button-indicator">(Conceito)</span>
                </a>
                <a href="location.php" class="cta-button cta-tertiary">
                    <i class="fas fa-info-circle"></i>
                    Dados Demográficos
                </a>
            </div>
        </div>
    </section>
    
    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Sobre o Minu15</h2>
            </div>
            <div class="fade-in">
                <p>O Minu15 é uma aplicação web que permite visualizar e analisar a acessibilidade urbana baseada no conceito de "Cidade de 15 Minutos" - um modelo de desenvolvimento urbano onde os cidadãos podem aceder a todas as suas necessidades básicas num raio de 15 minutos a pé ou de bicicleta da sua residência.</p>
                <p>Através da utilização de dados geoespaciais e análise de proximidade, o Minu15 ajuda a identificar áreas bem servidas e áreas com potencial para melhoria, promovendo cidades mais sustentáveis, acessíveis e habitáveis.</p>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section id="features" class="section features">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Funcionalidades</h2>
            </div>
            <div class="feature-grid">
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Mapeamento Interativo</h3>
                        <p>Visualize todas as comodidades e serviços num mapa interativo e personalizável. Selecione um ponto no mapa e veja automaticamente a área acessível a 15 minutos.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Análise de Acessibilidade</h3>
                        <p>Calcule e visualize áreas acessíveis a pé, de bicicleta ou de automóvel em diferentes intervalos de tempo, desde 5 até 30 minutos.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Estatísticas Detalhadas</h3>
                        <p>Obtenha estatísticas sobre serviços públicos, transportes, comércio e áreas verdes na sua vizinhança, com pontuação de acessibilidade para cada local.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-search-location"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Localizador Ideal</h3>
                        <p>Especifique os serviços e comodidades que são importantes para si, e descubra as áreas ideais para viver com base nas suas necessidades específicas.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Dados Demográficos</h3>
                        <p>Explore dados demográficos detalhados de qualquer freguesia, concelho ou distrito em Portugal, com informações dos Censos 2011 e 2021.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="feature-content">
                        <h3>Visualização Personalizada</h3>
                        <p>Personalize a visualização do mapa com diferentes estilos e filtros de pontos de interesse, adaptando a experiência às suas necessidades.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Parallax Section -->
    <section class="parallax">
        <div class="parallax-overlay"></div>
        <div class="parallax-content">
            <h2 class="fade-in">Cidades mais Habitáveis e Sustentáveis</h2>
            <p class="fade-in">Promovendo mobilidade ativa, reduzindo deslocações desnecessárias e melhorando a qualidade de vida</p>
        </div>
    </section>
    
    <!-- Tools Section -->
    <section id="tools" class="section">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Ferramentas Disponíveis</h2>
            </div>
            <div class="tools-grid">
                <div class="tool-card fade-in">
                    <div class="tool-icon">
                        <i class="fas fa-map"></i>
                    </div>
                    <h3>Mapa Interativo</h3>
                    <p>Explore a cidade com um mapa interativo que mostra todos os serviços e comodidades disponíveis. Clique em qualquer ponto para ver o que está acessível em 15 minutos.</p>
                    <a href="app.php" class="tool-link">Explorar <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="tool-card fade-in">
                    <div class="tool-icon">
                        <i class="fas fa-search-location"></i>
                    </div>
                    <h3>Localizador Ideal <span class="concept-tag">(Conceito)</span></h3>
                    <p>Encontre o local perfeito para viver com base nas suas necessidades específicas. Selecione os serviços importantes para si e descubra as áreas mais adequadas.</p>
                    <a href="ideal_finder.php" class="tool-link">Experimentar <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="tool-card fade-in">
                    <div class="tool-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3>Explorador de Dados</h3>
                    <p>Aceda a dados demográficos detalhados de qualquer localidade em Portugal. Compare dados dos Censos 2011 e 2021, e veja estatísticas sobre infraestruturas.</p>
                    <a href="location.php" class="tool-link">Analisar <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- How It Works Section -->
    <section id="how-it-works" class="section how-it-works">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Como Funciona</h2>
            </div>
            <div class="steps">
                <div class="step fade-in">
                    <div class="step-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Selecione um Local</h3>
                    <p>Pesquise um endereço ou clique no mapa para selecionar um ponto de partida. O sistema calcula automaticamente a área acessível.</p>
                </div>
                <div class="step fade-in">
                    <div class="step-icon">
                        <i class="fas fa-walking"></i>
                    </div>
                    <h3>Escolha o Modo de Transporte</h3>
                    <p>Defina se pretende deslocar-se a pé, de bicicleta ou de automóvel, e ajuste o tempo de deslocação de 5 a 30 minutos.</p>
                </div>
                <div class="step fade-in">
                    <div class="step-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3>Analise os Resultados</h3>
                    <p>Visualize a área acessível, todos os serviços disponíveis e estatísticas detalhadas sobre a acessibilidade do local selecionado.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Tech Section -->
    <section id="tech" class="section tech-section">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Tecnologia</h2>
            </div>
            <div class="tech-content fade-in">
                <p>O Minu15 utiliza tecnologias avançadas de mapeamento e análise geoespacial para fornecer informações precisas sobre acessibilidade urbana:</p>
                <div class="tech-grid">
                    <div class="tech-item">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Leaflet</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-route"></i>
                        <span>OpenRouteService</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-database"></i>
                        <span>GeoAPI.pt</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-layer-group"></i>
                        <span>OpenStreetMap</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-chart-area"></i>
                        <span>Turf.js</span>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-table"></i>
                        <span>Dados INE</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Screenshots Carousel Section -->
    <section id="screenshots" class="section">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Imagens do Projeto</h2>
            </div>
            <div class="carousel-container fade-in">
                <div class="carousel">
                    <div class="carousel-item">
                        <img src="images/landing/1.png" alt="Mapa interativo com isócrona" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Mapa Interativo</h3>
                            <p>Visualização de áreas acessíveis em 15 minutos</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/2.png" alt="Análise de pontos de interesse" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Análise de POIs</h3>
                            <p>Pontos de interesse dentro da área acessível</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/3.png" alt="Estatísticas de acessibilidade" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Estatísticas</h3>
                            <p>Análise detalhada de acessibilidade</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/4.png" alt="Localizador ideal" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Localizador Ideal</h3>
                            <p>Encontre o local perfeito para as suas necessidades</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/5.png" alt="Resultados do localizador ideal" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Resultados do localizador ideal</h3>
                            <p>Top 5 das áreas mais adequadas para viver</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/6.png" alt="Mapa com diferentes modos de transporte" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Dados demográficos</h3>
                            <p>Informações detalhadas sobre a população</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/7.png" alt="Filtros de pontos de interesse" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Dados demográficos com bordas de freguesias</h3>
                            <p>Opção para ver as bordas das freguesias</p>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/8.png" alt="Vista de detalhe de localidade" class="carousel-img">
                        <div class="carousel-caption">
                            <h3>Detalhes de Localidade</h3>
                            <p>Informações detalhadas sobre cada área</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-nav">
                    <div class="carousel-button prev-button">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                    <div class="carousel-button next-button">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                <div class="carousel-indicators">
                    <div class="carousel-indicator active" data-index="0"></div>
                    <div class="carousel-indicator" data-index="1"></div>
                    <div class="carousel-indicator" data-index="2"></div>
                    <div class="carousel-indicator" data-index="3"></div>
                    <div class="carousel-indicator" data-index="4"></div>
                    <div class="carousel-indicator" data-index="5"></div>
                    <div class="carousel-indicator" data-index="6"></div>
                    <div class="carousel-indicator" data-index="7"></div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- FAQ Section -->
    <section id="faq" class="section">
        <div class="container">
            <div class="section-title">
                <h2 class="fade-in">Perguntas Frequentes</h2>
            </div>
            <div class="fade-in">
                <div class="accordion">
                    <div class="accordion-header">O que é uma "Cidade de 15 Minutos"?</div>
                    <div class="accordion-content">
                        <p>O conceito de "Cidade de 15 Minutos" propõe que os residentes urbanos devem ter acesso a todas as suas necessidades básicas - trabalho, educação, saúde, cultura, lazer e compras - a uma distância de 15 minutos a pé ou de bicicleta da sua residência.</p>
                        <p>Este modelo de desenvolvimento urbano visa reduzir a dependência do automóvel, diminuir as emissões de carbono, melhorar a qualidade de vida e criar comunidades mais coesas e sustentáveis.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">Como o Minu15 calcula as áreas acessíveis?</div>
                    <div class="accordion-content">
                        <p>O Minu15 utiliza a API OpenRouteService para gerar isócronas - áreas que podem ser alcançadas dentro de um determinado período de tempo a partir de um ponto específico.</p>
                        <p>Para cada modo de transporte (a pé, de bicicleta ou de automóvel), a aplicação considera diferentes velocidades médias e restrições de circulação nas vias, garantindo cálculos precisos baseados em rotas reais.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">De onde vêm os dados sobre serviços e comodidades?</div>
                    <div class="accordion-content">
                        <p>Os dados de pontos de interesse (POIs) são obtidos principalmente do OpenStreetMap (OSM), uma base de dados geográficos colaborativa e de código aberto.</p>
                        <p>Periodicamente, importamos e processamos os dados mais recentes da Geofabrik, garantindo informações atualizadas sobre escolas, hospitais, supermercados e outros serviços essenciais.</p>
                        <p>Os dados demográficos são fornecidos pela GeoAPI.pt, que disponibiliza informações oficiais do Instituto Nacional de Estatística (INE) de Portugal.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">Como é calculada a pontuação de acessibilidade?</div>
                    <div class="accordion-content">
                        <p>A pontuação de acessibilidade é um cálculo ponderado baseado no número e variedade de pontos de interesse essenciais (supermercados, centros de saúde, escolas, etc.) alcançáveis no tempo selecionado pelo utilizador.</p>
                        <p>Cada tipo de serviço tem um peso diferente na pontuação final, e o utilizador pode personalizar estes pesos nas configurações da aplicação para refletir as suas prioridades pessoais.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">O Minu15 funciona em qualquer cidade de Portugal?</div>
                    <div class="accordion-content">
                        <p>Sim, o Minu15 funciona em qualquer localidade de Portugal continental e ilhas, onde existam dados disponíveis no OpenStreetMap e na GeoAPI.pt.</p>
                        <p>A qualidade e precisão dos resultados dependem da completude dos dados para cada região, sendo geralmente mais detalhados em áreas urbanas de maior dimensão.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="images/Minu15.png" alt="Minu15 Logo" class="logo" style="height: 80px; filter: brightness(0) invert(1);">
                    <p>Descubra a sua cidade em 15 minutos</p>
                </div>
                <div class="footer-links">
                    <div class="footer-links-column">
                        <h4>Ferramentas</h4>
                        <ul>
                            <li><a href="app.php">Mapa Interativo</a></li>
                            <li><a href="ideal_finder.php">Localizador Ideal</a></li>
                            <li><a href="location.php">Dados Demográficos</a></li>
                        </ul>
                    </div>
                    <div class="footer-links-column">
                        <h4>Recursos</h4>
                        <ul>
                            <li><a href="#about">Sobre o Projeto</a></li>
                            <li><a href="#features">Funcionalidades</a></li>
                            <li><a href="#faq">Perguntas Frequentes</a></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-social">
                    <h4>Siga-nos</h4>
                    <div class="social-links">
                        <a href="https://github.com/CruzGGC/Minu15" target="_blank" class="social-link"><i class="fab fa-github"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Minu15 | Desenvolvido por Guilherme Cruz, Pedro Sousa, Alexandra Dias e Rodrigo Ferreira</p>
                <p>Dados fornecidos por <a href="https://geoapi.pt" target="_blank">GeoAPI.pt</a> e <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top Button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>
    
    <!-- JavaScript for animations and scroll effects -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            // Back to top button visibility
            const backToTop = document.querySelector('.back-to-top');
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // Back to top functionality
        document.querySelector('.back-to-top').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Intersection Observer for fade-in and zoom-in animations
        const faders = document.querySelectorAll('.fade-in, .zoom-in');
        const appearOptions = {
            threshold: 0.15,
            rootMargin: "0px 0px -100px 0px"
        };
        
        const appearOnScroll = new IntersectionObserver(function(entries, appearOnScroll) {
            entries.forEach(entry => {
                if (!entry.isIntersecting) {
                    return;
                } else {
                    entry.target.classList.add('appear');
                    appearOnScroll.unobserve(entry.target);
                }
            });
        }, appearOptions);
        
        faders.forEach(fader => {
            appearOnScroll.observe(fader);
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Hero cursor effect
        const hero = document.querySelector('.hero');
        const cursorEffect = document.querySelector('.hero-cursor-effect');
        
        hero.addEventListener('mousemove', (e) => {
            const rect = hero.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            cursorEffect.style.opacity = '1';
            cursorEffect.style.left = x + 'px';
            cursorEffect.style.top = y + 'px';
        });
        
        hero.addEventListener('mouseleave', () => {
            cursorEffect.style.opacity = '0';
        });
        
        // FAQ Accordion
        const accordions = document.querySelectorAll('.accordion');
        
        accordions.forEach(accordion => {
            const header = accordion.querySelector('.accordion-header');
            
            header.addEventListener('click', () => {
                // Close all accordions
                accordions.forEach(item => {
                    if (item !== accordion) {
                        item.classList.remove('active');
                    }
                });
                
                // Toggle current accordion
                accordion.classList.toggle('active');
            });
        });
        
        // Screenshots Carousel functionality
        const carousel = document.querySelector('.carousel');
        const carouselItems = document.querySelectorAll('.carousel-item');
        const carouselNav = document.querySelector('.carousel-nav');
        const carouselIndicators = document.querySelectorAll('.carousel-indicator');
        let currentIndex = 0;
        
        // Show the first item by default
        carouselItems[currentIndex].classList.add('active');
        
        // Function to update carousel
        function updateCarousel() {
            // Remove active class from all items
            carouselItems.forEach(item => item.classList.remove('active'));
            
            // Add active class to the current item
            carouselItems[currentIndex].classList.add('active');
            
            // Update indicators
            carouselIndicators.forEach((indicator, index) => {
                if (index === currentIndex) {
                    indicator.classList.add('active');
                } else {
                    indicator.classList.remove('active');
                }
            });
        }
        
        // Next button functionality
        carouselNav.querySelector('.next-button').addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % carouselItems.length;
            updateCarousel();
        });
        
        // Previous button functionality
        carouselNav.querySelector('.prev-button').addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + carouselItems.length) % carouselItems.length;
            updateCarousel();
        });
        
        // Indicator functionality
        carouselIndicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel();
            });
        });
        
        // Auto slideshow
        let slideInterval = setInterval(() => {
            currentIndex = (currentIndex + 1) % carouselItems.length;
            updateCarousel();
        }, 5000);
        
        // Pause slideshow when hovering over carousel
        const carouselContainer = document.querySelector('.carousel-container');
        carouselContainer.addEventListener('mouseenter', () => {
            clearInterval(slideInterval);
        });
        
        // Resume slideshow when mouse leaves carousel
        carouselContainer.addEventListener('mouseleave', () => {
            slideInterval = setInterval(() => {
                currentIndex = (currentIndex + 1) % carouselItems.length;
                updateCarousel();
            }, 5000);
        });
    </script>
</body>
</html>