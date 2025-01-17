<?php

namespace Apto\Catalog\Domain\Core\Factory\EnrichedState;

use Apto\Base\Domain\Core\Model\AptoUuid;
use Apto\Catalog\Domain\Core\Model\Configuration\State\State;

class EnrichedState implements \JsonSerializable
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var array
     */
    protected $disabled;

    /**
     * @var array
     */
    protected $complete;

    /**
     * @param State|null $state
     */
    public function __construct(State $state = null)
    {
        $this->state = $state ?? (new State());
        $this->disabled = [];
        $this->complete = [];
    }

    /**
     * @return State
     */
    public function getState(): State
    {
        return $this->state;
    }

    /**
     * we do not consider here repetition as repetition sections cannot be enabled or disabled separate from each other
     *
     * @param AptoUuid $sectionId
     * @param array $elementIds
     * @return bool
     */
    public function isSectionDisabled(AptoUuid $sectionId, array $elementIds): bool
    {
        foreach ($elementIds as $elementId) {
            if (!$this->isElementDisabled($sectionId, $elementId)) {
                return false;
            }
        }

        return true;
    }

    /**
     *  we do not consider here repetition as repetition sections cannot be enabled or disabled separate from each other
     *
     * @param AptoUuid $sectionId
     * @param AptoUuid $elementId
     *
     * @return bool
     */
    public function isElementDisabled(AptoUuid $sectionId, AptoUuid $elementId): bool
    {
        $section = $sectionId->getId();
        $element = $elementId->getId();

        return $this->disabled[$section][$element] ?? false;
    }

    /**
     * @param AptoUuid $sectionId
     * @param AptoUuid $elementId
     * @param bool $disabled
     * @return $this
     */
    public function setElementDisabled(AptoUuid $sectionId, AptoUuid $elementId, bool $disabled = true): self
    {
        $section = $sectionId->getId();
        $element = $elementId->getId();

        if ($disabled) {
            // create branch if needed
            if (!array_key_exists($section, $this->disabled)) {
                $this->disabled[$section] = [];
            }
            $this->disabled[$section][$element] = true;
        } else {
            unset($this->disabled[$section][$element]);
            if (!$this->disabled[$section]) {
                unset($this->disabled[$section]);
            }
        }

        return $this;
    }

    /**
     * @param AptoUuid $sectionId
     * @param array    $elementIds
     * @param bool     $allowMultiple
     * @param bool     $isMandatory
     * @param int      $repetition
     *
     * @return bool
     */
    public function isSectionComplete(AptoUuid $sectionId, array $elementIds, bool $allowMultiple, bool $isMandatory, int $repetition = 0): bool
    {
        $section = $sectionId->getId();

        // not selected mandatory sections can not be complete
        if ($isMandatory && !$this->state->isSectionActive($sectionId, $repetition)) {
            return false;
        }

        // multi-select sections can be completed manually
        if ($this->complete[$section][$repetition] ?? false) {
            return true;
        }

        // single select sections are complete, if at least one element is active
        if (!$allowMultiple) {
            return $this->state->isSectionActive($sectionId, $repetition);
        }

        // multi-select sections are automatically completed if all contained elements are active
        foreach ($elementIds as $elementId) {
            if (!$this->state->isElementActive($sectionId, $elementId, $repetition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param AptoUuid $sectionId
     * @param bool     $complete
     * @param int      $repetition
     *
     * @return $this
     */
    public function setSectionComplete(AptoUuid $sectionId, bool $complete = true, int $repetition = 0): self
    {
        $section = $sectionId->getId();

        if ($complete) {
            $this->complete[$section][$repetition] = true;
        } else {
            unset ($this->complete[$section][$repetition]);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'disabled' => $this->disabled,
            'complete' => $this->complete
        ];
    }

}
