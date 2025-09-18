import template from './sw-order-line-items-grid.html.twig';
import './sw-order-line-items-grid.scss';

const { Component } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    computed: {

        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            return columnDefinitions.map(col => {
                if (col.property === 'price.taxRules[0]') {
                    return {
                        ...col,
                        label: 'salesTaxUsa.order.lineItem.tax',
                        allowResize: false,
                        align: 'right',
                        inlineEdit: false,
                        width: '110px',
                    };
                }
                return col;
            });

        },

        lineItemTaxesMap() {
            if (!this.order || !this.order.lineItems) {
                return {};
            }
    
            const map = {};
    
            this.order.lineItems.forEach(lineItem => {
                map[lineItem.id] = Array.isArray(lineItem.payload?.inoceanUsaTaxInfo) 
                    ? lineItem.payload.inoceanUsaTaxInfo.map(taxInfo => ({
                        taxName: taxInfo.name,
                        taxRate: taxInfo.rate,
                        taxPrice: Number(taxInfo.tax) || 0,
                    })) 
                    : [];
            });

            return map;
        },
    
        lineItemsTaxes() {
            return Object.entries(this.lineItemTaxesMap).map(([id, taxes]) => ({
                id,
                taxes,
            }));
        }

    }
});