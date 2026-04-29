        // ===============================
        // ELEMENTS
        // ===============================
        const dobInput = document.getElementById("dob");
        const ageInput = document.getElementById("age");
        const calendarBtn = document.getElementById("calendarBtn");
        const picker = document.getElementById("dobPicker");

        const monthLabel = document.getElementById("monthLabel");
        const yearLabel = document.getElementById("yearLabel");

        const provinceSelect = document.getElementById("province");
        const municipalitySelect = document.getElementById("municipality");
        const barangaySelect = document.getElementById("barangay");

        const submitBtn = document.getElementById("submitBtn");
        const summaryModal = document.getElementById("summaryModal");
        const summaryContent = document.getElementById("summaryContent");
        const confirmBtn = document.getElementById("confirmSubmit");
        const cancelBtn = document.getElementById("cancelSubmit");
        const addEmergencyContactBtn = document.getElementById("addEmergencyContactBtn");
        const emergencyBlocks = Array.from(document.querySelectorAll("[data-emergency-block]"))
            .filter((block) => block.querySelector(".form-group:not(.form-group-hidden)"));

        const form = document.getElementById("enrollmentForm");
        const fieldLabels = window.smartenrollFieldLabels || {};
        const provinceNameInput = document.getElementById("province_name");
        const municipalityNameInput = document.getElementById("municipality_name");

        function getFieldLabel(name, fallback) {
            return fieldLabels[name] || fallback;
        }

        function syncLocationName(hiddenInput, selectElement) {
            if (!hiddenInput) {
                return;
            }

            hiddenInput.value = selectElement && selectElement.value
                ? (selectElement.selectedOptions[0]?.text || "")
                : "";
        }

        function isInteractiveField(element) {
            return !!element && !element.disabled && !element.closest(".form-group-hidden");
        }
        // ===============================
        // FORMAT VALIDATION FUNCTIONS
        // ===============================
        function isValidEmail(email) {
            // Simple email regex
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function isValidContact(contact) {
            // Philippine mobile number format: starts with 09, 11 digits
            return /^09\d{9}$/.test(contact);
        }
        let view = "days";
        let selectedDate = new Date();


        // ======================================================
        // DATE PICKER
        // ======================================================
        if (calendarBtn && picker && monthLabel && yearLabel && isInteractiveField(dobInput)) {
            calendarBtn.addEventListener("click", (e) => {
                e.stopPropagation();

                if (picker.style.display === "block") {
                    picker.style.display = "none";
                    calendarBtn.style.visibility = "visible";
                } else {
                    picker.style.display = "block";
                    calendarBtn.style.visibility = "hidden";
                    render();
                }
            });

            document.addEventListener("click", (e) => {
                if (!picker.contains(e.target) && !calendarBtn.contains(e.target)) {
                    picker.style.display = "none";
                    calendarBtn.style.visibility = "visible";
                }
            });

            monthLabel.onclick = () => { view = "months"; render(); };
            yearLabel.onclick = () => { view = "years"; render(); };
        }

        function render() {
            updateHeader();

            const columns = picker.querySelector(".picker-columns");
            const monthCol = picker.querySelector(".month-col");
            const yearCol = picker.querySelector(".year-col");

            columns.style.display = view === "days" ? "none" : "flex";

            monthCol.classList.remove("show");
            yearCol.classList.remove("show");

            if (view === "months") monthCol.classList.add("show");
            if (view === "years") yearCol.classList.add("show");

            if (view === "days") renderDays();
            if (view === "months") renderMonths();
            if (view === "years") renderYears();
        }

        function updateHeader() {
            const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
            monthLabel.textContent = months[selectedDate.getMonth()];
            yearLabel.textContent = selectedDate.getFullYear();
        }

        function renderMonths() {
            const col = picker.querySelector(".month-col");
            col.innerHTML = "";

            const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

            months.forEach((m, i) => {
                const div = document.createElement("div");
                div.textContent = m;
                if (i === selectedDate.getMonth()) div.classList.add("active");

                div.onclick = () => {
                    selectedDate.setMonth(i);
                    view = "days";
                    render();
                };
                col.appendChild(div);
            });
        }

        function renderYears() {
            const col = picker.querySelector(".year-col");
            col.innerHTML = "";

            for (let y = new Date().getFullYear(); y >= 1920; y--) {
                const div = document.createElement("div");
                div.textContent = y;

                if (y === selectedDate.getFullYear()) div.classList.add("active");

                div.onclick = () => {
                    selectedDate.setFullYear(y);
                    view = "days";
                    render();
                };

                col.appendChild(div);
            }
        }

        function renderDays() {
            const grid = picker.querySelector(".day-grid");
            grid.innerHTML = "";

            const year = selectedDate.getFullYear();
            const month = selectedDate.getMonth();
            const days = new Date(year, month + 1, 0).getDate();

            for (let d = 1; d <= days; d++) {
                const div = document.createElement("div");
                div.textContent = d;

                div.onclick = () => {
                    selectedDate.setDate(d);
                    setDate(selectedDate);
                    picker.style.display = "none";
                    calendarBtn.style.visibility = "visible";
                };

                grid.appendChild(div);
            }
        }

        function setDate(date) {
            dobInput.value =
                String(date.getMonth() + 1).padStart(2, "0") + "/" +
                String(date.getDate()).padStart(2, "0") + "/" +
                date.getFullYear();

            calculateAge(date);
        }

        function calculateAge(dob) {
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();

            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            if (ageInput && !ageInput.disabled) {
                ageInput.value = age;
            }
        }


        // ======================================================
        // MANUAL DOB INPUT
        // ======================================================
        if (dobInput && !dobInput.disabled) {
            dobInput.addEventListener("input", () => {
                let value = dobInput.value.replace(/\D/g, "").substring(0, 8);

                if (value.length >= 5)
                    dobInput.value = value.slice(0,2)+"/"+value.slice(2,4)+"/"+value.slice(4);
                else if (value.length >= 3)
                    dobInput.value = value.slice(0,2)+"/"+value.slice(2);
                else
                    dobInput.value = value;
            });

            dobInput.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();

                    const parts = dobInput.value.split("/");
                    if (parts.length === 3) {
                        const manualDate = new Date(parts[2], parts[0]-1, parts[1]);
                        if (!isNaN(manualDate) && ageInput) calculateAge(manualDate);
                    }
                }
            });
        }


        // ======================================================
        // ADDRESS (PSGC API)
        // ======================================================
        if (provinceSelect && municipalitySelect && barangaySelect && !provinceSelect.disabled) {
            fetch("https://psgc.gitlab.io/api/provinces/")
            .then(r => r.json())
            .then(data => {
                data.sort((a, b) => a.name.localeCompare(b.name));

                data.forEach(p => {

                    const opt = document.createElement("option");
                    opt.text = p.name;
                    opt.value = p.code; // use CODE for API
                    provinceSelect.appendChild(opt);

                });

            });

            provinceSelect.addEventListener("change", () => {

                const provinceCode = provinceSelect.value;
                syncLocationName(provinceNameInput, provinceSelect);
                syncLocationName(municipalityNameInput, null);

                municipalitySelect.innerHTML = `<option value="">Select Municipality</option>`;
                barangaySelect.innerHTML = `<option value="">Select Barangay</option>`;
                municipalitySelect.disabled = true;
                barangaySelect.disabled = true;

                if (!provinceCode) return;

                fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`)
                .then(r => r.json())
                .then(data => {

                    data.forEach(m => {

                        const opt = document.createElement("option");
                        opt.text = m.name;
                        opt.value = m.code; // use code for API
                        municipalitySelect.appendChild(opt);

                    });

                    municipalitySelect.disabled = false;
                });

            });
            municipalitySelect.addEventListener("change", () => {

                const municipalityCode = municipalitySelect.value;
                syncLocationName(municipalityNameInput, municipalitySelect);

                barangaySelect.innerHTML = `<option value="">Select Barangay</option>`;
                barangaySelect.disabled = true;

                if (!municipalityCode) return;

                fetch(`https://psgc.gitlab.io/api/cities-municipalities/${municipalityCode}/barangays/`)
                .then(r => r.json())
                .then(data => {

                    data.forEach(b => {
                        barangaySelect.appendChild(new Option(b.name, b.name));
                    });

                    barangaySelect.disabled = false;
                });

            });
        }

        // ======================================================
        // GUARDIAN AUTO-FILL
        // ======================================================
        const guardianRadios = document.querySelectorAll('input[name="guardian_type"]');

        const guardianFields = {
            lname: document.querySelector('input[name="guardian_lname"]'),
            fname: document.querySelector('input[name="guardian_fname"]'),
            mname: document.querySelector('input[name="guardian_mname"]'),
            occ: document.querySelector('input[name="guardian_occ"]'),
            contact: document.querySelector('input[name="guardian_contact"]')
        };

        const father = {
            lname: document.querySelector('input[name="father_lname"]'),
            fname: document.querySelector('input[name="father_fname"]'),
            mname: document.querySelector('input[name="father_mname"]'),
            occ: document.querySelector('input[name="father_occ"]'),
            contact: document.querySelector('input[name="father_contact"]')
        };

        const mother = {
            lname: document.querySelector('input[name="mother_lname"]'),
            fname: document.querySelector('input[name="mother_fname"]'),
            mname: document.querySelector('input[name="mother_mname"]'),
            occ: document.querySelector('input[name="mother_occ"]'),
            contact: document.querySelector('input[name="mother_contact"]')
        };

        guardianRadios.forEach(radio => {
            radio.addEventListener("change", () => {

                if (radio.value === "father") fillGuardian(father, true);
                else if (radio.value === "mother") fillGuardian(mother, true);
                else fillGuardian(null, false);

            });
        });

        function fillGuardian(source, readonly) {

            Object.values(guardianFields).forEach(f => {
                if (!f || f.disabled) {
                    return;
                }

                const key = Object.keys(guardianFields).find((k) => guardianFields[k] === f);
                const sourceField = key && source ? source[key] : null;
                f.value = sourceField && !sourceField.disabled ? sourceField.value : "";
                f.readOnly = readonly;
            });
        }


        // ======================================================
        // MEDICATION FIELD ENABLE
        // ======================================================
        const medicationRadios = document.querySelectorAll('input[name="medication"]');
        const medicationInput = document.getElementById("medication_details");

        if (medicationInput && medicationRadios.length > 0) {
            medicationRadios.forEach(radio => {
                radio.addEventListener("change", () => {
                    medicationInput.disabled = radio.value !== "yes";
                    if (radio.value !== "yes") medicationInput.value = "";
                });
            });
        }

        function setEmergencyBlockEnabled(block, enabled) {
            block.querySelectorAll("input, select, textarea").forEach((field) => {
                if (field.closest(".form-group-hidden")) {
                    field.disabled = true;
                    return;
                }

                field.disabled = !enabled;
            });
        }

        function updateEmergencyAddButton() {
            if (!addEmergencyContactBtn) return;

            const hiddenBlocks = emergencyBlocks.filter((block) => block.classList.contains("emergency-contact-hidden"));
            addEmergencyContactBtn.disabled = hiddenBlocks.length === 0;
            addEmergencyContactBtn.style.display = hiddenBlocks.length === 0 ? "none" : "inline-flex";
        }

        emergencyBlocks.forEach((block, index) => {
            setEmergencyBlockEnabled(block, index === 0);
        });
        updateEmergencyAddButton();

        if (addEmergencyContactBtn) {
            addEmergencyContactBtn.addEventListener("click", () => {
                const nextHiddenBlock = emergencyBlocks.find((block) => block.classList.contains("emergency-contact-hidden"));
                if (!nextHiddenBlock) {
                    updateEmergencyAddButton();
                    return;
                }

                nextHiddenBlock.classList.remove("emergency-contact-hidden");
                setEmergencyBlockEnabled(nextHiddenBlock, true);
                updateEmergencyAddButton();
            });
        }

        const emergencyRemoveBtns = document.querySelectorAll(".emergency-remove-btn");

        function removeEmergencyContact(blockToRemove) {
            if (!blockToRemove) return;

            blockToRemove.classList.add("emergency-contact-hidden");
            setEmergencyBlockEnabled(blockToRemove, false);

            blockToRemove.querySelectorAll("input, select, textarea").forEach((field) => {
                if (field.type === "radio" || field.type === "checkbox") {
                    field.checked = false;
                } else if (!field.disabled) {
                    field.value = "";
                }
            });

            updateEmergencyAddButton();
        }

        emergencyRemoveBtns.forEach((btn) => {
            btn.addEventListener("click", () => {
                const blockNum = btn.dataset.removeBlock;
                const block = document.querySelector(`[data-emergency-block="${blockNum}"]`);
                removeEmergencyContact(block);
            });
        });


        // ======================================================
        // COMPLETION DATE AUTO TODAY
        // ======================================================
        document.addEventListener("DOMContentLoaded", () => {
            
            const completionDateInput = document.getElementById("completionDate");
            const display = document.getElementById("completionDisplay");

            if (!completionDateInput || completionDateInput.disabled) {
                return;
            }

            function formatDate(date) {
                return date.toLocaleDateString("en-US");
            }

            function setToday() {
                const today = new Date();
                today.setMinutes(today.getMinutes() - today.getTimezoneOffset());

                const formattedISO = today.toISOString().split("T")[0];

                completionDateInput.value = formattedISO;
                completionDateInput.max = formattedISO;
                if (display) {
                    display.value = formatDate(today);
                }
            }

            // Set default to today
            setToday();

            // When user clicks display field â†’ open date picker
            if (display) {
                display.addEventListener("click", () => {
                    completionDateInput.showPicker();
                });
            }

            // When date changes
            completionDateInput.addEventListener("change", () => {
                const selectedDate = new Date(completionDateInput.value);

                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Prevent future dates manually (extra safety)
                if (selectedDate > today) {
                    setToday();
                    return;
                }

                if (display) {
                    display.value = formatDate(selectedDate);
                }
            });
        });


        // ======================================================
        // SUBMIT â†’ VALIDATE â†’ SUMMARY
        // ======================================================
        const validationPopup = document.getElementById("validationPopup");
        const okValidation = document.getElementById("okValidation");
        const popupIcon = document.getElementById("popupIcon");
        const gradeLevelInputs = Array.from(document.querySelectorAll('input[name="grade_level"]'))
            .filter((input) => !input.disabled);
        const medsYes = document.querySelector('input[name="medication"][value="yes"]:not([disabled])');
        const medsNo = document.querySelector('input[name="medication"][value="no"]:not([disabled])');
        const medsInput = document.getElementById("medication_details");
        const successPopup = document.getElementById("successPopup");
        const successIcon = document.getElementById("successIcon");
        const closeSuccess = document.getElementById("closeSuccess");
        const optionalFields = new Set([
            "learner_ext",
            "learner_mname",
            "father_mname",
            "mother_mname",
            "guardian_mname",
            "age",
            "completion_date"
        ]);

        function showValidationPopup(message = "Please complete all required fields before submitting.") {
            if (!validationPopup) return;

            const popupText = validationPopup.querySelector("p");
            if (popupText) {
                popupText.textContent = message;
            }

            validationPopup.classList.add("active");
            if (popupIcon) {
                popupIcon.classList.remove("show-x");
                setTimeout(() => popupIcon.classList.add("show-x"), 700);
            }
        }

        function hideValidationPopup() {
            if (validationPopup) {
                validationPopup.classList.remove("active");
            }
        }

        function showInlineError(input, message) {
            if (!input) return;

            const errorEl = document.createElement("div");
            errorEl.classList.add("error-message");
            errorEl.style.color = "red";
            errorEl.style.fontSize = "12px";
            errorEl.textContent = message;
            input.insertAdjacentElement("afterend", errorEl);
        }

        function setGradeLevelErrorState(hasError) {
            gradeLevelInputs.forEach((radio) => {
                const label = radio.closest("label");
                if (label) label.classList.toggle("radio-error", hasError);
            });
        }

        function hasVisibleField(name) {
            return Array.from(document.querySelectorAll(`[name="${name}"]`)).some((element) => {
                if (element.type === "hidden") {
                    return false;
                }

                return !element.disabled && !element.closest(".form-group-hidden");
            });
        }

        function getFieldValue(name) {
            const elements = Array.from(document.querySelectorAll(`[name="${name}"]`)).filter((element) => {
                if (element.type === "hidden") {
                    return element.value.trim() !== "";
                }

                return !element.disabled && !element.closest(".form-group-hidden");
            });

            if (!elements.length) return "-";

            const first = elements[0];

            if (first.type === "radio") {
                const checked = elements.find((element) => element.checked);
                return checked && checked.value ? checked.value : "-";
            }

            return first.value ? first.value : "-";
        }

        function getSelectText(id, fallbackName) {
            const el = document.getElementById(id);
            if (el && !el.disabled && !el.closest(".form-group-hidden") && el.value) {
                return el.selectedOptions[0]?.text || "-";
            }

            return fallbackName ? getFieldValue(fallbackName) : "-";
        }

        function row(label, value) {
            return `
                <div class="summary-row">
                    <div class="summary-label">${label}:</div>
                    <div class="summary-value">${value}</div>
                </div>
            `;
        }

        function maybeRow(name, fallbackLabel, value = null) {
            if (!hasVisibleField(name)) {
                return "";
            }

            const resolvedValue = value ?? getFieldValue(name);
            return row(getFieldLabel(name, fallbackLabel), resolvedValue);
        }

        function buildSummarySection(title, rows) {
            const content = rows.filter(Boolean).join("");
            if (!content) {
                return "";
            }

            return `
                <div class="summary-section">
                    <h3>${title}</h3>
                    ${content}
                </div>
            `;
        }

        function getMedicationValue() {
            const selected = document.querySelector('input[name="medication"]:checked:not([disabled])');
            if (!selected || !selected.value) {
                return "-";
            }

            return selected.value.charAt(0).toUpperCase() + selected.value.slice(1);
        }

        function buildSummary() {
            if (!summaryContent) return;

            const sections = [
                buildSummarySection("LEARNER INFORMATION", [
                    maybeRow("grade_level", "Grade Level"),
                    maybeRow("learner_fname", "First Name"),
                    maybeRow("learner_mname", "Middle Name"),
                    maybeRow("learner_lname", "Last Name"),
                    maybeRow("learner_ext", "Extension Name"),
                    maybeRow("age", "Age"),
                    maybeRow("sex", "Sex"),
                    maybeRow("dob", "Date of Birth"),
                    maybeRow("email", "Email Address")
                ]),
                buildSummarySection("ADDRESS", [
                    getFieldValue("province") !== "-" ? row(getFieldLabel("province", "Province"), getFieldValue("province")) : "",
                    getFieldValue("municipality") !== "-" ? row(getFieldLabel("municipality", "Municipality"), getFieldValue("municipality")) : "",
                    hasVisibleField("barangay") ? row(getFieldLabel("barangay", "Barangay"), getFieldValue("barangay")) : "",
                    maybeRow("street", "Street")
                ]),
                buildSummarySection("FATHER INFORMATION", [
                    maybeRow("father_fname", "First Name"),
                    maybeRow("father_mname", "Middle Name"),
                    maybeRow("father_lname", "Last Name"),
                    maybeRow("father_occ", "Occupation"),
                    maybeRow("father_contact", "Contact")
                ]),
                buildSummarySection("MOTHER INFORMATION", [
                    maybeRow("mother_fname", "First Name"),
                    maybeRow("mother_mname", "Middle Name"),
                    maybeRow("mother_lname", "Last Name"),
                    maybeRow("mother_occ", "Occupation"),
                    maybeRow("mother_contact", "Contact"),
                    maybeRow("mother_maiden", "Mother Maiden Full Name")
                ]),
                buildSummarySection("GUARDIAN INFORMATION", [
                    maybeRow("guardian_type", "Guardian Type"),
                    maybeRow("guardian_fname", "First Name"),
                    maybeRow("guardian_mname", "Middle Name"),
                    maybeRow("guardian_lname", "Last Name"),
                    maybeRow("guardian_occ", "Occupation"),
                    maybeRow("guardian_contact", "Contact")
                ]),
                buildSummarySection("D. LEARNERS WITH SPECIAL EDUCATION NEEDS", [
                    maybeRow("special_needs", "Special Education Needs"),
                    hasVisibleField("medication") ? row(getFieldLabel("medication", "Takes Medication"), getMedicationValue()) : "",
                    getMedicationValue() === "Yes" ? maybeRow("medication_details", "Medication Details") : ""
                ]),
                buildSummarySection("IN CASE OF EMERGENCY", [
                    maybeRow("emergency1_name", "Contact 1 Name"),
                    maybeRow("emergency1_relationship", "Contact 1 Relationship"),
                    maybeRow("emergency1_contact", "Contact 1 Phone"),
                    maybeRow("emergency2_name", "Contact 2 Name"),
                    maybeRow("emergency2_relationship", "Contact 2 Relationship"),
                    maybeRow("emergency2_contact", "Contact 2 Phone"),
                    maybeRow("emergency3_name", "Contact 3 Name"),
                    maybeRow("emergency3_relationship", "Contact 3 Relationship"),
                    maybeRow("emergency3_contact", "Contact 3 Phone")
                ])
            ];

            summaryContent.innerHTML = sections.filter(Boolean).join("");
        }

        function toggleMedication() {
            if (!medsInput || medsInput.closest(".form-group-hidden")) {
                return;
            }

            if (!medsYes || !medsNo) {
                medsInput.disabled = false;
                medsInput.required = false;
                return;
            }

            if (medsYes.checked) {
                medsInput.disabled = false;
                medsInput.required = true;
            } else {
                medsInput.disabled = true;
                medsInput.required = false;
                medsInput.value = "";
            }
        }

        function resetFormKeepDate() {
            if (!form) return;

            const completionDateInput = document.getElementById("completionDate");
            const completionDateValue = completionDateInput ? completionDateInput.value : "";

            form.reset();

            if (completionDateInput) {
                completionDateInput.value = completionDateValue;
            }

            emergencyBlocks.forEach((block, index) => {
                block.classList.toggle("emergency-contact-hidden", index !== 0);
                setEmergencyBlockEnabled(block, index === 0);
            });
            updateEmergencyAddButton();

            toggleMedication();
            setDefaultGuardianType();
        }

        function showSuccessPopup() {
            if (!successPopup) return;

            successPopup.classList.add("active");

            if (successIcon) {
                successIcon.classList.remove("show-check");
                setTimeout(() => {
                    successIcon.classList.add("show-check");
                }, 600);
            }
        }

        gradeLevelInputs.forEach((radio) => {
            radio.addEventListener("change", () => {
                if (document.querySelector('input[name="grade_level"]:checked:not([disabled])')) {
                    setGradeLevelErrorState(false);
                }
            });
        });

        if (medsYes) {
            medsYes.addEventListener("change", toggleMedication);
        }

        if (medsNo) {
            medsNo.addEventListener("change", toggleMedication);
        }

        toggleMedication();

        if (submitBtn) {
            submitBtn.addEventListener("click", () => {
                let valid = true;

                document.querySelectorAll("input, select, textarea").forEach((el) => {
                    el.classList.remove("input-error");
                    const errorEl = el.nextElementSibling;
                    if (errorEl && errorEl.classList.contains("error-message")) {
                        errorEl.remove();
                    }
                });

                setGradeLevelErrorState(false);

                document.querySelectorAll("input, select, textarea").forEach((el) => {
                    if (
                        el.disabled ||
                        el.type === "hidden" ||
                        el.type === "radio" ||
                        el.readOnly ||
                        optionalFields.has(el.name) ||
                        el.closest(".form-group-hidden")
                    ) {
                        return;
                    }

                    if (!el.value.trim()) {
                        el.classList.add("input-error");
                        showInlineError(el, "This field is required");
                        valid = false;
                    }
                });

                if (gradeLevelInputs.length > 0 && !document.querySelector('input[name="grade_level"]:checked:not([disabled])')) {
                    valid = false;
                    setGradeLevelErrorState(true);
                }

                const emailField = document.querySelector('input[name="email"]:not([disabled])');
                if (emailField && emailField.value && !isValidEmail(emailField.value)) {
                    emailField.classList.add("input-error");
                    showInlineError(emailField, "Enter a valid email format");
                    valid = false;
                }

                [
                    'father_contact',
                    'mother_contact',
                    'guardian_contact',
                    'emergency1_contact',
                    'emergency2_contact',
                    'emergency3_contact'
                ].forEach((name) => {
                    const field = document.querySelector(`input[name="${name}"]:not([disabled])`);
                    if (field && field.value && !isValidContact(field.value)) {
                        field.classList.add("input-error");
                        showInlineError(field, "Must be 11 digits starting with 09");
                        valid = false;
                    }
                });

                if (medsYes && medsYes.checked && medsInput && !medsInput.disabled && !medsInput.value.trim()) {
                    medsInput.classList.add("input-error");
                    showInlineError(medsInput, "Please provide medication details");
                    valid = false;
                }

                if (!valid) {
                    showValidationPopup("Please correct all highlighted fields before continuing.");
                    if (summaryModal) {
                        summaryModal.style.display = "none";
                    }
                    return;
                }

                buildSummary();
                if (summaryModal) {
                    summaryModal.style.display = "flex";
                }
            });
        }

        if (confirmBtn && form) {
            confirmBtn.addEventListener("click", () => {
                window.scrollTo({ top: 0, behavior: "smooth" });

                const formData = new FormData(form);

                fetch("save_enrollment.php", {
                    method: "POST",
                    body: formData
                })
                .then((response) => response.text())
                .then((data) => {
                    if (data.trim() === "success") {
                        if (summaryModal) {
                            summaryModal.style.display = "none";
                        }

                        showSuccessPopup();
                    } else {
                        alert("Error: " + data);
                    }
                })
                .catch(() => {
                    alert("Something went wrong.");
                });
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener("click", () => {
                if (summaryModal) {
                    summaryModal.style.display = "none";
                }
            });
        }

        if (okValidation) {
            okValidation.onclick = hideValidationPopup;
        }

        if (validationPopup) {
            validationPopup.addEventListener("click", (e) => {
                if (e.target === validationPopup) hideValidationPopup();
            });
        }

        if (closeSuccess) {
            closeSuccess.addEventListener("click", () => {
                if (successPopup) {
                    successPopup.classList.remove("active");
                }
                if (successIcon) {
                    successIcon.classList.remove("show-check");
                }

                resetFormKeepDate();
            });
        }

        function setDefaultGuardianType() {
          const radios = Array.from(document.querySelectorAll('input[name="guardian_type"]'))
            .filter((radio) => !radio.disabled);
          if (!radios.length) return;

          const alreadyChecked = radios.some((radio) => radio.checked);
          if (alreadyChecked) return;

          const other = radios.find((radio) => (radio.value || '').toLowerCase() === 'other');

          if (other) {
            other.checked = true;
            other.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }

        // Default guardian type radio to "other" on load.
        document.addEventListener("DOMContentLoaded", function () {
          setDefaultGuardianType();
        });

