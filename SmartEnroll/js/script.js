const slides = document.querySelectorAll(".slide");
let currentSlide = 0;

function showNextSlide() {
    if (!slides.length) return;
    slides[currentSlide].classList.remove("active");
    currentSlide = (currentSlide + 1) % slides.length;
    slides[currentSlide].classList.add("active");
}

if (slides.length) {
    setInterval(showNextSlide, 5000);
}

if (window.flatpickr && document.querySelector("#dob")) {
    const dobPicker = flatpickr("#dob", {
        dateFormat: "m/d/Y",
        maxDate: "today",

        /* UX */
        allowInput: true,              // MANUAL INPUT ENABLED
        clickOpens: true,
        monthSelectorType: "dropdown",
        yearSelectorType: "dropdown",
        defaultDate: "2010-01-01",

        onChange: function(selectedDates) {
            if (selectedDates.length) {
                calculateAge(selectedDates[0]);
            }
        }
    });

    const calendarIcon = document.querySelector(".calendar-icon");
    if (calendarIcon) {
        calendarIcon.addEventListener("click", function () {
            dobPicker.open();
        });
    }
}

/* AUTO AGE CALCULATION */
function calculateAge(dob) {
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();

    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }

const ageInput = document.getElementById("age");
if (ageInput) {
    ageInput.value = age;
}
}

document.addEventListener("DOMContentLoaded", () => {
    const passwordFields = document.querySelectorAll(".password-field");

    passwordFields.forEach((field) => {
        const passwordInput = field.querySelector("input");
        const passwordToggle = field.querySelector(".password-icon");

        if (!passwordInput || !passwordToggle) {
            return;
        }

        passwordToggle.addEventListener("click", () => {
            const isHidden = passwordInput.type === "password";
            passwordInput.type = isHidden ? "text" : "password";
            const icon = passwordToggle.querySelector("i");
            if (icon) {
                icon.className = isHidden ? "fa-solid fa-eye-slash" : "fa-solid fa-eye";
            }
        });
    });
});
