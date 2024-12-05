jQuery(document).ready(function ($) {
    $(document).on('wc_checkout_before_submit', function (e) {
        let emailField = $('#billing_email').val();
        let fullNameField = $('#billing_full_name').val();
        let firstNameField = $('#billing_first_name').val();
        let lastNameField = $('#billing_last_name').val();

        let data = {
            action: 'validate_spam_email',
            email: emailField,
            full_name: fullNameField,
            first_name: firstNameField,
            last_name: lastNameField,
        };

        let isBlocked = false;

        // AJAX call to validate email
        $.ajax({
            url: spamBlockConfig.ajax_url,
            type: 'POST',
            data: data,
            async: false,
            success: function (response) {
                if (!response.success) {
                    alert(spamBlockConfig.error_message);
                    isBlocked = true;
                }
            },
        });

        if (isBlocked) {
            e.preventDefault();
        }
    });
});
