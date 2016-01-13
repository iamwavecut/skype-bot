<?php

namespace Bot\Plugins\Infrastructure;


interface GroupAdminInterface
{
    public static function registerGroup($chatGroup);

    public static function isGroupRegistered($chatGroup);

    public static function getGroups();
}
