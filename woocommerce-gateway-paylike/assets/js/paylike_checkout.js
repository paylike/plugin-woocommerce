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
                return $('#payment_method_paylike').is(':checked');
            },

            isPaylikeModalNeeded: function () {
                var token = wc_paylike_form.form.find('input.paylike_token').length,
                    card = wc_paylike_form.form.find('input.paylike_card_id').length,
                    $required_inputs;

                // If this is a paylike submission (after modal) and token exists, allow submit.
                if (wc_paylike_form.paylike_submit && token) {
                    if (wc_paylike_form.form.find('input.paylike_token').val() !== '')
                        return false;
                }

                // If this is a paylike submission (after modal) and card exists, allow submit.
                if (wc_paylike_form.paylike_submit && card) {
                    if (wc_paylike_form.form.find('input.paylike_card_id').val() !== '')
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
                var $account_password = $('#account_password');
                if ($('#createaccount').is(':checked') && $account_password.length && $account_password.val() === '') {
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
            logTransactionResponsePopup: function (err, res) {
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: wc_paylike_params.ajax_url,
                    data: {
                        action: 'paylike_log_transaction_data',
                        err: err,
                        res: res
                    }
                });
            },
            getName: function ($paylike_payment) {
                var $name = $("[name='billing_first_name']");
                var name = '';
                if ($name.length > 0) {
                    name = $name.val() + ' ' + $("[name='billing_last_name']").val();
                } else {
                    name = $paylike_payment.data('name');
                }
                return wc_paylike_form.escapeQoutes(name);
            },
            getAddress: function ($paylike_payment) {
                var $address = $("[name='billing_address_1']");
                var address = '';
                if ($address.length > 0) {
                    address = $address.val() + ' ' + $("[name='billing_address_2']").val();
                } else {
                    address = $paylike_payment.data('address');
                }
                return wc_paylike_form.escapeQoutes(address);
            },
            getPhoneNo: function ($paylike_payment) {

                var $phone = $("[name='billing_phone']");
                var phone = '';
                if ($phone.length > 0) {
                    phone = $phone.val()
                } else {
                    phone = $paylike_payment.data('phone');
                }
                return wc_paylike_form.escapeQoutes(phone);
            },
            onSubmit: function (e) {
                if (wc_paylike_form.isPaylikeModalNeeded()) {
                    e.preventDefault();

                    // Capture submit and open paylike modal
                    var $form = wc_paylike_form.form,
                        $paylike_payment = $('#paylike-payment-data'),
                        token = $form.find('input.paylike_token');

                    token.val('');

                    var name = wc_paylike_form.getName($paylike_payment);
                    var phoneNo = wc_paylike_form.getPhoneNo($paylike_payment);
                    var address = wc_paylike_form.getAddress($paylike_payment);
                    var paylike = Paylike(wc_paylike_params.key);
                    var $billing_email = $("[name='billing_email']");
                    var args = {
                        title: $paylike_payment.data('title'),
                        currency: $paylike_payment.data('currency'),
                        amount: $paylike_payment.data('amount'),
                        locale: $paylike_payment.data('locale'),
                        custom: {
                            email: $billing_email.val(),
                            orderId: $paylike_payment.data('order_id'),
                            products: [wc_paylike_params.products
                            ],
                            customer: {
                                name: name,
                                email: $billing_email.val(),
                                phoneNo: phoneNo,
                                address: address,
                                IP: wc_paylike_params.customer_IP
                            },
                            platform: {
                                name: 'WordPress',
                                version: wc_paylike_params.platform_version
                            },
                            ecommerce: {
                                name: 'WooCommerce',
                                version: wc_paylike_params.ecommerce_version
                            },
                            paylikePluginVersion: wc_paylike_params.version
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
                            // log this for debugging purposes
                            wc_paylike_form.logTransactionResponsePopup(err, res);
                            if (err) {
                                return err
                            }

                            console.log(res);
                            if (res.transaction) {
                                var trxid = res.transaction.id;
                                $form.find('input.paylike_token').remove();
                                $paylike_payment.append('<input type="hidden" class="paylike_token" name="paylike_token" value="' + trxid + '"/>');
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
            },
            escapeQoutes(str) {
                return str.replace(/"/g, '\\"');
            }
        }
    ;

    wc_paylike_form.init($("form.checkout, form#order_review, form#add_payment_method"));
})
;
