<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application UI strings
    |--------------------------------------------------------------------------
    |
    | Every user-facing string goes through __('app....'). Keep keys stable —
    | Livewire components and Blade views reference them directly.
    |
    */

    'nav' => [
        'dashboard' => 'Dashboard',
        'runs' => 'Checklist Runs',
        'kiosk' => 'Kiosk',
        'machines' => 'Machines',
        'locations' => 'Locations',
        'parts' => 'Parts',
        'templates' => 'Checklist Templates',
        'holidays' => 'Holidays',
        'issues' => 'Issues',
        'reports' => 'Reports',
        'users' => 'Users',
        'settings' => 'Settings',
        'admin' => 'Admin',
        'profile' => 'Profile',
        'logout' => 'Log Out',
    ],

    'auth' => [
        'login' => 'Log in',
        'login_title' => 'Sign in to your account',
        'email_or_employee_number' => 'Email or employee number',
        'password' => 'Password',
        'remember_me' => 'Remember me',
        'failed' => 'These credentials do not match our records.',
        'inactive' => 'This account has been deactivated. Contact your supervisor.',
        'logged_out' => 'You have been logged out.',
    ],

    // Run status — enum RunStatus (§3). Always shown with the coloured dot,
    // never colour alone.
    'status' => [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'missed' => 'Missed',
    ],

    // Run item status — enum RunItemStatus.
    'item_status' => [
        'pending' => 'Pending',
        'done' => 'Done',
        'not_applicable' => 'N/A',
        'failed' => 'Failed',
    ],

    // Work category — enum WorkCategory.
    'work_category' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'general' => 'General',
    ],

    // Frequency — enum Frequency.
    'frequency' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'on_demand' => 'On Demand',
    ],

    // Response type — enum ResponseType.
    'response_type' => [
        'check' => 'Checkbox',
        'pass_fail' => 'Pass / Fail',
        'numeric' => 'Numeric',
        'text' => 'Text',
    ],

    // Shift — enum Shift.
    'shift' => [
        'day' => 'Day',
        'night' => 'Night',
        'all' => 'All Day',
    ],

    // Issue severity — enum IssueSeverity.
    'issue_severity' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'breakdown' => 'Breakdown',
    ],

    // Issue status — enum IssueStatus.
    'issue_status' => [
        'open' => 'Open',
        'acknowledged' => 'Acknowledged',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'submit' => 'Submit',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'add' => 'Add',
        'create' => 'Create',
        'update' => 'Update',
        'view' => 'View',
        'back' => 'Back',
        'search' => 'Search',
        'filter' => 'Filter',
        'clear' => 'Clear',
        'close' => 'Close',
        'confirm' => 'Confirm',
        'start' => 'Start',
        'continue' => 'Continue',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'restore' => 'Restore',
        'reorder' => 'Reorder',
        'move_up' => 'Move up',
        'move_down' => 'Move down',
        'export' => 'Export',
        'download' => 'Download',
        'print' => 'Print',
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'common' => [
        'name' => 'Name',
        'code' => 'Code',
        'description' => 'Description',
        'status' => 'Status',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'actions' => 'Actions',
        'all' => 'All',
        'none' => 'None',
        'total' => 'Total',
        'date' => 'Date',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'no_results' => 'No results found.',
        'showing_results' => 'Showing :first–:last of :total',
        'are_you_sure' => 'Are you sure?',
        'confirm_delete' => 'Are you sure you want to delete this? This cannot be undone.',
        'saved' => 'Saved.',
        'created' => 'Created.',
        'updated' => 'Updated.',
        'deleted' => 'Deleted.',
        'error' => 'Something went wrong. Please try again.',
        'not_authorized' => 'You are not authorised to do that.',
        'optional' => 'Optional',
        'required' => 'Required',
    ],

    'machines' => [
        'title' => 'Machines',
        'machine' => 'Machine',
        'manufacturer' => 'Manufacturer',
        'model' => 'Model',
        'asset_tag' => 'Asset Tag',
        'location' => 'Location',
        'notes' => 'Notes',
        'add_machine' => 'Add Machine',
        'edit_machine' => 'Edit Machine',
        'no_machines' => 'No machines found.',
        'qr_hint' => 'The machine code is what the QR sticker encodes.',
    ],

    'locations' => [
        'title' => 'Locations',
        'location' => 'Location',
        'site' => 'Site',
        'floor' => 'Floor',
        'building' => 'Building',
        'add_location' => 'Add Location',
        'edit_location' => 'Edit Location',
    ],

    'parts' => [
        'title' => 'Parts',
        'part' => 'Part',
        'part_code' => 'Part Code',
        'unit' => 'Unit',
        'add_part' => 'Add Part',
        'edit_part' => 'Edit Part',
        'attach_part' => 'Attach Part',
        'no_parts' => 'No parts listed.',
    ],

    'templates' => [
        'title' => 'Checklist Templates',
        'template' => 'Template',
        'work_category' => 'Work Category',
        'work_description' => 'Work Description',
        'frequency' => 'Frequency',
        'per_shift' => 'One run per shift',
        'weekly_weekday' => 'Runs on weekday',
        'monthly_day' => 'Runs on day of month',
        'requires_supervisor_signoff' => 'Requires supervisor sign-off',
        'grace_period_hours' => 'Grace period (hours)',
        'version' => 'Version',
        'items' => 'Checklist Items',
        'item' => 'Item',
        'guidance' => 'Guidance',
        'requires_photo_on_fail' => 'Photo required on fail',
        'used_parts' => 'Used Parts',
        'add_template' => 'Add Template',
        'edit_template' => 'Edit Template',
        'add_item' => 'Add Item',
        'no_items' => 'This template has no items yet.',
        'version_note' => 'Editing items creates a new version. Historical runs keep the wording they were completed with.',
    ],

    'holidays' => [
        'title' => 'Holidays',
        'holiday' => 'Holiday',
        'all_sites' => 'All sites',
        'is_recurring' => 'Recurs every year',
        'add_holiday' => 'Add Holiday',
        'edit_holiday' => 'Edit Holiday',
    ],

    'runs' => [
        'title' => 'Checklist Runs',
        'run' => 'Run',
        'scheduled_for' => 'Scheduled for',
        'machine' => 'Machine',
        'template' => 'Checklist',
        'shift' => 'Shift',
        'operator' => 'Operator',
        'supervisor' => 'Supervisor',
        'started_at' => 'Started',
        'submitted_at' => 'Submitted',
        'approved_at' => 'Approved',
        'due_today' => 'Due today',
        'overdue' => 'Overdue',
        'no_runs' => 'No checklist runs found.',
        'no_runs_due' => 'Nothing due for this machine today.',
        'progress' => ':done of :total',
        'notes' => 'Notes',
        'notes_placeholder' => 'Anything worth recording about this maintenance…',
        'used_parts' => 'Used Parts',
        'qty_used' => 'Qty used',
        'downtime_minutes' => 'Downtime (minutes)',
        'mark_done' => 'Done',
        'mark_not_applicable' => 'Mark N/A',
        'mark_failed' => 'Mark Failed',
        'undo' => 'Undo',
        'fail_reason' => 'What is wrong?',
        'fail_reason_required' => 'A reason is required when an item fails.',
        'value_numeric' => 'Reading',
        'value_text' => 'Response',
        'pass' => 'Pass',
        'fail' => 'Fail',
        'start_run' => 'Start Checklist',
        'continue_run' => 'Continue Checklist',
        'submit_run' => 'Submit Checklist',
        'submitted_message' => 'Checklist submitted. Thank you.',
        'cannot_submit_incomplete' => 'You cannot submit yet — these required items are still pending:',
        'autosaved' => 'Saved automatically',
        'rejected_reason' => 'Rejected: :reason',
        'raise_issue_prompt' => 'This item failed. Raise an issue for maintenance?',
        'supervisor_comment' => 'Supervisor comment',
    ],

    'kiosk' => [
        'title' => 'Maintenance Kiosk',
        'pick_machine' => 'Choose a machine',
        'filter_by_location' => 'Filter by location',
        'all_locations' => 'All locations',
        'due_today' => 'Due today',
        'nothing_due' => 'No checklists due for this machine today.',
        'enter_pin' => 'Enter your PIN',
        'pin' => 'PIN',
        'employee_number' => 'Employee number',
        'who_are_you' => 'Who are you?',
        'pin_incorrect' => 'That PIN is not correct.',
        'pin_locked' => 'Too many attempts. Try again in :minutes minutes.',
        'pin_length' => 'Your PIN is :min to :max digits.',
        'signed_in_as' => 'Signed in as :name',
        'release' => 'Done — return to kiosk',
        'released' => 'Session ended. The tablet is back at the kiosk.',
        'idle_warning' => 'Still there? This session will end in :seconds seconds.',
        'idle_released' => 'Session ended after inactivity.',
        'device_not_registered' => 'This device is not registered as a kiosk. Contact your administrator.',
        'scan_hint' => 'Scan the QR sticker on the machine, or pick it from the grid.',
    ],

    'issues' => [
        'title' => 'Issues',
        'issue' => 'Issue',
        'severity' => 'Severity',
        'raised_by' => 'Raised by',
        'assigned_to' => 'Assigned to',
        'resolved_at' => 'Resolved',
        'resolution_notes' => 'Resolution notes',
        'no_issues' => 'No issues found.',
        'open_breakdown_flag' => 'Open breakdown issue on this machine',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'coming_soon' => 'Dashboards arrive in a later milestone.',
    ],

    'validation' => [
        'pin_digits' => 'The PIN must be :min to :max digits.',
        'pin_confirmation' => 'The PIN confirmation does not match.',
        'employee_number_taken' => 'That employee number is already in use.',
        'part_code_taken' => 'That part code is already in use.',
        'machine_code_taken' => 'That machine code is already in use.',
        'monthly_day_range' => 'The day of month must be between 1 and 28.',
    ],

];
