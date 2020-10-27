define(
    ['underscore', 'mage/utils/wrapper', 'TIG_PostcodeCheckoutSupport/js/Helper/DataProvider'],
    function (_, wrapper, DataProvider) {
        'use strict';

        return function (target) {
            /**
             * If custom attributes array, convert to object
             */
            target.quoteAddressToFormAddressData =
                wrapper.wrapSuper(target.quoteAddressToFormAddressData, function (modelAddress) {
                    if (DataProvider.getCheckoutCompatibility() != 'amasty') {
                        return this._super(modelAddress);
                    }

                    var shippingAddress = this._super(modelAddress);

                    if (modelAddress.customAttributes === undefined) {
                        modelAddress.customAttributes = [];
                    }

                    if (shippingAddress.custom_attributes !== undefined) {
                        modelAddress.customAttributes.push({'attribute_code': 'tig_housenumber', 'value': shippingAddress.custom_attributes['tig_housenumber']});
                        modelAddress.customAttributes.push({'attribute_code': 'tig_housenumber_addition', 'value': shippingAddress.custom_attributes['tig_housenumber_addition']});
                    }
                    return shippingAddress;
                });

            return target;
        };
    }
);
