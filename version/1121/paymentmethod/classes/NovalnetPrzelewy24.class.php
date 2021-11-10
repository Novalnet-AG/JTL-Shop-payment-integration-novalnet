<?php
/**
 * Novalnet payment plugin
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Novalnet End User License Agreement
 *
 * DISCLAIMER
 *
 * If you wish to customize Novalnet payment extension for your needs,
 * please contact technic@novalnet.de for more information.
 *
 * @author  	Novalnet AG
 * @copyright  	Copyright (c) Novalnet
 * @license    	https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 *
 * Script: NovalnetPrzelewy24.class.php
 *
 */
require_once(PFAD_ROOT . PFAD_INCLUDES_MODULES . 'PaymentMethod.class.php');
require_once('NovalnetGateway.class.php');

/**
 * Class NovalnetPrzelewy24
 */
class NovalnetPrzelewy24 extends PaymentMethod
{
    /**
     * @var string
     */
    public $moduleID;

    /**
     * @var string
     */
    public $paymentName = 'novalnet_przelewy24';

    /**
     * @var string
     */
    public $paymentKey = 78;

    /**
     * @var null|NovalnetGateway
     */
    public $novalnetGateway = null;

    /**
     * @var string
     */
    public $novalnetPrzelewy24Url = 'https://payport.novalnet.de/globalbank_transfer';

    /**
     * Constructor
     */
    public function __construct($moduleID)
    {
        // Creates instance for the NovalnetGateway class
        $this->novalnetGateway = NovalnetGateway::getInstance();

        // Sets and displays payment error
        $this->novalnetGateway->assignPaymentError();

        // Setting up module ID for the payment class
        $this->moduleID = 'kPlugin_' . $this->novalnetGateway->helper->oPlugin->kPlugin . '_novalnetprzelewy24';

        parent::__construct($moduleID);
    }

    /**
     * Core function - Sets the name and caption for the payment method and necessary when synchronizing with WAWI
     *
     * @param  int    $nAgainCheckout
     * @return object
     */
    public function init($nAgainCheckout = 0)
    {
        parent::init($nAgainCheckout);

        $this->name    = 'Novalnet Przelewy24';
        $this->caption = 'Novalnet Przelewy24';

        return $this;
    }

    /**
     * Core function - Called on payment page
     *
     * @param array $args_arr
     * @return bool
     */
    public function isValidIntern($args_arr = array())
    {
        // Update necessary for the shop version greater than 406 where shop information text is empty by default
        $this->novalnetGateway->setupShopInformationText($this->moduleID, 'przelewy24');

        return !(!$this->novalnetGateway->helper->getConfigurationParams('enablemode', $this->paymentName) ||
                $this->novalnetGateway->isConfigInvalid());
    }

    /**
     * Core function - Called when additional template is used
     *
     * @param array $aPost_arr
     * @return bool
     */
    public function handleAdditional($aPost_arr)
    {
        global $smarty, $shopVersion;
        
        // Unsets the other Novalnet payment sessions
        $this->novalnetGateway->helper->novalnetSessionUnset($this->paymentName);
        $smarty->assign(array(
            'paymentName' => $this->paymentName,
            'testMode'    => $this->novalnetGateway->helper->getConfigurationParams('testmode', $this->paymentName),
            'shopLatest'  => $shopVersion == '4x',
            'nnLang'      => nnGetLanguageText(array('__NN_testmode', '__NN_redirection_text', '__NN_redirection_browser_text')) // Gets language texts for the variables
        ));

        if (isset($aPost_arr['nn_payment'])) { // Only pass the payment step if the payment has been set
            return true;
        }
    }

    /**
     * Core function - Called when the additional template is submitted
     *
     * @param none
     * @return bool
     */
    public function validateAdditional()
    {
        return false;
    }

