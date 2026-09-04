<?php

return [
    'tab_label' => 'Admin Suggestions',
    'title' => 'New Church Admin Suggestions',
    'subtitle' => 'Members who typed a new (not yet registered) Church name while completing their profile — approve to create the church and make them its admin, or reject to keep them a plain personal account.',
    'none_pending' => 'No admin suggestions waiting for review.',
    'col_requester' => 'Submitted By',
    'col_church_name' => 'Proposed Church Name',
    'col_submitted_at' => 'Submitted On',
    'similar_warning' => 'May already be registered under a different name (:count) — click to view',
    'similar_disclaimer' => 'This is only an automatic system hint based on name similarity, not proof that this church is already registered. Verify it yourself before deciding.',
    'approve' => 'Approve',
    'reject' => 'Reject',
    'approve_confirm' => 'Approve ":name" as admin of Church ":church"? A new church will be created and they\'ll immediately be able to add social media accounts for it.',
    'reject_confirm' => 'Reject the admin suggestion from ":name"? They\'ll remain a regular personal account and the proposed church will not be created.',
    'approved' => 'Approved — ":name" is now admin of Church ":church".',
    'rejected' => 'Admin suggestion from ":name" rejected.',
    'status_pending' => 'Pending',
    'status_approved' => 'Approved',
    'status_rejected' => 'Rejected',

    // Email — sent only on approval, never on rejection.
    'mail_subject' => '[:app] You are now admin of Church ":church"',
    'mail_greeting' => 'Hi :name,',
    'mail_body' => 'Good news! Your suggestion to become admin of the following church has been approved and it was just created in the system:',
    'mail_next_steps' => 'You can now log in and start adding social media accounts for this church from your church page.',
    'mail_cta' => 'Log In',
];
