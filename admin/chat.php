<?php
/**
 * Admin Chat Management Panel
 * Live Chat Support System - Redesigned
 */

require_once '../config/database.php';
requireAdmin();

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;

// Get chat statistics
try {
    $stmt = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE status = 'open'");
    $open_chats = (int)$stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM chat_sessions WHERE status = 'closed'");
    $closed_chats = (int)$stmt->fetchColumn();
    
    // Count all unread chats regardless of status (not just open)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT cm.chat_id) 
        FROM chat_messages cm
        JOIN chat_sessions cs ON cm.chat_id = cs.id
        WHERE cm.sender_role = 'user' AND cm.is_read = 0
    ");
    $unread_chats = (int)$stmt->fetchColumn();
    
    // Get total unread messages count (all statuses)
    $stmt = $db->query("
        SELECT COUNT(*) as total_unread
        FROM chat_messages cm
        JOIN chat_sessions cs ON cm.chat_id = cs.id
        WHERE cm.sender_role = 'user' AND cm.is_read = 0
    ");
    $total_unread = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Chat admin stats error: " . $e->getMessage());
    $open_chats = 0;
    $closed_chats = 0;
    $unread_chats = 0;
    $total_unread = 0;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat Management - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .chat-list-item {
            transition: all 0.2s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }
        
        .chat-list-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .chat-list-item.active {
            background: rgba(3, 169, 244, 0.2);
            border-left-color: #03a9f4;
        }
        
        .chat-list-item.unread {
            background: rgba(255, 68, 68, 0.15);
            border-left-color: #ff4444;
            font-weight: 600;
        }
        
        .chat-list-item.unread.active {
            background: rgba(3, 169, 244, 0.25);
            border-left-color: #03a9f4;
        }
        
        .message-bubble {
            max-width: 75%;
            word-wrap: break-word;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-user {
            background: linear-gradient(135deg, #03a9f4 0%, #0288d1 100%);
            color: white;
            border-radius: 18px 18px 4px 18px;
            box-shadow: 0 2px 8px rgba(3, 169, 244, 0.3);
        }
        
        .message-admin {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-radius: 18px 18px 18px 4px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .typing-indicator {
            display: inline-flex;
            gap: 4px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 18px;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            animation: typing 1.4s infinite;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.7;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        .chat-messages-container {
            height: calc(100vh - 400px);
            min-height: 500px;
            overflow-y: auto;
            padding: 20px;
        }
        
        .chat-messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .chat-messages-container::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        .chat-messages-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        
        .chat-messages-container::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .unread-badge {
            background: #ff4444;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        
        #message-input {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            caret-color: #03a9f4;
        }
        
        #message-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        #message-input:focus {
            outline: none;
            border-color: #03a9f4;
            box-shadow: 0 0 0 3px rgba(3, 169, 244, 0.2);
        }
        
        #message-input:focus::placeholder {
            color: transparent;
        }
        
        /* Delete Confirmation Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }
        
        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .modal-icon i {
            font-size: 32px;
            color: #ef4444;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 12px;
            color: white;
        }
        
        .modal-message {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            font-size: 16px;
        }
        
        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .modal-btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .modal-btn-delete:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.5);
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- Admin Header -->
<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-6">
                <a href="index.php" class="text-xl font-bold text-purple-400">Ultra Harvest Admin</a>
                <nav class="hidden md:flex space-x-4">
                    <a href="index.php" class="px-3 py-2 rounded hover:bg-gray-700">Dashboard</a>
                    <a href="users.php" class="px-3 py-2 rounded hover:bg-gray-700">Users</a>
                    <a href="transactions.php" class="px-3 py-2 rounded hover:bg-gray-700">Transactions</a>
                    <a href="chat.php" class="px-3 py-2 rounded bg-purple-600 text-white relative">
                        Live Chat
                        <?php if ($unread_chats > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $unread_chats; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="tickets.php" class="px-3 py-2 rounded hover:bg-gray-700">Tickets</a>
                </nav>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-400"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                <a href="../logout.php" class="px-4 py-2 bg-red-600 rounded hover:bg-red-700">Logout</a>
            </div>
        </div>
    </div>
</header>

<div class="container mx-auto px-4 py-6">
    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-lg p-6 hover:bg-opacity-80 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm mb-1">Open Chats</p>
                    <p class="text-3xl font-bold text-green-400"><?php echo $open_chats; ?></p>
                </div>
                <i class="fas fa-comments text-green-400 text-4xl opacity-50"></i>
            </div>
        </div>
        <div class="glass-card rounded-lg p-6 hover:bg-opacity-80 transition bg-red-500/10 border-red-500/30">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm mb-1">Unread Chats</p>
                    <p class="text-3xl font-bold text-red-400"><?php echo $unread_chats; ?></p>
                </div>
                <i class="fas fa-exclamation-circle text-red-400 text-4xl opacity-50"></i>
            </div>
        </div>
        <div class="glass-card rounded-lg p-6 hover:bg-opacity-80 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm mb-1">Total Unread</p>
                    <p class="text-3xl font-bold text-yellow-400"><?php echo $total_unread; ?></p>
                </div>
                <i class="fas fa-envelope text-yellow-400 text-4xl opacity-50"></i>
            </div>
        </div>
        <div class="glass-card rounded-lg p-6 hover:bg-opacity-80 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm mb-1">Closed Chats</p>
                    <p class="text-3xl font-bold text-gray-400"><?php echo $closed_chats; ?></p>
                </div>
                <i class="fas fa-archive text-gray-400 text-4xl opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Main Chat Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Chat List - Wider -->
        <div class="lg:col-span-1 glass-card rounded-lg p-4">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-700">
                <h2 class="text-xl font-bold">All Chats</h2>
                <select id="status-filter" class="bg-gray-800 border border-gray-700 rounded px-3 py-1 text-sm text-white">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <div id="chat-list" class="space-y-2 max-h-[calc(100vh-300px)] overflow-y-auto">
                <!-- Chat list will be loaded here -->
            </div>
        </div>

        <!-- Chat Window - Wider -->
        <div class="lg:col-span-3 glass-card rounded-lg p-4 flex flex-col">
            <div id="chat-window-empty" class="flex-1 flex items-center justify-center text-gray-400 min-h-[500px]">
                <div class="text-center">
                    <i class="fas fa-comments text-6xl mb-4 opacity-30"></i>
                    <p class="text-xl mb-2">No chat selected</p>
                    <p class="text-sm">Select a chat from the list to view messages</p>
                </div>
            </div>
            
            <div id="chat-window-active" class="hidden flex flex-col flex-1">
                <!-- Chat Header -->
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-700">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-xl font-bold">
                            <span id="chat-user-initial">U</span>
                        </div>
                    <div>
                        <h3 id="chat-user-name" class="text-lg font-bold"></h3>
                        <p id="chat-user-email" class="text-sm text-gray-400"></p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button id="close-chat-btn" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Close
                        </button>
                        <button id="archive-chat-btn" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition">
                            <i class="fas fa-archive mr-2"></i>Archive
                        </button>
                        <button id="delete-chat-btn" class="px-4 py-2 bg-red-600 rounded hover:bg-red-700 transition">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div id="chat-messages" class="chat-messages-container flex-1 mb-4 space-y-4">
                    <!-- Messages will be loaded here -->
                </div>

                <!-- Typing Indicator -->
                <div id="typing-indicator" class="hidden mb-4 px-4">
                    <div class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-gray-700 pt-4">
                    <form id="message-form" class="flex gap-3 items-end">
                        <input type="hidden" id="current-chat-id" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="file" id="file-input" class="hidden" accept="image/*,.pdf,.txt">
                        <button type="button" id="file-btn" class="px-4 py-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition flex-shrink-0">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea 
                            id="message-input" 
                            class="flex-1 rounded-lg px-4 py-3 resize-none" 
                            rows="2" 
                            placeholder="Type your message here..."
                        ></textarea>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg hover:from-blue-600 hover:to-purple-700 transition flex-shrink-0 font-semibold">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete Chat</h3>
        <p class="modal-message">
            Are you sure you want to permanently delete this chat?<br>
            <strong>This action cannot be undone.</strong><br>
            All messages, attachments, and related data will be permanently deleted.
        </p>
        <div class="modal-buttons">
            <button class="modal-btn modal-btn-cancel" id="cancel-delete-btn">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button class="modal-btn modal-btn-delete" id="confirm-delete-btn">
                <i class="fas fa-trash mr-2"></i>Delete Chat
            </button>
        </div>
    </div>
</div>

<script>
const CONFIG = {
    apiBase: '/chat/api',
    pollInterval: 2000,
    typingTimeout: 3000
};

let state = {
    currentChatId: null,
    lastMessageId: 0,
    pollInterval: null,
    typingTimeout: null,
    isTyping: false,
    soundEnabled: true,
    audioContext: null,
    loadedChatIds: new Set()
};

// Initialize sound system for admin notifications
function initAdminSoundSystem() {
    try {
        state.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {
        console.warn('Could not initialize sound system:', e);
        state.soundEnabled = false;
    }
}

// Play notification sound for admin
function playAdminNotificationSound() {
    if (!state.soundEnabled || !state.audioContext) return;
    
    try {
        if (state.audioContext.state === 'suspended') {
            state.audioContext.resume().then(() => playSound());
        } else {
            playSound();
        }
    } catch (e) {
        console.warn('Could not play notification sound:', e);
    }
}

function playSound() {
    const oscillator = state.audioContext.createOscillator();
    const gainNode = state.audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(state.audioContext.destination);
    
    oscillator.frequency.value = 600;
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, state.audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, state.audioContext.currentTime + 0.3);
    
    oscillator.start(state.audioContext.currentTime);
    oscillator.stop(state.audioContext.currentTime + 0.3);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initAdminSoundSystem();
    setupEventListeners();
    
    // Show loading state
    const chatList = document.getElementById('chat-list');
    chatList.innerHTML = `
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
            <p>Loading chats...</p>
        </div>
    `;
    
    // Load chats immediately
    loadChatList();
    
    // Start polling for new chats (less frequent to reduce load)
    setInterval(loadChatList, 10000); // Every 10 seconds instead of 5
});

function setupEventListeners() {
    document.getElementById('status-filter').addEventListener('change', function() {
        const status = this.value;
        window.location.href = `chat.php?status=${status}`;
    });
    
    document.getElementById('message-form').addEventListener('submit', sendMessage);
    document.getElementById('file-btn').addEventListener('click', () => {
        document.getElementById('file-input').click();
    });
    document.getElementById('file-input').addEventListener('change', handleFileSelect);
    document.getElementById('message-input').addEventListener('input', handleTyping);
    document.getElementById('message-input').addEventListener('focus', function() {
        if (this.value === '') {
            this.placeholder = '';
        }
    });
    document.getElementById('message-input').addEventListener('blur', function() {
        if (this.value === '') {
            this.placeholder = 'Type your message here...';
        }
    });
    document.getElementById('close-chat-btn').addEventListener('click', closeChat);
    document.getElementById('archive-chat-btn').addEventListener('click', archiveChat);
    document.getElementById('delete-chat-btn').addEventListener('click', showDeleteModal);
    document.getElementById('cancel-delete-btn').addEventListener('click', hideDeleteModal);
    document.getElementById('confirm-delete-btn').addEventListener('click', confirmDeleteChat);
    
    // Close modal when clicking outside
    document.getElementById('delete-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideDeleteModal();
        }
    });
}

// Make function globally accessible
window.loadChatList = async function loadChatList() {
    try {
        const status = document.getElementById('status-filter').value;
        const startTime = Date.now();
        
        // Create abort controller for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        const response = await fetch(`${CONFIG.apiBase}/get_chats.php?status=${status}`, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            },
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const loadTime = Date.now() - startTime;
        console.log(`Chats loaded in ${loadTime}ms`, data);
        
        if (data.success) {
            renderChatList(data.chats || []);
        } else {
            const errorMsg = data.debug ? `${data.error}: ${data.debug}` : data.error || 'Failed to load chats';
            throw new Error(errorMsg);
        }
    } catch (error) {
        console.error('Failed to load chat list:', error);
        const container = document.getElementById('chat-list');
        
        if (error.name === 'AbortError') {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-clock text-yellow-400 text-2xl mb-2"></i>
                    <p class="text-yellow-400 mb-2">Request timeout</p>
                    <p class="text-gray-400 text-sm">The request took too long. Please try again.</p>
                    <button onclick="window.loadChatList()" class="mt-4 px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">
                        <i class="fas fa-redo mr-2"></i>Retry
                    </button>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2"></i>
                    <p class="text-red-400 mb-2">Failed to load chats</p>
                    <p class="text-gray-400 text-sm">${error.message || 'Unknown error'}</p>
                    <button onclick="window.loadChatList()" class="mt-4 px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">
                        <i class="fas fa-redo mr-2"></i>Retry
                    </button>
                </div>
            `;
        }
    }
}

function renderChatList(chats) {
    const container = document.getElementById('chat-list');
    
    // Clear loading state
    container.innerHTML = '';
    
    if (!chats || chats.length === 0) {
        container.innerHTML = '<p class="text-gray-400 text-center py-8">No chats found</p>';
        return;
    }
    
    // Sort chats: unread first, then by last message time
    chats.sort((a, b) => {
        if (a.unread_count > 0 && b.unread_count === 0) return -1;
        if (a.unread_count === 0 && b.unread_count > 0) return 1;
        const timeA = new Date(a.last_message_time || a.created_at || 0);
        const timeB = new Date(b.last_message_time || b.created_at || 0);
        return timeB - timeA;
    });
    
    chats.forEach(chat => {
        const item = document.createElement('div');
        const isUnread = chat.unread_count > 0;
        const isActive = state.currentChatId === chat.id;
        
        item.className = `chat-list-item p-4 rounded-lg ${isActive ? 'active' : ''} ${isUnread ? 'unread' : ''}`;
        item.dataset.chatId = chat.id;
        
        const userName = chat.display_name || chat.guest_name || chat.user_full_name || `User #${chat.user_id || chat.id}`;
        const userInitial = userName.charAt(0).toUpperCase();
        const lastMessage = chat.last_message 
            ? (chat.last_message.length > 40 ? chat.last_message.substring(0, 40) + '...' : chat.last_message)
            : 'No messages yet';
        
        const unreadBadge = isUnread 
            ? `<span class="unread-badge">${chat.unread_count}</span>`
            : '';
        
        item.innerHTML = `
            <div class="flex items-start space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center text-sm font-bold flex-shrink-0">
                    ${userInitial}
                </div>
                <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-sm truncate">${escapeHtml(userName)}</span>
                ${unreadBadge}
                    </div>
                    <p class="text-xs text-gray-400 truncate mb-1">${escapeHtml(lastMessage)}</p>
                    <p class="text-xs text-gray-500">${formatTime(chat.last_message_time || chat.created_at)}</p>
                </div>
            </div>
        `;
        
        item.addEventListener('click', () => openChat(chat.id));
        container.appendChild(item);
    });
}

async function openChat(chatId) {
    // Clear previous chat data
    state.currentChatId = null;
    state.lastMessageId = 0;
    state.loadedChatIds.clear();
    
    // Clear messages container
    document.getElementById('chat-messages').innerHTML = '';
    
    // Stop previous polling
    if (state.pollInterval) {
        clearInterval(state.pollInterval);
        state.pollInterval = null;
    }
    
    // Set new chat
    state.currentChatId = chatId;
    document.getElementById('current-chat-id').value = chatId;
    
    // Update UI
    document.getElementById('chat-window-empty').classList.add('hidden');
    document.getElementById('chat-window-active').classList.remove('hidden');
    
    // Update active chat in list
    document.querySelectorAll('.chat-list-item').forEach(item => {
        const isActive = item.dataset.chatId == chatId;
        item.classList.toggle('active', isActive);
    });
    
    // Load chat details and messages
    await loadChatDetails(chatId);
    await loadMessages(chatId);
    
    // Start polling for new messages
    state.pollInterval = setInterval(() => {
        if (state.currentChatId === chatId) {
            loadMessages(chatId);
            loadChatList(); // Update unread counts
        }
    }, CONFIG.pollInterval);
}

async function loadChatDetails(chatId) {
    try {
        const response = await fetch(`${CONFIG.apiBase}/get_chats.php?status=all`);
        const data = await response.json();
        
        if (data.success) {
            const chat = data.chats.find(c => c.id == chatId);
            if (chat) {
                const displayName = chat.display_name || chat.guest_name || chat.user_full_name || `User #${chat.user_id || chat.id}`;
                const displayEmail = chat.display_email || chat.guest_email || chat.user_email || 'No email provided';
                const initial = displayName.charAt(0).toUpperCase();
                
                document.getElementById('chat-user-name').textContent = displayName;
                document.getElementById('chat-user-email').textContent = displayEmail;
                document.getElementById('chat-user-initial').textContent = initial;
            }
        }
    } catch (error) {
        console.error('Failed to load chat details:', error);
    }
}

async function loadMessages(chatId) {
    try {
        const url = `${CONFIG.apiBase}/get_messages.php?chat_id=${chatId}&last_message_id=${state.lastMessageId}`;
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            if (data.messages && data.messages.length > 0) {
                let hasNewUserMessage = false;
                
                data.messages.forEach(msg => {
                    // Only add if not already loaded
                    if (!state.loadedChatIds.has(msg.id)) {
                        if (msg.sender_role === 'user') {
                            hasNewUserMessage = true;
                        }
                    addMessageToUI(msg);
                        state.loadedChatIds.add(msg.id);
                    state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                    }
                });
                
                // Play sound notification for new user messages
                if (hasNewUserMessage) {
                    playAdminNotificationSound();
                }
                
                scrollToBottom();
            }
            
            // Update typing indicator
            updateTypingIndicator(data.typing || []);
        }
    } catch (error) {
        console.error('Failed to load messages:', error);
    }
}

function addMessageToUI(message) {
    const container = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `flex ${message.sender_role === 'admin' ? 'justify-end' : 'justify-start'} mb-4`;
    
    const bubbleClass = message.sender_role === 'admin' ? 'message-admin' : 'message-user';
    const time = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const date = new Date(message.created_at).toLocaleDateString();
    
    let attachmentHTML = '';
    if (message.attachment_url) {
        if (message.attachment_type === 'image') {
            attachmentHTML = `<div class="mt-2 rounded-lg overflow-hidden"><img src="${message.attachment_url}" alt="Attachment" class="max-w-xs cursor-pointer hover:opacity-90 transition" onclick="window.open('${message.attachment_url}', '_blank')"></div>`;
        } else {
            attachmentHTML = `<a href="${message.attachment_url}" target="_blank" class="mt-2 inline-flex items-center gap-2 px-3 py-2 bg-white/10 rounded-lg text-sm hover:bg-white/20 transition"><i class="fas fa-download"></i> Download File</a>`;
        }
    }
    
    messageDiv.innerHTML = `
        <div class="message-bubble ${bubbleClass} px-4 py-3">
            <p class="mb-1 whitespace-pre-wrap">${escapeHtml(message.message_text)}</p>
            ${attachmentHTML}
            <p class="text-xs mt-2 opacity-70">${time} • ${date}</p>
        </div>
    `;
    
    container.appendChild(messageDiv);
}

function updateTypingIndicator(typing) {
    const indicator = document.getElementById('typing-indicator');
    if (typing && typing.includes('user')) {
        indicator.classList.remove('hidden');
    } else {
        indicator.classList.add('hidden');
    }
}

async function sendMessage(e) {
    e.preventDefault();
    
    const chatId = state.currentChatId;
    const message = document.getElementById('message-input').value.trim();
    const file = document.getElementById('file-input').files[0];
    
    if (!message && !file) return;
    if (!chatId) return;
    
    const formData = new FormData();
    formData.append('chat_id', chatId);
    formData.append('message', message);
    formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    
    if (file) {
        formData.append('attachment', file);
    }
    
    // Disable send button
    const sendBtn = document.querySelector('#message-form button[type="submit"]');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const response = await fetch(`${CONFIG.apiBase}/send_message.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('message-input').value = '';
            document.getElementById('file-input').value = '';
            addMessageToUI(data.message);
            state.loadedChatIds.add(data.message.id);
            state.lastMessageId = Math.max(state.lastMessageId, data.message.id);
            scrollToBottom();
            loadChatList(); // Refresh chat list
        } else {
            alert(data.error || 'Failed to send message');
        }
    } catch (error) {
        console.error('Failed to send message:', error);
        alert('Failed to send message. Please try again.');
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send';
        document.getElementById('message-input').focus();
    }
}

function handleTyping() {
    if (!state.currentChatId) return;
    
    if (state.typingTimeout) {
        clearTimeout(state.typingTimeout);
    }
    
    if (!state.isTyping) {
        state.isTyping = true;
        sendTypingIndicator();
    }
    
    state.typingTimeout = setTimeout(() => {
        state.isTyping = false;
    }, CONFIG.typingTimeout);
}

async function sendTypingIndicator() {
    if (!state.currentChatId) return;
    
    try {
        const formData = new URLSearchParams({
            chat_id: state.currentChatId,
            csrf_token: document.querySelector('[name="csrf_token"]').value
        });
        
        await fetch(`${CONFIG.apiBase}/typing.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
    } catch (error) {
        // Silently fail
    }
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        // Auto-send if it's an image
        if (file.type.startsWith('image/')) {
            sendMessage(e);
        }
    }
}

async function closeChat() {
    if (!state.currentChatId) return;
    
    if (!confirm('Are you sure you want to close this chat?')) return;
    
    try {
        const formData = new URLSearchParams({
            chat_id: state.currentChatId,
            action: 'close',
            csrf_token: document.querySelector('[name="csrf_token"]').value
        });
        
        const response = await fetch(`${CONFIG.apiBase}/close_chat.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadChatList();
            document.getElementById('chat-window-active').classList.add('hidden');
            document.getElementById('chat-window-empty').classList.remove('hidden');
            state.currentChatId = null;
        } else {
            alert(data.error || 'Failed to close chat');
        }
    } catch (error) {
        console.error('Failed to close chat:', error);
        alert('Failed to close chat. Please try again.');
    }
}

async function archiveChat() {
    if (!state.currentChatId) return;
    
    if (!confirm('Are you sure you want to archive this chat?')) return;
    
    try {
        const formData = new URLSearchParams({
            chat_id: state.currentChatId,
            action: 'archive',
            csrf_token: document.querySelector('[name="csrf_token"]').value
        });
        
        const response = await fetch(`${CONFIG.apiBase}/close_chat.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            loadChatList();
            document.getElementById('chat-window-active').classList.add('hidden');
            document.getElementById('chat-window-empty').classList.remove('hidden');
            state.currentChatId = null;
        } else {
            alert(data.error || 'Failed to archive chat');
        }
    } catch (error) {
        console.error('Failed to archive chat:', error);
        alert('Failed to archive chat. Please try again.');
    }
}

function showDeleteModal() {
    if (!state.currentChatId) return;
    document.getElementById('delete-modal').classList.add('active');
}

function hideDeleteModal() {
    document.getElementById('delete-modal').classList.remove('active');
}

async function confirmDeleteChat() {
    if (!state.currentChatId) return;
    
    // Disable buttons during deletion
    const confirmBtn = document.getElementById('confirm-delete-btn');
    const cancelBtn = document.getElementById('cancel-delete-btn');
    confirmBtn.disabled = true;
    cancelBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
    
    try {
        const formData = new URLSearchParams({
            chat_id: state.currentChatId,
            csrf_token: document.querySelector('[name="csrf_token"]').value
        });
        
        const response = await fetch(`${CONFIG.apiBase}/delete_chat.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Hide modal
            hideDeleteModal();
            
            // Clear current chat view
            document.getElementById('chat-window-active').classList.add('hidden');
            document.getElementById('chat-window-empty').classList.remove('hidden');
            document.getElementById('chat-messages').innerHTML = '';
            document.getElementById('message-input').value = '';
            state.currentChatId = null;
            state.lastMessageId = 0;
            state.loadedChatIds.clear();
            
            // Stop polling
            if (state.pollInterval) {
                clearInterval(state.pollInterval);
                state.pollInterval = null;
            }
            
            // Reload chat list
            loadChatList();
            
            // Show success message (optional - you can remove this if you don't want it)
            setTimeout(() => {
                alert('Chat deleted successfully');
            }, 300);
        } else {
            alert(data.error || 'Failed to delete chat');
            // Re-enable buttons on error
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-trash mr-2"></i>Delete Chat';
        }
    } catch (error) {
        console.error('Failed to delete chat:', error);
        alert('Failed to delete chat. Please try again.');
        // Re-enable buttons on error
        confirmBtn.disabled = false;
        cancelBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-trash mr-2"></i>Delete Chat';
    }
}

function scrollToBottom() {
    const container = document.getElementById('chat-messages');
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) return `${days}d ago`;
    if (hours > 0) return `${hours}h ago`;
    if (minutes > 0) return `${minutes}m ago`;
    return 'just now';
}
</script>

</body>
</html>
