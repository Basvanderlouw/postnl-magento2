<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\PostNL\Service\Shipment;

use TIG\PostNL\Api\Data\ShipmentInterface;
use TIG\PostNL\Service\Volume\Items\Calculate;

class Data
{
    /**
     * @var ProductOptions
     */
    private $productOptions;

    /**
     * @var ContentDescription
     */
    private $contentDescription;

    /**
     * @var Calculate
     */
    private $shipmentVolume;

    /**
     * @param ProductOptions $productOptions
     * @param ContentDescription $contentDescription
     * @param Calculate $calculate
     */
    public function __construct(
        ProductOptions $productOptions,
        ContentDescription $contentDescription,
        Calculate $calculate
    ) {
        $this->productOptions = $productOptions;
        $this->contentDescription = $contentDescription;
        $this->shipmentVolume = $calculate;
    }

    /**
     * @param ShipmentInterface $shipment
     * @param                   $address
     * @param                   $contact
     * @param int               $currentShipmentNumber
     *
     * @return array
     */
    public function get(ShipmentInterface $shipment, $address, $contact, $currentShipmentNumber = 0)
    {
        $shipmentData = $this->getDefaultShipmentData($shipment, $address, $contact, $currentShipmentNumber);
        $shipmentData = $this->setMandatoryShipmentData($shipment, $currentShipmentNumber, $shipmentData);

        return $shipmentData;
    }

    /**
     * @param ShipmentInterface $shipment
     * @param                   $address
     * @param                   $contact
     * @param                   $currentShipmentNumber
     *
     * @return array
     */
    private function getDefaultShipmentData(ShipmentInterface $shipment, $address, $contact, $currentShipmentNumber)
    {
        return [
            'Addresses'                => ['Address' => $address],
            'Barcode'                  => $shipment->getBarcode($currentShipmentNumber),
            'CollectionTimeStampEnd'   => '',
            'CollectionTimeStampStart' => '',
            'Contacts'                 => ['Contact' => $contact],
            'Dimension'                => ['Weight' => round($shipment->getTotalWeight())],
            'DeliveryDate'             => $shipment->getDeliveryDateFormatted(),
            'DownPartnerID'            => $shipment->getPgRetailNetworkId(),
            'DownPartnerLocation'      => $shipment->getPgLocationCode(),
            'ProductCodeDelivery'      => $shipment->getProductCode(),
        ];
    }

    /**
     * @param ShipmentInterface $shipment
     * @param int               $currentShipmentNumber
     * @param array             $shipmentData
     *
     * @return array
     */
    private function setMandatoryShipmentData(ShipmentInterface $shipment, $currentShipmentNumber, array $shipmentData)
    {
        $magentoShipment = $shipment->getShipment();
        if ($shipment->isExtraAtHome()) {
            $shipmentData['Content'] = $this->contentDescription->get($shipment);
            $shipmentData['Dimension']['Volume'] = $this->shipmentVolume->get($magentoShipment->getItems());
        }

        if ($shipment->isExtraCover()) {
            $shipmentData['Amounts'] = $this->getAmount($shipment);
        }

        if ($shipment->getParcelCount() > 1) {
            $shipmentData['Groups'] = $this->getGroupData($shipment, $currentShipmentNumber);
        }

        $productOptions = $this->productOptions->get($shipment);
        if ($productOptions) {
            $shipmentData['ProductOptions'] = $productOptions;
        }

        return $shipmentData;
    }

    /**
     * @param ShipmentInterface $shipment
     *
     * @return array
     */
    private function getAmount(ShipmentInterface $shipment)
    {
        $amounts = [];
        $extraCoverAmount = $shipment->getExtraCoverAmount();

        $amounts[] = [
            'AccountName'       => '',
            'BIC'               => '',
            'IBAN'              => '',
            'AmountType'        => '02', // 01 = COD, 02 = Insured
            'Currency'          => 'EUR',
            'Reference'         => '',
            'TransactionNumber' => '',
            'Value'             => number_format($extraCoverAmount, 2, '.', ''),
        ];

        return $amounts;
    }

    /**
     * @param ShipmentInterface $shipment
     * @param                   $currentShipmentNumber
     *
     * @return array
     */
    private function getGroupData(ShipmentInterface $shipment, $currentShipmentNumber)
    {
        return [
            'Group' => [
                'GroupCount'    => $shipment->getParcelCount(),
                'GroupSequence' => $currentShipmentNumber,
                'GroupType'     => '03',
                'MainBarcode'   => $shipment->getMainBarcode(),
            ]
        ];
    }
}
