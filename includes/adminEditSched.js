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



//Edit Schedule Modal
// Function to open the Edit Schedule modal
function openEditScheduleModal(scheduleData) {
    let modal = document.getElementById("editScheduleModal");

    // Ensure scheduleData is parsed properly
    if (typeof scheduleData === "string") {
        scheduleData = JSON.parse(scheduleData);
    }

    // Populate form fields
  
    document.getElementById("editProfessor").value = scheduleData.FName + " " + scheduleData.LName || "";
    document.getElementById("editDepartment").value = scheduleData.Department || "";
    document.getElementById("editCourse").value = scheduleData.Course || "";
    document.getElementById("editSection").value = scheduleData.Section || "";
    document.getElementById("editSubject").value = scheduleData.Subject_Description || "";
    document.getElementById("editSubjectCode").value = scheduleData.Subject_Code || "";
    document.getElementById("editCourseType").value = scheduleData.Course_Type || "";
    document.getElementById("editUnits").value = scheduleData.Units || "";
    document.getElementById("editRoom").value = scheduleData.RoomLab || "";
    document.getElementById("editDays").value = scheduleData.Days || "";
    document.getElementById("editStartTime").value = scheduleData.StartTime || "";
    document.getElementById("editEndTime").value = scheduleData.EndTime || "";

    // Show modal
    modal.style.display = "block";
}

document.addEventListener("DOMContentLoaded", function () {
    // Close modal when clicking the Cancel button
    document.getElementById("cancelEditModal").addEventListener("click", function () {
        document.getElementById("editScheduleModal").style.display = "none";
    });

    // Close modal when clicking the close button (×)
    document.getElementById("closeEditModal").addEventListener("click", function () {
        document.getElementById("editScheduleModal").style.display = "none";
    });

    // Close modal when clicking outside of modal content
    window.onclick = function (event) {
        let modal = document.getElementById("editScheduleModal");
        if (event.target === modal) {
            modal.style.display = "none";
        }
    };
});