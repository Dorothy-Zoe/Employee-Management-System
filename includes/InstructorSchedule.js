function applyFilters() {
    let filterDay = document.getElementById("filterDay").value;
    let filterSection = document.getElementById("filterSection").value;
    let entries = document.getElementById("entriesDropdown").value;

    let urlParams = new URLSearchParams(window.location.search);
    urlParams.set("filterDay", filterDay);
    urlParams.set("filterSection", filterSection);
    urlParams.set("entries", entries);
    urlParams.set("page", 1); // Reset to first page after filtering

    window.location.href = window.location.pathname + "?" + urlParams.toString();
}

function changePage(page) {
    let urlParams = new URLSearchParams(window.location.search);
    urlParams.set("page", page);

    window.location.href = window.location.pathname + "?" + urlParams.toString();
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

// Notification function
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


//   // Check localStorage for saved notification statuses on page load
// document.addEventListener('DOMContentLoaded', function() {
//     // Get all notification items
//     const notifications = document.querySelectorAll('.notification-item');

//     notifications.forEach(function(notification) {
//         const notifId = notification.id.split('-')[1]; // Get notification ID from the element's id
        
//         // If the notification is marked as read in localStorage, update its class
//         if (localStorage.getItem(`notif-read-${notifId}`) === 'true') {
//             notification.classList.remove('unread');
//             notification.classList.add('read');
//         }
//     });
// });

// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function () {
    // Get the "Mark all as read" button
    const markAllReadButton = document.getElementById('markAllRead');

    // Handle the "Mark all as read" button click event
    markAllReadButton.addEventListener('click', async function () {
        try {
            // Send a POST request to mark all notifications as read
            const response = await fetch('instructorDashboard.php', {  // Adjust URL if necessary
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





