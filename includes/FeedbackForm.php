    
    
    
    <!-- Feedback Icon -->
    <div class="feedback-icon">
        <i id="feedback-icon" class="fas fa-envelope" style="cursor: pointer;"></i> <!-- Font Awesome Form Icon -->
    </div>
    <!-- Modal (Feedback Form) -->
    <div id="feedbackModal" class="FBmodal">
        <div class="modal-content">
            <span id="closeModalBtn" class="close">&times;</span>
            <h2 class="modalTitle">System Feedback Form</h2>
            <form method="POST" enctype="multipart/form-data">
                <!-- Hidden Field to Pass User Role Dynamically -->
                <!-- <input type="hidden" name="user_role" value="<?php echo $user_role; ?>"> -->

                <!-- Feedback Category -->
                <label for="feedback_category">Feedback Category:</label>
                <select id="feedback_category" name="feedback_category" required>
                    <option value="Bug Report">Bug Report</option>
                    <option value="Feature Request">Feature Request</option>
                    <option value="User Experience">User Experience</option>
                    <option value="Performance Issue">Performance Issue</option>
                    <option value="Other">Other</option>
                </select><br><br>

                <!-- Page or Module -->
                <label for="page_or_module">Page or Module (Optional):</label>
                <input type="text" id="page_or_module" name="page_or_module" placeholder="Ex. Schedule Module"><br><br>

                <!-- Feedback Message -->
                <label for="feedback_message">Comments:</label>
                <textarea id="feedback_message" name="feedback_message" required></textarea><br><br>

                <!-- Screenshot Upload (Modern Style) -->
                <label for="screenshot">Screenshot (Optional):</label><br>
                <div class="file-upload-container">
                    <!-- Custom File Upload Button -->
                    <label for="screenshot" class="custom-file-upload">
                        <i class="fas fa-upload"></i> Upload File
                    </label>
                    <input type="file" id="screenshot" name="screenshot" accept="image/*,application/pdf,.docx,.xlsx">
                    
                    <!-- Display selected file name next to the input -->
                    <input type="text" id="file-name" disabled placeholder="No file selected" class="file-name-display"/>
                </div><br><br>

                <!-- Priority Level -->
                <label for="priority_level">Priority Level:</label>
                <select id="priority_level" name="priority_level" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select><br><br>

                <!-- Consent for Follow-Up (Dropdown) -->
                <label for="consent_for_follow_up">Consent for Follow-Up:</label>
                <select id="consent_for_follow_up" name="consent_for_follow_up" required>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select><br><br>

                <!-- Star Rating with Descriptions -->
                <label for="star_rating">Rate Your Experience (Optional, 1-5):</label><br>
                <small>Select a rating for your experience:</small>
                <p class="desc">1 - Poor, 2 - Fair, 3 - Good, 4 - Very Good, 5 - Excellent</p>
                <select id="star_rating" name="star_rating" required>
                    <option value="1">1 - Poor</option>
                    <option value="2">2 - Fair</option>
                    <option value="3">3 - Good</option>
                    <option value="4">4 - Very Good</option>
                    <option value="5">5 - Excellent</option>
                </select><br><br>

                <!-- Submit Button -->
                <button type="submit">Submit Feedback</button>
            </form>
        </div>
    </div>

    <script>
        // Get modal and button elements
        const modal = document.getElementById("feedbackModal");
        const feedbackIcon = document.getElementById("feedback-icon");
        const closeModalBtn = document.getElementById("closeModalBtn");

        // When the user clicks the feedback icon, open the modal
        feedbackIcon.onclick = function() {
            modal.style.display = "flex"; // Make the modal visible
        }

        // When the user clicks on <span> (x), close the modal
        closeModalBtn.onclick = function() {
            modal.style.display = "none"; // Hide the modal
        }

        // When the user clicks anywhere outside the modal, close it
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = "none"; // Hide the modal
            }
        }

        // Show the selected file name in the text input field
        document.getElementById("screenshot").addEventListener("change", function() {
            var fileName = this.files.length > 0 ? this.files[0].name : "No file selected";
            document.getElementById("file-name").value = fileName;
        });


       

    </script>



