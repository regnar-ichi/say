document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('wordModal');
    const addWordBtn = document.getElementById('addWordBtn');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const wordForm = document.getElementById('wordForm');
    const formMessage = document.getElementById('formMessage');
    const wordText = document.getElementById('wordText');
    const wordTranslate = document.getElementById('wordTranslate');
    const wordType = document.getElementById('wordType');
    const wordExample = document.getElementById('wordExample');
    const wordExampleRu = document.getElementById('wordExampleRu');

    if (!modal || !closeModal || !cancelBtn || !wordForm) return;

    let isEditMode = false;
    let editWordId = null;

    // Open modal for adding new word
    if (addWordBtn) {
        addWordBtn.addEventListener('click', function() {
            isEditMode = false;
            editWordId = null;
            modal.classList.add('show');
            wordForm.reset();
            formMessage.style.display = 'none';
            document.querySelector('.modal-header h3').textContent = 'Add New Word';
            wordText.focus();
        });
    }

    // Close modal
    function closeModalWindow() {
        modal.classList.remove('show');
        wordForm.reset();
        formMessage.style.display = 'none';
        isEditMode = false;
        editWordId = null;
    }

    closeModal.addEventListener('click', closeModalWindow);
    cancelBtn.addEventListener('click', closeModalWindow);

    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModalWindow();
        }
    });

    // Open modal for editing word
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-edit-word')) {
            const id = e.target.getAttribute('data-id');
            const text = e.target.getAttribute('data-text');
            const translate = e.target.getAttribute('data-translate');
            const type = e.target.getAttribute('data-type') || '';
            const example = e.target.getAttribute('data-example') || '';
            const exampleRu = e.target.getAttribute('data-example-ru') || '';
            
            isEditMode = true;
            editWordId = id;
            wordText.value = text;
            wordTranslate.value = translate;
            wordType.value = type;
            wordExample.value = example;
            wordExampleRu.value = exampleRu;
            document.querySelector('.modal-header h3').textContent = 'Edit Word';
            modal.classList.add('show');
            formMessage.style.display = 'none';
            wordText.focus();
        }
    });

    // Form submit via AJAX
    wordForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const text = wordText.value.trim();
        const translate = wordTranslate.value.trim();
        const type = wordType.value.trim();
        const example = wordExample.value.trim();
        const exampleRu = wordExampleRu.value.trim();

        if (!text || !translate) {
            showMessage('Please fill all fields', 'error');
            return;
        }

        const formData = new FormData();
        if (isEditMode) {
            formData.append('action', 'word_update');
            formData.append('id', editWordId);
        } else {
            formData.append('action', 'word_create');
        }
        formData.append('text', text);
        formData.append('translate', translate);
        formData.append('type', type);
        formData.append('example', example);
        formData.append('example_ru', exampleRu);

        fetch('/ajax.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                showMessage(data.message || 'Success', 'success');
                setTimeout(() => {
                    location.reload();
                }, 800);
            } else {
                showMessage(data.message || 'Error', 'error');
            }
        })
        .catch(error => {
            showMessage('Network error', 'error');
            console.error('Error:', error);
        });
    });

    function showMessage(text, type) {
        formMessage.textContent = text;
        formMessage.className = 'form-message ' + type;
        formMessage.style.display = 'block';
    }
});
