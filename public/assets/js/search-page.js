console.log('Search page loaded');

const input = document.getElementById('search-input-page');
const results = document.getElementById('search-results-page');

if (input) {

    input.addEventListener('input', function () {

        const query = this.value.trim();

        if (query.length < 1) {
            results.innerHTML = '';
            return;
        }

        fetch('/ajax.php?action=find_words&q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {

                console.log(data);

                let html = '';

                if (data.status === 'ok') {

                    data.data.forEach(word => {

                        html += `
                            <div>
                                <strong>${word.text}</strong>
                                - ${word.translate}
                            </div>
                        `;
                    });

                }

                results.innerHTML = html;

            });

    });

}

