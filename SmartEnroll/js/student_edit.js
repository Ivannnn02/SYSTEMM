const dobInput = document.querySelector('input[name="dob"]');
const ageInput = document.querySelector('input[name="age"]');
const completionDateInput = document.querySelector('input[name="completion_date"]');
const schoolYearInput = document.querySelector('input[name="school_year"]');

function calculateAge(dobValue) {
  if (!dobValue) return '';
  const dob = new Date(dobValue);
  if (Number.isNaN(dob.getTime())) return '';
  const today = new Date();
  let age = today.getFullYear() - dob.getFullYear();
  const m = today.getMonth() - dob.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
    age--;
  }
  return age >= 0 ? age : '';
}

if (dobInput && ageInput) {
  const initialAge = calculateAge(dobInput.value);
  if (initialAge !== '') {
    ageInput.value = initialAge;
  }
  dobInput.addEventListener('change', () => {
    const age = calculateAge(dobInput.value);
    ageInput.value = age !== '' ? age : '';
  });
}

function calculateSchoolYear(dateValue) {
  if (!dateValue) return '';
  const completionDate = new Date(dateValue);
  if (Number.isNaN(completionDate.getTime())) return '';
  const month = completionDate.getMonth() + 1;
  const year = completionDate.getFullYear();
  const startYear = month >= 6 ? year : year - 1;
  return `${startYear}-${startYear + 1}`;
}

if (completionDateInput && schoolYearInput) {
  const initialSchoolYear = calculateSchoolYear(completionDateInput.value);
  if (initialSchoolYear) {
    schoolYearInput.value = initialSchoolYear;
  }
  completionDateInput.addEventListener('change', () => {
    const schoolYear = calculateSchoolYear(completionDateInput.value);
    schoolYearInput.value = schoolYear || '';
  });
}

const guardianType = document.querySelector('select[name="guardian_type"]');
const medicationSelect = document.querySelector('select[name="medication"]');
const medicationDetailsInput = document.querySelector('[name="medication_details"]');
const guardianMap = {
  mother: {
    guardian_lname: document.querySelector('input[name="mother_lname"]'),
    guardian_fname: document.querySelector('input[name="mother_fname"]'),
    guardian_mname: document.querySelector('input[name="mother_mname"]'),
    guardian_occ: document.querySelector('input[name="mother_occ"]'),
    guardian_contact: document.querySelector('input[name="mother_contact"]')
  },
  father: {
    guardian_lname: document.querySelector('input[name="father_lname"]'),
    guardian_fname: document.querySelector('input[name="father_fname"]'),
    guardian_mname: document.querySelector('input[name="father_mname"]'),
    guardian_occ: document.querySelector('input[name="father_occ"]'),
    guardian_contact: document.querySelector('input[name="father_contact"]')
  }
};

function setGuardianFrom(sourceKey) {
  const source = guardianMap[sourceKey];
  if (!source) return;
  Object.keys(source).forEach((targetKey) => {
    const target = document.querySelector(`input[name="${targetKey}"]`);
    const srcInput = source[targetKey];
    if (target && srcInput) {
      target.value = srcInput.value || '';
    }
  });
}

function setGuardianReadOnly(readOnly) {
  document.querySelectorAll('[data-guardian-field]').forEach((field) => {
    field.readOnly = readOnly;
  });
}

if (guardianType) {
  if (guardianType.value === 'mother' || guardianType.value === 'father') {
    setGuardianFrom(guardianType.value);
    setGuardianReadOnly(true);
  } else {
    setGuardianReadOnly(false);
  }

  guardianType.addEventListener('change', () => {
    if (guardianType.value === 'mother' || guardianType.value === 'father') {
      setGuardianFrom(guardianType.value);
      setGuardianReadOnly(true);
    } else {
      setGuardianReadOnly(false);
    }
  });
}

function syncMedicationField() {
  if (!medicationSelect || !medicationDetailsInput) return;

  const enabled = medicationSelect.value === 'yes';
  medicationDetailsInput.disabled = !enabled;

  if (!enabled) {
    medicationDetailsInput.value = '';
  }
}

if (medicationSelect && medicationDetailsInput) {
  syncMedicationField();
  medicationSelect.addEventListener('change', syncMedicationField);
}

const closeSuccess = document.getElementById('closeSuccess');
const successPopup = document.getElementById('successPopup');
const successIcon = document.getElementById('successIcon');
if (successPopup && successIcon) {
  successPopup.classList.add('active');
  successIcon.classList.remove('show-check');
  setTimeout(() => {
    successIcon.classList.add('show-check');
  }, 600);
}
if (closeSuccess && successPopup) {
  closeSuccess.addEventListener('click', () => {
    successPopup.remove();
    const url = new URL(window.location.href);
    if (url.searchParams.has('saved')) {
      url.searchParams.delete('saved');
      window.history.replaceState({}, '', url.toString());
    }
  });
}
