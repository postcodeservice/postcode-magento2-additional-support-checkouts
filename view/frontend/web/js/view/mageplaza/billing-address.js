define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/billing-address',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Mageplaza_Osc/js/model/osc-data',
    'Magento_Checkout/js/action/create-billing-address',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/action/set-billing-address',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/checkout-data-resolver',
    'Mageplaza_Osc/js/model/address/auto-complete',
    'Mageplaza_Osc/js/model/compatible/amazon-pay',
    'uiRegistry',
    'mage/translate',
    'rjsResolver'
], function($, ko, Component, quote, checkoutData, oscData, createBillingAddress, selectBillingAddress, customer, setBillingAddressAction, addressConverter, additionalValidators, globalMessageList, checkoutDataResolver, addressAutoComplete, amazonPay, registry, $t, resolver) {
    'use strict';

    var observedElements = []
        , canShowBillingAddress = window.checkoutConfig.oscConfig.showBillingAddress;
    return Component.extend({
        defaults: {
            template: ''
        },
        isCustomerLoggedIn: customer.isLoggedIn,
        isAmazonAccountLoggedIn: amazonPay.isAmazonAccountLoggedIn,
        quoteIsVirtual: quote.isVirtual(),
        canUseShippingAddress: ko.computed(function() {
            return !quote.isVirtual() && quote.shippingAddress() && quote.shippingAddress().canUseForBilling() && canShowBillingAddress;
        }),
        initialize: function() {
            var self = this;
            this._super();
            this.initFields();
            additionalValidators.registerValidator(this);
            registry.async('checkoutProvider')(function(checkoutProvider) {
                var billingAddressData = checkoutData.getBillingAddressFromData();
                if (billingAddressData) {
                    checkoutProvider.set('billingAddress', $.extend({}, checkoutProvider.get('billingAddress'), billingAddressData));
                }
                checkoutProvider.on('billingAddress', function(billingAddressData) {
                    checkoutData.setBillingAddressFromData(billingAddressData);
                });
            });
            quote.shippingAddress.subscribe(function(newAddress) {
                if (self.isAddressSameAsShipping()) {
                    selectBillingAddress(newAddress);
                }
            });
            resolver(this.afterResolveDocument.bind(this));
            return this;
        },
        afterResolveDocument: function() {
            this.saveBillingAddress();
            addressAutoComplete.register('billing');
        },
        useShippingAddress: function() {
            if (this.isAddressSameAsShipping()) {
                selectBillingAddress(quote.shippingAddress());
                checkoutData.setSelectedBillingAddress(null);
                if (window.checkoutConfig.reloadOnBillingAddress) {
                    setBillingAddressAction(globalMessageList);
                }
            } else {
                this.updateAddress();
            }
            return true;
        },
        onAddressChange: function(address) {
            this._super(address);
            if (!this.isAddressSameAsShipping() && canShowBillingAddress) {
                this.updateAddress();
            }
        },
        updateAddress: function() {
            if (this.selectedAddress() && !this.isAddressFormVisible()) {
                newBillingAddress = createBillingAddress(this.selectedAddress());
                selectBillingAddress(newBillingAddress);
                checkoutData.setSelectedBillingAddress(this.selectedAddress().getKey());
            } else {
                var addressData = this.source.get('billingAddress'), newBillingAddress;
                if (customer.isLoggedIn() && !this.customerHasAddresses) {
                    this.saveInAddressBook(1);
                }
                addressData.save_in_address_book = this.saveInAddressBook() ? 1 : 0;
                newBillingAddress = createBillingAddress(addressData);
                selectBillingAddress(newBillingAddress);
                checkoutData.setSelectedBillingAddress(newBillingAddress.getKey());
                checkoutData.setNewCustomerBillingAddress(addressData);
            }
            // if (window.checkoutConfig.reloadOnBillingAddress) {
            setBillingAddressAction(globalMessageList);
            // }
        },
        initFields: function() {
            var self = this
                , addressFields = window.checkoutConfig.oscConfig.addressFields
                , fieldsetName = 'checkout.steps.shipping-step.billingAddress.billing-address-fieldset';
            $.each(addressFields, function(index, field) {
                registry.async(fieldsetName + '.' + field)(self.bindHandler.bind(self));
            });
            return this;
        },
        bindHandler: function(element) {
            var self = this;
            if (element.component.indexOf('/group') !== -1 || element.componentType == 'group') {

                //Added to add compatibility to TIG_postcode
                if(element.component == 'TIG_Postcode/js/view/form/fields'){
                    $.each(element.elems()[0].elems(), function(index, elem) {
                        registry.async(elem.name)(function() {
                            self.bindHandler(elem);
                        });
                    });
                } else {
                    $.each(element.elems(), function(index, elem) {
                        registry.async(elem.name)(function() {
                            self.bindHandler(elem);
                        });
                    });
                }

            } else {
                element.on('value', this.saveBillingAddress.bind(this, element.index));
                observedElements.push(element);
            }
        },
        saveBillingAddress: function(fieldName) {
            if (!this.isAddressSameAsShipping()) {
                if (!canShowBillingAddress && !this.quoteIsVirtual) {
                    selectBillingAddress(quote.shippingAddress());
                } else if (this.isAddressFormVisible()) {
                    var addressFlat = addressConverter.formDataProviderToFlatData(this.collectObservedData(), 'billingAddress'), newBillingAddress;
                    if (customer.isLoggedIn() && !this.customerHasAddresses) {
                        this.saveInAddressBook(1);
                    }
                    addressFlat.save_in_address_book = this.saveInAddressBook() ? 1 : 0;
                    newBillingAddress = createBillingAddress(addressFlat);

                    selectBillingAddress(newBillingAddress);
                    checkoutData.setSelectedBillingAddress(newBillingAddress.getKey());
                    checkoutData.setNewCustomerBillingAddress(addressFlat);
                    // if (window.checkoutConfig.reloadOnBillingAddress && (fieldName == 'country_id')) {
                    setBillingAddressAction(globalMessageList);
                    // }
                }
            }
        },
        collectObservedData: function() {
            var observedValues = {};
            $.each(observedElements, function(index, field) {
                if (field.hasOwnProperty('value')) {
                    observedValues[field.dataScope] = field.value();
                }
            });
            return observedValues;
        },
        validate: function() {
            if (this.isAmazonAccountLoggedIn()) {
                return true;
            }
            if (this.isAddressSameAsShipping()) {
                oscData.setData('same_as_shipping', true);
                return true;
            }
            if (!this.isAddressFormVisible()) {
                return true;
            }
            this.source.set('params.invalid', false);
            this.source.trigger('billingAddress.data.validate');
            if (this.source.get('billingAddress.custom_attributes')) {
                this.source.trigger('billingAddress.custom_attributes.data.validate');
            }
            oscData.setData('same_as_shipping', false);
            return !this.source.get('params.invalid');
        },
        getAddressTemplate: function() {
            return 'Mageplaza_Osc/container/address/billing-address';
        }
    });
});
