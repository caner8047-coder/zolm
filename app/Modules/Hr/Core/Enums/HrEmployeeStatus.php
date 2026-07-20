<?php

namespace App\Modules\Hr\Core\Enums;

enum HrEmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
