function filterByDate() {
    const fromDate = document.getElementById('from').value;
    const toDate = document.getElementById('to').value;

    if (!fromDate || !toDate) {
        alert('Please select both From and To dates.');
        return;
    }

    // Send the dates to the server via AJAX
    fetch('filterData.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ fromDate, toDate }),
    })
        .then((response) => response.json())
        .then((data) => {
            console.log('Filtered data:', data);
            // Update your UI with the filtered data
        })
        .catch((error) => {
            console.error('Error filtering data:', error);
        });
}


function applyFilters() {
    updateURL();
}

function changePage(page) {
    updateURL({ page });
}

function changeEntries(entries) {
    updateURL({ entries });
}

function updateURL(extraParams = {}) {
    let params = new URLSearchParams(window.location.search);
    params.set("filterDay", document.getElementById("filterDay").value);
    params.set("filterSection", document.getElementById("filterSection").value);
    params.set("entries", document.getElementById("entriesDropdown").value);

    // Add extra parameters like page number
    for (let key in extraParams) {
        params.set(key, extraParams[key]);
    }

    window.location.href = "AdminScheduleReport.php?" + params.toString();
}


// Function to search the table
function changePage(page) {
    let url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}


function searchTable() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let table = document.getElementById('dataTable');
    let rows = table.getElementsByTagName('tr');

    // Loop through table rows (skipping header row)
    for (let i = 1; i < rows.length; i++) {
        let cells = rows[i].getElementsByTagName('td');
        let found = false;

        // Check each cell in the row
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toLowerCase().includes(input)) {
                found = true;
                break;
            }
        }

        // Show or hide row based on search match
        rows[i].style.display = found ? '' : 'none';
    }
}