    /**
     * Core function - Called at the time when 'Buy now' button is clicked, initiates the Payment process
     *
     * @param object $order
     * @return none
     */
    public function preparePaymentProcess($order)
    {
        global $smarty, $shopUrl, $shopVersion;

        // Process when reordering the payment from My-account (for shop 3.x series)
        if (!empty($_REQUEST['kBestellung'])) {
            $this->novalnetGateway->handleReorderProcess($order);
        }

        $orderHash = $this->generateHash($order);   // Core function to generate order hash
        $uniqid = $this->novalnetGateway->helper->get_uniqueid();
        $paymentRequestParameters = $this->novalnetGateway->generatePaymentParams($order, $this->paymentName); // Retrieves payment parameters for the transaction

        $paymentRequestParameters['key']          = $this->paymentKey;
        $paymentRequestParameters['payment_type'] = 'PRZELEWY24';

        $this->novalnetGateway->preValidationCheckOnSubmission($paymentRequestParameters, $order, $this->paymentName);  // Validates whether the transaction can be passed to the server

        $handlerUrlParameters = $this->getPaymentReturnUrls($orderHash, $order->kBestellung);  // Retrieves return URL's for redirection payment

        $paymentRequestParameters['return_url']          = $handlerUrlParameters['cReturnURL'];
        $paymentRequestParameters['return_method']       = 'POST';
        $paymentRequestParameters['error_return_url']    = $handlerUrlParameters['cFailureURL'];
        $paymentRequestParameters['error_return_method'] = 'POST';
        $paymentRequestParameters['session']             = session_id();
        $paymentRequestParameters['user_variable_0']     = $shopUrl;
        $paymentRequestParameters['uniqid']              = $uniqid;
        $paymentRequestParameters['implementation']      = 'ENC';
        $paymentRequestParameters['input1']              = 'nn_sid';
        $paymentRequestParameters['inputval1']           = session_id();

        $this->novalnetGateway->helper->generateEncodeArray($paymentRequestParameters, $uniqid);
        $paymentRequestParameters['hash'] = $this->novalnetGateway->helper->generateHashValue($paymentRequestParameters, $this->paymentKey); // Encodes the basic payment parameters before sending to third party

        $smarty->assign(array(
            'paymentUrl'        => $this->novalnetPrzelewy24Url,
            'datas'             => $paymentRequestParameters,
            'message'           => $this->novalnetGateway->helper->oPlugin->oPluginSprachvariableAssoc_arr['__NN_redirection_text'],
            'browserMessage'    => $this->novalnetGateway->helper->oPlugin->oPluginSprachvariableAssoc_arr['__NN_redirection_browser_text'],
            'buttonText'        => $this->novalnetGateway->helper->oPlugin->oPluginSprachvariableAssoc_arr['__NN_redirection_button_text'],
            'shopLatest'        => $shopVersion == '4x',
            'paymentMethodPath' => $shopUrl . '/' . PFAD_PLUGIN . $this->novalnetGateway->helper->oPlugin->cVerzeichnis . '/' . PFAD_PLUGIN_VERSION . $this->novalnetGateway->helper->oPlugin->nVersion . '/' . PFAD_PLUGIN_PAYMENTMETHOD
        ));
    }

    /**
     * Core function - Called on notification URL for while ordering process
     *
     * @param object $order
     * @param string $hash
     * @param array  $args
     * @return bool
     */
    public function finalizeOrder($order, $hash, $args)
    {
        return $this->novalnetGateway->verifyNotification($order, $this->paymentName, $this->paymentKey, $args); // Finalises the order based on response
    }

    /**
     * Core function - Called when order is finalized and created on notification URL
     *
     * @param object $order
     * @param string $paymentHash
     * @param array  $args
     * @return none
     */
    public function handleNotification($order, $paymentHash, $args)
    {
        global $DB, $selectQuery;

        // Verify if the payment has been received already for the transaction
        $incomingPayment = $DB->$selectQuery('tzahlungseingang', 'kBestellung', $order->kBestellung, 'cHinweis', $args['tid']);

        if (is_object($incomingPayment) && intval($incomingPayment->kZahlungseingang) > 0) {
            $this->novalnetGateway->completeProcess($order, $this->generateHash($order), $this->paymentName, $args);
            // Verifies if the payment has not been processed already
        } else {
            // Adds the payment method into the shop and changes the order status
            $this->updateShopDatabase($order, $args);

            $this->novalnetGateway->handlePaymentCompletion($order, $this->generateHash($order), $this->paymentKey, $this->paymentName, $args); // Completes the order
        }
    }

    /**
     * Adds the payment method into the shop, updates notification ID, sets order status
     *
     * @param object $order
     * @param array  $args
     * @return none
     */
    public function updateShopDatabase($order, $args)
    {
        $this->updateNotificationID($order->kBestellung, $args['tid']);  // Updates transaction ID into shop for reference

        NovalnetGateway::performDbExecution('tbestellung', 'cStatus=' . constant($args['tid_status'] == 86 ? $this->novalnetGateway->helper->getConfigurationParams('pending_status', $this->paymentName) : $this->novalnetGateway->helper->getConfigurationParams('set_order_status', $this->paymentName)), 'cBestellNr = "' . $order->cBestellNr . '"'); // Updates the value into the database
    }

    /**
     * Set return URLs for redirection payments
     *
     * @param string $orderHash
     * @param string $orderNo
     * @return array $handlerUrlParameters
     */
    public function getPaymentReturnUrls($orderHash, $orderNo)
    {
        $handlerUrlParameters['cReturnURL']= ($_SESSION['Zahlungsart']->nWaehrendBestellung == 0) ? $this->getNotificationURL($orderHash) : $this->getNotificationURL($orderHash).'&sh=' . $orderHash;
        $handlerUrlParameters['cFailureURL']=$handlerUrlParameters['cReturnURL'];

        return $handlerUrlParameters;
    }
}
