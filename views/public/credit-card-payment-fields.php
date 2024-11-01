<script type="application/javascript">
    jQuery(function( $) {
        var anonymizeCC = {
            init: function(form) {
                <?php echo "this.API_ENDPOINT = '" . SpryngUtil::get_instance()->getApiEndpoint() ."';\n"; ?>
                this.CARD_URI = "/card";
                this.GATEWAY_IDENTIFIER = '<?php echo $this->id; ?>';
                this.CC_FORM_ID = '<?php echo $this->id; ?>';
                this.ORGANISATION = "<?php echo $this->get_option('organisation') ?>";
                this.ACCOUNT = "<?php echo $this->get_option('account') ?>";

                // Remove bancontact cvc from default WooCommerce cc form
                $("#spryng_payments_wc_bancontact_gateway-card-cvc").remove();
                $("label[for='spryng_payments_wc_bancontact_gateway-card-cvc']").first().remove();

                this.form = form;
                $(this.form)
                    .on('click', '#place_order', this.onSubmit)
                    .on('submit checkout_place_order_' + this.GATEWAY_IDENTIFIER)
            },
            collectCCData: function()
            {
                var expiry = $('#' + anonymizeCC.CC_FORM_ID + '-card-expiry').val().replace(/\s/g, '').split('/');

                // If user enters full year, like 2020, only use the last two characters (20 in this case)
                if (expiry[1].length === 4 && expiry[1].slice(0, 2) === "20") {
                    expiry[1] = expiry[1].slice(2)
                }

                var cc =
                {
                    account:   this.ACCOUNT,
                    organisation:   this.ORGANISATION,
                    card_number:    $('#' + anonymizeCC.CC_FORM_ID + '-card-number').val().replace(/\s/g, ''),
                    expiry_month:   expiry[0],
                    expiry_year:    expiry[1],
                    cvv:            $('#' + anonymizeCC.CC_FORM_ID + '-card-cvc').val()
                };

                return cc;
            },
            onSubmit: function ( e )
            {
                if ($('input[name="payment_method"]:checked').val() === anonymizeCC.GATEWAY_IDENTIFIER)
                {
                    e.preventDefault();

                    var $form = anonymizeCC.form,
                        $data = anonymizeCC.collectCCData();

                    var ccCallback = function( res )
                    {
                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-number').attr('disabled', 'disabled');
                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-expiry').attr('disabled', 'disabled');
                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-cvc').attr('disabled', 'disabled');

                        if ($('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-token').length < 1)
                        {
                            $form.append( '<input id="' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-token" type="hidden" name="' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-token" value="' + res._id + '" />' )
                        }

                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-number').val('');
                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-expiry').val('');
                        $('#' + anonymizeCC.GATEWAY_IDENTIFIER + '-card-cvc').val('');

                        $form.submit();
                    };

                    $.ajax({
                        url: anonymizeCC.API_ENDPOINT + anonymizeCC.CARD_URI,
                        method: "POST",
                        data: $data
                    }).always(function(data, statusText, xhr) {
                        console.log("The response code is " + xhr.status);
                        switch(xhr.status) {
                            case 200:
                                ccCallback(data);
                                break;
                            case 400:
                                /**
                                 * Response code 400 means that the request is malformed. This usually occurs when the
                                 * customer has entered incorrect card data. In this case, the response from the API is
                                 * validated and the customer sees a message explaining why the request went wrong.
                                 */
                                var errMsg;
                                for (var requestErr in data.details) {
                                    switch(requestErr) {
                                        case "body.card_number":
                                            errMsg += "- It seems like the card number you provided is not correct. Please review the number you entered and try again.\n";
                                            break;
                                        case "body.expiry_month":
                                            errMsg += "- The expiry month you provided for your card does not seem to be correct.\n";
                                            break;
                                        case "body.expiry_year":
                                            errMsg += "- The expiry year you provided for your card does not seem to be correct.\n";
                                            break;
                                        case "body.cvv":
                                            errMsg += "- There seems to be something wrong with the CVV you provided for your card.\n";
                                            break;
                                        default:
                                            errMsg += "- An unknown validation error occurred while trying to charge your card. Please validate the data you entered and try again."
                                            break;
                                    }
                                }
                                $('div.payment_method_spryng_payments_creditcard').prepend('<div class="woocommerce-error">' +
                                    errMsg + '</div>');
                                break;
                            case 500:
                            default:
                                $('div.payment_method_spryng_payments_creditcard').prepend('<div class="woocommerce-error">' +
                                    'Something went wrong. Please try again. You will not be charged twice.</div>');
                                break;
                        }
                    });

                    return false;
                }
            }
        };

        $(document).ready(function()
        {
            anonymizeCC.init($("form.checkout"));
        });
    });
</script>