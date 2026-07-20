<?php

namespace App\Modules\Hr\Core\Http\Controllers;

use App\Modules\Hr\Personnel\Actions\ExportEmployeesAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class HrExportController extends Controller
{
    public function exportEmployees(Request $request)
    {
        $action = app(ExportEmployeesAction::class);

        $path = $action->execute(
            filters: $request->only(['status', 'department_id', 'branch_id']),
            options: [
                'view_identity' => $request->user()->hasHrPermission('hr.employees.view_identity'),
                'view_bank' => $request->user()->hasHrPermission('hr.employees.view_bank'),
            ]
        );

        $fullPath = storage_path("app/private/{$path}");

        return response()->download($fullPath, basename($path), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
