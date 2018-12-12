<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Osc
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace TIG\PostcodeCheckoutSupport\Block\Frontend\Checkout\Mageplaza;

use Magento\Checkout\Block\Checkout\AttributeMerger;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\AttributeMetadataDataProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Ui\Component\Form\AttributeMapper;
use Mageplaza\Osc\Helper\Data as OscHelper;

/**
 * Class LayoutProcessor
 * @package Mageplaza\Osc\Block\Checkout
 */
class LayoutProcessor implements LayoutProcessorInterface
{
    /**
     * @var OscHelper
     */
    private $_oscHelper;

    /**
     * @var \Magento\Customer\Model\AttributeMetadataDataProvider
     */
    private $attributeMetadataDataProvider;

    /**
     * @var \Magento\Ui\Component\Form\AttributeMapper
     */
    protected $attributeMapper;

    /**
     * @var \Magento\Checkout\Block\Checkout\AttributeMerger
     */
    protected $merger;

    /**
     * @var \Magento\Customer\Model\Options
     */
    private $options;

    /**
     * @type \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * LayoutProcessor constructor.
     * @param CheckoutSession $checkoutSession
     * @param OscHelper $oscHelper
     * @param AttributeMetadataDataProvider $attributeMetadataDataProvider
     * @param AttributeMapper $attributeMapper
     * @param AttributeMerger $merger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        OscHelper $oscHelper,
        AttributeMetadataDataProvider $attributeMetadataDataProvider,
        AttributeMapper $attributeMapper,
        AttributeMerger $merger
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->_oscHelper = $oscHelper;
        $this->attributeMetadataDataProvider = $attributeMetadataDataProvider;
        $this->attributeMapper = $attributeMapper;
        $this->merger = $merger;
    }

    /**
     * Process js Layout of block
     *
     * @param array $jsLayout
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process($jsLayout)
    {
        if (!$this->_oscHelper->isOscPage()) {
            return $jsLayout;
        }

        /** Shipping address fields */
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
                  ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'])) {
            $fields = $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']
                      ['children']['shipping-address-fieldset']['children'];
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']
            ['children']['shipping-address-fieldset']['children'] = $this->getAddressFieldset($fields, 'shippingAddress');
        }

        /** Billing address fields */
        if (isset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
                  ['children']['billingAddress']['children']['billing-address-fieldset']['children'])) {
            $fields = $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['billingAddress']
                      ['children']['billing-address-fieldset']['children'];
            $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['billingAddress']
            ['children']['billing-address-fieldset']['children'] = $this->getAddressFieldset($fields, 'billingAddress');
        }

        /** Remove billing customer email if quote is not virtual */
        if (!$this->checkoutSession->getQuote()->isVirtual()) {
            unset($jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['billingAddress']
                  ['children']['customer-email']);
        }

        /** Remove billing address in payment method content */
        $fields = &$jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
                   ['payment']['children']['payments-list']['children'];
        foreach ($fields as $code => $field) {
            if ($field['component'] == 'Magento_Checkout/js/view/billing-address') {
                unset($fields[$code]);
            }
        }

        //Billing address compatible
        //Added from POSTNL to address compatibility issue

        $billingFields = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']
                          ['children']['billingAddress']['children']['billing-address-fieldset']['children'];

        $billingFields = $this->processAddress($billingFields, 'billingAddress', []);


        $this->setFieldToHide(
            $billingFields, 'postcode', true
        );
        $this->setFieldToHide($billingFields, 'city');
        $this->setFieldToHide($billingFields, 'street');


        return $jsLayout;
    }

    //PostNL compatible methods

    /**
     * @param $fieldset
     * @param $scope
     * @param $deps
     *
     * @return mixed
     */
    private function processAddress($fieldset, $scope, $deps)
    {
        $fieldset['postcode-field-group'] = [
            'component' => 'TIG_Postcode/js/view/form/fields',
            'type'      => 'group',
            'provider'  => 'checkoutProvider',
            'sortOrder' => '65',
            'config'    => [
                'customScope' => $scope,
                'template'    => 'TIG_Postcode/checkout/field-group'
            ],
            'deps'      => $deps,
            'children'  => [
                'field-group' => [
                    'component'   => 'uiComponent',
                    'displayArea' => 'field-group',
                    'children'  => [
                        'postcode'             => $fieldset['postcode'],
                        'housenumber'          => $this->getHouseNumberField($scope),
                        'housenumber_addition' => $this->getHouseNumberAdditionField($scope)
                    ]
                ]
            ],
            'dataScope' => '',
            'visible'   => true
        ];

        return $fieldset;
    }

    /**
     * @param string $scope
     *
     * @return array
     */
    private function getHouseNumberField($scope = 'billingAddress')
    {
        return [
            'component'  => 'Magento_Ui/js/form/element/abstract',
            'config'     => [
                'customScope' => $scope . '.custom_attributes',
                'template'    => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input'
            ],
            'provider'   => 'checkoutProvider',
            'dataScope'  => $scope . '.custom_attributes.tig_housenumber',
            'label'      => __('House number'),
            'sortOrder'  => '115',
            'validation' => [
                'required-entry' => true,
            ],
            'visible'    => true
        ];
    }

    /**
     * @param string $scope
     *
     * @return array
     */
    private function getHouseNumberAdditionField($scope = 'billingAddress')
    {
        return [
            'component'  => 'Magento_Ui/js/form/element/abstract',
            'config'     => [
                'customScope' => $scope . '.custom_attributes',
                'template'    => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input'
            ],
            'provider'   => 'checkoutProvider',
            'dataScope'  => $scope . '.custom_attributes.tig_housenumber_addition',
            'label'      => __('Addition'),
            'sortOrder'  => '120',
            'validation' => [
                'required-entry' => false,
            ],
            'visible'    => true
        ];
    }

    /**
     * Sets visible on false for the shipping fields that are re-writend by the postcode service.
     * @param $fields
     * @param $section
     * @param $disableRequired
     */
    private function setFieldToHide(&$fields, $section, $disableRequired = false)
    {
        $additionalClass = null;
        if (isset($fields[$section]['config']['additionalClasses'])) {
            $additionalClass = $fields[$section]['config']['additionalClasses'];
        }

        if ($section == 'street' || $section == 'city') {
            $additionalClass = $additionalClass . ' ' . 'tig_hidden';
        }

        $fields[$section]['visible'] = false;
        if ($disableRequired) {
            $fields[$section]['validation']['required-entry'] = false;
        }

        $fields[$section]['config']['additionalClasses'] = $additionalClass;
    }

    /**
     * Get address fieldset for shipping/billing address
     *
     * @param $fields
     * @param $type
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAddressFieldset($fields, $type)
    {
        $elements = $this->getAddressAttributes();

        $systemAttribute = $elements['default'];
        if (sizeof($systemAttribute)) {
            $attributesToConvert = [
                'prefix' => [$this->getOptions(), 'getNamePrefixOptions'],
                'suffix' => [$this->getOptions(), 'getNameSuffixOptions'],
            ];
            $systemAttribute = $this->convertElementsToSelect($systemAttribute, $attributesToConvert);
            $fields = $this->merger->merge(
                $systemAttribute,
                'checkoutProvider',
                $type,
                $fields
            );
        }

        $customAttribute = $elements['custom'];
        if (sizeof($customAttribute)) {
            $fields = $this->merger->merge(
                $customAttribute,
                'checkoutProvider',
                $type . '.custom_attributes',
                $fields
            );
        }

        $this->addCustomerAttribute($fields, $type);
        $this->addAddressOption($fields);


        return $fields;
    }

    /**
     * Add customer attribute like gender, dob, taxvat
     *
     * @param $fields
     * @param $type
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function addCustomerAttribute(&$fields, $type)
    {
        $attributes = $this->attributeMetadataDataProvider->loadAttributesCollection(
            'customer',
            'customer_account_create'
        );
        $addressElements = [];
        foreach ($attributes as $attribute) {
            if (!$this->_oscHelper->getAddressHelper()->isCustomerAttributeVisible($attribute)) {
                continue;
            }
            $addressElements[$attribute->getAttributeCode()] = $this->attributeMapper->map($attribute);
        }

        if (sizeof($addressElements)) {
            $fields = $this->merger->merge(
                $addressElements,
                'checkoutProvider',
                $type . '.custom_attributes',
                $fields
            );
        }

        foreach ($fields as $code => &$field) {
            if(isset($field['label'])) {
                $field['label'] = __($field['label']);
            }
        }

        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */
    private function addAddressOption(&$fields)
    {
        $fieldPosition = $this->_oscHelper->getAddressHelper()->getAddressFieldPosition();

        $oscField = [];
        $allFieldSection = $this->_oscHelper->getAddressHelper()->getSortedField(false);
        foreach ($allFieldSection as $allfield) {
            foreach ($allfield as $field) {
                $oscField[] = $field->getAttributeCode();
            }
        }

        $this->rewriteFieldStreet($fields);

        foreach ($fields as $code => &$field) {
            $fieldConfig = isset($fieldPosition[$code]) ? $fieldPosition[$code] : [];
            if (!sizeof($fieldConfig)) {
                if (in_array($code, ['country_id'])) {
                    $field['config']['additionalClasses'] = "mp-hidden";
                    continue;
                } else if (in_array($code, $oscField)) {
                    unset($fields[$code]);
                }
            } else {
                $oriClasses = isset($field['config']['additionalClasses']) ? $field['config']['additionalClasses'] : '';
                $field['config']['additionalClasses'] = "{$oriClasses} col-mp mp-{$fieldConfig['colspan']}" . ($fieldConfig['isNewRow'] ? ' mp-clear' : '');
                $field['sortOrder'] = (isset($field['sortOrder']) && !in_array($code, $oscField)) ? $field['sortOrder'] : $fieldConfig['sortOrder'];
                if ($code == 'dob') {
                    $field['options'] = ['yearRange' => '-120y:c+nn', 'maxDate' => '-1d', 'changeMonth' => true, 'changeYear' => true];
                }

                $this->rewriteTemplate($field);
            }
        }
        /**
         * Compatible Amazon Pay
         */
        if ($this->_oscHelper->isEnableAmazonPay()) {
            $fields['inline-form-manipulator'] = array(
                'component' => 'Mageplaza_Osc/js/view/amazon'
            );

        }

        return $this;
    }

    /**
     * Change template to remove valueUpdate = 'keyup'
     *
     * @param $field
     * @param string $template
     * @return $this
     */
    public function rewriteTemplate(&$field, $template = 'Mageplaza_Osc/container/form/element/input')
    {
        if (isset($field['type']) && $field['type'] == 'group') {
            foreach ($field['children'] as $key => &$child) {
                if ($key == 0 && in_array('street', explode('.', $field['dataScope'])) && $this->_oscHelper->isGoogleHttps()) {
                    $this->rewriteTemplate($child, 'Mageplaza_Osc/container/form/element/street');
                    continue;
                }
                $this->rewriteTemplate($child);
            }
        } elseif (isset($field['config']['elementTmpl']) && $field['config']['elementTmpl'] == "ui/form/element/input") {
            $field['config']['elementTmpl'] = $template;
            if ($this->_oscHelper->isUsedMaterialDesign()) {
                $field['config']['template'] = 'Mageplaza_Osc/container/form/field';
            }
        }

        return $this;
    }

    /**
     * Change template street when enable material design
     * @param $fields
     * @return $this
     */
    public function rewriteFieldStreet(&$fields)
    {

        if ($this->_oscHelper->isUsedMaterialDesign()) {
            $fields['country_id']['config']['template'] = 'Mageplaza_Osc/container/form/field';
            $fields['region_id']['config']['template'] = 'Mageplaza_Osc/container/form/field';
            foreach ($fields['street']['children'] as $key => $value) {
                $fields['street']['children'][0]['label'] = $fields['street']['label'];
                $fields['street']['children'][$key]['config']['template'] = 'Mageplaza_Osc/container/form/field';
            }
            $fields['street']['config']['fieldTemplate'] = 'Mageplaza_Osc/container/form/field';
            unset($fields['street']['label']);
        }

        return $this;
    }

    /**
     * @return \Magento\Customer\Model\Options
     */
    private function getOptions()
    {
        if (!is_object($this->options)) {
            $this->options = ObjectManager::getInstance()->get(\Magento\Customer\Model\Options::class);
        }

        return $this->options;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getAddressAttributes()
    {
        /** @var \Magento\Eav\Api\Data\AttributeInterface[] $attributes */
        $attributes = $this->attributeMetadataDataProvider->loadAttributesCollection(
            'customer_address',
            'customer_register_address'
        );

        $elements = [
            'custom' => [],
            'default' => []
        ];
        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            $element = $this->attributeMapper->map($attribute);
            if (isset($element['label'])) {
                $label = $element['label'];
                $element['label'] = __($label);
            }

            ($attribute->getIsUserDefined()) ?
                $elements['custom'][$code] = $element :
                $elements['default'][$code] = $element;
        }

        return $elements;
    }

    /**
     * Convert elements(like prefix and suffix) from inputs to selects when necessary
     *
     * @param array $elements address attributes
     * @param array $attributesToConvert fields and their callbacks
     * @return array
     */
    private function convertElementsToSelect($elements, $attributesToConvert)
    {
        $codes = array_keys($attributesToConvert);
        foreach (array_keys($elements) as $code) {
            if (!in_array($code, $codes)) {
                continue;
            }
            $options = call_user_func($attributesToConvert[$code]);
            if (!is_array($options)) {
                continue;
            }
            $elements[$code]['dataType'] = 'select';
            $elements[$code]['formElement'] = 'select';

            foreach ($options as $key => $value) {
                $elements[$code]['options'][] = [
                    'value' => $key,
                    'label' => $value,
                ];
            }
        }

        return $elements;
    }
}