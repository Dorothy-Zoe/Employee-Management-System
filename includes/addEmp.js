//ADD EMPLOYEE JS
function showTab(index) {
    let tabs = document.querySelectorAll('.tab');
    let sections = document.querySelectorAll('.form-section');

    tabs.forEach(tab => tab.classList.remove('active'));
    sections.forEach(section => section.classList.remove('active'));

    tabs[index].classList.add('active');
    sections[index].classList.add('active');
}

document.getElementById('photo').addEventListener('change', function(event) {
    let file = event.target.files[0];
    let preview = document.getElementById('preview');

    if (file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = "block";
    } else {
        preview.style.display = "none";
    }
});


function validateForm() {
    let inputs = document.querySelectorAll("input, select");
    for (let input of inputs) {
        if (!input.value) {
            alert("Please fill out all required fields.");
            return false;
        }
    }
    return true;
}

