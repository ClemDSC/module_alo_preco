<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2023 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'AloPrecoConfig.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'AloPrecoConfigProductAttribute.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'AloPrecoPreorder.php';

class Alo_Preco extends Module
{
    public const HOOK_LIST = [
        'actionCartSave',
        'actionCartUpdateQuantityBefore',
        'actionFrontControllerSetVariables',
        'actionObjectOrderUpdateAfter',
        'actionPresentCart',
        'actionValidateOrder',
        'displayBackOfficeHeader',
    ];

    public const CONFIG_KEY_PRECO_CATEGORY_ID = 'ALO_PRECO_CATEGORY_ID';
    public const CONFIG_KEY_PRECO_ORDER_STATE_ID = 'ALO_PRECO_ORDER_STATE_ID';
    public const CONFIG_KEY_PRECO_SUCCESS_ORDER_STATE_ID = 'ALO_PRECO_SUCCESS_ORDER_STATE_ID';
    public const CONFIG_KEY_PRECO_FAILED_ORDER_STATE_ID = 'ALO_PRECO_FAILED_ORDER_STATE_ID';

    public function __construct()
    {
        $this->name = 'alo_preco';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Klorel';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('A L\'O - Preco');
        $this->description = $this->l('Module de gestion des précommandes pour A L\'O');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->dependencies = ['alo_stocks'];
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install(): bool
    {
        $alo_stock = Module::getInstanceByName('alo_stocks');

        return parent::install()
            // Mise en place de la DB
            && AloPrecoConfig::createDatabase()
            && AloPrecoConfigProductAttribute::createDatabase()
            && AloPrecoPreorder::createDatabase()

            // Contrôleurs admins
            && $this->installAdminController('AdminAloPrecoConfig', 'Configuration des précommandes')
            && $this->installAdminController('AdminAloPrecoPreorder', 'Gestion des précommandes', false)
            && $this->installAdminController('AdminAloPrecoConfigProductAttribute', 'Gestion des liaisons précommandes - produits', false)
            && $this->registerHook(self::HOOK_LIST)

            // On passe alo_stocks en dernière position dans le hook validateOrder
            && $alo_stock->updatePosition(
                Hook::getIdByName('actionValidateOrder'),
                1,
                $this->getPosition(Hook::getIdByName('actionValidateOrder'))
            )

            // Remplacement des fichiers du cœur non surchargables
            && $this->replaceFile(
                $this->local_path . 'files' . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . 'custom_stockManager.php.file',
                _PS_CORE_DIR_ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR . 'stockManager.php'
            )

            // Caches
            && $this->opcacheReset();
    }

    /**
     * Installation du controller AdminAloPrecoConfig
     * @param string $class_name
     * @param string $name
     * @param bool $tab_active
     * @param string $parent_class_name
     * @return boolean
     */
    public function installAdminController(
        string $class_name,
        string $name,
        bool   $tab_active = true,
        string $parent_class_name = 'AdminKlorel'): bool
    {
        $parentId = Tab::getIdFromClassName($parent_class_name);
        $tab = new Tab();
        $tab->active = (int)$tab_active;
        $tab->class_name = $class_name;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->id_parent = $parentId;
        $tab->module = $this->name;

        if (!$tab->add()) {
            return false;
        }

        return true;
    }

    protected function replaceFile(string $source, string $dest): bool
    {
        return (bool)file_put_contents($dest, file_get_contents($source));
    }

    public function opcacheReset()
    {
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return true;
    }

