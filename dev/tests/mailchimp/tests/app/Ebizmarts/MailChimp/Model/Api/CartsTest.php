<?php
class Ebizmarts_MailChimp_Model_Api_CartsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Ebizmarts_MailChimp_Model_Api_Carts
     */
    private $cartsApiMock;
    const DATE = '2017-05-18-14-45-54-38849500';
    const BATCH_ID = 'storeid-1_QUO_2017-05-18-14-45-54-38849500';
    const MAILCHIMP_STORE_ID = '3ade9d9e52e35e9b18d95bdd4d9e9a44';
    const BATCH_LIMIT_FROM_CONFIG = 100;
    const MAGENTO_STORE_ID = 1;
    const ALREADY_SENT_CART_ID = 2;
    const WEB_SITE_ID_FROM_MAGENTO_STORE_ID = 0;
    const CUSTOMER_EMAIL_BY_CART = 'test@ebizmarts.com';
    const CART_ID = 1;
    const COUNTER = 0;
    const STRING_IS_ACTIVE = 'is_active';
    const STRING_STORE_ID = 'store_id';

    public function setUp()
    {
        Mage::app('default');
        $this->cartsApiMock = $this->getMockBuilder(Ebizmarts_MailChimp_Model_Api_Carts::class);
    }

    public function tearDown()
    {
        $this->cartsApiMock = null;
    }

    public function testCreateBatchJson()
    {
        $batchArray = array();
        $cartsApiMock = $this->cartsApiMock->setMethods(array(
            'getHelper',
            '_getConvertedQuotes',
            '_getModifiedQuotes',
            '_getNewQuotes',
            'setBatchId'
        ))
            ->getMock();
        $helperMock = $this->getMockBuilder(Ebizmarts_MailChimp_Helper_Data::class)
            ->disableOriginalConstructor()
            ->setMethods(array('isAbandonedCartEnabled', 'getAbandonedCartFirstDate', 'getDateMicrotime', 'getResendTurn'))
            ->getMock();
        $cartsApiMock->expects($this->once())->method('setBatchId')->with(self::BATCH_ID);
        $this->cartsApiGetHelper($helperMock, $cartsApiMock);
        $cartsApiMock->expects($this->once())->method('_getConvertedQuotes')->with(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID)->willReturn($batchArray);
        $cartsApiMock->expects($this->once())->method('_getModifiedQuotes')->with(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID)->willReturn($batchArray);
        $cartsApiMock->expects($this->once())->method('_getNewQuotes')->with(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID)->willReturn($batchArray);
        $helperMock->expects($this->once())->method('isAbandonedCartEnabled')->with(self::MAGENTO_STORE_ID)->willReturn(true);
        $helperMock->expects($this->once())->method('getAbandonedCartFirstDate')->with(self::MAGENTO_STORE_ID)->willReturn('00-00-00 00:00:00');
        $helperMock->expects($this->once())->method('getDateMicrotime')->willReturn(self::DATE);
        $helperMock->expects($this->once())->method('getResendTurn')->with(self::MAGENTO_STORE_ID)->willReturn(null);
        $cartsApiMock->createBatchJson(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetConvertedQuotes ()
    {
        $mailchimpTableName = 'mailchimp_ecommerce_sync_data';
        $arrayAddFieldToFilter = array('eq' => 0);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_deleted = 0";
        $arrayTableName = array('m4m' => $mailchimpTableName);
        $conditionSelect = "m4m.related_id = main_table.entity_id and m4m.type = '" . Ebizmarts_MailChimp_Model_Config::IS_QUOTE . "'
            AND m4m.mailchimp_store_id = '" . self::MAILCHIMP_STORE_ID . "'";
        $m4m = array('m4m.*');
        $cartByEmailModelMock = $this->cartByEmailModelMock();
        $varienSelectMock = $this->varienSelectMockModifiedCart($arrayTableName, $conditionSelect, $m4m, $where);
        $cartModelMock = $this
            ->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityId', 'getCustomerEmail'))
            ->getMock();
        $cartModelMock->expects($this->once())
            ->method('getCustomerEmail')
            ->willReturnOnConsecutiveCalls(self::CUSTOMER_EMAIL_BY_CART);
        $this->cartModelGetEntityId($cartModelMock);
        $newCartsCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Quote_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('addFieldToFilter', 'getSelect', 'getIterator'))
            ->getMock();
        $newCartsCollectionMock->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->withConsecutive(
                array(self::STRING_STORE_ID, $arrayAddFieldToFilterStoreId),
                array(self::STRING_IS_ACTIVE, $arrayAddFieldToFilter)
            );
        $this->newCartsCollectionGetSelectExactlyThree($varienSelectMock, $newCartsCollectionMock);
        $this->newCartsCollectionGetIterator($cartModelMock, $newCartsCollectionMock);
        $quoteByEmailResoureceCollectionMock = $this->quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock);
        $cartsApiMock = $this->cartsApiMock->setMethods(array(
            'getMailchimpEcommerceDataTableName',
            'getQuoteCollection',
            'getBatchLimitFromConfig',
            '_getAllCartsByEmail',
            'getCounter',
            'getBatchId',
            '_updateSyncData',
            'setCounter'
        ))
            ->getMock();
        $cartsApiMock->expects($this->once())
            ->method('getMailchimpEcommerceDataTableName')
            ->willReturn($mailchimpTableName);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock);
        $this->cartsApiGetCounterExactlyFour($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $cartsApiMock->expects($this->exactly(2))
            ->method('_updateSyncData')
            ->withConsecutive(
                array(self::ALREADY_SENT_CART_ID, self::MAILCHIMP_STORE_ID, null, null, null, null, 1),
                array(self::CART_ID, self::MAILCHIMP_STORE_ID,  null, null, null, null, 1)
            );
        $this->cartsApiSetCounterExactlyTwo($cartsApiMock);
        $cartsApiMock->_getConvertedQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetModifiedQuotes()
    {
        $mcTableName = 'mailchimp_ecommerce_sync_data';
        $customerEmailAddress = '';
        $cartJson = '{"id":"692","customer":{"id":"GUEST-2018-11-30-20-00-07-96938700","email_address":"test@ebizmarts.com","opt_in_status":false,"first_name":"Lucia","last_name":"en el checkout","address":{"address1":"asdf","city":"asd","postal_code":"212312","country":"Tajikistan","country_code":"TJ"}},"campaign_id":"482d28ee12","checkout_url":"http:\/\/f3364930.ngrok.io\/mailchimp\/cart\/loadquote\?id=692&token=ec4f79b2e4677d2edc5bf78c934e5794","currency_code":"USD","order_total":"1700.0000","tax_total":0,"lines":[{"id":"1","product_id":"425","product_variant_id":"310","quantity":5,"price":"1700.0000"}]}';
        $customerId = 1;
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_deleted = 0
        AND m4m.mailchimp_sync_delta < updated_at";
        $arrayTableName = array('m4m' => $mcTableName);
        $conditionSelect = "m4m.related_id = main_table.entity_id and m4m.type = '" . Ebizmarts_MailChimp_Model_Config::IS_QUOTE . "'
            AND m4m.mailchimp_store_id = '" . self::MAILCHIMP_STORE_ID . "'";
        $m4m = array('m4m.*');
        $allCarts = array(array('method' => 'DELETE', 'path' => '/ecommerce/stores/'.self::MAILCHIMP_STORE_ID.'/carts/'. self::ALREADY_SENT_CART_ID, 'operation_id' => self::BATCH_ID . '_' . self::ALREADY_SENT_CART_ID, 'body' => ''));
        $token = 'ec4f79b2e4677d2edc5bf78c934e5794';
        $customerModelMock = $this->customerModelMockNewQuotes($customerEmailAddress);
        $varienSelectMock = $this->varienSelectMockModifiedCart($arrayTableName, $conditionSelect, $m4m, $where);
        $cartModelMock = $this->cartModelMockModifiedCart($customerId);
        $newCartsCollectionMock = $this->newCartsCollectionMockModifiedQuotes($arrayAddFieldToFilter, $arrayAddFieldToFilterStoreId, $varienSelectMock, $cartModelMock);
        $cartByEmailModelMock = $this->cartByEmailModelMock();
        $quoteByEmailResoureceCollectionMock = $this->quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock);
        $cartsApiMock = $this->cartsApiMockModifiedQuotes($token, $mcTableName, $newCartsCollectionMock, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $cartJson, $allCarts);
        $cartsApiMock->_getModifiedQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetModifiedQuotesGuestCustomer()
    {
        $mcTableName = 'mailchimp_ecommerce_sync_data';
        $customerId = '';
        $customerEmailAddress = 'test@ebizmarts.com';
        $stringStoreId = 'store_id';
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_deleted = 0
        AND m4m.mailchimp_sync_delta < updated_at";
        $arrayTableName = array('m4m' => $mcTableName);
        $conditionSelect = "m4m.related_id = main_table.entity_id and m4m.type = '" . Ebizmarts_MailChimp_Model_Config::IS_QUOTE . "'
            AND m4m.mailchimp_store_id = '" . self::MAILCHIMP_STORE_ID . "'";
        $m4m = array('m4m.*');
        $varienSelectMock = $this->modifiedQuotesGuestCustomer($where, $arrayTableName, $conditionSelect, $m4m);
        $cartModelMock = $this->cartModelMockModifiedCart($customerId);
        $newCartsCollectionMock = $this->newCartsModifiedQuotesGuestCustomer($arrayAddFieldToFilter, $stringStoreId, $arrayAddFieldToFilterStoreId, $varienSelectMock, $cartModelMock);
        $customerModelMock = $this->customerModelMockSetWebSiteIdLoadByEmail();
        $this->customerModelMockGetEmail($customerModelMock, $customerEmailAddress);
        $cartsApiMock = $this->cartsApiMockModifiedQuotesGuestCustomer($mcTableName, $newCartsCollectionMock, $customerModelMock);
        $cartsApiMock->_getModifiedQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetModifiedQuotesEmptyJson()
    {
        $mcTableName = 'mailchimp_ecommerce_sync_data';
        $customerEmailAddress = '';
        $cartJson = '';
        $customerId = 1;
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_deleted = 0
        AND m4m.mailchimp_sync_delta < updated_at";
        $arrayTableName = array('m4m' => $mcTableName);
        $conditionSelect = "m4m.related_id = main_table.entity_id and m4m.type = '" . Ebizmarts_MailChimp_Model_Config::IS_QUOTE . "'
            AND m4m.mailchimp_store_id = '" . self::MAILCHIMP_STORE_ID . "'";
        $m4m = array('m4m.*');
        $allCarts = array(array('method' => 'DELETE', 'path' => '/ecommerce/stores/'.self::MAILCHIMP_STORE_ID.'/carts/'. self::ALREADY_SENT_CART_ID, 'operation_id' => self::BATCH_ID . '_' . self::ALREADY_SENT_CART_ID, 'body' => ''));
        $cartByEmailModelMock = $this->cartByEmailModelMock();
        $varienSelectMock = $this->varienSelectMockModifiedCart($arrayTableName, $conditionSelect, $m4m, $where);
        $cartModelMock = $this->cartModelMockModifiedCart($customerId);
        $newCartsCollectionMock = $this->newCartsCollectionMockModifiedQuotes($arrayAddFieldToFilter, $arrayAddFieldToFilterStoreId, $varienSelectMock, $cartModelMock);
        $customerModelMock = $this->customerModelMockNewQuotes($customerEmailAddress);
        $quoteByEmailResoureceCollectionMock = $this->quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock);
        $cartsApiMock = $this->modifiedQuotesEmptyJson($mcTableName, $newCartsCollectionMock, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $cartJson, $allCarts);
        $cartsApiMock->_getModifiedQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetNewQuotesNewQuote()
    {
        $existFirstDate = '2018-11-30';
        $customerId = 1;
        $token = 'ec4f79b2e4677d2edc5bf78c934e5794';
        $customerEmailAddress = '';
        $allCarts = array(array('method' => 'DELETE', 'path' => '/ecommerce/stores/'.self::MAILCHIMP_STORE_ID.'/carts/'. self::ALREADY_SENT_CART_ID, 'operation_id' => self::BATCH_ID . '_' . self::ALREADY_SENT_CART_ID, 'body' => ''));
        $cartJson = '{"id":"692","customer":{"id":"GUEST-2018-11-30-20-00-07-96938700","email_address":"test@ebizmarts.com","opt_in_status":false,"first_name":"Lucia","last_name":"en el checkout","address":{"address1":"asdf","city":"asd","postal_code":"212312","country":"Tajikistan","country_code":"TJ"}},"campaign_id":"482d28ee12","checkout_url":"http:\/\/f3364930.ngrok.io\/mailchimp\/cart\/loadquote\?id=692&token=ec4f79b2e4677d2edc5bf78c934e5794","currency_code":"USD","order_total":"1700.0000","tax_total":0,"lines":[{"id":"1","product_id":"425","product_variant_id":"310","quantity":5,"price":"1700.0000"}]}';
        $stringCustomerEmail = 'customer_email';
        $stringItemsCount = 'items_count';
        $stringUpdatedAt = 'updated_at';
        $arrayAddFieldToFilterUpdatedAt = array('gt' => $existFirstDate);
        $arrayAddFieldToFilterItemsCount = array('gt' => 0);
        $arrayAddFieldToFilterCustomerEmail = array('notnull' => true);
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_delta IS NULL";
        $allVisbleItems = array('item');
        $sizeOrderCollection = 0;
        $addFieldToFilterOrderCollection = array('eq' => self::CUSTOMER_EMAIL_BY_CART);
        $stringCustomerEmailMainTable = 'main_table.customer_email';
        $stringUpdated = 'main_table.updated_at';
        $addFieldToFilterUpdated = array('from' => '');
        $cartModelMock = $this->cartModelMockNewQuotes($customerId, $allVisbleItems);
        $varienSelectMock = $this->varienSelectMockNewQuotes($where);
        $newCartsCollectionMock = $this->newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock);
        $helperMock = $this->helperMockNewQuotes($newCartsCollectionMock);
        $customerModelMock = $this->customerModelMockNewQuotes($customerEmailAddress);
        $cartByEmailModelMock = $this->cartByEmailModelMock();
        $quoteByEmailResoureceCollectionMock = $this->quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock);
        $orderCollectionMock = $this->orderCollectionMock($sizeOrderCollection, $stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated);
        $cartsApiMock = $this->cartsApiMockNewQuote($helperMock, $newCartsCollectionMock, $existFirstDate, $token, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $allCarts, $cartJson, $orderCollectionMock);
        $cartsApiMock->_getNewQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetNewQuotesIsOrder()
    {
        $existFirstDate = '2018-11-30';
        $stringCustomerEmail = 'customer_email';
        $stringItemsCount = 'items_count';
        $stringUpdatedAt = 'updated_at';
        $arrayAddFieldToFilterUpdatedAt = array('gt' => $existFirstDate);
        $arrayAddFieldToFilterItemsCount = array('gt' => 0);
        $arrayAddFieldToFilterCustomerEmail = array('notnull' => true);
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_delta IS NULL";
        $allVisbleItems = array('item');
        $sizeOrderCollection = 1;
        $addFieldToFilterOrderCollection = array('eq' => '');
        $stringCustomerEmailMainTable = 'main_table.customer_email';
        $stringUpdated = 'main_table.updated_at';
        $addFieldToFilterUpdated = array('from' => '');
        $orderCollectionMock = $this->orderCollectionMock($sizeOrderCollection, $stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated);
        $varienSelectMock = $this->varienSelectMockNewQuotes($where);
        $cartModelMock = $this->cartModelMockEmptyQuote($allVisbleItems);
        $newCartsCollectionMock = $this->newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock);
        $helperMock = $this->helperMockNewQuotes($newCartsCollectionMock);
        $cartsApiMock = $this->cartsApiMockEmptyQuotes($helperMock, $newCartsCollectionMock, $existFirstDate, $orderCollectionMock);
        $cartsApiMock->_getNewQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetNewQuotesEmpty()
    {
        $existFirstDate = '2018-11-30';
        $stringCustomerEmail = 'customer_email';
        $stringItemsCount = 'items_count';
        $stringUpdatedAt = 'updated_at';
        $arrayAddFieldToFilterUpdatedAt = array('gt' => $existFirstDate);
        $arrayAddFieldToFilterItemsCount = array('gt' => 0);
        $arrayAddFieldToFilterCustomerEmail = array('notnull' => true);
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_delta IS NULL";
        $allVisbleItems = array();
        $addFieldToFilterOrderCollection = array('eq' => '');
        $stringCustomerEmailMainTable = 'main_table.customer_email';
        $stringUpdated = 'main_table.updated_at';
        $addFieldToFilterUpdated = array('from' => '');
        $cartModelMock = $this->cartModelMockEmptyQuote($allVisbleItems);
        $varienSelectMock = $this->varienSelectMockNewQuotes($where);
        $newCartsCollectionMock = $this->newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock);
        $helperMock = $this->helperMockNewQuotes($newCartsCollectionMock);
        $orderCollectionMock = $this->orderCollectionMockEmptyQuotes($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated);
        $cartsApiMock = $this->cartsApiMockEmptyQuotes($helperMock, $newCartsCollectionMock, $existFirstDate, $orderCollectionMock);
        $cartsApiMock->_getNewQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetNewQuotesGuestCustomer()
    {
        $existFirstDate = '2018-11-30';
        $customerId = '';
        $customerEmailAddress = 'test@ebizmarts.com';
        $stringCustomerEmail = 'customer_email';
        $stringItemsCount = 'items_count';
        $stringUpdatedAt = 'updated_at';
        $arrayAddFieldToFilterUpdatedAt = array('gt' => $existFirstDate);
        $arrayAddFieldToFilterItemsCount = array('gt' => 0);
        $arrayAddFieldToFilterCustomerEmail = array('notnull' => true);
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_delta IS NULL";
        $allVisbleItems = array('item');
        $sizeOrderCollection = 0;
        $addFieldToFilterOrderCollection = array('eq' => self::CUSTOMER_EMAIL_BY_CART);
        $stringCustomerEmailMainTable = 'main_table.customer_email';
        $stringUpdated = 'main_table.updated_at';
        $addFieldToFilterUpdated = array('from' => '');
        $varienSelectMock = $this->varienSelectMockNewQuotes($where);
        $cartModelMock = $this->cartModelMockNewQuotes($customerId, $allVisbleItems);
        $orderCollectionMock = $this->orderCollectionMock($sizeOrderCollection, $stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated);
        $newCartsCollectionMock = $this->newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock);
        $helperMock = $this->helperMockNewQuotes($newCartsCollectionMock);
        $customerModelMock = $this->customerModelMockSetWebSiteIdLoadByEmail();
        $this->customerModelMockGetEmail($customerModelMock, $customerEmailAddress);
        $cartsApiMock = $this->cartsApiMockGuestCustomer($helperMock, $newCartsCollectionMock, $existFirstDate, $customerModelMock, $orderCollectionMock);
        $cartsApiMock->_getNewQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    public function testGetNewQuotesEmptyJson()
    {
        $existFirstDate = '2018-11-30';
        $customerId = 1;
        $customerEmailAddress = '';
        $allCarts = array(array('method' => 'DELETE', 'path' => '/ecommerce/stores/'.self::MAILCHIMP_STORE_ID.'/carts/'. self::ALREADY_SENT_CART_ID, 'operation_id' => self::BATCH_ID . '_' . self::ALREADY_SENT_CART_ID, 'body' => ''));
        $cartJson = '';
        $stringCustomerEmail = 'customer_email';
        $stringItemsCount = 'items_count';
        $stringUpdatedAt = 'updated_at';
        $arrayAddFieldToFilterUpdatedAt = array('gt' => $existFirstDate);
        $arrayAddFieldToFilterItemsCount = array('gt' => 0);
        $arrayAddFieldToFilterCustomerEmail = array('notnull' => true);
        $arrayAddFieldToFilter = array('eq' => 1);
        $arrayAddFieldToFilterStoreId = array('eq' => self::MAGENTO_STORE_ID);
        $where = "m4m.mailchimp_sync_delta IS NULL";
        $allVisbleItems = array('item');
        $sizeOrderCollection = 0;
        $addFieldToFilterOrderCollection = array('eq' => self::CUSTOMER_EMAIL_BY_CART);
        $stringCustomerEmailMainTable = 'main_table.customer_email';
        $stringUpdated = 'main_table.updated_at';
        $addFieldToFilterUpdated = array('from' => '');
        $customerModelMock = $this->customerModelMockNewQuotes($customerEmailAddress);
        $cartModelMock = $this->cartModelMockNewQuotes($customerId, $allVisbleItems);
        $varienSelectMock = $this->varienSelectMockNewQuotes($where);
        $newCartsCollectionMock = $this->newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock);
        $helperMock = $this->helperMockNewQuotes($newCartsCollectionMock);
        $cartByEmailModelMock = $this->cartByEmailModelMock();
        $quoteByEmailResoureceCollectionMock = $this->quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock);
        $orderCollectionMock = $this->orderCollectionMock($sizeOrderCollection, $stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated);
        $cartsApiMock = $this->cartsApiMockNewQuotesEmptyJson($helperMock, $newCartsCollectionMock, $existFirstDate, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $allCarts, $cartJson, $orderCollectionMock);
        $cartsApiMock->_getNewQuotes(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID);
    }

    /**
     * @param $customerEmailAddress
     * @return mixed
     */
    protected function customerModelMockNewQuotes($customerEmailAddress)
    {
        $customerModelMock = $this->customerModelMockSetWebSiteIdLoadByEmail();
        $this->customerModelGetEmail($customerEmailAddress, $customerModelMock);
        return $customerModelMock;
    }

    /**
     * @param $customerId
     * @param $allVisbleItems
     * @return mixed
     */
    protected function cartModelMockNewQuotes($customerId, $allVisbleItems)
    {
        $cartModelMock = $this
            ->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityId', 'getCustomerEmail', 'getCustomerId', 'getAllVisibleItems'))
            ->getMock();
        $this->cartModelGetCustomerEmailExactlyFour($cartModelMock);
        $this->cartModelGetEntityId($cartModelMock);
        $this->cartModelGetCustomerId($customerId, $cartModelMock);
        $this->cartModelGetAllVisibleItems($allVisbleItems, $cartModelMock);
        return $cartModelMock;
    }

    /**
     * @param $where
     * @return mixed
     */
    protected function varienSelectMockNewQuotes($where)
    {
        $varienSelectMock = $this
            ->getMockBuilder(Varien_Db_Select::class)
            ->disableOriginalConstructor()
            ->setMethods(array('where', 'limit'))
            ->getMock();
        $this->varienSelectWhere($where, $varienSelectMock);
        $this->varienSelectLimit($varienSelectMock);
        return $varienSelectMock;
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $stringCustomerEmail
     * @param $arrayAddFieldToFilterCustomerEmail
     * @param $stringItemsCount
     * @param $arrayAddFieldToFilterItemsCount
     * @param $arrayAddFieldToFilterStoreId
     * @param $stringUpdatedAt
     * @param $arrayAddFieldToFilterUpdatedAt
     * @param $varienSelectMock
     * @param $cartModelMock
     * @return mixed
     */
    protected function newCartsCollectionMockNewQuotes($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $varienSelectMock, $cartModelMock)
    {
        $newCartsCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Quote_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('addFieldToFilter', 'getSelect', 'getIterator'))
            ->getMock();
        $this->newCartsCollectionAddFieldToFilterExactlyFive($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $newCartsCollectionMock);
        $this->newCartsCollectionGetSelectExactlyTwo($varienSelectMock, $newCartsCollectionMock);
        $this->newCartsCollectionGetIterator($cartModelMock, $newCartsCollectionMock);
        return $newCartsCollectionMock;
    }

    /**
     * @param $newCartsCollectionMock
     * @return mixed
     */
    protected function helperMockNewQuotes($newCartsCollectionMock)
    {
        $helperMock = $this
            ->getMockBuilder(Ebizmarts_MailChimp_Helper_Data::class)
            ->setMethods(array('addResendFilter'))
            ->getMock();
        $this->helperAddFieldToFilter($newCartsCollectionMock, $helperMock);
        return $helperMock;
    }

    /**
     * @return mixed
     */
    protected function cartByEmailModelMock()
    {
        $cartByEmailModelMock = $this
            ->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityId'))
            ->getMock();
        $this->cartByEmailGetEntityId($cartByEmailModelMock);
        return $cartByEmailModelMock;
    }

    /**
     * @param $cartByEmailModelMock
     * @return mixed
     */
    protected function quoteByEmailResourceCollectionMockNewQuotes($cartByEmailModelMock)
    {
        $quoteByEmailResoureceCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Quote_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('clear', 'getIterator'))
            ->getMock();
        $this->quoteByEmailClear($quoteByEmailResoureceCollectionMock);
        $this->quoteByEmailGetIterator($cartByEmailModelMock, $quoteByEmailResoureceCollectionMock);
        return $quoteByEmailResoureceCollectionMock;
    }

    /**
     * @param $sizeOrderCollection
     * @param $stringCustomerEmailMainTable
     * @param $addFieldToFilterOrderCollection
     * @param $stringUpdated
     * @param $addFieldToFilterUpdated
     * @return mixed
     */
    protected function orderCollectionMock($sizeOrderCollection, $stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated)
    {
        $orderCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Order_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getSize', 'addFieldToFilter'))
            ->getMock();
        $this->orderCollectionGetSize($sizeOrderCollection, $orderCollectionMock);
        $this->orderCollectionAddFieldToFilterExactlyTwo($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated, $orderCollectionMock);
        return $orderCollectionMock;
    }

    /**
     * @param $helperMock
     * @param $newCartsCollectionMock
     * @param $existFirstDate
     * @param $customerModelMock
     * @param $quoteByEmailResoureceCollectionMock
     * @param $cartModelMock
     * @param $allCarts
     * @param $cartJson
     * @param $orderCollectionMock
     * @return mixed
     */
    protected function cartsApiMockNewQuotesEmptyJson($helperMock, $newCartsCollectionMock, $existFirstDate, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $allCarts, $cartJson, $orderCollectionMock)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'getHelper',
                'getQuoteCollection',
                'getFirstDate',
                'joinMailchimpSyncDataWithoutWhere',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                '_getAllCartsByEmail',
                'getCounter',
                'getBatchId',
                'setCounter',
                'addProductNotSentData',
                '_makeCart',
                'setToken',
                'getOrderCollection'
            ))
            ->getMock();
        $this->cartsApiGetHelper($helperMock, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetFirstDateExactlyTwo($existFirstDate, $cartsApiMock);
        $this->cartsApiJoinMailchimpSyncDataWithoutWhere($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiUpdateSyncDataExactlyTwice($cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock);
        $this->cartsApiGetCounterExactlyTwo($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartsApiSetCounter($cartsApiMock);
        $this->cartsApiAddProductNotSentData($cartModelMock, $allCarts, $cartsApiMock);
        $this->cartsApiMakeCartExactlyOne($cartModelMock, $cartJson, $cartsApiMock);
        $this->cartsApiSetToken($cartsApiMock);
        $this->cartApiGetOrderCollection($orderCollectionMock, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @return mixed
     */
    protected function customerModelMockSetWebSiteIdLoadByEmail()
    {
        $customerModelMock = $this
            ->getMockBuilder(Mage_Customer_Model_Customer::class)
            ->disableOriginalConstructor()
            ->setMethods(array('setWebsiteId', 'loadByEmail', 'getEmail'))
            ->getMock();
        $this->customerModelSetWebsiteId($customerModelMock);
        $this->customerModelLoadByEmail($customerModelMock);
        return $customerModelMock;
    }

    /**
     * @param $customerModelMock
     * @param $customerEmailAddress
     */
    protected function customerModelMockGetEmail($customerModelMock, $customerEmailAddress)
    {
        $customerModelMock->expects($this->exactly(2))
            ->method('getEmail')
            ->willReturnOnConsecutiveCalls(
                $customerEmailAddress,
                $customerEmailAddress
            );
    }

    /**
     * @param $helperMock
     * @param $newCartsCollectionMock
     * @param $existFirstDate
     * @param $customerModelMock
     * @param $orderCollectionMock
     * @return mixed
     */
    protected function cartsApiMockGuestCustomer($helperMock, $newCartsCollectionMock, $existFirstDate, $customerModelMock, $orderCollectionMock)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'getHelper',
                'getQuoteCollection',
                'getFirstDate',
                'joinMailchimpSyncDataWithoutWhere',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                'getBatchId',
                'getOrderCollection'
            ))
            ->getMock();
        $this->cartsApiGetHelper($helperMock, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetFirstDateExactlyTwo($existFirstDate, $cartsApiMock);
        $this->cartsApiJoinMailchimpSyncDataWithoutWhere($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiUpdateSyncData($cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartApiGetOrderCollection($orderCollectionMock, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $helperMock
     * @param $newCartsCollectionMock
     * @param $existFirstDate
     * @param $orderCollectionMock
     * @return mixed
     */
    protected function cartsApiMockEmptyQuotes($helperMock, $newCartsCollectionMock, $existFirstDate, $orderCollectionMock)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'getHelper',
                'getQuoteCollection',
                'getFirstDate',
                'joinMailchimpSyncDataWithoutWhere',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getOrderCollection'
            ))
            ->getMock();
        $this->cartsApiGetHelper($helperMock, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetFirstDateExactlyTwo($existFirstDate, $cartsApiMock);
        $this->cartsApiJoinMailchimpSyncDataWithoutWhere($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiUpdateSyncData($cartsApiMock);
        $this->cartApiGetOrderCollection($orderCollectionMock, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $stringCustomerEmailMainTable
     * @param $addFieldToFilterOrderCollection
     * @param $stringUpdated
     * @param $addFieldToFilterUpdated
     * @return mixed
     */
    protected function orderCollectionMockEmptyQuotes($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated)
    {
        $orderCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Order_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('addFieldToFilter'))
            ->getMock();
        $this->orderCollectionAddFieldToFilterExactlyTwo($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated, $orderCollectionMock);
        return $orderCollectionMock;
    }

    /**
     * @param $allVisbleItems
     * @return mixed
     */
    protected function cartModelMockEmptyQuote($allVisbleItems)
    {
        $cartModelMock = $this
            ->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityId', 'getAllVisibleItems'))
            ->getMock();
        $this->cartModelGetEntityId($cartModelMock);
        $this->cartModelGetAllVisibleItems($allVisbleItems, $cartModelMock);
        return $cartModelMock;
    }

    /**
     * @param $helperMock
     * @param $newCartsCollectionMock
     * @param $existFirstDate
     * @param $token
     * @param $customerModelMock
     * @param $quoteByEmailResoureceCollectionMock
     * @param $cartModelMock
     * @param $allCarts
     * @param $cartJson
     * @param $orderCollectionMock
     * @return mixed
     */
    protected function cartsApiMockNewQuote($helperMock, $newCartsCollectionMock, $existFirstDate, $token, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $allCarts, $cartJson, $orderCollectionMock)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'getHelper',
                'getQuoteCollection',
                'getFirstDate',
                'joinMailchimpSyncDataWithoutWhere',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                '_getAllCartsByEmail',
                'getCounter',
                'getBatchId',
                'setCounter',
                'addProductNotSentData',
                '_makeCart',
                'setToken',
                'getToken',
                'getOrderCollection'
            ))
            ->getMock();
        $this->cartsApiGetHelper($helperMock, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetFirstDateExactlyTwo($existFirstDate, $cartsApiMock);
        $this->cartsApiJoinMailchimpSyncDataWithoutWhere($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiUpdateSyncDataExactlyTwo($token, $cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock);
        $this->cartsApiGetCounterExactlyFour($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartsApiSetCounterExactlyTwo($cartsApiMock);
        $this->cartsApiAddProductNotSentData($cartModelMock, $allCarts, $cartsApiMock);
        $this->cartsApiMakeCartExactlyOne($cartModelMock, $cartJson, $cartsApiMock);
        $this->cartsApiGetToken($token, $cartsApiMock);
        $this->cartsApiSetToken($cartsApiMock);
        $this->cartApiGetOrderCollection($orderCollectionMock, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $arrayTableName
     * @param $conditionSelect
     * @param $m4m
     * @param $where
     * @return mixed
     */
    protected function varienSelectMockModifiedCart($arrayTableName, $conditionSelect, $m4m, $where)
    {
        $varienSelectMock = $this
            ->getMockBuilder(Varien_Db_Select::class)
            ->disableOriginalConstructor()
            ->setMethods(array('joinLeft', 'where', 'limit'))
            ->getMock();
        $this->varienSelectJoinLeft($arrayTableName, $conditionSelect, $m4m, $varienSelectMock);
        $this->varienSelectWhere($where, $varienSelectMock);
        $this->varienSelectLimit($varienSelectMock);
        return $varienSelectMock;
    }

    /**
     * @param $customerId
     * @return mixed
     */
    protected function cartModelMockModifiedCart($customerId)
    {
        $cartModelMock = $this
            ->getMockBuilder(Mage_Sales_Model_Quote::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getEntityId', 'getCustomerEmail', 'getCustomerId'))
            ->getMock();
        $this->cartModelGetCustomerEmailExactlyThree($cartModelMock);
        $this->cartModelGetEntityId($cartModelMock);
        $this->cartModelGetCustomerId($customerId, $cartModelMock);
        return $cartModelMock;
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $arrayAddFieldToFilterStoreId
     * @param $varienSelectMock
     * @param $cartModelMock
     * @return mixed
     */
    protected function newCartsCollectionMockModifiedQuotes($arrayAddFieldToFilter, $arrayAddFieldToFilterStoreId, $varienSelectMock, $cartModelMock)
    {
        $newCartsCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Quote_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('addFieldToFilter', 'getSelect', 'getIterator'))
            ->getMock();
        $this->newCartsCollectionAddFieldToFilterExactlyTwo($arrayAddFieldToFilter, $arrayAddFieldToFilterStoreId, $newCartsCollectionMock);
        $this->newCartsCollectionGetSelectExactlyThree($varienSelectMock, $newCartsCollectionMock);
        $this->newCartsCollectionGetIterator($cartModelMock, $newCartsCollectionMock);
        return $newCartsCollectionMock;
    }

    /**
     * @param $mcTableName
     * @param $newCartsCollectionMock
     * @param $customerModelMock
     * @param $quoteByEmailResoureceCollectionMock
     * @param $cartModelMock
     * @param $cartJson
     * @param $allCarts
     * @return mixed
     */
    protected function modifiedQuotesEmptyJson($mcTableName, $newCartsCollectionMock, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $cartJson, $allCarts)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'setToken',
                'getBatchId',
                'getMailchimpEcommerceDataTableName',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getQuoteCollection',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                'setCounter',
                'getCounter',
                '_makeCart',
                '_getAllCartsByEmail',
                'addProductNotSentData'
            ))
            ->getMock();
        $this->cartsApiSetToken($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartsApiGetMailchimpEcommerceDataTableName($mcTableName, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock);
        $this->cartsApiGetCounterExactlyTwo($cartsApiMock);
        $this->cartsApiUpdateSyncDataExactlyTwice($cartsApiMock);
        $this->cartsApiSetCounter($cartsApiMock);
        $this->cartsApiMakeCart($cartModelMock, $cartJson, $cartsApiMock);
        $this->cartsApiAddProductNotSentData($cartModelMock, $allCarts, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $where
     * @param $arrayTableName
     * @param $conditionSelect
     * @param $m4m
     * @return mixed
     */
    protected function modifiedQuotesGuestCustomer($where, $arrayTableName, $conditionSelect, $m4m)
    {
        $varienSelectMock = $this
            ->getMockBuilder(Varien_Db_Select::class)
            ->disableOriginalConstructor()
            ->setMethods(array('where', 'limit', 'joinLeft'))
            ->getMock();
        $this->varienSelectWhere($where, $varienSelectMock);
        $this->varienSelectLimit($varienSelectMock);
        $this->varienSelectJoinLeft($arrayTableName, $conditionSelect, $m4m, $varienSelectMock);
        return $varienSelectMock;
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $stringStoreId
     * @param $arrayAddFieldToFilterStoreId
     * @param $varienSelectMock
     * @param $cartModelMock
     * @return mixed
     */
    protected function newCartsModifiedQuotesGuestCustomer($arrayAddFieldToFilter, $stringStoreId, $arrayAddFieldToFilterStoreId, $varienSelectMock, $cartModelMock)
    {
        $newCartsCollectionMock = $this
            ->getMockBuilder(Mage_Sales_Model_Resource_Quote_Collection::class)
            ->disableOriginalConstructor()
            ->setMethods(array('addFieldToFilter', 'getSelect', 'getIterator'))
            ->getMock();
        $this->newCartsAddFieldToFilterExactlyTwo($arrayAddFieldToFilter, $stringStoreId, $arrayAddFieldToFilterStoreId, $newCartsCollectionMock);
        $this->newCartsCollectionGetSelectExactlyThree($varienSelectMock, $newCartsCollectionMock);
        $this->newCartsCollectionGetIterator($cartModelMock, $newCartsCollectionMock);
        return $newCartsCollectionMock;
    }

    /**
     * @param $mcTableName
     * @param $newCartsCollectionMock
     * @param $customerModelMock
     * @return mixed
     */
    protected function cartsApiMockModifiedQuotesGuestCustomer($mcTableName, $newCartsCollectionMock, $customerModelMock)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'getQuoteCollection',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                'getBatchId',
                'getOrderCollection',
                'getMailchimpEcommerceDataTableName'
            ))
            ->getMock();
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiUpdateSyncData($cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartsApiGetMailchimpEcommerceDataTableName($mcTableName, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $token
     * @param $mcTableName
     * @param $newCartsCollectionMock
     * @param $customerModelMock
     * @param $quoteByEmailResoureceCollectionMock
     * @param $cartModelMock
     * @param $cartJson
     * @param $allCarts
     * @return mixed
     */
    protected function cartsApiMockModifiedQuotes($token, $mcTableName, $newCartsCollectionMock, $customerModelMock, $quoteByEmailResoureceCollectionMock, $cartModelMock, $cartJson, $allCarts)
    {
        $cartsApiMock = $this->cartsApiMock->setMethods(
            array(
                'setToken',
                'getToken',
                'getBatchId',
                'getMailchimpEcommerceDataTableName',
                'getBatchLimitFromConfig',
                '_updateSyncData',
                'getQuoteCollection',
                'getCustomerModel',
                'getWebSiteIdFromMagentoStoreId',
                'setCounter',
                'getCounter',
                '_makeCart',
                '_getAllCartsByEmail',
                'addProductNotSentData'
            ))
            ->getMock();
        $this->cartsApiGetToken($token, $cartsApiMock);
        $this->cartsApiSetToken($cartsApiMock);
        $this->cartsApiGetBatchId($cartsApiMock);
        $this->cartsApiGetMailchimpEcommerceDataTableName($mcTableName, $cartsApiMock);
        $this->cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock);
        $this->cartsApiGetBatchLimitFromConfig($cartsApiMock);
        $this->cartsApiGetCustomerModel($customerModelMock, $cartsApiMock);
        $this->cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock);
        $this->cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock);
        $this->cartsApiGetCounterExactlyFour($cartsApiMock);
        $this->cartsApiUpdateSyncDataExactlyTwo($token, $cartsApiMock);
        $this->cartsApiSetCounterExactlyTwo($cartsApiMock);
        $this->cartsApiMakeCart($cartModelMock, $cartJson, $cartsApiMock);
        $this->cartsApiAddProductNotSentData($cartModelMock, $allCarts, $cartsApiMock);
        return $cartsApiMock;
    }

    /**
     * @param $token
     * @param $cartsApiMock
     */
    protected function cartsApiGetToken($token, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getToken')
            ->willReturn($token);
    }

    /**
     * @param $cartModelMock
     * @param $allCarts
     * @param $cartsApiMock
     */
    protected function cartsApiAddProductNotSentData($cartModelMock, $allCarts, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('addProductNotSentData')
            ->with(self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID, $cartModelMock, $allCarts)
            ->willReturn($allCarts);
    }

    /**
     * @param $cartModelMock
     * @param $cartJson
     * @param $cartsApiMock
     */
    protected function cartsApiMakeCart($cartModelMock, $cartJson, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('_makeCart')
            ->with($cartModelMock, self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID, true)
            ->willReturn($cartJson);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiSetCounterExactlyTwo($cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(2))
            ->method('setCounter')
            ->withConsecutive(
                array(self::COUNTER + 1),
                array(self::COUNTER + 1)
            );
    }

    /**
     * @param $token
     * @param $cartsApiMock
     */
    protected function cartsApiUpdateSyncDataExactlyTwo($token, $cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(2))
            ->method('_updateSyncData')
            ->withConsecutive(
                array(self::ALREADY_SENT_CART_ID, self::MAILCHIMP_STORE_ID, null, null, null, null, 1),
                array(self::CART_ID, self::MAILCHIMP_STORE_ID, null, null, null, null, null, $token)
            );
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiGetCounterExactlyFour($cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(4))
            ->method('getCounter')
            ->willReturnOnConsecutiveCalls(
                self::COUNTER,
                self::COUNTER,
                self::COUNTER,
                self::COUNTER
            );
    }

    /**
     * @param $quoteByEmailResoureceCollectionMock
     * @param $cartsApiMock
     */
    protected function cartsApiGetAllCartsByEmail($quoteByEmailResoureceCollectionMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('_getAllCartsByEmail')
            ->with(self::CUSTOMER_EMAIL_BY_CART, self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID)
            ->willReturn($quoteByEmailResoureceCollectionMock);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiGetWebSiteFromMagentoStoreId($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getWebSiteIdFromMagentoStoreId')
            ->with(self::MAGENTO_STORE_ID)
            ->willReturn(self::WEB_SITE_ID_FROM_MAGENTO_STORE_ID);
    }

    /**
     * @param $customerModelMock
     * @param $cartsApiMock
     */
    protected function cartsApiGetCustomerModel($customerModelMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getCustomerModel')
            ->willReturn($customerModelMock);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiGetBatchLimitFromConfig($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getBatchLimitFromConfig')
            ->willReturn(self::BATCH_LIMIT_FROM_CONFIG);
    }

    /**
     * @param $newCartsCollectionMock
     * @param $cartsApiMock
     */
    protected function cartsApiGetQuoteCollection($newCartsCollectionMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getQuoteCollection')
            ->willReturn($newCartsCollectionMock);
    }

    /**
     * @param $mcTableName
     * @param $cartsApiMock
     */
    protected function cartsApiGetMailchimpEcommerceDataTableName($mcTableName, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getMailchimpEcommerceDataTableName')
            ->willReturn($mcTableName);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiGetBatchId($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getBatchId')
            ->willReturn(self::BATCH_ID);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiSetToken($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('setToken')
            ->with(null);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiUpdateSyncData($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('_updateSyncData')
            ->with(self::CART_ID, self::MAILCHIMP_STORE_ID);
    }

    /**
     * @param $cartModelMock
     * @param $newCartsCollectionMock
     */
    protected function newCartsCollectionGetIterator($cartModelMock, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator(array($cartModelMock)));
    }

    /**
     * @param $varienSelectMock
     * @param $newCartsCollectionMock
     */
    protected function newCartsCollectionGetSelectExactlyThree($varienSelectMock, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->exactly(3))
            ->method('getSelect')
            ->willReturnOnConsecutiveCalls(
                $varienSelectMock,
                $varienSelectMock,
                $varienSelectMock
            );
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $stringStoreId
     * @param $arrayAddFieldToFilterStoreId
     * @param $newCartsCollectionMock
     */
    protected function newCartsAddFieldToFilterExactlyTwo($arrayAddFieldToFilter, $stringStoreId, $arrayAddFieldToFilterStoreId, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->withConsecutive(
                array(self::STRING_IS_ACTIVE, $arrayAddFieldToFilter),
                array($stringStoreId, $arrayAddFieldToFilterStoreId)
            );
    }

    /**
     * @param $where
     * @param $varienSelectMock
     */
    protected function varienSelectWhere($where, $varienSelectMock)
    {
        $varienSelectMock->expects($this->once())
            ->method('where')
            ->with($where);
    }

    /**
     * @param $varienSelectMock
     */
    protected function varienSelectLimit($varienSelectMock)
    {
        $varienSelectMock->expects($this->once())
            ->method('limit')
            ->with(self::BATCH_LIMIT_FROM_CONFIG);
    }

    /**
     * @param $arrayTableName
     * @param $conditionSelect
     * @param $m4m
     * @param $varienSelectMock
     */
    protected function varienSelectJoinLeft($arrayTableName, $conditionSelect, $m4m, $varienSelectMock)
    {
        $varienSelectMock->expects($this->once())
            ->method('joinLeft')
            ->with($arrayTableName, $conditionSelect, $m4m);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiGetCounterExactlyTwo($cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(2))
            ->method('getCounter')
            ->willReturnOnConsecutiveCalls(
                self::COUNTER,
                self::COUNTER
            );
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiSetCounter($cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('setCounter')
            ->with(self::COUNTER + 1);
    }

    /**
     * @param $cartsApiMock
     */
    protected function cartsApiUpdateSyncDataExactlyTwice($cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(2))
            ->method('_updateSyncData')
            ->withConsecutive(
                array(self::ALREADY_SENT_CART_ID, self::MAILCHIMP_STORE_ID, null, null, null, null, 1),
                array(self::CART_ID, self::MAILCHIMP_STORE_ID)
            );
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $arrayAddFieldToFilterStoreId
     * @param $newCartsCollectionMock
     */
    protected function newCartsCollectionAddFieldToFilterExactlyTwo($arrayAddFieldToFilter, $arrayAddFieldToFilterStoreId, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->withConsecutive(
                array(self::STRING_IS_ACTIVE, $arrayAddFieldToFilter),
                array(self::STRING_STORE_ID, $arrayAddFieldToFilterStoreId)
            );
    }

    /**
     * @param $cartModelMock
     */
    protected function cartModelGetCustomerEmailExactlyThree($cartModelMock)
    {
        $cartModelMock->expects($this->exactly(3))
            ->method('getCustomerEmail')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_EMAIL_BY_CART,
                self::CUSTOMER_EMAIL_BY_CART,
                self::CUSTOMER_EMAIL_BY_CART
            );
    }

    /**
     * @param $cartModelMock
     */
    protected function cartModelGetEntityId($cartModelMock)
    {
        $cartModelMock->expects($this->once())
            ->method('getEntityId')
            ->willReturn(self::CART_ID);
    }

    /**
     * @param $customerId
     * @param $cartModelMock
     */
    protected function cartModelGetCustomerId($customerId, $cartModelMock)
    {
        $cartModelMock->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customerId);
    }

    /**
     * @param $orderCollectionMock
     * @param $cartsApiMock
     */
    protected function cartApiGetOrderCollection($orderCollectionMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getOrderCollection')
            ->willReturn($orderCollectionMock);
    }

    /**
     * @param $existFirstDate
     * @param $cartsApiMock
     */
    protected function cartsApiGetFirstDateExactlyTwo($existFirstDate, $cartsApiMock)
    {
        $cartsApiMock->expects($this->exactly(2))
            ->method('getFirstDate')
            ->willReturnOnConsecutiveCalls(
                $existFirstDate,
                $existFirstDate
            );
    }

    /**
     * @param $newCartsCollectionMock
     * @param $cartsApiMock
     */
    protected function cartsApiJoinMailchimpSyncDataWithoutWhere($newCartsCollectionMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('joinMailchimpSyncDataWithoutWhere')
            ->with($newCartsCollectionMock, self::MAILCHIMP_STORE_ID);
    }

    /**
     * @param $cartModelMock
     * @param $cartJson
     * @param $cartsApiMock
     */
    protected function cartsApiMakeCartExactlyOne($cartModelMock, $cartJson, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('_makeCart')
            ->with($cartModelMock, self::MAILCHIMP_STORE_ID, self::MAGENTO_STORE_ID)
            ->willReturn($cartJson);
    }

    /**
     * @param $helperMock
     * @param $cartsApiMock
     */
    protected function cartsApiGetHelper($helperMock, $cartsApiMock)
    {
        $cartsApiMock->expects($this->once())
            ->method('getHelper')
            ->willReturn($helperMock);
    }

    /**
     * @param $allVisbleItems
     * @param $cartModelMock
     */
    protected function cartModelGetAllVisibleItems($allVisbleItems, $cartModelMock)
    {
        $cartModelMock->expects($this->once())
            ->method('getAllVisibleItems')
            ->willReturn($allVisbleItems);
    }

    /**
     * @param $stringCustomerEmailMainTable
     * @param $addFieldToFilterOrderCollection
     * @param $stringUpdated
     * @param $addFieldToFilterUpdated
     * @param $orderCollectionMock
     */
    protected function orderCollectionAddFieldToFilterExactlyTwo($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection, $stringUpdated, $addFieldToFilterUpdated, $orderCollectionMock)
    {
        $orderCollectionMock->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->withConsecutive(
                array($stringCustomerEmailMainTable, $addFieldToFilterOrderCollection),
                array($stringUpdated, $addFieldToFilterUpdated)
            );
    }

    /**
     * @param $sizeOrderCollection
     * @param $orderCollectionMock
     */
    protected function orderCollectionGetSize($sizeOrderCollection, $orderCollectionMock)
    {
        $orderCollectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn($sizeOrderCollection);
    }

    /**
     * @param $cartByEmailModelMock
     * @param $quoteByEmailResoureceCollectionMock
     */
    protected function quoteByEmailGetIterator($cartByEmailModelMock, $quoteByEmailResoureceCollectionMock)
    {
        $quoteByEmailResoureceCollectionMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator(array($cartByEmailModelMock)));
    }

    /**
     * @param $quoteByEmailResoureceCollectionMock
     */
    protected function quoteByEmailClear($quoteByEmailResoureceCollectionMock)
    {
        $quoteByEmailResoureceCollectionMock->expects($this->once())
            ->method('clear');
    }

    /**
     * @param $cartByEmailModelMock
     */
    protected function cartByEmailGetEntityId($cartByEmailModelMock)
    {
        $cartByEmailModelMock->expects($this->once())
            ->method('getEntityId')
            ->willReturn(self::ALREADY_SENT_CART_ID);
    }

    /**
     * @param $newCartsCollectionMock
     * @param $helperMock
     */
    protected function helperAddFieldToFilter($newCartsCollectionMock, $helperMock)
    {
        $helperMock->expects($this->once())
            ->method('addResendFilter')
            ->with($newCartsCollectionMock, self::MAGENTO_STORE_ID, Ebizmarts_MailChimp_Model_Config::IS_QUOTE);
    }

    /**
     * @param $varienSelectMock
     * @param $newCartsCollectionMock
     */
    protected function newCartsCollectionGetSelectExactlyTwo($varienSelectMock, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->exactly(2))
            ->method('getSelect')
            ->willReturnOnConsecutiveCalls(
                $varienSelectMock,
                $varienSelectMock
            );
    }

    /**
     * @param $arrayAddFieldToFilter
     * @param $stringCustomerEmail
     * @param $arrayAddFieldToFilterCustomerEmail
     * @param $stringItemsCount
     * @param $arrayAddFieldToFilterItemsCount
     * @param $arrayAddFieldToFilterStoreId
     * @param $stringUpdatedAt
     * @param $arrayAddFieldToFilterUpdatedAt
     * @param $newCartsCollectionMock
     */
    protected function newCartsCollectionAddFieldToFilterExactlyFive($arrayAddFieldToFilter, $stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail, $stringItemsCount, $arrayAddFieldToFilterItemsCount, $arrayAddFieldToFilterStoreId, $stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt, $newCartsCollectionMock)
    {
        $newCartsCollectionMock->expects($this->exactly(5))
            ->method('addFieldToFilter')
            ->withConsecutive(
                array(self::STRING_IS_ACTIVE, $arrayAddFieldToFilter),
                array($stringCustomerEmail, $arrayAddFieldToFilterCustomerEmail),
                array($stringItemsCount, $arrayAddFieldToFilterItemsCount),
                array(self::STRING_STORE_ID, $arrayAddFieldToFilterStoreId),
                array($stringUpdatedAt, $arrayAddFieldToFilterUpdatedAt)
            );
    }

    /**
     * @param $cartModelMock
     */
    protected function cartModelGetCustomerEmailExactlyFour($cartModelMock)
    {
        $cartModelMock->expects($this->exactly(4))
            ->method('getCustomerEmail')
            ->willReturnOnConsecutiveCalls(
                self::CUSTOMER_EMAIL_BY_CART,
                self::CUSTOMER_EMAIL_BY_CART,
                self::CUSTOMER_EMAIL_BY_CART,
                self::CUSTOMER_EMAIL_BY_CART
            );
    }

    /**
     * @param $customerEmailAddress
     * @param $customerModelMock
     */
    protected function customerModelGetEmail($customerEmailAddress, $customerModelMock)
    {
        $customerModelMock->expects($this->once())
            ->method('getEmail')
            ->willReturn($customerEmailAddress);
    }

    /**
     * @param $customerModelMock
     */
    protected function customerModelLoadByEmail($customerModelMock)
    {
        $customerModelMock->expects($this->once())
            ->method('loadByEmail')
            ->with(self::CUSTOMER_EMAIL_BY_CART);
    }

    /**
     * @param $customerModelMock
     */
    protected function customerModelSetWebsiteId($customerModelMock)
    {
        $customerModelMock->expects($this->once())
            ->method('setWebsiteId')
            ->with(self::WEB_SITE_ID_FROM_MAGENTO_STORE_ID);
    }
}
