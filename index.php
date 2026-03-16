<?php
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /admin/');
    } else {
        header('Location: /user/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultra Harvest Global - Copy Forex Trades. Harvest Profits Fast.</title>
    <meta name="description" content="Choose a package, press Copy, and let your money grow with Ultra Harvest Global">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        
        * { 
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Hero Section with Background Image */
        .hero-bg {
            position: relative;
            background-image: url('/Trading Bg Image.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        /* Mobile: Make hero compact */
        @media (max-width: 640px) {
            .hero-bg {
                min-height: auto;
                background-position: center center;
            }
        }
        
        /* Tablet and above */
        @media (min-width: 641px) {
            .hero-bg {
                min-height: 100vh;
            }
        }
        
        /* Dark overlay for better text visibility */
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            z-index: 1;
        }
        
        /* Subtle gradient overlay for depth */
        .hero-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(251, 191, 36, 0.15) 100%);
            z-index: 2;
        }
        
        .hero-content {
            position: relative;
            z-index: 10;
        }
        
        /* Typography with strong visibility */
        .hero-title {
            font-weight: 800;
            line-height: 1.1;
            text-shadow: 2px 4px 8px rgba(0, 0, 0, 0.8);
        }
        
        .hero-subtitle {
            text-shadow: 1px 2px 4px rgba(0, 0, 0, 0.8);
        }
        
        /* Accent color - Using emerald and yellow accents */
        .accent-emerald {
            color: #10b981;
        }
        
        .accent-yellow {
            color: #fbbf24;
        }
        
        /* Button styles */
        .btn-primary {
            background: #fbbf24;
            color: #000;
            font-weight: 700;
            padding: 0.875rem 1.75rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
            font-size: 0.95rem;
        }
        
        .btn-primary:hover {
            background: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.6);
        }
        
        .btn-secondary {
            border: 2px solid #10b981;
            color: #10b981;
            font-weight: 700;
            padding: 0.875rem 1.75rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            background: transparent;
            font-size: 0.95rem;
        }
        
        .btn-secondary:hover {
            background: #10b981;
            color: #000;
            transform: translateY(-2px);
        }
        
        @media (max-width: 640px) {
            .btn-primary, .btn-secondary {
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Card styles */
        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-5px);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        @media (max-width: 640px) {
            .feature-card {
                padding: 1.25rem;
            }
        }
        
        .package-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .package-card:hover {
            transform: translateY(-10px);
            border-color: rgba(251, 191, 36, 0.5);
        }
        
        @media (max-width: 640px) {
            .package-card {
                padding: 1.5rem;
            }
        }
        
        /* Mobile menu */
        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-menu.active {
            transform: translateX(0);
        }
        
        /* Testimonial slider */
        .testimonial-slide {
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s ease-in-out;
            position: absolute;
            width: 100%;
            top: 0;
            left: 0;
        }
        
        .testimonial-slide.active {
            opacity: 1;
            transform: translateX(0);
            position: relative;
        }
        
        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4); }
            50% { box-shadow: 0 6px 25px rgba(251, 191, 36, 0.7); }
        }
        
        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }

        /* Responsive Typography */
        @media (max-width: 640px) {
            .hero-title {
                font-size: 1.875rem;
                line-height: 1.2;
            }
            .hero-subtitle {
                font-size: 1rem;
                line-height: 1.5;
            }
        }
        
        @media (min-width: 641px) and (max-width: 1024px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-subtitle {
                font-size: 1.125rem;
            }
        }
        
        @media (min-width: 1025px) {
            .hero-title {
                font-size: 4.5rem;
            }
            .hero-subtitle {
                font-size: 1.5rem;
            }
        }

        /* Remove extra spacing */
        section {
            margin: 0;
        }

        /* Adjust container spacing for mobile */
        @media (max-width: 640px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        /* TradingView responsive container */
        .tradingview-widget-container {
            width: 100%;
            overflow: hidden;
        }

        /* Fix chart responsiveness on mobile */
        @media (max-width: 640px) {
            .chart-wrapper {
                height: 400px !important;
                overflow: hidden;
            }
            
            #tradingview_chart {
                height: 400px !important;
                width: 100% !important;
            }

            .ticker-wrapper {
                height: 350px !important;
                overflow: hidden;
            }

            /* Prevent horizontal scroll from TradingView widgets */
            .tradingview-widget-container iframe {
                max-width: 100% !important;
            }
        }

        /* Prevent any horizontal scroll */
        body {
            overflow-x: hidden;
        }

        html {
            overflow-x: hidden;
        }

        /* Ensure all containers don't overflow */
        * {
            max-width: 100%;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">

    <!-- Header / Hero Section -->
    <div class="hero-bg">
        <div class="hero-content">
            <!-- Navigation -->
            <nav class="container mx-auto px-4 py-4 sm:py-6">
                <div class="flex justify-between items-center">
                    <!-- Logo -->
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden ring-2 ring-emerald-500">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-white">Ultra Harvest</h1>
                            <p class="text-xs text-emerald-400">Global</p>
                        </div>
                    </div>
                    
                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="#live-charts" class="text-gray-200 hover:text-emerald-400 transition font-medium">Live Charts</a>
                        <a href="#how-it-works" class="text-gray-200 hover:text-emerald-400 transition font-medium">How It Works</a>
                        <a href="#packages" class="text-gray-200 hover:text-emerald-400 transition font-medium">Packages</a>
                        <a href="/login.php" class="text-emerald-400 hover:text-emerald-300 transition font-semibold">Login</a>
                    </div>
                    
                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" class="md:hidden text-white text-2xl">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Mobile Menu -->
                <div id="mobile-menu" class="mobile-menu fixed top-0 right-0 h-full w-64 bg-gray-900 z-50 md:hidden shadow-2xl">
                    <div class="p-6">
                        <div class="flex justify-end mb-8">
                            <button id="mobile-menu-close" class="text-white text-2xl">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="space-y-6">
                            <a href="#live-charts" class="block text-gray-300 hover:text-emerald-400 transition text-lg mobile-menu-link">Live Charts</a>
                            <a href="#how-it-works" class="block text-gray-300 hover:text-emerald-400 transition text-lg mobile-menu-link">How It Works</a>
                            <a href="#packages" class="block text-gray-300 hover:text-emerald-400 transition text-lg mobile-menu-link">Packages</a>
                            <a href="/login.php" class="block text-emerald-400 hover:text-emerald-300 transition text-lg mobile-menu-link">Login</a>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu Overlay -->
                <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-40 hidden md:hidden"></div>
            </nav>

            <!-- Hero Content -->
            <div class="container mx-auto px-4 py-8 sm:py-12 md:py-16 lg:py-20">
                <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
                    <!-- Left Side - Main Content -->
                    <div class="text-center lg:text-left">
                        <h1 class="hero-title text-white mb-4 sm:mb-6">
                            Copy Forex Trades.
                            <span class="accent-emerald block">Harvest Profits</span>
                            <span class="accent-yellow">Fast.</span>
                        </h1>
                        <p class="hero-subtitle text-base sm:text-lg lg:text-xl text-gray-100 mb-6 sm:mb-8 max-w-2xl mx-auto lg:mx-0">
                            Choose a package, press Copy, and let your money grow.
                        </p>
                        
                        <!-- CTA Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center lg:justify-start max-w-md mx-auto lg:mx-0 pb-8 sm:pb-0">
                            <a href="/register.php" class="btn-primary pulse-glow">
                                <i class="fas fa-rocket"></i>
                                <span>Register Now</span>
                            </a>
                            <a href="#how-it-works" class="btn-secondary">
                                <i class="fas fa-play-circle"></i>
                                <span>Learn How It Works</span>
                            </a>
                        </div>
                    </div>

                    <!-- Right Side - Visual Elements (Desktop Only) -->
                    <div class="hidden lg:grid grid-cols-2 gap-6">
                        <!-- Forex Chart Card -->
                        <div class="feature-card text-center float-animation">
                            <div class="w-16 h-16 mx-auto mb-4 bg-emerald-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-2xl text-white"></i>
                            </div>
                            <div class="h-20 flex items-end justify-between space-x-1 mb-3">
                                <div class="bg-emerald-400 w-3 h-8 rounded"></div>
                                <div class="bg-emerald-500 w-3 h-16 rounded"></div>
                                <div class="bg-emerald-400 w-3 h-12 rounded"></div>
                                <div class="bg-emerald-600 w-3 h-20 rounded"></div>
                                <div class="bg-emerald-400 w-3 h-10 rounded"></div>
                            </div>
                            <p class="text-sm text-gray-300">Live Forex Data</p>
                        </div>

                        <!-- Wealth Growth Card -->
                        <div class="feature-card text-center float-animation" style="animation-delay: -2s;">
                            <div class="w-16 h-16 mx-auto mb-4 bg-yellow-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-seedling text-2xl text-white"></i>
                            </div>
                            <div class="flex justify-center space-x-1 mb-3">
                                <div class="w-2 h-8 bg-yellow-400 rounded-full"></div>
                                <div class="w-2 h-10 bg-yellow-500 rounded-full"></div>
                                <div class="w-2 h-6 bg-yellow-400 rounded-full"></div>
                                <div class="w-2 h-12 bg-yellow-600 rounded-full"></div>
                                <div class="w-2 h-8 bg-yellow-400 rounded-full"></div>
                            </div>
                            <p class="text-sm text-gray-300">Growing Wealth</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Forex Charts Section -->
    <section id="live-charts" class="py-12 sm:py-16 lg:py-20 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 text-white">
                    Live <span class="accent-emerald">Forex</span> Markets
                </h2>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300">Real-time forex trading data at your fingertips</p>
            </div>

            <!-- TradingView Widget -->
            <div class="max-w-7xl mx-auto">
                <div class="bg-gray-900 rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl border border-gray-700">
                    <!-- Chart Container -->
                    <div class="chart-wrapper">
                        <div id="tradingview_chart"></div>
                    </div>
                </div>

                <!-- Major Forex Pairs Ticker -->
                <div class="mt-6 sm:mt-8 bg-gray-900 rounded-xl p-4 sm:p-6 border border-gray-700">
                    <h3 class="text-lg sm:text-xl font-bold text-white mb-3 sm:mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-bar text-emerald-500"></i>
                        Major Forex Pairs
                    </h3>
                    <div class="ticker-wrapper">
                        <div id="tradingview_ticker"></div>
                    </div>
                </div>
            </div>

            <!-- Chart Info Cards -->
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mt-8 sm:mt-12 max-w-5xl mx-auto">
                <div class="feature-card text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-xl sm:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Real-Time Data</h3>
                    <p class="text-sm sm:text-base text-gray-400">Live market updates every second</p>
                </div>
                
                <div class="feature-card text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-yellow-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-globe text-xl sm:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Global Markets</h3>
                    <p class="text-sm sm:text-base text-gray-400">Trade major currency pairs worldwide</p>
                </div>
                
                <div class="feature-card text-center sm:col-span-2 md:col-span-1">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-xl sm:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Professional Tools</h3>
                    <p class="text-sm sm:text-base text-gray-400">Advanced charting and indicators</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-12 sm:py-16 bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 lg:gap-8">
                <div class="text-center group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-lg sm:text-xl lg:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-sm sm:text-base lg:text-lg mb-1 sm:mb-2 text-white">Secure & Transparent</h3>
                    <p class="text-gray-400 text-xs sm:text-sm">Bank-level security with full transparency</p>
                </div>
                
                <div class="text-center group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 mx-auto mb-3 sm:mb-4 bg-yellow-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-clock text-lg sm:text-xl lg:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-sm sm:text-base lg:text-lg mb-1 sm:mb-2 text-white">ROI in 24H—7D</h3>
                    <p class="text-gray-400 text-xs sm:text-sm">Fast returns on your investments</p>
                </div>
                
                <div class="text-center group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-handshake text-lg sm:text-xl lg:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-sm sm:text-base lg:text-lg mb-1 sm:mb-2 text-white">Simple Copy System</h3>
                    <p class="text-gray-400 text-xs sm:text-sm">One-click trading made easy</p>
                </div>
                
                <div class="text-center group">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 lg:w-16 lg:h-16 mx-auto mb-3 sm:mb-4 bg-yellow-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform">
                        <i class="fas fa-wallet text-lg sm:text-xl lg:text-2xl text-white"></i>
                    </div>
                    <h3 class="font-semibold text-sm sm:text-base lg:text-lg mb-1 sm:mb-2 text-white">Fast Withdrawals</h3>
                    <p class="text-gray-400 text-xs sm:text-sm">Quick access to your profits</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="packages" class="py-12 sm:py-16 lg:py-20 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="text-center mb-10 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 text-white">
                    Choose Your <span class="accent-emerald">Growth Path</span>
                </h2>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300">Unlock exclusive trading packages designed for every investor</p>
            </div>

            <!-- Packages Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8 sm:mb-12">
                <?php
                $packages = ['Seed', 'Sprout', 'Growth', 'Harvest', 'Golden Yield', 'Elite'];
                $icons = ['🌱', '🌿', '🌳', '🌾', '💰', '💎'];
                
                for ($i = 0; $i < 6; $i++): ?>
                <div class="package-card relative">
                    <!-- Lock Overlay -->
                    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm rounded-2xl flex items-center justify-center z-10">
                        <div class="text-center">
                            <i class="fas fa-lock text-3xl sm:text-4xl text-yellow-500 mb-3 sm:mb-4"></i>
                            <p class="text-white font-semibold text-sm sm:text-base">Sign up to unlock</p>
                            <p class="text-gray-300 text-xs sm:text-sm">package details</p>
                        </div>
                    </div>
                    
                    <div class="text-4xl sm:text-5xl mb-3 sm:mb-4 text-center"><?php echo $icons[$i]; ?></div>
                    <h3 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-4 text-center text-white"><?php echo $packages[$i]; ?></h3>
                    <div class="h-24 sm:h-32 flex items-center justify-center">
                        <div class="space-y-2 opacity-30 w-full">
                            <div class="h-3 sm:h-4 bg-white/20 rounded w-3/4 mx-auto"></div>
                            <div class="h-3 sm:h-4 bg-white/20 rounded w-1/2 mx-auto"></div>
                            <div class="h-3 sm:h-4 bg-white/20 rounded w-2/3 mx-auto"></div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- CTA Button -->
            <div class="text-center">
                <a href="/register.php" class="btn-primary text-base sm:text-lg pulse-glow">
                    <i class="fas fa-unlock"></i>
                    <span>Create Account to Unlock</span>
                </a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-12 sm:py-16 lg:py-20 bg-gray-900">
        <div class="container mx-auto px-4">
            <div class="text-center mb-10 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 text-white">How It Works</h2>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300">Three simple steps to start growing your wealth</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 sm:gap-10 lg:gap-12">
                <div class="text-center group">
                    <div class="relative mb-6 sm:mb-8">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto bg-emerald-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform mb-4 shadow-lg">
                            <i class="fas fa-user-plus text-2xl sm:text-3xl text-white"></i>
                        </div>
                        <div class="absolute top-0 right-1/2 transform translate-x-10 sm:translate-x-12 w-7 h-7 sm:w-8 sm:h-8 bg-yellow-500 rounded-full flex items-center justify-center text-black font-bold text-sm sm:text-base">1</div>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-4 text-white">Register Account</h3>
                    <p class="text-sm sm:text-base text-gray-400 leading-relaxed">Create your free account in less than 2 minutes. Secure, fast, and completely transparent.</p>
                </div>

                <div class="text-center group">
                    <div class="relative mb-6 sm:mb-8">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto bg-yellow-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform mb-4 shadow-lg">
                            <i class="fas fa-credit-card text-2xl sm:text-3xl text-white"></i>
                        </div>
                        <div class="absolute top-0 right-1/2 transform translate-x-10 sm:translate-x-12 w-7 h-7 sm:w-8 sm:h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm sm:text-base">2</div>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-4 text-white">Choose Package</h3>
                    <p class="text-sm sm:text-base text-gray-400 leading-relaxed">Select the perfect trading package that matches your investment goals and risk appetite.</p>
                </div>

                <div class="text-center group sm:col-span-2 lg:col-span-1">
                    <div class="relative mb-6 sm:mb-8">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto bg-emerald-500 rounded-full flex items-center justify-center group-hover:scale-110 transition-transform mb-4 shadow-lg">
                            <i class="fas fa-chart-line text-2xl sm:text-3xl text-white"></i>
                        </div>
                        <div class="absolute top-0 right-1/2 transform translate-x-10 sm:translate-x-12 w-7 h-7 sm:w-8 sm:h-8 bg-yellow-500 rounded-full flex items-center justify-center text-black font-bold text-sm sm:text-base">3</div>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-4 text-white">Copy Trade & Get ROI</h3>
                    <p class="text-sm sm:text-base text-gray-400 leading-relaxed">Sit back and watch your investment grow with automated trading and guaranteed returns.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-12 sm:py-16 lg:py-20 bg-gray-800">
        <div class="container mx-auto px-4">
            <div class="text-center mb-8 sm:mb-12">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 text-white">
                    What Our <span class="accent-emerald">Traders</span> Say
                </h2>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300">Real success stories from our community</p>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="testimonial-container relative min-h-[280px] sm:min-h-[300px]">
                    <!-- Testimonial 1 -->
                    <div class="testimonial-slide active feature-card">
                        <div class="text-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 bg-emerald-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-xl sm:text-2xl text-white"></i>
                            </div>
                            <blockquote class="text-base sm:text-lg lg:text-xl font-medium mb-4 sm:mb-6 text-gray-200 leading-relaxed">
                                "I started with Seed at KSh 500 and got returns in 24 hours. So simple! The platform is incredibly user-friendly and the profits are exactly as promised."
                            </blockquote>
                            <div>
                                <p class="font-semibold text-base sm:text-lg text-white">Sarah K.</p>
                                <p class="text-sm sm:text-base text-emerald-400">Nairobi, Kenya</p>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="testimonial-slide feature-card">
                        <div class="text-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 bg-yellow-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-xl sm:text-2xl text-white"></i>
                            </div>
                            <blockquote class="text-base sm:text-lg lg:text-xl font-medium mb-4 sm:mb-6 text-gray-200 leading-relaxed">
                                "Three months with Ultra Harvest and I've already doubled my initial investment. The Growth package delivered exactly what was promised!"
                            </blockquote>
                            <div>
                                <p class="font-semibold text-base sm:text-lg text-white">Michael O.</p>
                                <p class="text-sm sm:text-base text-yellow-400">Mombasa, Kenya</p>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="testimonial-slide feature-card">
                        <div class="text-center">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 bg-emerald-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-xl sm:text-2xl text-white"></i>
                            </div>
                            <blockquote class="text-base sm:text-lg lg:text-xl font-medium mb-4 sm:mb-6 text-gray-200 leading-relaxed">
                                "As a busy professional, I love the copy trading feature. Set it and forget it - my money works while I sleep. Highly recommended!"
                            </blockquote>
                            <div>
                                <p class="font-semibold text-base sm:text-lg text-white">Grace M.</p>
                                <p class="text-sm sm:text-base text-emerald-400">Kisumu, Kenya</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Testimonial Navigation -->
                <div class="flex justify-center mt-6 sm:mt-8 space-x-3">
                    <button class="testimonial-dot w-3 h-3 rounded-full bg-emerald-500 transition-all duration-300" data-slide="0"></button>
                    <button class="testimonial-dot w-3 h-3 rounded-full bg-gray-500 transition-all duration-300" data-slide="1"></button>
                    <button class="testimonial-dot w-3 h-3 rounded-full bg-gray-500 transition-all duration-300" data-slide="2"></button>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Banner -->
    <section class="py-12 sm:py-16 lg:py-20 bg-yellow-500">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-6xl font-bold text-black mb-4 sm:mb-6">
                Your harvest begins today
            </h2>
            <p class="text-base sm:text-lg lg:text-xl text-black/80 mb-6 sm:mb-8 max-w-2xl mx-auto">
                Join thousands of successful traders who are already growing their wealth with Ultra Harvest Global
            </p>
            <a href="/register.php" class="inline-flex items-center gap-2 sm:gap-3 px-8 sm:px-12 py-4 sm:py-5 bg-emerald-600 text-white font-bold text-base sm:text-lg lg:text-xl rounded-full hover:bg-emerald-700 transform hover:scale-105 transition-all duration-300 shadow-2xl">
                <i class="fas fa-seedling"></i>
                <span>Register Now</span>
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-8 sm:py-12 bg-gray-900 border-t border-gray-800">
        <div class="container mx-auto px-4">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <div>
                    <div class="flex items-center space-x-2 sm:space-x-3 mb-3 sm:mb-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden ring-2 ring-emerald-500">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-white">Ultra Harvest</h1>
                            <p class="text-xs sm:text-sm text-emerald-400">Global</p>
                        </div>
                    </div>
                    <p class="text-base sm:text-lg font-medium mb-3 sm:mb-4 text-gray-400">Growing Wealth Together</p>
                    <p class="text-sm sm:text-base text-gray-500">Your trusted partner in forex trading and wealth creation.</p>
                </div>
                
                <div class="sm:text-center">
                    <h3 class="font-semibold text-base sm:text-lg mb-3 sm:mb-4 text-white">Quick Links</h3>
                    <div class="space-y-2">
                        <a href="/terms.php" class="block text-sm sm:text-base text-gray-400 hover:text-emerald-400 transition">Terms & Conditions</a>
                        <a href="/privacy.php" class="block text-sm sm:text-base text-gray-400 hover:text-emerald-400 transition">Privacy Policy</a>
                        <a href="/help.php" class="block text-sm sm:text-base text-gray-400 hover:text-emerald-400 transition">Help Center</a>
                    </div>
                </div>
                
                <div class="sm:col-span-2 lg:col-span-1 lg:text-right">
                    <h3 class="font-semibold text-base sm:text-lg mb-3 sm:mb-4 text-white">Connect With Us</h3>
                    <div class="flex lg:justify-end space-x-3 sm:space-x-4 mb-3 sm:mb-4">
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-facebook-f text-white"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-twitter text-white"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-instagram text-white"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-whatsapp text-white"></i>
                        </a>
                    </div>
                    <p class="text-gray-500 text-xs sm:text-sm">
                        © <?php echo date('Y'); ?> Ultra Harvest Global. All rights reserved.
                    </p>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-6 pt-6 text-center">
                <p class="text-gray-500 text-xs mt-4 opacity-0">
    Developed by <a href="https://zurihub.co.ke" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 transition">Zurihub</a>
</p>

            </div>
        </div>
    </footer>

    <!-- Floating WhatsApp Button -->
    <a href="https://whatsapp.com/channel/0029Vb6ZWta17En4fWE1u22P" 
       target="_blank" 
       rel="noopener noreferrer"
       class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 w-14 h-14 sm:w-16 sm:h-16 bg-green-500 rounded-full flex items-center justify-center shadow-2xl hover:scale-110 transition-all duration-300 group"
       style="box-shadow: 0 4px 20px rgba(34, 197, 94, 0.5);">
        <i class="fab fa-whatsapp text-white text-2xl sm:text-3xl"></i>
        <span class="hidden sm:block absolute right-full mr-3 bg-gray-900 text-white px-4 py-2 rounded-lg text-sm whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none shadow-lg">
            Join Our Channel
        </span>
    </a>

    <!-- TradingView Widget Scripts -->
    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
    <script type="text/javascript">
        // Check screen size
        const isMobile = window.innerWidth < 640;
        
        // Advanced Chart Widget
        new TradingView.widget({
            "width": "100%",
            "height": isMobile ? 400 : 600,
            "symbol": "FX:EURUSD",
            "interval": "D",
            "timezone": "Africa/Nairobi",
            "theme": "dark",
            "style": "1",
            "locale": "en",
            "toolbar_bg": "#f1f3f6",
            "enable_publishing": false,
            "allow_symbol_change": true,
            "container_id": "tradingview_chart",
            "hide_top_toolbar": false,
            "hide_legend": false,
            "save_image": false,
            "backgroundColor": "rgba(17, 24, 39, 1)",
            "gridColor": "rgba(55, 65, 81, 0.3)",
            "studies": [
                "MASimple@tv-basicstudies"
            ]
        });
    </script>
    
    <script type="text/javascript">
        // Ticker Widget - Using different approach for mobile
        const tickerSymbols = [
            {
                "proName": "FX_IDC:EURUSD",
                "title": "EUR/USD"
            },
            {
                "proName": "FX_IDC:GBPUSD",
                "title": "GBP/USD"
            },
            {
                "proName": "FX_IDC:USDJPY",
                "title": "USD/JPY"
            },
            {
                "proName": "FX_IDC:USDCHF",
                "title": "USD/CHF"
            },
            {
                "proName": "FX_IDC:AUDUSD",
                "title": "AUD/USD"
            },
            {
                "proName": "FX_IDC:USDCAD",
                "title": "USD/CAD"
            }
        ];

        new TradingView.widget({
            "width": "100%",
            "height": isMobile ? 350 : 400,
            "symbolsGroups": [
                {
                    "name": "Forex",
                    "originalName": "Forex",
                    "symbols": tickerSymbols
                }
            ],
            "showSymbolLogo": true,
            "colorTheme": "dark",
            "isTransparent": false,
            "locale": "en",
            "container_id": "tradingview_ticker"
        });
    </script>

    <!-- JavaScript -->
    <script>
        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
        const mobileMenuLinks = document.querySelectorAll('.mobile-menu-link');

        function openMobileMenu() {
            mobileMenu.classList.add('active');
            mobileMenuOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('active');
            mobileMenuOverlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        mobileMenuBtn.addEventListener('click', openMobileMenu);
        mobileMenuClose.addEventListener('click', closeMobileMenu);
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        mobileMenuLinks.forEach(link => link.addEventListener('click', closeMobileMenu));

        // Testimonial Slider
        const testimonialSlides = document.querySelectorAll('.testimonial-slide');
        const testimonialDots = document.querySelectorAll('.testimonial-dot');
        let currentSlide = 0;

        function showSlide(slideIndex) {
            testimonialSlides.forEach((slide, index) => {
                slide.classList.remove('active');
                if (index === slideIndex) {
                    slide.classList.add('active');
                }
            });

            testimonialDots.forEach((dot, index) => {
                if (index === slideIndex) {
                    dot.classList.remove('bg-gray-500');
                    dot.classList.add('bg-emerald-500');
                } else {
                    dot.classList.remove('bg-emerald-500');
                    dot.classList.add('bg-gray-500');
                }
            });
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % testimonialSlides.length;
            showSlide(currentSlide);
        }

        testimonialDots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                showSlide(currentSlide);
            });
        });

        setInterval(nextSlide, 5000);

        // Smooth Scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Close mobile menu on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>