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

// // Modal
// // Get modal and elements
// const modal = document.getElementById("ForgotPasswordModal");
// const forgotPasswordLink = document.getElementById("forgotPassword");
// const closeModal = document.querySelector(".close");

// // Ensure modal is hidden by default
// modal.style.display = "none";

// // Show modal ONLY when "Forgot Password?" is clicked
// forgotPasswordLink.addEventListener("click", function (event) {
//     event.preventDefault(); // Prevent default link behavior
//     modal.style.display = "flex"; // Show modal
// });

// // Hide modal when close button is clicked
// closeModal.addEventListener("click", function () {
//     modal.style.display = "none";
// });

// // Hide modal when clicking outside the modal content
// window.addEventListener("click", function (event) {
//     if (event.target === modal) {
//         modal.style.display = "none";
//     }
// });


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


