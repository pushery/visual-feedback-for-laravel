<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Fehler',
        'visual' => 'Darstellung',
        'content' => 'Inhalt',
        'feature' => 'Wunsch',
        'question' => 'Frage',
        'other' => 'Sonstiges',
    ],

    'widget' => [
        'heading' => 'Feedback senden',
        'category_label' => 'Kategorie',
        'message_label' => 'Deine Nachricht',
        'message_placeholder' => 'Was ist passiert?',
        'submit' => 'Senden',
        'submitting' => 'Wird gesendet…',
        'success' => 'Danke — dein Feedback wurde gesendet.',
        'error' => 'Etwas ist schiefgelaufen. Bitte versuch es noch mal.',
        'disabled' => 'Feedback ist gerade abgeschaltet. Bitte versuch es später noch mal.',
        'report_another' => 'Weiteres Feedback senden',
        'name_label' => 'Dein Name',
        'email_label' => 'Deine E-Mail',
        'phone_label' => 'Deine Telefonnummer (optional)',
        'subject_label' => 'Betreff',
        'privacy_acknowledge' => 'Ich habe die Datenschutzhinweise gelesen.',
        'privacy_notice_link' => 'Datenschutzhinweise lesen',
        'close' => 'Schließen',
        'honeypot_label' => 'Dieses Feld leer lassen',
        'attachments_label' => 'Anhänge',
        'attachment_limit' => '{1} Bis zu :count Datei, je max. :size.|[2,*] Bis zu :count Dateien, je max. :size.',
        'add_files' => 'Dateien hinzufügen',
        'remove_file' => ':name entfernen',
        'capture_screenshot' => 'Screenshot aufnehmen',
        'screenshot_native_hint' => 'Dein Browser fragt dich eventuell, ob du diesen Tab für einen pixelgenauen Screenshot teilen möchtest; du kannst ablehnen, dann nehmen wir ihn anders auf.',
        'screenshot_capturing' => 'Aufnahme läuft…',
        'screenshot_uploading' => 'Wird hochgeladen…',
        'screenshot_attached' => 'Screenshot angehängt',
        'screenshot_preview' => 'Screenshot-Vorschau',
        'screenshot_attach' => 'Anhängen',
        'screenshot_discard' => 'Verwerfen',
        'screenshot_retake' => 'Neu aufnehmen',
        'screenshot_failed' => 'Aufnahme fehlgeschlagen — bitte versuch es noch mal.',
    ],

    'attachments' => [
        'too_many' => '{1} Du kannst höchstens :max Datei anhängen.|[2,*] Du kannst höchstens :max Dateien anhängen.',
        'too_large' => 'Die Datei „:name“ ist zu groß (max. :max MB).',
        'total_too_large' => 'Die Anhänge überschreiten die zulässige Gesamtgröße von :max MB.',
        'size_unit' => 'MB',
        'invalid_type' => 'Die Datei „:name“ hat einen nicht erlaubten Dateityp.',
        'missing' => 'Die Datei „:name“ wurde nicht gefunden.',
        'image_too_large' => 'Das Bild „:name“ überschreitet die erlaubten Abmessungen.',
        'screenshot_invalid' => 'Der Screenshot muss ein gültiges PNG-Bild sein.',
        'screenshot_too_large' => 'Der Screenshot ist zu groß.',
    ],

    'mail' => [
        'subject' => 'Betreff',
        'message' => 'Nachricht',
        'report_id' => 'Report-ID',
        'phone' => 'Telefon',
        'reporter' => 'Gemeldet von',
        'reporter_name' => 'Name',
        'reporter_type' => 'Art',
        'reporter_guest' => 'Gast',
        'reporter_member' => 'Angemeldetes Mitglied',
        'reporter_email' => 'E-Mail',
        'submitted_at' => 'Gesendet',
        'mode' => 'Widget-Modus',
        'context' => 'Kontext',
        'technical_details' => 'Technische Details',
        'field' => 'Feld',
        'value' => 'Wert',
        'attachments' => '{1} :count Anhang|[2,*] :count Anhänge',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: keine Reports-Tabelle — nichts zu bereinigen.',
            'no_retention' => 'visual-feedback: retention.reports_days ist nicht gesetzt — Reports werden unbegrenzt aufbewahrt.',
            'pruned' => '{0} visual-feedback: keine Reports jenseits der Aufbewahrungsfrist.|{1} visual-feedback: :count Report samt Anhängen gelöscht.|[2,*] visual-feedback: :count Reports samt Anhängen gelöscht.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: keine Reports-Tabelle — nichts zu löschen.',
            'erased' => '{0} visual-feedback: keine Reports für :email gefunden.|{1} visual-feedback: :count Report für :email gelöscht.|[2,*] visual-feedback: :count Reports für :email gelöscht.',
            'mail_note' => 'Hinweis: Bereits ans Admin-Postfach zugestellte Mail-Kopien liegen AUSSERHALB der Aufbewahrung dieses Pakets — sie zu löschen ist Sache des Hosts.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: keine verwaisten Anhänge älter als :minutes Minuten.|{1} visual-feedback: :count verwaisten Anhang älter als :minutes Minuten entfernt.|[2,*] visual-feedback: :count verwaiste Anhänge älter als :minutes Minuten entfernt.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Bitte bestätige, dass du die Datenschutzhinweise gelesen hast.',
        'required' => ':attribute fehlt noch.',
        'in' => ':attribute muss einer der angebotenen Werte sein.',
        'email' => ':attribute ist keine gültige E-Mail-Adresse.',
        'max' => ':attribute darf höchstens :max Zeichen lang sein.',
        'string' => ':attribute muss ein Text sein.',
    ],

];
