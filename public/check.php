<?php
require __DIR__.'/../vendor/autoload.php';

echo "<h1>Stopaj Math Test</h1>";

$gross = 1257.00;
$vatRate = 0.10; // 10% VAT
$vatExcluded = $gross / (1 + $vatRate);
$stopajExpected = $vatExcluded * 0.01; // 1%

echo "Gross Amount: " . number_format($gross, 2) . " TL<br>";
echo "VAT Rate: " . ($vatRate * 100) . "%<br>";
echo "VAT Excluded Amount (Base for Stopaj): " . number_format($vatExcluded, 2) . " TL<br>";
echo "Calculated Stopaj (1%): " . number_format($stopajExpected, 2) . " TL<br>";
echo "User Reported Stopaj: 11.43 TL<br><br>";

echo "<strong>Conclusion:</strong> Trendyol calculates the 1% Withholding Tax (Stopaj) on the VAT-excluded price (KDV Hariç Tutar). The AuditEngine is currently calculating it on the gross amount, causing the 1.14 TL false-positive error.";
