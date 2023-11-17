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

require_once
    _PS_MODULE_DIR_ . 'alo_preco'
    . DIRECTORY_SEPARATOR . 'classes'
    . DIRECTORY_SEPARATOR . 'AloPrecoConfig.php';

/**
 * Classe représentant la liaison entre une configuration de précommande et une déclinaison produit
 */
class AloPrecoConfigProductAttribute extends ObjectModel
{
    /** @var string Nom de la table sans préfixe */
    public const TABLE_NAME = 'alo_preco_config_product_attribute';

    /** @var string Nom de la clé primaire */
    public const PRIMARY_KEY = 'id_config_product_attribute';

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => self::TABLE_NAME,
        'primary' => self::PRIMARY_KEY,
        'fields' => [
            AloPrecoConfig::PRIMARY_KEY => [
                'type' => self::TYPE_INT,
                'required' => true,
            ],
            'id_product_attribute' => [
                'type' => self::TYPE_INT,
                'required' => true,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDate',
            ],
        ],
    ];

    public $id_config;
    public $id_product_attribute;
    public $date_add;

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function createDatabase(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` '
            . '('
            . '`' . self::PRIMARY_KEY . '` int(11) unsigned NOT NULL AUTO_INCREMENT, '
            . '`' . AloPrecoConfig::PRIMARY_KEY . '` int(11) unsigned NOT NULL, '
            . '`id_product_attribute` int(11) unsigned NOT NULL, '
            . '`date_add` datetime NOT NULL, '
            . 'PRIMARY KEY (`' . self::PRIMARY_KEY . '`)'
            . ') '
            . 'ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=UTF8;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * La déclinaison est-elle liée à la configuration de précommande ?
     * @param int $id_config
     * @param int $id_product_attribute
     * @return bool
     * @throws PrestaShopDatabaseException
     */
    public static function exists(int $id_config, int $id_product_attribute): bool
    {
        return (bool)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('COUNT(*)')
                ->from(self::TABLE_NAME)
                ->where('`' . AloPrecoConfig::PRIMARY_KEY . '` = ' . $id_config . ' '
                    . 'AND `id_product_attribute` = ' . $id_product_attribute)
        );
    }

    /**
     * @param int $id_product
     * @return bool
     */
    public static function isProductPreco(int $id_product, bool $check_dates = true): bool
    {
        $sql = (new DbQuery())
            ->select('pa.id_product')
            ->from(self::TABLE_NAME, 'cppa')
            ->leftJoin('product_attribute', 'pa', 'pa.id_product_attribute = cppa.id_product_attribute')
            ->where('pa.id_product = ' . $id_product);

        if ($check_dates) {
            $sql->leftJoin(
                AloPrecoConfig::TABLE_NAME, 'acp', 'acp.'.AloPrecoConfig::PRIMARY_KEY.' = cppa.'.AloPrecoConfig::PRIMARY_KEY)
                ->where('acp.date_begin <= NOW()')
                ->where('acp.date_end >= NOW()');
        }

        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * @param int $id_product_attribute
     * @return bool
     */
    public static function isProductAttributePreco(int $id_product_attribute, bool $check_dates = true): bool
    {
        $sql = (new DbQuery())
            ->select('id_product_attribute')
            ->from(self::TABLE_NAME, 'cppa')
            ->where('id_product_attribute = ' . $id_product_attribute);

        if ($check_dates) {
            $sql->leftJoin(
                AloPrecoConfig::TABLE_NAME, 'acp', 'acp.'.AloPrecoConfig::PRIMARY_KEY.' = cppa.'.AloPrecoConfig::PRIMARY_KEY)
                ->where('acp.date_begin <= NOW()')
                ->where('acp.date_end >= NOW()');
        }

        return (bool)Db::getInstance()->getValue($sql);
    }

    /**
     * Renvois l'ID de la configuration de précommande active associée à la déclinaison
     * @param int $product_attribute_id
     * @return false|int
     */
    public static function getIdConfigPrecoByProductAttributeId(int $product_attribute_id)
    {
        $sql = (new DbQuery())
            ->select('cppa.' . AloPrecoConfig::PRIMARY_KEY)
            ->from(self::TABLE_NAME, 'cppa')
            ->where('cppa.id_product_attribute = ' . $product_attribute_id)
            ->leftJoin(
                AloPrecoConfig::TABLE_NAME,
                'acp',
                'acp.' . AloPrecoConfig::PRIMARY_KEY . ' = cppa.' . AloPrecoConfig::PRIMARY_KEY
            )
            ->where('acp.date_begin <= NOW()')
            ->where('acp.date_end >= NOW()');

        $id_alo_config_preco = (int)Db::getInstance()->getValue($sql);

        if (empty($id_alo_config_preco)) {
            return false;
        }

        return $id_alo_config_preco;
    }

    /**
     * @param int $product_attribute_id
     * @return AloPrecoConfigProductAttribute
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function getConfigPrecoByProductAttributeId(int $product_attribute_id): AloPrecoConfig
    {
        $id_alo_config_preco = (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select(AloPrecoConfig::PRIMARY_KEY)
                ->from(self::TABLE_NAME)
                ->where('id_product_attribute = ' . $product_attribute_id)
        );

        $context = Context::getContext();

        return new AloPrecoConfig($id_alo_config_preco, $context->language->id);
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return (int)Db::getInstance()->getValue(
            (new DbQuery())
                ->select('id_product')
                ->from('product_attribute')
                ->where('id_product_attribute = ' . $this->id_product_attribute)
        );
    }
}
