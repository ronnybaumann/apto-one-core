<?php

namespace Apto\Catalog\Domain\Core\Service\JavascriptStateCreatorService;

use Apto\Base\Domain\Core\Model\AptoTranslatedValue;
use Apto\Base\Domain\Core\Model\AptoUuid;
use Apto\Base\Domain\Core\Model\InvalidUuidException;
use Apto\Catalog\Application\Core\Query\Product\Element\RenderImageFactory;
use Apto\Catalog\Application\Core\Service\ComputedProductValue\CircularReferenceException;
use Apto\Catalog\Domain\Core\Factory\ConfigurableProduct\ConfigurableProduct;
use Apto\Catalog\Domain\Core\Factory\EnrichedState\EnrichedState;
use Apto\Catalog\Domain\Core\Factory\RuleFactory\Rule\Payload\RulePayloadFactory;
use Apto\Catalog\Domain\Core\Model\Product\Repeatable;
use Apto\Catalog\Domain\Core\Service\EnrichedStateValidation\RuleValidationResult;
use Apto\Catalog\Domain\Core\Factory\RuleFactory\Rule;

class JavascriptStateCreatorService
{
    /**
     * @var RulePayloadFactory
     */
    protected RulePayloadFactory $rulePayloadFactory;

    /**
     * @var RenderImageFactory
     */
    protected RenderImageFactory $renderImageFactory;

