<?php

namespace App\Enum;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
}
