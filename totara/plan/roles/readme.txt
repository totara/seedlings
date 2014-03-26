To add a new role:

1. Pick a [ROLENAME]. Make sure it is different from any existing *component* names, and that it's not the same as any of the properties of the development_plan class defined in totara/plan/development_plan.class.php (because a property is created for each role using its name).
3. Create a file called totara/plan/roles/[ROLENAME]/[ROLENAME].class.php
4. Create a class called dp_[ROLENAME]_role in the file which extends dp_base_role
5. Implement the required abstract functions
6. Add [ROLENAME] to the $DP_AVAILABLE_ROLES global variable in plan/lib.php
