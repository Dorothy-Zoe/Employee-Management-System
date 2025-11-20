


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
                const response = await fetch('instructorDashboard.php', {
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
    