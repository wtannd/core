document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('institution_search');
    const hiddenIdInput = document.getElementById('iID');
    const resultsContainer = document.getElementById('institution_results');
    let timeoutId;

    searchInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        const query = this.value.trim();

        // Only search if the user has typed at least 4 characters
        if (query.length < 4) {
            resultsContainer.style.display = 'none';
            // Optional: clear the hidden iID if they delete the text
            if (query.length === 0) hiddenIdInput.value = '1';
            return;
        }

        // Debounce the AJAX call to save server resources
        timeoutId = setTimeout(() => {
            fetch('/lookupInstitutions', { // Update this URL to match your routing
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    text: query,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value 
                })
            })
            .then(response => response.json())
            .then(data => {
                resultsContainer.innerHTML = ''; // Clear old results
                
                if (data.length > 0) {
                    data.forEach(inst => {
                        const div = document.createElement('div');
                        div.className = 'autocomplete-item';
                        div.textContent = inst.iname;
                        
                        // When a user clicks an institution from the list
                        div.addEventListener('click', function() {
                            searchInput.value = inst.iname; // Fill visible box
                            hiddenIdInput.value = inst.iID; // Fill hidden input for POST
                            resultsContainer.style.display = 'none'; // Hide dropdown
                        });
                        
                        resultsContainer.appendChild(div);
                    });
                    if (data.length === 1) resultsContainer.insertAdjacentHTML('beforeend', '<div class="autocomplete-item text-muted">No institutions found...</div>');
                    resultsContainer.style.display = 'block';
                } else {
                    // Show a "No results" message
                    resultsContainer.innerHTML = '<div class="autocomplete-item text-muted">No institutions found...</div>';
                    resultsContainer.style.display = 'block';
                }
            })
            .catch(error => console.error('Error:', error));
        }, 500);
    });

    // Close the dropdown if the user clicks anywhere else on the page
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
});
