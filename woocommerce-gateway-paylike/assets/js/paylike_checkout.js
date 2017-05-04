jQuery(function ($) {
    /**
     * Object to handle Paylike payment forms.
     */
    var wc_paylike_form = {

            /**
             * Initialize e handlers and UI state.
             */
            init: function (form) {
                this.form = form;
                this.paylike_submit = false;

                $(this.form)
                    .on('click', '#place_order', this.onSubmit)

                    // WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
                    .on('submit checkout_place_order_paylike');
            },

            isPaylikeChosen: function () {
                return $('#payment_method_paylike').is(':checked') && ( !$('input[name="wc-paylike-payment-token"]:checked').length || 'new' === $('input[name="wc-paylike-payment-token"]:checked').val() );
            },

            isPaylikeModalNeeded: function (e) {
                var token = wc_paylike_form.form.find('input.paylike_token'),
                    $required_inputs;

                // If this is a stripe submission (after modal) and token exists, allow submit.
                if (wc_paylike_form.paylike_submit && token) {
                    return false;
                }

                // Don't affect submission if modal is not needed.
                if (!wc_paylike_form.isPaylikeChosen()) {
                    return false;
                }

                // Don't open modal if required fields are not complete
                if ($('input#terms').length === 1 && $('input#terms:checked').length === 0) {
                    return false;
                }

                if ($('#createaccount').is(':checked') && $('#account_password').length && $('#account_password').val() === '') {
                    return false;
                }

                // check to see if we need to validate shipping address
                if ($('#ship-to-different-address-checkbox').is(':checked')) {
                    $required_inputs = $('.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required');
                } else {
                    $required_inputs = $('.woocommerce-billing-fields .validate-required');
                }

                if ($required_inputs.length) {
                    var required_error = false;

                    $required_inputs.each(function () {
                        if ($(this).find('input.input-text, select').not($('#account_password, #account_username')).val() === '') {
                            required_error = true;
                        }
                    });

                    if (required_error) {
                        return false;
                    }
                }

                return true;
            },

            block: function () {
                wc_paylike_form.form.block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            },

            unblock: function () {
                wc_paylike_form.form.unblock();
            },

            onClose: function () {
                wc_paylike_form.unblock();
            },

            onSubmit: function (e) {
                if (wc_paylike_form.isPaylikeModalNeeded()) {
                    e.preventDefault();

                    // Capture submit and open paylike modal
                    var $form = wc_paylike_form.form,
                        $paylike_payment = $('#paylike-payment-data'),
                        token = $form.find('input.paylike_token');

                    token.val('');

                    var paylike = Paylike(wc_paylike_params.key);

                    var args = {
                        title: $paylike_payment.data('title'),
                        currency: $paylike_payment.data('currency'),
                        amount: $paylike_payment.data('amount'),
                        locale: $paylike_payment.data('locale'),
                        custom: {
                            products: [wc_paylike_params.products
                            ],
                            name: $("[name='billing_first_name']").val() + ' ' + $("[name='billing_last_name']").val(),
                            telephone: $("[name='billing_phone']").val(),
                            address: $("[name='billing_address_1']").val() + ' ' + $("[name='billing_address_2']").val(),
                            customerIp: wc_paylike_params.customer_IP,
                            platform_version: wc_paylike_params.platform_version,
                            ecommerce: 'WooCommerce',
                            version: wc_paylike_params.version
                        }
                    };

                    // used for cases like trial,
                    // change payment method
                    // see @https://github.com/paylike/sdk#popup-to-save-tokenize-a-card-for-later-use
                    if (args.amount === 0) {
                        delete args.amount;
                        delete args.currency;
                    }

                    paylike.popup(args,
                        function (err, res) {
                            if (err)
                                return console.warn(err);

                            console.log(res);
                            if (res.transaction) {
                                var trxid = res.transaction.id;
                                $form.find('input.paylike_token').remove();
                                $form.append('<input type="hidden" class="paylike_token" name="paylike_token" value="' + trxid + '"/>');
                            } else {
                                var cardid = res.card.id;
                                $form.find('input.paylike_card_id').remove();
                                $form.append('<input type="hidden" class="paylike_card_id" name="paylike_card_id" value="' + cardid + '"/>');
                            }

                            wc_paylike_form.paylike_submit = true;
                            $form.submit();
                        }
                    );


                    return false;
                }

                return true;
            }
        }
        ;

    wc_paylike_form.init($("form.checkout, form#order_review, form#add_payment_method"));
})
;
