<?php
/**
 * 2007-2018 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

require_once
    _PS_MODULE_DIR_ . 'alo_preco'
    . DIRECTORY_SEPARATOR . 'classes'
    . DIRECTORY_SEPARATOR . 'AloPrecoPreorder.php';
require
    _PS_MODULE_DIR_ . 'alo_preco'
    . DIRECTORY_SEPARATOR . 'classes'
    . DIRECTORY_SEPARATOR . 'AloPrecoConfig.php';

class AdminAloPrecoPreorderController extends ModuleAdminController
{
    protected $id_config = null;
    protected $config = null;
    protected $csvColumnMapping = [];

    /**
     * Instanciation de la classe
     * Définition des paramètres basiques obligatoires
     * Modification de la requête pour récupérer les entités
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true; //Gestion de l'affichage en mode bootstrap
        $this->table = AloPrecoPreorder::TABLE_NAME; //Table de l'objet
        $this->identifier = AloPrecoPreorder::PRIMARY_KEY; //Clé primaire de l'objet
        $this->className = AloPrecoPreorder::class; //Classe de l'objet
        $this->lang = false; //Flag pour dire si utilisation de langues ou non
        $this->list_simple_header = false;
        $this->list_no_link = true;

        if (Tools::getValue(AloPrecoConfig::PRIMARY_KEY) && Tools::getValue(AloPrecoConfig::PRIMARY_KEY) > 0) {
            $this->id_config = (int)Tools::getValue(AloPrecoConfig::PRIMARY_KEY);
            $this->config = new AloPrecoConfig($this->id_config);
        }

        parent::__construct();

        //Liste des champs de l'objet à afficher dans la liste
        $this->fields_list = $this->getFieldsList();

        $this->_select =
            'CONCAT("[#", a.id_order, "] ", o.reference) AS order_data, '
            . 'o.current_state, '
            . 'CONCAT("[#", o.current_state, "] ", osl.name) AS current_state_data, '
            . 'os.color AS order_state_color,'
            . 'a.id_order,'
            . 'CONCAT("[#", a.' . AloPrecoConfig::PRIMARY_KEY . ', "] ", apc.name) AS config_preco_data, '
            . 'apc.date_begin AS preco_config_date_begin, '
            . 'apc.date_end AS preco_config_date_end, '
            . 'a.' . AloPrecoConfig::PRIMARY_KEY . ', '
            . 'a.date_add AS preorder_date_add, '
            . 'apc.name AS preco_config_name, '
            . 'p.id_manufacturer, '
            . 'm.name AS manufacturer_name, '
            . 'CONCAT("[#", od.product_id, "-", od.product_attribute_id, "] [", od.product_reference, "] ", od.product_name) AS product_data, '
            . 'od.product_name, '
            . 'od.product_quantity, '
            . 'od.product_id, '
            . 'od.product_attribute_id, '
            . 'od.product_reference, '
            . 'pac_color.id_attribute AS id_attribute_color, '
            . 'al_color.name AS color_name, '
            . 'pac_size.id_attribute AS id_attribute_size, '
            . 'al_size.name AS size_name, '
            . 'CONCAT(c.firstname, " ", c.lastname) AS customer_name, '
            . 'c.email AS customer_email, '
            . 'col.name AS country_name, '
            . 'cal.delay AS carrier_name, ';

        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . AloPrecoConfig::TABLE_NAME . ' apc ON (a.' . AloPrecoConfig::PRIMARY_KEY . ' = apc.' . AloPrecoConfig::PRIMARY_KEY . ') ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'order_detail od ON (a.id_order = od.id_order) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'orders o ON (a.id_order = o.id_order) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'order_state_lang osl ON (o.current_state = osl.id_order_state AND osl.id_lang = ' . $this->context->language->id . ') ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'order_state os ON (o.current_state = os.id_order_state) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'product p ON (od.product_id = p.id_product) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (p.id_manufacturer = m.id_manufacturer) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'customer c ON (o.id_customer = c.id_customer) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'address ad ON (o.id_address_delivery = ad.id_address) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'country_lang col ON (ad.id_country = col.id_country AND col.id_lang = ' . $this->context->language->id . ') ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'carrier_lang cal ON (o.id_carrier = cal.id_carrier AND cal.id_lang = ' . $this->context->language->id . ') ';

        $this->_join .=
            'LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac_color ON ('
            . 'od.product_attribute_id = pac_color.id_product_attribute '
            . 'AND pac_color.id_attribute IN ('
            . 'SELECT id_attribute '
            . 'FROM ' . _DB_PREFIX_ . 'attribute '
            . 'LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group ON (' . _DB_PREFIX_ . 'attribute.id_attribute_group = ' . _DB_PREFIX_ . 'attribute_group.id_attribute_group) '
            . 'WHERE ' . _DB_PREFIX_ . 'attribute_group.is_color_group = 1'
            . ')'
            . ') ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al_color ON (pac_color.id_attribute = al_color.id_attribute AND al_color.id_lang = ' . $this->context->language->id . ') ';

        $this->_join .=
            'LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac_size ON ('
            . 'od.product_attribute_id = pac_size.id_product_attribute '
            . 'AND pac_size.id_attribute IN ('
            . 'SELECT id_attribute '
            . 'FROM ' . _DB_PREFIX_ . 'attribute '
            . 'LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group ON (' . _DB_PREFIX_ . 'attribute.id_attribute_group = ' . _DB_PREFIX_ . 'attribute_group.id_attribute_group) '
            . 'WHERE ' . _DB_PREFIX_ . 'attribute_group.is_color_group = 0'
            . ')'
            . ') ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al_size ON (pac_size.id_attribute = al_size.id_attribute AND al_size.id_lang = ' . $this->context->language->id . ') ';

        if ($this->id_config > 0) {
            $this->_where = 'AND apc.' . AloPrecoConfig::PRIMARY_KEY . ' = ' . Tools::getValue(AloPrecoConfig::PRIMARY_KEY);
        }

        if ($this->id_config > 0) {
            $this->page_header_toolbar_title = $this->l('Commande en précommandes pour') . ' [#' . $this->config->id . '] ' . $this->config->name;
        }

        $this->csvColumnMapping = [
            [
                'column_name' => 'id_order',
                'column_header' => $this->module->l('Id de commande'),
            ],
            [
                'column_name' => AloPrecoConfig::PRIMARY_KEY,
                'column_header' => $this->module->l('Id préco'),
            ],
            [
                'column_name' => 'preco_config_date_begin',
                'column_header' => $this->module->l('Date de début de la préco'),
            ],
            [
                'column_name' => 'preco_config_date_end',
                'column_header' => $this->module->l('Date de fin de la préco'),
            ],
            [
                'column_name' => 'preorder_date_add',
                'column_header' => $this->module->l('Date de l\'ajout'),
            ],
            [
                'column_name' => 'preco_config_name',
                'column_header' => $this->module->l('Nom de la précommande'),
            ],
            [
                'column_name' => 'manufacturer_name',
                'column_header' => $this->module->l('Marque'),
            ],
            [
                'column_name' => 'product_name',
                'column_header' => $this->module->l('Nom du produit'),
            ],
            [
                'column_name' => 'product_reference',
                'column_header' => $this->module->l('Référence du produit'),
            ],
            [
                'column_name' => 'product_quantity',
                'column_header' => $this->module->l('Quantité précommandé'),
            ],
            [
                'column_name' => 'product_id',
                'column_header' => $this->module->l('Id du produit'),
            ],
            [
                'column_name' => 'product_attribute_id',
                'column_header' => $this->module->l('Id de déclinaison du produit'),
            ],
            [
                'column_name' => 'color_name',
                'column_header' => $this->module->l('Nom de la couleur'),
            ],
            [
                'column_name' => 'size_name',
                'column_header' => $this->module->l('Nom de taille'),
            ],
            [
                'column_name' => 'customer_name',
                'column_header' => $this->module->l('Nom de l\'acheteur'),
            ],
            [
                'column_name' => 'customer_email',
                'column_header' => $this->module->l('Email de l\'acheteur'),
            ],
            [
                'column_name' => 'country_name',
                'column_header' => $this->module->l('Pays de livraison'),
            ],
            [
                'column_name' => 'carrier_name',
                'column_header' => $this->module->l('Livraison'),
            ],
        ];
    }

    protected function getFieldsList()
    {
        $field_list = [
            'order_data' => [
                'title' => $this->module->l('Commande'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-xl',
                'havingFilter' => true,
                'callback' => 'callbackDisplayOrder',
            ],
            'current_state_data' => [
                'title' => $this->module->l('Statut de commande'),
                'orderby' => true,
                'search' => false,
                'align' => 'left',
                'class' => 'fixed-width-xxl',
                'havingFilter' => true,
                'callback' => 'callbackDisplayOrderState',
            ],
            'config_preco_data' => [
                'title' => $this->module->l('Config préco'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-xl',
                'havingFilter' => true,
                'callback' => 'callbackDisplayConfigPreco',
            ],
            'product_data' => [
                'title' => $this->module->l('Produit'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-xl',
                'havingFilter' => true,
                'callback' => 'callbackDisplayProduct',
            ],
            'product_quantity' => [
                'title' => $this->module->l('Quantité précommandé'),
                'orderby' => true,
                'search' => true,
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
        ];

        if (Validate::isLoadedObject($this->config)
            && ($this->config->hasRefundedPreorders()
                || ($this->config->isClosed() && !$this->config->isSuccessful())
            )
        ) {
            $field_list['is_refunded'] = [
                'title' => $this->module->l('Remboursé'),
                'orderby' => true,
                'search' => true,
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'type' => 'bool',
            ];
        }

        $field_list['date_add'] = [
            'title' => $this->module->l('Date de commande'),
            'orderby' => true,
            'search' => false,
            'align' => 'right',
            'class' => 'fixed-width-xl',
            'callback' => 'callbackDisplayDate',
        ];

        return $field_list;
    }

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
        self::$currentIndex .= '&' . AloPrecoConfig::PRIMARY_KEY . '=' . Tools::getValue(AloPrecoConfig::PRIMARY_KEY);
    }

    public function callbackDisplayOrder($order_data, $row)
    {
        $order_link = $this->context->link->getAdminLink(
            'AdminOrders',
            true,
            [
                'vieworder' => '',
                'id_order' => $row['id_order'],
            ]
        );

        return '<a href="' . $order_link . '" target="_blank">' . $order_data . '</a>';
    }

    public function callbackDisplayConfigPreco($config_preco_data, $row)
    {
        $config_preco_link = $this->context->link->getAdminLink(
            'AdminAloPrecoConfig',
            true,
            [
                'view' . AloPrecoConfig::TABLE_NAME => true,
                AloPrecoConfig::PRIMARY_KEY => $row[AloPrecoConfig::PRIMARY_KEY],
            ],
            [
                'view' . AloPrecoConfig::TABLE_NAME => true,
                AloPrecoConfig::PRIMARY_KEY => $row[AloPrecoConfig::PRIMARY_KEY],
            ]
        );

        return '<a href="' . $config_preco_link . '" target="_blank">' . $config_preco_data . '</a>';
    }

    /**
     * @throws PrestaShopException
     */
    public function callbackDisplayDate($date, $row): string
    {
        return Tools::displayDate($date);
    }

    public function callbackDisplayProduct($product_data, $row)
    {
        $product_link = $this->context->link->getAdminLink(
            'AdminProducts',
            true,
            [
                'viewproduct' => '',
                'id_product' => $row['product_id'],
            ]
        );

        return '<a href="' . $product_link . '#tab-step3" target="_blank">'
            . '[#' . $row['product_id'] . '-' . $row['product_attribute_id'] . '] '
            . '[' . $row['product_reference'] . '] '
            . '</a><br> '
            . $row['product_name'];
    }

    public function callbackDisplayOrderState($order_state_data, $row)
    {
        return '<span style="background-color: ' . $row['order_state_color'] . ';color:white;border-radius:3px;padding:3px 6px;font-weight:600;">' . $order_state_data . '</span>';
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitChangeOrderStateForm')) {
            $id_config = (int)Tools::getValue('id_config');
            $idNewOrderState = (int)Tools::getValue('id_order_state');
            $excludedOrderStates = (array)Tools::getValue('id_order_state_except');
            $excludedStatusIDs = array_map('intval', $excludedOrderStates);

            $alo_preco_preorder = new AloPrecoPreorder();

            $ordersWithConfigPreco = $alo_preco_preorder->getOrdersWithConfigPreco($id_config);

            foreach ($ordersWithConfigPreco as $order) {
                $currentOrderStatus = (int)$order['current_state'];

                if (!in_array($currentOrderStatus, $excludedStatusIDs)) {
                    $orderObj = new Order($order['id_order']);
                    $orderObj->setCurrentState($idNewOrderState);
                }
            }

            $this->confirmations[] = $this->l('Statuts des précommandes mis à jour avec succès.');
        }

        if (Tools::isSubmit('bulk_refund') && !$this->config->hasRefundedPreorders()) {
            try {
                $this->processBulkRefund();
            } catch (PrestaShopException $e) {
                $this->errors[] = $e->getMessage();
                PrestaShopLogger::addLog(
                    'AdminAloPrecoPreorderController::postProcess : ' . $e->getMessage(),
                    PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                    null,
                    Order::class,
                    null,
                    true
                );
            }
        }

        if (Tools::isSubmit('getCSV')
            && Tools::isSubmit('product_attribute_id')
            && Tools::getValue('getCSV')) {
            $this->getList($this->context->language->id);

            $list = $this->formatDataForCsv();

            $this->arrayToCsvDownload(
                $list, // this array is going to be the second row
                'export_preco_' . $this->id_config . '_product_' . Tools::getValue('product_attribute_id') . '_' . date('Y-m-d') . '.csv'
            );

            die();
        }

        return parent::postProcess();
    }

    /**
     * @throws PrestaShopException
     */
    protected function processBulkRefund(): void
    {
        $preorder_list = $this->config->getPreorderList();

        foreach ($preorder_list as $preorder) {
            if ($preorder->isPsOrderCancelled()
                || !$this->processRefundOrder($preorder)
            ) {
                continue;
            }

            $preorder->is_refunded = true;
            $preorder->save();
            $this->informations[] = $this->l('Précommande ') . $preorder->getOrderReference() . $this->l(' remboursée avec succès.');
        }
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function processRefundOrder(AloPrecoPreorder $preorder)
    {
        $id_order = $preorder->getIdOrder();
        $orderPayPalId = $preorder->getIdOrderPayPal();
        $transactionPayPalId = $preorder->getIdTransactionPayPal();
        $amount = $preorder->getPaymentAmount();
        $currency = $preorder->getCurrencyIsoCode();

        if (empty($orderPayPalId) || false === Validate::isGenericName($orderPayPalId)) {
            PrestaShopLogger::addLog(
                'AdminAloPrecoPreorderController::processRefundOrder : PayPal Order is invalid.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                $id_order,
                true
            );
            $this->errors[] = 'Commande ' . $preorder->getOrderReference() . ' : Id de commande PayPal invalide.';
            return false;
        }

        if (empty($transactionPayPalId) || false === Validate::isGenericName($transactionPayPalId)) {
            PrestaShopLogger::addLog(
                'AdminAloPrecoPreorderController::processRefundOrder : PayPal Transaction is invalid.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                $id_order,
                true
            );
            $this->errors[] = 'Commande ' . $preorder->getOrderReference() . ' : Id de transaction PayPal invalide.';
            return false;
        }

        if (empty($amount) || false === Validate::isPrice($amount) || $amount <= 0) {
            PrestaShopLogger::addLog(
                'AdminAloPrecoPreorderController::processRefundOrder : PayPal refund amount is invalid.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                $id_order,
                true
            );
            $this->errors[] = 'Commande ' . $preorder->getOrderReference() . ' : Montant de remboursement invalide.';
            return false;
        }

        if (empty($currency) || false === in_array($currency, ['AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD'])) {
            // https://developer.paypal.com/docs/api/reference/currency-codes/
            PrestaShopLogger::addLog(
                'AdminAloPrecoPreorderController::processRefundOrder : PayPal refund currency is invalid.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                null,
                Order::class,
                $id_order,
                true
            );
            $this->errors[] = 'Commande ' . $preorder->getOrderReference() . ' : Devise de remboursement invalide.';
            return false;
        }

        /** @var PrestaShop\Module\PrestashopCheckout\PayPal\PayPalConfiguration $configurationPayPal */
        $ps_checkout = Module::getInstanceByName('ps_checkout');
        $configurationPayPal = $ps_checkout->getService('ps_checkout.paypal.configuration');

        $response = (new PrestaShop\Module\PrestashopCheckout\Api\Payment\Order($this->context->link))->refund([
            'orderId' => $orderPayPalId,
            'captureId' => $transactionPayPalId,
            'payee' => [
                'merchant_id' => $configurationPayPal->getMerchantId(),
            ],
            'amount' => [
                'currency_code' => $currency,
                'value' => $amount,
            ],
            'note_to_payer' => 'Refund by '
                . Configuration::get(
                    'PS_SHOP_NAME',
                    null,
                    null,
                    (int)Context::getContext()->shop->id
                ),
        ]);

        if (isset($response['httpCode']) && $response['httpCode'] === 200) {
            /** @var CacheInterface $paypalOrderCache */
            $paypalOrderCache = $ps_checkout->getService('ps_checkout.cache.paypal.order');
            if ($paypalOrderCache->has($orderPayPalId)) {
                $paypalOrderCache->delete($orderPayPalId);
            }

            PrestaShopLogger::addLog(
                'AdminAloPrecoPreorderController::processRefundOrder : PayPal refund has been processed.',
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE,
                null,
                Order::class,
                $id_order,
                true
            );
            return true;
        }

        PrestaShopLogger::addLog(
            'AdminAloPrecoPreorderController::processRefundOrder : PayPal refund cannot be processed.',
            PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
            null,
            Order::class,
            $id_order,
            true
        );
        $this->errors[] = 'Commande ' . $preorder->getOrderReference() . ' : Remboursement PayPal impossible.';

        return false;
    }

    /**
     * Ajoute l'en-tête du fichier CSV, défini avec $this->csvColumnMapping
     * Filtre la liste $this->_list pour ne garder que les colonnes définies dans $this->csvColumnMapping
     * Filtre la liste $this->_list pour ne garder que les lignes dont le produit est celui défini par Tools::getValue('product_attribute_id')
     * Filtre la liste $this->_list pour ne pas garder les commandes annulées
     * Ajoute le footer avec le total des quantités
     * @return array
     */
    protected function formatDataForCsv()
    {
        $output_list = [];
        $filtered_list = [];

        // Ajoute l'en-tête du fichier CSV, défini avec $this->csvColumnMapping
        $output_list[] = array_column($this->csvColumnMapping, 'column_header');

        // Filtre la liste $this->_list pour ne garder que les colonnes définies dans $this->csvColumnMapping
        $filtered_list = array_map(function ($row) {
            $formatted_row = [];
            foreach ($this->csvColumnMapping as $column) {
                $column_name = $column['column_name'];
                $formatted_row[$column_name] = $row[$column_name];
            }
            return $formatted_row;
        }, $this->_list);

        // Filtre la liste $this->_list pour ne garder que les lignes dont le produit est celui défini par Tools::getValue('product_attribute_id')
        if (Tools::getValue('product_attribute_id')) {
            $filtered_list = array_filter($filtered_list, function ($row) {
                return $row['product_attribute_id'] == Tools::getValue('product_attribute_id');
            });
        }

        // Filtre la liste $this->_list pour ne pas garder les commandes annulées
        $filtered_list = array_filter($filtered_list, function ($row) {
            return $row['current_state'] != Configuration::get('PS_OS_CANCELED');
        });

        // Ajoute le footer avec le total des quantités
        $filtered_list[] = [
            'head' => $this->module->l('Total'),
            'product_quantity' => array_sum(array_column($filtered_list, 'product_quantity')),
        ];

        // Merge du header
        return array_merge($output_list, $filtered_list);
    }

    public function arrayToCsvDownload($array, $filename = "export.csv", $delimiter = ";")
    {
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        // open the "output" stream
        // see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
        $f = fopen('php://output', 'wb');

        foreach ($array as $line) {
            fputcsv($f, $line, $delimiter);
        }
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function renderList(): string
    {
        $config_preco_panel = '';

        if ($this->id_config > 0) {
            $config_preco_panel = $this->module->getConfigPrecoAdminPanel($this->id_config);
        }

        $output = '<br>';
        $output .= $config_preco_panel;
        $output .= $this->renderForm();
        $output .= parent::renderList();
        $output .= $this->module->getSummaryTable($this->_list);

        return $output;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function renderForm(): string
    {
        $config_preco_list = AloPrecoConfig::getAllData();
        $order_status_list = OrderState::getOrderStates($this->context->language->id);

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Changement groupé du statut de commande'),
                    'icon' => 'icon-cogs',
                ),
                'input' => [],
                'submit' => [
                    'title' => $this->l('Appliquer'),
                ],
            ),
        );

        if ($this->id_config > 0) {
            $fields_form['form']['input'][] = [
                'type' => 'hidden',
                'name' => AloPrecoConfig::PRIMARY_KEY,
                'value' => $this->id_config,
            ];
        } else {
            $fields_form['form']['input'][] = [
                'type' => 'select',
                'label' => 'Précommande à modifier :',
                'name' => AloPrecoConfig::PRIMARY_KEY,
                'class' => 'chosen',
                'multiple' => false,
                'options' => [
                    'query' => $config_preco_list,
                    'id' => AloPrecoConfig::PRIMARY_KEY,
                    'name' => 'name',
                ],
            ];
        }

        $fields_form['form']['input'][] = [
            'type' => 'select',
            'label' => 'Statut de commande à attribuer :',
            'name' => 'id_order_state',
            'class' => 'chosen',
            'multiple' => false,
            'options' => [
                'query' => $order_status_list,
                'id' => 'id_order_state',
                'name' => 'name',
            ],
        ];

        $fields_form['form']['input'][] = [
            'type' => 'select',
            'label' => 'Statut(s) de commande à exclure :',
            'name' => 'id_order_state_except',
            'class' => 'chosen',
            'multiple' => true,
            'desc' => $this->module->l('Les commandes avec ces statuts ne seront pas modifiées. Vous ne voulez probablement pas modifier les commandes annulées.'),
            'options' => [
                'query' => $order_status_list,
                'id' => 'id_order_state',
                'name' => 'name',
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitChangeOrderStateForm';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminAloPrecoPreorder', false);
        $helper->token = Tools::getAdminTokenLite('AdminAloPrecoPreorder');

        $helper->tpl_vars = [
            'fields_value' => [
                AloPrecoConfig::PRIMARY_KEY => (int)$this->id_config,
                'id_order_state' => 0,
                'id_order_state_except[]' => [6],
            ],
        ];

        return $helper->generateForm(array($fields_form));
    }

    /**
     * assign default action in toolbar_btn smarty var, if they are not set.
     * uses override to specifically add, modify or remove items.
     */
    public function initToolbar()
    {
        switch ($this->display) {
            case 'add':
            case 'edit':
                // Default save button - action dynamically handled in javascript
                $this->toolbar_btn['save'] = [
                    'href' => '#',
                    'desc' => $this->trans('Save', [], 'Admin.Actions'),
                ];
                $back = Tools::safeOutput(Tools::getValue('back', ''));
                if (empty($back)) {
                    $back = self::$currentIndex . '&token=' . $this->token;
                }
                if (!Validate::isCleanHtml($back)) {
                    die(Tools::displayError());
                }
                if (!$this->lite_display) {
                    $this->toolbar_btn['cancel'] = [
                        'href' => $back,
                        'desc' => $this->trans('Cancel', [], 'Admin.Actions'),
                    ];
                }

                break;
            case 'view':
                // Default cancel button - like old back link
                $back = Tools::safeOutput(Tools::getValue('back', ''));
                if (empty($back)) {
                    $back = self::$currentIndex . '&token=' . $this->token;
                }
                if (!Validate::isCleanHtml($back)) {
                    die(Tools::displayError());
                }
                if (!$this->lite_display) {
                    $this->toolbar_btn['back'] = [
                        'href' => $back,
                        'desc' => $this->trans('Back to list', [], 'Admin.Actions'),
                    ];
                }

                break;
            case 'options':
                $this->toolbar_btn['save'] = [
                    'href' => '#',
                    'desc' => $this->trans('Save', [], 'Admin.Actions'),
                ];

                break;
            default:
                // list
                $this->toolbar_btn['custom'] = [
                    'href' => '#',
                    'desc' => '',
                    'class' => 'pull-right',
                ];
                if ($this->allow_export) {
                    $this->toolbar_btn['export'] = [
                        'href' => self::$currentIndex . '&export' . $this->table . '&token=' . $this->token,
                        'desc' => $this->trans('Export', [], 'Admin.Actions'),
                    ];
                }
        }
    }
}
