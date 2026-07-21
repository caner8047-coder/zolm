<?php

namespace App\Modules\Hr\Leave\Enums;

enum LeavePolicyScope: string
{
    case Company = 'company';
    case Branch = 'branch';
    case Department = 'department';
    case Position = 'position';
    case EmploymentType = 'employment_type';
}
