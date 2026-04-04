INSERT INTO mp_products (
    user_id, barcode, stock_code, product_name, 
    cogs, packaging_cost, vat_rate, cargo_cost, pieces, desi, created_at, updated_at
)
SELECT 
    IFNULL(p.updated_by, 1) as user_id, 
    IF(pc.barcode IS NULL OR pc.barcode = '', p.stok_kodu, pc.barcode) as barcode, 
    p.stok_kodu as stock_code, 
    p.urun_adi as product_name,
    COALESCE(pc.production_cost, p.tutar) as cogs,
    COALESCE(pc.packaging_cost, 0) as packaging_cost,
    COALESCE(pc.vat_rate, 20.00) as vat_rate,
    COALESCE(pc.shipping_cost, 0) as cargo_cost,
    p.parca as pieces,
    p.desi as desi,
    NOW(),
    NOW()
FROM products p
LEFT JOIN product_costs pc ON p.stok_kodu = pc.stock_code
ON DUPLICATE KEY UPDATE 
    stock_code = VALUES(stock_code),
    product_name = VALUES(product_name),
    cogs = VALUES(cogs),
    packaging_cost = VALUES(packaging_cost),
    vat_rate = VALUES(vat_rate),
    cargo_cost = VALUES(cargo_cost),
    pieces = VALUES(pieces),
    desi = VALUES(desi),
    updated_at = NOW();
