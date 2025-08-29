import template from './sw-order-detail-general.html.twig';

const { Component } = Shopware;

Component.override('sw-order-detail-general', {
    template,

    computed: {
        taxStatus() {
            return this.order.price.taxStatus;
        },
        sortedCalculatedTaxes() {

            if (!this.order || !this.order.lineItems) {
                return [];
            }

            const taxAggregation = {};

            this.order.lineItems.forEach(lineItem => {
                if (lineItem.payload && Array.isArray(lineItem.payload.inoceanUsaTaxInfo)) {
                    lineItem.payload.inoceanUsaTaxInfo.forEach(taxInfo => {
                        const rateKey = taxInfo.name;

                        if (!taxAggregation[rateKey]) {
                            taxAggregation[rateKey] = {
                                taxName: taxInfo.name,
                                taxRate: taxInfo.rate,
                                taxPriceTotal: 0,
                            };
                        }

                        taxAggregation[rateKey].taxPriceTotal += Number(taxInfo.tax) || 0;
                    });
                }
            });

            const aggregatedTaxes = Object.values(taxAggregation);

            // aggregatedTaxes.sort((a, b) => a.taxRate - b.taxRate);
            return aggregatedTaxes.map(tax => ({
                taxDetails: {
                    name: tax.taxName,
                    rate: tax.taxRate,
                    tax: tax.taxPriceTotal,
                },
            }));
        },
        sortedShippingTaxes() {

            if (!this.order || !this.order.lineItems) {
                return [];
            }

            const shippingTaxAggregation = {};

            this.order.lineItems.forEach(lineItem => {
                if (lineItem.payload && Array.isArray(lineItem.payload.inoceanShippingTaxInfo)) {
                    lineItem.payload.inoceanShippingTaxInfo.forEach(taxInfo => {
                        const rateKey = taxInfo.name;

                        if (!shippingTaxAggregation[rateKey]) {
                            shippingTaxAggregation[rateKey] = {
                                taxName: taxInfo.name,
                                taxRate: taxInfo.rate,
                                taxPriceTotal: 0,
                            };
                        }

                        shippingTaxAggregation[rateKey].taxPriceTotal += Number(taxInfo.tax) || 0;
                    });
                }
            });

            const aggregatedShippingTaxes = Object.values(shippingTaxAggregation);

            return aggregatedShippingTaxes.map(tax => ({
                taxDetails: {
                    name: tax.taxName,
                    rate: tax.taxRate,
                    tax: tax.taxPriceTotal,
                },
            }));
        },
    }
});