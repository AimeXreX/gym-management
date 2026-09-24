<?php

namespace App\Core\Tenancy;

enum GymResolution
{
    case Resolved;
    case NoGyms;
    case SelectionRequired;
    case InvalidSelection;
}
