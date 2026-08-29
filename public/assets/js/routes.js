const ROUTES = {

    location: {
        states : BASE_URL + 'location/states/',
        cities : BASE_URL + 'location/cities/',
    },


    /*=============================
    =            Admin            =
    =============================*/

    clinic: {
        create   : BASE_URL + 'admin/clinics/create',
        edit  : BASE_URL + 'admin/clinics/clinic-by-id/',
        update : BASE_URL + 'admin/clinics/update', 
    },

    doctor: {
        create   : BASE_URL + 'admin/doctors/create',
        edit  : BASE_URL + 'admin/doctors/doctor-by-id/',
        update : BASE_URL + 'admin/doctors/update',
    },

    doctorSchedule: {
        create   : BASE_URL + 'admin/doctor-schedules/create',
        edit  : BASE_URL + 'admin/doctor-schedules/schedule-by-id/',
        update : BASE_URL + 'admin/doctor-schedules/update',
    },

    doctorLeave: {
        create   : BASE_URL + 'admin/doctor-leaves/create',
        update : BASE_URL + 'admin/doctor-leaves/update',
    },

    doctorFee: {
        create   : BASE_URL + 'admin/doctor-fees/create',
        edit  : BASE_URL + 'admin/doctor-fees/fee-by-id/',
        update : BASE_URL + 'admin/doctor-fees/update',
    },

    doctorCommission: {
        create   : BASE_URL + 'admin/doctor-commissions/create',
        edit  : BASE_URL + 'admin/doctor-commissions/commission-by-id/',
        update : BASE_URL + 'admin/doctor-commissions/update',
    },

    doctorDocument: {
        create   : BASE_URL + 'admin/doctor-documents/create',
        edit  : BASE_URL + 'admin/doctor-documents/document-by-id/',
        update : BASE_URL + 'admin/doctor-documents/update',
    },

    appointments: {
        index       : BASE_URL + 'admin/appointments',
        view        : BASE_URL + 'admin/appointments/view/',
        calendar    : BASE_URL + 'admin/appointments/calendar',
        calendarData: BASE_URL + 'admin/appointments/calendar-data',
        queue       : BASE_URL + 'admin/appointments/queue',
        queueData   : BASE_URL + 'admin/appointments/queue-data',
        checkIn     : BASE_URL + 'admin/appointments/check-in/',
        queueMove   : BASE_URL + 'admin/appointments/queue-move/',
        queueStatus : BASE_URL + 'admin/appointments/queue-status/',
        reports     : BASE_URL + 'admin/appointments/reports',
        reportsData : BASE_URL + 'admin/appointments/reports-data',
        edit        : BASE_URL + 'admin/appointments/edit/',
        save        : BASE_URL + 'admin/appointments/save',
        update      : BASE_URL + 'admin/appointments/update/',
        delete      : BASE_URL + 'admin/appointments/delete/',
    },

    consultations: {
        index  : BASE_URL + 'admin/consultations',
        edit   : BASE_URL + 'admin/consultations/edit/',
        save   : BASE_URL + 'admin/consultations/save',
        update : BASE_URL + 'admin/consultations/update/',
        delete : BASE_URL + 'admin/consultations/delete/',
    },

    prescriptions: {
        index  : BASE_URL + 'admin/prescriptions',
        edit   : BASE_URL + 'admin/prescriptions/edit/',
        save   : BASE_URL + 'admin/prescriptions/save',
        update : BASE_URL + 'admin/prescriptions/update/',
        delete : BASE_URL + 'admin/prescriptions/delete/',
    },

    medicines: {
        index  : BASE_URL + 'admin/medicines',
        search : BASE_URL + 'admin/medicines/search',
        edit   : BASE_URL + 'admin/medicines/edit/',
        save   : BASE_URL + 'admin/medicines/save',
        update : BASE_URL + 'admin/medicines/update/',
        delete : BASE_URL + 'admin/medicines/delete/',
    },

    patients: {
        index  : BASE_URL + 'admin/patients',
        edit   : BASE_URL + 'admin/patients/edit/',
        save   : BASE_URL + 'admin/patients/save',
        update : BASE_URL + 'admin/patients/update/',
        registration: {
            index : BASE_URL + 'admin/patient-registration',
            register: BASE_URL + 'admin/patient-registration/register',
        },
        medicalHistory: {
            index : BASE_URL + 'admin/patients/',
            save  : BASE_URL + 'admin/patients/',
            edit  : BASE_URL + 'admin/patients/',
            update: BASE_URL + 'admin/patients/',
        },
        documents: {
            index   : BASE_URL + 'admin/patients/',
            save    : BASE_URL + 'admin/patients/',
            edit    : BASE_URL + 'admin/patients/',
            update  : BASE_URL + 'admin/patients/',
            download: BASE_URL + 'admin/patients/',
            delete  : BASE_URL + 'admin/patients/',
        },
    }

    
    
    /*=====  End of Admin  ======*/
    
    
   

};
