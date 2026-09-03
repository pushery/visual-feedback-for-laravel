<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Bug',
        'visual' => 'Problema visivo',
        'content' => 'Contenuto',
        'feature' => 'Suggerimento',
        'question' => 'Domanda',
        'other' => 'Altro',
    ],

    'widget' => [
        'heading' => 'Invia un feedback',
        'category_label' => 'Categoria',
        'message_label' => 'Il tuo messaggio',
        'message_placeholder' => 'Cosa è successo?',
        'submit' => 'Invia',
        'submitting' => 'Invio…',
        'success' => 'Grazie, il tuo feedback è stato inviato.',
        'error' => 'Qualcosa è andato storto. Riprova.',
        'disabled' => 'Il feedback è disattivato al momento. Riprova più tardi.',
        'report_another' => 'Inviane un altro',
        'name_label' => 'Il tuo nome',
        'email_label' => 'La tua email',
        'phone_label' => 'Il tuo telefono (opzionale)',
        'subject_label' => 'Oggetto',
        'privacy_acknowledge' => 'Ho letto l’informativa sulla privacy.',
        'privacy_notice_link' => 'Leggi l’informativa sulla privacy',
        'close' => 'Chiudi',
        'honeypot_label' => 'Lascia vuoto questo campo',
        'attachments_label' => 'Allegati',
        'attachment_limit' => '{1} Fino a :count file, :size ciascuno.|[2,*] Fino a :count file, :size ciascuno.',
        'add_files' => 'Aggiungi file',
        'remove_file' => 'Rimuovi :name',
        'capture_screenshot' => 'Cattura schermata',
        'screenshot_native_hint' => 'Il browser potrebbe chiedere di condividere questa scheda per una cattura fedele al pixel; puoi rifiutare e la acquisiremo in altro modo.',
        'screenshot_capturing' => 'Cattura in corso…',
        'screenshot_uploading' => 'Caricamento…',
        'screenshot_attached' => 'Schermata allegata',
        'screenshot_preview' => 'Anteprima della schermata',
        'screenshot_attach' => 'Allega',
        'screenshot_discard' => 'Scarta',
        'screenshot_retake' => 'Ripeti cattura',
        'screenshot_failed' => 'Cattura non riuscita — riprova.',
    ],

    'attachments' => [
        'too_many' => '{1} Puoi allegare al massimo :max file.|[2,*] Puoi allegare al massimo :max file.',
        'too_large' => 'Il file «:name» è troppo grande (max :max MB).',
        'total_too_large' => 'Gli allegati superano la dimensione totale consentita (:max MB).',
        'size_unit' => 'MB',
        'invalid_type' => 'Il file «:name» non è un tipo di file consentito.',
        'missing' => 'Impossibile trovare il file «:name».',
        'image_too_large' => 'L’immagine «:name» supera le dimensioni consentite.',
        'screenshot_invalid' => 'Lo screenshot deve essere un’immagine PNG valida.',
        'screenshot_too_large' => 'Lo screenshot è troppo grande.',
    ],

    'mail' => [
        'subject' => 'Oggetto',
        'message' => 'Messaggio',
        'report_id' => 'ID segnalazione',
        'phone' => 'Telefono',
        'reporter' => 'Segnalato da',
        'reporter_name' => 'Nome',
        'reporter_type' => 'Tipo',
        'reporter_guest' => 'Ospite',
        'reporter_member' => 'Membro autenticato',
        'reporter_email' => 'E-mail',
        'submitted_at' => 'Inviato',
        'mode' => 'Modalità del widget',
        'context' => 'Contesto',
        'technical_details' => 'Dettagli tecnici',
        'field' => 'Campo',
        'value' => 'Valore',
        'attachments' => '{1} :count allegato|[2,*] :count allegati',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: nessuna tabella delle segnalazioni — niente da eliminare.',
            'no_retention' => 'visual-feedback: retention.reports_days non è impostato — le segnalazioni vengono conservate a tempo indeterminato.',
            'pruned' => '{0} visual-feedback: nessuna segnalazione oltre il periodo di conservazione.|{1} visual-feedback: :count segnalazione eliminata insieme ai suoi allegati.|[2,*] visual-feedback: :count segnalazioni eliminate insieme ai loro allegati.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: nessuna tabella delle segnalazioni — niente da cancellare.',
            'erased' => '{0} visual-feedback: nessuna segnalazione trovata per :email.|{1} visual-feedback: :count segnalazione cancellata per :email.|[2,*] visual-feedback: :count segnalazioni cancellate per :email.',
            'mail_note' => 'Nota: le copie email già recapitate alla casella dell’amministratore sono AL DI FUORI della conservazione di questo pacchetto — cancellarle è responsabilità dell’host.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: nessun allegato orfano più vecchio di :minutes minuti.|{1} visual-feedback: :count allegato orfano più vecchio di :minutes minuti rimosso.|[2,*] visual-feedback: :count allegati orfani più vecchi di :minutes minuti rimossi.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Conferma di aver letto l\'informativa sulla privacy.',
        'required' => 'Manca :attribute.',
        'in' => ':attribute deve essere uno dei valori proposti.',
        'email' => ':attribute non è un indirizzo e-mail valido.',
        'max' => ':attribute non può superare :max caratteri.',
        'string' => ':attribute deve essere testo.',
    ],

];
