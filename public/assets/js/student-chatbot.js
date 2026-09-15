document.addEventListener('DOMContentLoaded', function () {
    const trigger       = document.getElementById('chatbotTrigger');
    const panel         = document.getElementById('chatbotPanel');
    const closeBtn      = document.getElementById('chatbotClose');
    const messagesArea  = document.getElementById('chatbotMessages');
    const input         = document.getElementById('chatbotInput');
    const sendBtn       = document.getElementById('chatbotSend');
    const typingIndicator = document.getElementById('typingIndicator');

    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken     = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';
    const baseUrlMeta   = document.querySelector('meta[name="base-url"]');
    const baseUrl       = baseUrlMeta ? baseUrlMeta.getAttribute('content') : window.location.origin;

    let isSending = false;

    // ── Chatbot Toggle ────────────────────────────────────────────────────
    trigger.addEventListener('click', toggleChatbot);
    closeBtn.addEventListener('click', toggleChatbot);

    function toggleChatbot() {
        const isOpen = panel.classList.toggle('open');
        trigger.classList.toggle('active', isOpen);

        const icon = trigger.querySelector('i');
        if (icon) {
            icon.className = isOpen ? 'bi bi-x-lg' : 'bi bi-chat-dots-fill';
        }

        if (isOpen) {
            setTimeout(() => input.focus(), 320);
        }
    }

    // ── Send on button click or Enter ─────────────────────────────────────
    sendBtn.addEventListener('click', () => sendMessage());
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });

    // ── Suggestion chips ──────────────────────────────────────────────────
    document.querySelectorAll('.suggestion-chip').forEach(chip => {
        chip.addEventListener('click', () => sendMessage(chip.textContent.trim()));
    });

    // ── Load history on first open ────────────────────────────────────────
    loadHistory();

    // ─────────────────────────────────────────────────────────────────────
    async function sendMessage(forcedText = null) {
        if (isSending) return;

        const text = typeof forcedText === 'string' ? forcedText.trim() : input.value.trim();
        if (!text) return;

        isSending = true;
        appendUserMessage(text);
        input.value = '';
        setLoading(true);

        try {
            const body = new URLSearchParams({ message: text, csrf_token: csrfToken });
            const res  = await fetch(baseUrl + '/student/chatbot', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            if (!res.ok) throw new Error('Server error ' + res.status);
            const result = await res.json();
            appendBotMessage(result);

        } catch (err) {
            console.error('Chatbot error:', err);
            appendBotMessage({ response_type: 'ERROR', message: 'Could not connect to the server. Please try again.' });
        } finally {
            setLoading(false);
            isSending = false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    function appendUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'chat-message user';
        div.textContent = text;
        messagesArea.insertBefore(div, typingIndicator);
        scrollBottom();
    }

    // ─────────────────────────────────────────────────────────────────────
    function appendBotMessage(obj) {
        const wrapper = document.createElement('div');
        wrapper.className = 'chat-message bot';

        if (obj.response_type === 'ERROR') {
            wrapper.classList.add('error');
        }

        // Main message (supports **bold** markdown)
        if (obj.message) {
            const msgDiv = document.createElement('div');
            msgDiv.className = 'chat-text';
            renderMarkdown(msgDiv, obj.message);
            wrapper.appendChild(msgDiv);
        }

        // LIST items
        if ((obj.response_type === 'LIST' || obj.response_type === 'HELP') && Array.isArray(obj.items) && obj.items.length > 0) {
            const ul = document.createElement('ul');
            obj.items.forEach(item => {
                const li = document.createElement('li');
                renderMarkdown(li, String(item));
                ul.appendChild(li);
            });
            wrapper.appendChild(ul);
        }

        // AMBIGUOUS options (clickable buttons)
        if (obj.response_type === 'AMBIGUOUS' && Array.isArray(obj.options) && obj.options.length > 0) {
            const optContainer = document.createElement('div');
            optContainer.className = 'chat-options';
            obj.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'chat-option-btn';
                btn.textContent = opt.name;
                btn.addEventListener('click', function () {
                    sendMessage(this.textContent.trim());
                });
                optContainer.appendChild(btn);
            });
            wrapper.appendChild(optContainer);
        }

        messagesArea.insertBefore(wrapper, typingIndicator);
        scrollBottom();
    }

    // ─────────────────────────────────────────────────────────────────────
    /**
     * Safe markdown-like renderer: only handles **bold** and \n line breaks.
     * Uses textContent — never innerHTML — to prevent XSS.
     */
    function renderMarkdown(container, text) {
        // Split by **...**
        const parts = text.split(/\*\*(.+?)\*\*/g);
        parts.forEach((part, i) => {
            if (i % 2 === 1) {
                // Odd index = bold segment
                const strong = document.createElement('strong');
                strong.textContent = part;
                container.appendChild(strong);
            } else {
                // Even index = plain text (handle \n as line breaks)
                const lines = part.split('\n');
                lines.forEach((line, li) => {
                    if (line) container.appendChild(document.createTextNode(line));
                    if (li < lines.length - 1) container.appendChild(document.createElement('br'));
                });
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    function setLoading(isLoading) {
        input.disabled  = isLoading;
        sendBtn.disabled = isLoading;
        typingIndicator.style.display = isLoading ? 'flex' : 'none';
        if (isLoading) scrollBottom();
    }

    function scrollBottom() {
        requestAnimationFrame(() => {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    async function loadHistory() {
        try {
            const res    = await fetch(baseUrl + '/student/chatbot/history');
            const result = await res.json();

            if (result && Array.isArray(result.history) && result.history.length > 0) {
                result.history.forEach(msg => {
                    if (msg.response_type === 'USER' || msg.type === 'user') {
                        appendUserMessage(msg.message || msg.text || '');
                    } else {
                        appendBotMessage(msg);
                    }
                });
            } else {
                appendBotMessage({
                    response_type: 'TEXT',
                    message: 'Hello! I\'m your **NexusCore Assistant**. Ask me about symposiums, events, schedules, or registrations. How can I help?',
                });
            }
        } catch {
            appendBotMessage({
                response_type: 'TEXT',
                message: 'Hello! I\'m your **NexusCore Assistant**. How can I assist you today?',
            });
        }
    }
});
