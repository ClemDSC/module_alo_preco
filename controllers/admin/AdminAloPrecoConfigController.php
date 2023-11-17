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
    . DIRECTORY_SEPARATOR . 'AloPrecoConfig.php';

class AdminAloPrecoConfigController extends ModuleAdminController
{
    const STATUS_NOT_STARTED = 'non commencé';
    const STATUS_IN_PROGRESS = 'en cours';
    const STATUS_VALIDATED = 'validé';
    const STATUS_FAILED = 'en échec';

    /**
     * Instanciation de la classe
     * Définition des paramètres basiques obligatoires
     * Modification de la requête pour récupérer les entités
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->bootstrap = true; //Gestion de l'affichage en mode bootstrap
        $this->table = AloPrecoConfig::TABLE_NAME; //Table de l'objet
        $this->identifier = AloPrecoConfig::PRIMARY_KEY; //Clé primaire de l'objet
        $this->className = AloPrecoConfig::class; //Classe de l'objet
        $this->lang = false; //Flag pour dire si utilisation de langues ou non
        $this->list_simple_header = false;
        $this->list_no_link = true;
        $this->addRowAction('view');
        $this->addRowAction('addProducts');
        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();

        //Liste des champs de l'objet à afficher dans la liste
        $this->fields_list = $this->getConfigList();

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', [], 'Admin.Actions'),
                'icon' => 'icon-trash',
                'confirm' => $this->trans('Delete selected items?', [], 'Admin.Notifications.Warning'),
            ],
        ];

        // Si la date de début n'est pas encore passée, on affiche "non commencé"
        // Sinon, si la date de fin n'est pas encore passée, on affiche "en cours"
        // Sinon, si le nombre de ventes est atteint, on affiche "validé"
        // Sinon, on affiche "en échec"
        $this->_select =
            '(CASE WHEN a.date_begin >= NOW() '
            . 'THEN \'' . self::STATUS_NOT_STARTED . '\' '
            . 'ELSE '
            . '(CASE WHEN a.date_end >= NOW() '
            . 'THEN \'' . self::STATUS_IN_PROGRESS . '\' '
            . 'ELSE (CASE WHEN (a.nb_current + a.incrementer) >= a.nb_target '
            . 'THEN \'' . self::STATUS_VALIDATED . '\' '
            . 'ELSE \'' . self::STATUS_FAILED . '\' '
            . 'END) '
            . 'END) '
            . 'END) status';

        $this->fields_form = $this->getConfigForm();
    }

    protected function getConfigList(): array
    {
        return [
            'name' => [
                'title' => $this->module->l('Nom préco'),
                'orderby' => true,
                'search' => true,
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'callback' => 'callbackDisplayName',

            ],
            'nb_current' => [
                'title' => $this->module->l('Quantité actuelle (avec incrémenteur)'),
                'orderby' => true,
                'search' => false,
                'align' => 'center',
                'callback' => 'callbackDisplayCurrent',
            ],
            'date_begin' => [
                'title' => $this->module->l('Date de début'),
                'orderby' => true,
                'search' => true,
                'align' => 'center',
                'class' => 'fixed-width-xl',
                'callback' => 'callbackDisplayDate',
            ],
            'date_end' => [
                'title' => $this->module->l('Date de fin'),
                'orderby' => true,
                'search' => true,
                'align' => 'center',
                'class' => 'fixed-width-xl',
                'callback' => 'callbackDisplayDate',
            ],
            'status' => [
                'title' => $this->module->l('statut'),
                'orderby' => false,
                'search' => false,
                'align' => 'center',
                'class' => 'fixed-width-xl',
                'callback' => 'callbackDisplayStatus',
            ],
        ];
    }

    protected function getConfigForm(): array
    {
        return [
            'legend' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'label' => $this->l('Nom'),
                    'name' => 'name',
                    'type' => 'text',
                    'col' => 3,
                    'desc' => $this->module->l('Ne sera pas affiché en front. Juste pour la gestion en back office.'),
                ],
                [
                    'label' => $this->l('Nombre de ventes à atteindre'),
                    'name' => 'nb_target',
                    'type' => 'text',
                    'col' => 1,
                ],
                [
                    'label' => $this->l('Boost / Nombre à incrémenter'),
                    'name' => 'incrementer',
                    'type' => 'text',
                    'col' => 1,
                ],
                [
                    'label' => $this->l('Ouverture des précommande'),
                    'name' => 'date_begin',
                    'type' => 'date',
                    'col' => 6,
                ],
                [
                    'label' => $this->l('Fin des précommandes'),
                    'name' => 'date_end',
                    'type' => 'date',
                    'col' => 6,
                ],
                [
                    'label' => $this->l('Date de Rappel'),
                    'desc' => $this->module->l('Un email de rappel sera envoyé à cette date.'),
                    'name' => 'date_reminder',
                    'type' => 'date',
                    'col' => 6,
                ],
                [
                    'label' => $this->l('Expédition des précommandes'),
                    'desc' => $this->module->l('Date affichée dans le mail fabrication précommande.'),
                    'name' => 'date_shipping',
                    'type' => 'date',
                    'col' => 6,
                ],
                [
                    'label' => $this->l('Phrase d\'information sur la page produit'),
                    'desc' => $this->module->l('Ex. : "Livraison en précommande estimée entre le xx/xx/xx et le xx/xx/xx"'),
                    'name' => 'product_page_info',
                    'type' => 'text',
                    'lang' => true,
                    'col' => 8,
                ],
                [
                    'label' => $this->l('Délais estimés de fabrication'),
                    'desc' => $this->module->l('Pour le mail validation précommande. Ex. : "4 jours".'),
                    'name' => 'production_time',
                    'type' => 'text',
                    'lang' => true,
                    'col' => 8,
                ],
            ],
            'submit' => [
                'title' => $this->l('Enregistrer'),
            ],
            'buttons' => [
                'save-and-stay' => [
                    'title' => $this->module->l('Enregistrer et ajouter des produits'),
                    'name' => 'submitAdd' . $this->table . 'AndAddProducts',
                    'type' => 'submit',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save',
                ],
            ],
        ];
    }

    /**
     * @throws PrestaShopException
     */
    public function callbackDisplayDate($date, $row): string
    {
        return Tools::displayDate($date);
    }

