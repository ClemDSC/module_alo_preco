<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

include_once _PS_MODULE_DIR_ . 'alo_preco' . DIRECTORY_SEPARATOR . 'alo_preco.php';
class OrderDetail extends OrderDetailCore
{
    /**
     * Updates product quantity in stock, according to order status.
     *
     * @param array $product
     * @param int $orderStateId
     */
    protected function updateProductQuantityInStock($product, $orderStateId): void
    {
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        if (AloPrecoConfigProductAttribute::isProductAttributePreco($product['id_product_attribute'])) {
            return;
        }
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $dismissOrderStateIds = Configuration::getMultiple([
            'PS_OS_CANCELED',
            'PS_OS_ERROR',
        ]);
        if (in_array($orderStateId, $dismissOrderStateIds)) {
            return;
        }
        if (!StockAvailable::dependsOnStock($product['id_product'])) {
            $orderState = new OrderState($orderStateId, $this->id_lang);
            $isQuantityUpdated = StockAvailable::updateQuantity(
                $product['id_product'],
                $product['id_product_attribute'],
                -(int) $product['cart_quantity'],
                $product['id_shop'],
                // Add stock movement only if order state is flagged as shipped
                true === (bool) $orderState->shipped,
                [
                    'id_order' => $this->id_order,
                    // Only one stock movement reason fits a new order creation
                    'id_stock_mvt_reason' => Configuration::get('PS_STOCK_CUSTOMER_ORDER_REASON'),
                ]
            );
        } else {
            $isQuantityUpdated = true;
        }
        if ($isQuantityUpdated === true) {
            $product['stock_quantity'] -= $product['cart_quantity'];
        }
        if ($product['stock_quantity'] < 0 && Configuration::get('PS_STOCK_MANAGEMENT')) {
            $this->outOfStock = true;
        }
        Product::updateDefaultAttribute($product['id_product']);
    }
}
