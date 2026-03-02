<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter
{
    private $startRow = 0;
    private $endRow   = 0;

    /**
     * @param int $startRow
     * @param int $chunkSize
     */
    public function __construct(int $startRow = 0, int $chunkSize = 0)
    {
        if ($chunkSize > 0) {
            $this->setRows($startRow, $chunkSize);
        }
    }

    public function setRows(int $startRow, int $chunkSize)
    {
        $this->startRow = $startRow;
        $this->endRow   = $startRow + $chunkSize;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        // Trendyol "Sipariş No" headers can be on row 1, 2, or 3 due to KVKK texts.
        // Always read the first 5 rows to be safe, plus the actual chunk zone.
        if ($row <= 5 || ($row >= $this->startRow && $row < $this->endRow)) {
            return true;
        }
        return false;
    }
}
