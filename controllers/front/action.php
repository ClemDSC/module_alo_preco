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

/**
 * Traitement des requêtes AJAX et CRON
 */
class Alo_PrecoActionModuleFrontController extends ModuleFrontController
{
    public $html_output = '';

    /**
     * @return void
     */
    public function postProcess(): void
    {
        parent::postProcess();

        switch (Tools::getValue('action')) {
            case 'get_color_attribute_options':
                // Renvoi la liste des couleurs d'un produit formatés pour des boutons radios
                // /module/alo_preco/action?action=get_color_attribute_options&product_id=1
                // Pour les tests :
                // Produit avec plusieurs couleurs : 102697
                // Produit avec une seule couleur (texture) : 20108
                // Produit avec plusieurs couleurs (textures) : 20086
                // Produit sans couleur : 102705
                die($this->module->getColorAttributeOptions((int)Tools::getValue('product_id')));
            case 'set_preco_products_category':
                // Ajoute les produits en préco dans la catégorie préco, retire les autres
                // /module/alo_preco/action?action=set_preco_products_category
                $output = $this->module->l('Suppression des produits de la catégorie "précommande"') . '<br>' . '<br>';
                $product_data_list = Product::getProducts($this->context->language->id, 0, 0, 'id_product', 'ASC', Configuration::get(Alo_Preco::CONFIG_KEY_PRECO_CATEGORY_ID));

                foreach ($product_data_list as $product_data) {
                    $product = new Product($product_data['id_product'], false, $this->context->language->id);
                    $result = $product->deleteCategory(Configuration::get(Alo_Preco::CONFIG_KEY_PRECO_CATEGORY_ID));
                    $result_message = $this->module->l('Impossible de supprimer le produit ') . '[#' . $product->id . '] ' . $product->name . $this->module->l(' de la catégorie "précommande"');

                    if ($result) {
                        $result_message = $this->module->l('Suppression du produit ') . '[#' . $product->id . '] ' . $product->name . $this->module->l(' de la catégorie "précommande"') . '<br>';
                    }

                    $output .= $result_message;
                }

                $output .= '<br>' . $this->module->l('Ajout des produits précommandables dans la catégorie "précommande"') . '<br>' . '<br>';

                try {
                    $config_preco_list = AloPrecoConfig::getAll(true);
                } catch (PrestaShopDatabaseException $e) {
                    PrestaShopLogger::addLog('Alo_PrecoActionModuleFrontController::postProcess - Impossible de récupérer la liste des précommandes : ' . $e->getMessage(), 3);
                    die('Impossible de récupérer la liste des précommandes');
                }

                foreach ($config_preco_list as $config_preco) {
                    $id_product_list = $config_preco->getIdProducts();

                    foreach ($id_product_list as $id_product) {
                        $product = new Product($id_product, false, $this->context->language->id);
                        $result = $product->addToCategories([Configuration::get(Alo_Preco::CONFIG_KEY_PRECO_CATEGORY_ID)]);
                        $result_message = $this->module->l('Impossible d\'ajouter le produit ') . '[#' . $product->id . '] ' . $product->name . $this->module->l(' dans la catégorie "précommande"');

                        if ($result) {
                            $result_message = $this->module->l('Ajout du produit ') . '[#' . $product->id . '] ' . $product->name . $this->module->l(' dans la catégorie "précommande"') . '<br>';
                        }

                        $output .= $result_message;
                    }
                }

                $output .= '<br>' . $this->module->l('Fin du script');
                die($output);
            case 'process_preorders':
                // /module/alo_preco/action?action=process_preorders
                // Traite l'évolution des précommandes
                // - Envoi des emails de rappel
                // - Validation ou échec des précommandes
                try {
                    $this->sendAllPreordersReminderEmails();
                } catch (PrestaShopDatabaseException $e) {
                    $message = 'Alo_PrecoActionModuleFrontController::postProcess - Impossible d\'envoyer les emails de rappel : ' . $e->getMessage();
                    PrestaShopLogger::addLog(
                        $message,
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                        null,
                        null,
                        null,
                        true
                    );
                    $this->html_output .= $message;
                    $this->displayResult();
                }

                try {
                    $this->closeAllConfigPreorders();
                } catch (PrestaShopDatabaseException|PrestaShopException $e) {
                    $message = 'Alo_PrecoActionModuleFrontController::postProcess - Erreur lors de la fermeture des précommandes : ' . $e->getMessage();
                    PrestaShopLogger::addLog(
                        $message,
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                        null,
                        null,
                        null,
                        true
                    );
                    $this->html_output .= $message;
                    $this->displayResult();
                }
                $this->displayResult();
                break;
            case 'send_preorder_reminder_emails':
                // /module/alo_preco/action?action=send_preorder_reminder_emails
                // Envoi des emails de rappel
                try {
                    $this->sendAllPreordersReminderEmails();
                } catch (PrestaShopDatabaseException $e) {
                    $message = 'Alo_PrecoActionModuleFrontController::postProcess - Impossible d\'envoyer les emails de rappel : ' . $e->getMessage();
                    PrestaShopLogger::addLog(
                        $message,
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                        null,
                        null,
                        null,
                        true
                    );
                    $this->html_output .= $message;
                }
                $this->displayResult();
                break;
            case 'close_preorders':
                // /module/alo_preco/action?action=close_preorders
                // Validation ou échec des précommandes
                try {
                    $this->closeAllConfigPreorders();
                } catch (PrestaShopDatabaseException|PrestaShopException $e) {
                    $message = 'Alo_PrecoActionModuleFrontController::postProcess - Erreur lors de la fermeture des précommandes : ' . $e->getMessage();
                    PrestaShopLogger::addLog(
                        $message,
                        PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR,
                        null,
                        null,
                        null,
                        true
                    );
                    $this->html_output .= $message;
                    $this->displayResult();
                }
                $this->displayResult();
                break;
        }

        die('Aucune action demandée');
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    protected function sendAllPreordersReminderEmails(): void
    {
        $config_preco_list = AloPrecoConfig::getAll(true);

        $this->html_output .= 'Envoi des emails de rappel<br><br>';
        foreach ($config_preco_list as $config) {
            $this->sendReminderEmails($config);
        }
    }

    /**
     * Envoie les emails de rappel si la date de rappel est aujourd'hui
     * @throws SmartyException
     */
    public function sendReminderEmails(AloPrecoConfig $config): bool
    {
        if (empty($config->date_reminder) || ($config->date_reminder !== date('Y-m-d 00:00:00'))) {
            return false;
        }

        $this->html_output .= 'Envoi des emails de rappel pour la configuration de précommande [#' . $config->id . '] ' . $config->name . '<br>';

        $preorder_list = $config->getPreorderList();

        foreach ($preorder_list as $preorder) {
            $order = $preorder->getOrder();

            // Si la commande n'est pas valide ou que son statut est "annulé", on n'envoie pas de mail
            if (!Validate::isLoadedObject($order)
                || $order->getCurrentState() === Configuration::get('PS_OS_CANCELED')) {
                continue;
            }

            $customer = new Customer($order->id_customer);
            $this->context->language->id = $customer->id_lang;

            $template = 'alo_preco_reminder';
            $templatePath = 'themes/alo_theme/mails';
            $alo_theme = Module::getInstanceByName('alo_theme');

            $product_data_list = $order->getProducts();

            foreach ($product_data_list as $key_product => $product_data) {
                $product_data_list[$key_product]['id_product_attribute'] = $product_data['product_attribute_id'];
                $product_data_list[$key_product]['name'] = $product_data['product_name'];
                $product_data_list[$key_product]['quantity'] = $product_data['product_quantity'];
                $product_data_list[$key_product]['customization'] = [];
                $product_data_list[$key_product]['price'] = Tools::displayPrice($product_data['price']);
                $product_data_list[$key_product]['manufacturer_name'] = $alo_theme->getMailExtraProductManufacturer($product_data);
                $product_data_list[$key_product]['cover_url'] = $alo_theme->getMailExtraProductCover($product_data);
            }

            $this->context->smarty->assign([
                'list' => $product_data_list,
            ]);

            $this->html_output .= 'Envoi du mail pour la commande ' . $order->reference . '<br>';

            Mail::send(
                $customer->id_lang,
                $template,
                $this->module->l('Rappel de votre commande'),
                [
                    '{products}' => $this->context->smarty->fetch(
                        _PS_MODULE_DIR_ . 'alo_theme'
                        . DIRECTORY_SEPARATOR . 'mails'
                        . DIRECTORY_SEPARATOR . '_partials'
                        . DIRECTORY_SEPARATOR . 'alo_preco_reminder_product_list.tpl'
                    ),
                ],
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                null,
                null,
                $templatePath

            );
        }

        return true;
    }

    protected function displayResult(): void
    {
        die($this->html_output);
    }

    /**
     * Vérifie si la date de fin de précommande est aujourd'hui.
     * Si le nombre de précommandes est atteint, on valide la précommande
     * Sinon on passe les commandes au statut annulé
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function closeAllConfigPreorders(): void
    {
        $config_preco_list = AloPrecoConfig::getAll(false);
        $current_date = date('Y-m-d 00:00:00');

        $this->html_output .= 'Cloture des précommandes<br><br>';
        foreach ($config_preco_list as $config) {
            if ($config->date_end !== $current_date) {
                continue;
            }

            $this->closePreorders($config);
        }
    }

    /**
     * Ferme les précommandes liées à cette configuration
     * @param AloPrecoConfig $config
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function closePreorders(AloPrecoConfig $config): void
    {
        $this->html_output .= 'Cloture de la configuration de précommande [#' . $config->id . '] ' . $config->name . '<br>';
        $preorder_list = $config->getPreorderList();
        $isSuccessful = $config->isSuccessful();

        foreach ($preorder_list as $preorder) {
            if ($preorder->isPsOrderCancelled()) {
                continue;
            }

            $this->html_output .= 'Cloture de la précommande ' . $preorder->getOrderReference() . '<br>';
            $preorder->close($isSuccessful);
        }
    }
}
