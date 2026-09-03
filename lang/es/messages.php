<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Error',
        'visual' => 'Problema visual',
        'content' => 'Contenido',
        'feature' => 'Sugerencia',
        'question' => 'Pregunta',
        'other' => 'Otro',
    ],

    'widget' => [
        'heading' => 'Enviar comentarios',
        'category_label' => 'Categoría',
        'message_label' => 'Tu mensaje',
        'message_placeholder' => '¿Qué ha pasado?',
        'submit' => 'Enviar',
        'submitting' => 'Enviando…',
        'success' => 'Gracias, hemos recibido tus comentarios.',
        'error' => 'Algo ha salido mal. Inténtalo de nuevo.',
        'disabled' => 'Los comentarios están desactivados ahora mismo. Inténtalo más tarde.',
        'report_another' => 'Enviar otro',
        'name_label' => 'Tu nombre',
        'email_label' => 'Tu correo electrónico',
        'phone_label' => 'Tu teléfono (opcional)',
        'subject_label' => 'Asunto',
        'privacy_acknowledge' => 'He leído el aviso de privacidad.',
        'privacy_notice_link' => 'Leer el aviso de privacidad',
        'close' => 'Cerrar',
        'honeypot_label' => 'Deja este campo vacío',
        'attachments_label' => 'Adjuntos',
        'attachment_limit' => '{1} Hasta :count archivo, :size cada uno.|[2,*] Hasta :count archivos, :size cada uno.',
        'add_files' => 'Añadir archivos',
        'remove_file' => 'Quitar :name',
        'capture_screenshot' => 'Capturar pantalla',
        'screenshot_native_hint' => 'Puede que tu navegador pida compartir esta pestaña para una captura exacta; puedes rechazarlo y la haremos de otra forma.',
        'screenshot_capturing' => 'Capturando…',
        'screenshot_uploading' => 'Subiendo…',
        'screenshot_attached' => 'Captura adjunta',
        'screenshot_preview' => 'Vista previa de la captura',
        'screenshot_attach' => 'Adjuntar',
        'screenshot_discard' => 'Descartar',
        'screenshot_retake' => 'Volver a capturar',
        'screenshot_failed' => 'Error de captura — inténtalo de nuevo.',
    ],

    'attachments' => [
        'too_many' => '{1} Puedes adjuntar como máximo :max archivo.|[2,*] Puedes adjuntar como máximo :max archivos.',
        'too_large' => 'El archivo «:name» es demasiado grande (máx. :max MB).',
        'total_too_large' => 'Los archivos adjuntos superan el límite de tamaño total (:max MB).',
        'size_unit' => 'MB',
        'invalid_type' => 'El archivo «:name» no es un tipo de archivo permitido.',
        'missing' => 'No se ha encontrado el archivo «:name».',
        'image_too_large' => 'La imagen «:name» supera las dimensiones permitidas.',
        'screenshot_invalid' => 'La captura debe ser una imagen PNG válida.',
        'screenshot_too_large' => 'La captura es demasiado grande.',
    ],

    'mail' => [
        'subject' => 'Asunto',
        'message' => 'Mensaje',
        'report_id' => 'ID del informe',
        'phone' => 'Teléfono',
        'reporter' => 'Informado por',
        'reporter_name' => 'Nombre',
        'reporter_type' => 'Tipo',
        'reporter_guest' => 'Invitado',
        'reporter_member' => 'Miembro con sesión iniciada',
        'reporter_email' => 'Correo electrónico',
        'submitted_at' => 'Enviado',
        'mode' => 'Modo del widget',
        'context' => 'Contexto',
        'technical_details' => 'Detalles técnicos',
        'field' => 'Campo',
        'value' => 'Valor',
        'attachments' => '{1} :count adjunto|[2,*] :count adjuntos',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: no hay tabla de informes — nada que purgar.',
            'no_retention' => 'visual-feedback: retention.reports_days no está definido — los informes se conservan indefinidamente.',
            'pruned' => '{0} visual-feedback: no hay informes anteriores al límite de retención.|{1} visual-feedback: :count informe purgado junto con sus adjuntos.|[2,*] visual-feedback: :count informes purgados junto con sus adjuntos.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: no hay tabla de informes — nada que borrar.',
            'erased' => '{0} visual-feedback: no se han encontrado informes de :email.|{1} visual-feedback: :count informe de :email borrado.|[2,*] visual-feedback: :count informes de :email borrados.',
            'mail_note' => 'Nota: las copias de correo ya entregadas en la bandeja del administrador están FUERA de la retención de este paquete — borrarlas es responsabilidad del host.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: no hay adjuntos huérfanos de más de :minutes minutos.|{1} visual-feedback: :count adjunto huérfano de más de :minutes minutos eliminado.|[2,*] visual-feedback: :count adjuntos huérfanos de más de :minutes minutos eliminados.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Confirma que has leído el aviso de privacidad.',
        'required' => 'Falta :attribute.',
        'in' => ':attribute tiene que ser uno de los valores ofrecidos.',
        'email' => ':attribute no es una dirección de correo válida.',
        'max' => ':attribute no puede tener más de :max caracteres.',
        'string' => ':attribute tiene que ser texto.',
    ],

];
