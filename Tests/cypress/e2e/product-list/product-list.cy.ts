import { Interception } from 'cypress/types/net-stubbing';
import { IProductListResponse, ProductList } from '../../classes/product-list/product-list';
import { RequestHandler } from '../../classes/requestHandler';
import { Common } from '../../classes/common';
import { Product } from '@apto-catalog-frontend/store/product/product.model';
import { Language } from '../../classes/language';

describe('Product list', () => {

  const baseUrl = Cypress.env('baseUrl');

  beforeEach(() => {
    const requests = ProductList.initialRequestList;

    requests.forEach((request) => RequestHandler.interceptQuery(request.alias));

    ProductList.visit();
  });

  it('Checks product list frontend', () => {

    // if all requests are made
    cy.wait(ProductList.initialAliasList).then(($response: Interception[]) => {

      // check also that incoming product data is in sync with displayed products
      $response.forEach(($query) => {

        expect(RequestHandler.hasResponseError($query)).to.equal(false);

        if ($query.request.body.query === 'FindProductsByFilter') {

          const result = $query.response.body.result as IProductListResponse;

          // we should see in product list "numberOfRecords" amount of products
          cy.get('.product-wrapper').should('have.length', result.numberOfRecords);

          result.data.forEach((product: Product) => {
            cy.get(`.product-wrapper[data-id="${product.id}"]`).then((productElement) => {

              // active products should be visible
              if (product.active) {
                cy.wrap(productElement).should('exist');
              }

              // if product has image, it should not be broken
              if (product.previewImage && product.previewImage.length) {
                cy.wrap(productElement).find('img').should((img) => {
                  Common.isImageLoadedCheck(img);
                });
              }

              // product title
              cy.wrap(productElement).find('.product-description').find('h3').should('not.be.empty');


              // product description
              if (Language.isTranslatedValueSet(product.description)) {
                cy.wrap(productElement).find('.product-description').find('.description').should('not.be.empty');
              }
              else {
                cy.wrap(productElement).find('.product-description').find('.description').should('not.exist');
              }


              // check that links for each product are clickable and exist
              cy.wrap(productElement)
                .find('.product-description button')
                .invoke('attr', 'data-link')
                .then((link) => {
                  cy.wrap(link).should('exist');
                  cy.wrap(link).should('not.be.empty');

                  Common.isLinkBrokenTest(`${baseUrl}#${link}`);
                });
            });
          });
        }
      });
    });
  });
});