    /**
     * Renvoi la liste des couleurs d'un produit formatés pour des boutons radios
     * @param int $product_id
     * @param string $return_format HTML|ARRAY
     * @return array[]|string
     */
    public function getColorAttributeOptions(int $product_id, string $return_format = 'HTML')
    {
        $attribute_data_list = Product::getAttributesColorList([$product_id], false);
        $output = '<div class="radio">';
        $output .= '<label><input type="radio" name="id_attribute" id="all" value="0" checked="checked">' . $this->l('Toutes les déclinaisons') . '</label>';
        $output .= '</div>';

        $output_array = [
            [
                'id' => 'all',
                'value' => 0,
                'label' => $this->l('Toutes les déclinaisons')
            ]
        ];

        if (!empty($attribute_data_list)) {
            $attribute_data = $attribute_data_list[$product_id];
            foreach ($attribute_data as $attribute) {
                $output .= '<div class="radio">';
                $output .= '<label><input type="radio" name="id_attribute" id="attribute_' . $attribute['id_attribute'] . '" value="' . $attribute['id_attribute'] . '">' . $attribute['name'] . '</label>';
                $output .= '</div>';

                $output_array[] = [
                    'id' => 'attribute_' . $attribute['id_attribute'],
                    'value' => $attribute['id_attribute'],
                    'label' => $attribute['name']
                ];
            }
        }

        if ($return_format === 'ARRAY') {
            return $output_array;
        }

        return $output;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallAdminController('AdminAloPrecoConfig')
            && $this->uninstallAdminController('AdminAloPrecoPreorder')
            && $this->uninstallAdminController('AdminAloPrecoConfigProductAttribute')
            && $this->unregisterHookList(self::HOOK_LIST)
            && $this->replaceFile(
                $this->local_path . 'files' . DIRECTORY_SEPARATOR . 'core_backup' . DIRECTORY_SEPARATOR . 'core_stockManager.php.file',
                _PS_CORE_DIR_ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR . 'stockManager.php'
            )
            && $this->opcacheReset();
    }

