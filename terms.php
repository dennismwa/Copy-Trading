<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - Ultra Harvest Global</title>
    <meta name="description" content="Read Ultra Harvest Global's terms and conditions">
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

        .terms-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            transition: all 0.3s ease;
        }

        .terms-card:hover {
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

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
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
                <a href="/privacy.php" class="text-gray-200 hover:text-emerald-400 transition font-medium">Privacy</a>
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
                    <a href="/privacy.php" class="block text-gray-300 hover:text-emerald-400 transition text-lg">Privacy</a>
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
                <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-6 bg-gradient-to-br from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center shadow-2xl">
                    <i class="fas fa-file-contract text-3xl sm:text-4xl text-white"></i>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-6xl font-bold mb-4 sm:mb-6">
                    Terms & <span class="accent-emerald">Conditions</span>
                </h1>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300 mb-6">
                    Please read these terms carefully before using Ultra Harvest Global
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                    <p class="text-sm sm:text-base text-gray-400">
                        <i class="fas fa-calendar-alt mr-2 text-emerald-400"></i>
                        Last Updated: <?php echo date('F j, Y'); ?>
                    </p>
                    <button onclick="window.print()" class="text-sm sm:text-base text-yellow-400 hover:text-yellow-300 transition">
                        <i class="fas fa-print mr-2"></i>
                        Print Terms
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
                    <div class="terms-card p-6 table-of-contents">
                        <h3 class="text-lg font-bold mb-4 text-white flex items-center gap-2">
                            <i class="fas fa-list text-emerald-400"></i>
                            Quick Navigation
                        </h3>
                        <nav class="space-y-2">
                            <a href="#introduction" class="toc-item block text-sm text-gray-300 hover:text-white rounded">1. Introduction</a>
                            <a href="#definitions" class="toc-item block text-sm text-gray-300 hover:text-white rounded">2. Definitions</a>
                            <a href="#services" class="toc-item block text-sm text-gray-300 hover:text-white rounded">3. Services Provided</a>
                            <a href="#eligibility" class="toc-item block text-sm text-gray-300 hover:text-white rounded">4. Eligibility</a>
                            <a href="#packages" class="toc-item block text-sm text-gray-300 hover:text-white rounded">5. Investment Packages</a>
                            <a href="#deposits" class="toc-item block text-sm text-gray-300 hover:text-white rounded">6. Deposits</a>
                            <a href="#withdrawals" class="toc-item block text-sm text-gray-300 hover:text-white rounded">7. Withdrawals</a>
                            <a href="#reinvestment" class="toc-item block text-sm text-gray-300 hover:text-white rounded">8. Reinvestment</a>
                            <a href="#referral" class="toc-item block text-sm text-gray-300 hover:text-white rounded">9. Referral Program</a>
                            <a href="#fees" class="toc-item block text-sm text-gray-300 hover:text-white rounded">10. Fees & Charges</a>
                            <a href="#risks" class="toc-item block text-sm text-gray-300 hover:text-white rounded">11. Risks & Disclaimer</a>
                            <a href="#privacy" class="toc-item block text-sm text-gray-300 hover:text-white rounded">12. Privacy & Data</a>
                            <a href="#termination" class="toc-item block text-sm text-gray-300 hover:text-white rounded">13. Termination</a>
                            <a href="#disputes" class="toc-item block text-sm text-gray-300 hover:text-white rounded">14. Dispute Resolution</a>
                            <a href="#miscellaneous" class="toc-item block text-sm text-gray-300 hover:text-white rounded">15. Miscellaneous</a>
                        </nav>
                        
                        <div class="mt-8 p-4 bg-gradient-to-br from-emerald-500/10 to-yellow-500/10 rounded-lg border border-emerald-500/20">
                            <p class="text-xs text-gray-400 mb-3">Need clarification?</p>
                            <a href="/help.php" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 flex items-center gap-2">
                                Contact Support <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Terms Content -->
                <div class="lg:col-span-3">
                    <div class="terms-card p-6 sm:p-8 lg:p-10">
                        
                        <!-- Important Notice -->
                        <div class="highlight-box mb-8">
                            <div class="flex items-start gap-4">
                                <i class="fas fa-exclamation-triangle text-2xl text-yellow-400 mt-1"></i>
                                <div>
                                    <h4 class="font-bold text-lg mb-2 text-white">Important Notice</h4>
                                    <p class="text-sm sm:text-base text-gray-300 leading-relaxed">
                                        By creating an account, making a deposit, or using our services, you confirm that you have read, 
                                        understood, and agreed to be bound by these terms and conditions. Please read this agreement carefully.
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
                                    Welcome to Ultra Harvest ("the Platform"). By creating an account, making a deposit, or using our services, 
                                    you ("the User") agree to comply with this User Agreement. This Agreement governs your use of the Platform, 
                                    including deposits, investments, withdrawals, referral programs, and all associated services.
                                </p>
                                <p class="text-sm sm:text-base leading-relaxed">
                                    By accessing or using the Platform, you confirm that you have read, understood, and agreed to be bound by these terms.
                                </p>
                            </div>
                        </div>

                        <!-- Section 2: Definitions -->
                        <div id="definitions" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">2</span>
                                Definitions
                            </h2>
                            <div class="space-y-3 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed mb-4">For the purposes of this Agreement:</p>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">User</p>
                                        <p class="text-sm">Any individual or entity registering and participating in the Platform.</p>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">Deposit</p>
                                        <p class="text-sm">Funds transferred into the Platform for participating in investment Packages.</p>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">ROI</p>
                                        <p class="text-sm">Return on Investment - the percentage-based return associated with a Package upon maturity.</p>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">Withdrawal</p>
                                        <p class="text-sm">The process of requesting and receiving funds from the Platform.</p>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">Reinvestment</p>
                                        <p class="text-sm">Using previously deposited funds or accrued ROI to purchase additional Packages.</p>
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <p class="font-semibold text-emerald-400 mb-2">Referral Bonus</p>
                                        <p class="text-sm">The incentive provided to a User who successfully introduces new Users to the Platform.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Services Provided -->
                        <div id="services" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">3</span>
                                Services Provided
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">
                                    Ultra Harvest provides digital investment opportunities in the form of predefined Packages. 
                                    Services include but are not limited to:
                                </p>
                                <ul class="space-y-2 ml-4">
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Hosting a secure digital environment for deposits and withdrawals</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Providing a variety of investment Packages with predetermined ROI rates and timelines</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Enabling Users to reinvest funds automatically or manually upon maturity of a Package</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Processing withdrawal requests subject to applicable fees</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Operating a referral program to incentivize Users for introducing new participants</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <i class="fas fa-check-circle text-emerald-400 mt-1"></i>
                                        <span class="text-sm sm:text-base">Providing account dashboards showing balances, ROI earned, referral bonuses, and active Packages</span>
                                    </li>
                                </ul>
                                <p class="text-sm text-gray-400 italic mt-4">
                                    The Platform may add, modify, or remove features at its discretion.
                                </p>
                            </div>
                        </div>

                        <!-- Section 4: Eligibility -->
                        <div id="eligibility" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">4</span>
                                Eligibility
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed">To use the Platform:</p>
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">1</span>
                                        <span class="text-sm sm:text-base">You must be at least 18 years old</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">2</span>
                                        <span class="text-sm sm:text-base">You must provide accurate and complete registration details</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">3</span>
                                        <span class="text-sm sm:text-base">You agree to maintain the confidentiality of your account credentials</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">4</span>
                                        <span class="text-sm sm:text-base">You are solely responsible for all activities conducted under your account</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">5</span>
                                        <span class="text-sm sm:text-base">You are responsible for ensuring that your use of the Platform complies with all applicable laws</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="w-6 h-6 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 text-sm mt-0.5">6</span>
                                        <span class="text-sm sm:text-base">The Platform reserves the right to refuse service, suspend, or terminate accounts at its discretion</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 5: Investment Packages -->
                        <div id="packages" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">5</span>
                                Investment Packages
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base leading-relaxed mb-4">Ultra Harvest offers the following investment Packages:</p>
                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-emerald-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-emerald-400">🌱 Seed</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 500</li>
                                            <li><span class="text-gray-400">ROI:</span> 10% in 24h</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 7%</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-emerald-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-emerald-400">🌿 Sprout</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 5,000</li>
                                            <li><span class="text-gray-400">ROI:</span> 11% in 48h</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 6%</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-emerald-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-emerald-400">🌳 Growth</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 10,000</li>
                                            <li><span class="text-gray-400">ROI:</span> 12% in 48h</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 5%</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-emerald-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-emerald-400">🌾 Harvest</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 25,000</li>
                                            <li><span class="text-gray-400">ROI:</span> 14% in 48h</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 5%</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-yellow-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-yellow-400">💰 Golden Yield</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 50,000</li>
                                            <li><span class="text-gray-400">ROI:</span> 15% in 72h</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 4%</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 p-5 rounded-xl border border-yellow-500/20">
                                        <h4 class="font-bold text-lg mb-3 text-yellow-400">💎 Elite</h4>
                                        <ul class="space-y-2 text-sm">
                                            <li><span class="text-gray-400">Minimum:</span> KSh 100,000</li>
                                            <li><span class="text-gray-400">ROI:</span> 15% in 14 days</li>
                                            <li><span class="text-gray-400">Withdrawal Fee:</span> 3%</li>
                                        </ul>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-400 italic mt-4">
                                    Ultra Harvest may revise these Packages, including ROI percentages, fees, and durations, at any time with prior notice to Users.
                                </p>
                            </div>
                        </div>

                        <!-- Section 6: Deposits -->
                        <div id="deposits" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">6</span>
                                Deposits
                            </h2>
                            <div class="space-y-3 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Deposits are accepted in Kenyan Shillings (KSh) or other currencies as approved</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Deposits must meet the minimum amount required by the chosen Package</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Deposits are locked until the maturity of the Package</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Deposits may be made via mobile money, bank transfer, or other approved channels</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>The Platform may impose transaction limits, both minimum and maximum</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Deposits may be subject to additional verification for fraud prevention</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 7: Withdrawals -->
                        <div id="withdrawals" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">7</span>
                                Withdrawals
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Users may withdraw funds only upon maturity of the selected Package</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Withdrawals consist of the principal + ROI</span>
                                    </li>
                                </ul>
                                <div class="bg-gray-800/50 p-4 rounded-lg mt-4">
                                    <p class="font-semibold text-emerald-400 mb-3">Withdrawals are subject to:</p>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-start gap-2">
                                            <span class="text-yellow-400">•</span>
                                            <span><strong>Transaction Fee:</strong> applied on ROI only, varying by Package</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-yellow-400">•</span>
                                            <span><strong>Commission:</strong> 1.5% of the total withdrawal amount (principal + ROI)</span>
                                        </li>
                                    </ul>
                                </div>
                                <ul class="space-y-3 ml-4 mt-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Withdrawal requests are typically processed within 24-72 hours, though delays may occur</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>The Platform reserves the right to withhold withdrawals if fraudulent activity is suspected</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-arrow-right text-yellow-400 mt-1"></i>
                                        <span>Users acknowledge that withdrawal processing may be affected by third-party payment processors</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 8: Reinvestment -->
                        <div id="reinvestment" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">8</span>
                                Reinvestment
                            </h2>
                            <div class="space-y-3 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-recycle text-emerald-400 mt-1"></i>
                                        <span>Upon maturity, Users may choose to reinvest their principal, ROI, or both</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-recycle text-emerald-400 mt-1"></i>
                                        <span>Reinvestments are treated as new deposits and are subject to the same rules</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-recycle text-emerald-400 mt-1"></i>
                                        <span>Reinvestment does not exempt Users from fees or commissions</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 9: Referral Program -->
                        <div id="referral" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">9</span>
                                Referral Program
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <div class="bg-gradient-to-r from-emerald-500/10 to-yellow-500/10 p-5 rounded-lg border border-emerald-500/30">
                                    <p class="text-lg font-semibold text-emerald-400 mb-2">
                                        <i class="fas fa-gift mr-2"></i>
                                        Earn 10% Referral Bonus
                                    </p>
                                    <p class="text-sm">When a referred User makes their first deposit</p>
                                </div>
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-check text-emerald-400 mt-1"></i>
                                        <span>Referral bonuses are credited instantly and may be withdrawn or reinvested</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                                        <span>Abuse of the referral program, including creation of fake accounts, circular referrals, or multiple identities, will result in forfeiture of bonuses and possible termination</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-info-circle text-blue-400 mt-1"></i>
                                        <span>The Platform reserves the right to amend or terminate the referral program at any time</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 10: Fees and Charges -->
                        <div id="fees" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">10</span>
                                Fees and Charges
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-percentage text-yellow-400 mt-1"></i>
                                        <span>ROI withdrawals are subject to a Transaction Fee ranging from 3% to 7% depending on the Package</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-percentage text-yellow-400 mt-1"></i>
                                        <span>All withdrawals are subject to a 1.5% Commission on the total amount (principal + ROI)</span>
                                    </li>
                                </ul>
                                <div class="bg-gray-800/50 p-4 rounded-lg">
                                    <p class="font-semibold text-emerald-400 mb-3">Additional charges may include:</p>
                                    <ul class="space-y-2 text-sm">
                                        <li class="flex items-start gap-2">
                                            <span class="text-yellow-400">•</span>
                                            <span>Currency conversion fees</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-yellow-400">•</span>
                                            <span>Processing fees from banks or mobile money providers</span>
                                        </li>
                                        <li class="flex items-start gap-2">
                                            <span class="text-yellow-400">•</span>
                                            <span>Fees associated with fraud checks or compliance requirements</span>
                                        </li>
                                    </ul>
                                </div>
                                <p class="text-sm text-gray-400 italic">
                                    The Platform may revise its fee structure at any time with prior notice.
                                </p>
                            </div>
                        </div>

                        <!-- Section 11: Risks and Disclaimer -->
                        <div id="risks" class="section-card border-l-4 border-l-red-500">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-lg">11</span>
                                Risks and Disclaimer
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <div class="bg-red-500/10 border border-red-500/30 p-5 rounded-lg">
                                    <p class="font-semibold text-red-400 mb-3 flex items-center gap-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Important Risk Information
                                    </p>
                                    <p class="text-sm leading-relaxed">
                                        Investments inherently carry risk of loss. ROI percentages are targets and should not be considered guaranteed. 
                                        Market conditions, technology failures, or unexpected events may affect performance.
                                    </p>
                                </div>
                                <p class="text-sm sm:text-base font-semibold">The Platform shall not be held responsible for:</p>
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-times-circle text-red-400 mt-1"></i>
                                        <span>Losses arising from User error</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-times-circle text-red-400 mt-1"></i>
                                        <span>Unauthorized access to accounts</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-times-circle text-red-400 mt-1"></i>
                                        <span>Technical downtime, hacking, or cyberattacks</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-times-circle text-red-400 mt-1"></i>
                                        <span>Acts of God, natural disasters, or government restrictions</span>
                                    </li>
                                </ul>
                                <p class="text-sm text-gray-400 italic">
                                    Users agree that past performance is not indicative of future results.
                                </p>
                            </div>
                        </div>

                        <!-- Section 12: Privacy and Data Protection -->
                        <div id="privacy" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">12</span>
                                Privacy and Data Protection
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-shield-alt text-emerald-400 mt-1"></i>
                                        <span>The Platform collects personal information such as names, emails, phone numbers, and transaction data</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-shield-alt text-emerald-400 mt-1"></i>
                                        <span>Data is stored securely and used only for service delivery, fraud prevention, and compliance</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-shield-alt text-emerald-400 mt-1"></i>
                                        <span>The Platform does not sell or share personal data with third parties except as required by law</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-shield-alt text-emerald-400 mt-1"></i>
                                        <span>Users consent to the collection and processing of their data by using the Platform</span>
                                    </li>
                                </ul>
                                <div class="bg-blue-500/10 border border-blue-500/30 p-4 rounded-lg">
                                    <p class="text-sm">
                                        <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                                        For more details, please read our <a href="/privacy.php" class="text-blue-400 hover:text-blue-300 underline">Privacy Policy</a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 13: Termination and Suspension -->
                        <div id="termination" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">13</span>
                                Termination and Suspension
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <p class="text-sm sm:text-base font-semibold">The Platform may suspend or terminate accounts for:</p>
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-ban text-red-400 mt-1"></i>
                                        <span>Violation of this Agreement</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-ban text-red-400 mt-1"></i>
                                        <span>Fraudulent or suspicious activity</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <i class="fas fa-ban text-red-400 mt-1"></i>
                                        <span>Legal or regulatory requirements</span>
                                    </li>
                                </ul>
                                <div class="bg-yellow-500/10 border border-yellow-500/30 p-4 rounded-lg">
                                    <p class="text-sm">
                                        <i class="fas fa-exclamation-triangle text-yellow-400 mr-2"></i>
                                        Upon termination, pending deposits may be forfeited at the Platform's discretion
                                    </p>
                                </div>
                                <p class="text-sm sm:text-base">
                                    Users may close their accounts by submitting a request, subject to settlement of outstanding obligations.
                                </p>
                            </div>
                        </div>

                        <!-- Section 14: Dispute Resolution -->
                        <div id="disputes" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">14</span>
                                Dispute Resolution
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <ul class="space-y-3 ml-4">
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <span class="w-8 h-8 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 font-bold text-sm mt-0.5 flex-shrink-0">1</span>
                                        <span>Any disputes shall first be addressed through internal support channels</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <span class="w-8 h-8 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 font-bold text-sm mt-0.5 flex-shrink-0">2</span>
                                        <span>If unresolved, disputes shall be referred to arbitration in Nairobi, Kenya</span>
                                    </li>
                                    <li class="flex items-start gap-3 text-sm sm:text-base">
                                        <span class="w-8 h-8 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-400 font-bold text-sm mt-0.5 flex-shrink-0">3</span>
                                        <span>Users waive the right to participate in class-action lawsuits against the Platform</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Section 15: Miscellaneous Provisions -->
                        <div id="miscellaneous" class="section-card">
                            <h2 class="text-2xl sm:text-3xl font-bold mb-4 text-white flex items-center gap-3">
                                <span class="w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center text-lg">15</span>
                                Miscellaneous Provisions
                            </h2>
                            <div class="space-y-4 text-gray-300">
                                <div class="bg-gray-800/50 p-5 rounded-lg">
                                    <h4 class="font-semibold text-emerald-400 mb-2">Entire Agreement</h4>
                                    <p class="text-sm">This Agreement constitutes the full understanding between the parties.</p>
                                </div>
                                <div class="bg-gray-800/50 p-5 rounded-lg">
                                    <h4 class="font-semibold text-emerald-400 mb-2">Severability</h4>
                                    <p class="text-sm">If any clause is found invalid, the remaining clauses remain in force.</p>
                                </div>
                                <div class="bg-gray-800/50 p-5 rounded-lg">
                                    <h4 class="font-semibold text-emerald-400 mb-2">Force Majeure</h4>
                                    <p class="text-sm">The Platform shall not be liable for delays caused by events outside its reasonable control.</p>
                                </div>
                                <div class="bg-gray-800/50 p-5 rounded-lg">
                                    <h4 class="font-semibold text-emerald-400 mb-2">Amendments</h4>
                                    <p class="text-sm">Ultra Harvest may modify this Agreement at any time. Continued use of the Platform implies acceptance of updates.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Acceptance Section -->
                        <div class="highlight-box mt-8">
                            <div class="text-center">
                                <i class="fas fa-handshake text-4xl text-emerald-400 mb-4"></i>
                                <h3 class="text-xl sm:text-2xl font-bold mb-4 text-white">Agreement Acceptance</h3>
                                <p class="text-sm sm:text-base text-gray-300 mb-6 leading-relaxed">
                                    By using Ultra Harvest Global, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. 
                                    If you do not agree to these terms, please do not use our services.
                                </p>
                                <a href="/register.php" class="btn-primary">
                                    <i class="fas fa-check-circle"></i>
                                    <span>I Accept - Create Account</span>
                                </a>
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
                        <a href="/terms.php" class="block text-sm text-emerald-400 hover:text-emerald-300 transition">Terms & Conditions</a>
                        <a href="/privacy.php" class="block text-sm text-gray-400 hover:text-emerald-400 transition">Privacy Policy</a>
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
        function printTerms() {
            window.print();
        }

        // Add keyboard shortcut for print (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printTerms();
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

        // Add print styles dynamically
        const printStyles = `
            @media print {
                nav, footer, #scrollTop, .floating-whatsapp, .btn-primary {
                    display: none !important;
                }
                .table-of-contents {
                    display: none !important;
                }
                .terms-card {
                    box-shadow: none !important;
                    border: 1px solid #ccc !important;
                }
                body {
                    background: white !important;
                    color: black !important;
                }
                .section-card {
                    page-break-inside: avoid;
                }
                h1, h2, h3 {
                    color: black !important;
                }
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>