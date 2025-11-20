function togglePassword() {
    let passwordField = document.getElementById("password");
    let toggleIcon = document.getElementById("toggle-password");

    if (passwordField.type === "password") {
        passwordField.type = "text";
        toggleIcon.src = "../src/eye-open.png";  // Change to open-eye icon
    } else {
        passwordField.type = "password";
        toggleIcon.src = "../src/eye-closed.png"; // Change back to closed-eye icon
    }
}

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
    modal.style.display = "none";
    resetPasswordModal.style.display = "block";
}