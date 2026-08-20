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
    }

    
    
    /*=====  End of Admin  ======*/
    
    
   

};