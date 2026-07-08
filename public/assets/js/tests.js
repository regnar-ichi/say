// Test Management System

(function() {
    // State
    let testState = {
        direction: 'translate',
        limit: 10,
        levels: [],
        topics: [],
        types: [],
        words: [],
        answers: [],
        answerValues: {},
        result: null,
        status: null
    };

    const TEST_PAGE_SESSION_KEY = 'english_test_page_session';

    // DOM elements
    const btnStartTest = document.getElementById('btn-start-test');
    const btnStartTestConfirm = document.getElementById('btn-start-test-confirm');
    const btnFinishTest = document.getElementById('btn-finish-test');
    
    const testSettingsModal = document.getElementById('test-settings-modal');
    const testWordsModal = document.getElementById('test-words-modal');
    const testResultsModal = document.getElementById('test-results-modal');

    const testPageWordsContainer = document.getElementById('test-page-words-container');
    const isPageMode = Boolean(testPageWordsContainer);
    const testWordsContainer = testPageWordsContainer || document.getElementById('test-words-container');
    const testPageEmpty = document.getElementById('test-page-empty');
    const testPageWorkspace = document.getElementById('test-page-workspace');
    const testPageResults = document.getElementById('test-page-results');
    const testPageMeta = document.getElementById('test-page-meta');
    const testPageSessionNotice = document.getElementById('test-page-session-notice');
    const testPageSessionTitle = document.getElementById('test-page-session-title');
    const testPageSessionText = document.getElementById('test-page-session-text');
    const btnTestSessionContinue = document.getElementById('btn-test-session-continue');
    const btnTestSessionNew = document.getElementById('btn-test-session-new');
    const btnTestSessionClear = document.getElementById('btn-test-session-clear');
    const testCountInput = document.getElementById('test-count');
    const testLevelsSelect = document.getElementById('test-levels');
    const testTopicsSelect = document.getElementById('test-topics');
    const testTypesSelect = document.getElementById('test-types');

    if (!btnStartTest) return; // Exit if test page not loaded

    // Initialize modal close handlers
    initializeModalCloseHandlers();
    initializeTestChips();
    initializePageSession();

    // Event listeners
    btnStartTest.addEventListener('click', showTestSettingsModal);
    btnStartTestConfirm.addEventListener('click', handleStartTest);
    btnFinishTest.addEventListener('click', handleFinishTest);

    if (btnTestSessionContinue) {
        btnTestSessionContinue.addEventListener('click', function() {
            hidePageSessionNotice();
            showPageTest();
        });
    }

    if (btnTestSessionNew) {
        btnTestSessionNew.addEventListener('click', function() {
            clearPageSession();
            resetPageTestView();
            showTestSettingsModal();
        });
    }

    if (btnTestSessionClear) {
        btnTestSessionClear.addEventListener('click', function() {
            clearPageSession();
            resetPageTestView();
        });
    }

    if (isPageMode) {
        window.addEventListener('beforeunload', function(event) {
            if (testState.status !== 'active') {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
    }

    /**
     * Show test settings modal
     */
    function showTestSettingsModal() {
        testSettingsModal.classList.add('show');
    }

    /**
     * Handle start test button
     */
    function handleStartTest() {
        const direction = document.querySelector('input[name="direction"]:checked')?.value || 'translate';
        const limit = parseInt(testCountInput.value, 10);

        if (limit < 1 || limit > 100) {
            alert('Please enter a valid number of words (1-100)');
            return;
        }

        testState.direction = direction;
        testState.limit = limit;
        testState.levels = getSelectedValues(testLevelsSelect);
        testState.topics = getSelectedValues(testTopicsSelect);
        testState.types = getSelectedValues(testTypesSelect);
        testState.answers = [];
        testState.answerValues = {};
        testState.result = null;
        testState.status = null;

        if (isPageMode) {
            hidePageSessionNotice();
        }

        // Close settings modal
        testSettingsModal.classList.remove('show');

        // Load test words
        loadTestWords();
    }

    /**
     * Load test words via AJAX
     */
    function loadTestWords() {
        const params = new URLSearchParams({
            direction: testState.direction,
            limit: testState.limit
        });

        testState.levels.forEach(level => params.append('levels[]', level));
        testState.topics.forEach(topic => params.append('topics[]', topic));
        testState.types.forEach(type => params.append('types[]', type));

        fetch(`/ajax.php?action=get_test_words&${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data) {
                    testState.words = data.data;
                    if (isPageMode) {
                        testState.status = 'active';
                        savePageSession();
                    }
                    renderTestWords();
                    if (isPageMode) {
                        showPageTest();
                    } else if (testWordsModal) {
                        testWordsModal.classList.add('show');
                    }
                } else {
                    alert('Error loading test words: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error loading test words:', error);
                alert('Error loading test words');
            });
    }

    /**
     * Render test words into modal or page workspace
     */
    function renderTestWords() {
        testWordsContainer.innerHTML = '';
        testState.answers = [];

        testState.words.forEach((word, index) => {
            const wordItem = document.createElement('div');

            let exampleHtml = '';
            if (word.example) {
                exampleHtml = isPageMode
                    ? `<div class="test-page-question-example">${escapeHtml(word.example)}</div>`
                    : `<div class="test-word-example">${escapeHtml(word.example)}</div>`;
            }

            if (isPageMode) {
                wordItem.className = 'test-page-question';
                wordItem.innerHTML = `
                    <div class="test-page-question-number">${index + 1}</div>
                    <div class="test-page-question-body">
                        <div class="test-page-question-word">${escapeHtml(word.word)}</div>
                        ${word.type ? `<div class="test-page-question-type">${escapeHtml(word.type)}</div>` : ''}
                        ${exampleHtml}
                        <input
                            type="text"
                            class="test-page-question-input"
                            placeholder="Your answer..."
                            data-word-id="${word.id}"
                            value="${escapeHtml(testState.answerValues[word.id] || '')}"
                            autocomplete="off"
                        >
                    </div>
                `;
            } else {
                wordItem.className = 'test-word-item';
                wordItem.innerHTML = `
                    <div class="test-word-label">${escapeHtml(word.word)}</div>
                    ${word.type ? `<div class="test-word-type">${escapeHtml(word.type)}</div>` : ''}
                    ${exampleHtml}
                    <input
                        type="text"
                        class="test-word-input"
                        placeholder="Your answer..."
                        data-word-id="${word.id}"
                        autocomplete="off"
                    >
                `;
            }

            testWordsContainer.appendChild(wordItem);
        });

        if (isPageMode) {
            testWordsContainer.querySelectorAll('.test-page-question-input').forEach(input => {
                input.addEventListener('input', handlePageAnswerInput);
            });
        }

        updatePageMeta();
    }

    /**
     * Handle finish test button
     */
    function handleFinishTest() {
        // Collect answers
        const inputs = testWordsContainer.querySelectorAll('.test-word-input, .test-page-question-input');
        testState.answers = [];
        if (isPageMode) {
            testState.answerValues = {};
        }

        inputs.forEach((input, index) => {
            if (index < testState.words.length) {
                if (isPageMode) {
                    testState.answerValues[input.dataset.wordId] = input.value.trim();
                }

                testState.answers.push({
                    id: testState.words[index].id,
                    value: input.value.trim()
                });
            }
        });

        if (testState.answers.length === 0) {
            alert('Please answer at least one question');
            return;
        }

        if (isPageMode) {
            savePageSession();
        }

        // Check answers via AJAX
        checkAnswers();
    }

    /**
     * Check answers via AJAX
     */
    function checkAnswers() {
        const payload = {
            answers: JSON.stringify(testState.answers),
            direction: testState.direction
        };

        const formData = new URLSearchParams(payload);

        fetch('/ajax.php?action=check_test_words', {
            method: 'POST',
            body: formData,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok' && data.data) {
                    if (!isPageMode && testWordsModal) {
                        testWordsModal.classList.remove('show');
                    }

                    if (isPageMode) {
                        testState.status = 'finished';
                        testState.result = data.data;
                        savePageSession();
                    }
                    
                    // Show results
                    showResults(data.data);
                } else {
                    alert('Error checking answers: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error checking answers:', error);
                alert('Error checking answers');
            });
    }

    /**
     * Show test results modal
     */
    function showResults(result) {
        // Update summary
        document.getElementById('result-correct').textContent = result.correct;
        document.getElementById('result-wrong').textContent = result.wrong;
        document.getElementById('result-percentage').textContent = result.percentage + '%';

        // Render details
        const detailsContainer = document.getElementById('test-results-details');
        detailsContainer.innerHTML = '';

        if (result.details && result.details.length > 0) {
            result.details.forEach(detail => {
                const detailItem = document.createElement('div');
                detailItem.className = `result-detail-item ${detail.status}`;

                let html = `<div class="result-detail-question">Q: ${escapeHtml(detail.shown || '')}</div>`;
                html += `<div class="result-detail-answer">Your answer: ${escapeHtml(detail.answer || '-')}</div>`;

                if (detail.status === 'wrong' && detail.expected) {
                    html += `<div class="result-detail-correct-answer">Correct: ${escapeHtml(detail.expected)}</div>`;
                }

                detailItem.innerHTML = html;
                detailsContainer.appendChild(detailItem);
            });
        }

        if (isPageMode) {
            if (testPageResults) {
                testPageResults.hidden = false;
                testPageResults.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            return;
        }

        if (testResultsModal) {
            testResultsModal.classList.add('show');
        }
    }

    function showPageTest() {
        if (testPageEmpty) {
            testPageEmpty.hidden = true;
        }

        if (testPageResults) {
            testPageResults.hidden = true;
        }

        if (testPageWorkspace) {
            testPageWorkspace.hidden = false;
            testPageWorkspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function initializePageSession() {
        if (!isPageMode) {
            return;
        }

        const savedSession = loadPageSession();

        if (!savedSession) {
            return;
        }

        restorePageSession(savedSession);
    }

    function restorePageSession(session) {
        testState.direction = session.direction || 'translate';
        testState.limit = parseInt(session.limit || session.count || 10, 10);
        testState.levels = session.filters?.levels || session.levels || [];
        testState.topics = session.filters?.topics || session.topics || [];
        testState.types = session.filters?.types || session.types || [];
        testState.words = Array.isArray(session.words) ? session.words : [];
        testState.answerValues = session.answers || {};
        testState.result = session.result || null;
        testState.status = session.state || null;

        if (testState.status === 'active' && testState.words.length > 0) {
            renderTestWords();
            updatePageMeta();
            if (testPageEmpty) {
                testPageEmpty.hidden = true;
            }
            if (testPageWorkspace) {
                testPageWorkspace.hidden = true;
            }
            if (testPageResults) {
                testPageResults.hidden = true;
            }
            showPageSessionNotice(
                'Unfinished test restored',
                'Your words and typed answers were saved locally. Continue when you are ready.',
                true
            );
            return;
        }

        if (testState.status === 'finished' && testState.result) {
            if (testPageEmpty) {
                testPageEmpty.hidden = true;
            }
            if (testPageWorkspace) {
                testPageWorkspace.hidden = true;
            }
            showResults(testState.result);
            showPageSessionNotice(
                'Last result restored',
                'This completed result was saved locally. Start a new test when you want a fresh session.',
                false
            );
        }
    }

    function handlePageAnswerInput(event) {
        const input = event.target;
        testState.answerValues[input.dataset.wordId] = input.value;
        testState.status = 'active';
        savePageSession();
    }

    function savePageSession() {
        if (!isPageMode) {
            return;
        }

        const session = {
            direction: testState.direction,
            count: testState.limit,
            limit: testState.limit,
            filters: {
                levels: testState.levels,
                topics: testState.topics,
                types: testState.types
            },
            words: testState.words,
            answers: testState.answerValues,
            state: testState.status || 'active',
            result: testState.result,
            timestamp: Date.now()
        };

        try {
            localStorage.setItem(TEST_PAGE_SESSION_KEY, JSON.stringify(session));
        } catch (error) {
            console.warn('Unable to save test session:', error);
        }
    }

    function loadPageSession() {
        try {
            const rawSession = localStorage.getItem(TEST_PAGE_SESSION_KEY);
            return rawSession ? JSON.parse(rawSession) : null;
        } catch (error) {
            console.warn('Unable to load test session:', error);
            return null;
        }
    }

    function clearPageSession() {
        if (!isPageMode) {
            return;
        }

        localStorage.removeItem(TEST_PAGE_SESSION_KEY);
        testState.answers = [];
        testState.answerValues = {};
        testState.words = [];
        testState.result = null;
        testState.status = null;
    }

    function resetPageTestView() {
        hidePageSessionNotice();

        if (testWordsContainer) {
            testWordsContainer.innerHTML = '';
        }

        if (testPageWorkspace) {
            testPageWorkspace.hidden = true;
        }

        if (testPageResults) {
            testPageResults.hidden = true;
        }

        if (testPageEmpty) {
            testPageEmpty.hidden = false;
        }
    }

    function showPageSessionNotice(title, text, canContinue) {
        if (!testPageSessionNotice) {
            return;
        }

        if (testPageSessionTitle) {
            testPageSessionTitle.textContent = title;
        }

        if (testPageSessionText) {
            testPageSessionText.textContent = text;
        }

        if (btnTestSessionContinue) {
            btnTestSessionContinue.hidden = !canContinue;
        }

        testPageSessionNotice.hidden = false;
    }

    function hidePageSessionNotice() {
        if (testPageSessionNotice) {
            testPageSessionNotice.hidden = true;
        }
    }

    function updatePageMeta() {
        if (testPageMeta) {
            const directionText = testState.direction === 'text' ? 'Russian -> English' : 'English -> Russian';
            testPageMeta.textContent = `${testState.words.length} words · ${directionText}`;
        }
    }

    /**
     * Initialize modal close handlers
     */
    function initializeModalCloseHandlers() {
        // Handle close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const modalId = this.getAttribute('data-close');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Handle close button with direct close attribute on footer buttons
        document.querySelectorAll('[data-close]').forEach(btn => {
            if (btn.classList.contains('modal-close')) return; // Skip already handled

            btn.addEventListener('click', function() {
                const modalId = this.getAttribute('data-close');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Close on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    }

    function getSelectedValues(select) {
        if (!select) return [];

        return Array.from(select.selectedOptions)
            .map(option => option.value.trim())
            .filter(Boolean);
    }

    /**
     * Initialize visual chips for hidden multiple selects.
     */
    function initializeTestChips() {
        const chipGroups = document.querySelectorAll('[data-test-chip-select]');

        chipGroups.forEach(group => {
            const select = document.getElementById(group.dataset.testChipSelect);

            if (!select) {
                return;
            }

            syncTestChips(group, select);

            group.addEventListener('click', function(event) {
                const chip = event.target.closest('.test-chip');

                if (!chip || !group.contains(chip)) {
                    return;
                }

                const option = Array.from(select.options).find(item => item.value === chip.dataset.testChipValue);

                if (!option) {
                    return;
                }

                option.selected = !option.selected;
                syncTestChips(group, select);
            });

            select.addEventListener('change', function() {
                syncTestChips(group, select);
            });
        });
    }

    /**
     * Sync chip active state from the native select.
     */
    function syncTestChips(group, select) {
        const selectedValues = new Set(Array.from(select.selectedOptions).map(option => option.value));

        group.querySelectorAll('.test-chip').forEach(chip => {
            const isActive = selectedValues.has(chip.dataset.testChipValue);
            chip.classList.toggle('is-active', isActive);
            chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
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
