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
    . DIRECTORY_SEPARATOR . 'AloPrecoConfigProductAttribute.php';

require_once
    _PS_MODULE_DIR_ . 'alo_preco'
    . DIRECTORY_SEPARATOR . 'classes'
    . DIRECTORY_SEPARATOR . 'AloPrecoConfig.php';

class AdminAloPrecoConfigProductAttributeController extends ModuleAdminController
{
    protected $id_config;

    /**
     * Instanciation de la classe
     * Définition des paramètres basiques obligatoires
     * Modification de la requête pour récupérer les entités
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true; //Gestion de l'affichage en mode bootstrap
        $this->table = AloPrecoConfigProductAttribute::TABLE_NAME; //Table de l'objet
        $this->identifier = AloPrecoConfigProductAttribute::PRIMARY_KEY; //Clé primaire de l'objet
        $this->className = AloPrecoConfigProductAttribute::class; //Classe de l'objet
        $this->lang = false; //Flag pour dire si utilisation de langues ou non
        $this->list_simple_header = false;
        $this->list_no_link = true;
        $this->addRowAction('delete');

        parent::__construct();

        if (!Tools::isSubmit(AloPrecoConfig::PRIMARY_KEY)) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminAloPrecoConfig'));
        }

        $this->id_config = (int)Tools::getValue(AloPrecoConfig::PRIMARY_KEY);

        //Liste des champs de l'objet à afficher dans la liste
        $this->fields_list = [
            'id_product' => [
                'title' => $this->module->l('ID produit'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-md',
                'filter_key' => 'pa!id_product',
                'callback' => 'callbackDisplayProduct',
            ],
            'id_product_attribute' => [
                'title' => $this->module->l('ID déclinaison'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-md',
                'filter_key' => 'a!id_product_attribute',
                'callback' => 'callbackDisplayProductAttribute',
            ],
            'reference' => [
                'title' => $this->module->l('Référence'),
                'orderby' => true,
                'search' => true,
                'align' => 'left',
                'class' => 'fixed-width-xl',
            ],
            'name' => [
                'title' => $this->module->l('Nom'),
                'orderby' => true,
                'search' => true,
                'width' => 'auto',
                'align' => 'left',
            ],
            'default_on' => [
                'title' => $this->module->l('Décli par défaut'),
                'orderby' => false,
                'search' => false,
                'width' => 'auto',
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'active' => 'status',
            ],
            'date_add' => [
                'title' => $this->module->l('Date d\'ajout'),
                'orderby' => true,
                'search' => false,
                'align' => 'right',
                'class' => 'fixed-width-xl',
                'callback' => 'callbackDisplayDate',
            ],
        ];

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', [], 'Admin.Actions'),
                'icon' => 'icon-trash',
                'confirm' => $this->trans('Delete selected items?', [], 'Admin.Notifications.Warning'),
            ],
        ];

        $this->fields_form = $this->getConfigForm();
        $alo_preco_config = new AloPrecoConfig($this->id_config);
        $this->_select = 'pa.id_product, pa.reference, a.id_product_attribute, a.date_add, pl.name, pa.default_on';
        $this->_join = 'LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON (a.id_product_attribute = pa.id_product_attribute) ';
        $this->_join .= 'LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pa.id_product = pl.id_product AND pl.id_lang = ' . $this->context->language->id . ') ';
        $this->_where = 'AND ' . AloPrecoConfig::PRIMARY_KEY . ' = ' . (int)$alo_preco_config->id;
        $this->page_header_toolbar_title = $this->l('Produits de la précommande') . ' [#' . $alo_preco_config->id . '] ' . $alo_preco_config->name;
    }

    /**
     * @return array
     */
    protected function getConfigForm(): array
    {
        $product_data_list = Product::getProducts(
            $this->context->language->id,
            0,
            0,
            'name',
            'ASC'
        );

        foreach ($product_data_list as $key => $product) {
            $product_data_list[$key]['name'] =
                $product['name'] . ' [' . $product['reference'] . '] [#' . $product['id_product'] . ']';
        }

        $config_form = [
            'legend' => [
                'title' => $this->l('Ajout de produits'),
                'icon' => 'icon-link',
            ],
            'input' => [
                [
                    'type' => 'hidden',
                    'name' => 'id_config',
                    'value' => $this->id_config,
                ],
                [
                    'type' => 'select',
                    'label' => 'Produit',
                    'name' => 'id_product',
                    'class' => 'chosen',
                    'multiple' => false,
                    'options' => [
                        'query' => $product_data_list,
                        'id' => 'id_product',
                        'name' => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Ajouter'),
            ],
        ];

        $radio_input = [
            'type' => 'radio',
            'label' => 'Déclinaison à passer en précommande',
            'name' => 'id_attribute',
            'form_group_class' => 'alo_select_id_attribute_container',
        ];

        $radio_input['values'] = $this->module->getColorAttributeOptions(
            (int)Tools::getValue('id_product'),
            'ARRAY'
        );

        $config_form['input'][] = $radio_input;

        return $config_form;
    }

    /**
     * @throws PrestaShopException
     */
    public function callbackDisplayDate($date, $row): string
    {
        return Tools::displayDate($date);
    }

    /**
     * @param $id
     * @param $row
     * @return string
     */
    public function callbackDisplayProductAttribute($id, $row): string
    {
        return $this->callbackDisplayProduct($id, $row);
    }

    /**
     * @param $id
     * @param $row
     * @return string
     */
    public function callbackDisplayProduct($id, $row): string
    {
        $product_link = $this->context->link->getAdminLink(
            'AdminProducts',
            true,
            ['id_product' => $row['id_product']],
            ['id_product' => $row['id_product']]
        );

        return '<a href="' . $product_link . '#tab-step3" target="blank">#' . $id . '</a>';
    }

    /**
     * @return void
     */
    public function init(): void
    {
        parent::init();
        self::$currentIndex .= '&id_config=' . Tools::getValue(AloPrecoConfig::PRIMARY_KEY);
    }

    /**
     * @return AloPrecoConfigProductAttribute
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function processStatus(): AloPrecoConfigProductAttribute
    {
        $id_config_product_attribute = Tools::getValue(AloPrecoConfigProductAttribute::PRIMARY_KEY);
        $alo_preco_config_product_attribute = new AloPrecoConfigProductAttribute($id_config_product_attribute);
        $product = new Product($alo_preco_config_product_attribute->getProductId());
        $product->deleteDefaultAttributes();
        $product->setDefaultAttribute($alo_preco_config_product_attribute->id_product_attribute);

        return $alo_preco_config_product_attribute;
    }

    /**
     * assign default action in toolbar_btn smarty var, if they are not set.
     * uses override to specifically add, modify or remove items.
     */
    public function initToolbar(): void
    {
        parent::initToolbar();

        if (
            ($this->display === 'list' || $this->display === null)
            && Tools::isSubmit(AloPrecoConfig::PRIMARY_KEY)
        ) {
            $id_config = '&' . AloPrecoConfig::PRIMARY_KEY . '=' . $this->id_config;

            $this->toolbar_btn['new'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token . $id_config,
                'desc' => $this->trans('Add new', [], 'Admin.Actions'),
            ];
        }
    }

    /**
     * @return string
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function renderList(): string
    {
        return '<br>'
            . $this->module->getConfigPrecoAdminPanel((int)$this->id_config)
            . $this->renderForm()
            . parent::renderList();
    }

    /**
     * @return string
     * @throws SmartyException
     */
    public function renderForm(): string
    {
        return parent::renderForm();
    }

    /**
     * Objects creation.
     * @return void
     * @throws PrestaShopException
     */
    public function processAdd(): void
    {
        $id_config = $this->id_config;
        $id_product_add = (int)Tools::getValue('id_product');
        $id_attribute = (int)Tools::getValue('id_attribute');

        $product = new Product($id_product_add);
        $product_attribute_data_list = $product->getAttributeCombinations($this->context->language->id);

        if ($id_attribute > 0) {
            // On récupère les product_attribute à partir d'id_attribute et id_product
            $product_attribute_data_list = Db::getInstance()->executeS(
                (new DbQuery())
                    ->select('pa.id_product_attribute')
                    ->from('product_attribute', 'pa')
                    ->leftJoin(
                        'product_attribute_combination',
                        'pac',
                        'pac.id_product_attribute = pa.id_product_attribute'
                    )
                    ->where('pac.id_attribute = ' . $id_attribute . ' AND pa.id_product = ' . $id_product_add)
            );
        }

        $counter = $this->addProductAttributes($id_config, $product_attribute_data_list);

        $this->informations[] = $counter . ' ' . $this->module->l('déclinaisons du produit ont été ajoutées.');
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function addProductAttributes(int $id_config, array $product_attributes): int
    {
        $counter = 0;

        foreach ($product_attributes as $product_attribute) {
            if (AloPrecoConfigProductAttribute::exists($id_config, $product_attribute['id_product_attribute'])) {
                continue;
            }

            $config_preco_product_attribute = new AloPrecoConfigProductAttribute();
            $config_preco_product_attribute->id_config = $id_config;
            $config_preco_product_attribute->id_product_attribute = $product_attribute['id_product_attribute'];
            $config_preco_product_attribute->save();
            $counter++;
        }

        return $counter;
    }
}