    public function callbackDisplayCurrent($nb_current, $row)
    {
        $config_preco = new AloPrecoConfig($row[$this->identifier]);
        $output = $config_preco->getNbCurrent() . ' / ' . $row['nb_target'] . ' (' . $config_preco->getVolumePct() . ' %)';

        if ($nb_current - $row['incrementer'] > 0) {
            $output .= '<br><a href="' . $this->context->link->getAdminLink('AdminAloPrecoPreorder') . '&' . AloPrecoConfig::PRIMARY_KEY . '=' . $row[$this->identifier] . '" class="btn btn-default"><i class="icon-eye"></i> ' . $this->module->l('Voir les précommandes') . '</a>';
        }

        return $output;
    }

    public function callbackDisplayName($name, $row)
    {
        return '[#' . $row[$this->identifier] . '] ' . $name;
    }

    public function callbackDisplayStatus($status, $row)
    {
        $badge = 'info';
        $bulk_refund_btn = '';

        switch ($status) {
            case self::STATUS_IN_PROGRESS:
                $badge = 'warning';
                break;
            case self::STATUS_VALIDATED:
                $badge = 'success';
                break;
            case self::STATUS_FAILED:
                $badge = 'danger';
                $config = new AloPrecoConfig($row[$this->identifier]);

                if (!$config->hasRefundedPreorders()) {
//                    $confirmation_script = 'return confirm(\'' . $this->module->l('Êtes-vous sûr de vouloir rembourser toutes les précommandes ?') . '\');';
                    $confirmation_script_v2 = "<script>jQuery('#open_dialog').click( function () {
                        if (confirm( 'Êtes-vous sûr de vouloir rembourser toutes les précommandes pour " . $row["name"] . "?' ) ) {
                            if (confirm( 'Êtes-vous vraiment sûr de vouloir rembourser toutes les précommandes pour " . $row["name"] . "?' ) ) {
                                //use statement to redirect on particular page.
                            }
                        }
                    })</script>";
//                    $bulk_refund_btn = '<br><a href="' . $this->context->link->getAdminLink('AdminAloPrecoPreorder') . '&' . AloPrecoConfig::PRIMARY_KEY . '=' . $row[$this->identifier] . '&bulk_refund" class="btn btn-default" style="margin-top: 5px;" onclick="' . $confirmation_script . '"><i class="icon-undo"></i> ' . $this->module->l('Rembourser les précommandes') . '</a>';
                    $bulk_refund_btn = '<br><a id="open_dialog" href="' . $this->context->link->getAdminLink('AdminAloPrecoPreorder') . '&' . AloPrecoConfig::PRIMARY_KEY . '=' . $row[$this->identifier] . '&bulk_refund" class="btn btn-default" style="margin-top: 5px;"><i class="icon-undo"></i> ' . $this->module->l('Rembourser les précommandes') . '</a>' . $confirmation_script_v2;
                } else {
                    $bulk_refund_btn = ' - Remboursé -';
                }

                break;
        }

        return '<span class="badge badge-' . $badge . '">' . $status . '</span>' . $bulk_refund_btn;
    }

    /**
     * @return string
     * @throws PrestaShopException
     */
    public function renderList(): string
    {
        return '<br>' . parent::renderList();
    }

    /**
     * @throws SmartyException
     */
    public function displayAddProductsLink($token = null, $id = null)
    {
        $this->context->smarty->assign([
            'add_products_url' => $this->context->link->getAdminLink(
                'AdminAloPrecoConfigProductAttribute',
                true,
                [AloPrecoConfig::PRIMARY_KEY => $id],
                [AloPrecoConfig::PRIMARY_KEY => $id]
            ),
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/add_products_link.tpl');
    }

    /**
     * @return string
     * @throws SmartyException
     */
    public function renderForm()
    {
        return '<br>' . parent::renderForm();
    }

    /**
     * Call the right method for creating or updating object.
     * @return false|ObjectModel|void|null
     */
    public function processSave()
    {
        $result = parent::processSave();

        if ($result && Tools::isSubmit('submitAdd' . $this->table . 'AndAddProducts')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminAloPrecoConfigProductAttribute') . '&' . AloPrecoConfig::PRIMARY_KEY . '=' . $this->object->id);
        }

        return $result;
    }

    public function renderView(): string
    {
        return '<br>' . $this->module->getConfigPrecoAdminPanel((int)$this->object->id);
    }
}
