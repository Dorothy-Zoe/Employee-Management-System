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