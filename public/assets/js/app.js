console.log('App JS works');

(function() {
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');

    if (!searchInput || !searchResults) return;

    let searchTimeout;

    // Handle input event
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 1) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });

    // Handle click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            searchResults.style.display = 'none';
        }
    });

    // Perform search via AJAX
    function performSearch(query) {
        fetch('/ajax.php?action=find_words&q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data && data.data.length > 0) {
                    displayResults(data.data);
                } else {
                    displayNoResults();
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                displayNoResults();
            });
    }

    // Display search results
    function displayResults(results) {
        let html = '';

        results.forEach(item => {
            html += `
                <div class="search-result-item">
                    <div class="search-result-item-content">
                        <div>
                            <div class="search-result-text">${escapeHtml(item.text)}</div>
                            <div class="search-result-translate">${escapeHtml(item.translate)}</div>
                        </div>
                    </div>
                </div>
            `;
        });

        searchResults.innerHTML = html;
        searchResults.style.display = 'block';
    }

    // Display no results message
    function displayNoResults() {
        searchResults.innerHTML = '<div class="search-no-results">Nothing found</div>';
        searchResults.style.display = 'block';
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
})();

(function() {
    const buttons = document.querySelectorAll('.word-sound-icon[data-speech-text]');

    if (!buttons.length || !('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
        return;
    }

    let activeButton = null;
    let activeUtterance = null;
    let voices = [];

    function loadVoices() {
        voices = window.speechSynthesis.getVoices() || [];
    }

    loadVoices();
    window.speechSynthesis.addEventListener('voiceschanged', loadVoices);

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const text = this.getAttribute('data-speech-text') || '';

            if (!text.trim()) return;

            if (activeButton === this && this.classList.contains('is-playing')) {
                stopSpeech();
                return;
            }

            stopSpeech();
            speak(text, this);
        });
    });

    function speak(text, button) {
        const utterance = new SpeechSynthesisUtterance(text);
        const voice = voices.find(item => /^en[-_]/i.test(item.lang));

        utterance.lang = 'en-US';

        if (voice) {
            utterance.voice = voice;
        }

        utterance.onend = function() {
            resetButton(button);
        };

        utterance.onerror = function() {
            resetButton(button);
        };

        activeButton = button;
        activeUtterance = utterance;
        button.classList.add('is-playing');
        button.setAttribute('aria-label', 'Stop pronunciation');
        button.setAttribute('title', 'Stop pronunciation');

        window.speechSynthesis.speak(utterance);
    }

    function stopSpeech() {
        if (activeButton) {
            resetButton(activeButton);
        }

        if (activeUtterance || window.speechSynthesis.speaking || window.speechSynthesis.pending) {
            window.speechSynthesis.cancel();
        }

        activeUtterance = null;
    }

    function resetButton(button) {
        button.classList.remove('is-playing');
        button.setAttribute('aria-label', 'Play pronunciation');
        button.setAttribute('title', 'Play pronunciation');

        if (activeButton === button) {
            activeButton = null;
            activeUtterance = null;
        }
    }
})();

(function() {
    const root = document.querySelector('[data-account-tabs]');

    if (!root) {
        return;
    }

    const buttons = root.querySelectorAll('[data-account-tab]');
    const panels = root.querySelectorAll('[data-account-panel]');

    root.classList.add('is-enhanced');

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const tab = this.dataset.accountTab;

            buttons.forEach(item => {
                const isActive = item === this;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach(panel => {
                panel.classList.toggle('is-active', panel.dataset.accountPanel === tab);
            });
        });
    });
})();
