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

// Mark notification as read when clicked
document.addEventListener('DOMContentLoaded', function () {
    // Get the "Mark all as read" button
    const markAllReadButton = document.getElementById('markAllRead');

    // Handle the "Mark all as read" button click event
    markAllReadButton.addEventListener('click', async function () {
        try {
            // Send a POST request to mark all notifications as read
            const response = await fetch('adminDashboard.php', {  // Adjust URL if necessary
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


// Function to show the modal
// Open modal
document.getElementById('viewAllNotifications').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('allNotificationsModal').style.display = 'block';
});

// Close modal
document.getElementById('closeModal').addEventListener('click', function() {
    document.getElementById('allNotificationsModal').style.display = 'none';
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    const modal = document.getElementById('allNotificationsModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function () {
    // Get the modal elements
    const modal = document.getElementById('allNotificationsModal');
    const closeModal = document.getElementById('closeModal');
    const clearNotificationsBtn = document.getElementById('clearNotificationsBtn');

    // Open the modal when needed (if you have a button or condition for opening the modal)
    // Example: openModalBtn.addEventListener('click', () => modal.style.display = 'block');

    // Close the modal when clicking the close button (X)
    closeModal.addEventListener('click', function () {
        modal.style.display = 'none';
    });

    // Clear notifications functionality
    clearNotificationsBtn.addEventListener('click', async function () {
        try {
            const response = await fetch('adminDashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'clearNotifications': true
                })
            });

            if (response.ok) {
                // Mark all notifications as read and remove them from the UI
                const notificationItems = document.querySelectorAll('.notification-item.unread');
                notificationItems.forEach(function (notification) {
                    notification.classList.remove('unread');
                    notification.classList.add('read');
                });

                // Optionally, update the message in the modal
    document.querySelector('.all-notifications-list').innerHTML = '<div class="no-notifications-message">' +
        '<p>No notifications available</p>' +
    '</div>';
            } else {
                console.error('Failed to clear notifications');
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
        }
    });
});