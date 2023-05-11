<?php

namespace Apto\Plugins\ImageUpload\Application\Core\Service\PriceProvider;

use Apto\Base\Domain\Core\Model\AptoUuid;
use Apto\Base\Domain\Core\Model\InvalidUuidException;
use Apto\Catalog\Application\Core\Service\PriceCalculator\PriceCalculator;
use Apto\Catalog\Application\Core\Service\PriceCalculator\PriceProvider\BasePriceProvider;
use Apto\Catalog\Domain\Core\Model\Product\Element\ElementDefinition;
use Apto\Plugins\ImageUpload\Application\Core\Service\ElementDefinitionRegistry\ImageUploadStaticValuesProvider;
use Apto\Plugins\ImageUpload\Domain\Core\Model\Product\Element\ImageUploadDefinition;
use Money\Money;

class ImageUploadPriceProvider implements BasePriceProvider
{
    /**
     * @var ImageUploadStaticValuesProvider
     */
    private $staticValuesProvider;

    /**
     * @param ImageUploadStaticValuesProvider $staticValuesProvider
     */
    public function __construct(ImageUploadStaticValuesProvider $staticValuesProvider)
    {
        $this->staticValuesProvider = $staticValuesProvider;
    }

    /**
     * @return string
     */
    public function getElementDefinitionClass(): string
    {
        return ImageUploadDefinition::class;
    }

    /**
     * @param PriceCalculator $priceCalculator
     * @param AptoUuid $sectionId
     * @param AptoUuid $elementId
     * @param ElementDefinition $elementDefinition
     * @param Money $elementPrice
     * @return Money
     * @throws InvalidUuidException
     */
    public function getPrice(
        PriceCalculator $priceCalculator,
        AptoUuid $sectionId,
        AptoUuid $elementId,
        ElementDefinition $elementDefinition,
        Money $elementPrice
    ): Money {
        // set required properties from price calculator
        $state = $priceCalculator->getState();
        $elementState = $state->getElementState($sectionId, $elementId);
        $customerGroupId = $priceCalculator->getCustomerGroup()['id'];
        $fallbackCustomerGroup = $priceCalculator->getFallbackCustomerGroupOrNull();
        $fallbackCustomerGroupId = null !== $fallbackCustomerGroup ? $fallbackCustomerGroup['id'] : null;

        if (null === $elementState) {
            return $elementPrice;
        }

        // get static values
        $useTextPrice = false;
        $useImagePrice = false;
        $staticValues = $this->staticValuesProvider->getStaticValues($elementDefinition);
        $useHighest = $staticValues['user']['useSurchargeAsReplacement'];

        // use surcharge?
        if (array_key_exists('fabricItemsOnCanvas', $elementState) && is_array($elementState['fabricItemsOnCanvas']) && array_key_exists('text', $elementState['fabricItemsOnCanvas'])) {
            $useTextPrice = $this->isTextPresent($elementState);
            $useImagePrice = $this->isImagePresent($elementState);
        }

        $prices = $staticValues['user']['surchargePrices'];

        $prices = $this->filterPrices(
            $prices,
            $priceCalculator->getCurrency()->getCode(),
            $priceCalculator->getFallbackCurrency()->getCode(),
            $customerGroupId,
            $fallbackCustomerGroupId
        );

        $prices = $this->filterPricesByType(
            $prices
        );

        $imagePrice = $priceCalculator->getTaxAdaptedPriceByPreferredCustomerGroup($prices['imagePrices']);
        $textPrice = $priceCalculator->getTaxAdaptedPriceByPreferredCustomerGroup($prices['textPrices']);

        if (!$useImagePrice) {
            $imagePrice = $imagePrice->multiply(0);
        }
        if (!$useTextPrice) {
            $textPrice = $textPrice->multiply(0);
        }

        if ($useHighest) {
            $highestPrice = $imagePrice;
            if ($textPrice->greaterThan($highestPrice)) {
                $highestPrice = $textPrice;
            }
            return $elementPrice->add($highestPrice);
        }
        return $elementPrice->add($imagePrice->add($textPrice));
    }


    /**
     * @param array $prices
     * @param string $currencyCode
     * @param string $fallBackCurrencyCode
     * @param string $groupId
     * @param string|null $fallbackGroupId
     * @return array
     */
    private function filterPrices(array $prices, string $currencyCode, string $fallBackCurrencyCode, string $groupId, string $fallbackGroupId = null)
    {
        $result = [];
        foreach ($prices as $price) {
            if (($price['currencyCode'] === $currencyCode || $price['currencyCode'] === $fallBackCurrencyCode) && ($price['customerGroupId'] === $groupId || $price['customerGroupId'] === $fallbackGroupId)) {
                $result[] = $price;
            }
        }
        return $result;
    }

    /**
     * @param array $prices
     * @return array[]
     */
    private function filterPricesByType(array $prices)
    {
        $result = [
            'imagePrices' => [],
            'textPrices' => []
        ];
        foreach ($prices as $price) {
            if ($price['type'] === 'Bild') {
                $result['imagePrices'][] = $price;
            }
            if ($price['type'] === 'Text') {
                $result['textPrices'][] = $price;
            }
        }

        return $result;
    }

    /**
     * @param array $elementState
     * @return bool
     */
    function isTextPresent(array $elementState) {
        return sizeof($elementState['fabricItemsOnCanvas']['text']) > 0;
    }

    /**
     * @param array $elementState
     * @return bool
     */
    function isImagePresent(array $elementState) {
        return (sizeof($elementState['fabricItemsOnCanvas']['image']) > 0 || sizeof($elementState['fabricItemsOnCanvas']['clipArt']) > 0);
    }
}