    /**
     * @param RulePayloadFactory $rulePayloadFactory
     * @param RenderImageFactory $renderImageFactory
     */
    public function __construct(RulePayloadFactory $rulePayloadFactory, RenderImageFactory $renderImageFactory)
    {
        $this->rulePayloadFactory = $rulePayloadFactory;
        $this->renderImageFactory = $renderImageFactory;
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState $state
     * @param RuleValidationResult $ruleValidationResult
     * @param array $intention
     * @return array
     * @throws InvalidUuidException
     * @throws CircularReferenceException
     */
    public function createState(ConfigurableProduct $product, EnrichedState $state, RuleValidationResult $ruleValidationResult, array $intention): array
    {
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod';
        $result = [
            'configurationState' => [
                'sections' => $this->createSections($product, $state),
                'elements' => $this->createElements($product, $state)
            ],
            'compressedState' => $state->getState()->jsonSerialize(),
            'failedRules' => $this->createFailedRules($product, $state, $ruleValidationResult),
            'ignoredRules' => $this->createIgnoredRules($product, $state, $ruleValidationResult),
            'cachedProduct' => $product->isCached(),
            'intention' => $intention,
            'renderImages' => $this->renderImageFactory->getRenderImagesByImageList($state->getState(), $product->getId()->getId())
        ];

        if ($appEnv === 'dev') {
            $result['allRules'] = $this->createAllRules($product, $state, $ruleValidationResult);
        }
        return $result;
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState       $enrichedState
     *
     * @return array
     * @throws CircularReferenceException
     * @throws InvalidUuidException
     */
    protected function createSections(ConfigurableProduct $product, EnrichedState $enrichedState): array
    {
        $state = $enrichedState->getState();
        $sections = [];
        $calculatedValueName = $this->rulePayloadFactory->getPayload($product, $state, false);

        foreach ($product->getSections() as $section) {
            $sectionId = new AptoUuid($section['id']);
            $elementIds = $product->getElementIds($sectionId);

            $sectionCount = 1;
            if ($section['repeatableType'] === Repeatable::TYPES[1]) {
                $sectionCount = $calculatedValueName->getComputedValues()[$section['repeatableCalculatedValueName']];
                $sectionCount = $sectionCount == 0 ? 1 : $sectionCount;
            }

            for($i = 0; $i < $sectionCount; $i++) {
                $sections[] = [
                    'id' => $sectionId->getId(),
                    'repetition' => $i,
                    'allowMultiple' => $section['allowMultiple'],
                    //'defaultElements' => [], // @TODO shall we implement defaultElements, just use isDefault flag from elements instead?!
                    'identifier' => $section['identifier'],
                    'isHidden' => $section['isHidden'],
                    'isMandatory' => $section['isMandatory'],
                    'repeatableType' => $section['repeatableType'],
                    'repeatableCalculatedValueName' => $section['repeatableCalculatedValueName'],
                    'name' => AptoTranslatedValue::fromArray($section['name'] ?: []),
                    'state' => [
                        'active' => $state->isSectionActive($sectionId, $i),
                        'complete' => $enrichedState->isSectionComplete($sectionId, $elementIds, $section['allowMultiple'], $section['isMandatory'], $i),
                        'disabled' => $enrichedState->isSectionDisabled($sectionId, $elementIds)
                    ]
                ];
            }
        }

        return $sections;
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState       $enrichedState
     *
     * @return array
     * @throws InvalidUuidException
     */
    protected function createElements(ConfigurableProduct $product, EnrichedState $enrichedState): array
    {
        $state = $enrichedState->getState();
        $elements = [];
        $calculatedValueName = $this->rulePayloadFactory->getPayload($product, $state, false);

        foreach ($product->getSections() as $section) {
            $sectionId = new AptoUuid($section['id']);

            $sectionCount = 1;
            if ($section['repeatableType'] === Repeatable::TYPES[1]) {
                $sectionCount = $calculatedValueName->getComputedValues()[$section['repeatableCalculatedValueName']];
                $sectionCount = $sectionCount == 0 ? 1 : $sectionCount;
            }

            for($i = 0; $i < $sectionCount; $i++) {
                foreach ($section['elements'] as $element) {
                    $elementId = new AptoUuid($element['id']);

                    // empty properties must be initialized with null, elements without electable values must use null instead of an empty array
                    $selectedValues = array_merge(
                        array_fill_keys(
                            array_keys(
                                $element['definition']['properties'] ?? []
                            ),
                            null
                        ),
                        $state->getValues($sectionId, $elementId, $i) ?: []
                    ) ?: null;

                    $elements[] = [
                        'id' => $elementId->getId(),
                        'sectionId' => $sectionId->getId(),
                        'sectionRepetition' => $i,
                        'errorMessage' => AptoTranslatedValue::fromArray($element['errorMessage'] ?: []),
                        'identifier' => $element['identifier'],
                        'isDefault' => $element['isDefault'],
                        'isMandatory' => $element['isMandatory'],
                        'name' => AptoTranslatedValue::fromArray($element['name'] ?: []),
                        'previewImage' => $element['previewImage'],
                        'properties' => $element['definition']['properties'] ?? null, // @TODO needed anymore?
                        'state' => [
                            'active' => $state->isElementActive($sectionId, $elementId, $i),
                            'disabled' => $enrichedState->isElementDisabled($sectionId, $elementId),
                            'values' => $selectedValues
                        ],
                        'staticValues' => $element['definition']['staticValues'] ?? []
                    ];
                }
            }
        }

        return $elements;
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState $state
     * @param RuleValidationResult $validationResult
     * @return array
     * @throws InvalidUuidException
     * @throws CircularReferenceException
     */
    protected function createFailedRules(ConfigurableProduct $product, EnrichedState $state, RuleValidationResult $validationResult): array
    {
        return $this->convertRulesToArray(
            $product,
            $state,
            $validationResult->getFailed()
        );
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState $state
     * @param RuleValidationResult $validationResult
     * @return array
     * @throws InvalidUuidException
     * @throws CircularReferenceException
     */
    protected function createIgnoredRules(ConfigurableProduct $product, EnrichedState $state, RuleValidationResult $validationResult): array
    {
        return $this->convertRulesToArray(
            $product,
            $state,
            $validationResult->getIgnored()
        );
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState $state
     * @param RuleValidationResult $validationResult
     * @return array
     * @throws InvalidUuidException
     * @throws CircularReferenceException
     */
    protected function createAllRules(ConfigurableProduct $product, EnrichedState $state, RuleValidationResult $validationResult): array
    {
        return $this->convertRulesToArray(
            $product,
            $state,
            array_merge($validationResult->getAffected(), $validationResult->getInactive())
        );
    }

    /**
     * @param ConfigurableProduct $product
     * @param EnrichedState $enrichedState
     * @param Rule[] $rules
     * @return array
     * @throws InvalidUuidException
     * @throws CircularReferenceException
     */
    private function convertRulesToArray(ConfigurableProduct $product, EnrichedState $enrichedState, array $rules): array
    {
        $state = $enrichedState->getState();
        $rulePayload = $this->rulePayloadFactory->getPayload($product, $enrichedState->getState());

        return array_map(
            function(Rule $rule) use ($product, $state, $rulePayload) {
                return $rule->toArray($product, $state, $rulePayload);
            },
            $rules
        );
    }

}