<style>
/* Feedback Icon */
.feedback-icon {
    position: absolute;
    bottom: 1%; /* Move to lower part */
    right: 20px;  /* Move to left part */
    background-color: transparent; /* Set background to transparent */
    border-radius: 50%; /* Keep the circular shape */
    padding: 15px; /* Add padding to increase size */
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.3); /* Optional: Adds shadow for effect */
    opacity: 0.5; /* Lower opacity by default */
    transition: opacity 0.3s ease-in-out, background-color 0.3s ease-in-out; /* Smooth transition for opacity and background */
}

.feedback-icon i {
    font-size: 30px;
    color: #ff4757; /* Set icon color */
    cursor: pointer;
}

.feedback-icon i:hover {
    opacity: 1; /* Increase opacity when hovered */
}

.feedback-icon:hover {
    opacity: 1; /* Ensure the icon becomes fully visible when hovered */
    background-color: rgba(255, 71, 87, 0.1); /* Add a slight transparent background color on hover */
}


    /* Modal Background */

    .modalTitle {
        font-size: 24px;
        color: #333;
        margin-bottom: 20px;
        text-align: center;
    }

    .FBmodal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
    }

    /* Modal Box */
    .modal-content {
        background: rgba(255, 255, 255, 0.95);
        padding: 25px;
        width: 40%;
        height: 90%;
        border-radius: 12px;
        box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.3);
        position: relative;
        animation: fadeIn 0.3s ease-in-out;
        overflow-y: auto; /* Allows scrolling */
    }

    /* Close Button */
    .close {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 22px;
        font-weight: bold;
        color: #333;
        cursor: pointer;
        transition: color 0.3s ease-in-out;
    }

    .close:hover {
        color: #ff4757;
    }

    /* Input Fields */
    .modal-content input,
    .modal-content select,
    .modal-content textarea {
        width: 90%;
        padding: 10px;
        margin-top: 10px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 16px;
        outline: none;
    }

    /* Button Styling */
    .modal-content button {
        width: 100%;
        background: #ff4757;
        color: white;
        border: none;
        padding: 12px;
        margin-top: 15px;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s ease-in-out;
    }

    .modal-content button:hover {
        background: #e84118;
    }

    /* Custom File Upload Button */
.modal-content input[type="file"] {
    display: none; /* Hide default file input */
}

/* Custom File Upload Button */
.custom-file-upload {
    display: inline-block;
    background-color: #f0f0f0; /* Light background similar to your design */
    color: #333;
    border: 2px solid #ccc;
    margin-top: 2%;
    padding: 10px 10px;
    border-radius: 5%;
    font-size: 18px; /* Icon size */
    cursor: pointer;
    transition: background-color 0.3s ease;
    text-align: center;
    width: 100%;
}

.custom-file-upload:hover {
    background-color: #e1e1e1; /* Light hover effect */
}

/* File name input beside the upload button */
.file-upload-container {
    display: flex;
    align-items: center;
}

.file-name-display {
    display: inline-block;
    width: 70%; /* Adjust width to make it fit beside the button */
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-left: 10px;
    font-size: 16px;
    color: #333;
    background-color: #f1f1f1;
}

.file-name-display:disabled {
    background-color: #e1e1e1;
    cursor: not-allowed;
    font-size: 80%;
}

.desc {
    font-size: 90%;
    color: #666;
    margin-top: 5px;
    margin-bottom: 2px;
}
    @keyframes fadeIn {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
    }

   /* CSS for Success and Error Messages */
.message-box {
    font-size: 16px;
    font-family: Arial, sans-serif;
    text-align: center;
    width: 80%; /* Adjust width as necessary */
    max-width: 400px; /* Set a max width */
    position: fixed;
    top: 20px; /* Place it near the top of the screen */
    left: 50%;
    transform: translateX(-50%); /* Center the message horizontally */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    font-weight: bold;
    z-index: 9999; /* Ensure it stays above all other elements */
    display: none; /* Initially hidden */
    transition: all 0.3s ease-in-out;
}

/* Success Message */
.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Error Message */
.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

</style>
