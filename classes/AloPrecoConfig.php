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
class AloPrecoConfig extends ObjectModel
{
    /** @var string Nom de la table sans préfixe */
    public const TABLE_NAME = 'alo_preco_config';

    /** @var string Nom de la clé primaire */
    public const PRIMARY_KEY = 'id_config';

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => self::TABLE_NAME,
        'primary' => self::PRIMARY_KEY,
        'multilang' => true,
        'fields' => [
            'name' => [
                'type' => self::TYPE_STRING,
                'required' => false,
            ],
            'nb_current' => [
                'type' => self::TYPE_INT,
                'required' => false,
            ],
            'nb_target' => [
                'type' => self::TYPE_INT,
                'required' => true,
            ],
            'date_begin' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],
            'date_reminder' => [
                'type' => self::TYPE_DATE,
                'required' => false,
                'validate' => 'isDate',
            ],
            'date_end' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],
            'incrementer' => [
                'type' => self::TYPE_INT,
                'required' => false,
                'size' => 128,
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
            'date_shipping' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],

            /* Lang fields */
            'product_page_info' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'required' => false,
                'size' => 255
            ],
            'production_time' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'required' => false,
                'size' => 255
            ],
        ],
    ];

    public $name;
    public $nb_current;
    public $nb_target;
    public $date_begin;
    public $date_reminder;
    public $date_end;
    public $incrementer;
    public $product_page_info;
    public $date_add;
    public $date_upd;
    public $date_shipping;
    public $production_time;

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
            . '`name` varchar(255) NULL, '
            . '`nb_current` int(11) NULL, '
            . '`nb_target` int(11) NOT NULL, '
            . '`date_begin` datetime NULL, '
            . '`date_reminder` datetime NULL, '
            . '`date_end` datetime NOT NULL, '
            . '`incrementer` int(11) NULL, '
            . '`date_add` datetime NOT NULL, '
            . '`date_upd` datetime NULL, '
            . '`date_shipping` datetime NOT NULL, '
            . 'PRIMARY KEY (`' . self::PRIMARY_KEY . '`)'
            . ') '
            . 'ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        $result = Db::getInstance()->execute($sql);

        // Table multilang
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '_lang` '
            . '('
            . '`' . self::PRIMARY_KEY . '` int(11) unsigned NOT NULL,'
            . '`id_lang` int(11) unsigned NOT NULL,'
            . '`product_page_info` varchar(255) NULL, '
            . '`production_time` varchar(255) NOT NULL, '
            . 'PRIMARY KEY (`' . self::PRIMARY_KEY . '`, `id_lang`)'
            . ') '
            . 'ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        return $result && Db::getInstance()->execute($sql);
    }

    /**
     * Retourne les instances des précommandes, en cours si $is_active est à true, sinon toutes
     * @param bool $is_active
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getAll(bool $is_active = false): array
    {
        $sql = new DbQuery();
        $sql->select(self::PRIMARY_KEY);
        $sql->from(self::TABLE_NAME);

        if ($is_active) {
            $sql->where('date_begin <= NOW() AND date_end >= NOW()');
        }

        $sql->orderBy('date_begin ASC');
        $preco_data_list = Db::getInstance()->executeS($sql);
        $preco_list = [];

        foreach ($preco_data_list as $preco_data) {
            $preco_list[] = new AloPrecoConfig($preco_data[self::PRIMARY_KEY]);
        }

        return $preco_list;
    }

    /**
     * Retourne les données des précommandes, en cours si $is_active est à true, sinon toutes
     * @param bool $is_active
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getAllData(bool $is_active = false): array
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from(self::TABLE_NAME);

        if ($is_active) {
            $sql->where('date_begin <= NOW() AND date_end >= NOW()');
        }

        $sql->orderBy('date_begin ASC');
        return (array)Db::getInstance()->executeS($sql);
    }

    public static function getIdFromIdOrder(int $id_order)
    {
        $order = new Order($id_order);
        $product_data_list = $order->getProducts();

        foreach ($product_data_list as $product_data) {
            if (AloPrecoConfigProductAttribute::isProductAttributePreco($product_data['product_attribute_id'])) {
                return AloPrecoConfigProductAttribute::getIdConfigPrecoByProductAttributeId(
                    $product_data['product_attribute_id']
                );
            }
        }

        return false;
    }

    /**
     * Renvois les IDs des produits associés à la précommande
     */
    public function getIdProducts(): array
    {
        $id_product_list = Db::getInstance()->executeS(
            (new DbQuery())
                ->select('id_product')
                ->from(AloPrecoConfigProductAttribute::TABLE_NAME, 'cppa')
                ->leftJoin('product_attribute', 'pa', 'pa.id_product_attribute = cppa.id_product_attribute')
                ->where(self::PRIMARY_KEY . ' = ' . (int)$this->id)
                ->groupBy('id_product')
        );

        if (empty($id_product_list)) {
            return [];
        }

        foreach ($id_product_list as $key => $id_product) {
            $id_product_list[] = $id_product['id_product'];
            unset($id_product_list[$key]);
        }

        return $id_product_list;
    }

    /**
     * Cette configuration est-elle liée des précommandes remboursées ?
     * @throws PrestaShopException
     */
    public function hasRefundedPreorders(): bool
    {
        $preorder_list = $this->getPreorderList();

        foreach ($preorder_list as $preorder) {
            if ((bool)$preorder->is_refunded) {
                return true;
            }
        }

        return false;
    }

    /**
     * Donne la liste des commandes en précommandes liées à cette configuration
     * @throws PrestaShopException
     */
    public function getPreorderList(): array
    {
        $collection = new PrestaShopCollection(AloPrecoPreorder::class, Configuration::get('PS_LANG_DEFAULT'));
        $collection->where('id_config', '=', $this->id);

        return $collection->getResults();
    }

    /**
     * Le volume de précommande est-il atteint ?
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public function isSuccessful(): bool
    {
        return $this->getVolumePct() >= 100;
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    public function getVolumePct(): float
    {
        return round((($this->getNbCurrent()) / $this->nb_target) * 100, 2);
    }

    /**
     * Donne la quantité totale de produits précommandés pour cette conf de précommande
     * @return int
     * @throws PrestaShopDatabaseException
     */
    public function getNbCurrent(): int
    {
        $this->nb_current = (int)AloPrecoPreorder::getPreorderActualProductQuantity((int)$this->id) + $this->incrementer;
        $this->save();
        return $this->nb_current;
    }

    /**
     * La date de cloture est-elle passée ?
     */
    public function isClosed(): bool
    {
        return (strtotime($this->date_end) < time());
    }
}
