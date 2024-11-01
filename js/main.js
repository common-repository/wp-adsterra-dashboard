jQuery(document).ready(function ($) {

    jQuery('#adsterra_dashboard_widget_filter_month').on('change', function () {

        var filterMonth = $(this).val();
        
        jQuery.ajax({
            type: "POST",
            url: adsterra_ajax_url,
            data: {
                action: "adsterra_update_month_filter",
                filter_month: filterMonth
            },
            success: function (risp) {
                window.location.reload();
            },
            error: function (errorThrown) {
                console.log(errorThrown);
            }

        });

    });
});