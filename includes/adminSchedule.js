function applyFilters() {
    updateURL();
}

function changePage(page) {
    updateURL({ page });
}

function changeEntries(entries) {
    updateURL({ entries });
}

function updateURL(extraParams = {}) {
    let params = new URLSearchParams(window.location.search);
    params.set("filterDay", document.getElementById("filterDay").value);
    params.set("filterSection", document.getElementById("filterSection").value);
    params.set("entries", document.getElementById("entriesDropdown").value);

    // Add extra parameters like page number
    for (let key in extraParams) {
        params.set(key, extraParams[key]);
    }

    window.location.href = "adminSchedule.php?" + params.toString();
}


//Add Schedule Modal 
document.addEventListener("DOMContentLoaded", function() {
    // Get modal elements
    var modal = document.getElementById("scheduleModal");
    var openModalBtn = document.getElementById("openModalBtn");
    var closeModalBtn = document.getElementById("closeModalBtn");

    // Show modal when clicking the "Add New Schedule" button
    openModalBtn.addEventListener("click", function() {
        modal.style.display = "block";
    });

    // Close modal when clicking the close (×) button
    closeModalBtn.addEventListener("click", function() {
        modal.style.display = "none";
    });

    // Close modal when clicking the Cancel button
    closeModalBtn.addEventListener("click", function() {
        modal.style.display = "none";
    });

    // Close modal when clicking outside the modal content
    window.addEventListener("click", function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
});



// Edit Schedule Modal

// Function to open the Edit Schedule modal
function openEditScheduleModal(scheduleData) {
    const modal = document.getElementById("editScheduleModal");

    // Parse stringified JSON if needed
    if (typeof scheduleData === "string") {
        try {
            scheduleData = JSON.parse(scheduleData);
        } catch (e) {
            console.error("Invalid JSON data for schedule:", e);
            return;
        }
    }

    // Set hidden schedule ID
    document.querySelector('input[name="editScheduleId"]').value = scheduleData.ID || "";

    // Populate fields
    document.getElementById("editProfessor").value     = (scheduleData.FName + " " + scheduleData.LName) || "";
    document.getElementById("editDepartment").value     = scheduleData.Department || "";
    document.getElementById("editCourse").value         = scheduleData.Course || "";
    document.getElementById("editSection").value        = scheduleData.Section || "";
    document.getElementById("editSubject").value        = scheduleData.Subject_Description || "";
    document.getElementById("editSubjectCode").value    = scheduleData.Subject_Code || "";
    document.getElementById("editCourseType").value     = scheduleData.Course_Type || "";
    document.getElementById("editUnits").value          = scheduleData.Units || "";
    document.getElementById("editRoom").value           = scheduleData.RoomLab || "";
    document.getElementById("editDays").value           = scheduleData.Days || "";
    document.getElementById("editStartTime").value      = scheduleData.StartTime || "";
    document.getElementById("editEndTime").value        = scheduleData.EndTime || "";

    // Show modal
    modal.style.display = "block";
}

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("editScheduleModal");

    // Close with cancel button
    document.getElementById("cancelEditModal").addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Close with "×"
    document.getElementById("closeEditModal").addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Close by clicking outside modal content
    window.onclick = function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };
});



// Success & Error Message 
    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            let successAlert = document.getElementById("success-alert");
            let errorAlert = document.getElementById("error-alert");

            if (successAlert) {
                successAlert.style.transition = "opacity 0.5s";
                successAlert.style.opacity = "0";
                setTimeout(() => successAlert.remove(), 500);
            }
            
            if (errorAlert) {
                errorAlert.style.transition = "opacity 0.5s";
                errorAlert.style.opacity = "0";
                setTimeout(() => errorAlert.remove(), 500);
            }
        }, 5000); // 5 seconds before hiding
    });



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