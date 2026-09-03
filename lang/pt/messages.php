<?php

declare(strict_types=1);

return [

    'categories' => [
        'bug' => 'Erro',
        'visual' => 'Problema visual',
        'content' => 'Conteúdo',
        'feature' => 'Sugestão',
        'question' => 'Pergunta',
        'other' => 'Outro',
    ],

    'widget' => [
        'heading' => 'Enviar comentários',
        'category_label' => 'Categoria',
        'message_label' => 'A tua mensagem',
        'message_placeholder' => 'O que aconteceu?',
        'submit' => 'Enviar',
        'submitting' => 'A enviar…',
        'success' => 'Obrigado, os teus comentários foram enviados.',
        'error' => 'Algo correu mal. Tenta novamente.',
        'disabled' => 'Os comentários estão desligados neste momento. Tenta mais tarde.',
        'report_another' => 'Enviar outro',
        'name_label' => 'O teu nome',
        'email_label' => 'O teu email',
        'phone_label' => 'O teu telefone (opcional)',
        'subject_label' => 'Assunto',
        'privacy_acknowledge' => 'Li o aviso de privacidade.',
        'privacy_notice_link' => 'Ler o aviso de privacidade',
        'close' => 'Fechar',
        'honeypot_label' => 'Deixa este campo vazio',
        'attachments_label' => 'Anexos',
        'attachment_limit' => '{1} Até :count ficheiro, :size cada.|[2,*] Até :count ficheiros, :size cada.',
        'add_files' => 'Adicionar ficheiros',
        'remove_file' => 'Remover :name',
        'capture_screenshot' => 'Capturar ecrã',
        'screenshot_native_hint' => 'O teu navegador pode pedir para partilhar este separador para uma captura exata ao pixel; podes recusar e faremos a captura de outra forma.',
        'screenshot_capturing' => 'A capturar…',
        'screenshot_uploading' => 'A enviar…',
        'screenshot_attached' => 'Captura anexada',
        'screenshot_preview' => 'Pré-visualização da captura',
        'screenshot_attach' => 'Anexar',
        'screenshot_discard' => 'Descartar',
        'screenshot_retake' => 'Repetir captura',
        'screenshot_failed' => 'Falha na captura — tenta novamente.',
    ],

    'attachments' => [
        'too_many' => '{1} Podes anexar no máximo :max ficheiro.|[2,*] Podes anexar no máximo :max ficheiros.',
        'too_large' => 'O ficheiro «:name» é demasiado grande (máx. :max MB).',
        'total_too_large' => 'Os anexos excedem o limite de tamanho total (:max MB).',
        'size_unit' => 'MB',
        'invalid_type' => 'O ficheiro «:name» não é um tipo de ficheiro permitido.',
        'missing' => 'Não foi possível encontrar o ficheiro «:name».',
        'image_too_large' => 'A imagem «:name» excede as dimensões permitidas.',
        'screenshot_invalid' => 'A captura de ecrã tem de ser uma imagem PNG válida.',
        'screenshot_too_large' => 'A captura de ecrã é demasiado grande.',
    ],

    'mail' => [
        'subject' => 'Assunto',
        'message' => 'Mensagem',
        'report_id' => 'ID do relatório',
        'phone' => 'Telefone',
        'reporter' => 'Relatado por',
        'reporter_name' => 'Nome',
        'reporter_type' => 'Tipo',
        'reporter_guest' => 'Convidado',
        'reporter_member' => 'Membro autenticado',
        'reporter_email' => 'E-mail',
        'submitted_at' => 'Enviado',
        'mode' => 'Modo do widget',
        'context' => 'Contexto',
        'technical_details' => 'Detalhes técnicos',
        'field' => 'Campo',
        'value' => 'Valor',
        'attachments' => '{1} :count anexo|[2,*] :count anexos',
    ],

    'console' => [
        'prune' => [
            'no_table' => 'visual-feedback: sem tabela de relatórios — nada a eliminar.',
            'no_retention' => 'visual-feedback: retention.reports_days não está definido — os relatórios são mantidos indefinidamente.',
            'pruned' => '{0} visual-feedback: sem relatórios além do período de retenção.|{1} visual-feedback: :count relatório eliminado juntamente com os anexos.|[2,*] visual-feedback: :count relatórios eliminados juntamente com os anexos.',
        ],
        'forget' => [
            'no_table' => 'visual-feedback: sem tabela de relatórios — nada a apagar.',
            'erased' => '{0} visual-feedback: nenhum relatório encontrado para :email.|{1} visual-feedback: :count relatório de :email apagado.|[2,*] visual-feedback: :count relatórios de :email apagados.',
            'mail_note' => 'Nota: as cópias de email já entregues na caixa de entrada do administrador estão FORA da retenção deste pacote — apagá-las é da responsabilidade do host.',
        ],
        'sweep' => [
            'swept' => '{0} visual-feedback: sem anexos órfãos com mais de :minutes minutos.|{1} visual-feedback: :count anexo órfão com mais de :minutes minutos removido.|[2,*] visual-feedback: :count anexos órfãos com mais de :minutes minutos removidos.',
        ],
    ],

    'validation' => [
        'privacy_required' => 'Confirma que leste o aviso de privacidade.',
        'required' => 'Falta :attribute.',
        'in' => ':attribute tem de ser um dos valores oferecidos.',
        'email' => ':attribute não é um endereço de e-mail válido.',
        'max' => ':attribute não pode ter mais de :max caracteres.',
        'string' => ':attribute tem de ser texto.',
    ],

];
