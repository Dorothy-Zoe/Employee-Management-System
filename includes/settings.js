

document.getElementById("save-password").addEventListener("click", function(event) {
    let newPassword = document.getElementById("new_password").value;
    let confirmPassword = document.getElementById("confirm_new_password").value;
    let messageBox = document.getElementById("message-box");

    if (newPassword.length < 8) {
        messageBox.style.display = "block";
        messageBox.style.color = "red";
        messageBox.innerHTML = "New password must be at least 8 characters long.";
        event.preventDefault();
    } else if (newPassword !== confirmPassword) {
        messageBox.style.display = "block";
        messageBox.style.color = "red";
        messageBox.innerHTML = "New passwords do not match.";
        event.preventDefault();
    } else {
        messageBox.style.display = "none"; // Hide message box on success
    }
});


document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.settings-content');
    
    // Set default active tab
    document.getElementById('account-tab').classList.add('active');
    document.getElementById('account-settings').classList.add('active');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content based on tab ID
            if (this.id === 'account-tab') {
                document.getElementById('account-settings').classList.add('active');
            } else if (this.id === 'notification-tab') {
                document.getElementById('notification-settings').classList.add('active');
            } else if (this.id === 'privacy-tab') {
                document.getElementById('privacy-settings').classList.add('active');
            }
        });
    });
});




// Get the modal elements
var modal = document.getElementById("ForgotPasswordModal");
var resetPasswordModal = document.getElementById("ResetPasswordModal");
var btn = document.getElementById("forgotPassword");
var span = document.getElementsByClassName("close");

// Open the "Forgot Password" modal
btn.onclick = function() {
    modal.style.display = "flex";
}

// Close the "Forgot Password" modal
span[0].onclick = function() {
    modal.style.display = "none";
}

// Close the reset password modal
span[1].onclick = function() {
    resetPasswordModal.style.display = "none";
}

// When the user clicks anywhere outside the modal, close it
window.onclick = function(event) {
    if (event.target == modal || event.target == resetPasswordModal) {
        modal.style.display = "none";
        resetPasswordModal.style.display = "none";
    }
}
// Show the reset password modal after the email has been submitted
function showResetPasswordModal() {
    modal.style.display = "none";  // Close the Forgot Password Modal
    resetPasswordModal.style.display = "block";  // Open the Reset Password Modal
}



