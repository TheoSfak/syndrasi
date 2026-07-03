<?php
/**
 * SynDrasi - Canonical user role vocabulary (users.role ENUM).
 * Use these constants instead of raw strings in requireRole() calls and
 * route declarations so a typo is a fatal "undefined constant" error
 * instead of a silently-failing string comparison.
 * (Class constants rather than a PHP 8.1 backed enum so existing
 * string-comparison call sites keep working unchanged.)
 */
class Role
{
    public const SUPER_ADMIN        = 'super_admin';
    public const MUNICIPALITY_ADMIN = 'municipality_admin';
    public const EVENT_OPERATOR     = 'event_operator';
    public const TEAM_ADMIN         = 'team_admin';

    /** Every valid role (mirrors the users.role ENUM). */
    public const ALL = [
        self::SUPER_ADMIN,
        self::MUNICIPALITY_ADMIN,
        self::EVENT_OPERATOR,
        self::TEAM_ADMIN,
    ];

    /** Command-side staff: run operations, see the war-room. */
    public const COMMAND = [self::MUNICIPALITY_ADMIN, self::EVENT_OPERATOR];
}
