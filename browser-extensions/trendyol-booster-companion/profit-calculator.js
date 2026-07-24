(() => {
  'use strict';

  const money = (value) => Math.round((Number(value) || 0) * 100) / 100;

  /**
   * ZOLM kanonik kârlılık sözleşmesinin tarayıcı karşılığı.
   * salePrice satıcı tarafından karşılanan indirim öncesiyse sellerDiscount ayrıca verilir.
   * Tüm maliyet girdileri KDV dahil brüt tutardır; KDV muhasebesi sunucu tarafında yapılır.
   */
  function calculate(input = {}) {
    const salePrice = Math.max(0, Number(input.salePrice) || 0);
    const sellerDiscount = Math.max(0, Number(input.sellerDiscount) || 0);
    const grossRevenue = money(Math.max(0, salePrice - sellerDiscount));
    const cost = input.cost || {};
    const cogs = Math.max(0, Number(cost.cogs) || 0);
    const packagingCost = Math.max(0, Number(cost.packaging_cost) || 0);
    const cargoCost = Math.max(0, Number(cost.cargo_cost) || 0);
    const extraFixed = Math.max(0, Number(cost.extra_cost_fixed) || 0);
    const extraRate = Math.max(0, Number(cost.extra_cost_percentage) || 0);
    const commissionRate = Math.max(0, Number(input.commissionRate ?? cost.commission_rate) || 0);
    const serviceFee = money(Math.max(0, Number(input.serviceFeeFixed) || 0));
    const vatRate = Math.max(0, Number(cost.vat_rate) || 0);
    const withholdingRate = Math.max(0, Number(input.withholdingRate ?? 1) || 0);
    const withholdingBase = vatRate > 0 ? grossRevenue / (1 + vatRate / 100) : grossRevenue;
    const commission = money(grossRevenue * commissionRate / 100);
    const withholding = input.withholdingEnabled
      ? money(withholdingBase * withholdingRate / 100)
      : 0;
    const extraPercentageCost = money(grossRevenue * extraRate / 100);
    const totalCost = money(cogs + packagingCost + cargoCost + extraFixed + extraPercentageCost);
    const accountingProfit = money(grossRevenue - commission - serviceFee - totalCost);
    const cashProfit = money(accountingProfit - withholding);

    return {
      calculationVersion: 2,
      grossRevenue,
      salePrice,
      sellerDiscount,
      cogs,
      packagingCost,
      cargoCost,
      extraFixed,
      extraRate,
      extraPercentageCost,
      totalCost,
      commissionRate,
      commissionAmount: commission,
      serviceFee,
      vatRate,
      withholdingBase: money(withholdingBase),
      withholdingTax: withholding,
      withholdingTaxCredit: withholding,
      accountingProfit,
      cashProfit,
      netProfit: cashProfit,
      profitMargin: cogs > 0 ? Math.round((cashProfit / cogs) * 1000) / 10 : 0,
      salesMargin: grossRevenue > 0 ? Math.round((cashProfit / grossRevenue) * 1000) / 10 : 0,
      hasCost: cogs > 0,
    };
  }

  globalThis.ZolmProfitCalculator = Object.freeze({
    version: 2,
    calculate,
  });
})();
