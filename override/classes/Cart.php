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

class Cart extends CartCore
{
    /**
     * Get the delivery option selected, or if no delivery option was selected,
     * the cheapest option for each address.
     *
     * @param Country|null $default_country Default country
     * @param bool $dontAutoSelectOptions Do not auto select delivery option
     * @param bool $use_cache Use cache
     *
     * @return array|false Delivery option
     */
    public function getDeliveryOption($default_country = null, $dontAutoSelectOptions = false, $use_cache = true)
    {
        $cache_id = (int) (is_object($default_country) ? $default_country->id : 0) . '-' . (int) $dontAutoSelectOptions;
        if (isset(static::$cacheDeliveryOption[$cache_id]) && $use_cache) {
            return static::$cacheDeliveryOption[$cache_id];
        }

        $delivery_option_list = $this->getDeliveryOptionList($default_country);

        // The delivery option was selected
        if (isset($this->delivery_option) && $this->delivery_option != '') {
            $delivery_option = json_decode($this->delivery_option, true);
            $validated = true;

            if (is_array($delivery_option)) {
                foreach ($delivery_option as $id_address => $key) {
                    if (!isset($delivery_option_list[$id_address][$key])) {
                        $validated = false;

                        break;
                    }
                }

                if ($validated) {
                    ////////////////////////////////////////////////////////////////////////////////////////////////////
                    /// Si il y a plusieurs options de livraison, on prend celle qui a été sélectionnée sur l'adresse client réelle
                    if (count($delivery_option) > 1) {
                        $selected_delivery_option = '';
                        foreach ($delivery_option as $id_address => $delivery_key) {
                            if ((int)$id_address === (int)$this->id_address_delivery) {
                                $selected_delivery_option = $delivery_key;
                                break;
                            }
                        }

                        if (!empty($selected_delivery_option)) {
                            foreach ($delivery_option as $id_address => $delivery_kry) {
                                $delivery_option[$id_address] = $selected_delivery_option;
                            }
                        }
                    }
                    ////////////////////////////////////////////////////////////////////////////////////////////////////
                    static::$cacheDeliveryOption[$cache_id] = $delivery_option;

                    return $delivery_option;
                }
            }
        }

        if ($dontAutoSelectOptions) {
            return false;
        }

        // No delivery option selected or delivery option selected is not valid, get the better for all options
        $delivery_option = [];
        foreach ($delivery_option_list as $id_address => $options) {
            foreach ($options as $key => $option) {
                if (Configuration::get('PS_CARRIER_DEFAULT') == -1 && $option['is_best_price']) {
                    $delivery_option[$id_address] = $key;

                    break;
                } elseif (Configuration::get('PS_CARRIER_DEFAULT') == -2 && $option['is_best_grade']) {
                    $delivery_option[$id_address] = $key;

                    break;
                } elseif ($option['unique_carrier'] && in_array(Configuration::get('PS_CARRIER_DEFAULT'), array_keys($option['carrier_list']))) {
                    $delivery_option[$id_address] = $key;

                    break;
                }
            }

            reset($options);
            if (!isset($delivery_option[$id_address])) {
                $delivery_option[$id_address] = key($options);
            }
        }

        static::$cacheDeliveryOption[$cache_id] = $delivery_option;

        return $delivery_option;
    }

    /**
     * Are all products of the Cart in stock?
     *
     * @param bool $ignoreVirtual Ignore virtual products
     * @param bool $exclusive (DEPRECATED) If true, the validation is exclusive : it must be present product in stock and out of stock
     *
     * @since 1.5.0
     *
     * @return bool False if not all products in the cart are in stock
     */
    public function isAllProductsInStock($ignoreVirtual = false, $exclusive = false)
    {
        if (func_num_args() > 1) {
            @trigger_error(
                '$exclusive parameter is deprecated since version 1.7.3.2 and will be removed in the next major version.',
                E_USER_DEPRECATED
            );
        }
        $productOutOfStock = 0;
        $productInStock = 0;

        foreach ($this->getProducts(false, false, null, false) as $product) {
            if ($ignoreVirtual && $product['is_virtual']) {
                continue;
            }
            $idProductAttribute = !empty($product['id_product_attribute']) ? $product['id_product_attribute'] : null;
            $availableOutOfStock = Product::isAvailableWhenOutOfStock($product['out_of_stock']);
            $productQuantity = Product::getQuantity(
                $product['id_product'],
                $idProductAttribute,
                null,
                $this,
                $product['id_customization']
            );

            if (!$exclusive
                && ($productQuantity < 0 && !$availableOutOfStock)
            ) {
                if ($idProductAttribute && $this->isProductAttributePreco($idProductAttribute)) {
                    continue;
                }
                return false;
            } elseif ($exclusive) {
                if ($productQuantity <= 0) {
                    ++$productOutOfStock;
                } else {
                    ++$productInStock;
                }

                if ($productInStock > 0 && $productOutOfStock > 0) {
                    if ($idProductAttribute && $this->isProductAttributePreco($idProductAttribute)) {
                        continue;
                    }
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param int $id_product_attribute
     * @return bool
     */
    protected function isProductAttributePreco($id_product_attribute): bool
    {
        $alo_preco = Module::getInstanceByName('alo_preco');

        if (!$alo_preco) {
            return false;
        }

        return $alo_preco->isProductAttributePreco((int)$id_product_attribute);
    }
}
