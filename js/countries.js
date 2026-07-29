/**
 * countries.js
 * Handles the searchable country dropdown logic.
 * Updated: 2025-12-13 - Hardened for robustness
 */

// A comprehensive list of countries
const ALL_COUNTRIES = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", 
    "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", 
    "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", 
    "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", 
    "Bulgaria", "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon", 
    "Canada", "Central African Republic", "Chad", "Chile", "China", "Colombia", 
    "Comoros", "Congo (Congo-Brazzaville)", "Costa Rica", "Croatia", "Cuba", 
    "Cyprus", "Czechia", "Democratic Republic of the Congo", "Denmark", "Djibouti", 
    "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", 
    "Equatorial Guinea", "Eritrea", "Estonia", "Eswatini (fmr. 'Swaziland')", 
    "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", 
    "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", 
    "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", 
    "Iran", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", 
    "Kazakhstan", "Kenya", "Kiribati", "Kosovo", "Kuwait", "Kyrgyzstan", 
    "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", 
    "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", 
    "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", 
    "Micronesia", "Moldova", "Monaco", "Mongolia", "Montenegro", "Morocco", 
    "Mozambique", "Myanmar (formerly Burma)", "Namibia", "Nauru", "Nepal", 
    "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", 
    "North Macedonia (formerly Macedonia)", "Norway", "Oman", "Pakistan", "Palau", 
    "Palestine State", "Panama", "Papua New Guinea", "Paraguay", "Peru", 
    "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", 
    "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", 
    "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", 
    "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", 
    "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", 
    "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", 
    "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Timor-Leste", "Togo", 
    "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", 
    "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States of America", 
    "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", 
    "Yemen", "Zambia", "Zimbabwe"
];

function initializeCountrySearch(initialCountryList = [], initialValue = '') {
    // 1. Safe Element Selection
    const searchInput = document.getElementById('country-search');
    const hiddenInput = document.getElementById('country');
    const dropdown = document.getElementById('country-dropdown');
    const wrapper = document.getElementById('country-select-wrapper');

    if (!searchInput || !hiddenInput || !dropdown || !wrapper) {
        console.error('Country Search Error: Required DOM elements missing. Check HTML IDs.');
        return;
    }

    // 2. Data Preparation
    const countries = (Array.isArray(initialCountryList) && initialCountryList.length > 0) 
        ? initialCountryList 
        : ALL_COUNTRIES;

    // 3. Helper Functions
    const showDropdown = () => {
        dropdown.classList.remove('hidden');
        // Force styles to ensure visibility over other elements
        dropdown.style.display = 'block';
        dropdown.style.zIndex = '9999';
        dropdown.style.backgroundColor = 'white';
    };

    const hideDropdown = () => {
        dropdown.classList.add('hidden');
        dropdown.style.display = 'none';
    };

    const renderDropdown = (query = '') => {
        const lowerQuery = query.toLowerCase().trim();
        const filtered = countries.filter(c => c.toLowerCase().includes(lowerQuery));

        dropdown.innerHTML = '';

        if (filtered.length === 0) {
            const noMatch = document.createElement('div');
            noMatch.className = 'p-3 text-sm text-gray-500 italic bg-gray-50';
            noMatch.textContent = query ? `No match for "${query}"` : 'Type to search...';
            // Stop click propagation on the "no match" item so clicking it keeps the dropdown open/focused
            noMatch.addEventListener('mousedown', (e) => e.stopPropagation()); 
            dropdown.appendChild(noMatch);
        } else {
            filtered.forEach(country => {
                const item = document.createElement('div');
                item.className = 'p-2.5 cursor-pointer text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition-colors border-b border-gray-100 last:border-0';
                item.textContent = country;
                
                // Use 'mousedown' instead of 'click'. 
                // 'mousedown' fires before 'blur', ensuring the selection happens before the field loses focus.
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // Prevent focus loss on input
                    e.stopPropagation(); // Stop bubbling
                    
                    searchInput.value = country;
                    hiddenInput.value = country;
                    
                    console.log('Country selected:', country);
                    hideDropdown();
                });
                
                dropdown.appendChild(item);
            });
        }
    };

    // 4. Event Listeners

    // Input: Filter list and show
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value;
        hiddenInput.value = val; // Sync flexible input
        
        console.log('Searching for:', val);
        renderDropdown(val);
        showDropdown();
    });

    // Focus: Show list
    searchInput.addEventListener('focus', (e) => {
        renderDropdown(e.target.value);
        showDropdown();
    });

    // Click Outside: Hide dropdown
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            hideDropdown();
            
            // Flexible Input Logic: Capture what was typed if no selection was made
            if (searchInput.value.trim() !== '' && hiddenInput.value === '') {
                hiddenInput.value = searchInput.value;
            }
        }
    });

    // 5. Initial Setup
    if (initialValue) {
        searchInput.value = initialValue;
        hiddenInput.value = initialValue;
    }
    
    console.log('Country search initialized successfully.');
}

// Expose to window
window.initializeCountrySearch = initializeCountrySearch;