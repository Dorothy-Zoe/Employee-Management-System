// Add click event to cards
document.querySelectorAll('.leave-card').forEach(function(card) {
    card.addEventListener('click', function() {
        var status = this.getAttribute('data-status'); // Get the status from the card's data-status attribute
        window.location.href = 'HRLeaveApplication.php?status=' + status; // Redirect to the page with the status query parameter
    });
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

