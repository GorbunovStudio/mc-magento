<?php

/**
 * mailchimp-lib Magento Component
 *
 * @category  Ebizmarts
 * @package   mailchimp-lib
 * @author    Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Ebizmarts_MailChimp_Model_Api_Products
{
    const PRODUCT_IS_ENABLED = 1;
    const PRODUCT_IS_DISABLED = 2;
    const BATCH_LIMIT = 100;
    private $_parentImageUrl = null;
    private $_parentId = null;
    private $_parentUrl = null;
    private $_parentPrice = null;
    private $_visibility = null;
    /** @var Mage_Catalog_Model_Product_Type_Configurable */
    private $productTypeConfigurable;

    /**
     * @var Ebizmarts_MailChimp_Helper_Data
     */
    private $mailchimpHelper;
    private $visibilityOptions;
    private $productTypeConfigurableResource;
    public static $noChildrenIds = array(0 => array());

    const PRODUCT_DISABLED_IN_MAGENTO = 'This product was deleted because it is disabled in Magento.';

    public function __construct()
    {
        $this->productTypeConfigurable = Mage::getModel('catalog/product_type_configurable');
        $this->productTypeConfigurableResource = Mage::getResourceSingleton('catalog/product_type_configurable');
        $this->mailchimpHelper = Mage::helper('mailchimp');
        $this->visibilityOptions = Mage::getModel('catalog/product_visibility')->getOptionArray();
    }

    public function createBatchJson($mailchimpStoreId, $magentoStoreId)
    {
        $helper = $this->getMailChimpHelper();
        $oldStore = $helper->getMageApp()->getStore()->getId();
        $helper->getMageApp()->setCurrentStore($magentoStoreId);

        if ($this->isProductFlatTableEnabled()) {
            $helper->getMageApp()->getStore($magentoStoreId)
                ->setConfig(Mage_Catalog_Helper_Category_Flat::XML_PATH_IS_ENABLED_FLAT_CATALOG_CATEGORY, 0)
                ->setConfig(Mage_Catalog_Helper_Product_Flat::XML_PATH_USE_PRODUCT_FLAT, 0);
        }
        $this->_markSpecialPrices($mailchimpStoreId, $magentoStoreId);
        $collection = $this->makeProductsNotSentCollection($magentoStoreId);
        $this->joinMailchimpSyncData($collection, $mailchimpStoreId);
        $batchArray = array();

        $batchId = $this->makeBatchId($magentoStoreId);
        $counter = 0;
        foreach ($collection as $product) {

            $productId = $product->getId();

            if ($this->shouldSendProductUpdate($mailchimpStoreId, $magentoStoreId, $product)) {
                $batchArray = array_merge($this->_buildUpdateProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId), $batchArray);
                $counter = count($batchArray);
                $this->_updateSyncData($productId, $mailchimpStoreId);
                continue;
            } else {
                $data = $this->_buildNewProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId);
            }

            if (!empty($data)) {
                $batchArray[$counter] = $data;
                $counter++;

                $dataProduct = $helper->getEcommerceSyncDataItem($productId, Ebizmarts_MailChimp_Model_Config::IS_PRODUCT, $mailchimpStoreId);
                if ($dataProduct->getId()) {
                    $helper->modifyCounterSentPerBatch(Ebizmarts_MailChimp_Helper_Data::PRO_MOD);
                } else {
                    $helper->modifyCounterSentPerBatch(Ebizmarts_MailChimp_Helper_Data::PRO_NEW);
                }

                //update product delta
                $this->_updateSyncData($productId, $mailchimpStoreId);
            } else {
                $this->_updateSyncData($productId, $mailchimpStoreId, $this->getCurrentDate(), "This product type is not supported on MailChimp.", null, null, 0);
            }
        }
        $helper->getMageApp()->setCurrentStore($oldStore);
        return $batchArray;
    }

    public function createDeletedProductsBatchJson($mailchimpStoreId, $magentoStoreId)
    {
        $deletedProducts = $this->getProductResourceCollection();

        $this->joinMailchimpSyncDataDeleted($mailchimpStoreId, $deletedProducts);

        $batchArray = array();
        $batchId = $this->makeBatchId($magentoStoreId);
        $counter = 0;
        foreach ($deletedProducts as $product) {

            $data = $this->_buildDeleteProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId);

            if (!empty($data)) {
                $batchArray[$counter] = $data;
                $counter++;
            }
            $this->_updateSyncData($product->getId(), $mailchimpStoreId, null, self::PRODUCT_DISABLED_IN_MAGENTO, null, null, 0);

        }
        return $batchArray;
    }

    protected function _buildDeleteProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId)
    {
        if ($this->isBundleProduct($product))
        {
            //@TODO bundle
            return array();
        } else {
            $data = array();
            $data['method'] = "DELETE";
            $data['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products/" . $product->getId();
            $data['operation_id'] = $batchId . '_' . $product->getId();
        }

        return $data;
    }

    protected function _buildNewProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId)
    {
        $variantProducts = array();
        if ($this->isSimpleProduct($product)) {
            $variantProducts[] = $product;
        } else if ($this->isConfigurableProduct($product)) {
            $variantProducts = $this->makeProductChildrenArray($product, $magentoStoreId);
        } else if ($this->isVirtualProduct($product) || $this->isDownloadableProduct($product)) {
            $variantProducts[] = $product;
        } else {
            //@TODO bundle
            return array();
        }

        $bodyData = $this->_buildProductData($product, $magentoStoreId, false, $variantProducts);
        try {
            $body = json_encode($bodyData, JSON_HEX_APOS|JSON_HEX_QUOT);
        } catch (Exception $e) {
            //json encode failed
            $this->getMailChimpHelper()->logError("Product " . $product->getId() . " json encode failed");
            return array();
        }

        $data = array();
        $data['method'] = "POST";
        $data['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products";
        $data['operation_id'] = $batchId . '_' . $product->getId();
        $data['body'] = $body;
        return $data;
    }

    protected function _buildUpdateProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId)
    {
        $variantProducts = array();
        if ($this->isSimpleProduct($product) || $this->isVirtualProduct($product) || $this->isDownloadableProduct($product)) {
            $variantProducts[] = $product;
            $parentIds = $this->productTypeConfigurableResource->getParentIdsByChild($product->getId());
            foreach ($parentIds as $parentId) {
                $helper = $this->getMailChimpHelper();
                $productSyncDataItem = $helper->getEcommerceSyncDataItem($parentId, Ebizmarts_MailChimp_Model_Config::IS_PRODUCT, $mailchimpStoreId);
                if ($productSyncDataItem->getMailchimpSyncDelta()) {
                    $parent = Mage::getModel('catalog/product')->load($parentId);
                    $variantProducts = $this->makeProductChildrenArray($product, $magentoStoreId, true);
                    $bodyData = $this->_buildProductData($parent, $magentoStoreId, false, $variantProducts);
                    try {
                        $body = json_encode($bodyData, JSON_HEX_APOS|JSON_HEX_QUOT);
                    } catch (Exception $e) {
                        //json encode failed
                        $this->getMailChimpHelper()->logError("Product " . $product->getId() . " json encode failed");
                        return array();
                    }

                    $data = array();
                    $data['method'] = "PATCH";
                    $data['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products/" . $parent->getId();
                    $data['operation_id'] = $batchId . '_' . $parent->getId();
                    $data['body'] = $body;
                    $operations[] = $data;
                }
            }

        } else if ($this->isConfigurableProduct($product)) {
            $variantProducts = $this->makeProductChildrenArray($product, $magentoStoreId, true);
        } else {
            //@TODO bundle
            return array();
        }
        $bodyData = $this->_buildProductData($product, $magentoStoreId, false, $variantProducts);
        try {
            $body = json_encode($bodyData, JSON_HEX_APOS|JSON_HEX_QUOT);
        } catch (Exception $e) {
            //json encode failed
            $this->getMailChimpHelper()->logError("Product " . $product->getId() . " json encode failed");
            return array();
        }

        $data = array();
        $data['method'] = "PATCH";
        $data['path'] = "/ecommerce/stores/" . $mailchimpStoreId . "/products/" . $product->getId();
        $data['operation_id'] = $batchId . '_' . $product->getId();
        $data['body'] = $body;
        $operations[] = $data;
        return $operations;
    }

    protected function _buildProductData($product, $magentoStoreId, $isVariant = true, $variants = array())
    {
        $data = array();

        $productId = $product->getId();
        $helper = $this->getMailChimpHelper();
        $rc = $helper->getProductResourceModel();
        //data applied for both root and varient products
        $data["id"] = $productId;
        $data["title"] = $rc->getAttributeRawValue($productId, 'name', $magentoStoreId);
        $this->_visibility = $rc->getAttributeRawValue($productId, 'visibility', $magentoStoreId);
        $url = null;
        if (!$this->currentProductIsVisible()) {
            $url = $this->getNotVisibleProductUrl($product->getId(), $magentoStoreId);
        } else {
            $url = $this->getProductUrl($product, $magentoStoreId);
        }
        if (!$url) {
            $url = Mage::app()->getStore($magentoStoreId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        }
        $data["url"] = $url;

        //image
        $imageUrl = $this->getMailChimpImageUrl($product, $magentoStoreId);
        if ($imageUrl) {
            $data["image_url"] = $imageUrl;
        }

        //missing data
        $data["published_at_foreign"] = "";

        if ($isVariant) {
            $data += $this->getProductVariantData($product, $magentoStoreId);
        } else {
            $description = $rc->getAttributeRawValue($productId, 'description', $magentoStoreId);
            if (is_string($description)) {
                $data["description"] = $description;
            }

            //mailchimp product type and vendor (magento category)
            $categoryName = $this->getProductCategories($product, $magentoStoreId);
            if ($categoryName) {
                $data["type"] = $categoryName;
                $data["vendor"] = $data["type"];
            }

            //missing data
            $data["handle"] = "";

            //variants
            if (count($variants)) {
                $data["variants"] = array();
                if (isset($data["image_url"])) {
                    $this->_parentImageUrl = $data["image_url"];
                }
                $this->_parentId = $product->getId();
                if ($this->currentProductIsVisible()) {
                    $this->_parentUrl = $data['url'];
                }
                $price = $this->getMailchimpFinalPrice($product);
                if ($price) {
                    $this->_parentPrice = $price;
                }

                foreach ($variants as $variant) {
                    $data["variants"][] = $this->_buildProductData($variant, $magentoStoreId);
                }

                $this->_parentImageUrl = null;
                $this->_parentPrice = null;
                $this->_parentId = null;
                $this->_parentUrl = null;
            }
        }

        return $data;
    }

    /**
     * Get stores to update and call update function after modification.
     *
     * @param $productId
     * @param $mailchimpStoreId
     */
    public function update($productId, $mailchimpStoreId)
    {
        $parentIdArray = $this->getAllParentIds($productId);
        foreach ($parentIdArray as $parentId) {
            $this->_updateSyncData($parentId, $mailchimpStoreId, null, null, 1, 0, null, true, false);
        }
        $this->_updateSyncData($productId, $mailchimpStoreId, null, null, 1, 0, null, true, false);
    }


    /**
     * Get stores to update and call update function after product is disabled.
     *
     * @param $productId
     * @param $mailchimpStoreId
     */
    public function updateDisabledProducts($productId, $mailchimpStoreId)
    {
        $this->_updateSyncData($productId, $mailchimpStoreId, null, '', 0, 1, null, false, false);
    }


    /**
     * Return products belonging to an order or a cart in a valid format to be sent to MailChimp.
     *
     * @param  $order
     * @param  $mailchimpStoreId
     * @param  $magentoStoreId
     * @return array
     */
    public function sendModifiedProduct($order, $mailchimpStoreId, $magentoStoreId)
    {
        $data = array();
        $batchId = $this->makeBatchId($magentoStoreId);
        $items = $order->getAllVisibleItems();
        $helper = $this->getMailChimpHelper();
        $syncDateFlag = $helper->getEcommMinSyncDateFlag($mailchimpStoreId, $magentoStoreId);
        foreach ($items as $item) {
            $itemProductId = $item->getProductId();
            $product = $this->loadProductById($itemProductId);
            $productId = $product->getId();
            $productSyncData = $helper->getEcommerceSyncDataItem($productId, Ebizmarts_MailChimp_Model_Config::IS_PRODUCT, $mailchimpStoreId);
            if ($productId != $itemProductId || $this->isBundleProduct($product) || $this->isGroupedProduct($product)) {
                if ($productId) {
                    $this->_updateSyncData($productId, $mailchimpStoreId, $this->getCurrentDate(), "This product type is not supported on MailChimp.", null, null, 0);
                }
                continue;
            }

            $syncModified = $productSyncData->getMailchimpSyncModified();
            $syncDelta = $productSyncData->getMailchimpSyncDelta();
            $isProductEnabled = $this->isProductEnabled($productId, $magentoStoreId);

            if ($syncModified && $syncDelta > $syncDateFlag && $isProductEnabled) {
                $data = array_merge($this->_buildUpdateProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId), $data);
                $this->_updateSyncData($productId, $mailchimpStoreId);
            } elseif (!$syncDelta || $syncDelta < $syncDateFlag || !$isProductEnabled) {
                $data[] = $this->_buildNewProductRequest($product, $batchId, $mailchimpStoreId, $magentoStoreId);
                // avoid update for disabled products to prevent send the product as modified
                if ($isProductEnabled) {
                    $this->_updateSyncData($productId, $mailchimpStoreId);
                }
            }
        }

        return $data;
    }

    /**
     * update product sync data
     *
     * @param $productId
     * @param $mailchimpStoreId
     * @param int|null $syncDelta
     * @param int|null $syncError
     * @param int|null $syncModified
     * @param int|null $syncDeleted
     * @param int|null $syncedFlag
     * @param bool $saveOnlyIfexists
     * @param bool $allowBatchRemoval
     */
    protected function _updateSyncData($productId, $mailchimpStoreId, $syncDelta = null, $syncError = null, $syncModified = 0, $syncDeleted = null, $syncedFlag = null, $saveOnlyIfexists = false, $allowBatchRemoval = true)
    {
        $this->getMailChimpHelper()->saveEcommerceSyncData(
            $productId,
            Ebizmarts_MailChimp_Model_Config::IS_PRODUCT,
            $mailchimpStoreId,
            $syncDelta,
            $syncError,
            $syncModified,
            $syncDeleted,
            null,
            $syncedFlag,
            $saveOnlyIfexists,
            null,
            $allowBatchRemoval
        );
    }

    /**
     * @param $magentoStoreId
     * @return string
     */
    public function makeBatchId($magentoStoreId)
    {
        $batchId = 'storeid-' . $magentoStoreId . '_' . Ebizmarts_MailChimp_Model_Config::IS_PRODUCT;
        $batchId .= '_' . Mage::helper('mailchimp')->getDateMicrotime();

        return $batchId;
    }

    /**
     * @param $magentoStoreId
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    public function makeProductsNotSentCollection($magentoStoreId, $isParentProduct = false)
    {
        /**
         * @var Mage_Catalog_Model_Resource_Product_Collection $collection
         */
        $collection = $this->getProductResourceCollection();
        if (!$isParentProduct) {
            $collection->addFinalPrice();
        }
        $collection->addStoreFilter($magentoStoreId);
        $this->mailchimpHelper->addResendFilter($collection, $magentoStoreId, Ebizmarts_MailChimp_Model_Config::IS_PRODUCT);

        $this->joinQtyAndBackorders($collection);

        if (!$isParentProduct) {
            $collection->getSelect()->limit($this->getBatchLimitFromConfig());
        }

        return $collection;
    }

    /**
     * @return mixed
     */
    protected function getBatchLimitFromConfig()
    {
        $helper = $this->mailchimpHelper;
        return $helper->getProductAmountLimit();
    }

    /**
     * @return string
     */
    public function getSyncDataTableName()
    {
        $mailchimpTableName = Mage::getSingleton('core/resource')->getTableName('mailchimp/ecommercesyncdata');

        return $mailchimpTableName;
    }

    /**
     * @param $mailchimpStoreId
     * @param $magentoStoreId
     * @param $product
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function shouldSendProductUpdate($mailchimpStoreId, $magentoStoreId, $product)
    {
        $helper = $this->getMailChimpHelper();
        $resendTurn = $helper->getResendTurn($magentoStoreId);
        return !$resendTurn && $product->getMailchimpSyncModified() && $product->getMailchimpSyncDelta() && $product->getMailchimpSyncDelta() > $helper->getEcommMinSyncDateFlag($mailchimpStoreId, $magentoStoreId) && $product->getMailchimpSyncError() == '';
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isSimpleProduct($product)
    {
        return $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isVirtualProduct($product)
    {
        return $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL;
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isDownloadableProduct($product)
    {
        return $product->getTypeId() == "downloadable";
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isBundleProduct($product)
    {
        return $product->getTypeId() == 'bundle';
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isGroupedProduct($product)
    {
        return $product->getTypeId() == 'grouped';
    }

    /**
     * @param $product
     * @return bool
     */
    protected function isConfigurableProduct($product)
    {
        return $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
    }

    /**
     * @param $collection
     */
    public function joinQtyAndBackorders($collection)
    {
        $collection->joinField(
            'qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id',
            '{{table}}.stock_id=1', 'left'
        );

        $collection->joinField(
            'backorders', 'cataloginventory/stock_item', 'backorders', 'product_id=entity_id',
            '{{table}}.stock_id=1', 'left'
        );
    }

    /**
     * This function will perform the join of the collection with the table mailchimp_ecommerce_sync_data when the program
     * creates the batch json to send the product data to mailchimp or to mark the products with an active special price
     *
     * @param $collection
     * @param $mailchimpStoreId
     */
    public function joinMailchimpSyncData($collection, $mailchimpStoreId, $isForSpecialPrice = false)
    {
        $whereCreateBatchJson = "m4m.mailchimp_sync_delta IS null OR m4m.mailchimp_sync_modified = 1";
        $this->joinMailchimpSyncDataWithoutWhere($collection, $mailchimpStoreId, $isForSpecialPrice);
        if ($isForSpecialPrice) {
            $whereMarkSpecialPrice = new Zend_Db_Expr("((IF(at_special_from_date.value_id > 0, at_special_from_date.value, at_special_from_date_default.value) <= '" . date('Y-m-d', time()) . " 23:59:59' AND m4m.mailchimp_sync_delta <  IF(at_special_from_date.value_id > 0, at_special_from_date.value, at_special_from_date_default.value)) OR (IF(at_special_to_date.value_id > 0, at_special_to_date.value, at_special_to_date_default.value) < '" . date('Y-m-d', time()) . "  00:00:00' AND m4m.mailchimp_sync_delta <  IF(at_special_to_date.value_id > 0, at_special_to_date.value, at_special_to_date_default.value)) AND mailchimp_sync_delta IS NOT NULL)");
            $collection->getSelect()->where($whereMarkSpecialPrice);
        } else {
            $collection->getSelect()->where($whereCreateBatchJson);
        }
    }

    /**
     * @param $product
     * @param $magentoStoreId
     * @return mixed
     */
    protected function getProductVariantData($product, $magentoStoreId)
    {
        $data = array();
        $sku = $product->getSku();
        $data["sku"] = $sku ? $sku : '';

        $price = $this->getMailChimpProductPrice($product, $magentoStoreId);
        if ($price) {
            $data["price"] = $price;
        }

        //stock
        $data["inventory_quantity"] = (int)$product->getQty();
        $data["backorders"] = (string)$product->getBackorders();

        $data["visibility"] = $this->getVisibility($this->_visibility);

        return $data;
    }

    /**
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function getProductResourceCollection()
    {
        return Mage::getResourceModel('catalog/product_collection');
    }

    /**
     * @param $product
     * @return array
     */
    protected function getConfigurableChildrenIds($product)
    {
        $childrenIds = $this->getChildrenIdsForConfigurable($product);

        if ($childrenIds === self::$noChildrenIds) {
            return array();
        }

        return $childrenIds;
    }

    /**
     * @param $product
     * @param $magentoStoreId
     * @param bool $isBuildUpdateProductRequest
     * @return array | return an array with the childs of the product passed by parameter
     */
    public function makeProductChildrenArray($product, $magentoStoreId, $isBuildUpdateProductRequest = false)
    {
        $variantProducts[] = $product;
        /**
         * @var Mage_Catalog_Model_Resource_Product_Collection $collection
         */
        $collection = $this->makeProductsNotSentCollection($magentoStoreId, true);

        $childProducts = $this->getConfigurableChildrenIds($product);
        $collection->addAttributeToFilter("entity_id", array("in" => $childProducts));

        foreach ($collection as $childProduct) {
            if ($isBuildUpdateProductRequest) {
                if ($childProduct->getId() != $product->getId()) {
                    $variantProducts[] = $childProduct;
                }
            } else {
                $variantProducts[] = $childProduct;
            }
        }

        return $variantProducts;

    }

    /**
     * @param $product
     * @return array
     */
    protected function getChildrenIdsForConfigurable($product)
    {
        return $this->productTypeConfigurable->getChildrenIds($product->getId());
    }

    /**
     * @return Ebizmarts_MailChimp_Helper_Data
     */
    protected function getMailChimpHelper()
    {
        return $this->mailchimpHelper;
    }

    /**
     * @param $collection
     * @param $mailchimpStoreId
     * @param bool $isForSpecialPrice
     */
    public function joinMailchimpSyncDataWithoutWhere($collection, $mailchimpStoreId, $isForSpecialPrice = false)
    {
        $joinCondition = "m4m.related_id = e.entity_id AND m4m.type = '%s' AND m4m.mailchimp_store_id = '%s'";
        if ($isForSpecialPrice) {
            $joinCondition = $joinCondition . "AND m4m.mailchimp_sync_modified = 0";
        }
        $mailchimpTableName = $this->getSyncDataTableName();
        $collection->getSelect()->joinLeft(
            array("m4m" => $mailchimpTableName),
            sprintf($joinCondition, Ebizmarts_MailChimp_Model_Config::IS_PRODUCT, $mailchimpStoreId), array(
                "m4m.related_id",
                "m4m.type",
                "m4m.mailchimp_store_id",
                "m4m.mailchimp_sync_delta",
                "m4m.mailchimp_sync_modified"
            )
        );
    }

    public function getNotVisibleProductUrl($childId, $magentoStoreId)
    {
        $helper = $this->getMailChimpHelper();
        $parentId = null;
        if (!$this->_parentId) {
            $parentId = $this->getParentId($childId);
        } else {
            $parentId = $this->_parentId;
        }
        if ($parentId) {
            $collection = $this->getProductWithAttributesById($magentoStoreId, $parentId);

            $rc = $helper->getProductResourceModel();
            if ($this->_parentUrl) {
                $url = $this->_parentUrl;
            } else {
                $path = $rc->getAttributeRawValue($parentId, 'url_path', $magentoStoreId);
                $url = $this->getUrlByPath($path, $magentoStoreId);
            }
            $tailUrl = '#';
            $count = 0;
            foreach ($collection as $attribute) {
                if ($attribute->getAttributeId()) {
                    $attributeId = $attribute->getAttributeId();
                    $attributeValue = $rc->getAttributeRawValue($childId, $attribute->getAttributeId(), $magentoStoreId);
                    if ($count > 0) {
                        $tailUrl .= '&';
                    }
                    $tailUrl .= $attributeId . '=' . $attributeValue;
                }
                $count++;
            }
            if ($tailUrl != '#') {
                $url .= $tailUrl;
            }
        } else {
            $url = null;
        }
        return $url;
    }

    public function getParentImageUrl($childId, $magentoStoreId)
    {
        $imageUrl = null;
        $parentId = null;
        if (!$this->_parentId) {
            $parentId = $this->getParentId($childId);
        } else {
            $parentId = $this->_parentId;
        }
        if ($parentId) {
            $helper = $this->getMailChimpHelper();
            $imageUrl = $helper->getImageUrlById($parentId, $magentoStoreId);
        }
        return $imageUrl;
    }

    /**
     * @param $product
     * @param $magentoStoreId
     * @return mixed
     */
    protected function getProductUrl($product, $magentoStoreId)
    {
        $oldStoreId = Mage::app()->getStore()->getId();
        Mage::app()->setCurrentStore($magentoStoreId);
        $url = $product->getProductUrl();
        Mage::app()->setCurrentStore($oldStoreId);
        return $url;
    }

    public function getProductCategories($product, $magentoStoreId)
    {
        $categoryIds = $product->getResource()->getCategoryIds($product);
        $categoryNames = array();
        $categoryName = null;
        if (is_array($categoryIds) && count($categoryIds)) {
            /* @var $collection Mage_Catalog_Model_Resource_Category_Collection */
            $collection = $this->makeCatalogCategory()->getCollection();
            $collection->addAttributeToSelect(array('name'))
                ->setStoreId($magentoStoreId)
                ->addAttributeToFilter('is_active', array('eq' => '1'))
                ->addAttributeToFilter('entity_id', array('in' => $categoryIds))
                ->addAttributeToSort('level', 'asc')
                ->addAttributeToSort('name', 'asc');

            /* @var $category Mage_Catalog_Model_Category */
            foreach ($collection as $category) {
                $categoryNames[] = $category->getName();
            }
            $categoryName = (count($categoryNames)) ? implode(" - ", $categoryNames) : 'None';
        }
        return $categoryName;
    }

    /**
     * @param $childId
     * @return mixed
     */
    protected function getParentId($childId)
    {
        $parentId = null;
        $parentIds = $this->getAllParentIds($childId);
        if (count($parentIds)) {
            $parentId = $parentIds[0];
        }
        return $parentId;
    }

    /**
     * @param $childId
     * @return mixed
     */
    protected function getAllParentIds($childId)
    {
        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($childId);
        return $parentIds;
    }

    /**
     * @param $magentoStoreId
     * @param $parentId
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function getProductWithAttributesById($magentoStoreId, $parentId)
    {
        $tableName = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute');
        $eavTableName = Mage::getSingleton('core/resource')->getTableName('eav/attribute');

        $collection = $this->getProductResourceCollection();
        $collection->addStoreFilter($magentoStoreId);
        $collection->addFieldToFilter('entity_id', array('eq' => $parentId));

        $collection->getSelect()->joinLeft(
            array("super_attribute" => $tableName),
            'entity_id=super_attribute.product_id'
        );

        $collection->getSelect()->joinLeft(
            array("eav_attribute" => $eavTableName),
            'super_attribute.attribute_id=eav_attribute.attribute_id'
        );
        $collection->getSelect()->reset(Zend_Db_Select::COLUMNS)->columns('eav_attribute.attribute_id');
        return $collection;
    }

    /**
     * @param $product
     * @param $magentoStoreId
     * @return mixed|null
     */
    protected function getMailChimpImageUrl($product, $magentoStoreId)
    {
        $imageUrl = $this->getMailChimpHelper()->getMailChimpProductImageUrl($this->_parentImageUrl, $this->getMailChimpHelper()->getImageUrlById($product->getId(), $magentoStoreId));
        if (!$imageUrl) {
            $imageUrl = $this->getParentImageUrl($product->getId(), $magentoStoreId);
        }
        return $imageUrl;
    }

    /**
     * @param $product
     * @param $magentoStoreId
     * @return float
     */
    protected function getMailChimpProductPrice($product, $magentoStoreId)
    {
        $price = null;
        $parentId = null;
        if (!$this->currentProductIsVisible()) {
            $parentId = $this->getParentId($product->getId());
            if ($parentId) {
                $price = $this->getProductPrice($product, $magentoStoreId);
            }
        } else {
            if ($this->_parentPrice) {
                $price = $this->_parentPrice;
            }
        }
        return $price;
    }

    /**
     * @return bool
     */
    protected function currentProductIsVisible()
    {
        return $this->_visibility != Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE;
    }

    protected function getProductPrice($product, $magentoStoreId)
    {
        $helper = $this->getMailChimpHelper();
        $rc = $helper->getProductResourceModel();
        $price = $this->getMailchimpFinalPrice($product);
        return $price;
    }

    /**
     * @param $path
     * @param $magentoStoreId
     * @return string
     */
    protected function getUrlByPath($path, $magentoStoreId)
    {
        $url = Mage::getUrl($path, array('_store' => $magentoStoreId));
        return $url;
    }

    /**
     * @return mixed
     */
    protected function isProductFlatTableEnabled()
    {
        return Mage::helper('catalog/category_flat')->isEnabled();
    }

    /**
     * @return string
     */
    protected function getCurrentDate()
    {
        return Varien_Date::now();
    }

    /**
     * @return false|Mage_Core_Model_Abstract
     */
    protected function makeCatalogCategory()
    {
        return Mage::getModel('catalog/category');
    }

    /**
     * @param $productId
     * @return Mage_Catalog_Model_Product
     */
    protected function loadProductById($productId)
    {
        return Mage::getModel('catalog/product')->load($productId);
    }

    /**
     * @param $mailchimpStoreId
     * @param $deletedProducts
     * @param $mailchimpTableName
     */
    protected function joinMailchimpSyncDataDeleted($mailchimpStoreId, $deletedProducts)
    {
        $mailchimpTableName = $this->getSyncDataTableName();
        $deletedProducts->getSelect()->joinLeft(
            array('m4m' => $mailchimpTableName),
            "m4m.related_id = e.entity_id AND m4m.type = '" . Ebizmarts_MailChimp_Model_Config::IS_PRODUCT . "'
            AND m4m.mailchimp_store_id = '" . $mailchimpStoreId . "'",
            array('m4m.*')
        );
        $deletedProducts->getSelect()->where("m4m.mailchimp_sync_deleted = 1");
        $deletedProducts->getSelect()->where("m4m.mailchimp_sync_error = ''");

        $deletedProducts->getSelect()->limit($this->getBatchLimitFromConfig());
    }

    /**
     * @param string $visibility Visibility.
     * @return int or null
     */
    protected function getVisibility($visibility)
    {
        if (array_key_exists($visibility, $this->visibilityOptions)) {
            return $this->visibilityOptions[$visibility];
        }

        return null;
    }

    /**
     * Return price with tax if setting enabled.
     *
     * @param $product
     * @return float \ return the price of the product
     * @throws Mage_Core_Exception
     */
    protected function getMailchimpFinalPrice($product)
    {
        $helper = $this->getMailChimpHelper();
        $price = Mage::helper('tax')->getPrice($product, $product->getFinalPrice(), $helper->isIncludeTaxesEnabled());

        return $price;
    }

    /**
     * Sync to mailchimp the special price of the products
     *
     * @param $mailchimpStoreId
     * @param $magentoStoreId
     */
    public function _markSpecialPrices($mailchimpStoreId, $magentoStoreId)
    {
        /**
         * get the products with current special price that are not synced and mark it as modified
         * get the products that was synced when it have special price and have no more special price
         */
        $collection = $this->getProductResourceCollection();
        $collection->addStoreFilter($magentoStoreId);

        $collection->addAttributeToFilter(
            'special_price',
            array('notnull' => true),
            'left'
        )->addAttributeToFilter(
            'special_from_date',
            array('notnull' => true),
            'left'
        )->addAttributeToSelect(
            'special_to_date',
            'left'
        );

        $this->joinMailchimpSyncData($collection, $mailchimpStoreId, true);

        foreach ($collection as $item) {
            $this->update($item->getEntityId(), $mailchimpStoreId);
        }
    }

    /**
     * @param $productId
     * @return bool | return true if the product is enabled in Magento.
     */
    public function isProductEnabled($productId, $magentoStoreId)
    {
        $isProductEnabled = false;
        $status = $this->getCatalogProductStatusModel()->getProductStatus($productId, $magentoStoreId);
        if ($status[$productId] == self::PRODUCT_IS_ENABLED) {
            $isProductEnabled = true;
        }

        return $isProductEnabled;
    }

    /**
     * @return Mage_Catalog_Model_Product_Status
     */
    protected function getCatalogProductStatusModel()
    {
        return Mage::getModel('catalog/product_status');
    }
}
