import { TranslatedValue } from '@apto-base-core/store/translated-value/translated-value.model';
import { selectLocale } from '@apto-base-frontend/store/language/language.selectors';
import {
  ProgressState,
  ProgressStep,
  RenderImage,
  RenderImageData, SectionPriceTableItem,
} from '@apto-catalog-frontend/store/configuration/configuration.model';
import { CatalogFeatureState, featureSelector } from '@apto-catalog-frontend/store/feature';
import { createSelector } from '@ngrx/store';
import { Element, Section } from '../product/product.model';

export const selectConfiguration = createSelector(featureSelector, (state: CatalogFeatureState) => state.configuration);

export const selectHideOnePage = createSelector(featureSelector, (state: CatalogFeatureState) => state.configuration.hideOnePage);

export const selectConfigurationLoading = createSelector(featureSelector, (state: CatalogFeatureState) => state.configuration.loading);

export const selectProduct = createSelector(featureSelector, (state: CatalogFeatureState) => state.product);

function getDescription(section: Section, state: CatalogFeatureState, locale: string | null): string {
	const elements = state.configuration.state.elements.filter((e) => e.active && e.sectionId === section?.id).map((e) => e.id);

	const description = state.product.elements
		.filter((e) => elements.includes(e.id))
		.map((e) => e.name[locale || ''])
		.join(', ');

	return description;
}

export const selectProgressState = createSelector(featureSelector, selectLocale, (state: CatalogFeatureState, locale: string | null) => {
	const currentSection = state.product.sections.find((section) => section.id === state.configuration.currentStep);
	const sections = state.configuration.state.sections.filter((section) => !section.hidden && !section.disabled);

	let currentStep: ProgressStep | undefined;
	const afterSteps: ProgressStep[] = [];
	const beforeSteps: ProgressStep[] = [];

	for (const s of sections) {
		const pSection = state.product.sections.find((ppSection) => ppSection.id === s.id);
		if (!pSection) {
			continue;
		}

		const elements = state.configuration.state.elements.filter((e) => !e.disabled && e.sectionId === s.id).map((e) => e.id);

		const pElements = state.product.elements.filter((e) => elements.includes(e.id));

		const progressElements = pElements.map((pElement) => ({
			state: state.configuration.state.elements.find((e) => e.id === pElement?.id)!,
			element: pElement,
		}));

		const description = getDescription(pSection, state, locale);

		const section: Section = { ...pSection };

		const active: boolean = progressElements.some((e) => e.state.active);

		const fulfilledElements: boolean[] = [];

		let fulfilled: boolean = false;

		if (!s.mandatory) {
			fulfilled = true;
		}

		for (const entry of progressElements) {
			if (!entry.element.isMandatory || (entry.element.isMandatory && entry.state.active)) {
				fulfilledElements.push(true);
			}
			if (entry.element.isMandatory && !entry.state.active) {
				fulfilledElements.push(false);
			}
		}

		if (s.mandatory && fulfilledElements.every((e) => e) && active) {
			fulfilled = true;
		}

		if (section.id === state.configuration.currentStep) {
			currentStep = {
				status: 'CURRENT',
				fulfilled,
				description,
				active,
				section,
				elements: progressElements,
			};
		} else if (currentStep) {
			afterSteps.push({
				status: 'REMAINING',
				fulfilled,
				description,
				active,
				section,
				elements: progressElements,
			});
		} else {
			beforeSteps.push({
				status: 'COMPLETED',
				fulfilled,
				description,
				active,
				section,
				elements: progressElements,
			});
		}
	}
	const progress: number = Math.round(
		((afterSteps.filter((s) => s.fulfilled).length + beforeSteps.filter((s) => s.fulfilled).length + (currentStep?.fulfilled ? 1 : 0)) /
			(beforeSteps.length + afterSteps.length + (currentStep?.fulfilled ? 1 : 0))) *
			100
	);

	const progressState: ProgressState = {
		productId: state.product.product?.id,
		currentStep,
		afterSteps,
		beforeSteps,
		steps: currentStep ? [...beforeSteps, currentStep, ...afterSteps] : [...beforeSteps, ...afterSteps],
		progress,
	};

	return progressState;
});

