<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Ultra Harvest Global</title>
    <meta name="description" content="Read Ultra Harvest Global's privacy policy and data protection practices">
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

        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
        }

        .accent-emerald {
            color: #10b981;
        }
        
        .accent-yellow {
            color: #fbbf24;
        }

        .privacy-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            transition: all 0.3s ease;
        }

        .privacy-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border-left: 4px solid #10b981;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .section-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }

        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-menu.active {
            transform: translateX(0);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

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
        }
        
        .btn-primary:hover {
            background: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.6);
        }

        .table-of-contents {
            position: sticky;
            top: 100px;
        }

        @media (max-width: 1024px) {
            .table-of-contents {
                position: relative;
                top: 0;
            }
        }

        .toc-item {
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .toc-item:hover, .toc-item.active {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: #10b981;
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(251, 191, 36, 0.1) 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 0.75rem;
            padding: 1.25rem;
        }

        .data-category {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            padding: 1.25rem;
            transition: all 0.3s ease;
        }

        .data-category:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-3px);
        }

        html {
            scroll-behavior: smooth;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar-thumb {
            background: #10b981;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #059669;
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="text-white">

    <!-- Navigation -->
    <nav class="container mx-auto px-4 py-4 sm:py-6 sticky top-0 z-40 backdrop-blur-md bg-gray-900/80 rounded-b-2xl">
        <div class="flex justify-between items-center">
            <!-- Logo -->
            <a href="/" class="flex items-center space-x-2 sm:space-x-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden ring-2 ring-emerald-500">
                    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" class="w-full h-full object-cover">
                </div>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-white">Ultra Harvest</h1>
                    <p class="text-xs text-emerald-400">Global</p>
                </div>
            </a>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="/" class="text-gray-200 hover:text-emerald-400 transition font-medium">Home</a>
                <a href="/terms.php" class="text-gray-200 hover:text-emerald-400 transition font-medium">Terms</a>
                <a href="/help.php" class="text-gray-200 hover:text-emerald-400 transition font-medium">Help</a>
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
                    <a href="/" class="block text-gray-300 hover:text-emerald-400 transition text-lg">Home</a>
                    <a href="/terms.php" class="block text-gray-300 hover:text-emerald-400 transition text-lg">Terms</a>
                    <a href="/help.php" class="block text-gray-300 hover:text-emerald-400 transition text-lg">Help</a>
                    <a href="/login.php" class="block text-emerald-400 hover:text-emerald-300 transition text-lg">Login</a>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-70 z-40 hidden md:hidden"></div>
    </nav>

    <!-- Header Section -->
    <section class="py-12 sm:py-16 lg:py-20">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-4xl mx-auto fade-in-up">
                <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-6 bg-gradient-to-br from-emerald-500 to-blue-500 rounded-full flex items-center justify-center shadow-2xl">
                    <i class="fas fa-shield-alt text-3xl sm:text-4xl text-white"></i>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold mb-4 sm:mb-6">
                    Privacy <span class="accent-emerald">Policy</span>
                </h1>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300 mb-6">
                    Your privacy and data security are our top priorities
                </p>
                <div class="flex flex-wrap gap-3 justify-center items-center">
                    <div class="security-badge">
                        <i class="fas fa-lock text-emerald-400"></i>
                        <span class="text-white">256-bit Encryption</span>
                    </div>
                    <div class="security-badge">
                        <i class="fas fa-user-shield text-emerald-400"></i>
                        <span class="text-white">GDPR Compliant</span>
                    </div>
                    <div class="security-badge">
                        <i class="fas fa-check-circle text-emerald-400"></i>
                        <span class="text-white">Data Protected</span>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mt-6">
                    <p class="text-sm sm:text-base text-gray-400">
                        <i class="fas fa-calendar-alt mr-2 text-emerald-400"></i>
                        Last Updated: <?php echo date('F j, Y'); ?>
                    </p>
                    <button onclick="window.print()" class="text-sm sm:text-base text-yellow-400 hover:text-yellow-300 transition">
                        <i class="fas fa-print mr-2"></i>
                        Print Policy
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-8 sm:py-12 lg:py-16">
        <div class="container mx-auto px-4">
            <div class="grid lg:grid-cols-4 gap-8">
                
                <!-- Table of Contents (Sidebar) -->
                <div class="lg:col-span-1">
                    <div class="privacy-card p-6 table-of-contents">
                        <h3 class="text-lg font-bold mb-4 text-white flex items-center gap-2">
                            <i class="fas fa-list text-emerald-400"></i>
                            Quick Navigation
                        </h3>
                        <nav class="space-y-2">
                            <a href="#introduction" class="toc-item block text-sm text-gray-300 hover:text-white rounded">1. Introduction</a>
                            <a href="#information-collect" class="toc-item block text-sm text-gray-300 hover:text-white rounded">2. Information We Collect</a>
                            <a href="#how-we-use" class="toc-item block text-sm text-gray-300 hover:text-white rounded">3. How We Use Data</a>
                            <a href="#data-sharing" class="toc-item block text-sm text-gray-300 hover:text-white rounded">4. Data Sharing</a>
                            <a href="#data-security" class="toc-item block text-sm text-gray-300 hover:text-white rounded">5. Data Security</a>
                            <a href="#your-rights" class="toc-item block text-sm text-gray-300 hover:text-white rounded">6. Your Rights</a>
                            <a href="#cookies" class="toc-item block text-sm text-gray-300 hover:text-white rounded">7. Cookies & Tracking</a>
                            <a href="#data-retention" class="toc-item block text-sm text-gray-300 hover:text-white rounded">8. Data Retention</a>
                            <a href="#children" class="toc-item block text-sm text-gray-300 hover:text-white rounded">9. Children's Privacy</a>
                            <a href="#international" class="toc-item block text-sm text-gray-300 hover:text-white rounded">10. International Users</a>
                            <a href="#changes" class="toc-item block text-sm text-gray-300 hover:text-white rounded">11. Policy Changes</a>
                            <a href="#contact" class="toc-item block text-sm text-gray-300 hover:text-white rounded">12. Contact Us</a>
                        </nav>
                        
                        <div class="mt-8 p-4 bg-gradient-to-br from-emerald-500/10 to-blue-500/10 rounded-lg border border-emerald-500/20">
                            <p class="text-xs text-gray-400 mb-3">Have privacy concerns?</p>
                            <a href="/help.php" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 flex items-center gap-2">
                                Contact Support <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Privacy Content -->
                <div class="lg:col-span-3">
                    <div class="privacy-card p-6 sm:p-8 lg:p-10">
                        
                        <!-- Important Notice -->
                        <div class="highlight-box mb-8">
                            <div class="flex items-start gap-4">
                                <i class="fas fa-info-circle text-2xl text-blue-400 mt-1"></i>
                                <div>
                                    <h4 class="font-bold text-lg mb-2 text-white">Our Commitment to Your Privacy</h4>
                                    <p class="text-sm sm:text-base text-gray-300 leading-relaxed">
                                        At Ultra Harvest Global, we are committed to protecting your personal information and your right to privacy. 
                                        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our platform.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 1: Introduction -->
                        <div id="introduction" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">1</span>
                                Introduction
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    This Privacy Policy describes how Ultra Harvest Global ("we," "us," or "our") collects, uses, and shares your personal 
                                    information when you access or use our website, mobile application, and related services (collectively, the "Platform").
                                </p>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    By using the Platform, you agree to the collection and use of information in accordance with this Privacy Policy. 
                                    If you do not agree with our policies and practices, please do not use our Platform.
                                </p>
                                <div class="info-box mt-4">
                                    <p class="text-sm flex items-start gap-2">
                                        <i class="fas fa-lightbulb text-yellow-400 mt-1"></i>
                                        <span><strong>Key Point:</strong> We only collect information that is necessary to provide you with our services 
                                        and improve your experience on our platform.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Information We Collect -->
                        <div id="information-collect" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">2</span>
                                Information We Collect
                            </h2>
                            <div class="space-y-6 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    We collect several types of information from and about users of our Platform:
                                </p>

                                <!-- Personal Information -->
                                <div class="data-category">
                                    <div class="icon-box">
                                        <i class="fas fa-user text-2xl text-white"></i>
                                    </div>
                                    <h3 class="text-xl font-bold mb-3 text-emerald-400">Personal Information</h3>
                                    <ul class="space-y-2 ml-4 text-sm sm:text-base">
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Full name and contact information (email address, phone number)</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Date of birth and government-issued identification numbers</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Physical address and residential information</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Username and password for account access</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Financial Information -->
                                <div class="data-category">
                                    <div class="icon-box">
                                        <i class="fas fa-wallet text-2xl text-white"></i>
                                    </div>
                                    <h3 class="text-xl font-bold mb-3 text-emerald-400">Financial Information</h3>
                                    <ul class="space-y-2 ml-4 text-sm sm:text-base">
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Bank account details and mobile money information</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Transaction history and payment information</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Investment package selections and preferences</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Withdrawal and deposit records</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Technical Information -->
                                <div class="data-category">
                                    <div class="icon-box">
                                        <i class="fas fa-laptop text-2xl text-white"></i>
                                    </div>
                                    <h3 class="text-xl font-bold mb-3 text-emerald-400">Technical Information</h3>
                                    <ul class="space-y-2 ml-4 text-sm sm:text-base">
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>IP address, browser type, and device information</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Operating system and mobile carrier information</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Usage data, including pages visited and time spent</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Cookies and similar tracking technologies</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Communication Data -->
                                <div class="data-category">
                                    <div class="icon-box">
                                        <i class="fas fa-comments text-2xl text-white"></i>
                                    </div>
                                    <h3 class="text-xl font-bold mb-3 text-emerald-400">Communication Data</h3>
                                    <ul class="space-y-2 ml-4 text-sm sm:text-base">
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Customer support inquiries and correspondence</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Feedback, reviews, and survey responses</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <i class="fas fa-circle text-emerald-400 text-xs mt-2"></i>
                                            <span>Marketing preferences and communication settings</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: How We Use Your Data -->
                        <div id="how-we-use" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">3</span>
                                How We Use Your Data
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed mb-4">
                                    We use the information we collect for various purposes, including:
                                </p>
                                
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-cogs text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Service Provision</h4>
                                        </div>
                                        <p class="text-sm">Process your investments, manage your account, and facilitate transactions</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-shield-alt text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Security & Fraud Prevention</h4>
                                        </div>
                                        <p class="text-sm">Verify your identity, prevent fraud, and ensure platform security</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-headset text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Customer Support</h4>
                                        </div>
                                        <p class="text-sm">Respond to your inquiries and provide technical assistance</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-chart-line text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Platform Improvement</h4>
                                        </div>
                                        <p class="text-sm">Analyze usage patterns and improve our services</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-balance-scale text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Legal Compliance</h4>
                                        </div>
                                        <p class="text-sm">Comply with legal obligations and regulatory requirements</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border border-emerald-500/20">
                                        <div class="flex items-center gap-3 mb-3">
                                            <i class="fas fa-bullhorn text-2xl text-emerald-400"></i>
                                            <h4 class="font-semibold text-white">Marketing Communications</h4>
                                        </div>
                                        <p class="text-sm">Send promotional materials and updates (with your consent)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Data Sharing -->
                        <div id="data-sharing" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">4</span>
                                Data Sharing and Disclosure
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <div class="bg-blue-500/10 border border-blue-500/30 p-5 rounded-lg mb-4">
                                    <p class="text-sm sm:text-base flex items-start gap-2">
                                        <i class="fas fa-info-circle text-blue-400 mt-1"></i>
                                        <span><strong>Important:</strong> We do not sell your personal information to third parties. 
                                        We only share your data in the following limited circumstances:</span>
                                    </p>
                                </div>

                                <ul class="space-y-4 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-building text-yellow-400 mt-1"></i>
                                        <div>
                                            <strong class="text-white">Service Providers:</strong>
                                            <span class="block mt-1">Third-party vendors who help us operate the Platform (payment processors, hosting providers, analytics services)</span>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-gavel text-yellow-400 mt-1"></i>
                                        <div>
                                            <strong class="text-white">Legal Requirements:</strong>
                                            <span class="block mt-1">When required by law, court order, or government authorities</span>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-exchange-alt text-yellow-400 mt-1"></i>
                                        <div>
                                            <strong class="text-white">Business Transfers:</strong>
                                            <span class="block mt-1">In connection with a merger, acquisition, or sale of company assets</span>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-user-check text-yellow-400 mt-1"></i>
                                        <div>
                                            <strong class="text-white">With Your Consent:</strong>
                                            <span class="block mt-1">When you explicitly authorize us to share your information</span>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                                        <div>
                                            <strong class="text-white">Protection of Rights:</strong>
                                            <span class="block mt-1">To protect our rights, property, or safety, or that of our users</span>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 5: Data Security -->
                        <div id="data-security" class="section-card border-l-4 border-l-blue-500">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-lg">5</span>
                                Data Security
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    We implement industry-standard security measures to protect your personal information:
                                </p>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-lock text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Encryption</h4>
                                        <p class="text-sm">256-bit SSL encryption for all data transmission and storage</p>
                                    </div>

                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-server text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Secure Servers</h4>
                                        <p class="text-sm">Data stored on secure, protected servers with regular backups</p>
                                    </div>

                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-key text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Access Controls</h4>
                                        <p class="text-sm">Restricted access to personal data on a need-to-know basis</p>
                                    </div>

                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-eye text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Monitoring</h4>
                                        <p class="text-sm">24/7 security monitoring and threat detection systems</p>
                                    </div>

                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-shield-virus text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Firewall Protection</h4>
                                        <p class="text-sm">Advanced firewalls to prevent unauthorized access</p>
                                    </div>

                                    <div class="bg-gradient-to-br from-blue-500/10 to-emerald-500/10 p-5 rounded-lg border border-blue-500/30">
                                        <i class="fas fa-sync-alt text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold text-lg mb-2 text-white">Regular Updates</h4>
                                        <p class="text-sm">Continuous security patches and system updates</p>
                                    </div>
                                </div>

                                <div class="bg-yellow-500/10 border border-yellow-500/30 p-4 rounded-lg mt-4">
                                    <p class="text-sm flex items-start gap-2">
                                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                                        <span><strong>Important:</strong> While we implement robust security measures, no method of transmission 
                                        over the Internet is 100% secure. We cannot guarantee absolute security of your data.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Your Rights -->
                        <div id="your-rights" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">6</span>
                                Your Privacy Rights
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed mb-4">
                                    You have the following rights regarding your personal information:
                                </p>

                                <div class="space-y-4">
                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-eye text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Access</h4>
                                                <p class="text-sm">You can request a copy of the personal information we hold about you</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-edit text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Correction</h4>
                                                <p class="text-sm">You can request that we correct any inaccurate or incomplete information</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-trash text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Deletion</h4>
                                                <p class="text-sm">You can request deletion of your personal data, subject to legal obligations</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-ban text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Object</h4>
                                                <p class="text-sm">You can object to certain types of data processing, including marketing communications</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-download text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Data Portability</h4>
                                                <p class="text-sm">You can request your data in a structured, commonly used format</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg border-l-4 border-emerald-500">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-hand-paper text-2xl text-emerald-400 mt-1"></i>
                                            <div>
                                                <h4 class="font-semibold text-lg mb-2 text-white">Right to Withdraw Consent</h4>
                                                <p class="text-sm">You can withdraw your consent for data processing at any time</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="info-box mt-6">
                                    <p class="text-sm">
                                        <i class="fas fa-envelope text-blue-400 mr-2"></i>
                                        To exercise any of these rights, please contact us at <a href="mailto:info@theultraharvest.com" class="text-blue-400 hover:text-blue-300 underline">info@theultraharvest.com</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 7: Cookies & Tracking -->
                        <div id="cookies" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">7</span>
                                Cookies and Tracking Technologies
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    We use cookies and similar tracking technologies to enhance your experience on our Platform:
                                </p>

                                <div class="grid sm:grid-cols-3 gap-4">
                                    <div class="bg-gray-800/50 p-5 rounded-lg text-center">
                                        <i class="fas fa-cookie-bite text-3xl text-yellow-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Essential Cookies</h4>
                                        <p class="text-sm">Required for platform functionality and security</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg text-center">
                                        <i class="fas fa-chart-pie text-3xl text-blue-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Analytics Cookies</h4>
                                        <p class="text-sm">Help us understand how you use our platform</p>
                                    </div>

                                    <div class="bg-gray-800/50 p-5 rounded-lg text-center">
                                        <i class="fas fa-bullseye text-3xl text-emerald-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Marketing Cookies</h4>
                                        <p class="text-sm">Deliver personalized content and ads</p>
                                    </div>
                                </div>

                                <div class="info-box">
                                    <p class="text-sm">
                                        <i class="fas fa-cog text-blue-400 mr-2"></i>
                                        You can control cookies through your browser settings. Note that disabling certain cookies may affect platform functionality.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 8: Data Retention -->
                        <div id="data-retention" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">8</span>
                                Data Retention
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    We retain your personal information for as long as necessary to:
                                </p>
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span>Provide you with our services and maintain your account</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span>Comply with legal, accounting, or reporting obligations</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span>Resolve disputes and enforce our agreements</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span>Prevent fraud and maintain security</span>
                                    </li>
                                </ul>
                                <div class="bg-gray-800/50 p-4 rounded-lg mt-4">
                                    <p class="text-sm">
                                        <strong class="text-white">Typical Retention Periods:</strong>
                                    </p>
                                    <ul class="mt-2 space-y-1 text-sm ml-4">
                                        <li>• Account information: Duration of account + 7 years</li>
                                        <li>• Transaction records: 7 years from transaction date</li>
                                        <li>• Marketing data: Until consent is withdrawn</li>
                                        <li>• Technical data: 2 years from collection</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Section 9: Children's Privacy -->
                        <div id="children" class="section-card border-l-4 border-l-red-500">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-lg">9</span>
                                Children's Privacy
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <div class="bg-red-500/10 border border-red-500/30 p-5 rounded-lg">
                                    <p class="text-sm sm:text-base flex items-start gap-2">
                                        <i class="fas fa-exclamation-triangle text-red-400 mt-1 text-xl"></i>
                                        <span><strong>Age Restriction:</strong> Our Platform is not intended for individuals under the age of 18. 
                                        We do not knowingly collect personal information from children.</span>
                                    </p>
                                </div>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    If we become aware that we have collected personal information from a child under 18 without parental consent, 
                                    we will take steps to delete that information as quickly as possible.
                                </p>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    If you believe we have collected information from a child, please contact us immediately at 
                                    <a href="mailto:info@theultraharvest.com" class="text-blue-400 hover:text-blue-300 underline">info@theultraharvest.com</a>
                                </p>
                            </div>
                        </div>

                        <!-- Section 10: International Users -->
                        <div id="international" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">10</span>
                                International Data Transfers
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    Our Platform is operated from Kenya, and your information may be transferred to, stored, and processed in Kenya 
                                    or other countries where our service providers operate.
                                </p>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    By using our Platform, you consent to the transfer of your information to countries outside of your residence, 
                                    which may have different data protection laws than your country.
                                </p>
                                <div class="info-box">
                                    <p class="text-sm">
                                        <i class="fas fa-globe text-blue-400 mr-2"></i>
                                        We ensure appropriate safeguards are in place to protect your information when transferred internationally.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 11: Policy Changes -->
                        <div id="changes" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">11</span>
                                Changes to This Privacy Policy
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    We may update this Privacy Policy from time to time to reflect changes in our practices or for legal, 
                                    operational, or regulatory reasons.
                                </p>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <h4 class="font-semibold mb-2 text-emerald-400">How We Notify You</h4>
                                        <ul class="space-y-1 text-sm">
                                            <li>• Email notification to registered users</li>
                                            <li>• Prominent notice on our Platform</li>
                                            <li>• Updated "Last Modified" date</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <h4 class="font-semibold mb-2 text-emerald-400">Your Options</h4>
                                        <ul class="space-y-1 text-sm">
                                            <li>• Review changes before they take effect</li>
                                            <li>• Contact us with questions or concerns</li>
                                            <li>• Discontinue use if you disagree</li>
                                        </ul>
                                    </div>
                                </div>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    Your continued use of the Platform after changes indicates your acceptance of the updated Privacy Policy.
                                </p>
                            </div>
                        </div>

                        <!-- Section 12: Contact Us -->
                        <div id="contact" class="section-card border-l-4 border-l-yellow-500">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center text-lg">12</span>
                                Contact Us
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, 
                                    please contact us:
                                </p>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gradient-to-br from-emerald-500/10 to-yellow-500/10 p-5 rounded-lg border border-emerald-500/30">
                                        <i class="fas fa-envelope text-2xl text-emerald-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Email</h4>
                                        <a href="mailto:info@theultraharvest.com" class="text-sm text-blue-400 hover:text-blue-300 underline">
                                            info@theultraharvest.com
                                        </a>
                                    </div>

                                    <div class="bg-gradient-to-br from-emerald-500/10 to-yellow-500/10 p-5 rounded-lg border border-emerald-500/30">
                                        <i class="fas fa-life-ring text-2xl text-emerald-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Support Center</h4>
                                        <a href="/help.php" class="text-sm text-blue-400 hover:text-blue-300 underline">
                                            Visit Help Center
                                        </a>
                                    </div>

                                    <div class="bg-gradient-to-br from-emerald-500/10 to-yellow-500/10 p-5 rounded-lg border border-emerald-500/30">
                                        <i class="fab fa-whatsapp text-2xl text-emerald-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">WhatsApp</h4>
                                        <a href="https://whatsapp.com/channel/0029Vb6ZWta17En4fWE1u22P" target="_blank" class="text-sm text-blue-400 hover:text-blue-300 underline">
                                            Join Our Channel
                                        </a>
                                    </div>

                                    <div class="bg-gradient-to-br from-emerald-500/10 to-yellow-500/10 p-5 rounded-lg border border-emerald-500/30">
                                        <i class="fas fa-map-marker-alt text-2xl text-emerald-400 mb-3"></i>
                                        <h4 class="font-semibold mb-2 text-white">Location</h4>
                                        <p class="text-sm">Nairobi, Kenya</p>
                                    </div>
                                </div>

                                <div class="bg-blue-500/10 border border-blue-500/30 p-4 rounded-lg mt-4">
                                    <p class="text-sm">
                                        <i class="fas fa-clock text-blue-400 mr-2"></i>
                                        <strong>Response Time:</strong> We aim to respond to all privacy-related inquiries within 48 hours.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Acceptance Section -->
                        <div class="highlight-box mt-8">
                            <div class="text-center">
                                <i class="fas fa-shield-alt text-4xl text-emerald-400 mb-4"></i>
                                <h3 class="text-xl sm:text-2xl font-bold mb-4 text-white">Your Privacy Matters</h3>
                                <p class="text-sm sm:text-base text-gray-300 mb-6 leading-relaxed">
                                    By using Ultra Harvest Global, you acknowledge that you have read and understood this Privacy Policy 
                                    and agree to the collection, use, and disclosure of your information as described herein.
                                </p>
                                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                                    <a href="/register.php" class="btn-primary">
                                        <i class="fas fa-user-plus"></i>
                                        <span>Create Secure Account</span>
                                    </a>
                                    <a href="/terms.php" class="inline-flex items-center gap-2 px-6 py-3 border-2 border-emerald-500 text-emerald-400 font-bold rounded-full hover:bg-emerald-500 hover:text-white transition">
                                        <i class="fas fa-file-contract"></i>
                                        <span>View Terms</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-8 sm:py-12 bg-gray-900/50 backdrop-blur-md border-t border-gray-800 mt-16">
        <div class="container mx-auto px-4">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8">
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
                    <p class="text-sm text-gray-400">Growing Wealth Together</p>
                </div>
                
                <div>
                    <h3 class="font-semibold text-base sm:text-lg mb-3 sm:mb-4 text-white">Legal</h3>
                    <div class="space-y-2">
                        <a href="/terms.php" class="block text-sm text-gray-400 hover:text-emerald-400 transition">Terms & Conditions</a>
                        <a href="/privacy.php" class="block text-sm text-emerald-400 hover:text-emerald-300 transition">Privacy Policy</a>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-semibold text-base sm:text-lg mb-3 sm:mb-4 text-white">Support</h3>
                    <div class="space-y-2">
                        <a href="/help.php" class="block text-sm text-gray-400 hover:text-emerald-400 transition">Help Center</a>
                        <a href="/" class="block text-sm text-gray-400 hover:text-emerald-400 transition">Back to Home</a>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-semibold text-base sm:text-lg mb-3 sm:mb-4 text-white">Connect</h3>
                    <div class="flex space-x-3 mb-4">
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-facebook-f text-white"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-twitter text-white"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform">
                            <i class="fab fa-whatsapp text-white"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-6 text-center">
                <p class="text-gray-500 text-xs sm:text-sm">
                    © <?php echo date('Y'); ?> Ultra Harvest Global. All rights reserved.
                </p>
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

    <!-- Scroll to Top Button -->
    <button id="scrollTop" class="fixed bottom-20 right-4 sm:bottom-24 sm:right-6 z-40 w-12 h-12 bg-emerald-500 rounded-full flex items-center justify-center shadow-xl hover:scale-110 transition-all duration-300 opacity-0 invisible"
            style="box-shadow: 0 4px 20px rgba(16, 185, 129, 0.5);">
        <i class="fas fa-arrow-up text-white text-xl"></i>
    </button>

    <!-- JavaScript -->
    <script>
        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');

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

        // Close mobile menu on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Table of Contents - Active Section Highlighting
        const sections = document.querySelectorAll('.section-card');
        const tocItems = document.querySelectorAll('.toc-item');

        function highlightTOC() {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (pageYOffset >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            tocItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === '#' + current) {
                    item.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', highlightTOC);

        // Smooth Scrolling for TOC
        document.querySelectorAll('.toc-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileMenu();
                const targetId = this.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                if (targetSection) {
                    const offsetTop = targetSection.offsetTop - 100;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Scroll to Top Button
        const scrollTopBtn = document.getElementById('scrollTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.style.opacity = '1';
                scrollTopBtn.style.visibility = 'visible';
            } else {
                scrollTopBtn.style.opacity = '0';
                scrollTopBtn.style.visibility = 'hidden';
            }
        });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Animate sections on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        sections.forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(section);
        });

        // Print functionality
        function printPrivacy() {
            window.print();
        }

        // Add keyboard shortcut for print (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printPrivacy();
            }
        });

        // Prevent horizontal scroll from mobile menu
        let scrollPosition = 0;
        mobileMenuBtn.addEventListener('click', () => {
            scrollPosition = window.pageYOffset;
            document.body.style.position = 'fixed';
            document.body.style.top = `-${scrollPosition}px`;
            document.body.style.width = '100%';
        });

        [mobileMenuClose, mobileMenuOverlay].forEach(el => {
            el.addEventListener('click', () => {
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                window.scrollTo(0, scrollPosition);
            });
        });

        // Security badges animation
        const securityBadges = document.querySelectorAll('.security-badge');
        securityBadges.forEach((badge, index) => {
            badge.style.opacity = '0';
            badge.style.transform = 'translateY(20px)';
            setTimeout(() => {
                badge.style.transition = 'all 0.5s ease';
                badge.style.opacity = '1';
                badge.style.transform = 'translateY(0)';
            }, 200 * index);
        });

        // Data category hover effect enhancement
        const dataCategories = document.querySelectorAll('.data-category');
        dataCategories.forEach(category => {
            category.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 10px 30px rgba(16, 185, 129, 0.3)';
            });
            category.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
            });
        });

        // Add print styles dynamically
        const printStyles = `
            @media print {
                nav, footer, #scrollTop, .floating-whatsapp, .btn-primary, .security-badge {
                    display: none !important;
                }
                .table-of-contents {
                    display: none !important;
                }
                .privacy-card {
                    box-shadow: none !important;
                    border: 1px solid #ccc !important;
                }
                body {
                    background: white !important;
                    color: black !important;
                }
                .section-card {
                    page-break-inside: avoid;
                    border-left-color: #10b981 !important;
                }
                h1, h2, h3, h4 {
                    color: black !important;
                }
                .data-category, .info-box, .highlight-box {
                    border: 1px solid #ddd !important;
                    background: #f9f9f9 !important;
                }
                a {
                    color: #0066cc !important;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);

        // Email link validation
        const emailLinks = document.querySelectorAll('a[href^="mailto:"]');
        emailLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const email = this.href.replace('mailto:', '');
                console.log('Email contact initiated:', email);
            });
        });

        // Smooth reveal for icon boxes
        const iconBoxes = document.querySelectorAll('.icon-box');
        const iconObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'float 3s ease-in-out infinite';
                }
            });
        }, { threshold: 0.5 });

        iconBoxes.forEach(box => {
            iconObserver.observe(box);
        });

        // Add float animation keyframes
        const floatAnimation = `
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
            }
        `;
        
        const animationSheet = document.createElement('style');
        animationSheet.textContent = floatAnimation;
        document.head.appendChild(animationSheet);

        // Cookie notice interaction (if needed in future)
        function showCookieNotice() {
            // Placeholder for future cookie notice implementation
            const cookieConsent = localStorage.getItem('cookieConsent');
            if (!cookieConsent) {
                // Show cookie notice
                console.log('Cookie consent not given');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Fade in main content
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.5s ease';
                document.body.style.opacity = '1';
            }, 100);
        });

        // Handle back to top with progress indicator
        let scrollPercentage = 0;
        window.addEventListener('scroll', () => {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            scrollPercentage = (winScroll / height) * 100;
            
            // You can use this to show a progress bar if needed
            if (scrollPercentage > 10) {
                scrollTopBtn.style.display = 'flex';
            }
        });
    </script>
</body>
</html>