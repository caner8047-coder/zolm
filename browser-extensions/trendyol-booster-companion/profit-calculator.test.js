import test from 'node:test';
import assert from 'node:assert/strict';
import './profit-calculator.js';

test('approved Trendyol example matches the server contract', () => {
  const result = globalThis.ZolmProfitCalculator.calculate({
    salePrice: 839.90,
    commissionRate: 22,
    serviceFeeFixed: 9.33,
    withholdingEnabled: true,
    withholdingRate: 1,
    cost: {
      cogs: 373.24,
      packaging_cost: 0,
      cargo_cost: 194.60,
      extra_cost_fixed: 0,
      extra_cost_percentage: 0,
      vat_rate: 10,
    },
  });

  assert.equal(result.calculationVersion, 2);
  assert.equal(result.commissionAmount, 184.78);
  assert.equal(result.withholdingBase, 763.55);
  assert.equal(result.withholdingTax, 7.64);
  assert.equal(result.accountingProfit, 77.95);
  assert.equal(result.cashProfit, 70.31);
  assert.equal(result.profitMargin, 18.8);
  assert.equal(result.salesMargin, 8.4);
});

test('seller-funded discount changes revenue, commission and withholding once', () => {
  const result = globalThis.ZolmProfitCalculator.calculate({
    salePrice: 1000,
    sellerDiscount: 100,
    commissionRate: 10,
    serviceFeeFixed: 9.33,
    withholdingEnabled: true,
    cost: { cogs: 400, vat_rate: 20 },
  });

  assert.equal(result.grossRevenue, 900);
  assert.equal(result.commissionAmount, 90);
  assert.equal(result.withholdingBase, 750);
  assert.equal(result.withholdingTax, 7.5);
  assert.equal(result.cashProfit, 393.17);
});