export const selectProgress = createSelector(selectProgressState, (state: ProgressState) =>
	Math.round((state.steps.filter((s) => s.fulfilled).length / state.steps.length) * 100)
);

export const selectCompressedState = createSelector(
	featureSelector,
	(state: CatalogFeatureState) => state.configuration.state.compressedState
);

export const selectRenderImage = createSelector(featureSelector, (state: CatalogFeatureState): RenderImage | null => {
	let currentRenderImage: RenderImage | null = null;

	// search current render image
	// state.configuration.renderImages.every((renderImage) => {
	// 	if (renderImage.perspective === state.configuration.currentPerspective) {
	// 		currentRenderImage = renderImage;
	// 		return false;
	// 	}
	// 	return true;
	// });

	return currentRenderImage;
});

export const selectCurrentRenderImages = createSelector(featureSelector, (state: CatalogFeatureState): RenderImageData[] => {
  let currentRenderImages: RenderImageData[] = [];

  // search current render image
  Object.keys(state.configuration.renderImages).forEach((key,index) => {
    if (key === state.configuration.currentPerspective) {
      currentRenderImages = state.configuration.renderImages[key];
    }
  });

  return currentRenderImages;
});

export const selectRenderImageByPerspective = (perspective: string) => createSelector(featureSelector, (state: CatalogFeatureState): RenderImage | null => {
    let currentRenderImage: RenderImage | null = null;

    // search current render image
    // state.configuration.renderImages.every((renderImage) => {
    //   if (renderImage.perspective === perspective) {
    //     currentRenderImage = renderImage;
    //     return false;
    //   }
    //   return true;
    // });

    return currentRenderImage;
  }
);

export const selectSumPrice = createSelector(featureSelector, (state: CatalogFeatureState) => {
	if (state.configuration.statePrice === null) {
		return null;
	}
	return state.configuration.statePrice.sum.price.formatted;
});

export const selectCurrentPerspective = createSelector(
	featureSelector,
	(state: CatalogFeatureState) => state.configuration.currentPerspective
);

export const selectPerspectives = createSelector(featureSelector, (state: CatalogFeatureState) => {
	const perspectives: string[] = [];

  Object.keys(state.configuration.renderImages).forEach((key,index) => {
    if (state.configuration.renderImages[key].length > 0) {
      perspectives.push(key);
    }
  });

	return perspectives;
});

export const selectBasicPrice = createSelector(featureSelector, (state: CatalogFeatureState) => {
	if (state.configuration.statePrice === null) {
		return null;
	}
	return state.configuration.statePrice.own.price.formatted;
});

export const selectBasicPseudoPrice = createSelector(featureSelector, (state: CatalogFeatureState) => {
	if (state.configuration.statePrice === null) {
		return null;
	}
	return state.configuration.statePrice.own.pseudoPrice.formatted;
});

export const selectSumPseudoPrice = createSelector(featureSelector, (state: CatalogFeatureState) => {
	if (state.configuration.statePrice === null) {
		return null;
	}
	return state.configuration.statePrice.sum.pseudoPrice.formatted;
});

export const selectSectionPrice = (section: Section): any =>
	createSelector(featureSelector, (state: CatalogFeatureState) => {
		if (state.configuration.statePrice === null) {
			return null;
		}
		return Object.entries(state.configuration.statePrice.sections).find(([key]) => key === section.id)?.[1].sum.price.formatted;
	});

export const selectSectionPseudoPrice = (section: Section): any =>
  createSelector(featureSelector, (state: CatalogFeatureState) => {
    if (state.configuration.statePrice === null) {
      return null;
    }
    return Object.entries(state.configuration.statePrice.sections).find(([key]) => key === section.id)?.[1].sum.pseudoPrice.formatted;
  });

