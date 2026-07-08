// Word cards learning mode

(function() {
    const state = {
        direction: 'all',
        limit: 10,
        levels: [],
        topics: [],
        types: [],
        cards: [],
        index: 0,
        known: 0,
        learning: 0,
        skipped: 0,
        answerState: 'answerHidden',
        isFinished: false
    };

    const startButton = document.getElementById('word-card-start-button');
    const startConfirm = document.getElementById('word-card-start-confirm');
    const settingsModal = document.getElementById('word-card-settings-modal');
    const cardModal = document.getElementById('word-card-modal');
    const container = document.getElementById('word-card-container');
    const knownButton = document.getElementById('word-card-known-button');
    const learningButton = document.getElementById('word-card-learning-button');
    const nextButton = document.getElementById('word-card-next-button');
    const actionFooter = cardModal.querySelector('.word-card-modal-footer');
    const directionSelect = document.getElementById('word-card-direction');
    const countInput = document.getElementById('word-card-count');
    const levelsSelect = document.getElementById('word-card-levels');
    const topicsSelect = document.getElementById('word-card-topics');
    const typesSelect = document.getElementById('word-card-types');

    if (!startButton) return;

    knownButton.textContent = '1 · Знаю слово';
    learningButton.textContent = '2 · Хочу учить';
    nextButton.textContent = '3 · Дальше';

    initWordCardChips();

    startButton.addEventListener('click', function() {
        settingsModal.classList.add('show');
    });

    startConfirm.addEventListener('click', startSession);
    knownButton.addEventListener('click', function() {
        markCurrentCard('known');
    });
    learningButton.addEventListener('click', function() {
        markCurrentCard('learning');
    });
    nextButton.addEventListener('click', function() {
        showNextCard(true);
    });
    container.addEventListener('click', function(event) {
        const showAnswerButton = event.target.closest('#word-card-show-answer-button');

        if (!showAnswerButton) {
            return;
        }

        showCurrentAnswer();
    });

    document.addEventListener('keydown', function(event) {
        if (!cardModal.classList.contains('show') || state.isFinished) {
            return;
        }

        if (isTypingTarget(event.target)) {
            return;
        }

        if (event.code === 'Space') {
            event.preventDefault();
            if (state.answerState === 'answerHidden') {
                showCurrentAnswer();
            }
            return;
        }

        if (state.answerState !== 'answerShown') {
            return;
        }

        if (event.key === '1') {
            event.preventDefault();
            markCurrentCard('known');
            return;
        }

        if (event.key === '2') {
            event.preventDefault();
            markCurrentCard('learning');
            return;
        }

        if (event.key === '3') {
            event.preventDefault();
            showNextCard(true);
        }
    });

    function startSession() {
        const limit = parseInt(countInput.value, 10);

        if (limit < 1 || limit > 100) {
            alert('Please enter a valid number of words (1-100)');
            return;
        }

        state.direction = directionSelect.value || 'all';
        state.limit = limit;
        state.levels = getSelectedValues(levelsSelect);
        state.topics = getSelectedValues(topicsSelect);
        state.types = getSelectedValues(typesSelect);
        state.cards = [];
        state.index = 0;
        state.known = 0;
        state.learning = 0;
        state.skipped = 0;
        state.answerState = 'answerHidden';
        state.isFinished = false;

        settingsModal.classList.remove('show');
        loadCards();
    }

    function loadCards() {
        const params = new URLSearchParams({
            direction: state.direction,
            limit: state.limit
        });

        state.levels.forEach(level => params.append('levels[]', level));
        state.topics.forEach(topic => params.append('topics[]', topic));
        state.types.forEach(type => params.append('types[]', type));

        fetch(`/ajax.php?action=get_word_cards&${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data) {
                    state.cards = data.data;
                    state.index = 0;
                    renderCurrentCard();
                    cardModal.classList.add('show');
                    return;
                }

                alert('Error loading word cards: ' + (data.message || 'Unknown error'));
            })
            .catch(error => {
                console.error('Error loading word cards:', error);
                alert('Error loading word cards');
            });
    }

    function renderCurrentCard() {
        const card = state.cards[state.index];

        if (!card) {
            renderFinalScreen();
            return;
        }

        setActionButtonsVisible(false);
        state.answerState = 'answerHidden';

        container.innerHTML = `
            <article class="word-card-study-card word-card-answer-hidden" data-word-card-answer-state="answerHidden">
                <div class="word-card-progress">${state.index + 1} / ${state.cards.length}</div>
                <div class="word-card-main-word">${escapeHtml(card.text || '')}</div>
                ${card.transcription ? `<div class="word-card-transcription">${escapeHtml(card.transcription)}</div>` : ''}
                <div class="word-card-meta-row">
                    ${card.type ? `<span>${escapeHtml(card.type)}</span>` : ''}
                    ${card.level ? `<span>${escapeHtml(card.level)}</span>` : ''}
                    ${card.topics ? `<span>${escapeHtml(card.topics)}</span>` : ''}
                </div>
                ${card.translate ? `
                    <div class="word-card-field word-card-translate" data-word-card-answer-field="translate">
                        <span class="word-card-answer-placeholder">скрыто</span>
                        <span class="word-card-answer-value">${escapeHtml(card.translate)}</span>
                    </div>
                ` : ''}
                ${card.example ? `<div class="word-card-example"><strong>EN:</strong> ${escapeHtml(card.example)}</div>` : ''}
                ${card.example_ru ? `
                    <div class="word-card-example" data-word-card-answer-field="example_ru">
                        <strong>RU:</strong>
                        <span class="word-card-answer-placeholder">скрыто</span>
                        <span class="word-card-answer-value">${escapeHtml(card.example_ru)}</span>
                    </div>
                ` : ''}
                ${card.memory_hint ? `
                    <div class="word-card-hint" data-word-card-answer-field="memory_hint">
                        <strong>Hint:</strong>
                        <span class="word-card-answer-placeholder">скрыто</span>
                        <span class="word-card-answer-value">${escapeHtml(card.memory_hint)}</span>
                    </div>
                ` : ''}
                <div class="word-card-help" id="word-card-help">Пробел — показать ответ</div>
                <button type="button" class="word-card-show-answer-button" id="word-card-show-answer-button">
                    Показать
                </button>
            </article>
        `;
    }

    function showCurrentAnswer() {
        if (state.answerState === 'answerShown') {
            return;
        }

        const showAnswerButton = document.getElementById('word-card-show-answer-button');
        const helpText = document.getElementById('word-card-help');
        const studyCard = container.querySelector('.word-card-study-card');

        state.answerState = 'answerShown';

        if (showAnswerButton) {
            showAnswerButton.textContent = 'Открыто';
            showAnswerButton.disabled = true;
        }

        if (helpText) {
            helpText.textContent = '1 — знаю · 2 — учить · 3 — дальше';
        }

        setActionButtonsVisible(true);

        if (studyCard) {
            studyCard.classList.remove('word-card-answer-hidden');
            studyCard.classList.add('word-card-answer-shown');
            studyCard.dataset.wordCardAnswerState = 'answerShown';
        }
    }

    function markCurrentCard(status) {
        const card = state.cards[state.index];

        if (state.answerState !== 'answerShown') {
            return;
        }

        if (knownButton.disabled || learningButton.disabled) {
            return;
        }

        if (!card) {
            renderFinalScreen();
            return;
        }

        setActionButtonsDisabled(true);

        const formData = new URLSearchParams({
            word_id: card.id,
            status
        });

        fetch('/ajax.php?action=mark_word_card', {
            method: 'POST',
            body: formData,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'ok') {
                    alert('Error saving word status: ' + (data.message || 'Unknown error'));
                    setActionButtonsDisabled(false);
                    return;
                }

                if (status === 'known') {
                    state.known++;
                } else {
                    state.learning++;
                }

                showNextCard(false);
            })
            .catch(error => {
                console.error('Error saving word status:', error);
                alert('Error saving word status');
                setActionButtonsDisabled(false);
            });
    }

    function showNextCard(countAsSkipped) {
        if (countAsSkipped && state.answerState !== 'answerShown') {
            return;
        }

        if (nextButton.disabled && countAsSkipped) {
            return;
        }

        if (countAsSkipped && state.cards[state.index]) {
            state.skipped++;
        }

        state.index++;
        state.answerState = 'answerHidden';
        renderCurrentCard();
    }

    function renderFinalScreen() {
        state.isFinished = true;
        state.answerState = 'answerShown';
        setActionButtonsVisible(false);

        container.innerHTML = `
            <div class="word-card-final">
                <h4>Session finished</h4>
                <div class="word-card-final-grid">
                    <div>
                        <span>Known</span>
                        <strong>${state.known}</strong>
                    </div>
                    <div>
                        <span>Learning</span>
                        <strong>${state.learning}</strong>
                    </div>
                    <div>
                        <span>Skipped</span>
                        <strong>${state.skipped}</strong>
                    </div>
                </div>
            </div>
        `;
    }

    function setActionButtonsDisabled(disabled) {
        knownButton.disabled = disabled;
        learningButton.disabled = disabled;
        nextButton.disabled = disabled;
    }

    function setActionButtonsVisible(visible) {
        if (actionFooter) {
            actionFooter.classList.toggle('word-card-actions-hidden', !visible);
        }

        setActionButtonsDisabled(!visible);
    }

    function getSelectedValues(select) {
        if (!select) return [];

        return Array.from(select.selectedOptions)
            .map(option => option.value.trim())
            .filter(Boolean);
    }

    function initWordCardChips() {
        const chipGroups = document.querySelectorAll('[data-word-card-chip-select]');

        chipGroups.forEach(group => {
            const selectId = group.dataset.wordCardChipSelect;
            const select = document.getElementById(selectId);

            if (!select) {
                return;
            }

            syncWordCardChips(group, select);

            group.addEventListener('click', function(event) {
                const chip = event.target.closest('.word-card-chip');

                if (!chip || !group.contains(chip)) {
                    return;
                }

                const option = Array.from(select.options).find(item => item.value === chip.dataset.wordCardChipValue);

                if (!option) {
                    return;
                }

                option.selected = !option.selected;
                syncWordCardChips(group, select);
            });

            select.addEventListener('change', function() {
                syncWordCardChips(group, select);
            });
        });
    }

    function syncWordCardChips(group, select) {
        const selectedValues = new Set(Array.from(select.selectedOptions).map(option => option.value));

        group.querySelectorAll('.word-card-chip').forEach(chip => {
            const isActive = selectedValues.has(chip.dataset.wordCardChipValue);
            chip.classList.toggle('is-active', isActive);
            chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function isTypingTarget(target) {
        if (!target || target.nodeType !== Node.ELEMENT_NODE) {
            return false;
        }

        const tagName = target.tagName.toLowerCase();
        return ['input', 'select', 'textarea', 'button'].includes(tagName)
            || target.isContentEditable
            || Boolean(target.closest('[contenteditable="true"]'));
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
})();
