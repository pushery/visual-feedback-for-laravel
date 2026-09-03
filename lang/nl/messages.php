<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Fout',
        'visual' => 'Weergaveprobleem',
        'content' => 'Inhoud',
        'feature' => 'Wens',
        'question' => 'Vraag',
        'other' => 'Overig',
    ],

    'widget' => [
        'heading' => 'Feedback sturen',
        'category_label' => 'Categorie',
        'message_label' => 'Je bericht',
        'message_placeholder' => 'Wat is er gebeurd?',
        'submit' => 'Versturen',
        'submitting' => 'Bezig met versturen…',
        'success' => 'Bedankt, je feedback is verstuurd.',
        'error' => 'Er ging iets mis. Probeer het opnieuw.',
        'disabled' => 'Feedback staat nu uit. Probeer het later opnieuw.',
        'report_another' => 'Nog een sturen',
        'name_label' => 'Je naam',
        'email_label' => 'Je e-mailadres',
        'phone_label' => 'Je telefoonnummer (optioneel)',
        'subject_label' => 'Onderwerp',
        'privacy_acknowledge' => 'Ik heb de privacyverklaring gelezen.',
        'privacy_notice_link' => 'Privacyverklaring lezen',
        'close' => 'Sluiten',
        'honeypot_label' => 'Laat dit veld leeg',
        'attachments_label' => 'Bijlagen',
        'attachment_limit' => '{1} Maximaal :count bestand, :size per stuk.|[2,*] Maximaal :count bestanden, :size per stuk.',
        'add_files' => 'Bestanden toevoegen',
        'remove_file' => ':name verwijderen',
        'capture_screenshot' => 'Schermafbeelding maken',
        'screenshot_native_hint' => 'Je browser vraagt mogelijk om dit tabblad te delen voor een pixelnauwkeurige schermafbeelding; je kunt weigeren, dan maken we hem op een andere manier.',
        'screenshot_capturing' => 'Bezig met vastleggen…',
        'screenshot_uploading' => 'Bezig met uploaden…',
        'screenshot_attached' => 'Schermafbeelding toegevoegd',
        'screenshot_preview' => 'Voorbeeld van schermafbeelding',
        'screenshot_attach' => 'Toevoegen',
        'screenshot_discard' => 'Weggooien',
        'screenshot_retake' => 'Opnieuw maken',
        'screenshot_failed' => 'Vastleggen mislukt — probeer opnieuw.',
    ],

    'attachments' => [
        'too_many' => '{1} Je kunt maximaal :max bestand toevoegen.|[2,*] Je kunt maximaal :max bestanden toevoegen.',
        'too_large' => 'Het bestand ":name" is te groot (max. :max MB).',
        'total_too_large' => 'De bijlagen overschrijden de totale limiet (:max MB).',
        'size_unit' => 'MB',
        'invalid_type' => 'Het bestand ":name" is geen toegestaan bestandstype.',
        'missing' => 'Het bestand ":name" kon niet worden gevonden.',
        'image_too_large' => 'De afbeelding ":name" overschrijdt de toegestane afmetingen.',
        'screenshot_invalid' => 'De schermafbeelding moet een geldige PNG-afbeelding zijn.',
        'screenshot_too_large' => 'De schermafbeelding is te groot.',
    ],

    'mail' => [
        'subject' => 'Onderwerp',
        'message' => 'Bericht',
        'report_id' => 'Rapport-ID',
        'phone' => 'Telefoon',
        'reporter' => 'Gemeld door',
        'reporter_name' => 'Naam',
        'reporter_type' => 'Type',
        'reporter_guest' => 'Gast',
        'reporter_member' => 'Ingelogd lid',
        'reporter_email' => 'E-mail',
        'submitted_at' => 'Verzonden',
        'mode' => 'Widgetmodus',
        'context' => 'Context',
        'technical_details' => 'Technische details',
        'field' => 'Veld',
        'value' => 'Waarde',
        'attachments' => '{1} :count bijlage|[2,*] :count bijlagen',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: geen rapporttabel — niets om op te schonen.',
            'no_retention' => 'visual-feedback: retention.reports_days is niet ingesteld — rapporten worden onbeperkt bewaard.',
            'pruned' => '{0} visual-feedback: geen rapporten ouder dan de bewaartermijn.|{1} visual-feedback: :count rapport en de bijlagen verwijderd.|[2,*] visual-feedback: :count rapporten en hun bijlagen verwijderd.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: geen rapporttabel — niets om te wissen.',
            'erased' => '{0} visual-feedback: geen rapporten gevonden voor :email.|{1} visual-feedback: :count rapport voor :email gewist.|[2,*] visual-feedback: :count rapporten voor :email gewist.',
            'mail_note' => 'Let op: e-mailkopieën die al aan de beheerdersinbox zijn afgeleverd, vallen BUITEN het bewaarbeleid van dit pakket — het wissen daarvan is de verantwoordelijkheid van de host.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: geen verweesde bijlagen ouder dan :minutes minuten.|{1} visual-feedback: :count verweesde bijlage ouder dan :minutes minuten verwijderd.|[2,*] visual-feedback: :count verweesde bijlagen ouder dan :minutes minuten verwijderd.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Bevestig dat je de privacyverklaring hebt gelezen.',
        'required' => ':attribute ontbreekt nog.',
        'in' => ':attribute moet een van de aangeboden waarden zijn.',
        'email' => ':attribute is geen geldig e-mailadres.',
        'max' => ':attribute mag niet langer zijn dan :max tekens.',
        'string' => ':attribute moet tekst zijn.',
    ],

];