export const selectSectionPriceTable = (section: Section): any => createSelector(featureSelector, (state: CatalogFeatureState): SectionPriceTableItem[] => {
  let priceTable: SectionPriceTableItem[] = [];

  // if that section has no surcharges we can return an empty array here
  if (!state.configuration.statePrice.sections.hasOwnProperty(section.id)) {
    return priceTable;
  }

  // add all element surcharges of that section to the priceTable array
  const sectionPrices = state.configuration.statePrice.sections[section.id];
  Object.keys(sectionPrices.elements).forEach((elementId) => {
    const elementPrice = sectionPrices.elements[elementId];
    const pElement = state.product.elements.find(e => e.id === elementId);

    // we do not want to add elements without a surcharge
    if (elementPrice.own.price.amount <= 0) {
      return;
    }

    // add element own price
    priceTable.push({
      name: pElement.name,
      value: elementPrice.own.pseudoPrice.formatted,
      position: pElement.position,
      isDiscount: false,
      isSection: false,
      isElement: true
    });

    // add element discount
    if (elementPrice.own.pseudoDiff.amount !== 0) {
      priceTable.push({
        name: elementPrice.discount.name,
        value: elementPrice.own.pseudoDiff.formatted,
        // element position is always an integer, we add 0.5 here to make sure after sorting, the element price and its discount is displayed one below the other
        position: pElement.position + 0.5,
        isDiscount: true,
        isSection: false,
        isElement: true
      });
    }
  });

  // resort elements by its position
  priceTable.sort((a,b) => a.position - b.position);

  // add section discount
  if (sectionPrices.own.pseudoDiff.amount !== 0) {
    priceTable.unshift({
      name: sectionPrices.discount.name,
      value: sectionPrices.own.pseudoDiff.formatted,
      position: -1,
      isDiscount: true,
      isSection: true,
      isElement: false
    });
  }

  // add section own price
  if (sectionPrices.own.pseudoPrice.amount !== 0) {
    priceTable.unshift({
      name: section.name,
      value: sectionPrices.own.pseudoPrice.formatted,
      position: -2,
      isDiscount: false,
      isSection: true,
      isElement: false
    });
  }

  return priceTable;
});

export const selectQuantity = createSelector(featureSelector, (state: CatalogFeatureState) => state.configuration.quantity);

export const selectElementValues = (element: Element): any =>
	createSelector(featureSelector, (state: CatalogFeatureState): TranslatedValue[] => {
    if (!state.configuration.humanReadableState) {
      return [];
    }

    const x = Object.entries<any>(state.configuration.humanReadableState).find((e) => e[0] === element.id);
    if (x) {
      return Object.values(x[1]);
    }

    return [];
	});

export const selectHumanReadableState = createSelector(featureSelector, (state: CatalogFeatureState) => state.configuration.humanReadableState);

export const selectCurrentProductElements = createSelector(featureSelector, (state: CatalogFeatureState) => {
  return state.product.elements.filter((element) => element.sectionId === state.configuration.currentStep);
});

export const selectCurrentStateElements = createSelector(featureSelector, (state: CatalogFeatureState) => {
  return state.configuration.state.elements.filter((element) => element.sectionId === state.configuration.currentStep);
});

export const selectSectionProductElements = (sectionId: string) => createSelector(featureSelector, (state: CatalogFeatureState) => {
  return state.product.elements.filter((element) => element.sectionId === sectionId);
});

export const selectSectionStateElements = (sectionId: string) => createSelector(featureSelector, (state: CatalogFeatureState) => {
  return state.configuration.state.elements.filter((element) => element.sectionId === sectionId);
});

export const selectElementState = (elementId: string) => createSelector(featureSelector, (state: CatalogFeatureState) => {
  const filtered = state.configuration.state.elements.filter((element) => element.id === elementId);
  if (filtered.length > 0) {
    return filtered[0];
  }
  return null;
});

export const selectStateElements = createSelector(featureSelector, (state: CatalogFeatureState) => {
  return state.configuration.state.elements;
});
