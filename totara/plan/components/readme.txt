To add a new component:

1. Pick a [COMPONENTNAME]. Make sure it is different from any existing *role* names, and that it's not the same as any of the properties of the development_plan class defined in totara/plan/development_plan.class.php (because a property is created for each component using its name).
3. Create a file called totara/plan/components/[COMPONENTNAME]/[COMPONENTNAME].class.php
4. Create a class called dp_[COMPONENTNAME]_component in the file which extends dp_base_component
5. Implement the required abstract functions
6. Add [COMPONENTNAME] to the $DP_AVAILABLE_COMPONENTS global variable in plan/lib.php
