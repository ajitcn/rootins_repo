const BASE_URL = $('meta[name="base-url"]').attr('content');

$.ajaxSetup({
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
});

 