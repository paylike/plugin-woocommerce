jQuery( function( $ ) {
	/**
	 * Object to handle Paylike payment forms.
	 */
	var wc_paylike_form = {

			/**
			 * Initialize e handlers and UI state.
			 */
			init: function( form ) {
				this.form = form;
				this.paylike_submit = false;

				$( this.form )
					.on( 'click', '#place_order', this.onSubmit )

					// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
					.on( 'submit checkout_place_order_paylike' );
			},

			isPaylikeChosen: function() {
				return $( '#payment_method_paylike' ).is( ':checked' );
			},

			isAddPaymentMethod: function() {
				return $( '#add_payment_method' ).length === 1;
			},

			isCardSavedForFuturePurchases: function() {
				return $( '#wc-paylike-new-payment-method' ).is( ':checked' );
			},

			isManualPaymentMethod: function() {
				return $( 'body.woocommerce-order-pay' ).length === 1;
			},

			isPaylikeModalNeeded: function() {

				// Don't affect submission if modal is not needed.
				if ( ! wc_paylike_form.isPaylikeChosen() ) {
					return false;
				}

				var token = wc_paylike_form.form.find( 'input.paylike_token' ).length,
					savedToken = wc_paylike_form.form.find( 'input#wc-paylike-payment-token-new' ).length,
					card = wc_paylike_form.form.find( 'input.paylike_card_id' ).length,
					$required_inputs;

				// token is used
				if ( savedToken ) {
					if ( ! wc_paylike_form.form.find( 'input#wc-paylike-payment-token-new' ).is( ':checked' ) )
						if ( $( '.wc-saved-payment-methods' ).length > 0 ) {
							if ( $( '.wc-saved-payment-methods' ).data( 'count' ) > 0 ) {
								if ( wc_paylike_form.form.find( 'input[name="wc-paylike-payment-token"]:checked' ).length > 0 )
									return false;
							}
						}
				}

				// If this is a paylike submission (after modal) and token exists, allow submit.
				if ( wc_paylike_form.paylike_submit && token ) {
					if ( wc_paylike_form.form.find( 'input.paylike_token' ).val() !== '' )
						return false;
				}

				// If this is a paylike submission (after modal) and card exists, allow submit.
				if ( wc_paylike_form.paylike_submit && card ) {
					if ( wc_paylike_form.form.find( 'input.paylike_card_id' ).val() !== '' )
						return false;
				}

				// Don't open modal if required fields are not complete
				if ( $( 'input#legal' ).length === 1 && $( 'input#legal:checked' ).length === 0 ) {
					return false;
				}

				if ( $( 'input#data-download' ).length === 1 && $( 'input#data-download:checked' ).length === 0 ) {
					return false;
				}

				if ( $( 'input#terms' ).length === 1 && $( 'input#terms:checked' ).length === 0 ) {
					return false;
				}


				if ( ! wc_paylike_form.validateShipmondo() ) return false;

				return true;
			},

			block: function() {
				wc_paylike_form.form.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			},

			unblock: function() {
				wc_paylike_form.form.unblock();
			},
			logTransactionResponsePopup: function( err, res ) {
				$.ajax( {
					type: "POST",
					dataType: "json",
					url: wc_paylike_params.ajax_url,
					data: {
						action: 'paylike_log_transaction_data',
						err: err,
						res: res
					}
				} );
			},
			getName: function( $paylike_payment ) {
				var $name = $( "[name='billing_first_name']" );
				var name = '';
				if ( $name.length > 0 ) {
					name = $name.val() + ' ' + $( "[name='billing_last_name']" ).val();
				} else {
					name = $paylike_payment.data( 'name' );
				}
				return wc_paylike_form.escapeQoutes( name );
			},
			getAddress: function( $paylike_payment ) {
				var $address = $( "[name='billing_address_1']" );
				var address = '';
				if ( $address.length > 0 ) {
					address = $address.val()
					var $address_2 = $( "[name='billing_address_2']" );
					if ( $address_2.length > 0 ) {
						address += ' ' + $address_2.val();
					}
					var $billing_city = $( "[name='billing_city']" );
					if ( $billing_city.length > 0 ) {
						address += ' ' + $billing_city.val();
					}
					var $billing_state = $( "[name='billing_state']" );
					if ( $billing_state.length > 0 ) {
						address += ' ' + $billing_state.find( ':selected' ).text();
					}
					var $billing_postcode = $( "[name='billing_postcode']" )
					if ( $billing_postcode.length > 0 ) {
						address += ' ' + $billing_postcode.val();
					}
				} else {
					address = $paylike_payment.data( 'address' );
				}
				return wc_paylike_form.escapeQoutes( address );
			},
			getPhoneNo: function( $paylike_payment ) {

				var $phone = $( "[name='billing_phone']" );
				var phone = '';
				if ( $phone.length > 0 ) {
					phone = $phone.val()
				} else {
					phone = $paylike_payment.data( 'phone' );
				}
				return wc_paylike_form.escapeQoutes( phone );
			},
			onSubmit: function( e ) {

				// Don't affect submission if modal is not needed.
				if ( ! wc_paylike_form.isPaylikeModalNeeded() ) {
					return true;
				}
				// on add card page skip validation
				if ( wc_paylike_form.isAddPaymentMethod() || wc_paylike_form.isManualPaymentMethod() ) {
					return wc_paylike_form.showPopup( e );
				}

				// Get checkout form data
				var formData = wc_paylike_form.form.serializeArray();

				// Modify form to make sure its just a validation check
				formData.push( { name: "woocommerce_checkout_update_totals", value: true } );

				// Show loading indicator
				wc_paylike_form.form.addClass( 'processing' );
				wc_paylike_form.block();

				// Make request to validate checkout form
				$.ajax( {
					type: 'POST',
					url: wc_checkout_params.checkout_url,
					data: $.param( formData ),
					dataType: 'json',
					success: function( result ) {
						if ( result.messages ) {
							wc_paylike_form.submit_error( result.messages );
							return false;
						} else {
							wc_paylike_form.form.removeClass( 'processing' ).unblock();
							wc_paylike_form.showPopup( e );
							return true;
						}
					},
					error: function( jqXHR, textStatus, errorThrown ) {
						wc_paylike_form.submit_error( '<div class="woocommerce-error">' + errorThrown + '</div>' );
					}
				} );

				return false;
			},
			showPopup: function( e ) {
				if ( wc_paylike_form.isPaylikeModalNeeded() ) {
					e.preventDefault();

					// Capture submit and open paylike modal
					var $form = wc_paylike_form.form,
						$paylike_payment = $( '#paylike-payment-data' ),
						token = $form.find( 'input.paylike_token' );

					token.val( '' );

					var name = wc_paylike_form.getName( $paylike_payment );
					var phoneNo = wc_paylike_form.getPhoneNo( $paylike_payment );
					var address = wc_paylike_form.getAddress( $paylike_payment );
					var paylike = Paylike( { key: wc_paylike_params.key } );
					var $billing_email = $( "[name='billing_email']" );
					var args = {
						title: $paylike_payment.data( 'title' ),
						test: !! $paylike_payment.data( 'test' ),
						amount: {
							currency: $paylike_payment.data( 'currency' ),
							exponent: $paylike_payment.data( 'decimals' ),
							value: $paylike_payment.data( 'amount' ),
						},
						locale: $paylike_payment.data( 'locale' ),
						custom: {
							email: $billing_email.val(),
							orderId: $paylike_payment.data( 'order_id' ),
							products: [ wc_paylike_params.products
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

					if ( wc_paylike_params.plan_arguments ) {
						for ( var attrname in wc_paylike_params.plan_arguments ) {
							args[ attrname ] = wc_paylike_params.plan_arguments[ attrname ];
						}
						if(args.plan && args.plan.repeat && args.plan.repeat.first){
							args.plan.repeat.first = new Date(args.plan.repeat.first);
						}
						if(args.plan) {
							args.plan = [ args.plan ];
						}
					}
					// used for cases like trial,
					// change payment method
					// see @https://github.com/paylike/sdk#popup-to-save-tokenize-a-card-for-later-use
					if ( args.amount.value === 0 ) {
						delete args.amount;
					}


					// if card is reused mark unplanned for customer but also for merchant since merchant will be able to reuse for subscriptions
					if ( this.isCardSavedForFuturePurchases() || this.isAddPaymentMethod() ) {
						args[ 'unplanned' ] = {
							customer: true,
							merchant: true
						}
					}
					console.log(args);

					paylike.pay( args,
						function( err, res ) {
							// log this for debugging purposes
							wc_paylike_form.logTransactionResponsePopup( err, res );
							if ( err ) {
								return err
							}

							if ( res.transaction ) {
								var trxid = res.transaction.id;
								$form.find( 'input.paylike_token' ).remove();
								$paylike_payment.append( '<input type="hidden" class="paylike_token" name="paylike_token" value="' + trxid + '"/>' );
							} else {
								var cardid = res.card.id;
								$form.find( 'input.paylike_card_id' ).remove();
								$form.append( '<input type="hidden" class="paylike_card_id" name="paylike_card_id" value="' + cardid + '"/>' );
							}

							wc_paylike_form.paylike_submit = true;
							$form.submit();
						}
					);

					return false;
				}
			},
			escapeQoutes: function( str ) {
				return str.toString().replace( /"/g, '\\"' );
			},

			validateShipmondo: function() {
				var selectedShipping = $( '#shipping_method input:checked' ).val();
				if ( ! selectedShipping ) {
					return true;
				}
				// Check if Shipmondo (Pakkelabels.dk) shipping option is selected
				if ( selectedShipping.indexOf( "pakkelabels" ) >= 0 ) {
					// Business shipping, but no business name
					var shipmondoBusinessTypes = [
						"pakkelabels_shipping_gls_business",
						"pakkelabels_shipping_postnord_business",
						"pakkelabels_shipping_bring_business"
					];

					if ( shipmondoBusinessTypes.includes( $( '#shipping_method input:checked' ).val() ) && $( "#billing_company" ).val() == '' ) {
						return false;
					}

					// Pickup point shipping, but no pickup point selected
					var shipmondoPickupPointTypes = [
						"pakkelabels_shipping_gls",
						"pakkelabels_shipping_pdk",
						"pakkelabels_shipping_dao",
						"pakkelabels_shipping_bring"
					];

					// Check if pickup point shipping is selected
					if ( shipmondoPickupPointTypes.includes( $( '#shipping_method input:checked' ).val() ) ) {
						// Check if a shopID exists
						if ( $( "#hidden_chosen_shop input[name='shop_ID']" ).val() == '' ) {
							return false;
						}
					}
				}
				return true;

			},
			submit_error:

				function( error_message ) {
					$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
					wc_paylike_form.form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
					wc_paylike_form.form.removeClass( 'processing' ).unblock();
					wc_paylike_form.form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
					wc_paylike_form.scroll_to_notices();
					$( document.body ).trigger( 'checkout_error', [ error_message ] );
				}

			,
			scroll_to_notices: function() {
				var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );
				if ( ! scrollElement.length ) {
					scrollElement = $( '.form.checkout' );
				}
				$.scroll_to_notices( scrollElement );
			}
			,
		}
	;

	wc_paylike_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} )
;
