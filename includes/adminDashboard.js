document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".dropdown-toggle").forEach((dropdown) => {
        dropdown.addEventListener("click", function (event) {
            event.preventDefault(); // Prevents jumping to the top
            let dropdownContent = this.nextElementSibling;
            
            // Hide other dropdowns (optional)
            document.querySelectorAll(".dropdown-content").forEach(content => {
                if (content !== dropdownContent) content.classList.remove("show");
            });

            // Toggle the clicked dropdown
            dropdownContent.classList.toggle("show");
        });
    });

    // Close dropdown if clicked outside
    document.addEventListener("click", function (event) {
        if (!event.target.closest(".dropdown")) {
            document.querySelectorAll(".dropdown-content").forEach(content => content.classList.remove("show"));
        }
    });
});

//Filter in Admin Schedule
function applyFilters() {
    let filterDay = document.getElementById("filterDay").value;
    let filterSection = document.getElementById("filterSection").value;
    window.location.href = `adminSchedule.php?day=${filterDay}&section=${filterSection}`;
}



