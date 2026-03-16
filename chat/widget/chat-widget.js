/**
 * Live Chat Widget JavaScript
 * Self-hosted chat support system
 */

(function() {
    'use strict';
    
    // Configuration
    const CONFIG = {
        apiBase: '/chat/api',
        pollInterval: 2000, // 2 seconds
        typingTimeout: 3000, // 3 seconds
        maxFileSize: 5 * 1024 * 1024, // 5MB
        allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain']
    };
    
    // State
    let chatState = {
        chatId: null,
        sessionToken: null,
        lastMessageId: 0,
        lastSeenMessageId: 0, // Track last seen message ID to prevent duplicate sounds
        isTyping: false,
        typingTimeout: null,
        pollInterval: null,
        isInitialized: false,
        userInfo: null,
        soundEnabled: true,
        audioContext: null,
        notificationSound: null,
        isFirstLoad: true // Track if this is the first load to prevent sound on initial load
    };
    
    // DOM Elements (will be initialized)
    let elements = {};
    
    /**
     * Initialize the chat widget
     */
    function init() {
        if (chatState.isInitialized) return;
        
        // Initialize sound system
        initSoundSystem();
        
        // Create widget HTML
        createWidgetHTML();
        
        // Get DOM elements
        elements = {
            container: document.getElementById('chat-widget-container'),
            button: document.getElementById('chat-widget-button'),
            window: document.getElementById('chat-widget-window'),
            close: document.getElementById('chat-widget-close'),
            body: document.getElementById('chat-widget-body'),
            messages: document.getElementById('chat-widget-messages'),
            welcome: document.getElementById('chat-widget-welcome'),
            userForm: document.getElementById('chat-widget-user-form'),
            input: document.getElementById('chat-widget-input'),
            send: document.getElementById('chat-widget-send'),
            fileInput: document.getElementById('chat-widget-file-input'),
            fileButton: document.querySelector('.chat-file-button'),
            notificationBadge: document.querySelector('.notification-badge'),
            inputArea: document.getElementById('chat-widget-input-area')
        };
        
        // Ensure chat window is closed by default
        if (elements.window) {
            elements.window.classList.remove('active');
        }
        if (elements.overlay) {
            elements.overlay.classList.remove('active');
        }
        
        // Get overlay element
        elements.overlay = document.getElementById('chat-widget-overlay');
        
        // Ensure chat window is closed by default (double check)
        if (elements.window) {
            elements.window.classList.remove('active');
        }
        if (elements.overlay) {
            elements.overlay.classList.remove('active');
        }
        
        // Event listeners
        elements.button.addEventListener('click', toggleChat);
        elements.close.addEventListener('click', closeChat);
        if (elements.overlay) {
            elements.overlay.addEventListener('click', function(e) {
                // Only close if clicking the overlay itself, not the window
                if (e.target === elements.overlay) {
                    closeChat(e);
                }
            });
        }
        
        // Prevent window clicks from closing when clicking inside
        if (elements.window) {
            elements.window.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        
        // Only add event listeners if elements exist
        if (elements.send) {
            elements.send.addEventListener('click', sendMessage);
        }
        if (elements.input) {
            elements.input.addEventListener('keypress', handleKeyPress);
            elements.input.addEventListener('input', handleTyping);
            
            // Handle input focus - hide placeholder when focused
            elements.input.addEventListener('focus', function() {
                if (this.value === '') {
                    this.placeholder = '';
                }
            });
            
            // Handle input blur - show placeholder if empty
            elements.input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.placeholder = 'Type your message...';
                }
            });
        }
        if (elements.fileButton && elements.fileInput) {
            elements.fileButton.addEventListener('click', () => elements.fileInput.click());
            elements.fileInput.addEventListener('change', handleFileSelect);
        }
        
        // Handle user form submission
        const userForm = elements.userForm;
        if (userForm) {
            userForm.addEventListener('submit', handleUserFormSubmit);
        }
        
        // Get or create session
        initializeSession();
        
        chatState.isInitialized = true;
    }
    
    /**
     * Initialize sound system for notifications
     */
    function initSoundSystem() {
        try {
            // Create audio context for generating notification sound
            chatState.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            // Create a simple notification sound (beep)
            chatState.notificationSound = function() {
                if (!chatState.soundEnabled) return;
                
                try {
                    const oscillator = chatState.audioContext.createOscillator();
                    const gainNode = chatState.audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(chatState.audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.3, chatState.audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, chatState.audioContext.currentTime + 0.3);
                    
                    oscillator.start(chatState.audioContext.currentTime);
                    oscillator.stop(chatState.audioContext.currentTime + 0.3);
                } catch (e) {
                    console.warn('Could not play notification sound:', e);
                }
            };
        } catch (e) {
            console.warn('Could not initialize sound system:', e);
            chatState.soundEnabled = false;
        }
    }
    
    /**
     * Play notification sound
     */
    function playNotificationSound() {
        if (chatState.notificationSound) {
            // Resume audio context if suspended (browser autoplay policy)
            if (chatState.audioContext && chatState.audioContext.state === 'suspended') {
                chatState.audioContext.resume().then(() => {
                    chatState.notificationSound();
                });
            } else {
                chatState.notificationSound();
            }
        }
    }
    
    /**
     * Create widget HTML structure
     */
    function createWidgetHTML() {
        const container = document.createElement('div');
        container.id = 'chat-widget-container';
        container.innerHTML = `
            <button id="chat-widget-button" aria-label="Open chat">
                <svg viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                </svg>
                <span class="notification-badge" style="display: none;">0</span>
            </button>
            <div id="chat-widget-overlay" class="chat-widget-overlay"></div>
            <div id="chat-widget-window" class="">
                <div id="chat-widget-header">
                    <h3>Chat Support</h3>
                    <button id="chat-widget-close" aria-label="Close chat">×</button>
                </div>
                <div id="chat-widget-body">
                    <div id="chat-widget-welcome">
                        <h4>Welcome!</h4>
                        <p>How can we help you today?</p>
                        <form id="chat-widget-user-form">
                            <input type="text" id="user-name" placeholder="Your Name" required>
                            <input type="email" id="user-email" placeholder="Your Email (optional)">
                            <button type="submit">Start Chat</button>
                        </form>
                    </div>
                    <div id="chat-widget-messages"></div>
                </div>
                <div id="chat-widget-input-area" style="display: none;">
                    <div id="chat-widget-input-wrapper">
                        <button class="chat-file-button" aria-label="Attach file">
                            <svg viewBox="0 0 24 24">
                                <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                            </svg>
                        </button>
                        <input type="file" id="chat-widget-file-input" accept="image/*,.pdf,.txt">
                        <textarea id="chat-widget-input" placeholder="Type your message..." rows="1" autocomplete="off" spellcheck="true"></textarea>
                        <button id="chat-widget-send" aria-label="Send message">
                            <svg viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(container);
    }
    
    /**
     * Initialize chat session
     */
    async function initializeSession() {
        try {
            // Check if user is logged in (check for user info in page)
            const isLoggedIn = document.body.dataset.userId || window.userId;
            
            if (isLoggedIn) {
                // User is logged in, create session without form
                const response = await fetch(`${CONFIG.apiBase}/create_session.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        guest_name: '',
                        guest_email: ''
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.session) {
                    chatState.chatId = data.session.id;
                    chatState.sessionToken = data.session.session_token;
                    
                    // Hide welcome form, show chat interface
                    if (elements.welcome) {
                        elements.welcome.style.display = 'none';
                    }
                    if (elements.userForm) {
                        elements.userForm.style.display = 'none';
                    }
                    if (elements.messages) {
                        elements.messages.style.display = 'flex';
                        elements.messages.classList.add('active');
                    }
                    if (elements.inputArea) {
                        elements.inputArea.style.display = 'block';
                        elements.inputArea.classList.add('active');
                    }
                    
                    // Load messages (this will set lastSeenMessageId from sessionStorage)
                    await loadMessages();
                    
                    // After initial load, update lastSeenMessageId to current lastMessageId
                    // This prevents old messages from triggering sounds on subsequent loads
                    if (chatState.lastMessageId > 0) {
                        const storageKey = `chat_last_seen_${chatState.chatId}`;
                        if (!sessionStorage.getItem(storageKey)) {
                            sessionStorage.setItem(storageKey, chatState.lastMessageId.toString());
                            chatState.lastSeenMessageId = chatState.lastMessageId;
                        }
                    }
                    
                    // Start polling
                    startPolling();
                }
            }
            // If not logged in, wait for user to fill form
        } catch (error) {
            console.error('Failed to initialize session:', error);
        }
    }
    
    /**
     * Handle user form submission
     */
    async function handleUserFormSubmit(e) {
        e.preventDefault();
        
        const nameInput = document.getElementById('user-name');
        const emailInput = document.getElementById('user-email');
        
        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        
        if (!name) {
            alert('Please enter your name');
            return;
        }
        
        // Disable form
        const submitButton = e.target.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Starting...';
        
        try {
            // Create session with user info
            const response = await fetch(`${CONFIG.apiBase}/create_session.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    guest_name: name,
                    guest_email: email
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.session) {
                chatState.chatId = data.session.id;
                chatState.sessionToken = data.session.session_token;
                chatState.userInfo = { name, email };
                
                // Set cookie
                setCookie('chat_session_token', data.session.session_token, 30);
                
                // Hide welcome form, show chat
                if (elements.welcome) {
                    elements.welcome.style.display = 'none';
                }
                if (elements.userForm) {
                    elements.userForm.style.display = 'none';
                }
                if (elements.messages) {
                    elements.messages.style.display = 'flex';
                    elements.messages.classList.add('active');
                }
                if (elements.inputArea) {
                    elements.inputArea.style.display = 'block';
                    elements.inputArea.classList.add('active');
                }
                
                // Load messages (this will set lastSeenMessageId from sessionStorage)
                await loadMessages();
                
                // After initial load, update lastSeenMessageId to current lastMessageId
                // This prevents old messages from triggering sounds on subsequent loads
                if (chatState.lastMessageId > 0) {
                    const storageKey = `chat_last_seen_${chatState.chatId}`;
                    if (!sessionStorage.getItem(storageKey)) {
                        sessionStorage.setItem(storageKey, chatState.lastMessageId.toString());
                        chatState.lastSeenMessageId = chatState.lastMessageId;
                    }
                }
                
                // Start polling
                startPolling();
            } else {
                alert('Failed to start chat. Please try again.');
                submitButton.disabled = false;
                submitButton.textContent = 'Start Chat';
            }
        } catch (error) {
            console.error('Failed to start chat:', error);
            alert('Failed to start chat. Please try again.');
            submitButton.disabled = false;
            submitButton.textContent = 'Start Chat';
        }
    }
    
    /**
     * Toggle chat window
     */
    function toggleChat() {
        if (!chatState.chatId && !elements.userForm) {
            // Need to show user form first
            return;
        }
        
        const isOpening = !elements.window.classList.contains('active');
        elements.window.classList.toggle('active');
        
        // Show/hide overlay on mobile
        if (window.innerWidth <= 768 && elements.overlay) {
            if (isOpening) {
                elements.overlay.classList.add('active');
                document.body.classList.add('chat-widget-open');
                // Prevent body scroll - better method for mobile
                const scrollY = window.scrollY;
                document.body.style.position = 'fixed';
                document.body.style.top = `-${scrollY}px`;
                document.body.style.width = '100%';
                document.body.style.overflow = 'hidden';
            } else {
                elements.overlay.classList.remove('active');
                document.body.classList.remove('chat-widget-open');
                // Restore body scroll
                const scrollY = document.body.style.top;
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                document.body.style.overflow = '';
                if (scrollY) {
                    window.scrollTo(0, parseInt(scrollY || '0') * -1);
                }
            }
        }
        
        if (isOpening) {
            // When opening chat, mark all current messages as seen
            if (chatState.chatId) {
                const storageKey = `chat_last_seen_${chatState.chatId}`;
                sessionStorage.setItem(storageKey, chatState.lastMessageId.toString());
                chatState.lastSeenMessageId = chatState.lastMessageId;
                // Clear notification badge when opening
                updateNotificationBadge(0);
            }
            
            // Ensure input area is visible when opening chat
            if (chatState.chatId && elements.inputArea) {
                elements.inputArea.style.display = 'block';
                elements.inputArea.classList.add('active');
            }
            if (chatState.chatId && elements.messages) {
                elements.messages.style.display = 'flex';
                elements.messages.classList.add('active');
            }
            
            // Focus input when opened and clear placeholder on focus
            setTimeout(() => {
                if (elements.input && chatState.chatId) {
                    elements.input.focus();
                    // Ensure cursor is visible
                    if (elements.input.value === '') {
                        elements.input.placeholder = '';
                    }
                }
            }, 100);
        }
    }
    
    /**
     * Close chat window
     */
    function closeChat(e) {
        // Prevent event bubbling if called from overlay click
        if (e) {
            e.stopPropagation();
        }
        
        elements.window.classList.remove('active');
        
        // Hide overlay and restore body scroll on mobile
        if (window.innerWidth <= 768) {
            if (elements.overlay) {
                elements.overlay.classList.remove('active');
            }
            document.body.classList.remove('chat-widget-open');
            // Restore body scroll
            const scrollY = document.body.style.top;
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            document.body.style.overflow = '';
            if (scrollY) {
                window.scrollTo(0, parseInt(scrollY || '0') * -1);
            }
        }
    }
    
    /**
     * Load messages
     */
    async function loadMessages() {
        if (!chatState.chatId) return;
        
        try {
            // Get last seen message ID from sessionStorage to prevent duplicate sounds
            const storageKey = `chat_last_seen_${chatState.chatId}`;
            if (chatState.isFirstLoad) {
                const storedLastSeen = sessionStorage.getItem(storageKey);
                if (storedLastSeen) {
                    chatState.lastSeenMessageId = parseInt(storedLastSeen, 10) || 0;
                }
                chatState.isFirstLoad = false;
            }
            
            const url = `${CONFIG.apiBase}/get_messages.php?chat_id=${chatState.chatId}&last_message_id=${chatState.lastMessageId}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                // Add new messages
                if (data.messages && data.messages.length > 0) {
                    let hasNewAdminMessage = false;
                    let newAdminMessageCount = 0;
                    
                    data.messages.forEach(msg => {
                        // Only consider messages as "new" if they have a higher ID than last seen
                        const isNewMessage = msg.id > chatState.lastSeenMessageId;
                        
                        // Check if this is a new admin message (not from current user) that we haven't seen
                        if (msg.sender_role === 'admin' && isNewMessage) {
                            hasNewAdminMessage = true;
                            newAdminMessageCount++;
                        }
                        
                        addMessageToUI(msg);
                        chatState.lastMessageId = Math.max(chatState.lastMessageId, msg.id);
                    });
                    
                    // Update last seen message ID
                    if (data.messages.length > 0) {
                        const maxMessageId = Math.max(...data.messages.map(m => m.id));
                        chatState.lastSeenMessageId = Math.max(chatState.lastSeenMessageId, maxMessageId);
                        // Store in sessionStorage
                        sessionStorage.setItem(storageKey, chatState.lastSeenMessageId.toString());
                    }
                    
                    // Only play sound for genuinely new admin messages (not on first load of old messages)
                    if (hasNewAdminMessage && newAdminMessageCount > 0) {
                        // Play sound notification for new admin messages
                        playNotificationSound();
                        
                        // Show notification if chat is closed
                        if (!elements.window.classList.contains('active')) {
                            updateNotificationBadge(newAdminMessageCount);
                        } else {
                            // If chat is open, clear notification badge
                            updateNotificationBadge(0);
                        }
                    } else if (!elements.window.classList.contains('active')) {
                        // Update badge count even if no new messages (for existing unread)
                        updateNotificationBadge(data.messages.length);
                    } else {
                        // Chat is open, clear badge
                        updateNotificationBadge(0);
                    }
                    
                    // Ensure messages container and input area are visible
                    if (elements.messages && !elements.messages.classList.contains('active')) {
                        elements.messages.style.display = 'flex';
                        elements.messages.classList.add('active');
                    }
                    if (elements.inputArea && !elements.inputArea.classList.contains('active')) {
                        elements.inputArea.style.display = 'block';
                        elements.inputArea.classList.add('active');
                    }
                    
                    // Scroll to bottom
                    scrollToBottom();
                } else {
                    // No new messages, but ensure UI is visible if chat is active
                    if (elements.window.classList.contains('active')) {
                        updateNotificationBadge(0);
                        // Still ensure messages and input are visible
                        if (elements.messages && chatState.chatId) {
                            elements.messages.style.display = 'flex';
                            elements.messages.classList.add('active');
                        }
                        if (elements.inputArea && chatState.chatId) {
                            elements.inputArea.style.display = 'block';
                            elements.inputArea.classList.add('active');
                        }
                    }
                }
                
                // Update typing indicator
                updateTypingIndicator(data.typing || []);
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
        }
    }
    
    /**
     * Send message
     */
    async function sendMessage() {
        const message = elements.input.value.trim();
        const file = elements.fileInput.files[0];
        
        if (!message && !file) return;
        if (!chatState.chatId) return;
        
        // Disable send button
        elements.send.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('chat_id', chatState.chatId);
            formData.append('message', message);
            formData.append('csrf_token', getCSRFToken());
            
            if (file) {
                formData.append('attachment', file);
            }
            
            const response = await fetch(`${CONFIG.apiBase}/send_message.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear input
                elements.input.value = '';
                elements.fileInput.value = '';
                
                // Add message to UI immediately
                addMessageToUI(data.message);
                scrollToBottom();
                
                // Update last message ID
                chatState.lastMessageId = Math.max(chatState.lastMessageId, data.message.id);
            } else {
                alert(data.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            alert('Failed to send message. Please try again.');
        } finally {
            elements.send.disabled = false;
            elements.input.focus();
        }
    }
    
    /**
     * Handle key press in input
     */
    function handleKeyPress(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }
    
    /**
     * Handle typing indicator
     */
    function handleTyping() {
        if (!chatState.chatId) return;
        
        // Clear existing timeout
        if (chatState.typingTimeout) {
            clearTimeout(chatState.typingTimeout);
        }
        
        // Set typing indicator
        if (!chatState.isTyping) {
            chatState.isTyping = true;
            sendTypingIndicator();
        }
        
        // Clear typing indicator after timeout
        chatState.typingTimeout = setTimeout(() => {
            chatState.isTyping = false;
        }, CONFIG.typingTimeout);
    }
    
    /**
     * Send typing indicator
     */
    async function sendTypingIndicator() {
        if (!chatState.chatId) return;
        
        try {
            const formData = new URLSearchParams({
                chat_id: chatState.chatId,
                csrf_token: getCSRFToken()
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
    
    /**
     * Handle file selection
     */
    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file
        if (file.size > CONFIG.maxFileSize) {
            alert('File size exceeds 5MB limit');
            e.target.value = '';
            return;
        }
        
        if (!CONFIG.allowedTypes.includes(file.type)) {
            alert('File type not allowed. Please upload images, PDF, or text files.');
            e.target.value = '';
            return;
        }
        
        // Auto-send if it's an image
        if (file.type.startsWith('image/')) {
            sendMessage();
        }
    }
    
    /**
     * Add message to UI
     */
    function addMessageToUI(message) {
        // Ensure messages container is visible
        if (elements.messages && !elements.messages.classList.contains('active')) {
            elements.messages.style.display = 'flex';
            elements.messages.classList.add('active');
        }
        
        // Ensure input area is visible
        if (elements.inputArea && !elements.inputArea.classList.contains('active')) {
            elements.inputArea.style.display = 'block';
            elements.inputArea.classList.add('active');
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${message.sender_role}`;
        
        const time = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        let attachmentHTML = '';
        if (message.attachment_url) {
            if (message.attachment_type === 'image') {
                attachmentHTML = `
                    <div class="chat-message-attachment">
                        <img src="${message.attachment_url}" alt="Attachment" onclick="window.open('${message.attachment_url}', '_blank')">
                    </div>
                `;
            } else {
                attachmentHTML = `
                    <div class="chat-message-attachment">
                        <a href="${message.attachment_url}" target="_blank" download>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                            </svg>
                            Download File
                        </a>
                    </div>
                `;
            }
        }
        
        messageDiv.innerHTML = `
            <div class="chat-message-content">
                <p class="chat-message-text">${escapeHtml(message.message_text)}</p>
                ${attachmentHTML}
                <div class="chat-message-time">${time}</div>
            </div>
        `;
        
        elements.messages.appendChild(messageDiv);
    }
    
    /**
     * Update typing indicator
     */
    function updateTypingIndicator(typing) {
        // Remove existing typing indicator
        const existing = elements.messages.querySelector('.chat-typing-indicator');
        if (existing) {
            existing.remove();
        }
        
        // Add typing indicator if someone is typing
        if (typing && typing.length > 0) {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'chat-typing-indicator';
            typingDiv.innerHTML = '<span></span><span></span><span></span>';
            elements.messages.appendChild(typingDiv);
            scrollToBottom();
        }
    }
    
    /**
     * Start polling for new messages
     */
    function startPolling() {
        if (chatState.pollInterval) {
            clearInterval(chatState.pollInterval);
        }
        
        chatState.pollInterval = setInterval(() => {
            if (chatState.chatId) {
                loadMessages();
            }
        }, CONFIG.pollInterval);
    }
    
    /**
     * Scroll to bottom of messages
     */
    function scrollToBottom() {
        elements.body.scrollTop = elements.body.scrollHeight;
    }
    
    /**
     * Update notification badge
     */
    function updateNotificationBadge(count) {
        if (elements.notificationBadge) {
            if (count > 0) {
                elements.notificationBadge.textContent = count > 99 ? '99+' : count;
                elements.notificationBadge.style.display = 'flex';
            } else {
                elements.notificationBadge.style.display = 'none';
            }
        }
    }
    
    /**
     * Get CSRF token from meta tag or cookie
     */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        return '';
    }
    
    /**
     * Get cookie value
     */
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
    
    /**
     * Set cookie
     */
    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = `expires=${date.toUTCString()}`;
        document.cookie = `${name}=${value};${expires};path=/;SameSite=Lax`;
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Format time
     */
    function formatTime(dateString) {
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
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose to global scope for debugging
    window.ChatWidget = {
        init,
        toggleChat,
        closeChat,
        sendMessage,
        state: chatState
    };
    
})();

