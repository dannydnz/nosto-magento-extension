<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Nosto
 * @package     Nosto_Tagging
 * @copyright   Copyright (c) 2013-2015 Nosto Solutions Ltd (http://www.nosto.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getBaseDir('lib').'/nosto/php-sdk/src/config.inc.php');

/**
 * Event observer model.
 * Used to interact with Magento events.
 *
 * @category    Nosto
 * @package     Nosto_Tagging
 * @author      Nosto Solutions Ltd
 */
class Nosto_Tagging_Model_Observer
{
    /**
     * Xml layout handle for the default page footer section.
     */
    const XML_LAYOUT_PAGE_DEFAULT_FOOTER_HANDLE = 'nosto_tagging_page_default_footer';

    /**
     * Adds Nosto footer block at the end of the content block.
     *
     * Event 'controller_action_layout_load_before'.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return Nosto_Tagging_Model_Observer
     */
    public function addBlockAfterMainContent(Varien_Event_Observer $observer)
    {
        if (Mage::helper('nosto_tagging')->isModuleEnabled()) {
            /** @var $layout Mage_Core_Model_Layout_Update */
            $layout = $observer->getEvent()->getLayout()->getUpdate();
            $layout->addHandle(self::XML_LAYOUT_PAGE_DEFAULT_FOOTER_HANDLE);
        }

        return $this;
    }

	/**
	 * Event handler for the "catalog_product_save_after" event.
	 * Sends a product re-crawl API call to Nosto.
	 *
	 * @param Varien_Event_Observer $observer the event observer.
	 *
	 * @return Nosto_Tagging_Model_Observer
	 */
	public function recrawlProduct(Varien_Event_Observer $observer)
	{
		if (Mage::helper('nosto_tagging')->isModuleEnabled()) {
			try {
				/** @var Mage_Catalog_Model_Product $mageProduct */
				$mageProduct = $observer->getEvent()->getProduct();
				$product = new Nosto_Tagging_Model_Meta_Product();
				$product->loadData($mageProduct);
				// If the product does not have the store id set.
				if ((int)$mageProduct->getStoreId() === 0) {
					// Then assume that the product has been modified for all stores.
					$stores = Mage::app()->getStores();
				} else {
					// Otherwise only re-crawl the product for the specific store.
					$stores = array(Mage::app()->getStore($mageProduct->getStoreId()));
				}
				foreach ($stores as $store) {
					/** @var NostoAccount $account */
					$account = Mage::helper('nosto_tagging/account')->find($store);
					if ($account === null || !$account->isConnectedToNosto()) {
						continue;
					}
					NostoProductReCrawl::send($product, $account);
				}
			} catch (NostoException $e) {
				Mage::log("\n" . $e->__toString(), Zend_Log::ERR, 'nostotagging.log');
			}
		}

		return $this;
	}
}