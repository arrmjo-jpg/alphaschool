<?php

/*
|--------------------------------------------------------------------------
| Core domain-agnosticism (docs/DOMAIN_BLUEPRINT.md, Addendum B1)
|--------------------------------------------------------------------------
|
| Core must make sense in any ERP, not just a school one. If a class under
| App\Core ever needs to know what a "Student" or "Enrollment" is, it
| belongs in a Foundation or Domain module instead, per the promotion-not-
| prediction rule (B1). This is checked here at the class level and, at
| the module level, by deptrac's ruleset (deptrac.yaml — Core: []).
|
*/

arch('Core does not depend on any Foundation or Domain module')
    ->expect('App\Core')
    ->not->toUse('App\Modules');

arch('Core has no dependency on Eloquent models outside itself')
    ->expect('App\Core')
    ->not->toUse('App\Models');
