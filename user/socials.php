<?php
/**
 * Socials Page
 * Connect with Ultra Harvest on Telegram and WhatsApp
 */

require_once '../config/database.php';
requireLogin();

$page_title = "Socials - Ultra Harvest";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .social-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .social-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .social-card:hover::before {
            left: 100%;
        }
        
        .social-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .whatsapp-gradient {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        }
        
        .telegram-gradient {
            background: linear-gradient(135deg, #0088cc 0%, #0066aa 100%);
        }
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }
        
        .floating-icon {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .icon-glow {
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.5));
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Header -->
    <header class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <a href="/user/dashboard.php" class="flex items-center space-x-2 text-emerald-400 hover:text-emerald-300 transition">
                    <i class="fas fa-arrow-left text-xl"></i>
                    <span class="font-semibold">Back</span>
                </a>
                <h1 class="text-xl font-bold">Socials</h1>
                <div class="w-20"></div> <!-- Spacer for centering -->
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 pb-24">
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <div class="inline-block mb-4">
                <i class="fas fa-share-alt text-6xl text-emerald-400 floating-icon"></i>
            </div>
            <h2 class="text-3xl font-bold mb-3">Stay Connected</h2>
            <p class="text-gray-400 text-lg max-w-md mx-auto">
                Join our community channels for updates, tips, and exclusive content
            </p>
        </div>

        <!-- Social Cards -->
        <div class="max-w-2xl mx-auto space-y-6">
            <!-- WhatsApp Card -->
            <a href="https://whatsapp.com/channel/0029Vb6ZWta17En4fWE1u22P" 
               target="_blank" 
               rel="noopener noreferrer"
               class="block social-card whatsapp-gradient rounded-2xl p-8 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fab fa-whatsapp text-3xl icon-glow"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">WhatsApp Channel</h3>
                            <p class="text-white/80 text-sm">Join our WhatsApp community</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-2xl opacity-80"></i>
                </div>
                <div class="flex items-center space-x-2 text-sm">
                    <span class="bg-white/20 px-3 py-1 rounded-full backdrop-blur-sm">
                        <i class="fas fa-users mr-1"></i>
                        79+ Followers
                    </span>
                    <span class="bg-white/20 px-3 py-1 rounded-full backdrop-blur-sm">
                        <i class="fas fa-bolt mr-1"></i>
                        Instant Updates
                    </span>
                </div>
            </a>

            <!-- Telegram Card -->
            <a href="https://t.me/+Kn9w0TC-lag5YmU8" 
               target="_blank" 
               rel="noopener noreferrer"
               class="block social-card telegram-gradient rounded-2xl p-8 text-white">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                            <i class="fab fa-telegram text-3xl icon-glow"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">Telegram Channel</h3>
                            <p class="text-white/80 text-sm">Join our Telegram community</p>
                        </div>
                    </div>
                    <i class="fas fa-arrow-right text-2xl opacity-80"></i>
                </div>
                <div class="flex items-center space-x-2 text-sm">
                    <span class="bg-white/20 px-3 py-1 rounded-full backdrop-blur-sm">
                        <i class="fas fa-users mr-1"></i>
                        2,806+ Subscribers
                    </span>
                    <span class="bg-white/20 px-3 py-1 rounded-full backdrop-blur-sm">
                        <i class="fas fa-bell mr-1"></i>
                        Notifications
                    </span>
                </div>
            </a>
        </div>

        <!-- Features Section -->
        <div class="max-w-2xl mx-auto mt-12">
            <div class="bg-gray-800/50 rounded-2xl p-6 backdrop-blur-sm border border-gray-700">
                <h3 class="text-xl font-bold mb-4 text-center">What You'll Get</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-chart-line text-emerald-400"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-1">Trading Tips</h4>
                            <p class="text-sm text-gray-400">Expert insights and strategies</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-bell text-blue-400"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-1">Latest Updates</h4>
                            <p class="text-sm text-gray-400">Stay informed in real-time</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-gift text-purple-400"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-1">Exclusive Offers</h4>
                            <p class="text-sm text-gray-400">Special bonuses and promotions</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-yellow-400"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold mb-1">Community Support</h4>
                            <p class="text-sm text-gray-400">Connect with other traders</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="max-w-2xl mx-auto mt-8 text-center">
            <p class="text-gray-400 text-sm">
                Don't miss out on important updates and opportunities. Join our channels now!
            </p>
        </div>
    </main>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-40">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/referrals.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-users text-xl mb-1"></i>
                <span class="text-xs">Network</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/socials.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-share-alt text-xl mb-1"></i>
                <span class="text-xs">Socials</span>
            </a>
        </div>
    </div>

    <!-- Live Chat Support Widget -->
    <?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>
</body>
</html>

