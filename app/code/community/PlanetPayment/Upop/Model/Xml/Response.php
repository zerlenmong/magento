<?php

/**
 * One Pica
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to codemaster@onepica.com so we can send you a copy immediately.
 * 
 * @category    PlanetPayment
 * @package     PlanetPayment_Upop
 * @copyright   Copyright (c) 2012 Planet Payment Inc. (http://www.planetpayment.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Planet Payment
 *
 * @category   PlanetPayment
 * @package    PlanetPayment_Upop
 * @author     One Pica Codemaster <codemaster@onepica.com>
 */
class PlanetPayment_Upop_Model_Xml_Response extends PlanetPayment_Upop_Model_Xml_Abstract {

    protected $_responseXml = null;

    /**
     * returns the response after decryption
     * @return object SImpleXML 
     */
    protected function _getResponse() {
        $response = $this->getUpopResponse();
        if ($response) {
            $xmlResponse = simplexml_load_string($response);
            if ($xmlResponse instanceof SimpleXMLElement) {
                
               /* if ($this->_hasEncryption() && $this->_isProductionMode()) {
                    $this->_responseXml = simplexml_load_string($this->_decryptResponse($xmlResponse[0]));
                } elseif($this->_hasEncryption() && !$this->_isProductionMode()) {
                    $encoded_data = $xmlResponse[0];
                    $base64_encoded_data = base64_decode($encoded_data);
                    $this->_responseXml = simplexml_load_string($base64_encoded_data);
                }
                else {
                    $this->_responseXml = $xmlResponse->RESPONSE;
                }*/


               if ($this->_hasEncryption()) {
                    $this->_responseXml = simplexml_load_string($this->_decryptResponse($xmlResponse[0]));
                } else {
                    $this->_responseXml = $xmlResponse->RESPONSE;
                }

                //Logging

                $requestXml = $this->_getRequestObject()->getTransactionForLog()->asXML();
                $requestXml = preg_replace("/<ACCOUNT_NUMBER>[0-9]+([0-9]{4})/", '<ACCOUNT_NUMBER>************$1', $requestXml);
                $requestXml = preg_replace("/<CVV>([0-9]+)<\/CVV>/", '<CVV>***</CVV>', $requestXml);
                $loggedInfo = Mage::helper('upop')->log($requestXml, $this->_responseXml->asXML(), $this->_getRequestObject()->getCurrencyRate());
                Mage::log("Print Request : " . $loggedInfo->getRequest(), null, PlanetPayment_Upop_Model_Upop::LOG_FILE, true);
                Mage::log("Print Response : " . $loggedInfo->getResponse(), null, PlanetPayment_Upop_Model_Upop::LOG_FILE, true);

                //This will be saved in log file if any error occured while Auth.
                //The database transaction will be rolled back if error.
                $this->setlogInfo("\n \t Request:" . $loggedInfo->getRequest() . "\n \t Response:" . $loggedInfo->getResponse());
            } else {
                Mage::throwException('Invalid response');
            }
        } else {
            Mage::throwException('Invalid response');
        }

        return $this->_responseXml;
    }

    /**
     * Checking whether the request was successful
     * @return bool
     */
    public function isSuccess() {
        $responseXml = $this->_getResponse();
        if ($responseXml->FIELDS->ARC) {
            if ($responseXml->FIELDS->ARC == '00' && $responseXml->FIELDS->MRC == '00') {
                return true;
            } else {
                $errorHelper = Mage::helper('upop/error');
                $arcMsg = $errorHelper->getErrorMessage((string) $responseXml->FIELDS->ARC, 'arc');
                $mrcMsg = $errorHelper->getErrorMessage((string) $responseXml->FIELDS->MRC, 'mrc');
                $this->setMessage("<i> (" . $arcMsg . ", " . $mrcMsg . ")</i><br/>RESPONSE TEXT:" . $responseXml->FIELDS->RESPONSE_TEXT);
            }
        }

        return false;
    }

    /**
     * Returns the Xml content of response
     * @return type 
     */
    public function getXmlContent() {
        if (!$this->_responseXml) {
            $this->_getResponse();
        }

        return $this->_responseXml;
    }

    /**
     * Returning the request object of this response
     * @return type 
     */
    protected function _getRequestObject() {
        return $this->getUpopRequest();
    }

}
