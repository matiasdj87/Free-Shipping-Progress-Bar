<?php

/**
 * Free Shipping Progress Bar for PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author     Ettore Stani
 * @copyright  2025 Ettore Stani
 * @license    https://opensource.org/licenses/MIT  MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class FreeShippingProgress extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'freeshippingprogress';
        $this->tab = 'front_office_features';
        $this->version = '2.2.1';
        $this->author = 'Ettore Stani';
        $this->need_instance = 0;

        // PS 1.7+
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_
        ];

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Free Shipping Progress Bar', [], 'Modules.Freeshippingprogress.Admin');
        $this->description = $this->trans('Displays a progress bar showing how much more customers need to spend to qualify for free shipping.', [], 'Modules.Freeshippingprogress.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Freeshippingprogress.Admin');
    }

    /**
     * Enable new translation system (PrestaShop 1.7+)
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Install the module
     */
    public function install()
    {
        // Impostazioni di visualizzazione
        Configuration::updateValue('FREESHIPPING_DISPLAY_CART', true);
        Configuration::updateValue('FREESHIPPING_DISPLAY_CHECKOUT', true);
        Configuration::updateValue('FREESHIPPING_DISPLAY_POPUP', false);

        // Impostazioni colori (valori predefiniti espliciti)
        Configuration::updateValue('FREESHIPPING_BACKGROUND_COLOR', '#f5f5f5');
        Configuration::updateValue('FREESHIPPING_PROGRESS_COLOR', '#2fb5d2');
        Configuration::updateValue('FREESHIPPING_MESSAGE_COLOR', '#333333');
        Configuration::updateValue('FREESHIPPING_SUCCESS_MESSAGE_COLOR', '#4caf50');

        // Altre impostazioni
        Configuration::updateValue('FREESHIPPING_POPUP_DURATION', 5000);
        Configuration::updateValue('FREESHIPPING_THRESHOLD_TYPE', 'default');
        Configuration::updateValue('FREESHIPPING_CUSTOM_THRESHOLD', 0);

        // Exclusion settings
        Configuration::updateValue('FREESHIPPING_EXCLUDE_VIRTUAL', false);
        Configuration::updateValue('FREESHIPPING_EXCLUDE_CATEGORIES', '');
        Configuration::updateValue('FREESHIPPING_EXCLUDE_PRODUCTS', '');

        // Multilanguage messages
        $languages = Language::getLanguages(false);
        $values = array();

        foreach ($languages as $lang) {
            // Default message in Italian if it's Italian language
            if ($lang['iso_code'] == 'it') {
                $values['FREESHIPPING_MESSAGE'][$lang['id_lang']] = 'Aggiungi {remaining_amount} per ottenere la spedizione gratuita!';
                $values['FREESHIPPING_SUCCESS_MESSAGE'][$lang['id_lang']] = 'Complimenti! Hai ottenuto la spedizione gratuita!';
            } else {
                $values['FREESHIPPING_MESSAGE'][$lang['id_lang']] = 'Add {remaining_amount} to get free shipping!';
                $values['FREESHIPPING_SUCCESS_MESSAGE'][$lang['id_lang']] = 'Congratulations! You\'ve got free shipping!';
            }
        }

        Configuration::updateValue('FREESHIPPING_MESSAGE', $values['FREESHIPPING_MESSAGE']);
        Configuration::updateValue('FREESHIPPING_SUCCESS_MESSAGE', $values['FREESHIPPING_SUCCESS_MESSAGE']);

        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayShoppingCart') &&
            $this->registerHook('displayBeforeCarrier') &&
            $this->registerHook('displayTop') &&
            $this->registerHook('actionCartUpdateQuantityBefore');
    }

    /**
     * Uninstall the module
     */
    public function uninstall()
    {
        Configuration::deleteByName('FREESHIPPING_DISPLAY_CART');
        Configuration::deleteByName('FREESHIPPING_DISPLAY_CHECKOUT');
        Configuration::deleteByName('FREESHIPPING_DISPLAY_POPUP');
        Configuration::deleteByName('FREESHIPPING_BACKGROUND_COLOR');
        Configuration::deleteByName('FREESHIPPING_PROGRESS_COLOR');
        Configuration::deleteByName('FREESHIPPING_MESSAGE_COLOR');
        Configuration::deleteByName('FREESHIPPING_SUCCESS_MESSAGE_COLOR');
        Configuration::deleteByName('FREESHIPPING_MESSAGE');
        Configuration::deleteByName('FREESHIPPING_SUCCESS_MESSAGE');
        Configuration::deleteByName('FREESHIPPING_POPUP_DURATION');
        Configuration::deleteByName('FREESHIPPING_THRESHOLD_TYPE');
        Configuration::deleteByName('FREESHIPPING_CUSTOM_THRESHOLD');
        Configuration::deleteByName('FREESHIPPING_EXCLUDE_VIRTUAL');
        Configuration::deleteByName('FREESHIPPING_EXCLUDE_CATEGORIES');
        Configuration::deleteByName('FREESHIPPING_EXCLUDE_PRODUCTS');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $output = '';

        // Process form submission
        if (Tools::isSubmit('submitFreeShippingProgressModule')) {
            // Multilanguage fields
            $languages = Language::getLanguages(false);
            $values = array();

            foreach ($languages as $lang) {
                $values['FREESHIPPING_MESSAGE'][$lang['id_lang']] =
                    Tools::getValue('FREESHIPPING_MESSAGE_' . $lang['id_lang']);

                $values['FREESHIPPING_SUCCESS_MESSAGE'][$lang['id_lang']] =
                    Tools::getValue('FREESHIPPING_SUCCESS_MESSAGE_' . $lang['id_lang']);
            }

            Configuration::updateValue('FREESHIPPING_MESSAGE', $values['FREESHIPPING_MESSAGE']);
            Configuration::updateValue('FREESHIPPING_SUCCESS_MESSAGE', $values['FREESHIPPING_SUCCESS_MESSAGE']);

            // Boolean values
            Configuration::updateValue(
                'FREESHIPPING_DISPLAY_CART',
                Tools::getValue('FREESHIPPING_DISPLAY_CART')
            );
            Configuration::updateValue(
                'FREESHIPPING_DISPLAY_CHECKOUT',
                Tools::getValue('FREESHIPPING_DISPLAY_CHECKOUT')
            );
            Configuration::updateValue(
                'FREESHIPPING_DISPLAY_POPUP',
                Tools::getValue('FREESHIPPING_DISPLAY_POPUP')
            );

            // Threshold settings with validation
            $thresholdType = Tools::getValue('FREESHIPPING_THRESHOLD_TYPE');
            $customThreshold = (float)Tools::getValue('FREESHIPPING_CUSTOM_THRESHOLD');

            // Validate custom threshold
            if ($thresholdType == 'custom' && $customThreshold < 0) {
                $output .= $this->displayError($this->trans('Custom threshold cannot be negative', [], 'Modules.Freeshippingprogress.Admin'));
                $customThreshold = 0;
            }

            Configuration::updateValue('FREESHIPPING_THRESHOLD_TYPE', $thresholdType);
            Configuration::updateValue('FREESHIPPING_CUSTOM_THRESHOLD', $customThreshold);

            // Verifica e salva le impostazioni dei colori
            $backgroundColor = Tools::getValue('FREESHIPPING_BACKGROUND_COLOR');
            $progressColor = Tools::getValue('FREESHIPPING_PROGRESS_COLOR');
            $messageColor = Tools::getValue('FREESHIPPING_MESSAGE_COLOR');
            $successMessageColor = Tools::getValue('FREESHIPPING_SUCCESS_MESSAGE_COLOR');

            // Verifica che siano colori validi, altrimenti usa i valori predefiniti
            Configuration::updateValue(
                'FREESHIPPING_BACKGROUND_COLOR',
                !empty($backgroundColor) ? $backgroundColor : '#f5f5f5'
            );
            Configuration::updateValue(
                'FREESHIPPING_PROGRESS_COLOR',
                !empty($progressColor) ? $progressColor : '#2fb5d2'
            );
            Configuration::updateValue(
                'FREESHIPPING_MESSAGE_COLOR',
                !empty($messageColor) ? $messageColor : '#333333'
            );
            Configuration::updateValue(
                'FREESHIPPING_SUCCESS_MESSAGE_COLOR',
                !empty($successMessageColor) ? $successMessageColor : '#4caf50'
            );

            $popupDuration = (int)Tools::getValue('FREESHIPPING_POPUP_DURATION');

            // Validate popup duration
            if ($popupDuration < 0) {
                $output .= $this->displayError($this->trans('Popup duration cannot be negative', [], 'Modules.Freeshippingprogress.Admin'));
                $popupDuration = 5000;
            }

            Configuration::updateValue('FREESHIPPING_POPUP_DURATION', $popupDuration);

            // Exclusion settings
            Configuration::updateValue(
                'FREESHIPPING_EXCLUDE_VIRTUAL',
                Tools::getValue('FREESHIPPING_EXCLUDE_VIRTUAL')
            );

            // Parse and validate excluded categories
            $excludedCategories = Tools::getValue('FREESHIPPING_EXCLUDE_CATEGORIES');
            $excludedCategories = $this->parseAndValidateIds($excludedCategories);
            Configuration::updateValue('FREESHIPPING_EXCLUDE_CATEGORIES', $excludedCategories);

            // Parse and validate excluded products
            $excludedProducts = Tools::getValue('FREESHIPPING_EXCLUDE_PRODUCTS');
            $excludedProducts = $this->parseAndValidateIds($excludedProducts);
            Configuration::updateValue('FREESHIPPING_EXCLUDE_PRODUCTS', $excludedProducts);

            $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Freeshippingprogress.Admin'));
        }

        // Check if threshold is configured and show warning if not
        $thresholdType = Configuration::get('FREESHIPPING_THRESHOLD_TYPE');
        $threshold = 0;

        if ($thresholdType == 'custom') {
            $threshold = (float)Configuration::get('FREESHIPPING_CUSTOM_THRESHOLD');
        } else {
            $threshold = (float)Configuration::get('PS_SHIPPING_FREE_PRICE');
        }

        if ($threshold <= 0) {
            $output .= $this->displayWarning(
                $this->trans('Free shipping threshold is set to 0 or not configured. The module will not be displayed on the front office. ', [], 'Modules.Freeshippingprogress.Admin') .
                ($thresholdType != 'custom' ? $this->trans('Please configure the free shipping price in PrestaShop settings or use a custom value.', [], 'Modules.Freeshippingprogress.Admin') : '')
            );
        }

        // Show multi-currency information if multiple currencies are active
        $currencies = Currency::getCurrencies(true);
        if (count($currencies) > 1) {
            $defaultCurrency = Currency::getDefaultCurrency();
            $output .= $this->displayInformation(
                $this->trans('Multi-currency detected:', [], 'Modules.Freeshippingprogress.Admin') . ' ' .
                $this->trans('The threshold is configured in', [], 'Modules.Freeshippingprogress.Admin') . ' ' . $defaultCurrency->iso_code . ' (' . $defaultCurrency->name . '). ' .
                $this->trans('It will be automatically converted to other active currencies using PrestaShop\'s exchange rates.', [], 'Modules.Freeshippingprogress.Admin')
            );
        }

        // Display the config form
        $this->context->smarty->assign('module_dir', $this->_path);
        return $output . $this->renderForm() . $this->displaySupportBox();
    }

    /**
     * Renders the configuration form
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitFreeShippingProgressModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Define the configuration form structure
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', [], 'Modules.Freeshippingprogress.Admin'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display on cart page', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_DISPLAY_CART',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Freeshippingprogress.Admin')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Freeshippingprogress.Admin')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display on checkout page', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_DISPLAY_CHECKOUT',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Freeshippingprogress.Admin')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Freeshippingprogress.Admin')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Display as popup', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_DISPLAY_POPUP',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Freeshippingprogress.Admin')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Freeshippingprogress.Admin')
                            )
                        ),
                        'desc' => $this->trans('Show popup when products are added to cart', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Popup duration (ms)', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_POPUP_DURATION',
                        'class' => 'fixed-width-md',
                        'desc' => $this->trans('Time in milliseconds that the popup will be displayed', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Free shipping threshold', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_THRESHOLD_TYPE',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'default',
                                    'name' => $this->trans('Use PrestaShop configuration', [], 'Modules.Freeshippingprogress.Admin'),
                                ),
                                array(
                                    'id_option' => 'custom',
                                    'name' => $this->trans('Custom value', [], 'Modules.Freeshippingprogress.Admin'),
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                        'desc' => $this->trans('Choose where to get the free shipping threshold value', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Custom threshold amount', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_CUSTOM_THRESHOLD',
                        'class' => 'fixed-width-md',
                        'suffix' => Currency::getDefaultCurrency()->sign,
                        'desc' => $this->trans('Custom amount for free shipping (only used if custom value is selected)', [], 'Modules.Freeshippingprogress.Admin') . ' - ' .
                                  $this->trans('Amount is in default currency', [], 'Modules.Freeshippingprogress.Admin') . ' (' . Currency::getDefaultCurrency()->iso_code . '). ' .
                                  $this->trans('It will be automatically converted for other currencies.', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->trans('Progress bar background color', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_BACKGROUND_COLOR',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->trans('Progress bar fill color', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_PROGRESS_COLOR',
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->trans('Regular message color', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_MESSAGE_COLOR',
                        'class' => 'fixed-width-md',
                        'desc' => $this->trans('Color for the message when free shipping is not reached', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'color',
                        'label' => $this->trans('Success message color', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_SUCCESS_MESSAGE_COLOR',
                        'class' => 'fixed-width-md',
                        'desc' => $this->trans('Color for the message when free shipping is reached', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Message when free shipping not reached', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_MESSAGE',
                        'lang' => true,
                        'desc' => $this->trans('Use {remaining_amount} as a placeholder for the remaining amount', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Message when free shipping is reached', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_SUCCESS_MESSAGE',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'html',
                        'label' => '',
                        'name' => '',
                        'html_content' => '<hr style="margin: 20px 0;"><h4>' . $this->trans('Product Exclusion Settings', [], 'Modules.Freeshippingprogress.Admin') . '</h4>',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Exclude virtual/downloadable products', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_EXCLUDE_VIRTUAL',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->trans('Enabled', [], 'Modules.Freeshippingprogress.Admin')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->trans('Disabled', [], 'Modules.Freeshippingprogress.Admin')
                            )
                        ),
                        'desc' => $this->trans('Virtual and downloadable products will not count towards free shipping threshold', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Excluded categories', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_EXCLUDE_CATEGORIES',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->trans('Category IDs to exclude (comma separated, e.g. "5,12,18"). Products in these categories will not count towards the threshold.', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Excluded products', [], 'Modules.Freeshippingprogress.Admin'),
                        'name' => 'FREESHIPPING_EXCLUDE_PRODUCTS',
                        'class' => 'fixed-width-xxl',
                        'desc' => $this->trans('Product IDs to exclude (comma separated, e.g. "10,25,47"). These specific products will not count towards the threshold.', [], 'Modules.Freeshippingprogress.Admin'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', [], 'Modules.Freeshippingprogress.Admin'),
                ),
            ),
        );
    }

    /**
     * Set values for the configuration form fields
     */
    protected function getConfigFormValues()
    {
        // Recupera valori multilingua
        $languages = Language::getLanguages(false);
        $values = array();

        foreach ($languages as $lang) {
            $values['FREESHIPPING_MESSAGE'][$lang['id_lang']] = Configuration::get(
                'FREESHIPPING_MESSAGE',
                $lang['id_lang']
            );

            $values['FREESHIPPING_SUCCESS_MESSAGE'][$lang['id_lang']] = Configuration::get(
                'FREESHIPPING_SUCCESS_MESSAGE',
                $lang['id_lang']
            );
        }

        // Recupera valori booleani
        $values['FREESHIPPING_DISPLAY_CART'] = Configuration::get('FREESHIPPING_DISPLAY_CART');
        $values['FREESHIPPING_DISPLAY_CHECKOUT'] = Configuration::get('FREESHIPPING_DISPLAY_CHECKOUT');
        $values['FREESHIPPING_DISPLAY_POPUP'] = Configuration::get('FREESHIPPING_DISPLAY_POPUP');

        // Recupera valori colori (con fallback ai valori predefiniti se non impostati)
        $values['FREESHIPPING_BACKGROUND_COLOR'] = Configuration::get('FREESHIPPING_BACKGROUND_COLOR') ?: '#f5f5f5';
        $values['FREESHIPPING_PROGRESS_COLOR'] = Configuration::get('FREESHIPPING_PROGRESS_COLOR') ?: '#2fb5d2';
        $values['FREESHIPPING_MESSAGE_COLOR'] = Configuration::get('FREESHIPPING_MESSAGE_COLOR') ?: '#333333';
        $values['FREESHIPPING_SUCCESS_MESSAGE_COLOR'] = Configuration::get('FREESHIPPING_SUCCESS_MESSAGE_COLOR') ?: '#4caf50';

        // Recupera altri valori
        $values['FREESHIPPING_POPUP_DURATION'] = Configuration::get('FREESHIPPING_POPUP_DURATION');
        $values['FREESHIPPING_THRESHOLD_TYPE'] = Configuration::get('FREESHIPPING_THRESHOLD_TYPE');
        $values['FREESHIPPING_CUSTOM_THRESHOLD'] = Configuration::get('FREESHIPPING_CUSTOM_THRESHOLD');

        // Exclusion settings
        $values['FREESHIPPING_EXCLUDE_VIRTUAL'] = Configuration::get('FREESHIPPING_EXCLUDE_VIRTUAL');
        $values['FREESHIPPING_EXCLUDE_CATEGORIES'] = Configuration::get('FREESHIPPING_EXCLUDE_CATEGORIES');
        $values['FREESHIPPING_EXCLUDE_PRODUCTS'] = Configuration::get('FREESHIPPING_EXCLUDE_PRODUCTS');

        return $values;
    }

    /**
     * Parse and validate comma-separated IDs
     *
     * @param string $input Comma-separated IDs
     * @return string Cleaned comma-separated IDs
     */
    protected function parseAndValidateIds($input)
    {
        if (empty($input)) {
            return '';
        }

        // Split by comma, trim whitespace, filter out non-numeric values
        $ids = array_map('trim', explode(',', $input));
        $ids = array_filter($ids, function($id) {
            return is_numeric($id) && $id > 0;
        });

        // Remove duplicates and return as comma-separated string
        $ids = array_unique($ids);
        return implode(',', $ids);
    }

    /**
     * Calculate eligible cart total excluding specified products/categories
     *
     * @param Cart $cart The shopping cart
     * @return float Total amount eligible for free shipping calculation
     */
    protected static function getEligibleCartTotal($cart)
    {
        $products = $cart->getProducts();
        $total = 0;

        // Get exclusion settings
        $excludeVirtual = (bool)Configuration::get('FREESHIPPING_EXCLUDE_VIRTUAL');
        $excludedCategoriesStr = Configuration::get('FREESHIPPING_EXCLUDE_CATEGORIES');
        $excludedProductsStr = Configuration::get('FREESHIPPING_EXCLUDE_PRODUCTS');

        // Parse excluded IDs
        $excludedCategories = !empty($excludedCategoriesStr) ?
            array_map('intval', explode(',', $excludedCategoriesStr)) : [];
        $excludedProducts = !empty($excludedProductsStr) ?
            array_map('intval', explode(',', $excludedProductsStr)) : [];

        foreach ($products as $product) {
            $productId = (int)$product['id_product'];

            // Skip if product is explicitly excluded
            if (in_array($productId, $excludedProducts)) {
                continue;
            }

            // Skip virtual products if setting is enabled
            if ($excludeVirtual && isset($product['is_virtual']) && $product['is_virtual']) {
                continue;
            }

            // Skip if product is in excluded category
            if (!empty($excludedCategories)) {
                $productCategories = Product::getProductCategoriesFull($productId);
                $isExcluded = false;

                foreach ($productCategories as $category) {
                    if (in_array((int)$category['id_category'], $excludedCategories)) {
                        $isExcluded = true;
                        break;
                    }
                }

                if ($isExcluded) {
                    continue;
                }
            }

            // Add product total to eligible amount
            $total += (float)$product['total_wt']; // With tax
        }

        return $total;
    }

    /**
     * Calculate free shipping data for templates
     * This is a public static method so it can be called from AJAX controller
     *
     * @param Context|null $context Optional context (defaults to current)
     * @return array Free shipping data or empty array if threshold is 0
     */
    public static function getFreeShippingData($context = null)
    {
        if ($context === null) {
            $context = Context::getContext();
        }

        $cart = $context->cart;
        $langId = $context->language->id;

        // Current cart total (excluding products based on exclusion settings)
        $currentAmount = self::getEligibleCartTotal($cart);

        // Get free shipping threshold based on configuration
        $thresholdType = Configuration::get('FREESHIPPING_THRESHOLD_TYPE');

        if ($thresholdType == 'custom') {
            // Use custom threshold from module settings
            $freeShippingThreshold = (float)Configuration::get('FREESHIPPING_CUSTOM_THRESHOLD');
        } else {
            // Use PrestaShop's configuration value
            $freeShippingThreshold = (float)Configuration::get('PS_SHIPPING_FREE_PRICE');

            // If not set in PrestaShop, use 0
            if ($freeShippingThreshold === false) {
                $freeShippingThreshold = 0;
            }
        }

        // If threshold is 0 or negative, return empty array (module effectively disabled)
        if ($freeShippingThreshold <= 0) {
            return [];
        }

        // MULTI-CURRENCY SUPPORT: Convert threshold to current currency
        // Threshold is assumed to be in default currency
        $currentCurrency = $context->currency;
        $defaultCurrency = Currency::getDefaultCurrency();

        // Only convert if current currency is different from default
        if ($currentCurrency->id != $defaultCurrency->id) {
            $freeShippingThreshold = Tools::convertPrice($freeShippingThreshold, $currentCurrency);
        }

        // Calculate remaining amount and percentage
        $remainingAmount = max(0, $freeShippingThreshold - $currentAmount);
        $percentage = $freeShippingThreshold > 0 ?
            min(100, ($currentAmount / $freeShippingThreshold) * 100) : 100;

        // Get messages
        $message = Configuration::get('FREESHIPPING_MESSAGE', $langId);
        $successMessage = Configuration::get('FREESHIPPING_SUCCESS_MESSAGE', $langId);

        // MODIFICACIÓN MODERNA PARA PS 1.7 / PS 8 (Evita error fatal de Tools::displayPrice)
        $formattedAmount = Context::getContext()->currentLocale->formatPrice($remainingAmount, Context::getContext()->currency->iso_code);
        $message = str_replace('{remaining_amount}', $formattedAmount, $message);

        // Define colors - usa valori predefiniti se non impostati
        $backgroundColor = Configuration::get('FREESHIPPING_BACKGROUND_COLOR') ?: '#f5f5f5';
        $progressColor = Configuration::get('FREESHIPPING_PROGRESS_COLOR') ?: '#2fb5d2';
        $messageColor = Configuration::get('FREESHIPPING_MESSAGE_COLOR') ?: '#333333';
        $successMessageColor = Configuration::get('FREESHIPPING_SUCCESS_MESSAGE_COLOR') ?: '#4caf50';

        return [
            'current_amount' => $currentAmount,
            'threshold' => $freeShippingThreshold,
            'remaining_amount' => $remainingAmount,
            'percentage' => $percentage,
            'message' => $message,
            'success_message' => $successMessage,
            'is_free_shipping' => $remainingAmount <= 0,
            'background_color' => $backgroundColor,
            'progress_color' => $progressColor,
            'message_color' => $messageColor,
            'success_message_color' => $successMessageColor,
            'popup_duration' => (int)Configuration::get('FREESHIPPING_POPUP_DURATION'),
        ];
    }

    /**
     * Add CSS and JavaScript to the page header
     */
    public function hookDisplayHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');

        // Pass the module data to JavaScript
        Media::addJsDef([
            'freeShippingProgressData' => self::getFreeShippingData(),
            'freeShippingProgressAjaxUrl' => $this->context->link->getModuleLink(
                'freeshippingprogress',
                'ajax'
            ),
            'freeShippingProgressToken' => Tools::getToken(false),
            'displayPopup' => (bool)Configuration::get('FREESHIPPING_DISPLAY_POPUP'),
        ]);
    }

    /**
     * Display in shopping cart
     */
    public function hookDisplayShoppingCart()
    {
        if (!Configuration::get('FREESHIPPING_DISPLAY_CART')) {
            return '';
        }

        $data = self::getFreeShippingData();
        // Don't display if threshold is 0 or no data
        if (empty($data)) {
            return '';
        }

        $this->context->smarty->assign('freeShippingData', $data);
        return $this->display(__FILE__, 'views/templates/hook/displayShoppingCart.tpl');
    }

    /**
     * Display in checkout before carrier selection
     */
    public function hookDisplayBeforeCarrier()
    {
        if (!Configuration::get('FREESHIPPING_DISPLAY_CHECKOUT')) {
            return '';
        }

        $data = self::getFreeShippingData();
        // Don't display if threshold is 0 or no data
        if (empty($data)) {
            return '';
        }

        $this->context->smarty->assign('freeShippingData', $data);
        return $this->display(__FILE__, 'views/templates/hook/displayBeforeCarrier.tpl');
    }

    /**
     * Display popup (if enabled)
     */
    public function hookDisplayTop()
    {
        // Non mostrare il popup se:
        // - non è abilitato nelle configurazioni
        // - il carrello è vuoto
        // - siamo nella pagina del carrello
        if (
            !Configuration::get('FREESHIPPING_DISPLAY_POPUP') ||
            $this->context->cart->nbProducts() == 0 ||
            $this->context->controller->php_self == 'cart'
        ) {
            return '';
        }

        $data = self::getFreeShippingData();
        // Don't display if threshold is 0 or no data
        if (empty($data)) {
            return '';
        }

        $this->context->smarty->assign('freeShippingData', $data);
        return $this->display(__FILE__, 'views/templates/hook/displayPopup.tpl');
    }

    /**
     * Handle cart updates to refresh free shipping data via AJAX
     * and trigger popup when products are added
     */
    public function hookActionCartUpdateQuantityBefore($params)
    {
        // Imposta un flag per aggiornare l'UI quando il carrello cambia
        $this->context->cookie->freeShippingDataUpdated = true;

        // Imposta un flag per attivare il popup
        $this->context->cookie->freeShippingCartUpdated = true;

        // Assicurati che i cookie vengano salvati
        $this->context->cookie->write();
    }

    /**
     * Display support box in admin configuration
     */
    private function displaySupportBox()
    {
        return '
        <div class="panel" style="margin-top: 20px; border-left: 4px solid #00aff0;">
            <div class="panel-heading">
                <i class="icon-heart"></i> ' . $this->trans('Support This Module', [], 'Modules.Freeshippingprogress.Admin') . '
            </div>
            <div class="panel-body">
                <p>' . $this->trans('This module is free and open-source. If you find it useful, please consider supporting its development:', [], 'Modules.Freeshippingprogress.Admin') . '</p>
                <p style="margin: 15px 0;">
                    <a href="https://www.paypal.com/paypalme/ettorestani" target="_blank" class="btn btn-primary" style="background-color: #00aff0;">
                        <i class="icon-heart"></i> ' . $this->trans('Support via PayPal', [], 'Modules.Freeshippingprogress.Admin') . '
                    </a>
                </p>
                <p style="font-size: 12px; color: #666;">
                    ' . $this->trans('Need professional help? Contact me at', [], 'Modules.Freeshippingprogress.Admin') . '
                    <a href="mailto:info@ettorestani.it">info@ettorestani.it</a>
                    ' . $this->trans('or visit', [], 'Modules.Freeshippingprogress.Admin') . '
                    <a href="https://www.ettorestani.it" target="_blank">www.ettorestani.it</a>
                </p>
            </div>
        </div>';
    }
}
