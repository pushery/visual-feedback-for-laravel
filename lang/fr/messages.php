<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Bug',
        'visual' => 'Problème visuel',
        'content' => 'Contenu',
        'feature' => 'Suggestion',
        'question' => 'Question',
        'other' => 'Autre',
    ],

    'widget' => [
        'heading' => 'Envoyer un retour',
        'category_label' => 'Catégorie',
        'message_label' => 'Ton message',
        'message_placeholder' => 'Que s’est-il passé ?',
        'submit' => 'Envoyer',
        'submitting' => 'Envoi…',
        'success' => 'Merci, ton retour a bien été envoyé.',
        'error' => 'Une erreur est survenue. Réessaie.',
        'disabled' => 'Le retour d’expérience est désactivé pour le moment. Réessaie plus tard.',
        'report_another' => 'En envoyer un autre',
        'name_label' => 'Ton nom',
        'email_label' => 'Ton e-mail',
        'phone_label' => 'Ton téléphone (facultatif)',
        'subject_label' => 'Objet',
        'privacy_acknowledge' => 'J’ai lu la politique de confidentialité.',
        'privacy_notice_link' => 'Lire la politique de confidentialité',
        'close' => 'Fermer',
        'honeypot_label' => 'Laisse ce champ vide',
        'attachments_label' => 'Pièces jointes',
        'attachment_limit' => '{1} Jusqu\'à :count fichier, :size chacun.|[2,*] Jusqu\'à :count fichiers, :size chacun.',
        'add_files' => 'Ajouter des fichiers',
        'remove_file' => 'Retirer :name',
        'capture_screenshot' => 'Capturer l’écran',
        'screenshot_native_hint' => 'Ton navigateur peut te demander de partager cet onglet pour une capture fidèle au pixel ; tu peux refuser, on la fera autrement.',
        'screenshot_capturing' => 'Capture en cours…',
        'screenshot_uploading' => 'Téléversement…',
        'screenshot_attached' => 'Capture jointe',
        'screenshot_preview' => 'Aperçu de la capture',
        'screenshot_attach' => 'Joindre',
        'screenshot_discard' => 'Ignorer',
        'screenshot_retake' => 'Reprendre',
        'screenshot_failed' => 'Échec de la capture — réessaie.',
    ],

    'attachments' => [
        'too_many' => '{1} Tu peux joindre au maximum :max fichier.|[2,*] Tu peux joindre au maximum :max fichiers.',
        'too_large' => 'Le fichier « :name » est trop volumineux (max. :max Mo).',
        'total_too_large' => 'Les pièces jointes dépassent la taille totale autorisée (:max Mo).',
        'size_unit' => 'Mo',
        'invalid_type' => 'Le fichier « :name » n’est pas d’un type autorisé.',
        'missing' => 'Le fichier « :name » est introuvable.',
        'image_too_large' => 'L’image « :name » dépasse les dimensions autorisées.',
        'screenshot_invalid' => 'La capture doit être une image PNG valide.',
        'screenshot_too_large' => 'La capture est trop volumineuse.',
    ],

    'mail' => [
        'subject' => 'Objet',
        'message' => 'Message',
        'report_id' => 'ID du rapport',
        'phone' => 'Téléphone',
        'reporter' => 'Signalé par',
        'reporter_name' => 'Nom',
        'reporter_type' => 'Type',
        'reporter_guest' => 'Invité',
        'reporter_member' => 'Membre connecté',
        'reporter_email' => 'E-mail',
        'submitted_at' => 'Envoyé',
        'mode' => 'Mode du widget',
        'context' => 'Contexte',
        'technical_details' => 'Détails techniques',
        'field' => 'Champ',
        'value' => 'Valeur',
        'attachments' => '{1} :count pièce jointe|[2,*] :count pièces jointes',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: aucune table de rapports — rien à purger.',
            'no_retention' => 'visual-feedback: retention.reports_days n’est pas défini — les rapports sont conservés indéfiniment.',
            'pruned' => '{0} visual-feedback: aucun rapport au-delà du délai de conservation.|{1} visual-feedback: :count rapport purgé avec ses pièces jointes.|[2,*] visual-feedback: :count rapports purgés avec leurs pièces jointes.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: aucune table de rapports — rien à effacer.',
            'erased' => '{0} visual-feedback: aucun rapport trouvé pour :email.|{1} visual-feedback: :count rapport effacé pour :email.|[2,*] visual-feedback: :count rapports effacés pour :email.',
            'mail_note' => 'Remarque : les copies d’e-mail déjà remises à la boîte de l’administrateur sont EN DEHORS de la conservation de ce package — les effacer relève de la responsabilité de l’hôte.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: aucune pièce jointe orpheline de plus de :minutes minutes.|{1} visual-feedback: :count pièce jointe orpheline de plus de :minutes minutes supprimée.|[2,*] visual-feedback: :count pièces jointes orphelines de plus de :minutes minutes supprimées.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Merci de confirmer que tu as lu la politique de confidentialité.',
        'required' => 'Il manque :attribute.',
        'in' => ':attribute doit être une des valeurs proposées.',
        'email' => ':attribute n\'est pas une adresse e-mail valide.',
        'max' => ':attribute ne peut pas dépasser :max caractères.',
        'string' => ':attribute doit être du texte.',
    ],

];
