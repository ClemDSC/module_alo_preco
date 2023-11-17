<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

/**
 * Classe représentant la configuration d'une précommande
 */
class AloPrecoPreorder extends ObjectModel
{
    /** @var string Nom de la table sans préfixe */
    public const TABLE_NAME = 'alo_preco_preorder';

    /** @var string Nom de la clé primaire */
    public const PRIMARY_KEY = 'id_preorder';

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => self::TABLE_NAME,
        'primary' => self::PRIMARY_KEY,
        'fields' => [
            'id_order' => [
                'type' => self::TYPE_INT,
                'required' => true,
            ],
            AloPrecoConfig::PRIMARY_KEY => [
                'type' => self::TYPE_INT,
                'required' => true,
            ],
            'is_refunded' => [
                'type' => self::TYPE_BOOL,
                'required' => false,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],
        ],
    ];

    public $id_order;
    public $id_config;
    public $is_refunded;
    public $date_add;
    public $date_upd;

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function createDatabase(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` '
            . '('
            . '`' . self::PRIMARY_KEY . '` int(11) unsigned NOT NULL AUTO_INCREMENT,'
            . '`id_order` int(11) NOT NULL, '
            . '`' . AloPrecoConfig::PRIMARY_KEY . '` int(11) NOT NULL, '
            . '`is_refunded` tinyint(1) NOT NULL DEFAULT 0, '
            . '`date_add` datetime NULL, '
            . '`date_upd` datetime NULL, '
            . 'PRIMARY KEY (`' . self::PRIMARY_KEY . '`)'
            . ') '
            . 'ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getPrecoFromOrderId(int $id_order)
    {
        if (!self::isOrderEligible($id_order)) {
            return false;
        }

        $id_preorder = Db::getInstance()->getValue(
            (new DbQuery())
                ->select('id_preorder')
                ->from(self::TABLE_NAME)
                ->where('id_order = ' . (int)$id_order)
        );

        $id_customer = (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('id_customer')
                ->from('orders')
                ->where('id_order = ' . (int)$id_order)
        );

        if (empty($id_preorder) && self::isOrderEligible($id_order) && !Alo_Preco::isSecondaryShopCustomerId($id_customer)) {
            $preorder = new self();
            $preorder->id_order = $id_order;
            $preorder->id_config = AloPrecoConfig::getIdFromIdOrder($id_order);
            $preorder->add();
            return $preorder;
        }

        return new self($id_preorder);
    }

    /**
     * Une commande peut-elle être convertie en précommande ?
     * @param int $id_order
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function isOrderEligible(int $id_order): bool
    {
        $order = new Order($id_order);
        $product_data_list = $order->getProducts();
        $alo_preco = Module::getInstanceByName('alo_preco');

        foreach ($product_data_list as $product_data) {
            if ($alo_preco->isProductAttributePreco($product_data['product_attribute_id'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * La commande est-elle une précommande ?
     * @param int $id_order
     * @return bool
     */
    public static function isPreorderExists(int $id_order): bool
    {
        return (bool)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->from(self::TABLE_NAME)
                ->where('id_order = ' . (int)$id_order)
        );
    }

    /**
     * Retourne le nombre de produits précommandés pour une précommande
     * @param int $id_config
     * @throws PrestaShopDatabaseException
     */
    public static function getPreorderActualProductQuantity(int $id_config)
    {
        $id_order_list = Db::getInstance()->executeS(
            (new DbQuery())
                ->select('id_order')
                ->from(self::TABLE_NAME)
                ->where(AloPrecoConfig::PRIMARY_KEY . ' = ' . $id_config)
        );

        $quantity = 0;

        foreach ($id_order_list as $id_order) {
            $quantity += Db::getInstance()->getValue(
                (new DbQuery())
                    ->select('SUM(product_quantity)')
                    ->from('order_detail')
                    ->where('id_order = ' . $id_order['id_order'])
            );
        }

        return $quantity;
    }

    /**
     * La commande Prestashop associée est-elle annulée ?
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function isPsOrderCancelled(): bool
    {
        return (int)$this->getOrder()->getCurrentState() === (int)Configuration::get('PS_OS_CANCELED');
    }

    /**
     * Instance de la commande Prestashop associée
     * @return Order
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrder(): Order
    {
        return new Order($this->id_order);
    }

    public function close(bool $is_successful)
    {
        $order = new Order($this->id_order);
        $id_order_state_success = Configuration::get(Alo_Preco::CONFIG_KEY_PRECO_SUCCESS_ORDER_STATE_ID);
        $id_order_state_failed = Configuration::get(Alo_Preco::CONFIG_KEY_PRECO_FAILED_ORDER_STATE_ID);
        $order->setCurrentState($is_successful ? $id_order_state_success : $id_order_state_failed);
    }

    /**
     * Retourne les commandes associées à une configuration de précommande
     */
    public function getOrdersWithConfigPreco($idConfigPreco)
    {
        $sql = "SELECT *
                FROM " . _DB_PREFIX_ . "alo_preco_preorder AS pre
                JOIN " . _DB_PREFIX_ . "orders AS o ON pre.id_order = o.id_order
                WHERE pre." . AloPrecoConfig::PRIMARY_KEY . " = " . (int)$idConfigPreco;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Retourne l'id de commande Paypal
     * pscheckout_order_matrice.id_order_paypal
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function getIdOrderPayPal(): string
    {
        $sql = (new DbQuery())
            ->select('id_order_paypal')
            ->from('pscheckout_order_matrice')
            ->where('id_order_prestashop = ' . $this->getIdOrder());

        return (string)Db::getInstance()->getValue($sql);
    }

    /**
     * Id de la commande Prestashop associée
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getIdOrder(): int
    {
        return (int)$this->getOrder()->id;
    }

    /**
     * Retourne l'id de transaction Paypal
     * order_payment.transaction_id
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function getIdTransactionPayPal(): string
    {
        $sql = (new DbQuery())
            ->select('transaction_id')
            ->from('order_payment')
            ->where('order_reference = "' . $this->getOrderReference() . '"');

        return (string)Db::getInstance()->getValue($sql);
    }

    /**
     * Référence de la commande Prestashop associée
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getOrderReference(): string
    {
        return $this->getOrder()->reference;
    }

    /**
     * Retourne le montant de la commande Prestashop associée
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getPaymentAmount(): float
    {
        return round($this->getOrder()->total_paid, 2);
    }

    /**
     * Retourne le code iso de la devise de la commande Prestashop associée
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function getCurrencyIsoCode(): string
    {
        return Currency::getIsoCodeById($this->getOrder()->id_currency);
    }
}
