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


document.addEventListener('DOMContentLoaded', function () {
    const applyFilterButton = document.getElementById('applyFilter');
    const filterStatus = document.getElementById('filterStatus');

    if (applyFilterButton && filterStatus) {
        applyFilterButton.addEventListener('click', function () {
            const status = filterStatus.value;
            const urlParams = new URLSearchParams(window.location.search);

            if (status) {
                urlParams.set('Status', status);
            } else {
                urlParams.delete('Status');
            }

            // Update the URL with the new parameters
            window.location.search = urlParams.toString();
        });
    }
});


//Notification process
// Toggle dropdown visibility when notification icon is clicked
document.getElementById('notificationDropdown').addEventListener('click', function(event) {
    const dropdown = document.querySelector('.notif-dropdown');
    dropdown.classList.toggle('show'); // Toggle visibility of the dropdown
    event.stopPropagation(); // Prevents click event from propagating to window
});

// Close the dropdown if clicked outside
window.onclick = function(event) {
    const dropdown = document.querySelector('.notif-dropdown');
    
    // If clicked outside of the dropdown, close the dropdown
    if (!dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
};

// Update unread notification count
function updateUnreadCount() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const unreadCountElement = document.getElementById('unreadCount');
    if (unreadCount > 0) {
        unreadCountElement.textContent = unreadCount;
        unreadCountElement.style.display = 'inline'; // Show the unread count
    } else {
        unreadCountElement.textContent = '';
        unreadCountElement.style.display = 'none'; // Hide the unread count
    }
}

// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function () {
    // Get the "Mark all as read" button
    const markAllReadButton = document.getElementById('markAllRead');

    // Handle the "Mark all as read" button click event
    markAllReadButton.addEventListener('click', async function () {
        try {
            // Send a POST request to mark all notifications as read
            const response = await fetch('HRDashboard.php', {  // Adjust URL if necessary
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'markAllRead': true
                })
            });

            if (response.ok) {
                // Mark all notification items as read in the UI
                const notificationItems = document.querySelectorAll('.notification-item.unread');
                notificationItems.forEach(function (notification) {
                    notification.classList.remove('unread');
                    notification.classList.add('read');
                });

                // Update the unread count
                updateUnreadCount();
            } else {
                console.error('Failed to mark all notifications as read');
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    });

    // Function to update the unread count on the page
    function updateUnreadCount() {
        const unreadNotifications = document.querySelectorAll('.notification-item.unread');
        const unreadCount = document.getElementById('unreadCount');
        
        const count = unreadNotifications.length;
        unreadCount.textContent = count;

        // Hide the unread count if there are no unread notifications
        if (count === 0) {
            unreadCount.style.display = 'none';
        } else {
            unreadCount.style.display = 'inline-block';
        }
    }

    // Initial call to update the unread count on page load
    updateUnreadCount();
});



//Profile dropdown process
// Get the dropdown button and the dropdown container
const profileButton = document.querySelector('.Pdropdown-button');
const profileDropdown = document.querySelector('.Profile-dropdown');

// Add a click event to toggle the dropdown visibility
profileButton.addEventListener('click', function (event) {
    profileDropdown.classList.toggle('show');
    event.stopPropagation(); // Prevent click event from propagating to the window
});

// Close the dropdown if clicked outside
window.addEventListener('click', function (event) {
    if (!profileDropdown.contains(event.target) && event.target !== profileButton) {
        profileDropdown.classList.remove('show');
    }
});