    /**
     * Désinstallation du controller AdminAloPrecoConfig
     * @param string $class_name
     * @return boolean
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function uninstallAdminController(string $class_name): bool
    {
        $idTab = (int)Tab::getIdFromClassName($class_name);
        if ($idTab) {
            $tab = new Tab($idTab);
            try {
                $tab->delete();
            } catch (Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $hook_list
     * @return bool
     */
    protected function unregisterHookList(array $hook_list): bool
    {
        foreach ($hook_list as $hook) {
            if (!$this->unregisterHook($hook)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent(): string
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitAlo_PrecoModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign([
            'category_cron_url' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/' . $this->name . '/action?action=set_preco_products_category',
            'process_preorders' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/' . $this->name . '/action?action=process_preorders',
            'send_preorder_reminder_emails' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/' . $this->name . '/action?action=send_preorder_reminder_emails',
            'close_preorders' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/' . $this->name . '/action?action=close_preorders',
        ]);

        $information_panel = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/information_panel.tpl');

        return '<br>' . $information_panel . $this->renderForm();
    }

    /**
     * Save form data.
     */
    protected function postProcess(): void
    {
        $config_form = $this->getConfigForm();

        foreach ($config_form['form']['input'] as $input) {
            if (array_key_exists('multiple', $input) && $input['multiple']) {
                $input_value = Tools::getValue($input['name']);
                if (is_array($input_value)) {
                    $input_value = implode(',', $input_value);
                }
                Configuration::updateValue($input['name'], $input_value);
                continue;
            }
            Configuration::updateValue($input['name'], Tools::getValue($input['name']));

            if ($input['name'] === self::CONFIG_KEY_PRECO_ORDER_STATE_ID) {
                $order_state = new OrderState((int)Tools::getValue($input['name']));
                $order_state->send_email = true;
                $language_list = Language::getLanguages(true);
                foreach ($language_list as $language_data) {
                    $order_state->template[$language_data['id_lang']] = 'alo_preco_order';
                }
                $order_state->save();
            }

            if ($input['name'] === self::CONFIG_KEY_PRECO_SUCCESS_ORDER_STATE_ID) {
                $order_state = new OrderState((int)Tools::getValue($input['name']));
                $order_state->send_email = true;
                $language_list = Language::getLanguages(true);
                foreach ($language_list as $language_data) {
                    $order_state->template[$language_data['id_lang']] = 'alo_preco_success';
                }
                $order_state->save();
            }

            if ($input['name'] === 'ALO_PRECO_PRODUCED_ORDER_STATE_ID') {
                $order_state = new OrderState((int)Tools::getValue($input['name']));
                $order_state->send_email = true;
                $language_list = Language::getLanguages(true);
                foreach ($language_list as $language_data) {
                    $order_state->template[$language_data['id_lang']] = 'alo_preco_produced';
                }
                $order_state->save();
            }

            if ($input['name'] === self::CONFIG_KEY_PRECO_FAILED_ORDER_STATE_ID) {
                $order_state = new OrderState((int)Tools::getValue($input['name']));
                $order_state->send_email = true;
                $language_list = Language::getLanguages(true);
                foreach ($language_list as $language_data) {
                    $order_state->template[$language_data['id_lang']] = 'alo_preco_failed';
                }
                $order_state->save();
            }
        }

        $this->opcacheReset();
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm(): array
    {
        $category_data_list = Category::getCategories($this->context->language->id, false, false);
        $order_status_list = OrderState::getOrderStates($this->context->language->id);

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => 'Catégorie Précommande',
                        'name' => self::CONFIG_KEY_PRECO_CATEGORY_ID,
                        'options' => [
                            'query' => $category_data_list,
                            'id' => 'id_category',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Statut "Précommande en cours"',
                        'name' => self::CONFIG_KEY_PRECO_ORDER_STATE_ID,
                        'options' => [
                            'query' => $order_status_list,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Statut "Précommande validée"',
                        'name' => self::CONFIG_KEY_PRECO_SUCCESS_ORDER_STATE_ID,
                        'options' => [
                            'query' => $order_status_list,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Statut "Précommande en échec"',
                        'name' => self::CONFIG_KEY_PRECO_FAILED_ORDER_STATE_ID,
                        'options' => [
                            'query' => $order_status_list,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => 'Statut "Précommande fabriquée"',
                        'name' => 'ALO_PRECO_PRODUCED_ORDER_STATE_ID',
                        'options' => [
                            'query' => $order_status_list,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAlo_PrecoModule';

        $helper->currentIndex =
            $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name .
            '&tab_module=' . $this->tab .
            '&module_name=' . $this->name;

        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues(): array
    {
        $config_form = $this->getConfigForm();
        $config_form_values = [];

        foreach ($config_form['form']['input'] as $input) {
            if (array_key_exists('multiple', $input) && $input['multiple']) {
                $config_form_values[$input['name'] . '[]'] = explode(',', Configuration::get($input['name']));
                continue;
            }
            $config_form_values[$input['name']] = Configuration::get($input['name']);
        }

        return $config_form_values;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader(): void
    {
        $this->context->controller->addJS($this->_path . 'views/js/back.js');
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');

        if (Tools::getValue('controller') === 'AdminAloPrecoPreorder'
            || Tools::getValue('controller') === 'AdminAloPrecoConfigProductAttribute'
        ) {
            $this->context->controller->addJS($this->_path . 'views/js/admin_custom_ux.js');
            $this->context->controller->addCSS($this->_path . 'views/css/admin_custom_ux.css');
        }

        if (!Configuration::get(self::CONFIG_KEY_PRECO_CATEGORY_ID)) {
            $module_configuration_url = $this->context->link->getAdminLink(
                'AdminModules',
                true,
                ['configure' => $this->name,],
                ['configure' => $this->name,]
            );

            $this->context->controller->informations[] = 'Merci de mapper la catégorie précommande dans la <a href="' . $module_configuration_url . '">page de configuration du module ' . $this->displayName . '</a>';
        }

        if (!Configuration::get(self::CONFIG_KEY_PRECO_ORDER_STATE_ID)
            || !Configuration::get(self::CONFIG_KEY_PRECO_SUCCESS_ORDER_STATE_ID)
            || !Configuration::get(self::CONFIG_KEY_PRECO_FAILED_ORDER_STATE_ID)
            || !Configuration::get('ALO_PRECO_PRODUCED_ORDER_STATE_ID')) {
            $module_configuration_url = $this->context->link->getAdminLink(
                'AdminModules',
                true,
                ['configure' => $this->name,],
                ['configure' => $this->name,]
            );

            $this->context->controller->informations[] = 'Merci de mapper les statuts de précommande dans la <a href="' . $module_configuration_url . '">page de configuration du module ' . $this->displayName . '</a>';
        }
    }

    /**
     * @param int $id_product
     * @return bool
     */
    public function isProductPreco(int $id_product): bool
    {
        return AloPrecoConfigProductAttribute::isProductPreco($id_product);
    }

    public function hookActionFrontControllerSetVariables(): array
    {
        return [
            'module' => $this,
        ];
    }

    /**
     * Si le panier va engendrer une commande splittée, on renvois "n X " avec n = nombre de livraisons
     * Pour afficher sur les choix de livraison
     * @param $params
     * @return string
     */
    public function displayNbDelivery($params): string
    {
        if (!array_key_exists('price_with_tax', $params)
            || ($params['price_with_tax'] === 0)) {
            return '';
        }

        $cart = $this->context->cart;

        if (!$this->isSplittedCart($cart)) {
            return '';
        }

        $delivery_option = $cart->getDeliveryOption();
        $nb_delivery = count($delivery_option);

        return $nb_delivery . ' X ';
    }

    /**
     * Est-ce que le panier va engendrer un split de commande ?
     */
    public function isSplittedCart(Cart $cart): bool
    {
        $delivery_option = $cart->getDeliveryOption();
        $nb_delivery = count($delivery_option);

        if (empty($delivery_option) || $nb_delivery <= 1) {
            return false;
        }

        return true;
    }

    public function isColorAttributePreco(int $id_product, int $id_attribute): bool
    {
        $attribute_data_list = Product::getAttributesColorList([$id_product], false);
        $attribute_list = $attribute_data_list[$id_product];

        foreach ($attribute_list as $attribute) {
            if ($attribute['id_attribute'] == $id_attribute) {

                $result = $this->isProductAttributePreco($attribute['id_product_attribute']);

                if ($result) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Renvois l'id attribut courant qui n'est pas dans le groupe blacklisté
     * @param array $group_list
     * @param array $blacklist_group_data
     * @return false|int
     */
    public function getSelectedAttribute(array $group_list, array $blacklist_group_data)
    {
        foreach ($group_list as $group_data) {
            if($group_data['name'] === $blacklist_group_data['name']) {
                continue;
            }

            foreach ($group_data['attributes'] as $id_attribute => $attribute_data) {
                if ($attribute_data['selected']) {
                    return (int)$id_attribute;
                }
            }
        }

        return false;
    }

    /**
     * Donne l'id de déclinaison dans product-variants.tpl, au niveau de la sélection des tailles
     * @param int $id_product
     * @param int $id_attribute
     * @param array $group_list
     * @param array $blacklist_group_data
     * @return int
     * @throws PrestaShopException
     */
    public function getIdProductAttribute(int $id_product, int $id_attribute, array $group_list, array $blacklist_group_data): int
    {
        $selected_attribute = $this->getSelectedAttribute($group_list, $blacklist_group_data);
        $id_attribute_list = [$id_attribute];

        if ($selected_attribute) {
            $id_attribute_list[] = $selected_attribute;
        }

        return Product::getIdProductAttributeByIdAttributes($id_product, $id_attribute_list);
    }

    /**
     * @param int $id_product_attribute
     * @return bool
     */
    public function isProductAttributePreco(int $id_product_attribute): bool
    {
        return AloPrecoConfigProductAttribute::isProductAttributePreco($id_product_attribute);
    }

    public function isSplitOrder($reference): bool
    {
        $referenceList = Order::getByReference($reference);

        $orderQty = count($referenceList);

        if (!$orderQty) {
            return false;
        }

        if ($orderQty > 1) {
            return true;
        } else {
            return false;
        }
    }


    public function getSplitOrders($reference)
    {

        $orderIdsList = Order::getByReference($reference);
        $splitOrders = [];

        foreach ($orderIdsList as $order) {
            $orderPresenter = new PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter();
            $splitOrders[] = $orderPresenter->present($order);
        }

        return $splitOrders;

    }

    public function isOrderPreco($order_id): bool
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . AloPrecoPreorder::TABLE_NAME . ' WHERE id_order = "' . pSQL($order_id) . '"';
        $result = Db::getInstance()->executeS($sql);

        if (!$result) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param int $id_product_attribute
     * @return AloPrecoConfig|AloPrecoConfigProductAttribute|false
     */
    public function getConfigPrecoFromIdProductAttribute(int $id_product_attribute)
    {
        try {
            $config_preco = AloPrecoConfigProductAttribute::getConfigPrecoByProductAttributeId($id_product_attribute);
        } catch (PrestaShopDatabaseException|PrestaShopException $e) {
            PrestaShopLogger::addLog(
                'Alo_Preco::getConfigPrecoFromIdProductAttribute - Erreur : ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                ProductAttribute::class,
                $id_product_attribute,
                true
            );

            return false;
        }

        if (!Validate::isLoadedObject($config_preco)) {
            return false;
        }

        return $config_preco;
    }

    /**
     * Converti une commande en précommande si elle est éligible
     * @param array $params
     * @return void
     * @throws PrestaShopException
     */
    public function hookActionValidateOrder(array $params): void
    {
        if (!array_key_exists('order', $params)
            || !($params['order'] instanceof Order)
            || !Validate::isLoadedObject($params['order'])) {
            return;
        }

        $order = $params['order'];
        $id_config = AloPrecoConfig::getIdFromIdOrder((int)$order->id);

        if ($id_config && !self::isSecondaryShopCustomerId((int)$order->id_customer)) {

            PrestaShopLogger::addLog(
                'ALO_PRECO - Conversion de la commande ' . $order->reference . ' en précommande',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
                null,
                Order::class,
                (int)$order->id,
                true
            );

            $alo_preorder = new AloPrecoPreorder();
            $alo_preorder->id_order = (int)$order->id;
            $alo_preorder->id_config = (int)$id_config;
            $alo_preorder->add();
        }
    }

    /**
     * L'id customer est-il un client d'une boutique secondaire ?
     * @param int $id_customer
     * @return bool
     */
    public static function isSecondaryShopCustomerId(int $id_customer): bool
    {
        $raw_shop_customer_id_list = Configuration::get('ALO_SHOP_CUSTOMER_ID');
        $shop_customer_id_list = explode(',', $raw_shop_customer_id_list);

        return in_array($id_customer, $shop_customer_id_list, false);
    }

    /**
     * S'il y a plusieurs produits dans le panier :
     * - On parcourt les produits,
     * - Pour chaque produit précommande, on crée une adresse custom et on met à jour cart_product.id_address_delivery
     * @param $params
     * @return void
     */
    public function hookActionCartSave($params): void
    {
        if (!array_key_exists('cart', $params)
            || !($params['cart'] instanceof Cart)
            || !Validate::isLoadedObject($params['cart'])) {
            return;
        }

        $cart = $params['cart'];
        // $product_data_list = $cart->getProducts(); // Renvois une erreur dans certaines conditions (?)
        try {
            $product_data_list = Db::getInstance()->executeS(
                (new DbQuery())
                    ->select('cp.id_product, cp.id_product_attribute, cp.id_address_delivery')
                    ->from('cart_product', 'cp')
                    ->where('cp.id_cart = ' . (int)$cart->id)
            );
        } catch (PrestaShopDatabaseException $e) {
            PrestaShopLogger::addLog(
                'Alo_Preco::hookActionCartSave - Erreur : ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Cart::class,
                (int)$cart->id,
                true
            );
            return;
        }

        if (empty($product_data_list) || count($product_data_list) === 1) {
            return;
        }

        foreach ($product_data_list as $key => $product_data) {
            if (!array_key_exists('id_product_attribute', $product_data)
                || (int)$product_data['id_product_attribute'] === 0
                || (int)$product_data['id_address_delivery'] === 0) {
                continue;
            }

            if ($this->isProductAttributePreco($product_data['id_product_attribute'])
                && count($product_data_list) > 1) {
                try {
                    $this->updateDeliveryAddress($cart, $product_data);
                } catch (PrestaShopException $e) {
                    PrestaShopLogger::addLog(
                        'Alo_Preco::hookActionCartSave - Erreur : ' . $e->getMessage(),
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                        null,
                        Cart::class,
                        $cart->id,
                        true
                    );
                    continue;
                }

                PrestaShopLogger::addLog(
                    'Alo_Preco::hookActionCartSave - Adresse de livraison mise à jour pour le produit ' . $product_data['id_product'] . ' - ' . $product_data['id_product_attribute'],
                    PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
                    null,
                    Cart::class,
                    $cart->id,
                    true);

                unset($product_data_list[$key]);
            }
        }
    }

    /**
     * Duplique l'adresse de livraison du client et met à jour cart_product.id_address_delivery
     * @param Cart $cart
     * @param $product_data
     * @return bool
     * @throws PrestaShopException
     */
    protected function updateDeliveryAddress(Cart $cart, $product_data): bool
    {
        if (!array_key_exists('id_address_delivery', $product_data)
            || (int)$product_data['id_address_delivery'] === 0) {
            throw new PrestaShopException(
                'Alo_Preco::updateDeliveryAddress - Erreur : cart_product.id_address_delivery est vide'
            );
        }

        $id_address_delivery = (int)$product_data['id_address_delivery'];
        $address = new Address($id_address_delivery);

        if (!Validate::isLoadedObject($address)) {
            throw new PrestaShopException(
                'Alo_Preco::updateDeliveryAddress - Erreur : Impossible de charger l\'adresse de livraison'
            );
        }

        $new_address = $address->duplicateObject();

        if (!Validate::isLoadedObject($new_address)) {
            throw new PrestaShopException(
                'Alo_Preco::updateDeliveryAddress - Erreur : Impossible de dupliquer l\'adresse de livraison'
            );
        }

        if ((int)$new_address->id_customer === 0) {
            // Ceci est déjà une adresse custom, tout va bien
            return true;
        }

        $new_address->id_customer = 0;
        $new_address->update();

        return Db::getInstance()->update(
            'cart_product',
            ['id_address_delivery' => (int)$new_address->id],
            'id_cart = ' . (int)$cart->id . ' AND id_product_attribute = ' . (int)$product_data['id_product_attribute']
        );
    }

    /**
     * Génère le panneau récap de la config préco pour l'admin
     * @param int $id_config
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getConfigPrecoAdminPanel(int $id_config): string
    {
        $alo_preco_config = new AloPrecoConfig($id_config);

        $this->context->smarty->assign([
            'alo_preco_config' => $alo_preco_config,
        ]);

        return (string)$this->context->smarty->fetch(
            $this->getLocalPath() . 'views/templates/admin/config_preco_panel.tpl'
        );
    }

    /**
     * Fix d'un bug lors des mises à jour des quantités lorsque les adresses sont différentes :
     * On remet l'adresse de livraison de base avant de mettre à jour les quantités
     * @param $params
     * @return void
     * @throws PrestaShopDatabaseException
     */
    public function hookActionCartUpdateQuantityBefore($params): void
    {
        if (!array_key_exists('cart', $params)
            || !($params['cart'] instanceof Cart)
            || !Validate::isLoadedObject($params['cart'])
            || !array_key_exists('id_product_attribute', $params)
        ) {
            return;
        }

        $id_product_attribute_list = Db::getInstance()->executeS(
            (new DbQuery())
                ->select('id_product_attribute')
                ->from('cart_product')
                ->where('id_cart = ' . (int)$params['cart']->id)
        );

        foreach ($id_product_attribute_list as $id_product_attribute) {
            if (!$this->isProductAttributePreco($id_product_attribute['id_product_attribute'])) {
                continue;
            }

            Db::getInstance()->update(
                'cart_product',
                ['id_address_delivery' => (int)Address::getFirstCustomerAddressId((int)$params['cart']->id_customer)],
                'id_cart = ' . (int)$params['cart']->id . ' '
                . 'AND id_product_attribute = ' . (int)$id_product_attribute['id_product_attribute']
            );
        }
    }

    /**
     * Met en place le statut "Précommande en cours" pour les précommandes qui viennent d'être créées
     * @param $params
     * @return void
     */
    public function hookActionObjectOrderUpdateAfter($params): void
    {
        $id_preco_order_state = (int)Configuration::get(self::CONFIG_KEY_PRECO_ORDER_STATE_ID);

        try {
            if (!array_key_exists('object', $params)
                || !($params['object'] instanceof Order)
                || !Validate::isLoadedObject($params['object'])
                || !($this->hasHistory($params['object']->id))
                || $this->hasOrderStatusInHistory((int)$params['object']->id, (int)Configuration::get(self::CONFIG_KEY_PRECO_ORDER_STATE_ID))
                || in_array($id_preco_order_state, $this->getIdOrderStateList((int)$params['object']->id), true)
            ) {
                return;
            }
        } catch (PrestaShopDatabaseException $e) {
            PrestaShopLogger::addLog(
                'Alo_Preco::hookActionObjectOrderUpdateAfter - Erreur : ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                (int)$params['object']->id,
                true
            );
            return;
        }

        try {
            $preorder = AloPrecoPreorder::getPrecoFromOrderId((int)$params['object']->id);
        } catch (PrestaShopDatabaseException|PrestaShopException $e) {
            PrestaShopLogger::addLog(
                'Alo_Preco::hookActionObjectOrderUpdateAfter - Erreur : ' . $e->getMessage(),
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                (int)$params['object']->id,
                true
            );
            return;
        }

        if (!Validate::isLoadedObject($preorder)) {
            return;
        }

        $params['object']->setCurrentState(Configuration::get(self::CONFIG_KEY_PRECO_ORDER_STATE_ID));
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public function hasHistory($id_order): bool
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'order_history WHERE id_order = "' . pSQL($id_order) . '"';
        $result = Db::getInstance()->executeS($sql);

        if (empty($result)) {
            return false;
        }

        return true;
    }

    public function hasOrderStatusInHistory(int $id_order, int $id_order_state): bool
    {
        $sql = (new DbQuery())
            ->select('id_order_history')
            ->from('order_history')
            ->where('id_order = ' . pSQL($id_order) . ' AND id_order_state = ' . pSQL($id_order_state));

        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * Renvois la liste des id_order_state d'une commande
     * @param int $id_order
     * @return int[]
     * @throws PrestaShopDatabaseException
     */
    protected function getIdOrderStateList(int $id_order): array
    {
        $sql = (new DbQuery())
            ->select('id_order_state')
            ->from('order_history')
            ->where('id_order = ' . pSQL($id_order));

        $result_list = (array)Db::getInstance()->executeS($sql);
        $id_order_state_list = [];

        foreach ($result_list as $key => $result_data) {
            $id_order_state_list[] = (int)$result_data['id_order_state'];
        }

        return $id_order_state_list;
    }

    /**
     * Si le panier va engendrer une commande splittée, on splitte la clé shipping pour détailler les frais de port
     * @param array $params
     * @return void
     */
    public function hookActionPresentCart(array $params): void
    {
        if (!array_key_exists('cart', $params)
            || !($params['cart'] instanceof Cart)
            || !Validate::isLoadedObject($params['cart'])) {
            return;
        }

        $delivery_option = $params['cart']->getDeliveryOption();
        $nb_delivery = count($delivery_option);

        if (empty($delivery_option) || $nb_delivery <= 1) {
            return;
        }

        $shipping_part = $params['presentedCart']['subtotals']['shipping'];
        $shipping_part['amount'] = $params['presentedCart']['subtotals']['shipping']['amount'] / $nb_delivery;
        $shipping_part['value'] = Context::getContext()->getCurrentLocale()->formatPrice(
            $shipping_part['amount'],
            Currency::getIsoCodeById((int)$params['cart']->id_currency)
        );

        for ($i = 0; $i < $nb_delivery; $i++) {
            $shipping_part['label'] = $params['presentedCart']['subtotals']['shipping']['label'] . ' (commande ' . ($i + 1) . '/' . $nb_delivery . ')';
            $params['presentedCart']['subtotals']['shipping_' . $i] = $shipping_part;
        }

        unset($params['presentedCart']['subtotals']['shipping']);
    }

    public function getSummaryTable(array $preorder_list)
    {
        $product_list = [];
        foreach ($preorder_list as $preorder_data) {
            $product_list[$preorder_data['product_attribute_id']] = $preorder_data['product_name'];
        }

        $html_table_list = [];

        foreach ($product_list as $product_attribute_id => $product_attribute_name) {
            $this->context->smarty->assign([
                'product_attribute_id' => $product_attribute_id,
                'product_attribute_name' => $product_attribute_name,
                'preorder_list' => $preorder_list,
            ]);

            $html_table_list[] = $this->context->smarty->fetch(
                $this->getLocalPath() . 'views/templates/admin/preorder_table.tpl'
            );
        }

        $this->context->smarty->assign([
            'html_table_list' => $html_table_list,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/preorder_table_panel.tpl'
        );
    }
}
