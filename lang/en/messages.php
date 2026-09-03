<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Bug',
        'visual' => 'Visual issue',
        'content' => 'Content',
        'feature' => 'Feature request',
        'question' => 'Question',
        'other' => 'Other',
    ],

    'widget' => [
        'heading' => 'Send feedback',
        'category_label' => 'Category',
        'message_label' => 'Your message',
        'message_placeholder' => 'What happened?',
        'submit' => 'Send',
        'submitting' => 'Sending…',
        'success' => 'Thanks — your feedback was sent.',
        'error' => 'Something went wrong. Please try again.',
        'disabled' => 'Feedback is switched off right now. Please try again later.',
        'report_another' => 'Send another',
        'name_label' => 'Your name',
        'email_label' => 'Your email',
        'phone_label' => 'Your phone (optional)',
        'subject_label' => 'Subject',
        'privacy_acknowledge' => 'I have read the privacy notice.',
        'privacy_notice_link' => 'Read the privacy notice',
        'close' => 'Close',
        'honeypot_label' => 'Leave this field empty',
        'attachments_label' => 'Attachments',
        'attachment_limit' => '{1} Up to :count file, :size each.|[2,*] Up to :count files, :size each.',
        'add_files' => 'Add files',
        'remove_file' => 'Remove :name',
        'capture_screenshot' => 'Capture screenshot',
        'screenshot_native_hint' => 'Your browser may ask to share this tab for a pixel-exact screenshot; you can decline and we will capture it another way.',
        'screenshot_capturing' => 'Capturing…',
        'screenshot_uploading' => 'Uploading…',
        'screenshot_attached' => 'Screenshot attached',
        'screenshot_preview' => 'Screenshot preview',
        'screenshot_attach' => 'Attach',
        'screenshot_discard' => 'Discard',
        'screenshot_retake' => 'Retake',
        'screenshot_failed' => 'Capture failed — please try again.',
    ],

    'attachments' => [
        'too_many' => '{1} You can attach at most :max file.|[2,*] You can attach at most :max files.',
        'too_large' => 'The file ":name" is too large (max :max MB).',
        'total_too_large' => 'The attachments exceed the total size limit (:max MB).',
        'size_unit' => 'MB',
        'invalid_type' => 'The file ":name" is not an allowed file type.',
        'missing' => 'The file ":name" could not be found.',
        'image_too_large' => 'The image ":name" exceeds the allowed dimensions.',
        'screenshot_invalid' => 'The screenshot must be a valid PNG image.',
        'screenshot_too_large' => 'The screenshot is too large.',
    ],

    'mail' => [
        'subject' => 'Subject',
        'message' => 'Message',
        'report_id' => 'Report ID',
        'phone' => 'Phone',
        'context' => 'Context',
        'technical_details' => 'Technical details',
        'field' => 'Field',
        'value' => 'Value',
        'attachments' => '{1} :count attachment|[2,*] :count attachments',
    ],

    // Artisan command output. Localized like the widget so a package that ships
    // seven locales speaks one language end to end; every countable line is a `trans_choice`, so
    // the lazy "report(s)" plural never reaches an operator. `visual-feedback:` and the config
    // keys are technical tokens and stay verbatim in every locale.
    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: no reports table — nothing to prune.',
            'no_retention' => 'visual-feedback: retention.reports_days is not set — reports are kept forever.',
            'pruned' => '{0} visual-feedback: no reports past the retention cutoff.|{1} visual-feedback: pruned :count report and its attachments.|[2,*] visual-feedback: pruned :count reports and their attachments.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: no reports table — nothing to erase.',
            'erased' => '{0} visual-feedback: no reports found for :email.|{1} visual-feedback: erased :count report for :email.|[2,*] visual-feedback: erased :count reports for :email.',
            'mail_note' => 'Note: mail copies already delivered to the admin inbox are OUTSIDE this package retention — erasing those is the host responsibility.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: no orphaned attachments older than :minutes minutes.|{1} visual-feedback: swept :count orphaned attachment older than :minutes minutes.|[2,*] visual-feedback: swept :count orphaned attachments older than :minutes minutes.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Please confirm that you have read the privacy notice.',
        'required' => ':attribute is still missing.',
        'in' => ':attribute has to be one of the offered values.',
        'email' => ':attribute is not a valid email address.',
        'max' => ':attribute may not be longer than :max characters.',
        'string' => ':attribute has to be text.',
    ],

];
