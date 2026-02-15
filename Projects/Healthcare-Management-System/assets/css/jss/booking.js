/* assets/js/booking.js - 81 İL UYUMLU */

document.addEventListener('DOMContentLoaded', function() {
    const citySelect = document.getElementById('city');
    const districtSelect = document.getElementById('district');
    const hospitalSelect = document.getElementById('hospital');
    const departmentSelect = document.getElementById('department');
    const doctorSelect = document.getElementById('doctor_id');
    const doctorHelp = document.getElementById('doctor_help');

    // PHP'den gelen doktor verisi
    // (appointments.php içinde window.DOCTOR_DATA tanımlı olmalı)
    const ALL_DOCTORS = window.DOCTOR_DATA || [];
    
    // Harici dosyadan gelen şehir verisi (turkey_data.js)
    const LOCATIONS = window.TURKEY_DATA || {};

    // 1. Sayfa açılınca ŞEHİR listesini doldur
    function initCities() {
        citySelect.innerHTML = '<option value="">-- Select City --</option>';
        const cities = Object.keys(LOCATIONS).sort();
        cities.forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
    }

    // 2. Şehir değişince -> İLÇELERİ doldur
    function populateDistricts() {
        const city = citySelect.value;
        districtSelect.innerHTML = '<option value="">-- Select District --</option>';
        hospitalSelect.innerHTML = '<option value="">-- Select Hospital --</option>';
        
        districtSelect.disabled = true;
        hospitalSelect.disabled = true;
        
        // Doktor seçimi de sıfırlanmalı
        doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
        doctorSelect.disabled = true;

        if (city && LOCATIONS[city]) {
            const districts = Object.keys(LOCATIONS[city]).sort();
            districts.forEach(dist => {
                const opt = document.createElement('option');
                opt.value = dist;
                opt.textContent = dist;
                districtSelect.appendChild(opt);
            });
            districtSelect.disabled = false;
        }
    }

    // 3. İlçe değişince -> HASTANELERİ doldur
    function populateHospitals() {
        const city = citySelect.value;
        const dist = districtSelect.value;
        
        hospitalSelect.innerHTML = '<option value="">-- Select Hospital --</option>';
        hospitalSelect.disabled = true;
        
        // Doktor seçimi sıfırlanmalı
        doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
        doctorSelect.disabled = true;

        if (city && dist && LOCATIONS[city][dist]) {
            const hospitals = LOCATIONS[city][dist];
            hospitals.forEach(hosp => {
                const opt = document.createElement('option');
                opt.value = hosp;
                opt.textContent = hosp;
                hospitalSelect.appendChild(opt);
            });
            hospitalSelect.disabled = false;
        }
    }

    // 4. Hastane veya Bölüm değişince -> DOKTORLARI filtrele
    function populateDoctors() {
        const hosp = hospitalSelect.value;
        const dept = departmentSelect.value;
        
        doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
        doctorSelect.disabled = true;
        
        if (!hosp || !dept) {
            doctorHelp.textContent = 'Please select hospital and department.';
            return;
        }

        // Veritabanındaki doktorlar arasında, seçilen hastane ve bölümde çalışan var mı?
        const matchedDocs = ALL_DOCTORS.filter(d => 
            d.hospital_name === hosp && d.department === dept
        );

        if (matchedDocs.length > 0) {
            matchedDocs.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = "Dr. " + d.name;
                doctorSelect.appendChild(opt);
            });
            doctorSelect.disabled = false;
            doctorHelp.textContent = matchedDocs.length + ' doctor(s) available.';
        } else {
            doctorHelp.textContent = 'No doctors found in this department.';
        }
    }

    // Olay Dinleyicileri
    initCities(); // Başlangıçta şehirleri yükle
    citySelect.addEventListener('change', populateDistricts);
    districtSelect.addEventListener('change', populateHospitals);
    hospitalSelect.addEventListener('change', populateDoctors);
    departmentSelect.addEventListener('change', populateDoctors);
});