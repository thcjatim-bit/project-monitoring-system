<?php

namespace App\Enums;

enum MasterKind: string
{
    case Material = 'material';
    case Unit = 'unit';
    case Pop = 'pop';
    case PekerjaanJasa = 'pekerjaan_jasa';
    case Warehouse = 'warehouse';
}
