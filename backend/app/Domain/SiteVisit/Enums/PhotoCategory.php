<?php

declare(strict_types=1);

namespace App\Domain\SiteVisit\Enums;

enum PhotoCategory: string
{
    case AccessRoad = 'access_road';
    case FrontView = 'front_view';
    case RearView = 'rear_view';
    case LeftView = 'left_view';
    case RightView = 'right_view';
    case Boundary = 'boundary';
    case Floor = 'floor';
    case InternalRoom = 'internal_room';
    case Staircase = 'staircase';
    case Kitchen = 'kitchen';
    case Toilet = 'toilet';
    case Roof = 'roof';
    case UtilitySystem = 'utility_system';
    case StructuralDefect = 'structural_defect';
    case Neighbourhood = 'neighbourhood';
    case GpsEvidence = 'gps_evidence';
    case DocumentEvidence = 'document_evidence';
    case Other = 'other';
}
