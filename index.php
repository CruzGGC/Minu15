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
                    <li><a href="#about" class="nav-link">Sobre</a></li>
                    <li><a href="#features" class="nav-link">Funcionalidades</a></li>
                    <li><a href="#how-it-works" class="nav-link">Como Funciona</a></li>
                    <li><a href="#screenshots" class="nav-link">Capturas</a></li>
                    <li><a href="#faq" class="nav-link">FAQ</a></li>
                    <li><a href="app.php" class="nav-link nav-cta">Explorador</a></li>
                    <li><a href="ideal_finder.php" class="nav-link nav-cta">Localizador</a></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section with Parallax -->
    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="zoom-in">Minu15 - Cidade em 15 Minutos</h1>
            <p class="zoom-in">Descubra tudo o que está acessível a 15 minutos de onde você está</p>
            <div class="cta-buttons zoom-in">
                <a href="app.php" class="cta-button">
                    <i class="fas fa-map"></i>
                    Explorar Mapa
                </a>
                <a href="ideal_finder.php" class="cta-button cta-secondary">
                    <i class="fas fa-search-location"></i>
                    Localizador Ideal
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
                <p>O Minu15 é uma aplicação web que permite visualizar e analisar a acessibilidade urbana baseada no conceito de "Cidade de 15 Minutos" - um modelo de desenvolvimento urbano onde os cidadãos podem aceder a todas as suas necessidades básicas dentro de um raio de 15 minutos a pé ou de bicicleta da sua residência.</p>
                <p>Através do uso de dados geoespaciais e análise de proximidade, o Minu15 ajuda a identificar áreas bem servidas e áreas com potencial para melhoria, promovendo cidades mais sustentáveis, acessíveis e habitáveis.</p>
            </div>
        </div>
    </section>
    
    <!-- Stats Counter Section -->
    <section class="counter-section">
        <div class="container">
            <div class="counter-grid">
                <div class="counter-item">
                    <div class="counter-value"><span class="count" data-target="15">0</span>+</div>
                    <div class="counter-label">Cidades Analisadas</div>
                </div>
                <div class="counter-item">
                    <div class="counter-value"><span class="count" data-target="10000">0</span>+</div>
                    <div class="counter-label">Usuários Ativos</div>
                </div>
                <div class="counter-item">
                    <div class="counter-value"><span class="count" data-target="500000">0</span>+</div>
                    <div class="counter-label">Pesquisas Realizadas</div>
                </div>
                <div class="counter-item">
                    <div class="counter-value"><span class="count" data-target="25000">0</span>+</div>
                    <div class="counter-label">POIs Mapeados</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Parallax Section 1 -->
    <section class="parallax">
        <div class="parallax-overlay"></div>
        <div class="parallax-content">
            <h2 class="fade-in">Cidades mais Vivíveis e Sustentáveis</h2>
            <p class="fade-in">Promovendo mobilidade ativa, reduzindo deslocações desnecessárias e melhorando a qualidade de vida</p>
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
                        <img src="images/landing/mapa_interativo.png" alt="Mapeamento Interativo" 
                             onerror="this.style.display='none'; this.parentElement.style.backgroundColor='#4361ee';" 
                             style="width: 100%; height: 200px; object-fit: cover;">
                    </div>
                    <div class="feature-content">
                        <h3>Mapeamento Interativo</h3>
                        <p>Visualize todas as amenidades e serviços num mapa interativo e personalizável.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <img src="images/landing/acessibilidade.png" alt="Análise de Acessibilidade" 
                             onerror="this.style.display='none'; this.parentElement.style.backgroundColor='#3a0ca3';" 
                             style="width: 100%; height: 200px; object-fit: cover;">
                    </div>
                    <div class="feature-content">
                        <h3>Análise de Acessibilidade</h3>
                        <p>Calcule e visualize áreas acessíveis a pé, de bicicleta ou de carro em diferentes intervalos de tempo.</p>
                    </div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-img">
                        <img src="images/landing/estatisticas.png" alt="Estatísticas Detalhadas" 
                             onerror="this.style.display='none'; this.parentElement.style.backgroundColor='#4cc9f0';" 
                             style="width: 100%; height: 200px; object-fit: cover;">
                    </div>
                    <div class="feature-content">
                        <h3>Estatísticas Detalhadas</h3>
                        <p>Obtenha estatísticas sobre serviços públicos, transporte, comércio e áreas verdes na sua vizinhança.</p>
                    </div>
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
                    <p>Pesquise um endereço ou clique no mapa para selecionar um ponto de partida.</p>
                </div>
                <div class="step fade-in">
                    <div class="step-icon">
                        <i class="fas fa-walking"></i>
                    </div>
                    <h3>Escolha o Modo de Transporte</h3>
                    <p>Defina se pretende deslocar-se a pé, de bicicleta ou de carro.</p>
                </div>
                <div class="step fade-in">
                    <div class="step-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h3>Calcule e Explore</h3>
                    <p>Visualize a área acessível e todos os serviços disponíveis no tempo definido.</p>
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
                        <img src="images/landing/1.png" alt="Captura de Ecrã 1" class="carousel-img"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="carousel-img fallback-content" style="display: none; background-color: #4361ee; justify-content: center; align-items: center; color: white;">
                            <div>
                                <i class="fas fa-map-marked-alt" style="font-size: 5rem; margin-bottom: 20px;"></i>
                                <h3>Vista do Mapa</h3>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/2.png" alt="Captura de Ecrã 2" class="carousel-img"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="carousel-img fallback-content" style="display: none; background-color: #3a0ca3; justify-content: center; align-items: center; color: white;">
                            <div>
                                <i class="fas fa-chart-pie" style="font-size: 5rem; margin-bottom: 20px;"></i>
                                <h3>Análise de Dados</h3>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/3.png" alt="Captura de Ecrã 3" class="carousel-img"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="carousel-img fallback-content" style="display: none; background-color: #4cc9f0; justify-content: center; align-items: center; color: white;">
                            <div>
                                <i class="fas fa-ruler-combined" style="font-size: 5rem; margin-bottom: 20px;"></i>
                                <h3>Medição de Distâncias</h3>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <img src="images/landing/4.png" alt="Captura de Ecrã 4" class="carousel-img"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="carousel-img fallback-content" style="display: none; background-color: #7209b7; justify-content: center; align-items: center; color: white;">
                            <div>
                                <i class="fas fa-search-location" style="font-size: 5rem; margin-bottom: 20px;"></i>
                                <h3>Pesquisa de Locais</h3>
                            </div>
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
                        <p>Para cada modo de transporte (a pé, de bicicleta ou de carro), a aplicação considera diferentes velocidades médias e restrições de circulação nas vias, garantindo cálculos precisos baseados em rotas reais.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">De onde vêm os dados sobre serviços e amenidades?</div>
                    <div class="accordion-content">
                        <p>Os dados de pontos de interesse (POIs) são obtidos principalmente do OpenStreetMap (OSM), uma base de dados geográficos colaborativa e de código aberto.</p>
                        <p>Periodicamente, importamos e processamos os dados mais recentes da Geofabrik, garantindo informações atualizadas sobre escolas, hospitais, supermercados e outros serviços essenciais.</p>
                    </div>
                </div>
                
                <div class="accordion">
                    <div class="accordion-header">O Minu15 funciona em qualquer cidade?</div>
                    <div class="accordion-content">
                        <p>Sim, o Minu15 pode funcionar em qualquer cidade onde existam dados disponíveis no OpenStreetMap. A qualidade e precisão dos resultados dependem da completude dos dados para cada região.</p>
                        <p>Atualmente, focamo-nos nas cidades portuguesas, mas a plataforma está preparada para ser expandida para outras regiões.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <img src="images/Minu15.png" alt="Minu15 Logo" class="logo" style="height: 80px; filter: brightness(0) invert(1);">
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-github"></i></a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Minu15 | Guilherme Cruz | Pedro Sousa | Alexandra Dias | Rodrigo Ferreira</a></p>
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
        
        // Animated counters
        function animateCounter() {
            const counters = document.querySelectorAll('.count');
            const speed = 200;  // Lower is faster
            
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const increment = Math.ceil(target / speed);
                let currentValue = 0;
                
                const updateCounter = () => {
                    if (currentValue < target) {
                        currentValue += increment;
                        if (currentValue > target) currentValue = target;
                        counter.innerText = new Intl.NumberFormat().format(currentValue);
                        setTimeout(updateCounter, 1);
                    }
                };
                
                updateCounter();
            });
        }
        
        // Start counter animation when the section is in view
        const counterSection = document.querySelector('.counter-section');
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        });
        
        counterObserver.observe(counterSection);
        
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
        
        // Image Carousel
        const carousel = document.querySelector('.carousel');
        const carouselItems = document.querySelectorAll('.carousel-item');
        const indicators = document.querySelectorAll('.carousel-indicator');
        const prevButton = document.querySelector('.prev-button');
        const nextButton = document.querySelector('.next-button');
        let currentIndex = 0;
        const totalItems = carouselItems.length;
        
        function updateCarousel() {
            carousel.style.transform = `translateX(-${currentIndex * 100}%)`;
            
            // Update indicators
            indicators.forEach((indicator, index) => {
                if (index === currentIndex) {
                    indicator.classList.add('active');
                } else {
                    indicator.classList.remove('active');
                }
            });
        }
        
        // Set up indicator clicks
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentIndex = index;
                updateCarousel();
            });
        });
        
        // Set up prev/next buttons
        prevButton.addEventListener('click', () => {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        });
        
        nextButton.addEventListener('click', () => {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        });
        
        // Automatic slideshow
        setInterval(() => {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }, 5000);
    </script>
</body>
</html>