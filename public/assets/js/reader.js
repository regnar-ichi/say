// Reader display settings

(function() {
    const storageKey = 'readerSettings';
    const root = document.querySelector('[data-reader-root]');

    if (!root) return;

    initWordCards(root);

    const settings = document.querySelector('[data-reader-settings]');
    const toggle = document.querySelector('[data-reader-settings-toggle]');
    const panel = document.querySelector('[data-reader-settings-panel]');

    if (!settings || !toggle || !panel) return;

    const defaults = {
        size: 2,
        font: 'serif',
        theme: 'light',
        width: 'normal'
    };

    let state = loadSettings();

    applySettings();
    updateActiveButtons();

    toggle.addEventListener('click', function() {
        const isOpen = settings.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    panel.addEventListener('click', function(event) {
        const button = event.target.closest('button');

        if (!button) return;

        if (button.dataset.readerSize === 'down') {
            state.size = Math.max(0, state.size - 1);
        }

        if (button.dataset.readerSize === 'up') {
            state.size = Math.min(4, state.size + 1);
        }

        if (button.dataset.readerFont) {
            state.font = button.dataset.readerFont;
        }

        if (button.dataset.readerTheme) {
            state.theme = button.dataset.readerTheme;
        }

        if (button.dataset.readerWidth) {
            state.width = button.dataset.readerWidth;
        }

        saveSettings();
        applySettings();
        updateActiveButtons();
    });

    document.addEventListener('click', function(event) {
        if (!settings.contains(event.target)) {
            settings.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    function loadSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');

            return {
                size: clampSize(Number.isInteger(saved.size) ? saved.size : defaults.size),
                font: ['serif', 'sans'].includes(saved.font) ? saved.font : defaults.font,
                theme: ['light', 'sepia', 'dark'].includes(saved.theme) ? saved.theme : defaults.theme,
                width: ['narrow', 'normal', 'wide'].includes(saved.width) ? saved.width : defaults.width
            };
        } catch (error) {
            return { ...defaults };
        }
    }

    function saveSettings() {
        localStorage.setItem(storageKey, JSON.stringify(state));
    }

    function applySettings() {
        root.dataset.readerSize = String(clampSize(state.size));
        root.dataset.readerFont = state.font;
        root.dataset.readerTheme = state.theme;
        root.dataset.readerWidth = state.width;
    }

    function updateActiveButtons() {
        panel.querySelectorAll('button').forEach(button => {
            let isActive = false;

            if (button.dataset.readerFont) {
                isActive = button.dataset.readerFont === state.font;
            }

            if (button.dataset.readerTheme) {
                isActive = button.dataset.readerTheme === state.theme;
            }

            if (button.dataset.readerWidth) {
                isActive = button.dataset.readerWidth === state.width;
            }

            button.classList.toggle('is-active', isActive);
        });
    }

    function clampSize(size) {
        if (Number.isNaN(size)) return defaults.size;

        return Math.max(0, Math.min(4, size));
    }

    function initWordCards(readerRoot) {
        const words = readerRoot.querySelectorAll('.reader-word[data-meanings]');

        if (!words.length) return;

        const hoverMedia = window.matchMedia('(hover: hover) and (pointer: fine)');
        const card = document.createElement('div');
        let activeWord = null;
        let closeTimer = null;

        card.className = 'reader-word-card';
        card.setAttribute('role', 'dialog');
        readerRoot.appendChild(card);

        words.forEach(word => {
            word.addEventListener('mouseenter', () => {
                if (hoverMedia.matches) {
                    openCard(word);
                }
            });

            word.addEventListener('mouseleave', scheduleClose);
            word.addEventListener('focusin', () => openCard(word));
            word.addEventListener('focusout', scheduleClose);

            word.addEventListener('click', event => {
                if (hoverMedia.matches) return;

                event.preventDefault();
                event.stopPropagation();

                if (activeWord === word && card.classList.contains('is-open')) {
                    closeCard();
                    return;
                }

                openCard(word);
            });
        });

        card.addEventListener('mouseenter', cancelClose);
        card.addEventListener('mouseleave', scheduleClose);
        card.addEventListener('click', event => event.stopPropagation());

        document.addEventListener('click', event => {
            if (!activeWord) return;
            if (card.contains(event.target)) return;
            if (event.target instanceof Element && event.target.closest('.reader-word[data-meanings]')) return;

            closeCard();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeCard();
            }
        });

        window.addEventListener('resize', () => {
            if (activeWord) {
                positionCard(activeWord);
            }
        });

        window.addEventListener('scroll', () => {
            if (activeWord) {
                positionCard(activeWord);
            }
        }, true);

        function openCard(word) {
            const meanings = parseMeanings(word);

            if (!meanings.length) return;

            cancelClose();

            if (activeWord && activeWord !== word) {
                activeWord.classList.remove('is-card-open');
            }

            activeWord = word;
            activeWord.classList.add('is-card-open');
            card.innerHTML = renderMeanings(meanings);
            card.classList.add('is-open');
            positionCard(word);
        }

        function closeCard() {
            cancelClose();

            if (activeWord) {
                activeWord.classList.remove('is-card-open');
            }

            activeWord = null;
            card.classList.remove('is-open');
            card.innerHTML = '';
        }

        function scheduleClose() {
            cancelClose();
            closeTimer = window.setTimeout(closeCard, 140);
        }

        function cancelClose() {
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            }
        }

        function parseMeanings(word) {
            try {
                const parsed = JSON.parse(word.dataset.meanings || '[]');

                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function renderMeanings(meanings) {
            return meanings.map(meaning => {
                const word = meaning.word || meaning.text || '';
                const type = meaning.type || '';
                const translation = meaning.translation || '';
                const heading = [type, translation].filter(Boolean).join(' - ');

                return [
                    '<section class="reader-word-card-section">',
                    word ? '<div class="reader-word-card-word">' + escapeHtml(word) + '</div>' : '',
                    heading ? '<div class="reader-word-card-heading">' + renderHeading(type, translation) + '</div>' : '',
                    meaning.transcription ? '<div class="reader-word-card-line">' + escapeHtml(meaning.transcription) + '</div>' : '',
                    meaning.example ? '<div class="reader-word-card-example">' + escapeHtml(meaning.example) + '</div>' : '',
                    meaning.example_ru ? '<div class="reader-word-card-example-ru">' + escapeHtml(meaning.example_ru) + '</div>' : '',
                    '</section>'
                ].join('');
            }).join('');
        }

        function renderHeading(type, translation) {
            const parts = [];

            if (type) {
                parts.push('<span class="reader-word-card-type">' + escapeHtml(type) + '</span>');
            }

            if (type && translation) {
                parts.push('<span aria-hidden="true">-</span>');
            }

            if (translation) {
                parts.push('<span class="reader-word-card-translation">' + escapeHtml(translation) + '</span>');
            }

            return parts.join('');
        }

        function positionCard(word) {
            const margin = 12;
            const gap = 10;
            const wordRect = word.getBoundingClientRect();

            card.style.visibility = 'hidden';
            card.style.left = '0px';
            card.style.top = '0px';

            const cardRect = card.getBoundingClientRect();
            let left = wordRect.left + (wordRect.width / 2) - (cardRect.width / 2);
            let top = wordRect.bottom + gap;

            if (top + cardRect.height + margin > window.innerHeight) {
                top = wordRect.top - cardRect.height - gap;
            }

            left = Math.max(margin, Math.min(left, window.innerWidth - cardRect.width - margin));
            top = Math.max(margin, Math.min(top, window.innerHeight - cardRect.height - margin));

            card.style.left = `${left}px`;
            card.style.top = `${top}px`;
            card.style.visibility = 'visible';
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    }
})();
